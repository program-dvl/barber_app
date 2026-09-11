<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscriptionAccess
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('business.billing.*')) {
            return $next($request);
        }

        $subscription = $this->tenant->business()->subscription()->first();
        $trialExpired = $subscription?->status === SubscriptionStatus::Trialing
            && $subscription->trial_ends_at?->isPast();

        if ($subscription && ! $trialExpired && $subscription->status->permitsNormalWrites()) {
            return $next($request);
        }

        $message = $trialExpired
            ? 'The free trial has ended. Existing information remains available, but protected changes require an active subscription.'
            : 'This account is currently read-only. Resolve subscription billing to restore protected changes.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => $trialExpired ? 'trial_expired' : 'subscription_restricted',
                'billing_url' => $request->user()?->can(PermissionName::BillingManage->value)
                    ? route('business.billing.show', $this->tenant->business())
                    : null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        $route = $request->user()?->can(PermissionName::BillingManage->value)
            ? route('business.billing.show', $this->tenant->business())
            : route('business.dashboard', $this->tenant->business());

        return redirect()->to($route)->with('status', $message);
    }
}
