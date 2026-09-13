<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Domain\BusinessConfiguration\Services\BusinessSetupProgress;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\PlatformNotice;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Http\Controllers\Auth\SocialiteController;
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
            'appName' => config('brand.product_name'),
            'brand' => fn () => [
                'product_name' => config('brand.product_name'),
                'company_name' => config('brand.company_name'),
                'tagline' => config('brand.tagline'),
                'description' => config('brand.description'),
                'logo' => config('brand.logo'),
                'logo_inverse' => config('brand.logo_inverse'),
                'logo_mark' => config('brand.logo_mark'),
                'logo_mark_inverse' => config('brand.logo_mark_inverse'),
                'favicon' => config('brand.favicon'),
                'website_url' => config('brand.website_url'),
                'booking_host' => config('brand.booking_host'),
                'support_email' => config('brand.support_email'),
            ],
            'signupIntent' => fn () => $request->routeIs('register')
                ? app(PublicPricingCatalog::class)->validSelection(
                    $request->query('plan'),
                    $request->query('interval')
                )
                : null,
            'googleAuth' => fn () => [
                'enabled' => filled(config('services.google.client_id'))
                    && filled(config('services.google.client_secret'))
                    && filled(config('services.google.redirect')),
                'pending_registration' => $request->routeIs('register')
                    ? SocialiteController::sharedPendingRegistration($request)
                    : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'tenant' => function () use ($context, $request): ?array {
                $business = $context->hasBusiness()
                    ? $context->business()
                    : $request->attributes->get('tenant_business');
                $membership = $context->hasBusiness()
                    ? $context->membership()
                    : $request->attributes->get('tenant_membership');

                if (! $business || ! $membership) {
                    return null;
                }

                $canManageBilling = $membership
                    ? app(MembershipAccessManager::class)->allows($membership, PermissionName::BillingManage)
                    : false;
                $accessSubscription = $business->subscription()->with('plan:id,name,code')->first();
                $subscription = $canManageBilling ? $accessSubscription : null;
                $entitlements = app(EntitlementEvaluator::class);
                $canManageSetup = $membership
                    ? app(MembershipAccessManager::class)->allows($membership, PermissionName::SettingsManage)
                    : false;

                return [
                    'public_id' => $business->public_id,
                    'name' => $business->name,
                    'regional' => [
                        'country_code' => $business->country_code ?: 'IN',
                        'currency_code' => $business->currency_code ?: 'INR',
                        'locale' => $business->locale ?: 'en-IN',
                        'time_zone' => $business->time_zone ?: config('app.timezone'),
                    ],
                    'membership_id' => $membership?->public_id,
                    'can_manage_billing' => $canManageBilling,
                    'can_manage_setup' => $canManageSetup,
                    'setup' => $canManageSetup ? app(BusinessSetupProgress::class)->for($business) : null,
                    'features' => app(WorkspaceAccessService::class)->navigation($business, $membership),
                    'access' => $accessSubscription ? [
                        'status' => $accessSubscription->status->value,
                        'restriction_level' => $accessSubscription->restriction_level->value,
                        'trial_ends_at' => $accessSubscription->trial_ends_at?->toIso8601String(),
                        'grace_ends_at' => $accessSubscription->grace_ends_at?->toIso8601String(),
                    ] : null,
                    'entitlements' => [
                        'inventory.enabled' => (bool) $entitlements->value($business, 'inventory.enabled'),
                        'reporting.advanced' => (bool) $entitlements->value($business, 'reporting.advanced'),
                        'branding.custom' => (bool) $entitlements->value($business, 'branding.custom'),
                    ],
                    'subscription' => $subscription ? [
                        'plan_name' => $subscription->plan->name,
                        'plan_code' => $subscription->plan->code,
                        'status' => $subscription->status->value,
                        'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                        'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
                        'restriction_level' => $subscription->restriction_level->value,
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
