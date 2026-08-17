<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Contracts\MobileChannelProvider;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationDeliveryAttempt;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationProviderEvent;
use App\Domain\Communications\Models\CommunicationSuppression;
use App\Domain\Communications\Services\CommunicationActionLinkService;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationProviderCallbackService;
use App\Domain\Communications\Services\CommunicationScheduleService;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\NotificationIntentService;
use App\Domain\Communications\Services\TemplateVariableCatalog;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

class RecordingEmailProvider implements EmailChannelProvider
{
    /** @var list<OutboundCommunication> */
    public array $calls = [];

    /** @var list<CommunicationProviderException> */
    public array $failures = [];

    public function name(): string
    {
        return 'recording-email';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $this->calls[] = $message;
        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }

        return new ProviderSendResult('email-message-'.count($this->calls), 'email-request-'.count($this->calls));
    }
}

class RecordingMobileProvider implements MobileChannelProvider
{
    /** @var list<OutboundCommunication> */
    public array $calls = [];

    /** @var list<Throwable> */
    public array $failures = [];

    public function name(): string
    {
        return 'recording-mobile';
    }

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        $this->calls[] = $message;
        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }

        return new ProviderSendResult('whatsapp-message-'.count($this->calls));
    }
}

beforeEach(function () {
    $this->emailProvider = new RecordingEmailProvider;
    $this->mobileProvider = new RecordingMobileProvider;
    app()->instance(EmailChannelProvider::class, $this->emailProvider);
    app()->instance(MobileChannelProvider::class, $this->mobileProvider);
    Queue::fake();
});

/** @return array{business:Business,location:Location,client:Client,appointment:Appointment} */
function communicationFixture(array $clientOverrides = [], ?Business $business = null): array
{
    $business ??= Business::factory()->create([
        'country_code' => 'IN', 'locale' => 'en-IN', 'currency_code' => 'INR', 'time_zone' => 'Asia/Kolkata',
    ]);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    $client = Client::factory()->create([
        'business_id' => $business->id, 'email' => 'client@example.test', 'normalized_email' => 'client@example.test',
        'mobile' => '+919999999999', 'normalized_mobile' => '919999999999',
        'communication_preferences' => ['email', 'whatsapp'], ...$clientOverrides,
    ]);
    $appointment = Appointment::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'client_id' => $client->id,
        'idempotency_key' => 'communications-fixture-'.str()->uuid(), 'request_hash' => hash('sha256', (string) str()->uuid()),
        'status' => 'confirmed', 'source' => 'online', 'client_name' => $client->name, 'client_email' => $client->email,
        'client_mobile' => $client->mobile, 'communication_preferences' => ['email', 'whatsapp'],
        'starts_at_utc' => CarbonImmutable::parse('2026-11-02 05:30:00', 'UTC'), 'ends_at_utc' => CarbonImmutable::parse('2026-11-02 06:30:00', 'UTC'),
        'time_zone' => 'America/New_York', 'local_starts_at' => '2026-11-02 00:30:00 -05:00', 'local_ends_at' => '2026-11-02 01:30:00 -05:00',
        'price_minor' => 3500, 'currency_code' => 'INR', 'confirmed_at' => now(),
    ]);

    return compact('business', 'location', 'client', 'appointment');
}

function publishWhatsAppTemplate(Business $business, string $intent): void
{
    $templates = app(CommunicationTemplateService::class);
    $defaults = TemplateVariableCatalog::defaults($intent);
    $template = $templates->save($business, $intent, 'whatsapp', 'en-IN', $defaults['subject'], $defaults['body'], [], 'HX_APPROVED_'.$intent, 'approved');
    $templates->publish($business, $template);
}

function intentFor(array $fixture, string $eventKey, string $intent = 'booking_confirmation', array $recipients = []): CommunicationIntentData
{
    return new CommunicationIntentData(
        $fixture['business']->id, $eventKey, 'appointment.created', $intent,
        in_array($intent, ['feedback_request', 'rebooking_reminder'], true) ? 'marketing' : 'transactional',
        'contract_performance', 'en-IN', 'America/New_York', now()->toImmutable(),
        $recipients ?: ['email' => $fixture['client']->email, 'whatsapp' => $fixture['client']->mobile],
        [
            'client_name' => $fixture['client']->name, 'service_name' => 'Signature cut', 'location_name' => $fixture['location']->name,
            'appointment_date' => '2 November 2026', 'appointment_time' => '00:30', 'time_zone' => 'America/New_York',
            'booking_reference' => $fixture['appointment']->booking_reference, 'business_name' => $fixture['business']->name,
        ], $fixture['client']->id, Appointment::class, $fixture['appointment']->id, '123e4567-e89b-12d3-a456-426614174000',
    );
}

