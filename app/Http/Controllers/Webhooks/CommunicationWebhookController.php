<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationProviderCallbackService;
use App\Domain\Communications\Services\CommunicationWebhookVerifier;
use App\Domain\Communications\Services\InboundCommunicationService;
use App\Domain\Communications\Services\SesNotificationVerifier;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class CommunicationWebhookController extends Controller
{
    public function ses(Request $request, SesNotificationVerifier $verifier, CommunicationProviderCallbackService $callbacks): Response
    {
        abort_if(strlen($request->getContent()) > 262144, 413);
        $envelope = json_decode($request->getContent(), true);
        abort_unless(is_array($envelope) && $verifier->verify($envelope), 400, 'Invalid email delivery signature.');
        if ($envelope['Type'] === 'SubscriptionConfirmation') {
            $region = explode(':', $envelope['TopicArn'])[3] ?? '';
            $url = parse_url($envelope['SubscribeURL']);
            parse_str($url['query'] ?? '', $query);
            abort_unless(($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === 'sns.'.$region.'.amazonaws.com'
                && ! isset($url['port']) && ! isset($url['user']) && ! isset($url['pass']) && in_array($url['path'] ?? '/', ['/', ''], true)
                && ($query['Action'] ?? '') === 'ConfirmSubscription' && ($query['TopicArn'] ?? '') === $envelope['TopicArn']
                && ($query['Token'] ?? '') === $envelope['Token'], 400);
            try {
                $result = Http::withOptions(['allow_redirects' => false])->timeout(5)->get($envelope['SubscribeURL']);
            } catch (\Throwable) {
                return response('Subscription confirmation could not be verified.', 503);
            }

            return $result->successful() ? response()->noContent() : response('Subscription confirmation could not be verified.', 503);
        }
        $payload = json_decode($envelope['Message'], true);
        abort_unless(is_array($payload), 400);
        $type = (string) ($payload['eventType'] ?? $payload['notificationType'] ?? '');
        $event = match ($type) {
            'Delivery' => 'email.delivered', 'Send' => 'email.sent', 'Bounce' => 'email.bounced',
            'Complaint' => 'email.complained', 'Reject', 'Rendering Failure' => 'email.failed',
            'DeliveryDelay' => 'email.delayed', default => null,
        };
        if (! $event) {
            return response()->noContent();
        }
        $id = (string) data_get($payload, 'mail.messageId', '');
        $tag = data_get($payload, 'mail.tags.client_message.0');
        // A callback can race the provider response. Ask SNS to retry while the
        // matching client message is still being sent, rather than lose evidence.
        if ($tag && ! CommunicationMessage::query()->where('provider', 'ses')->where('provider_message_id', $id)->exists()
            && CommunicationMessage::query()->where('idempotency_key', $tag)->where('provider', 'ses')->where('status', 'sending')->exists()) {
            return response('Delivery evidence is awaiting the sending result.', 503);
        }
        $timestamp = data_get($payload, lcfirst(str_replace(' ', '', $type)).'.timestamp', $envelope['Timestamp']);
        try {
            $occurred = CarbonImmutable::parse((string) $timestamp)->utc();
        } catch (\Throwable) {
            abort(400, 'Invalid delivery time.');
        }
        $callbacks->receive('ses', $envelope['MessageId'], $id, $event, $occurred, hash('sha256', $request->getContent()));

        return response()->noContent();
    }

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

    public function twilioInbound(Request $request, CommunicationWebhookVerifier $verifier, InboundCommunicationService $inbound): Response
    {
        abort_unless($verifier->verifyTwilio($request), 400, 'Invalid Twilio webhook signature.');
        $sid = $request->string('MessageSid')->toString();
        $from = $request->string('From')->toString();
        $to = $request->string('To')->toString();
        abort_if($sid === '' || $from === '' || $to === '', 422, 'Inbound message identifiers are required.');
        $media = [];
        for ($index = 0; $index < min(10, max(0, $request->integer('NumMedia'))); $index++) {
            $media[] = [
                'url' => $request->string('MediaUrl'.$index)->toString(),
                'content_type' => $request->string('MediaContentType'.$index)->toString() ?: null,
            ];
        }
        $inbound->receiveTwilio($sid, $from, $to, $request->string('Body')->toString(), $media, CarbonImmutable::now('UTC'));

        return response()->noContent();
    }
}
