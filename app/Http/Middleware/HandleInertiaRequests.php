<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\BusinessConfiguration\Services\BusinessSetupProgress;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\PlatformNotice;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Http\Controllers\Auth\SocialiteController;
use App\Support\Seo\PublicIndexation;
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
            'seoDefaults' => fn () => [
                'title' => match ($request->route()?->getName()) {
                    'login' => 'Sign in | ClipperDesk',
                    'register' => 'Create your account | ClipperDesk',
                    'password.request' => 'Reset your password | ClipperDesk',
                    'password.reset' => 'Choose a new password | ClipperDesk',
                    'booking.welcome' => 'Book an appointment | ClipperDesk',
                    'booking.manage', 'public.appointment.view', 'public.appointment.action' => 'Manage your appointment | ClipperDesk',
                    'client-forms.completed' => 'Form received | ClipperDesk',
                    'two-factor.login' => 'Verify your sign-in | ClipperDesk',
                    'staff-invitations.show' => 'Join your team | ClipperDesk',
                    default => config('brand.product_name'),
                },
                'description' => match ($request->route()?->getName()) {
                    'login' => 'Sign in to your ClipperDesk workspace to manage appointments, client records, team schedules and recorded sales.',
                    'register' => 'Create your ClipperDesk account and start a free trial after email verification. No credit card is required.',
                    'password.request', 'password.reset' => 'Reset your ClipperDesk password to regain access to your business workspace.',
                    'booking.welcome' => 'Open the booking link shared by your business to choose services and an available appointment time.',
                    'booking.manage', 'public.appointment.view', 'public.appointment.action' => 'Use your private appointment link to review booking details and the changes allowed by your business’s policy.',
                    'client-forms.completed' => 'Your form has been sent to the business. You can close this page.',
                    'two-factor.login' => 'Use your authenticator app or a saved recovery code to finish signing in to ClipperDesk.',
                    'staff-invitations.show' => 'Review your private invitation to join your team’s ClipperDesk workspace.',
                    default => config('brand.description'),
                },
                'image' => url(config('brand.social_image')),
                'site_name' => config('brand.product_name'),
                'locale' => 'en_IN',
                'robots' => app(PublicIndexation::class)->directive($request),
                'canonical' => null,
            ],
            'brand' => fn () => [
                'product_name' => $request->routeIs('policy.show') ? 'Clipperdesk' : config('brand.product_name'),
                'company_name' => config('brand.company_name'),
                'tagline' => config('brand.tagline'),
                'description' => config('brand.description'),
                'logo' => config('brand.logo'),
                'logo_inverse' => config('brand.logo_inverse'),
                'logo_mark' => config('brand.logo_mark'),
                'logo_mark_inverse' => config('brand.logo_mark_inverse'),
                'favicon' => config('brand.favicon'),
                'website_url' => config('brand.website_url'),
                'booking_host' => config('booking.host'),
                'booking_base_url' => config('booking.scheme').'://'.config('booking.host'),
                'support_email' => config('brand.support_email'),
            ],
            'signupIntent' => fn () => $request->routeIs('register')
                ? app(PublicPricingCatalog::class)->validSelection(
                    $request->query('plan'),
                    $request->query('interval'),
                    $request->query('locations', 1),
                    $request->query('staff', 1)
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
                $canManageSetup = app(BusinessSetupAccess::class)->allows($business, $membership);

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
                    'setup' => $canManageSetup ? ($request->attributes->get('business_setup_summary') ?? app(BusinessSetupProgress::class)->for($business)) : null,
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