it('creates at most one message per intended event channel and recipient after duplicate domain events', function () {
    $fixture = communicationFixture();
    ClientConsent::query()->create([
        'business_id' => $fixture['business']->id, 'client_id' => $fixture['client']->id, 'type' => 'whatsapp',
        'status' => 'granted', 'source' => 'booking', 'occurred_at' => now(),
    ]);
    publishWhatsAppTemplate($fixture['business'], 'booking_confirmation');
    $service = app(NotificationIntentService::class);
    $first = $service->create(intentFor($fixture, 'appointment:event-1'));
    $second = $service->create(intentFor($fixture, 'appointment:event-1'));

    expect($second->id)->toBe($first->id)
        ->and(CommunicationIntent::query()->count())->toBe(1)
        ->and(CommunicationMessage::query()->count())->toBe(2)
        ->and(CommunicationMessage::query()->pluck('channel')->sort()->values()->all())->toBe(['email', 'whatsapp'])
        ->and(CommunicationMessage::query()->pluck('idempotency_key')->unique()->count())->toBe(2);
    Queue::assertPushed(DeliverCommunicationMessage::class, 2);
});

it('validates allow-listed templates, uses safe fallbacks, and denies cross-tenant template access', function () {
    $first = communicationFixture();
    $second = communicationFixture();
    $templates = app(CommunicationTemplateService::class);
    $template = $templates->save($first['business'], 'booking_pending', 'email', 'en-IN', 'Hello {{client_name}}', 'Your appointment is with {{staff_name}}.');
    $preview = $templates->preview($first['business'], $template, []);
    expect($preview['subject'])->toBe('Hello there')->and($preview['body'])->toContain('your professional');
    expect(fn () => $templates->save($first['business'], 'booking_pending', 'email', 'en-IN', 'Hi', 'Secret {{internal_note}}'))
        ->toThrow(ValidationException::class, 'Unsupported template variables');
    $required = $templates->save($first['business'], 'booking_pending', 'email', 'en-IN', 'Review', 'Open {{action_link}}');
    expect(fn () => $templates->preview($first['business'], $required, []))->toThrow(ValidationException::class, 'A value is required');
    $draftLocale = $templates->save($first['business'], 'booking_confirmation', 'email', 'fr-FR', 'Brouillon', 'Brouillon');
    expect($templates->resolve($first['business']->id, 'booking_confirmation', 'email', 'fr-FR')->id)
        ->not->toBe($draftLocale->id);
    expect(fn () => $templates->preview($second['business'], $template, []))->toThrow(HttpException::class);
});

it('calculates quiet-hour boundaries and preserves explicit offsets across daylight-saving transitions', function () {
    $schedule = app(CommunicationScheduleService::class);
    $atStart = CarbonImmutable::parse('2026-08-14 21:00:00', 'Asia/Kolkata');
    $atEnd = CarbonImmutable::parse('2026-08-15 08:00:00', 'Asia/Kolkata');
    expect($schedule->outsideQuietHours($atStart, '21:00:00', '08:00:00')->format('Y-m-d H:i P'))->toBe('2026-08-15 08:00 +05:30')
        ->and($schedule->outsideQuietHours($atEnd, '21:00:00', '08:00:00')->equalTo($atEnd))->toBeTrue();

    $springGap = CarbonImmutable::parse('2026-03-08 02:30:00', 'America/New_York');
    $fallFirst = CarbonImmutable::parse('2026-11-01 01:30:00 -04:00')->setTimezone('America/New_York');
    expect($springGap->format('H:i P'))->toBe('03:30 -04:00')
        ->and($schedule->outsideQuietHours($fallFirst, '00:00:00', '00:00:00')->format('H:i P'))->toBe('01:30 -04:00');

    $fixture = communicationFixture();
    $settings = app(CommunicationTemplateService::class)->settings($fixture['business']);
    $settings->update(['quiet_hours_start' => '21:00', 'quiet_hours_end' => '08:00']);
    expect($schedule->reminderTime($fixture['appointment'], 120, $settings)->setTimezone('America/New_York')->format('Y-m-d H:i P'))
        ->toBe('2026-11-01 20:59 -05:00');
});

