<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationActionLink;
use App\Domain\Communications\Models\CommunicationDeliveryAttempt;
use App\Domain\Communications\Models\CommunicationMessage;
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
    ) {}

    public function deliver(CommunicationMessage|int $message): CommunicationMessage
    {
        $messageId = $message instanceof CommunicationMessage ? $message->id : $message;
        $attempt = DB::transaction(function () use ($messageId): ?CommunicationDeliveryAttempt {
            $current = CommunicationMessage::query()->with(['intent', 'template', 'actionLink'])->lockForUpdate()->findOrFail($messageId);
            if (in_array($current->status, ['sent', 'delivered', 'suppressed'], true)) {
                return null;
            }
            if ($current->next_attempt_at && $current->next_attempt_at->isFuture()) {
                return null;
            }
            $decision = $this->consent->decision(
                $this->templates->settings($current->business_id), $current->client,
                $current->channel, $current->recipient, $current->category, $current->legal_basis,
            );
            if (! $decision['allowed']) {
                $current->update(['status' => 'suppressed', 'suppression_reason' => $decision['reason'], 'next_attempt_at' => null]);
                $this->refreshIntent($current);

                return null;
            }
            if ($this->obsoleteReminder($current)) {
                $current->update(['status' => 'suppressed', 'suppression_reason' => 'source_cancelled_or_rescheduled', 'next_attempt_at' => null]);
                $this->refreshIntent($current);

                return null;
            }
            if ($current->attempt_count >= $current->max_attempts) {
                $current->update(['status' => 'failed', 'failed_at' => now(), 'last_error_code' => 'retry_limit_reached', 'last_error_class' => 'terminal', 'next_attempt_at' => null]);
                $this->refreshIntent($current);

                return null;
            }
            $number = $current->attempt_count + 1;
            $provider = $current->channel === 'email' ? $this->email->name() : $this->mobile->name();
            $current->update(['status' => 'sending', 'attempt_count' => $number, 'provider' => $provider, 'next_attempt_at' => null]);

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
            $this->recordFailure($message, $attempt, $error->safeCode, $error->retryable);
            if ($error->retryable && $message->attempt_count < $message->max_attempts) {
                throw $error;
            }
        } catch (ValidationException $error) {
            $this->recordFailure($message, $attempt, 'template_render_failed', false);
        } catch (Throwable $error) {
            $retryable = $message->channel === 'email';
            $this->recordFailure($message, $attempt, 'unexpected_provider_error', $retryable);
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

    private function recordFailure(CommunicationMessage $message, CommunicationDeliveryAttempt $attempt, string $code, bool $retryable): void
    {
        DB::transaction(function () use ($message, $attempt, $code, $retryable): void {
            $current = CommunicationMessage::query()->lockForUpdate()->findOrFail($message->id);
            $willRetry = $retryable && $current->attempt_count < $current->max_attempts;
            $delay = [1 => 60, 2 => 300, 3 => 900][$current->attempt_count] ?? 1800;
            $current->update([
                'status' => $willRetry ? 'retried' : 'failed', 'last_error_code' => $code,
                'last_error_class' => $willRetry ? 'transient' : 'terminal',
                'next_attempt_at' => $willRetry ? now()->addSeconds($delay) : null,
                'failed_at' => $willRetry ? null : now(),
            ]);
            $attempt->update(['status' => $willRetry ? 'retried' : 'failed', 'error_code' => $code, 'error_class' => $willRetry ? 'transient' : 'terminal', 'finished_at' => now()]);
            $this->refreshIntent($current);
        });
    }

    private function obsoleteReminder(CommunicationMessage $message): bool
    {
        if ($message->intent->intent_type !== 'appointment_reminder' || $message->intent->source_type !== Appointment::class) {
            return false;
        }
        $status = Appointment::query()->where('business_id', $message->business_id)->whereKey($message->intent->source_id)->value('status');

        return in_array($status, ['cancelled_by_client', 'cancelled_by_shop', 'rescheduled'], true);
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
}
