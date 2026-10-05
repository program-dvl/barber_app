<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunicationSupportService
{
    private const MAX_OPERATOR_ATTEMPTS = 8;

    public function __construct(private readonly AuditWriter $audit) {}

    /** @return array<string,mixed> */
    public function diagnostic(CommunicationMessage $message): array
    {
        return [
            'message_id' => $message->id, 'business_id' => $message->business_id,
            'intent_type' => $message->intent->intent_type, 'event_type' => $message->intent->event_type,
            'source' => ['type' => $message->intent->source_type, 'id' => $message->intent->source_id],
            'channel' => $message->channel, 'recipient_fingerprint' => substr($message->recipient_hash, 0, 12),
            'status' => $message->status, 'attempt_count' => $message->attempt_count, 'max_attempts' => $message->max_attempts,
            'provider' => $message->provider, 'provider_message_id' => $message->provider_message_id,
            'last_error_code' => $message->last_error_code, 'last_error_class' => $message->last_error_class,
            'correlation_id' => $message->intent->correlation_id, 'next_attempt_at' => $message->next_attempt_at?->toIso8601String(),
            'content_available_to_support' => false,
        ];
    }

    public function replay(CommunicationMessage $message, string $reason): CommunicationMessage
    {
        return DB::transaction(function () use ($message, $reason): CommunicationMessage {
            $message = CommunicationMessage::query()->with(['intent', 'business'])->lockForUpdate()->findOrFail($message->id);
            if (! $this->canReplay($message)) {
                throw ValidationException::withMessages(['message' => 'Only a failed message with a safe retry reason and remaining attempts can be retried. Messages already queued, sending or delivered cannot be retried.']);
            }
            $message->forceFill([
                'status' => 'queued', 'next_attempt_at' => now(), 'failed_at' => null,
                'max_attempts' => max($message->max_attempts, $message->attempt_count + 1),
            ])->save();
            $this->audit->write('communication.replay_requested', $message->business, target: $message, reason: $reason, after: [
                'message_id' => $message->id, 'channel' => $message->channel, 'attempt_count' => $message->attempt_count,
            ], source: 'support', correlationId: $message->intent->correlation_id);
            DeliverCommunicationMessage::dispatch($message->id, $message->intent->correlation_id)->afterCommit();

            return $message->fresh();
        }, 3);
    }

    public function canReplay(CommunicationMessage $message): bool
    {
        return $message->status === 'failed' && ! $message->delivered_at && ! $message->sent_at
            && $message->attempt_count < self::MAX_OPERATOR_ATTEMPTS && $this->replayableError($message);
    }

    private function replayableError(CommunicationMessage $message): bool
    {
        $code = $message->last_error_code;
        if (in_array($code, ['provider_not_configured', 'ses_throttled', 'ses_authentication_failed', 'ses_sender_rejected', 'ses_configuration_set_missing', 'ses_sending_paused', 'resend_missing_message_id', 'twilio_http_401', 'twilio_http_403'], true)) {
            return true;
        }
        if ($code === 'unexpected_provider_error') {
            return $message->channel === 'email' && $message->provider !== 'ses';
        }

        return (bool) preg_match('/^resend_http_(429|5\d\d)$/', (string) $code)
            || $code === 'twilio_http_429';
    }
}