it('separates transactional necessity, WhatsApp opt-in, marketing consent, unsubscribe, and suppression', function () {
    $fixture = communicationFixture(['marketing_status' => 'unknown']);
    $templates = app(CommunicationTemplateService::class);
    $settings = $templates->settings($fixture['business']);
    $settings->update(['marketing_enabled' => true]);
    $consent = app(CommunicationConsentService::class);

    expect($consent->decision($settings, $fixture['client'], 'email', $fixture['client']->email, 'transactional', 'contract_performance')['allowed'])->toBeTrue()
        ->and($consent->decision($settings, $fixture['client'], 'whatsapp', $fixture['client']->mobile, 'transactional', 'contract_performance')['reason'])->toBe('whatsapp_opt_in_missing')
        ->and($consent->decision($settings, $fixture['client'], 'email', $fixture['client']->email, 'marketing', 'explicit_marketing_consent')['reason'])->toBe('marketing_consent_missing');

    $fixture['client']->update(['marketing_status' => 'subscribed']);
    $consent->recordWhatsAppOptIn($fixture['client'], 'booking', $fixture['appointment']->id);
    expect($consent->decision($settings, $fixture['client']->fresh(), 'whatsapp', $fixture['client']->mobile, 'transactional', 'contract_performance')['allowed'])->toBeTrue()
        ->and($consent->decision($settings, $fixture['client']->fresh(), 'email', $fixture['client']->email, 'marketing', 'explicit_marketing_consent')['allowed'])->toBeTrue();

    $consent->unsubscribe($fixture['client']->fresh(), 'email');
    expect($consent->decision($settings, $fixture['client']->fresh(), 'email', $fixture['client']->email, 'marketing', 'explicit_marketing_consent')['allowed'])->toBeFalse()
        ->and($consent->decision($settings, $fixture['client']->fresh(), 'email', $fixture['client']->email, 'transactional', 'legal_obligation_receipt')['allowed'])->toBeTrue()
        ->and(CommunicationSuppression::query()->where('scope', 'marketing')->exists())->toBeTrue();
});

it('bounds provider retries, reuses one provider idempotency key, and recovers after an outage', function () {
    $fixture = communicationFixture();
    $intent = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:outage', recipients: ['email' => $fixture['client']->email]), false);
    $message = $intent->messages->first();
    $this->emailProvider->failures = [
        new CommunicationProviderException('provider_unavailable', true),
        new CommunicationProviderException('provider_unavailable', true),
    ];
    $delivery = app(CommunicationDeliveryService::class);
    foreach ([1, 2] as $attempt) {
        expect(fn () => $delivery->deliver($message->fresh()))->toThrow(CommunicationProviderException::class);
        $message->refresh()->update(['next_attempt_at' => now()->subSecond()]);
        expect($message->fresh()->status)->toBe('retried');
    }
    $sent = $delivery->deliver($message->fresh());
    expect($sent->status)->toBe('sent')->and($sent->provider_message_id)->toBe('email-message-3')
        ->and(CommunicationDeliveryAttempt::query()->pluck('idempotency_key')->unique()->all())->toBe([$message->idempotency_key])
        ->and(CommunicationDeliveryAttempt::query()->pluck('status')->all())->toBe(['retried', 'retried', 'sent'])
        ->and($this->emailProvider->calls)->toHaveCount(3);

    app(CommunicationConsentService::class)->recordWhatsAppOptIn($fixture['client'], 'booking', $fixture['appointment']->id);
    publishWhatsAppTemplate($fixture['business'], 'booking_confirmation');
    $mobile = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:mobile-ambiguous', recipients: ['whatsapp' => $fixture['client']->mobile]), false)->messages->first();
    $this->mobileProvider->failures = [new RuntimeException('Ambiguous transport failure')];
    $mobile = $delivery->deliver($mobile);
    expect($mobile->status)->toBe('failed')->and($mobile->attempt_count)->toBe(1)
        ->and($mobile->last_error_code)->toBe('unexpected_provider_error')->and($mobile->next_attempt_at)->toBeNull();
});

