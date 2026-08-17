<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Models\BillingCoupon;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\PlatformAccess\Enums\PlatformCapability;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\PlatformAccountNote;
use App\Domain\PlatformAccess\Models\PlatformFeatureFlag;
use App\Domain\PlatformAccess\Models\PlatformNotice;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use App\Domain\PlatformAccess\Services\PlatformAuthorizer;
use App\Domain\PlatformAccess\Services\PlatformBusinessLifecycleService;
use App\Domain\PlatformAccess\Services\PlatformFailureRecoveryService;
use App\Domain\PlatformAccess\Services\PlatformFeatureFlagService;
use App\Domain\PlatformAccess\Services\PlatformHealthService;
use App\Domain\PlatformAccess\Services\PlatformInvitationService;
use App\Domain\PlatformAccess\Services\PlatformTenantQuery;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOperationsController extends Controller
{
    public function __construct(private readonly PlatformAuthorizer $authorization) {}

    public function overview(Request $request, PlatformHealthService $health): Response
    {
        return Inertia::render('Platform/Overview', ['capabilities' => $this->authorization->capabilities($request->user()), 'health' => $health->summary()]);
    }

    public function businesses(Request $request, PlatformTenantQuery $tenants): JsonResponse|Response
    {
        $this->allow($request, PlatformCapability::TenantView);
        $data = ['businesses' => $tenants->search($request->string('search')->toString()), 'search' => $request->string('search')->toString()];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/Businesses', $data);
    }

    public function business(Request $request, Business $business, PlatformTenantQuery $tenants): JsonResponse
    {
        $this->allow($request, PlatformCapability::TenantView);

        return response()->json(['business' => $tenants->summary($business)]);
    }

    public function status(Request $request, Business $business, PlatformBusinessLifecycleService $lifecycle): JsonResponse
    {
        $this->allow($request, PlatformCapability::TenantLifecycle);
        $data = $request->validate(['status' => ['required', Rule::enum(BusinessStatus::class)], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return response()->json(['business' => $lifecycle->changeStatus($business, BusinessStatus::from($data['status']), $request->user(), $data['reason'])]);
    }

    public function extendTrial(Request $request, Business $business, PlatformBusinessLifecycleService $lifecycle): JsonResponse
    {
        $this->allow($request, PlatformCapability::BillingManage);
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:30'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return response()->json(['subscription' => $lifecycle->extendTrial($business->subscription()->firstOrFail(), $data['days'], $request->user(), $data['reason'])]);
    }

    public function changePlan(Request $request, Business $business, PlatformBusinessLifecycleService $lifecycle): JsonResponse
    {
        $this->allow($request, PlatformCapability::BillingManage);
        $data = $request->validate(['price_id' => ['required', 'integer'], 'at_period_end' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $subscription = $business->subscription()->firstOrFail();
        $price = BillingPlanPrice::query()->where('is_active', true)->findOrFail($data['price_id']);
        $atPeriodEnd = app(SubscriptionLifecycleManager::class)->requiresPeriodEnd($subscription, $price, $data['at_period_end']);
        if ($subscription->provider_subscription_id) {
            app(SubscriptionProvider::class)->changePrice($subscription, $price, $atPeriodEnd);
        }

        return response()->json(['change' => $lifecycle->changePlan($subscription, $price, $request->user(), $data['reason'], $atPeriodEnd)]);
    }

    public function applyCoupon(Request $request, Business $business, AuditWriter $audit): JsonResponse
    {
        $this->allow($request, PlatformCapability::BillingManage);
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:64'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $coupon = BillingCoupon::query()->where('code', Str::upper($data['coupon_code']))->firstOrFail();
        abort_unless($coupon->isRedeemable(), 422, 'Coupon is not redeemable.');
        DB::table('platform_coupon_assignments')->upsert([[
            'business_id' => $business->id, 'billing_coupon_id' => $coupon->id, 'assigned_by_user_id' => $request->user()->id,
            'reason' => $data['reason'], 'status' => 'pending_provider_confirmation', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]], ['business_id', 'billing_coupon_id'], ['assigned_by_user_id', 'reason', 'status', 'assigned_at', 'updated_at']);
        $audit->write('platform.subscription.coupon_assigned', $business, $request->user(), $coupon, $data['reason'], after: ['coupon_code' => $coupon->code, 'status' => 'pending_provider_confirmation'], source: 'platform');

        return response()->json(['coupon' => $coupon->only(['public_id', 'code', 'discount_type', 'discount_value']), 'status' => 'pending_provider_confirmation']);
    }

    public function initiateExport(Request $request, Business $business, AuditWriter $audit): JsonResponse
    {
        $this->allow($request, PlatformCapability::ExportInitiate);
        if ($request->has('business_ids')) {
            DB::table('platform_alerts')->insert([
                'public_id' => (string) Str::ulid(), 'operator_user_id' => $request->user()->id, 'business_id' => $business->id,
                'kind' => 'restricted_bulk_export_attempt', 'severity' => 'critical', 'summary' => 'A platform operator attempted a bulk or cross-tenant export.',
                'evidence' => json_encode(['requested_count' => is_array($request->input('business_ids')) ? count($request->input('business_ids')) : 1], JSON_THROW_ON_ERROR),
                'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $data = $request->validate(['export_type' => ['required', 'in:business_archive,financial_archive'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'business_ids' => ['prohibited']]);
        $row = DB::table('platform_export_requests')->insertGetId([
            'public_id' => (string) Str::ulid(), 'business_id' => $business->id, 'requested_by_user_id' => $request->user()->id,
            'export_type' => $data['export_type'], 'reason' => $data['reason'], 'status' => 'queued',
            'scope_snapshot' => json_encode(['business_id' => $business->id, 'business_public_id' => $business->public_id], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->write('platform.export.initiated', $business, $request->user(), reason: $data['reason'], after: ['request_id' => $row, 'export_type' => $data['export_type'], 'single_business' => true], source: 'platform');

        return response()->json(['request_id' => $row, 'status' => 'queued'], 202);
    }

    public function resendVerification(Request $request, Business $business, AuditWriter $audit, PlatformTenantQuery $tenants): JsonResponse
    {
        $this->allow($request, PlatformCapability::TenantLifecycle);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $owner = $tenants->owner($business);
        abort_unless($owner, 404);
        abort_if($owner->hasVerifiedEmail(), 422, 'The owner email is already verified.');
        $owner->sendEmailVerificationNotification();
        $audit->write('platform.owner_verification.resent', $business, $request->user(), $owner, $data['reason'], after: ['owner_user_id' => $owner->id], source: 'platform');

        return response()->json(['sent' => true]);
    }

    public function resendInvitation(Request $request, Business $business, StaffInvitation $staffInvitation, PlatformInvitationService $invitations): JsonResponse
    {
        $this->allow($request, PlatformCapability::TenantLifecycle);
        abort_unless($staffInvitation->business_id === $business->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return response()->json(['invitation' => $invitations->resend($staffInvitation, $request->user(), $data['reason'])->only(['public_id', 'email', 'expires_at'])]);
    }

    public function note(Request $request, Business $business, AuditWriter $audit): JsonResponse
    {
        $this->allow($request, PlatformCapability::NotesManage);
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:4000'], 'retain_until' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addYears(2)->toDateString()]]);
        $note = PlatformAccountNote::query()->create(['business_id' => $business->id, 'author_user_id' => $request->user()->id, 'body' => $data['body'], 'visibility' => 'platform_internal', 'retain_until' => $data['retain_until'] ?? now()->addYears(2)]);
        $audit->write('platform.account_note.added', $business, $request->user(), $note, 'Internal account note added.', after: ['visibility' => 'platform_internal', 'retain_until' => $note->retain_until?->toIso8601String()], source: 'platform');

        return response()->json(['note' => $note->only(['public_id', 'body', 'visibility', 'retain_until'])], 201);
    }

    public function failures(Request $request, PlatformFailureRecoveryService $failures): JsonResponse|Response
    {
        $this->allow($request, PlatformCapability::FailureView);
        $business = $request->filled('business_id') ? Business::query()->where('public_id', $request->string('business_id'))->firstOrFail() : null;
        $data = ['failures' => $failures->list($business?->id), 'businessId' => $business?->public_id];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/Failures', $data);
    }

    public function replay(Request $request, string $type, int $id, PlatformFailureRecoveryService $failures): JsonResponse
    {
        $this->allow($request, PlatformCapability::FailureReplay);
        $data = $request->validate(['operation_key' => ['required', 'string', 'min:8', 'max:100'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return response()->json(['replay' => $failures->replay($type, $id, $data['operation_key'], $request->user(), $data['reason'])]);
    }

    public function health(Request $request, PlatformHealthService $health): JsonResponse|Response
    {
        $this->allow($request, PlatformCapability::HealthView);
        $data = ['health' => $health->summary()];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/Health', $data);
    }

    public function flags(Request $request): JsonResponse|Response
    {
        $this->allow($request, PlatformCapability::FeatureFlagManage);
        $data = ['flags' => PlatformFeatureFlag::query()->latest()->get()->map->only(['public_id', 'key', 'scope_type', 'scope_id', 'enabled', 'description', 'reason'])];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/FeatureFlags', $data);
    }

    public function setFlag(Request $request, PlatformFeatureFlagService $flags): JsonResponse
    {
        $this->allow($request, PlatformCapability::FeatureFlagManage);
        $data = $request->validate(['key' => ['required', 'string', 'max:96'], 'enabled' => ['required', 'boolean'], 'description' => ['required', 'string', 'max:1000'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'business_id' => ['nullable', 'string']]);
        $business = filled($data['business_id'] ?? null) ? Business::query()->where('public_id', $data['business_id'])->firstOrFail() : null;

        return response()->json(['flag' => $flags->set($data['key'], $data['enabled'], $data['description'], $data['reason'], $request->user(), $business)]);
    }

    public function publishNotice(Request $request, AuditWriter $audit): JsonResponse
    {
        $this->allow($request, PlatformCapability::NoticeManage);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:2000'], 'severity' => ['required', 'in:info,warning,critical'], 'audience' => ['required', 'in:all_businesses,single_business'], 'business_id' => ['nullable', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $business = $data['audience'] === 'single_business' ? Business::query()->where('public_id', $data['business_id'] ?? '')->firstOrFail() : null;
        $notice = PlatformNotice::query()->create([...$data, 'business_id' => $business?->id, 'published_at' => now(), 'published_by_user_id' => $request->user()->id]);
        $audit->write('platform.notice.published', $business, $request->user(), $notice, $data['reason'], after: ['audience' => $data['audience'], 'severity' => $data['severity'], 'starts_at' => $notice->starts_at->toIso8601String()], source: 'platform');

        return response()->json(['notice' => $notice], 201);
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->allow($request, PlatformCapability::AlertView);

        return response()->json(['alerts' => DB::table('platform_alerts')->latest('detected_at')->limit(100)->get()->map(fn ($alert) => ['public_id' => $alert->public_id, 'operator_user_id' => $alert->operator_user_id, 'business_id' => $alert->business_id, 'kind' => $alert->kind, 'severity' => $alert->severity, 'summary' => $alert->summary, 'detected_at' => $alert->detected_at])]);
    }

    public function auditEvents(Request $request): JsonResponse|Response
    {
        $this->allow($request, PlatformCapability::AuditView);
        $data = ['events' => AuditEvent::query()->latest('occurred_at')->limit(100)->get()->map->only([
            'public_id', 'business_id', 'actor_user_id', 'actor_platform_role', 'action', 'auditable_type', 'auditable_id', 'source', 'correlation_id', 'reason', 'occurred_at',
        ])];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/AuditEvents', $data);
    }

    private function allow(Request $request, PlatformCapability $capability): void
    {
        $this->authorization->authorize($request->user(), $capability);
    }
}
