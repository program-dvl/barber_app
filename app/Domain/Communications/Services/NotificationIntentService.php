<?php

namespace App\Domain\Communications\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\PlatformAccess\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationIntentService
{
    public function __construct(
        private readonly CommunicationTemplateService $templates,
        private readonly CommunicationConsentService $consent,
        private readonly CommunicationActionLinkService $links,
    ) {}

    public function create(CommunicationIntentData $data, bool $dispatch = true): CommunicationIntent
    {
        $business = Business::query()->findOrFail($data->businessId);
        $settings = $this->templates->settings($business);
        $client = $data->clientId ? Client::query()->where('business_id', $business->id)->findOrFail($data->clientId) : null;
        $correlation = $data->correlationId ?? (string) Str::uuid();

        $intent = DB::transaction(function () use ($data, $business, $settings, $client, $correlation): CommunicationIntent {
            $intent = CommunicationIntent::query()->firstOrCreate([
                'business_id' => $business->id, 'event_key' => $data->eventKey, 'intent_type' => $data->intentType,
            ], [
                'client_id' => $client?->id, 'source_type' => $data->sourceType, 'source_id' => $data->sourceId,
                'event_type' => $data->eventType, 'category' => $data->category, 'legal_basis' => $data->legalBasis,
                'locale' => $data->locale, 'time_zone' => $data->timeZone, 'scheduled_for_utc' => $data->scheduledForUtc->utc(),
                'local_scheduled_for' => $data->scheduledForUtc->setTimezone($data->timeZone)->format('Y-m-d H:i:s P'),
                'status' => 'queued', 'correlation_id' => $correlation,
            ]);
            if (! $intent->wasRecentlyCreated) {
                return $intent->load('messages');
            }

            $target = $this->target($data);
            foreach ($data->recipients as $channel => $destination) {
                if (! in_array($channel, ['email', 'whatsapp'], true) || blank($destination)) {
                    continue;
                }
                $template = $this->templates->resolve($business->id, $data->intentType, $channel, $data->locale);
                $decision = $this->consent->decision($settings, $client, $channel, $destination, $data->category, $data->legalBasis);
                $variables = $data->variables;
                $actionLink = null;
                $purpose = $data->actionPurpose ?? TemplateVariableCatalog::defaults($data->intentType)['action_purpose'];
                if ($purpose) {
                    $actionLink = $this->links->issue($business->id, $client, $purpose, $target, $this->expiry($data->intentType));
                    $variables['__action_link_id'] = $actionLink->id;
                }
                if ($data->category === 'marketing' && $client) {
                    $unsubscribe = $this->links->issue($business->id, $client, 'marketing_unsubscribe_'.$channel, $client, now()->addDays(30)->toImmutable());
                    $variables['__unsubscribe_link_id'] = $unsubscribe->id;
                }
                $recipientHash = CommunicationConsentService::destinationHash($channel, $destination);
                $key = hash('sha256', implode('|', [$business->id, $data->eventKey, $data->intentType, $channel, $recipientHash]));
                $status = $decision['allowed'] && $template->status === 'published' ? 'queued' : 'suppressed';
                $reason = $decision['reason'] ?? ($template->status !== 'published' ? 'template_not_published' : null);
                CommunicationMessage::query()->firstOrCreate(['idempotency_key' => $key], [
                    'business_id' => $business->id, 'communication_intent_id' => $intent->id, 'client_id' => $client?->id,
                    'communication_template_id' => $template->id, 'communication_action_link_id' => $actionLink?->id,
                    'channel' => $channel, 'recipient_hash' => $recipientHash, 'recipient' => $destination,
                    'category' => $data->category, 'legal_basis' => $decision['basis'], 'locale' => $data->locale,
                    'time_zone' => $data->timeZone, 'template_variables' => $variables, 'status' => $status,
                    'suppression_reason' => $reason, 'queued_at' => now(), 'next_attempt_at' => $status === 'queued' ? $data->scheduledForUtc->utc() : null,
                ]);
            }
            if (! $intent->messages()->where('status', 'queued')->exists()) {
                $intent->update(['status' => 'suppressed']);
            }

            return $intent->load('messages');
        }, 3);

        if ($dispatch && $intent->wasRecentlyCreated) {
            foreach ($intent->messages->where('status', 'queued') as $message) {
                DeliverCommunicationMessage::dispatch($message->id, $correlation)->delay($intent->scheduled_for_utc)->afterCommit();
            }
        }

        return $intent;
    }

    private function target(CommunicationIntentData $data): ?Model
    {
        if (! $data->sourceType || ! $data->sourceId || ! is_a($data->sourceType, Model::class, true)) {
            return null;
        }

        return $data->sourceType::query()->where('business_id', $data->businessId)->find($data->sourceId);
    }

    private function expiry(string $intent): CarbonImmutable
    {
        return now()->addMinutes(in_array($intent, ['waitlist_opening', 'deposit_request'], true) ? 60 : 1440)->toImmutable();
    }
}
