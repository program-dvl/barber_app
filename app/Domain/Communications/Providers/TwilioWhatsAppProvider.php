<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
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
        $account = (string) config('communications.twilio.account_sid');
        $token = (string) config('communications.twilio.auth_token');
        if ($account === '' || $token === '' || blank($message->providerTemplateId)) {
            throw new CommunicationProviderException('provider_not_configured', false);
        }
        $response = Http::withBasicAuth($account, $token)->asForm()->timeout(10)
            ->withHeaders(['X-Correlation-ID' => $message->correlationId])
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$account.'/Messages.json', [
                'To' => 'whatsapp:'.$message->destination, 'From' => config('communications.twilio.whatsapp_from'),
                'ContentSid' => $message->providerTemplateId, 'ContentVariables' => json_encode($message->providerVariables, JSON_THROW_ON_ERROR),
                'StatusCallback' => route('communications.webhooks.twilio'),
            ]);
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
