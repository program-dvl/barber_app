<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use Illuminate\Support\Facades\Http;

class TwilioWhatsAppProvider implements MobileChannelProvider
{
    public function name(): string
    {
        return 'twilio';
    }

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $mode = (string) config('communications.transport_mode', 'fake');
        if ($mode === 'whatsapp_sandbox' && ! config('communications.whatsapp_sandbox_send_enabled')) {
            throw new CommunicationProviderException('whatsapp_sandbox_send_not_enabled', false);
        }
        if ($mode === 'live' && ! config('communications.live_send_enabled')) {
            throw new CommunicationProviderException('live_send_not_enabled', false);
        }
        if (! in_array($mode, ['whatsapp_sandbox', 'live'], true)) {
            throw new CommunicationProviderException('whatsapp_transport_not_enabled', false);
        }

        $profile = $message->senderProfileId
            ? CommunicationSenderProfile::query()->where('business_id', $message->businessId)->find($message->senderProfileId)
            : null;
        $sandbox = $mode === 'whatsapp_sandbox';
        $account = (string) ($profile?->provider_account_sid ?: config('communications.twilio.account_sid'));
        $username = (string) ($profile?->provider_api_key_sid ?: config('communications.twilio.api_key_sid') ?: $account);
        $secret = (string) ($profile?->provider_api_key_secret ?: config('communications.twilio.api_key_secret') ?: config('communications.twilio.auth_token'));
        $from = $sandbox ? (string) config('communications.twilio.whatsapp_sandbox_from') : (string) ($profile?->sender_identifier ?: config('communications.twilio.whatsapp_from'));
        if ($account === '' || $username === '' || $secret === '' || $from === '') {
            throw new CommunicationProviderException('provider_not_configured', false);
        }
        $payload = [
            'To' => 'whatsapp:'.$message->destination,
            'From' => str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:'.$from,
            'StatusCallback' => route('communications.webhooks.twilio'),
        ];
        if (filled($message->providerTemplateId)) {
            $payload['ContentSid'] = $message->providerTemplateId;
            $payload['ContentVariables'] = json_encode($message->providerVariables, JSON_THROW_ON_ERROR);
        } elseif ($sandbox || $message->conversationReply) {
            // Free-form replies are accepted only inside the active customer-service window.
            $payload['Body'] = $message->body;
        } else {
            throw new CommunicationProviderException('approved_template_required', false);
        }
        $response = Http::withBasicAuth($username, $secret)->asForm()->timeout(10)
            ->withHeaders(['X-Correlation-ID' => $message->correlationId])
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$account.'/Messages.json', $payload);
        if (! $response->successful()) {
            $retryable = $response->status() === 429;
            throw new CommunicationProviderException('twilio_http_'.$response->status(), $retryable);
        }
        $sid = (string) $response->json('sid');
        if ($sid === '') {
            throw new CommunicationProviderException('twilio_missing_message_id', false);
        }

        return new ProviderSendResult($sid, $response->header('Twilio-Request-Id'), (string) ($response->json('status') ?: 'queued'));
    }
}
