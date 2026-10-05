<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationMessage;
use Illuminate\Support\Facades\DB;

class CommunicationFallbackService
{
    public function __construct(
        private readonly CommunicationConsentService $consent,
        private readonly CommunicationTemplateService $templates,
    ) {}

    public function activateAfterDefinitiveFailure(CommunicationMessage $failed): ?CommunicationMessage
    {
        if ($failed->fresh()->status !== 'failed') {
            return null;
        }

        $fallback = DB::transaction(function () use ($failed): ?CommunicationMessage {
            $candidate = CommunicationMessage::query()->with(['client', 'senderProfile', 'template', 'intent'])
                ->where('fallback_of_message_id', $failed->id)->where('status', 'held')->lockForUpdate()->first();
            if (! $candidate) {
                return null;
            }
            $decision = $this->consent->decision(
                $this->templates->settings($candidate->business_id), $candidate->client,
                $candidate->channel, $candidate->recipient, $candidate->category, $candidate->legal_basis,
            );
            if (! $decision['allowed'] || $candidate->template->status !== 'published' || $candidate->senderProfile?->status !== 'active') {
                $candidate->update([
                    'status' => 'suppressed',
                    'suppression_reason' => $decision['reason'] ?? 'fallback_not_ready',
                    'next_attempt_at' => null,
                ]);

                return null;
            }
            $candidate->update([
                'status' => 'queued',
                'suppression_reason' => null,
                'next_attempt_at' => now(),
            ]);

            return $candidate;
        }, 3);

        if ($fallback) {
            DeliverCommunicationMessage::dispatch($fallback->id, $fallback->intent->correlation_id)->afterCommit();
        }

        return $fallback;
    }
}
