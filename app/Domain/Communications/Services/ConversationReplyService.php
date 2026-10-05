<?php

namespace App\Domain\Communications\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Models\CommunicationConversation;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationReplyService
{
    public function __construct(
        private readonly EntitlementEvaluator $entitlements,
        private readonly CommunicationTemplateService $templates,
        private readonly NotificationIntentService $notifications,
    ) {}

    public function send(Business $business, CommunicationConversation $conversation, string $body, string $requestId): CommunicationIntent
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 1000) {
            throw ValidationException::withMessages(['body' => 'A reply between 1 and 1,000 characters is required.']);
        }
        if (! Str::isUuid($requestId)) {
            throw ValidationException::withMessages(['client_request_id' => 'A valid reply request identifier is required.']);
        }
        if ($conversation->business_id !== $business->id) {
            abort(404);
        }
        $settings = $this->templates->settings($business);
        $sender = $conversation->senderProfile;
        if (! $this->entitlements->decide($business, 'messaging.two_way')->allowed
            || $settings->sender_mode !== 'branded'
            || ! $settings->two_way_enabled
            || ! $sender
            || $sender->mode !== 'branded'
            || $sender->status !== 'active'
            || $sender->channel !== $conversation->channel) {
            throw ValidationException::withMessages(['body' => 'Direct replies require an active branded client line.']);
        }
        if (! $conversation->client_id) {
            throw ValidationException::withMessages(['body' => 'Match this conversation to a client before replying.']);
        }
        if ($conversation->channel === 'whatsapp' && (! $conversation->last_message_at || $conversation->last_message_at->lt(now()->subHours(24)))) {
            throw ValidationException::withMessages(['body' => 'The WhatsApp reply window has closed. Send an approved notification template instead.']);
        }

        return $this->notifications->create(new CommunicationIntentData(
            businessId: $business->id,
            eventKey: 'conversation:'.$conversation->public_id.':reply:'.$requestId,
            eventType: 'conversation.reply',
            intentType: 'conversation_reply',
            category: 'transactional',
            legalBasis: 'user_requested_service',
            locale: $business->locale ?: 'en-US',
            timeZone: $business->time_zone ?: 'UTC',
            scheduledForUtc: now()->toImmutable(),
            recipients: [$conversation->channel => $conversation->participant],
            variables: ['reply_body' => $body],
            clientId: $conversation->client_id,
            sourceType: CommunicationConversation::class,
            sourceId: $conversation->id,
            correlationId: $requestId,
        ));
    }
}
