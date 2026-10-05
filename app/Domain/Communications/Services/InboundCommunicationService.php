<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Models\CommunicationConversation;
use App\Domain\Communications\Models\CommunicationInboundMessage;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\PlatformAccess\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InboundCommunicationService
{
    public function __construct(private readonly CommunicationConsentService $consent) {}

    /** @param array<int, array{url:string,content_type:?string}> $media */
    public function receiveTwilio(string $providerMessageId, string $from, string $to, string $body, array $media, CarbonImmutable $receivedAt): ?CommunicationInboundMessage
    {
        $channel = str_starts_with($from, 'whatsapp:') ? 'whatsapp' : 'sms';
        $participant = preg_replace('/^(?:whatsapp:|sms:)/', '', trim($from));
        $sender = $this->resolveSender($channel, $to);
        if (! $sender || ! ($sender->capabilities['inbound'] ?? false)) {
            return null;
        }

        return DB::transaction(function () use ($providerMessageId, $participant, $body, $media, $receivedAt, $channel, $sender): CommunicationInboundMessage {
            $existing = CommunicationInboundMessage::query()->where('provider', 'twilio')->where('provider_message_id', $providerMessageId)->first();
            if ($existing) {
                return $existing;
            }
            $hash = CommunicationConsentService::destinationHash($channel, $participant);
            $normalizedMobile = preg_replace('/\D/', '', $participant);
            $client = Client::query()->where('business_id', $sender->business_id)->where('normalized_mobile', $normalizedMobile)->first();
            $conversation = CommunicationConversation::query()->firstOrCreate([
                'business_id' => $sender->business_id, 'channel' => $channel, 'participant_hash' => $hash,
            ], [
                'client_id' => $client?->id, 'communication_sender_profile_id' => $sender->id,
                'participant' => $participant, 'status' => 'open', 'last_message_at' => $receivedAt,
            ]);
            $message = CommunicationInboundMessage::query()->create([
                'business_id' => $sender->business_id, 'communication_conversation_id' => $conversation->id,
                'provider' => 'twilio', 'provider_message_id' => $providerMessageId, 'channel' => $channel,
                'sender_hash' => $hash, 'sender' => $participant, 'body' => $body, 'media' => $media,
                'received_at' => $receivedAt,
            ]);
            $conversation->update(['client_id' => $conversation->client_id ?: $client?->id, 'last_message_at' => $receivedAt, 'status' => 'open']);

            $keyword = strtoupper(trim($body));
            if ($client && in_array($keyword, ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
                $this->consent->withdrawChannel($client, $channel);
            } elseif ($client && in_array($keyword, ['START', 'UNSTOP'], true)) {
                $this->consent->resumeChannel($client, $channel);
            }

            return $message;
        }, 3);
    }

    private function resolveSender(string $channel, string $to): ?CommunicationSenderProfile
    {
        if (config('communications.transport_mode') === 'whatsapp_sandbox' && $publicId = config('communications.twilio.sandbox_business_public_id')) {
            $business = Business::query()->where('public_id', $publicId)->first();
            if ($business) {
                return CommunicationSenderProfile::query()->where('business_id', $business->id)->where('channel', $channel)->where('status', 'active')->first();
            }
        }
        $hash = CommunicationSenderService::identifierHash($channel, $to);
        $profiles = CommunicationSenderProfile::query()->where('sender_identifier_hash', $hash)
            ->where('channel', $channel)->where('mode', 'branded')->where('status', 'active')->limit(2)->get();

        return $profiles->count() === 1 ? $profiles->first() : null;
    }
}
