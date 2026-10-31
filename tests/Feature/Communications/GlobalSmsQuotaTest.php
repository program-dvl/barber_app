<?php

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Billing\Services\EntitlementUsageManager;
use App\Domain\Billing\Services\SmsCreditWallet;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationSenderService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\OperationalCommunicationService;
use App\Domain\Communications\Services\SmsSegmentCounter;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    config([
        'communications.transport_mode' => 'live', 'communications.live_send_enabled' => true,
        'communications.twilio.account_sid' => 'AC'.str_repeat('a', 32),
        'communications.twilio.api_key_sid' => null, 'communications.twilio.api_key_secret' => null,
        'communications.twilio.auth_token' => 'synthetic-token',
        'communications.twilio.sms_from' => '+18555550123',
        'communications.twilio.messaging_service_sid' => 'MG'.str_repeat('b', 32),
        'communications.twilio.status_callback_url' => 'https://callbacks.example.test/communications/webhooks/twilio',
    ]);
    $this->providerPayload = ['sid' => 'SM_SYNTHETIC', 'status' => 'queued'];
    $this->providerStatus = 201;
    $this->providerFailure = null;
    Http::fake(['api.twilio.com/*' => function () {
        if ($this->providerFailure) {
            throw $this->providerFailure;
        }

        return Http::response($this->providerPayload, $this->providerStatus);
    }]);
    [, $this->business] = createTenantMembership();
    $this->subscription = activateTestSubscription($this->business);
    // Reproduce the deployed paid snapshot. It must not need a data migration.
    $this->snapshot = ['locations' => 1, 'staff' => 1, 'sms_monthly_allowance' => 100,
        'sms_routes' => ['+1' => 1], 'features' => []];
    $this->subscription->update(['capacity_snapshot' => $this->snapshot]);
});

function globalQuotaMessage($business, string $destination, string $body = 'Your appointment is confirmed.', bool $consent = true): CommunicationMessage
{
    $client = Client::factory()->create(['business_id' => $business->id, 'mobile' => $destination]);
    if ($consent) {
        app(CommunicationConsentService::class)->recordSmsOptIn($client, 'synthetic-test');
    }
    $sender = app(CommunicationSenderService::class)->ensurePlatformProfile($business, 'sms');
    $template = CommunicationTemplate::create(['business_id' => $business->id, 'intent_type' => 'booking_confirmation',
        'channel' => 'sms', 'locale' => 'en-US', 'version' => CommunicationTemplate::where('business_id', $business->id)->count() + 1,
        'status' => 'published', 'category' => 'transactional', 'body' => $body, 'variables' => [], 'fallbacks' => []]);
    $intent = CommunicationIntent::create(['business_id' => $business->id, 'event_type' => 'appointment.confirmed',
        'legal_basis' => 'contract_performance', 'locale' => 'en-US', 'time_zone' => 'UTC',
        'scheduled_for_utc' => now(), 'local_scheduled_for' => now()->toIso8601String(), 'event_key' => (string) str()->uuid(),
        'intent_type' => 'booking_confirmation', 'category' => 'transactional', 'correlation_id' => (string) str()->uuid(), 'status' => 'queued']);

    return CommunicationMessage::create(['business_id' => $business->id, 'client_id' => $client->id,
        'communication_intent_id' => $intent->id, 'communication_template_id' => $template->id,
        'communication_sender_profile_id' => $sender->id, 'channel' => 'sms', 'recipient' => $destination,
        'recipient_hash' => CommunicationConsentService::destinationHash('sms', $destination),
        'legal_basis' => 'contract_performance', 'locale' => 'en-US', 'time_zone' => 'UTC', 'template_variables' => [],
        'queued_at' => now(), 'next_attempt_at' => now(), 'idempotency_key' => (string) str()->uuid(),
        'status' => 'queued', 'category' => 'transactional']);
}

it('sends to international clients using the same allowance without rewriting the paid snapshot', function (string $destination) {
    $message = globalQuotaMessage($this->business, $destination);
    $delivery = app(CommunicationDeliveryService::class);
    expect($delivery->deliver($message)->status)->toBe('sent');
    expect($delivery->deliver($message->fresh())->attempt_count)->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['To'] === $destination && $request['MessagingServiceSid'] === 'MG'.str_repeat('b', 32));
    expect(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(1)
        ->and(DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->value('quantity'))->toBe(1)
        ->and($this->subscription->fresh()->capacity_snapshot)->toBe($this->snapshot);
})->with(['US' => '+12025550123', 'Canada' => '+14165550123', 'India' => '+919876543210',
    'UK' => '+447700900123', 'Germany' => '+4915123456789', 'Australia' => '+61412345678']);

