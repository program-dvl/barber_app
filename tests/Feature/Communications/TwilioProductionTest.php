<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSuppression;
use App\Domain\Communications\Providers\TwilioSmsProvider;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationProviderCallbackService;
use App\Domain\Communications\Services\CommunicationSenderService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\CommunicationWebhookVerifier;
use App\Domain\Communications\Services\InboundCommunicationService;
use App\Domain\Communications\Services\NotificationIntentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    config([
        'communications.transport_mode' => 'fake', 'communications.live_send_enabled' => false,
        'communications.twilio.account_sid' => 'AC'.str_repeat('a', 32),
        'communications.twilio.api_key_sid' => 'SK'.str_repeat('b', 32),
        'communications.twilio.api_key_secret' => 'synthetic-secret',
        'communications.twilio.auth_token' => 'synthetic-token',
        'communications.twilio.webhook_auth_token' => 'synthetic-token',
        'communications.twilio.messaging_service_sid' => null,
        'communications.twilio.sms_from' => '+18555550123',
        'communications.twilio.status_callback_url' => 'https://callbacks.example.test/api/webhooks/twilio/status',
        'communications.twilio.inbound_callback_url' => 'https://callbacks.example.test/api/webhooks/twilio/inbound',
    ]);
});

function productionQueuedMessage($business, $client, string $key, string $channel = 'email'): CommunicationMessage
{
    return app(NotificationIntentService::class)->create(new CommunicationIntentData(
        $business->id, $key, 'appointment.pending', 'booking_pending', 'transactional', 'contract_performance',
        'en-US', ($business->time_zone ?: 'UTC'), now()->toImmutable(), [$channel => $channel === 'email' ? $client->email : $client->mobile],
        ['client_name' => $client->name, 'business_name' => $business->name, 'booking_reference' => 'BK-100', 'appointment_date' => '29 October 2026', 'appointment_time' => '14:30'],
        $client->id,
    ), false)->messages->first();
}

function productionSms(): OutboundCommunication
{
    return new OutboundCommunication('+14155550123', '', 'Synthetic appointment confirmation', 'synthetic-key', 'synthetic-correlation', channel: 'sms');
}

it('uses MessagingServiceSid so Twilio can choose an alphanumeric or number sender by destination', function () {
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true, 'communications.twilio.messaging_service_sid' => 'MG'.str_repeat('c', 32)]);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_SENT', 'status' => 'accepted'], 201)]);
    expect(app(TwilioSmsProvider::class)->send(productionSms())->acceptedState)->toBe('accepted');
    Http::assertSent(fn ($r) => $r['MessagingServiceSid'] === 'MG'.str_repeat('c', 32)
        && ! isset($r['From']) && $r['StatusCallback'] === config('communications.twilio.status_callback_url'));
});

it('requires an HTTPS callback in live mode before contacting Twilio', function () {
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true, 'communications.twilio.status_callback_url' => 'http://localhost/communications/webhooks/twilio']);
    expect(fn () => app(TwilioSmsProvider::class)->send(productionSms()))->toThrow(CommunicationProviderException::class, 'twilio_callback_not_configured');
    Http::assertNothingSent();
});

it('uses the same minimal token and callback-base settings in local and production', function () {
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true,
        'communications.twilio.api_key_sid' => null, 'communications.twilio.api_key_secret' => null,
        'communications.twilio.status_callback_url' => null, 'communications.twilio.inbound_callback_url' => null,
        'communications.twilio.webhook_base_url' => 'https://clipperdesk.example.test',
        'communications.twilio.messaging_service_sid' => 'MG'.str_repeat('c', 32)]);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_TOKEN', 'status' => 'queued'], 201)]);
    app(TwilioSmsProvider::class)->send(productionSms());
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('AC'.str_repeat('a', 32).':synthetic-token'))
        && $r['StatusCallback'] === 'https://clipperdesk.example.test/communications/webhooks/twilio');
});

it('rejects an old simulated queue item even before any profile refresh runs', function () {
    [$user, $business] = createTenantMembership();
    activateTestSubscription($business);
    $client = Client::factory()->create(['business_id' => $business->id, 'mobile' => '+14155550123']);
    app(CommunicationConsentService::class)->recordSmsOptIn($client, 'synthetic-test');
    $queued = productionQueuedMessage($business, $client, 'simulation-first-delivery', 'sms');
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true]);
    $result = app(CommunicationDeliveryService::class)->deliver($queued);
    expect($result->status)->toBe('suppressed')->and($result->attempt_count)->toBe(0)->and($result->entitlement_charged_at)->toBeNull();
    Http::assertNothingSent();
});

