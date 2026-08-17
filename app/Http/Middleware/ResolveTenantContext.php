<?php

namespace App\Http\Middleware;

use App\Domain\PlatformAccess\Models\Business;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if (is_string($business)) {
            $business = Business::query()->where('public_id', $business)->first();

            if ($business) {
                $request->route()->setParameter('business', $business);
            }
        }

        abort_unless($business instanceof Business && $business->isActive(), 404);

        $membership = $request->user()?->memberships()
            ->with('business')
            ->where('business_id', $business->getKey())
            ->active()
            ->first();

        abort_unless($membership?->isActive(), 403);

        $this->context->activate($business, $membership);
        $request->attributes->set('tenant_membership', $membership);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
