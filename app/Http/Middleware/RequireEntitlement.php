<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEntitlement
{
    public function __construct(private readonly TenantContext $tenant, private readonly EntitlementEvaluator $entitlements) {}

    public function handle(Request $request, Closure $next, string $key, string $operation = 'use'): Response
    {
        $this->entitlements->authorize($this->tenant->business(), $key, $operation, (int) $request->input('entitlement_increase', 0));

        return $next($request);
    }
}
