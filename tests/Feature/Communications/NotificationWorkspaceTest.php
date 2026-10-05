<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Jobs\DeliverCommunicationMessage;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\CommunicationActionLinkService;
use App\Domain\Communications\Services\CommunicationConsentService;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\NotificationIntentService;
use App\Domain\Communications\Services\NotificationWorkspaceQuery;
use App\Domain\Communications\Services\OperationalCommunicationService;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function workspaceMessage($business, $client, string $key = 'event', string $channel = 'email', string $intent = 'booking_pending', ?Appointment $appointment = null): CommunicationMessage
{
    return app(NotificationIntentService::class)->create(new CommunicationIntentData(
        $business->id, $key, 'appointment.pending', $intent, 'transactional', 'contract_performance',
        'en-US', ($business->time_zone ?: 'UTC'), now()->toImmutable(), [$channel => $channel === 'email' ? $client->email : $client->mobile],
        ['client_name' => $client->name, 'business_name' => $business->name, 'booking_reference' => 'BK-100', 'appointment_date' => '10 October 2026', 'appointment_time' => '14:30'],
        $client->id, $appointment ? Appointment::class : null, $appointment?->id,
    ), false)->messages->first();
}

beforeEach(function () {
    Queue::fake();
    config(['communications.transport_mode' => 'fake']);
    [$this->user, $this->business, $this->membership] = createTenantMembership();
    $this->business->update(['time_zone' => 'Asia/Kolkata']);
    activateTestSubscription($this->business);
    $this->client = Client::factory()->create(['business_id' => $this->business->id, 'name' => 'Synthetic Sarah', 'email' => 'sarah@example.test', 'mobile' => '+14155552671']);
    $this->actingAs($this->user);
});

it('projects existing rules and both channels without credentials or secure links', function () {
    config(['services.ses.key' => 'secret-email-key', 'services.ses.secret' => 'secret-email-secret', 'communications.twilio.auth_token' => 'secret-text-token']);
    workspaceMessage($this->business, $this->client);
    $response = $this->getJson(route('business.communications.index', $this->business))->assertOk();
    $response->assertJsonCount(12, 'automations')->assertJsonPath('history.total', 1)->assertJsonPath('history.data.0.client_name', 'Synthetic Sarah');
    expect($response->getContent())->not->toContain('secret-email-key', 'secret-email-secret', 'secret-text-token', 'template_variables', '__action_link_id');
});

it('saves a first draft without suppressing the live default and publishes without mutating queued snapshots', function () {
    $payload = ['intent_type' => 'booking_pending', 'channel' => 'email', 'locale' => 'en-US', 'subject' => 'Hello {{client_name}}', 'body' => '{{business_name}}: Pending for {{client_name}}.', 'base_version' => 0];
    $draft = $this->postJson(route('business.communications.templates.store', $this->business), $payload)->assertCreated()->json('template');
    expect($draft['version'])->toBe(2);
    $queued = workspaceMessage($this->business, $this->client);
    expect($queued->status)->toBe('queued')->and($queued->template->version)->toBe(1);
    $this->postJson(route('business.communications.templates.publish', [$this->business, $draft['public_id']]))->assertOk()->assertJsonPath('template.status', 'published');
    expect(workspaceMessage($this->business, $this->client, 'second')->template->id)->toBe($draft['id'])
        ->and($queued->fresh()->communication_template_id)->not->toBe($draft['id']);
    $this->postJson(route('business.communications.templates.store', $this->business), $payload)->assertUnprocessable()->assertJsonValidationErrors('body');
    expect(AuditEvent::query()->where('action', 'communication.template_saved')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'communication.template_published')->count())->toBe(1);
    $this->postJson(route('business.communications.templates.publish', [$this->business, $draft['public_id']]))->assertOk();
    expect(AuditEvent::query()->where('action', 'communication.template_published')->count())->toBe(1);
});

it('validates event-specific variables, malformed syntax, HTML and deferred channels', function () {
    $base = ['intent_type' => 'queue_update', 'channel' => 'sms', 'locale' => 'en-US', 'base_version' => 0];
    foreach (['{{action_link}}', '{{client-name}}', '<b>Hi</b>', '{{private_notes}}'] as $body) {
        $this->postJson(route('business.communications.templates.store', $this->business), [...$base, 'body' => $body])->assertUnprocessable()->assertJsonValidationErrors('body');
    }
    $this->postJson(route('business.communications.templates.store', $this->business), [...$base, 'channel' => 'whatsapp', 'body' => 'Hi'])->assertUnprocessable();
    $this->postJson(route('business.communications.templates.store', $this->business), [...$base, 'intent_type' => 'rebooking_reminder', 'body' => 'Hi'])->assertUnprocessable();
    expect(CommunicationTemplate::query()->count())->toBe(0);
});

