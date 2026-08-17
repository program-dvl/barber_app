<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookProcessor $processor): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('billing.stripe.webhook_secret')
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            abort(400, 'Invalid Stripe webhook signature.');
        }

        $processor->receiveVerified($event->toArray());

        return response()->noContent();
    }
}