it('checks production sender approval using GET requests without exposing secrets or sending messages', function () {
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true,
        'communications.twilio.messaging_service_sid' => 'MG'.str_repeat('c', 32)]);
    Http::fake([
        'api.twilio.com/*/IncomingPhoneNumbers.json*' => Http::response(['incoming_phone_numbers' => [['sid' => 'PN'.str_repeat('d', 32), 'phone_number' => '+18555550123', 'capabilities' => ['sms' => true]]]]),
        'messaging.twilio.com/v1/Services/*/PhoneNumbers*' => Http::response(['phone_numbers' => [['phone_number' => '+18555550123']]]),
        'messaging.twilio.com/v1/Services/*' => Http::response(['status_callback' => config('communications.twilio.status_callback_url'), 'inbound_request_url' => config('communications.twilio.inbound_callback_url'), 'use_inbound_webhook_on_number' => false]),
        'messaging.twilio.com/v1/Tollfree/Verifications*' => Http::response(['verifications' => [['status' => 'IN_REVIEW']]]),
    ]);
    $this->artisan('communications:check-twilio --provider-read')->expectsOutputToContain('BLOCK Toll-free SMS approval: IN_REVIEW')
        ->doesntExpectOutputToContain('synthetic-secret')->doesntExpectOutputToContain('synthetic-token')->assertExitCode(1);
    Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
});

it('verifies a configured HTTPS callback behind TLS termination without trusting arbitrary forwarded hosts', function () {
    $params = ['MessageSid' => 'SM_PROXY', 'MessageStatus' => 'delivered'];
    ksort($params);
    $data = config('communications.twilio.status_callback_url');
    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }
    $signature = base64_encode(hash_hmac('sha1', $data, 'synthetic-token', true));
    $request = Request::create('http://callbacks.example.test/api/webhooks/twilio/status', 'POST', $params);
    $request->headers->set('X-Twilio-Signature', $signature);
    expect(app(CommunicationWebhookVerifier::class)->verifyTwilio($request))->toBeTrue();
    $wrongHost = Request::create('http://evil.example.test/api/webhooks/twilio/status', 'POST', $params);
    $wrongHost->headers->set('X-Twilio-Signature', $signature);
    $wrongHost->headers->set('X-Forwarded-Host', 'callbacks.example.test');
    expect(app(CommunicationWebhookVerifier::class)->verifyTwilio($wrongHost))->toBeFalse();
});

it('retains safe Twilio error numbers without storing private response details', function () {
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true]);
    Http::fake(['api.twilio.com/*' => Http::response(['code' => 30032, 'message' => 'Private recipient and message'], 400)]);
    try {
        app(TwilioSmsProvider::class)->send(productionSms());
        $this->fail('Expected rejection');
    } catch (CommunicationProviderException $e) {
        expect($e->safeCode)->toBe('twilio_error_30032')->and($e->retryable)->toBeFalse()->and($e->getMessage())->not->toContain('Private');
    }
});

it('rotates the platform profile on promotion and never live-sends an old simulated queue item', function () {
    [$user, $business] = createTenantMembership();
    activateTestSubscription($business);
    $client = Client::factory()->create(['business_id' => $business->id, 'mobile' => '+14155550123']);
    app(CommunicationConsentService::class)->recordSmsOptIn($client, 'synthetic-test');
    $queued = productionQueuedMessage($business, $client, 'old-simulation', 'sms');
    $old = $queued->senderProfile;
    $oldIdentifier = $old->sender_identifier;
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true, 'communications.twilio.messaging_service_sid' => 'MG'.str_repeat('c', 32)]);
    $sender = app(CommunicationSenderService::class)->resolve($business, 'sms', app(CommunicationTemplateService::class)->settings($business));
    expect($sender->id)->not->toBe($old->id)->and($sender->sender_identifier)->toBe('+18555550123')
        ->and($old->fresh()->status)->toBe('retired')->and($old->fresh()->sender_identifier)->toBe($oldIdentifier);
    $result = app(CommunicationDeliveryService::class)->deliver($queued);
    expect($result->status)->toBe('suppressed')->and($result->attempt_count)->toBe(0);
    Http::assertNothingSent();
});

