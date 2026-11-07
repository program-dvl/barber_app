<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientReviews\Models\ClientReview;
use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Models\CommunicationIntent;
use App\Domain\Communications\Providers\SesEmailProvider;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\OperationalCommunicationService;
use App\Domain\Communications\Services\VisitEmailService;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Services\ReceiptPdfService;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use Aws\MockHandler;
use Aws\Result;
use Aws\Ses\SesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);
beforeEach(function () {
    Queue::fake();
    URL::forceRootUrl('https://example.test');
    config(['communications.email_transport_mode' => 'fake']);
    [$this->user, $this->business] = createTenantMembership();
    $location = Location::factory()->create(['business_id' => $this->business->id]);
    $this->client = Client::factory()->create(['business_id' => $this->business->id, 'email' => 'synthetic@example.test', 'marketing_status' => 'subscribed', 'communication_preferences' => ['email' => true]]);
    $this->visit = Appointment::query()->create(['business_id' => $this->business->id, 'location_id' => $location->id, 'client_id' => $this->client->id, 'status' => 'completed', 'source' => 'phone', 'client_name' => 'Synthetic Client', 'client_email' => $this->client->email, 'starts_at_utc' => now()->subHours(2), 'ends_at_utc' => now()->subHour(), 'completed_at' => now(), 'local_starts_at' => '2026-11-05 10:00:00', 'local_ends_at' => '2026-11-05 11:00:00', 'time_zone' => 'Asia/Kolkata', 'price_minor' => 5000, 'currency_code' => 'INR', 'idempotency_key' => (string) str()->uuid(), 'request_hash' => hash('sha256', (string) str()->uuid())]);
    $this->settings = app(CommunicationTemplateService::class)->settings($this->business);
});

it('defaults review automation off and shares one manual and automatic invitation', function () {
    expect($this->settings->automatic_review_email)->toBeFalse()->and($this->settings->automatic_receipt_email)->toBeTrue();
    $service = app(VisitEmailService::class);
    expect(fn () => $service->request($this->visit, 'visit_review', true))->toThrow(ValidationException::class);
    $first = $service->request($this->visit, 'visit_review');
    $this->settings->update(['automatic_review_email' => true, 'review_email_enabled_at' => now()->subMinute()]);
    expect($service->request($this->visit, 'visit_review', true)->id)->toBe($first->id)->and(CommunicationIntent::query()->count())->toBe(1)->and($first->messages)->toHaveCount(1);
    $sent = app(CommunicationDeliveryService::class)->deliver($first->messages->first());
    expect($sent->status)->toBe('sent')->and($sent->channel)->toBe('email');
    expect(app(CommunicationDeliveryService::class)->deliver($sent)->attempt_count)->toBe(1);
});
it('requires client permission even for manual requests', function () {
    $this->client->update(['marketing_status' => 'unknown']);
    expect(fn () => app(VisitEmailService::class)->request($this->visit, 'visit_review'))->toThrow(ValidationException::class);
    expect(CommunicationIntent::query()->count())->toBe(0);
});
it('does not send old visits when automation is enabled', function () {
    $this->settings->update(['automatic_review_email' => true, 'review_email_enabled_at' => now()->addSecond()]);
    expect(fn () => app(VisitEmailService::class)->request($this->visit, 'visit_review', true))->toThrow(ValidationException::class);
});
it('blocks missing email unfinished visits and opted out clients', function ($condition) {
    if ($condition === 'email') {
        $this->visit->update(['client_email' => null]);
    }
    if ($condition === 'unfinished') {
        $this->visit->update(['status' => 'in_service']);
    }
    if ($condition === 'cancelled') {
        $this->visit->update(['status' => 'cancelled_by_shop']);
    }
    if ($condition === 'no_show') {
        $this->visit->update(['status' => 'no_show']);
    }
    if ($condition === 'optout') {
        $this->client->update(['communication_preferences' => ['email' => false]]);
    }
    expect(fn () => app(VisitEmailService::class)->request($this->visit, 'visit_review'))->toThrow(ValidationException::class);
})->with(['email', 'unfinished', 'cancelled', 'no_show', 'optout']);
it('rechecks client consent and business automation before delivery', function ($condition) {
    $this->settings->update(['automatic_review_email' => true, 'review_email_enabled_at' => now()->subMinute()]);
    $intent = app(VisitEmailService::class)->request($this->visit, 'visit_review', true);
    if ($condition === 'business') {
        $this->settings->update(['automatic_review_email' => false]);
    }
    if ($condition === 'consent') {
        $this->client->update(['marketing_status' => 'withdrawn']);
    }
    if ($condition === 'status') {
        $this->visit->update(['status' => 'no_show']);
    }
    expect(app(CommunicationDeliveryService::class)->deliver($intent->messages->first())->status)->toBe('suppressed');
})->with(['business', 'consent', 'status']);
it('processes duplicate completion events once without sending historical invitations', function () {
    $this->settings->update(['automatic_review_email' => true, 'review_email_enabled_at' => now()->subMinute()]);
    foreach (['one', 'two'] as $key) {
        $event = OperationalNotificationEvent::query()->create(['business_id' => $this->business->id, 'idempotency_key' => $key, 'event_type' => 'appointment.status_changed', 'subject_type' => Appointment::class, 'subject_id' => $this->visit->id, 'payload' => ['status' => 'completed'], 'status' => 'pending', 'occurred_at' => now()]);
        app(OperationalCommunicationService::class)->process($event);
    }
    expect(CommunicationIntent::query()->where('intent_type', 'visit_review')->count())->toBe(1);
});

