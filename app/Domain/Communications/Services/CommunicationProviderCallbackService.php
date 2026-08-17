<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationProviderEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CommunicationProviderCallbackService
{
    public function __construct(private readonly CommunicationConsentService $consent) {}

    public function receive(string $provider, string $eventId, string $providerMessageId, string $eventType, CarbonImmutable $occurredAt, string $payloadHash): CommunicationProviderEvent
    {
        abort_if($eventId === '' || $providerMessageId === '', 400, 'Provider event identifiers are required.');
        $event = CommunicationProviderEvent::query()->firstOrCreate([
            'provider' => $provider, 'provider_event_id' => $eventId,
        ], [
            'provider_message_id' => $providerMessageId, 'event_type' => $eventType, 'payload_hash' => $payloadHash,
            'signature_verified' => true, 'status' => 'pending', 'provider_occurred_at' => $occurredAt->utc(),
        ]);
        abort_unless(hash_equals($event->payload_hash, $payloadHash), 409, 'Provider event ID was reused with different content.');
        if (in_array($event->status, ['processed', 'ignored'], true)) {
            return $event;
        }

        DB::transaction(function () use ($event, $provider, $providerMessageId, $eventType, $occurredAt): void {
            $event = CommunicationProviderEvent::query()->lockForUpdate()->findOrFail($event->id);
            if (in_array($event->status, ['processed', 'ignored'], true)) {
                return;
            }
            $event->increment('attempts');
            $message = CommunicationMessage::query()->where('provider', $provider)->where('provider_message_id', $providerMessageId)->lockForUpdate()->first();
            if (! $message) {
                $event->update(['status' => 'ignored', 'processed_at' => now(), 'last_error_code' => 'message_not_found']);

                return;
            }
            $event->update(['business_id' => $message->business_id, 'communication_message_id' => $message->id]);
            $normalized = $this->normalize($provider, $eventType);
            $newer = ! $message->provider_state_at || $occurredAt->greaterThan($message->provider_state_at);
            $sameButForward = $message->provider_state_at && $occurredAt->equalTo($message->provider_state_at)
                && $this->rank($normalized) >= $this->rank($message->status);
            if (! $newer && ! $sameButForward) {
                $event->update(['status' => 'ignored', 'processed_at' => now(), 'last_error_code' => 'out_of_order']);

                return;
            }
            $updates = ['status' => $normalized, 'provider_state_at' => $occurredAt->utc()];
            if ($normalized === 'delivered') {
                $updates['delivered_at'] = $occurredAt->utc();
            } elseif ($normalized === 'failed') {
                $updates['failed_at'] = $occurredAt->utc();
                $updates['last_error_class'] = 'terminal';
                $updates['last_error_code'] = $eventType;
            }
            $message->update($updates);
            if ($normalized === 'failed' && $this->hardSuppression($eventType)) {
                $this->consent->suppress($message->business_id, $message->client, $message->channel, $message->recipient, 'all', $eventType, $provider.'_callback');
            }
            $this->refreshIntent($message);
            $event->update(['status' => 'processed', 'processed_at' => now(), 'last_error_code' => null]);
        }, 3);

        return $event->fresh();
    }

    private function normalize(string $provider, string $event): string
    {
        return match (true) {
            in_array($event, ['email.delivered', 'delivered', 'read'], true) => 'delivered',
            in_array($event, ['email.sent', 'sent', 'queued', 'accepted'], true) => 'sent',
            in_array($event, ['email.failed', 'email.bounced', 'email.complained', 'failed', 'undelivered'], true) => 'failed',
            default => $provider === 'resend' && str_contains($event, 'delayed') ? 'sent' : 'sent',
        };
    }

    private function rank(string $state): int
    {
        return match ($state) {
            'queued', 'retried', 'sending' => 0,
            'sent' => 1,
            'delivered' => 3,
            'failed' => 2,
            default => 0,
        };
    }

    private function hardSuppression(string $event): bool
    {
        return in_array($event, ['email.bounced', 'email.complained', 'undelivered'], true);
    }

    private function refreshIntent(CommunicationMessage $message): void
    {
        $intent = $message->intent;
        $states = $intent->messages()->pluck('status');
        $intent->update(['status' => $states->contains('delivered') ? 'delivered' : ($states->contains('sent') ? 'sent' : ($states->contains('failed') ? 'failed' : 'queued'))]);
    }
}