it('rejects stale publishes and cross-tenant template preview and publication', function () {
    $templates = app(CommunicationTemplateService::class);
    $old = $templates->save($this->business, 'booking_pending', 'email', 'en-US', 'Subject', 'Body');
    $new = $templates->save($this->business, 'booking_pending', 'email', 'en-US', 'New subject', 'New body');
    $this->postJson(route('business.communications.templates.publish', [$this->business, $old]))->assertUnprocessable();
    [$otherUser, $other] = createTenantMembership();
    activateTestSubscription($other);
    $this->actingAs($otherUser)->postJson(route('business.communications.templates.publish', [$other, $new]))->assertNotFound();
    $this->postJson(route('business.communications.templates.preview', [$other, $new]))->assertNotFound();
});

it('paginates history, filters local dates and searches exact encrypted destinations', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 09:00 UTC'), function () {
        for ($i = 0; $i < 28; $i++) {
            workspaceMessage($this->business, $this->client, 'event-'.$i);
        }
        $url = route('business.communications.history', $this->business);
        $this->getJson($url)->assertOk()->assertJsonCount(25, 'history.data')->assertJsonPath('history.total', 28);
        $this->getJson($url.'?page=2')->assertJsonCount(3, 'history.data');
        $this->getJson($url.'?search=sarah%40example.test')->assertJsonPath('history.total', 28);
        $this->getJson($url.'?search=does-not-exist')->assertJsonPath('history.total', 0);
        $this->getJson($url.'?search=Synthetic')->assertJsonPath('history.total', 28);
        $this->getJson($url.'?channel=sms')->assertJsonPath('history.total', 0);
        $this->getJson($url.'?from=invalid')->assertUnprocessable();
        $this->getJson($url.'?to=2026-01-01')->assertOk();
        $message = CommunicationMessage::query()->first();
        $message->update(['created_at' => CarbonImmutable::parse('2026-10-03 20:00', 'UTC')]);
        $this->business->update(['time_zone' => 'Asia/Kolkata']);
        $this->getJson($url.'?from=2026-10-04&to=2026-10-04')->assertJsonPath('history.total', 28);
    });
});

it('allows reception to view branch-scoped history while denying edits and foreign messages', function () {
    [$user, $business, $membership] = createTenantMembership(StarterRole::Receptionist);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $otherLocation = Location::factory()->create(['business_id' => $business->id]);
    $membership->locations()->attach($location, ['business_id' => $business->id]);
    $business->update(['time_zone' => 'Asia/Kolkata']);
    $client = Client::factory()->create(['business_id' => $business->id]);
    $makeAppointment = fn ($loc) => Appointment::query()->create(['business_id' => $business->id, 'location_id' => $loc->id, 'client_id' => $client->id, 'idempotency_key' => (string) str()->uuid(), 'request_hash' => hash('sha256', (string) str()->uuid()), 'status' => 'confirmed', 'source' => 'phone', 'client_name' => $client->name, 'starts_at_utc' => now()->addDays(3), 'ends_at_utc' => now()->addDays(3)->addHour(), 'time_zone' => $business->time_zone, 'local_starts_at' => '2026-10-07 10:00:00', 'local_ends_at' => '2026-10-07 11:00:00', 'price_minor' => 1000, 'currency_code' => 'USD']);
    $allowed = workspaceMessage($business, $client, 'allowed', appointment: $makeAppointment($location));
    $hidden = workspaceMessage($business, $client, 'hidden', appointment: $makeAppointment($otherLocation));
    workspaceMessage($business, $client, 'payment', intent: 'deposit_received', appointment: $makeAppointment($location));
    $this->actingAs($user)->get(route('business.communications.page', $business))->assertOk()->assertInertia(fn (Assert $p) => $p->where('capabilities.manage', false)->has('templates', 0)->where('history.total', 1));
    $this->getJson(route('business.communications.messages.show', [$business, $hidden]))->assertNotFound();
    $this->getJson(route('business.communications.messages.show', [$business, $allowed]))->assertOk();
    $this->postJson(route('business.communications.templates.store', $business), [])->assertForbidden();
    $this->postJson(route('business.communications.messages.replay', [$business, $allowed]), ['reason' => 'Fixed'])->assertForbidden();
    $foreign = workspaceMessage($this->business, $this->client, 'foreign');
    $this->getJson(route('business.communications.messages.show', [$business, $foreign]))->assertNotFound();
    $allowedAppointment = Appointment::query()->findOrFail($allowed->intent->source_id);
    $allowed->intent->update(['intent_type' => 'booking_confirmation']);
    $hidden->intent->update(['intent_type' => 'booking_confirmation']);
    $summary = app(TenantContext::class)->run($business, $membership, fn () => app(NotificationWorkspaceQuery::class)->appointmentSummary($business, $membership->fresh(), Appointment::query()->where('business_id', $business->id)->pluck('public_id')->all()));
    expect(array_keys($summary))->toBe([$allowedAppointment->public_id])
        ->and($summary[$allowedAppointment->public_id][0])->toBe(['name' => 'Booking confirmation', 'channel' => 'email', 'status' => 'queued', 'simulated' => false]);
});