it('queues receipt attachments from the immutable issued snapshot and deduplicates payment events', function () {
    $sale = Sale::query()->create(['business_id' => $this->business->id, 'location_id' => $this->visit->location_id, 'appointment_id' => $this->visit->id, 'client_id' => $this->client->id, 'status' => 'completed', 'currency_code' => 'INR', 'calculation_snapshot' => [], 'subtotal_minor' => 5000, 'total_minor' => 5000, 'paid_minor' => 5000, 'balance_minor' => 0]);
    $receipt = app(ReceiptService::class)->issue($sale);
    $snapshot = $receipt->snapshot;
    $this->business->update(['name' => 'Renamed Business']);
    $this->settings->update(['automatic_receipt_email' => false]);
    expect(fn () => app(VisitEmailService::class)->request($this->visit, 'payment_receipt', true))->toThrow(ValidationException::class);
    $intent = app(VisitEmailService::class)->request($this->visit, 'payment_receipt');
    $pdf = Mockery::mock(ReceiptPdfService::class)->makePartial();
    $pdf->shouldReceive('bytes')->once()->withArgs(fn ($actual) => $actual->snapshot === $snapshot)->andReturn('%PDF-synthetic');
    app()->instance(ReceiptPdfService::class, $pdf);
    $outbound = null;
    $provider = Mockery::mock(EmailChannelProvider::class);
    $provider->shouldReceive('name')->andReturn('fake');
    $provider->shouldReceive('send')->once()->withArgs(function ($message) use (&$outbound) {
        $outbound = $message;

        return true;
    })->andReturn(new ProviderSendResult('fake-attachment'));
    app()->instance(EmailChannelProvider::class, $provider);
    $message = app(CommunicationDeliveryService::class)->deliver($intent->messages->first());
    expect($message->status)->toBe('sent')->and($outbound->attachments[0]['content'])->toBe('%PDF-synthetic')->and($outbound->attachments[0]['content_type'])->toBe('application/pdf')->and($outbound->htmlBody)->toContain('View receipt');
    expect(app(VisitEmailService::class)->request($this->visit, 'payment_receipt')->id)->toBe($intent->id)->and($receipt->fresh()->snapshot)->toBe($snapshot);
});
it('uses SES MIME attachments with text HTML and delivery tracking', function () {
    config(['communications.email_transport_mode' => 'ses', 'services.ses.key' => 'test', 'services.ses.secret' => 'test', 'services.ses.region' => 'us-east-1', 'communications.ses.from_address' => 'notifications@example.test']);
    $handler = new MockHandler([new Result(['MessageId' => 'attachment-accepted'])]);
    $ses = new SesClient(['version' => '2010-12-01', 'region' => 'us-east-1', 'credentials' => ['key' => 'test', 'secret' => 'test'], 'handler' => $handler, 'retries' => 0]);
    $out = new OutboundCommunication('synthetic@example.test', 'Receipt', 'Plain receipt', 'stable-key', 'correlation', htmlBody: '<p>Your receipt</p>', attachments: [['filename' => 'receipt.pdf', 'content' => '%PDF-synthetic', 'content_type' => 'application/pdf']]);
    expect((new SesEmailProvider($ses))->send($out)->providerMessageId)->toBe('attachment-accepted');
    $command = $handler->getLastCommand();
    expect($command->getName())->toBe('SendRawEmail')->and($command['RawMessage']['Data'])->toContain('application/pdf', 'receipt.pdf', 'text/plain', 'text/html', base64_encode('%PDF-synthetic'))->and($command['Tags'][0]['Value'])->toBe('stable-key');
});