it('accepts signed public callback aliases and preserves delivered state when late statuses lack timestamps', function () {
    [$user, $business] = createTenantMembership();
    $client = Client::factory()->create(['business_id' => $business->id]);
    $message = productionQueuedMessage($business, $client, 'callback-alias');
    $message->update(['provider' => 'twilio', 'provider_message_id' => 'SM_CALLBACK', 'status' => 'sent', 'sent_at' => now()]);
    $url = config('communications.twilio.status_callback_url');
    foreach (['delivered', 'undelivered', 'sent'] as $status) {
        $this->travel(1)->seconds();
        $params = ['MessageSid' => 'SM_CALLBACK', 'MessageStatus' => $status];
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, 'synthetic-token', true));
        $this->withHeaders(['X-Twilio-Signature' => $signature])->post($url, $params)->assertNoContent();
    }
    expect($message->fresh()->status)->toBe('delivered')->and(CommunicationSuppression::query()->count())->toBe(0);
    $this->post('https://callbacks.example.test/api/webhooks/twilio/inbound', [])->assertBadRequest();
});

it('retains carrier error codes without treating every unreachable phone as an opt-out', function () {
    [$user, $business] = createTenantMembership();
    $client = Client::factory()->create(['business_id' => $business->id]);
    $message = productionQueuedMessage($business, $client, 'carrier-error');
    $message->update(['provider' => 'twilio', 'provider_message_id' => 'SM_UNREACHABLE', 'status' => 'sent']);
    app(CommunicationProviderCallbackService::class)->receive('twilio', 'unreachable', 'SM_UNREACHABLE', 'undelivered', CarbonImmutable::now()->addSecond(), hash('sha256', 'carrier-error'), '30003');
    expect($message->fresh()->last_error_code)->toBe('twilio_error_30003')->and(CommunicationSuppression::query()->count())->toBe(0);
});

it('recovers an early verified callback after the accepted SID is saved without sending another SMS', function () {
    [$user, $business] = createTenantMembership();
    $client = Client::factory()->create(['business_id' => $business->id]);
    $message = productionQueuedMessage($business, $client, 'early-callback');
    $callbacks = app(CommunicationProviderCallbackService::class);
    $at = CarbonImmutable::now();
    $early = $callbacks->receive('twilio', 'early-delivered', 'SM_EARLY', 'delivered', $at, hash('sha256', 'early'));
    expect($early->status)->toBe('pending');
    $message->update(['provider' => 'twilio', 'provider_message_id' => 'SM_EARLY', 'status' => 'sent', 'provider_state_at' => $at->addSecond()]);
    expect($callbacks->reconcilePendingTwilio(businessId: $business->id + 99999))->toBe(0);
    expect($callbacks->reconcilePendingTwilio($message->id))->toBe(1)->and($message->fresh()->status)->toBe('delivered');
    expect($callbacks->reconcilePendingTwilio($message->id))->toBe(0);
    Http::assertNothingSent();
});

it('honors STOP for an unambiguous shared platform sender without selecting another tenant', function () {
    [$user, $business] = createTenantMembership();
    $client = Client::factory()->create(['business_id' => $business->id, 'mobile' => '+14155550123', 'normalized_mobile' => '14155550123']);
    config(['communications.transport_mode' => 'live', 'communications.live_send_enabled' => true]);
    app(CommunicationSenderService::class)->ensurePlatformProfile($business, 'sms');
    app(InboundCommunicationService::class)->receiveTwilio('SM_STOP', '+14155550123', '+18555550123', 'STOP', [], CarbonImmutable::now());
    expect(CommunicationSuppression::query()->where('business_id', $business->id)->count())->toBe(1);
    [$secondUser, $second] = createTenantMembership();
    Client::factory()->create(['business_id' => $second->id, 'mobile' => '+14155550123', 'normalized_mobile' => '14155550123']);
    app(CommunicationSenderService::class)->ensurePlatformProfile($second, 'sms');
    expect(app(InboundCommunicationService::class)->receiveTwilio('SM_AMBIGUOUS', '+14155550123', '+18555550123', 'START', [], CarbonImmutable::now()))->toBeNull();
    expect(CommunicationSuppression::query()->where('business_id', $business->id)->whereNull('released_at')->count())->toBe(1);
});
