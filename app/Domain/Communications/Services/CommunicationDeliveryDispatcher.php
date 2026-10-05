<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationMessage;
use Throwable;

class CommunicationDeliveryDispatcher
{
    public function dispatchDue(?int $businessId = null, int $limit = 100): int
    {
        $messages = CommunicationMessage::query()
            ->whereIn('status', ['queued', 'retried'])
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'business_id', 'communication_intent_id']);

        $messages->each(function (CommunicationMessage $message): void {
            try {
                DeliverCommunicationMessage::dispatch(
                    $message->id,
                    $message->intent()->value('correlation_id'),
                );
            } catch (Throwable $error) {
                if (config('queue.default') !== 'sync') {
                    throw $error;
                }

                report($error);
            }
        });

        return $messages->count();
    }
}
