<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Support\Audit\AuditWriter;
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
        if (! in_array($message->status, ['failed', 'retried', 'sending'], true)) {
            throw ValidationException::withMessages(['message' => 'Only failed or interrupted communication can be replayed.']);
        }
        if (! $this->replayableError($message)) {
            throw ValidationException::withMessages(['message' => 'This terminal destination or provider error is not safe to replay.']);
        }
        if ($message->attempt_count >= self::MAX_OPERATOR_ATTEMPTS) {
            throw ValidationException::withMessages(['message' => 'The operator replay limit has been reached.']);
        }
        $message->forceFill([
            'status' => 'queued', 'next_attempt_at' => now(), 'failed_at' => null,
            'max_attempts' => max($message->max_attempts, $message->attempt_count + 1),
        ])->save();
        $this->audit->write('communication.replay_requested', $message->business, target: $message, reason: $reason, after: [
            'message_id' => $message->id, 'channel' => $message->channel, 'attempt_count' => $message->attempt_count,
        ], source: 'support', correlationId: $message->intent->correlation_id);
        DeliverCommunicationMessage::dispatch($message->id, $message->intent->correlation_id);

        return $message->fresh();
    }

    private function replayableError(CommunicationMessage $message): bool
    {
        $code = $message->last_error_code;
        if ($code === 'provider_not_configured' || $code === 'resend_missing_message_id') {
            return true;
        }
        if ($code === 'unexpected_provider_error') {
            return $message->channel === 'email';
        }

        return (bool) preg_match('/^resend_http_(429|5\d\d)$/', (string) $code)
            || $code === 'twilio_http_429';
    }
}