it('authorizes direct review emails within tenant branch scope and blocks existing feedback', function () {
    activateTestSubscription($this->business);
    $membership = Membership::query()->where('business_id', $this->business->id)->where('user_id', $this->user->id)->firstOrFail();
    $membership->locations()->attach($this->visit->location_id, ['business_id' => $this->business->id]);
    $url = route('business.appointments.review-email', [$this->business, $this->visit]);
    $this->actingAs($this->user)->postJson($url)->assertOk();
    $this->postJson($url)->assertOk();
    expect(CommunicationIntent::query()->count())->toBe(1);
    [, $foreign] = createTenantMembership();
    $this->postJson(route('business.appointments.review-email', [$foreign, $this->visit]))->assertForbidden();
    ClientReview::query()->create(['business_id' => $this->business->id, 'appointment_id' => $this->visit->id, 'client_id' => $this->client->id, 'rating' => 3, 'author_initials' => 'SC', 'public_consent' => false, 'request_hash' => str_repeat('a', 64), 'submitted_at' => now(), 'public_consent_evidence' => []]);
    $this->postJson($url)->assertUnprocessable();
    expect(app(CommunicationDeliveryService::class)->deliver(CommunicationIntent::query()->first()->messages->first())->status)->toBe('suppressed');
});

it('renders business email controls with explicit safe defaults', function () {
    activateTestSubscription($this->business);
    $this->actingAs($this->user)->getJson(route('business.communications.index', $this->business))->assertOk()->assertJsonPath('settings.automatic_review_email', false)->assertJsonPath('settings.automatic_receipt_email', true);
    if (getenv('VISIT_EMAIL_CAPTURE')) {
        config(['inertia.ssr.enabled' => false]);
        $html = $this->get(route('business.communications.page', $this->business))->assertOk()->getContent();
        file_put_contents(base_path('tmp/pdfs/settings.html'), $html);
    }
});

it('handles receipt issuance before completion and suppresses disabled receipt automation', function () {
    $sale = Sale::query()->create(['business_id' => $this->business->id, 'location_id' => $this->visit->location_id, 'appointment_id' => $this->visit->id, 'client_id' => $this->client->id, 'status' => 'completed', 'currency_code' => 'INR', 'calculation_snapshot' => [], 'subtotal_minor' => 5000, 'total_minor' => 5000, 'paid_minor' => 5000, 'balance_minor' => 0]);
    $this->visit->update(['status' => 'in_service', 'completed_at' => null]);
    app(ReceiptService::class)->issue($sale);
    app(OperationalCommunicationService::class)->processPending($this->business->id);
    expect(CommunicationIntent::query()->count())->toBe(0);
    $this->visit->update(['status' => 'completed', 'completed_at' => now()]);
    $event = OperationalNotificationEvent::query()->create(['business_id' => $this->business->id, 'idempotency_key' => 'completion-after-receipt', 'event_type' => 'appointment.status_changed', 'subject_type' => Appointment::class, 'subject_id' => $this->visit->id, 'payload' => ['status' => 'completed'], 'status' => 'pending', 'occurred_at' => now()]);
    app(OperationalCommunicationService::class)->process($event);
    $intent = CommunicationIntent::query()->where('intent_type', 'payment_receipt')->firstOrFail();
    $this->settings->update(['automatic_receipt_email' => false]);
    expect(app(CommunicationDeliveryService::class)->deliver($intent->messages->first())->status)->toBe('suppressed');
});
