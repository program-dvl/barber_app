<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\MoneyCommerce\Contracts\AppointmentPaymentProvider;
use App\Domain\MoneyCommerce\Services\PaymentWebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppointmentPaymentWebhookController extends Controller
{
    public function __invoke(Request $request, AppointmentPaymentProvider $provider, PaymentWebhookProcessor $payments)
    {
        $event = $provider->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'));
        $payments->ingest('stripe', $event);

        return response()->json(['received' => true]);
    }
}