it('deduplicates provider callbacks and ignores an older callback that would rewind delivery', function () {
    $fixture = communicationFixture();
    $message = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:callbacks', recipients: ['email' => $fixture['client']->email]), false)->messages->first();
    app(CommunicationDeliveryService::class)->deliver($message);
    $message->refresh();
    $callbacks = app(CommunicationProviderCallbackService::class);
    // Keep callback evidence later than the just-recorded send regardless of
    // the calendar date on which the suite runs.
    $deliveredAt = CarbonImmutable::now('UTC')->addMinute();
    $first = $callbacks->receive('recording-email', 'evt-delivered', $message->provider_message_id, 'email.delivered', $deliveredAt, hash('sha256', 'delivered'));
    $duplicate = $callbacks->receive('recording-email', 'evt-delivered', $message->provider_message_id, 'email.delivered', $deliveredAt, hash('sha256', 'delivered'));
    $older = $callbacks->receive('recording-email', 'evt-older-failure', $message->provider_message_id, 'email.bounced', $deliveredAt->subMinute(), hash('sha256', 'older'));

    expect($first->status)->toBe('processed')->and($duplicate->id)->toBe($first->id)
        ->and($older->status)->toBe('ignored')->and($older->last_error_code)->toBe('out_of_order')
        ->and($message->fresh()->status)->toBe('delivered')
        ->and(CommunicationProviderEvent::query()->count())->toBe(2);
});

it('rejects unsigned callbacks and accepts valid Resend and Twilio webhook signatures', function () {
    $fixture = communicationFixture();
    $message = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:webhook-signatures', recipients: ['email' => $fixture['client']->email]), false)->messages->first();
    $message->update(['provider' => 'resend', 'provider_message_id' => 'resend-webhook-message', 'status' => 'sent', 'sent_at' => now()]);

    $secretBytes = 'resend-test-secret';
    config(['communications.resend.webhook_secret' => 'whsec_'.base64_encode($secretBytes)]);
    $payload = json_encode(['type' => 'email.delivered', 'created_at' => now()->toIso8601String(), 'data' => ['email_id' => 'resend-webhook-message']], JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $eventId = 'resend-event-signed';
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $eventId.'.'.$timestamp.'.'.$payload, $secretBytes, true));
    $this->call('POST', route('communications.webhooks.resend'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_SVIX_ID' => $eventId,
        'HTTP_SVIX_TIMESTAMP' => $timestamp, 'HTTP_SVIX_SIGNATURE' => $signature,
    ], $payload)->assertNoContent();
    $this->post(route('communications.webhooks.resend'), [])->assertBadRequest();
    expect($message->fresh()->status)->toBe('delivered');

    $mobileMessage = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:twilio-signature', recipients: ['email' => 'twilio-holder@example.test']), false)->messages->first();
    $mobileMessage->update(['provider' => 'twilio', 'provider_message_id' => 'SM_SIGNED', 'status' => 'sent', 'sent_at' => now()]);
    config(['communications.twilio.auth_token' => 'twilio-test-token']);
    $params = ['MessageSid' => 'SM_SIGNED', 'MessageStatus' => 'delivered', 'ErrorCode' => ''];
    ksort($params, SORT_STRING);
    $signatureData = route('communications.webhooks.twilio');
    foreach ($params as $key => $value) {
        $signatureData .= $key.$value;
    }
    $twilioSignature = base64_encode(hash_hmac('sha1', $signatureData, 'twilio-test-token', true));
    $this->withHeaders(['X-Twilio-Signature' => $twilioSignature])->post(route('communications.webhooks.twilio'), $params)->assertNoContent();
    expect($mobileMessage->fresh()->status)->toBe('delivered');
});

it('suppresses invalid destinations and cancelled or rescheduled reminders before provider delivery', function () {
    $fixture = communicationFixture();
    $invalid = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:invalid', recipients: ['email' => 'not-an-email']), false)->messages->first();
    expect($invalid->status)->toBe('suppressed')->and($invalid->suppression_reason)->toBe('invalid_destination');

    $reminder = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:reminder', 'appointment_reminder', ['email' => $fixture['client']->email]), false)->messages->first();
    $fixture['appointment']->update(['status' => 'cancelled_by_client']);
    $result = app(CommunicationDeliveryService::class)->deliver($reminder);
    expect($result->status)->toBe('suppressed')->and($result->suppression_reason)->toBe('source_cancelled_or_rescheduled')
        ->and($this->emailProvider->calls)->toHaveCount(0);

    $fixture['appointment']->update(['status' => 'confirmed']);
    $rescheduled = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:rescheduled-reminder', 'appointment_reminder', ['email' => $fixture['client']->email]), false)->messages->first();
    $fixture['appointment']->update(['status' => 'rescheduled']);
    $rescheduled = app(CommunicationDeliveryService::class)->deliver($rescheduled);
    expect($rescheduled->status)->toBe('suppressed')->and($this->emailProvider->calls)->toHaveCount(0);
});

