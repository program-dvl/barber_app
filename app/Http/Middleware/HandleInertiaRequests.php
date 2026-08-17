<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\PlatformNotice;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = app(TenantContext::class);

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'signupIntent' => fn () => $request->routeIs('register')
                ? app(PublicPricingCatalog::class)->validSelection(
                    $request->query('plan'),
                    $request->query('interval')
                )
                : null,
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'tenant' => function () use ($context): ?array {
                if (! $context->hasBusiness()) {
                    return null;
                }

                $membership = $context->membership();
                $canManageBilling = $membership
                    ? app(MembershipAccessManager::class)->allows($membership, PermissionName::BillingManage)
                    : false;
                $subscription = $canManageBilling
                    ? $context->business()->subscription()->with('plan:id,name,code')->first()
                    : null;

                return [
                    'public_id' => $context->business()->public_id,
                    'name' => $context->business()->name,
                    'membership_id' => $membership?->public_id,
                    'can_manage_billing' => $canManageBilling,
                    'subscription' => $subscription ? [
                        'plan_name' => $subscription->plan->name,
                        'plan_code' => $subscription->plan->code,
                        'status' => $subscription->status->value,
                        'renews_at' => $subscription->current_period_ends_at?->toIso8601String(),
                        'cancel_at' => $subscription->cancel_at?->toIso8601String(),
                    ] : null,
                ];
            },
            'platform' => fn () => $request->user()?->hasAnyActivePlatformRole() ? [
                'roles' => $request->user()->activePlatformRoles()->get()->map(fn ($assignment) => $assignment->role->value)->values()->all(),
            ] : null,
            'supportAccessBanner' => fn () => $context->hasBusiness() && Schema::hasTable('support_access_sessions')
                ? SupportAccessSession::query()->where('business_id', $context->business()->id)->whereNull('ended_at')
                    ->whereHas('grant', fn ($query) => $query->whereNull('revoked_at')->where('expires_at', '>', now()))
                    ->with(['operator:id,name', 'grant:id,ticket_reference,reason,expires_at'])->get()->map(fn ($session) => [
                        'operator' => $session->operator->name, 'ticket_reference' => $session->grant->ticket_reference,
                        'reason' => $session->grant->reason, 'expires_at' => $session->grant->expires_at->toIso8601String(),
                    ])->all()
                : [],
            'platformNotices' => fn () => $context->hasBusiness() && Schema::hasTable('platform_notices')
                ? PlatformNotice::query()->whereNotNull('published_at')->where('starts_at', '<=', now())
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->where(fn ($query) => $query->where('audience', 'all_businesses')->orWhere('business_id', $context->business()->id))
                    ->get()->map->only(['public_id', 'title', 'message', 'severity'])->all()
                : [],
            'account' => fn () => [
                'workspaces' => $request->user()?->memberships()
                    ->active()
                    ->with('business:id,public_id,name,status')
                    ->oldest('id')
                    ->get()
                    ->filter(fn ($membership) => $membership->business?->isActive())
                    ->map(fn ($membership) => [
                        'public_id' => $membership->business->public_id,
                        'name' => $membership->business->name,
                        'can_manage_billing' => app(MembershipAccessManager::class)
                            ->allows($membership, PermissionName::BillingManage),
                    ])
                    ->values()
                    ->all() ?? [],
            ],
            'flash' => fn () => [
                'status' => $request->session()->get('status'),
                'secure_url' => $request->session()->get('form_url')
                    ?? $request->session()->get('attachment_url')
                    ?? $request->session()->get('privacy_export_url'),
            ],
        ];
    }
}