it('requires a current settings revision and audits only saved changes', function () {
    $settings = $this->getJson(route('business.communications.index', $this->business))->json('settings');
    $payload = collect($settings)->except(['email_provider', 'mobile_provider', 'fallback_mobile_channel'])->all();
    $payload['quiet_hours_start'] = '22:00';
    $this->patchJson(route('business.communications.update', $this->business), $payload)->assertOk();
    $this->patchJson(route('business.communications.update', $this->business), $payload)->assertUnprocessable()->assertJsonValidationErrors('settings_revision');
    expect(AuditEvent::query()->where('action', 'communication.settings_updated')->count())->toBe(1);
});

it('serializes retry commands and never retries a queued or accepted message', function () {
    $message = workspaceMessage($this->business, $this->client);
    $message->update(['status' => 'failed', 'last_error_code' => 'provider_not_configured', 'attempt_count' => 1]);
    $url = route('business.communications.messages.replay', [$this->business, $message]);
    $this->postJson($url, ['reason' => 'Connection restored'])->assertOk()->assertJsonMissingPath('message.template_variables');
    $this->postJson($url, ['reason' => 'Duplicate browser retry'])->assertUnprocessable();
    expect(AuditEvent::query()->where('action', 'communication.replay_requested')->count())->toBe(1);
    Queue::assertPushed(DeliverCommunicationMessage::class, 1);
    $message->update(['status' => 'failed', 'sent_at' => now()]);
    expect(app(CommunicationSupportService::class)->canReplay($message->fresh()))->toBeFalse();
});

it('keeps future reminder links usable through their actual send time and rechecks email preference', function () {
    $message = app(NotificationIntentService::class)->create(new CommunicationIntentData($this->business->id, 'future-reminder', 'appointment.reminder', 'appointment_reminder', 'transactional', 'contract_performance', 'en-US', ($this->business->time_zone ?: 'UTC'), now()->addDays(20)->toImmutable(), ['email' => $this->client->email], ['client_name' => $this->client->name], $this->client->id), false)->messages->first();
    expect($message->actionLink->expires_at->greaterThan(now()->addDays(20)))->toBeTrue();
    $pending = workspaceMessage($this->business, $this->client, 'preference-changed');
    $this->client->update(['communication_preferences' => ['email' => false]]);
    expect(app(CommunicationDeliveryService::class)->deliver($pending)->suppression_reason)->toBe('channel_preference_withdrawn');
});

it('suppresses queued SMS when its captured sender becomes inactive without a provider attempt', function () {
    app(CommunicationConsentService::class)->recordSmsOptIn($this->client, 'booking');
    $message = workspaceMessage($this->business, $this->client, 'sender-disabled', 'sms');
    expect($message->status)->toBe('queued');
    CommunicationSenderProfile::query()->whereKey($message->communication_sender_profile_id)->update(['status' => 'pending']);
    $result = app(CommunicationDeliveryService::class)->deliver($message);
    expect($result->status)->toBe('suppressed')->and($result->suppression_reason)->toBe('sender_not_ready')
        ->and($result->attempt_count)->toBe(0)->and($result->attempts()->count())->toBe(0)->and($result->entitlement_charged_at)->toBeNull();
});

it('does not revive an elapsed day-before reminder by shifting it out of quiet hours', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 22:00', 'Asia/Kolkata'));
    $location = Location::factory()->create(['business_id' => $this->business->id, 'time_zone' => 'Asia/Kolkata']);
    $appointment = Appointment::query()->create(['business_id' => $this->business->id, 'location_id' => $location->id, 'client_id' => $this->client->id, 'idempotency_key' => 'late-booking', 'request_hash' => hash('sha256', 'late-booking'), 'status' => 'confirmed', 'source' => 'phone', 'client_name' => $this->client->name, 'starts_at_utc' => CarbonImmutable::parse('2026-10-05 21:30', 'Asia/Kolkata')->utc(), 'ends_at_utc' => CarbonImmutable::parse('2026-10-05 22:30', 'Asia/Kolkata')->utc(), 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => '2026-10-05 21:30:00', 'local_ends_at' => '2026-10-05 22:30:00', 'price_minor' => 1000, 'currency_code' => 'USD']);
    app(CommunicationTemplateService::class)->settings($this->business)->update(['quiet_hours_start' => '21:00', 'quiet_hours_end' => '08:00']);
    expect(app(OperationalCommunicationService::class)->scheduleReminders($appointment))->toBe(0);
    expect(CommunicationIntent::query()->where('intent_type', 'appointment_reminder')->count())->toBe(0);
    $this->travelBack();
});

