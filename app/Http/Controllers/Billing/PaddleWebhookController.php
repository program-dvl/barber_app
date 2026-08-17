<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\PaddleWebhookProcessor;
use App\Domain\Billing\Services\PaddleWebhookSignatureVerifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaddleWebhookController extends Controller
{
    public function __invoke(Request $request, PaddleWebhookSignatureVerifier $verifier, PaddleWebhookProcessor $processor): Response
    {
        $payload = $request->getContent();
        abort_unless($verifier->verify($payload, $request->header('Paddle-Signature')), 400, 'Invalid Paddle webhook signature.');
        $decoded = json_decode($payload, true);
        abort_unless(is_array($decoded), 400, 'Invalid Paddle webhook payload.');

        if (! $processor->acceptsPayload($decoded)) {
            return response()->noContent();
        }

        $processor->receiveVerified($decoded);

        return response()->noContent();
    }
}
