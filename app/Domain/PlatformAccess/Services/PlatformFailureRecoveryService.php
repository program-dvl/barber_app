<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Services\PaddleWebhookProcessor;
use App\Domain\Billing\Services\StripeWebhookProcessor;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\MoneyCommerce\Models\PaymentProviderEvent;
use App\Domain\MoneyCommerce\Services\PaymentWebhookProcessor;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlatformFailureRecoveryService
{
    public function __construct(
        private readonly StripeWebhookProcessor $stripeBilling,
        private readonly PaddleWebhookProcessor $paddleBilling,
        private readonly PaymentWebhookProcessor $payments,
        private readonly CommunicationSupportService $communications,
        private readonly AuditWriter $audit,
    ) {}

    /** @return array<string,mixed> */
    public function list(?int $businessId = null): array
    {
        return [
            'billing_webhooks' => BillingProviderEvent::query()->when($businessId, fn ($q) => $q->where('business_id', $businessId))->where('status', 'failed')->latest()->limit(50)->get()->map(fn ($event) => [
                'id' => $event->id, 'business_id' => $event->business_id, 'provider' => $event->provider, 'event_type' => $event->event_type,
                'status' => $event->status, 'attempts' => $event->attempts, 'signature_verified' => $event->signature_verified, 'occurred_at' => $event->provider_created_at?->toIso8601String(),
                'payload_available_to_support' => false,
            ])->all(),
            'payment_webhooks' => PaymentProviderEvent::query()->when($businessId, fn ($q) => $q->where('business_id', $businessId))->where('processing_status', 'failed')->latest()->limit(50)->get()->map(fn ($event) => [
                'id' => $event->id, 'business_id' => $event->business_id, 'provider' => $event->provider, 'event_type' => $event->event_type,
                'status' => $event->processing_status, 'attempts' => $event->attempts, 'signature_verified' => $event->signature_verified, 'payload_available_to_support' => false,
            ])->all(),
            'notifications' => CommunicationMessage::query()->when($businessId, fn ($q) => $q->where('business_id', $businessId))->where('status', 'failed')->with('intent')->latest()->limit(50)->get()->map(fn ($message) => $this->communications->diagnostic($message))->all(),
            'jobs' => DB::table('failed_jobs')->latest('failed_at')->limit(50)->get()->map(fn ($job) => [
                'uuid' => $job->uuid, 'queue' => $job->queue, 'failed_at' => $job->failed_at,
                'job_class' => data_get(json_decode($job->payload, true), 'displayName'), 'payload_available_to_support' => false,
            ])->all(),
        ];
    }

    public function replay(string $type, int $id, string $operationKey, User $operator, string $reason, ?int $requiredBusinessId = null): array
    {
        $idempotencyKey = hash('sha256', implode(':', [$type, $id, $operationKey]));
        $existing = DB::table('platform_replay_attempts')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return ['status' => $existing->status, 'result_code' => $existing->result_code, 'duplicate' => true];
        }
        [$businessId, $target] = $this->target($type, $id);
        abort_if($requiredBusinessId !== null && $businessId !== $requiredBusinessId, 404);
        $attemptId = DB::table('platform_replay_attempts')->insertGetId([
            'public_id' => (string) Str::ulid(), 'business_id' => $businessId, 'operator_user_id' => $operator->id,
            'target_type' => $type, 'target_id' => $id, 'idempotency_key' => $idempotencyKey, 'reason' => $reason,
            'status' => 'running', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $resultCode = match ($type) {
                'billing_webhook' => $this->replayBilling($target),
                'payment_webhook' => $this->replayPayment($target),
                'notification' => $this->replayCommunication($target, $reason),
                default => throw ValidationException::withMessages(['type' => 'This failed-work type is not safely replayable.']),
            };
            DB::table('platform_replay_attempts')->where('id', $attemptId)->update(['status' => 'completed', 'result_code' => $resultCode, 'updated_at' => now()]);
            $this->audit->write('platform.failure.replayed', $businessId ? Business::find($businessId) : null, $operator, reason: $reason, after: ['target_type' => $type, 'target_id' => $id, 'result_code' => $resultCode], source: 'support');

            return ['status' => 'completed', 'result_code' => $resultCode, 'duplicate' => false];
        } catch (Throwable $error) {
            DB::table('platform_replay_attempts')->where('id', $attemptId)->update(['status' => 'failed', 'result_code' => class_basename($error), 'updated_at' => now()]);
            throw $error;
        }
    }

    private function target(string $type, int $id): array
    {
        if ($type === 'billing_webhook') {
            $row = BillingProviderEvent::findOrFail($id);
            abort_unless($row->signature_verified && in_array($row->status, ['failed', 'pending'], true), 422);

            return [$row->business_id, $row];
        }
        if ($type === 'payment_webhook') {
            $row = PaymentProviderEvent::findOrFail($id);
            abort_unless($row->signature_verified && in_array($row->processing_status, ['failed', 'pending'], true), 422);

            return [$row->business_id, $row];
        }
        if ($type === 'notification') {
            $row = CommunicationMessage::with(['intent', 'business'])->findOrFail($id);
            abort_unless($row->status === 'failed', 422);

            return [$row->business_id, $row];
        }

        throw ValidationException::withMessages(['type' => 'This failed-work type is not safely replayable.']);
    }

    private function replayBilling(BillingProviderEvent $event): string
    {
        $result = match ($event->provider) {
            'stripe' => $this->stripeBilling->receiveVerified($event->payload),
            'paddle' => $this->paddleBilling->receiveVerified($event->payload),
            default => throw ValidationException::withMessages(['provider' => 'The billing provider has no reviewed replay adapter.']),
        };

        return $result->status;
    }

    private function replayPayment(PaymentProviderEvent $event): string
    {
        $result = $this->payments->ingest($event->provider, ['event_id' => $event->provider_event_id, 'event_type' => $event->event_type, 'created_at' => $event->provider_created_at, 'payload' => $event->payload]);

        return $result->processing_status;
    }

    private function replayCommunication(CommunicationMessage $message, string $reason): string
    {
        return $this->communications->replay($message, $reason)->status;
    }
}