it('keeps international texts within the remaining quota without making a partial debit', function () {
    app(EntitlementUsageManager::class)->reserve($this->business, 'messaging.monthly_allowance', 99);
    $message = globalQuotaMessage($this->business, '+919876543210', str_repeat('a', 161));
    $result = app(CommunicationDeliveryService::class)->deliver($message);
    expect($result->status)->toBe('suppressed')->and($result->suppression_reason)->toBe('plan_limit')
        ->and($result->attempt_count)->toBe(0)->and($result->entitlement_charged_at)->toBeNull()
        ->and(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(99)
        ->and(DB::table('sms_usage_reservations')->count())->toBe(0);
    Http::assertNothingSent();
});

it('ignores absent or weighted historical destination metadata without changing it', function (?array $routes) {
    $snapshot = $this->snapshot;
    if ($routes === null) {
        unset($snapshot['sms_routes']);
    } else {
        $snapshot['sms_routes'] = $routes;
    }
    $this->subscription->update(['capacity_snapshot' => $snapshot]);
    $message = globalQuotaMessage($this->business, '+919876543210');
    expect(app(CommunicationDeliveryService::class)->deliver($message)->status)->toBe('sent')
        ->and(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(1)
        ->and($this->subscription->fresh()->capacity_snapshot)->toBe($snapshot);
    Http::assertSentCount(1);
})->with([[null], [[]], [['+1' => 1, '+91' => 9]]]);

it('uses remaining included and prepaid credits once for international multipart text', function () {
    app(EntitlementUsageManager::class)->reserve($this->business, 'messaging.monthly_allowance', 99);
    DB::table('sms_credit_entries')->insert(['business_id' => $this->business->id, 'source_key' => 'synthetic-topup',
        'quantity' => 2, 'kind' => 'purchase', 'created_at' => now()]);
    $message = globalQuotaMessage($this->business, '+919876543210', str_repeat('a', 307));
    $delivery = app(CommunicationDeliveryService::class);
    expect($delivery->deliver($message)->status)->toBe('sent');
    $delivery->deliver($message->fresh());
    expect(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(100)
        ->and(app(SmsCreditWallet::class)->balance($this->business->id))->toBe(0);
    $reservation = DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->first();
    expect($reservation->quantity)->toBe(3)->and($reservation->included_quantity)->toBe(1)->and($reservation->purchased_quantity)->toBe(2);
    Http::assertSentCount(1);
});

it('releases an international reservation after a definitive provider rejection', function () {
    $this->providerPayload = ['code' => 21614];
    $this->providerStatus = 400;
    $message = globalQuotaMessage($this->business, '+919876543210');
    $result = app(CommunicationDeliveryService::class)->deliver($message);
    expect($result->status)->toBe('failed')->and($result->last_error_code)->toBe('twilio_error_21614')
        ->and(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(0)
        ->and(DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->value('status'))->toBe('released');
    Http::assertSentCount(1);
});

it('still requires consent and a valid international destination', function (string $destination, bool $consent, string $reason) {
    $message = globalQuotaMessage($this->business, $destination, consent: $consent);
    $result = app(CommunicationDeliveryService::class)->deliver($message);
    expect($result->status)->toBe('suppressed')->and($result->suppression_reason)->toBe($reason)
        ->and($result->attempt_count)->toBe(0)->and(DB::table('sms_usage_reservations')->count())->toBe(0);
    Http::assertNothingSent();
})->with([['+919876543210', false, 'sms_opt_in_missing'], ['123', true, 'invalid_destination']]);

it('does not automatically replay a historical destination suppression', function () {
    $message = globalQuotaMessage($this->business, '+919876543210');
    $message->update(['status' => 'suppressed', 'suppression_reason' => 'destination_not_included']);
    expect(app(CommunicationDeliveryService::class)->deliver($message)->status)->toBe('suppressed');
    Http::assertNothingSent();
});

it('delivers a US appointment confirmation to an Indian client through the saved booking event', function () {
    $this->business->update(['country_code' => 'US', 'time_zone' => 'America/New_York']);
    $location = Location::factory()->create(['business_id' => $this->business->id, 'time_zone' => 'America/New_York']);
    $client = Client::factory()->create(['business_id' => $this->business->id, 'mobile' => '+919876543210', 'email' => null,
        'communication_preferences' => ['sms']]);
    $start = now()->addDays(3)->toImmutable();
    $appointment = Appointment::create([
        'business_id' => $this->business->id, 'location_id' => $location->id, 'client_id' => $client->id,
        'idempotency_key' => (string) str()->uuid(), 'request_hash' => hash('sha256', (string) str()->uuid()),
        'status' => 'confirmed', 'source' => 'online', 'client_name' => $client->name, 'client_mobile' => $client->mobile,
        'communication_preferences' => ['sms'], 'starts_at_utc' => $start, 'ends_at_utc' => $start->addHour(),
        'time_zone' => 'America/New_York', 'local_starts_at' => $start->setTimezone('America/New_York')->format('Y-m-d H:i:s P'),
        'local_ends_at' => $start->addHour()->setTimezone('America/New_York')->format('Y-m-d H:i:s P'),
        'price_minor' => 3500, 'currency_code' => 'USD', 'confirmed_at' => now(),
    ]);
    $service = Service::create(['business_id' => $this->business->id, 'kind' => 'service', 'name' => 'Haircut',
        'price_minor' => 3500, 'currency_code' => 'USD', 'duration_minutes' => 60]);
    $staff = StaffProfile::factory()->create(['business_id' => $this->business->id]);
    $appointment->serviceLines()->create(['business_id' => $this->business->id, 'service_id' => $service->id,
        'primary_staff_profile_id' => $staff->id, 'sequence' => 1, 'name' => $service->name,
        'price_minor' => 3500, 'currency_code' => 'USD', 'bookable_minutes' => 60, 'configuration_snapshot' => []]);
    app(CommunicationConsentService::class)->recordSmsOptIn($client, 'online', $appointment->id);
    app(CommunicationTemplateService::class)->settings($this->business)
        ->update(['quiet_hours_start' => '00:00:00', 'quiet_hours_end' => '00:00:00']);
    $event = OperationalNotificationEvent::create([
        'business_id' => $this->business->id, 'subject_type' => $appointment::class, 'subject_id' => $appointment->id,
        'event_type' => 'appointment.confirmed', 'status' => 'pending', 'idempotency_key' => 'appointment:'.$appointment->id.':confirmed',
        'occurred_at' => now(), 'payload' => [],
    ]);
    $events = app(OperationalCommunicationService::class);
    $events->process($event);
    $events->process($event->fresh());
    $confirmation = CommunicationMessage::whereHas('intent', fn ($query) => $query->where('event_key', $event->idempotency_key))->sole();
    $result = app(CommunicationDeliveryService::class)->deliver($confirmation);
    expect($result->last_error_code)->toBeNull()->and($result->status)->toBe('sent')->and($confirmation->recipient)->toBe('+919876543210')
        ->and($event->fresh()->status)->toBe('processed')->and($this->subscription->fresh()->capacity_snapshot)->toBe($this->snapshot);
    $reservation = DB::table('sms_usage_reservations')->where('communication_message_id', $confirmation->id)->first();
    expect($reservation->quantity)->toBeGreaterThan(0)
        ->and(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe($reservation->quantity);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['To'] === '+919876543210' && str_contains($request['Body'], $service->name)
        && app(SmsSegmentCounter::class)->segments($request['Body']) === $reservation->quantity);
});

it('holds international credits after an unknown provider outcome without retrying the SMS', function () {
    $this->providerFailure = new ConnectionException('Synthetic timeout');
    $message = globalQuotaMessage($this->business, '+919876543210');
    $delivery = app(CommunicationDeliveryService::class);
    $result = $delivery->deliver($message);
    expect($result->status)->toBe('failed')->and($result->last_error_code)->toBe('unexpected_provider_error')
        ->and($result->attempt_count)->toBe(1)->and($result->next_attempt_at)->toBeNull()
        ->and(app(EntitlementEvaluator::class)->usage($this->business, 'messaging.monthly_allowance'))->toBe(1)
        ->and(DB::table('sms_usage_reservations')->where('communication_message_id', $message->id)->value('status'))->toBe('reserved');
    $delivery->deliver($message->fresh());
    expect($message->fresh()->attempt_count)->toBe(1);
});