it('opens the exact issued receipt through a signed private link and rejects cross-tenant targets', function () {
    $location = Location::factory()->create(['business_id' => $this->business->id]);
    $appointment = Appointment::query()->create(['business_id' => $this->business->id, 'location_id' => $location->id, 'client_id' => $this->client->id, 'idempotency_key' => 'receipt-booking', 'request_hash' => hash('sha256', 'receipt-booking'), 'status' => 'completed', 'source' => 'phone', 'client_name' => $this->client->name, 'starts_at_utc' => now()->subHour(), 'ends_at_utc' => now(), 'time_zone' => $this->business->time_zone, 'local_starts_at' => '2026-10-04 10:00:00', 'local_ends_at' => '2026-10-04 11:00:00', 'price_minor' => 4500, 'currency_code' => 'USD']);
    $sale = Sale::query()->create(['business_id' => $this->business->id, 'location_id' => $location->id, 'appointment_id' => $appointment->id, 'status' => 'completed', 'currency_code' => 'USD', 'total_minor' => 4500, 'paid_minor' => 4500, 'calculation_snapshot' => []]);
    $receipt = SaleReceipt::query()->create(['business_id' => $this->business->id, 'sale_id' => $sale->id, 'receipt_number' => 'REVIEW-R-1', 'content_hash' => hash('sha256', 'review-receipt'), 'snapshot' => ['client' => 'Synthetic Sarah', 'business' => ['name' => $this->business->name], 'sale' => ['currency_code' => 'USD', 'total_minor' => 4500, 'paid_minor' => 4500, 'balance_minor' => 0], 'lines' => []], 'issued_at' => now()]);
    $message = workspaceMessage($this->business, $this->client, 'receipt-event', intent: 'payment_receipt', appointment: $appointment);
    expect($message->actionLink->target_type)->toBe(SaleReceipt::class)->and($message->actionLink->target_id)->toBe($receipt->id);
    $links = app(CommunicationActionLinkService::class);
    $url = $links->url($message->actionLink);
    $this->get($url)->assertOk()->assertSee('REVIEW-R-1')->assertSee('USD 45.00')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Referrer-Policy', 'no-referrer');
    // A receipt can be viewed again until expiry; it is never regenerated.
    $this->get($url)->assertOk();
    $legacy = $links->issue($this->business->id, $this->client, 'receipt_view', $appointment, now()->addHour()->toImmutable());
    $this->get($links->url($legacy))->assertOk()->assertSee('REVIEW-R-1');
    $this->get(route('communications.action', $message->actionLink->public_id))->assertForbidden();
    [, $other] = createTenantMembership();
    $foreign = $links->issue($other->id, null, 'receipt_view', $receipt, now()->addHour()->toImmutable());
    $this->get($links->url($foreign))->assertNotFound();
    $message->actionLink->update(['revoked_at' => now()]);
    $this->get($url)->assertGone();
    expect(SaleReceipt::query()->count())->toBe(1);
});

it('retains explicit client channel withdrawals when editing other profile details and stops queued messages', function () {
    $this->client->update(['communication_preferences' => ['email' => false, 'sms' => true, 'whatsapp' => false]]);
    $pending = workspaceMessage($this->business, $this->client, 'profile-preference-changed');
    $this->patchJson(route('business.clients.update', [$this->business, $this->client]), [
        'name' => 'Synthetic Sarah Updated', 'version' => $this->client->version,
        'reason' => 'Client requested a correction', 'communication_preferences' => ['sms'],
    ])->assertRedirect();
    expect($this->client->fresh()->communication_preferences)->toBe(['email' => false, 'sms' => true, 'whatsapp' => false]);
    expect(app(CommunicationDeliveryService::class)->deliver($pending)->suppression_reason)->toBe('channel_preference_withdrawn');
    app(CommunicationConsentService::class)->recordSmsOptIn($this->client, 'booking');
    $sms = workspaceMessage($this->business, $this->client, 'profile-sms-withdrawal', 'sms');
    expect($sms->status)->toBe('queued');
    $this->patchJson(route('business.clients.update', [$this->business, $this->client]), [
        'name' => 'Synthetic Sarah Updated', 'version' => $this->client->fresh()->version,
        'reason' => 'Client withdrew text preferences', 'communication_preferences' => [],
    ])->assertRedirect();
    expect($this->client->fresh()->communication_preferences['sms'])->toBeFalse();
    expect(app(CommunicationDeliveryService::class)->deliver($sms)->suppression_reason)->toBe('channel_preference_withdrawn');
});
