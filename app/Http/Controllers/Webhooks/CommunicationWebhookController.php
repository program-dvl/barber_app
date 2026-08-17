<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Communications\Services\CommunicationProviderCallbackService;
use App\Domain\Communications\Services\CommunicationWebhookVerifier;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommunicationWebhookController extends Controller
{
    public function resend(Request $request, CommunicationWebhookVerifier $verifier, CommunicationProviderCallbackService $callbacks): Response
    {
        abort_unless($verifier->verifyResend($request), 400, 'Invalid Resend webhook signature.');
        $payload = $request->json()->all();
        $callbacks->receive(
            'resend', (string) $request->header('svix-id'), (string) data_get($payload, 'data.email_id'),
            (string) ($payload['type'] ?? 'unknown'), CarbonImmutable::parse((string) ($payload['created_at'] ?? now()))->utc(),
            hash('sha256', $request->getContent()),
        );

        return response()->noContent();
    }

    public function twilio(Request $request, CommunicationWebhookVerifier $verifier, CommunicationProviderCallbackService $callbacks): Response
    {
        abort_unless($verifier->verifyTwilio($request), 400, 'Invalid Twilio webhook signature.');
        $sid = $request->string('MessageSid')->toString();
        $status = $request->string('MessageStatus')->toString();
        $occurred = CarbonImmutable::parse($request->input('Timestamp', now()))->utc();
        $eventId = (string) ($request->header('I-Twilio-Idempotency-Token') ?: hash('sha256', $sid.'|'.$status.'|'.$request->input('ErrorCode')));
        $callbacks->receive('twilio', $eventId, $sid, $status, $occurred, hash('sha256', $request->getContent()));

        return response()->noContent();
    }
}
