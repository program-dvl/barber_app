<?php

namespace App\Domain\Communications\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\EntitlementUsageManager;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationActionLink;
use App\Domain\Communications\Models\CommunicationConversation;
use App\Domain\Communications\Models\CommunicationDeliveryAttempt;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommunicationDeliveryService
{
    public function __construct(
        private readonly EmailChannelProvider $email,
        private readonly MobileChannelProvider $mobile,
        private readonly CommunicationTemplateRenderer $renderer,
        private readonly CommunicationActionLinkService $links,
        private readonly CommunicationConsentService $consent,
        private readonly CommunicationTemplateService $templates,
        private readonly EntitlementUsageManager $usage,
        private readonly EntitlementEvaluator $entitlements,
        private readonly CommunicationFallbackService $fallbacks,
    ) {}

    public function deliver(CommunicationMessage|int $message): CommunicationMessage
    {
        $messageId = $message instanceof CommunicationMessage ? $message->id : $message;
        $attempt = DB::transaction(function () use ($messageId): ?CommunicationDeliveryAttempt {
            $current = CommunicationMessage::query()->with(['intent', 'template', 'actionLink'])->lockForUpdate()->findOrFail($messageId);
            // Only a due queued/retried message owns permission to enter the
            // provider call. Duplicate queue deliveries and concurrent sweeps
            // observe `sending` and return without contacting the provider.
            if (! in_array($current->status, ['queued', 'retried'], true)) {
                return null;
            }
            if ($current->next_attempt_at && $current->next_attempt_at->isFuture()) {
                return null;
            }
            if ($current->channel === 'email' && $current->attempt_count > 0 && $current->provider
                && $current->provider !== $this->email->name()
                && ! in_array($current->last_error_code, ['provider_not_configured', 'resend_http_429', 'ses_throttled', 'ses_authentication_failed', 'ses_sender_rejected', 'ses_configuration_set_missing', 'ses_sending_paused'], true)) {
                $current->update(['status' => 'failed', 'failed_at' => now(), 'last_error_code' => 'provider_changed_requires_review', 'last_error_class' => 'terminal', 'next_attempt_at' => null]);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->channel !== 'email' && ! in_array($current->channel, config('communications.client_mobile_channels', ['sms']), true)) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => 'channel_paused', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->channel !== 'email' && $current->category === 'marketing' && ! config('communications.mobile_marketing_enabled', false)) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => 'mobile_marketing_paused', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            $decision = $this->consent->decision(
                $this->templates->settings($current->business_id), $current->client,
                $current->channel, $current->recipient, $current->category, $current->legal_basis,
            );
            if (! $decision['allowed']) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => $decision['reason'], 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($this->preferenceWithdrawn($current)) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => 'channel_preference_withdrawn', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($this->obsoleteReminder($current)) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => 'source_cancelled_or_rescheduled', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->channel === 'sms' && ! CommunicationSenderProfile::query()
                ->where('business_id', $current->business_id)->where('channel', 'sms')->where('status', 'active')
                ->whereKey($current->communication_sender_profile_id)->exists()) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => 'sender_not_ready', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($reason = $this->invalidConversationReply($current)) {
                $updates = ['status' => 'suppressed', 'suppression_reason' => $reason, 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->attempt_count >= $current->max_attempts) {
                $updates = ['status' => 'failed', 'failed_at' => now(), 'last_error_code' => 'retry_limit_reached', 'last_error_class' => 'terminal', 'next_attempt_at' => null];
                $this->releaseMobileAllowance($current, $updates);
                $current->update($updates);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->channel !== 'email' && ! $current->entitlement_charged_at) {
                if ($current->channel === 'sms' && $current->business->subscription?->capacity_snapshot) {
                    $snapshot = $current->business->subscription->capacity_snapshot;
                    try {
                        $rendered = $this->renderer->render($current->template, $this->deliveryVariables($current));
                    } catch (Throwable) {
                        $current->update(['status' => 'failed', 'last_error_code' => 'template_render_failed', 'last_error_class' => 'terminal', 'failed_at' => now()]);
                        $this->refreshIntent($current);

                        return null;
                    }
                    $quantity = app(SmsSegmentCounter::class)->credits($rendered['body'], $current->recipient, $snapshot['sms_routes']);
                    if ($quantity === null) {
                        $current->update(['status' => 'suppressed', 'suppression_reason' => 'destination_not_included', 'next_attempt_at' => null]);
                        $this->refreshIntent($current);

                        return null;
                    }
                    $reserved = app(SmsCreditWallet::class)->reserve($current, $quantity);
                } else {
                    $reserved = $this->usage->reserve($current->business, 'messaging.monthly_allowance');
                }
                if (! $reserved) {
                    $current->update(['status' => 'suppressed', 'suppression_reason' => 'plan_limit', 'next_attempt_at' => null]);
                    $this->refreshIntent($current);

                    return null;
                }
                $current->entitlement_charged_at = now();
            }
            $number = $current->attempt_count + 1;
            $provider = $current->channel === 'email' ? $this->email->name() : $this->mobile->name();
            $current->update(['status' => 'sending', 'attempt_count' => $number, 'provider' => $provider, 'next_attempt_at' => null, 'entitlement_charged_at' => $current->entitlement_charged_at]);

            return CommunicationDeliveryAttempt::query()->create([
                'business_id' => $current->business_id, 'communication_message_id' => $current->id,
                'attempt_number' => $number, 'idempotency_key' => $current->idempotency_key,
                'status' => 'started', 'provider' => $provider, 'started_at' => now(),
            ]);
        }, 3);
        if (! $attempt) {
            return CommunicationMessage::query()->findOrFail($messageId);
        }

        $message = CommunicationMessage::query()->with(['intent', 'template', 'actionLink'])->findOrFail($messageId);
        try {
            $variables = $this->deliveryVariables($message);
            $rendered = $this->renderer->render($message->template, $variables);
            $outbound = new OutboundCommunication(
                $message->recipient, $rendered['subject'], $rendered['body'], $message->idempotency_key,
                $message->intent->correlation_id, $message->template->provider_template_id, $rendered['variables'],
                $message->channel, $message->business_id, $message->communication_sender_profile_id,
                $message->intent->intent_type === 'conversation_reply',
            );
            $result = $message->channel === 'email' ? $this->email->send($outbound) : $this->mobile->send($outbound);
            DB::transaction(function () use ($message, $attempt, $result, $rendered): void {
                $current = CommunicationMessage::query()->lockForUpdate()->findOrFail($message->id);
                $current->update([
                    'status' => 'sent', 'provider_message_id' => $result->providerMessageId,
                    'provider_state_at' => now(), 'sent_at' => now(), 'failed_at' => null,
                    'last_error_code' => null, 'last_error_class' => null,
                    'subject_hash' => hash('sha256', $rendered['subject']), 'body_hash' => hash('sha256', $rendered['body']),
                ]);
                $attempt->update(['status' => 'sent', 'provider_request_id' => $result->providerRequestId, 'provider_message_id' => $result->providerMessageId, 'finished_at' => now()]);
                $this->refreshIntent($current);
            });
        } catch (CommunicationProviderException $error) {
            $this->recordFailure($message, $attempt, $error->safeCode, $error->retryable, true);
            $this->activateFallbackIfFailed($message);
            if ($error->retryable && $message->attempt_count < $message->max_attempts) {
                throw $error;
            }
        } catch (ValidationException $error) {
            $this->recordFailure($message, $attempt, 'template_render_failed', false, true);
            $this->activateFallbackIfFailed($message);
        } catch (Throwable $error) {
            $retryable = $message->channel === 'email' && $message->provider !== 'ses';
            $this->recordFailure($message, $attempt, 'unexpected_provider_error', $retryable, false);
            $this->activateFallbackIfFailed($message);
            if ($retryable && $message->attempt_count < $message->max_attempts) {
                throw $error;
            }
        }

        return $message->fresh(['attempts', 'intent']);
    }

    /** @return array<string,mixed> */
    private function deliveryVariables(CommunicationMessage $message): array
    {
        $variables = $message->template_variables ?? [];
        if ($id = $variables['__action_link_id'] ?? null) {
            $link = CommunicationActionLink::query()->where('business_id', $message->business_id)->findOrFail($id);
            $this->links->assertUsable($link);
            $key = $link->purpose === 'feedback' ? 'feedback_link' : 'action_link';
            $variables[$key] = $this->links->url($link);
        }
        if ($id = $variables['__unsubscribe_link_id'] ?? null) {
            $link = CommunicationActionLink::query()->where('business_id', $message->business_id)->findOrFail($id);
            $this->links->assertUsable($link);
            $variables['unsubscribe_link'] = $this->links->url($link);
        }
        unset($variables['__action_link_id'], $variables['__unsubscribe_link_id']);

        return $variables;
    }

    private function recordFailure(CommunicationMessage $message, CommunicationDeliveryAttempt $attempt, string $code, bool $retryable, bool $releaseTerminalAllowance): void
    {
        DB::transaction(function () use ($message, $attempt, $code, $retryable, $releaseTerminalAllowance): void {
            $current = CommunicationMessage::query()->lockForUpdate()->findOrFail($message->id);
            $willRetry = $retryable && $current->attempt_count < $current->max_attempts;
            $delay = [1 => 60, 2 => 300, 3 => 900][$current->attempt_count] ?? 1800;
            $updates = [
                'status' => $willRetry ? 'retried' : 'failed', 'last_error_code' => $code,
                'last_error_class' => $willRetry ? 'transient' : 'terminal',
                'next_attempt_at' => $willRetry ? now()->addSeconds($delay) : null,
                'failed_at' => $willRetry ? null : now(),
            ];
            if (! $willRetry && $releaseTerminalAllowance) {
                $this->releaseMobileAllowance($current, $updates);
            }
            $current->update($updates);
            $attempt->update(['status' => $willRetry ? 'retried' : 'failed', 'error_code' => $code, 'error_class' => $willRetry ? 'transient' : 'terminal', 'finished_at' => now()]);
            $this->refreshIntent($current);
        });
    }

    /** @param array<string, mixed> $updates */
    private function releaseMobileAllowance(CommunicationMessage $message, array &$updates): void
    {
        if ($message->channel === 'email' || ! $message->entitlement_charged_at) {
            return;
        }

        if (DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->exists()) {
            app(SmsCreditWallet::class)->release($message);
        } else {
            $this->usage->release($message->business, 'messaging.monthly_allowance', $message->entitlement_charged_at);
        }
        $updates['entitlement_charged_at'] = null;
    }

    private function obsoleteReminder(CommunicationMessage $message): bool
    {
        if ($message->intent->intent_type !== 'appointment_reminder' || $message->intent->source_type !== Appointment::class) {
            return false;
        }
        $appointment = Appointment::query()->where('business_id', $message->business_id)->find($message->intent->source_id);

        if (! $appointment || $appointment->starts_at_utc->lessThanOrEqualTo(now())) {
            return true;
        }
        if (preg_match('/^appointment:\d+:reminder:(\d+):\d+$/', $message->intent->event_key, $match)
            && (int) $match[1] !== $appointment->starts_at_utc->getTimestamp()) {
            return true;
        }

        return in_array($appointment->status, ['cancelled_by_client', 'cancelled_by_shop', 'rescheduled', 'completed', 'no_show'], true);
    }

    private function preferenceWithdrawn(CommunicationMessage $message): bool
    {
        $preferences = $message->client?->communication_preferences ?? [];
        if (($preferences[$message->channel] ?? null) === false) {
            return true;
        }
        if ($message->intent->source_type !== Appointment::class) {
            return false;
        }
        $appointment = Appointment::query()->where('business_id', $message->business_id)->find($message->intent->source_id);

        return $appointment && (($appointment->communication_preferences[$message->channel] ?? null) === false);
    }

    private function invalidConversationReply(CommunicationMessage $message): ?string
    {
        if ($message->intent->intent_type !== 'conversation_reply') {
            return null;
        }
        if ($message->intent->source_type !== CommunicationConversation::class) {
            return 'conversation_reply_invalid';
        }
        $conversation = CommunicationConversation::query()->where('business_id', $message->business_id)
            ->with('senderProfile')->find($message->intent->source_id);
        $settings = $this->templates->settings($message->business_id);
        if (! $conversation || $conversation->client_id !== $message->client_id || $conversation->channel !== $message->channel
            || $conversation->communication_sender_profile_id !== $message->communication_sender_profile_id
            || $conversation->senderProfile?->mode !== 'branded' || $conversation->senderProfile?->status !== 'active'
            || $settings->sender_mode !== 'branded' || ! $settings->two_way_enabled
            || ! $this->entitlements->decide($message->business, 'messaging.two_way')->allowed) {
            return 'conversation_reply_unavailable';
        }
        if ($message->channel === 'whatsapp' && (! $conversation->last_message_at || $conversation->last_message_at->lt(now()->subHours(24)))) {
            return 'conversation_reply_window_closed';
        }

        return null;
    }

    private function refreshIntent(CommunicationMessage $message): void
    {
        $intent = $message->intent()->first();
        $states = $intent->messages()->pluck('status');
        $status = $states->contains('delivered') ? 'delivered'
            : ($states->every(fn ($state) => $state === 'suppressed') ? 'suppressed'
                : ($states->contains('failed') && ! $states->contains(fn ($state) => in_array($state, ['queued', 'sending', 'retried', 'sent'], true)) ? 'failed'
                    : ($states->contains('sent') ? 'sent' : 'queued')));
        $intent->update(['status' => $status]);
    }

    private function activateFallbackIfFailed(CommunicationMessage $message): void
    {
        $fresh = $message->fresh();
        if ($fresh->status === 'failed') {
            $this->fallbacks->activateAfterDefinitiveFailure($fresh);
        }
    }
}
