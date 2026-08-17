<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use Illuminate\Support\Facades\Http;

class ResendEmailProvider implements EmailChannelProvider
{
    public function name(): string
    {
        return 'resend';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $key = (string) config('communications.resend.api_key');
        if ($key === '') {
            throw new CommunicationProviderException('provider_not_configured', false);
        }
        $response = Http::withToken($key)->acceptJson()->withHeaders([
            'Idempotency-Key' => $message->idempotencyKey, 'X-Correlation-ID' => $message->correlationId,
        ])->timeout(10)->post(rtrim((string) config('communications.resend.api_url'), '/').'/emails', [
            'from' => config('communications.resend.from'), 'to' => [$message->destination],
            'subject' => $message->subject, 'html' => nl2br(e($message->body)),
        ]);
        if (! $response->successful()) {
            $retryable = $response->status() === 429 || $response->serverError();
            throw new CommunicationProviderException('resend_http_'.$response->status(), $retryable);
        }
        $id = (string) $response->json('id');
        if ($id === '') {
            throw new CommunicationProviderException('resend_missing_message_id', true);
        }

        return new ProviderSendResult($id, $response->header('request-id'));
    }
}