it('rechecks consent immediately before delivery and safely replays a diagnosed provider configuration failure', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    $fixture = communicationFixture(['marketing_status' => 'subscribed'], $business);
    $settings = app(CommunicationTemplateService::class)->settings($fixture['business']);
    $settings->update(['marketing_enabled' => true]);
    $marketing = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:marketing', 'rebooking_reminder', ['email' => $fixture['client']->email]), false)->messages->first();
    app(CommunicationConsentService::class)->unsubscribe($fixture['client']->fresh(), 'email');
    $suppressed = app(CommunicationDeliveryService::class)->deliver($marketing);
    expect($suppressed->status)->toBe('suppressed')->and($suppressed->suppression_reason)->toBe('destination_suppressed');

    $transactional = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:provider-config', recipients: ['email' => $fixture['client']->email]), false)->messages->first();
    $this->emailProvider->failures = [new CommunicationProviderException('provider_not_configured', false)];
    $failed = app(CommunicationDeliveryService::class)->deliver($transactional);
    expect($failed->status)->toBe('failed')->and($failed->last_error_class)->toBe('terminal');
    $support = app(CommunicationSupportService::class);
    $diagnostic = $support->diagnostic($failed->load('intent'));
    expect($diagnostic['content_available_to_support'])->toBeFalse()
        ->and($diagnostic)->not->toHaveKeys(['recipient', 'subject', 'body', 'template_variables']);
    $this->actingAs($user)->get(route('business.communications.messages.show', [$business, $failed]))
        ->assertOk()->assertJsonMissingPath('diagnostic.recipient');
    $other = communicationFixture();
    $otherMessage = app(NotificationIntentService::class)->create(intentFor($other, 'appointment:other-diagnostic', recipients: ['email' => $other['client']->email]), false)->messages->first();
    $this->get(route('business.communications.messages.show', [$business, $otherMessage]))->assertNotFound();
    expect(Route::has('platform.communications.show'))->toBeFalse();
    $replayed = $support->replay($failed->load(['intent', 'business']), 'Provider credentials were restored.');
    expect($replayed->status)->toBe('queued')->and($replayed->idempotency_key)->toBe($transactional->idempotency_key);
    Queue::assertPushed(DeliverCommunicationMessage::class, fn ($job) => $job->messageId === $transactional->id);

    $replayed->update(['status' => 'failed', 'attempt_count' => 4, 'max_attempts' => 4, 'last_error_code' => 'resend_http_503', 'last_error_class' => 'terminal']);
    $boundedReplay = $support->replay($replayed->fresh(['intent', 'business']), 'Provider outage is resolved.');
    expect($boundedReplay->max_attempts)->toBe(5)->and($boundedReplay->attempt_count)->toBe(4);
});

it('creates revocable purpose-bound short-lived action links and explicit tenant-aware job context', function () {
    $fixture = communicationFixture();
    $links = app(CommunicationActionLinkService::class);
    $link = $links->issue($fixture['business']->id, $fixture['client'], 'appointment_view', $fixture['appointment'], now()->addMinutes(10)->toImmutable());
    $url = $links->url($link);
    expect($url)->toContain('signature=')->and($url)->toContain($link->public_id);
    $links->revokeTarget($fixture['business']->id, $fixture['appointment'], 'appointment_view');
    expect(fn () => $links->assertUsable($link->fresh()))->toThrow(HttpException::class);

    $message = app(NotificationIntentService::class)->create(intentFor($fixture, 'appointment:job', recipients: ['email' => $fixture['client']->email]), false)->messages->first();
    $job = new DeliverCommunicationMessage($message->id, '123e4567-e89b-12d3-a456-426614174999');
    expect($job->tenantBusinessId())->toBe($fixture['business']->id)
        ->and($job->correlationId())->toBe('123e4567-e89b-12d3-a456-426614174999');
});
