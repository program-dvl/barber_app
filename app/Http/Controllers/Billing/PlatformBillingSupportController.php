<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Contracts\SubscriptionProvider;
use App\Domain\Billing\Services\SubscriptionLifecycleManager;
use App\Domain\PlatformAccess\Enums\PlatformCapability;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\PlatformAuthorizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformBillingSupportController extends Controller
{
    public function cancel(Request $request, Business $business, SubscriptionLifecycleManager $lifecycle): JsonResponse
    {
        app(PlatformAuthorizer::class)->authorize($request->user(), PlatformCapability::BillingManage);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $subscription = $business->subscription()->firstOrFail();

        if ($subscription->provider_subscription_id) {
            app(SubscriptionProvider::class)->cancelImmediately($subscription);
        }

        return response()->json($lifecycle->supportCancel($subscription, $request->user(), $validated['reason']));
    }
}
