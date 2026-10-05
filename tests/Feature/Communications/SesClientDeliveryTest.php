<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\Communications\Data\CommunicationIntentData;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationProviderEvent;
use App\Domain\Communications\Models\CommunicationSuppression;
use App\Domain\Communications\Providers\SesEmailProvider;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\NotificationIntentService;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\Ses\SesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    config(['services.ses.key' => 'test-key', 'services.ses.secret' => 'test-secret', 'services.ses.region' => 'us-east-1', 'communications.email_transport_mode' => 'ses', 'communications.ses.from_address' => 'notifications@example.test', 'communications.ses.from_name' => 'Synthetic Salon', 'communications.ses.configuration_set' => 'client-delivery', 'communications.ses.sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:client-email']);
    [, $this->business] = createTenantMembership();
    $this->client = Client::factory()->create(['business_id' => $this->business->id, 'email' => 'sarah@example.test']);
});

function sesTestClient(MockHandler $handler): SesClient
{
    return new SesClient(['version' => '2010-12-01', 'region' => 'us-east-1', 'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'], 'retries' => 0, 'handler' => $handler]);
}

function sesTestMessage($business, $client, string $key = 'ses-event')
{
    return app(NotificationIntentService::class)->create(new CommunicationIntentData($business->id, $key, 'appointment.pending', 'booking_pending', 'transactional', 'contract_performance', 'en-US', 'UTC', now()->toImmutable(), ['email' => $client->email], ['client_name' => $client->name, 'business_name' => $business->name, 'service_name' => 'Haircut', 'location_name' => 'Studio', 'appointment_date' => '10 October', 'appointment_time' => '14:30', 'time_zone' => 'UTC'], $client->id), false)->messages->first();
}

function signedSesEnvelope(array $event, array $extra = []): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $csr = openssl_csr_new(['commonName' => 'sns.us-east-1.amazonaws.com'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1);
    openssl_x509_export($cert, $pem);
    $certUrl = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-'.str_replace('-', '', (string) str()->uuid()).'.pem';
    Http::fake([$certUrl => Http::response($pem)]);
    $envelope = ['Type' => 'Notification', 'MessageId' => (string) str()->uuid(), 'TopicArn' => config('communications.ses.sns_topic_arn'), 'Message' => json_encode($event), 'Timestamp' => now()->toIso8601String(), 'SignatureVersion' => '2', 'SigningCertURL' => $certUrl];
    $envelope = [...$envelope, ...$extra];
    $canonical = '';
    $fields = $envelope['Type'] === 'Notification' ? ['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
    foreach ($fields as $field) {
        $canonical .= $field."\n".$envelope[$field]."\n";
    }
    openssl_sign($canonical, $signature, $key, OPENSSL_ALGO_SHA256);
    $envelope['Signature'] = base64_encode($signature);

    return $envelope;
}

it('uses configured SES with a real message identifier, escaped HTML and no internal SDK retries', function () {
    $handler = new MockHandler([new Result(['MessageId' => 'ses-accepted', '@metadata' => ['headers' => ['x-amzn-requestid' => 'request-1']]])]);
    $provider = new SesEmailProvider(sesTestClient($handler));
    $result = $provider->send(new OutboundCommunication('sarah@example.test', 'Your booking', "Hi Sarah\n<script>private</script>", str_repeat('a', 64), 'correlation'));
    expect($provider->name())->toBe('ses')->and($result->providerMessageId)->toBe('ses-accepted')->and($result->providerRequestId)->toBe('request-1');
    $command = $handler->getLastCommand();
    expect($command['Source'])->toBe('"Synthetic Salon" <notifications@example.test>')->and($command['Destination']['ToAddresses'])->toBe(['sarah@example.test'])->and($command['Message']['Body']['Html']['Data'])->toContain('&lt;script&gt;')->and($command['ConfigurationSetName'])->toBe('client-delivery')->and($command['Tags'][0]['Value'])->toBe(str_repeat('a', 64));
    config(['services.ses.key' => '']);
    expect(fn () => $provider->send(new OutboundCommunication('sarah@example.test', 'test', 'test', 'key', 'correlation')))->toThrow(CommunicationProviderException::class, 'provider_not_configured');
});

it('only retries explicit SES throttling and holds uncertain acceptance for investigation', function () {
    $handler = new MockHandler([new AwsException('Private provider detail', new Command('SendEmail'), ['code' => 'Throttling']), new Result(['MessageId' => 'unused'])]);
    $provider = new SesEmailProvider(sesTestClient($handler));
    try {
        $provider->send(new OutboundCommunication('sarah@example.test', 'test', 'test', 'key', 'correlation'));
    } catch (CommunicationProviderException $e) {
        expect($e->safeCode)->toBe('ses_throttled')->and($e->retryable)->toBeTrue()->and($e->getMessage())->not->toContain('Private');
    }
    expect(count($handler))->toBe(1);
    $handler = new MockHandler([new AwsException('Private transport timeout', new Command('SendEmail'), ['connection_error' => true]), new Result(['MessageId' => 'unused'])]);
    app()->instance(SesClient::class, sesTestClient($handler));
    $message = app(CommunicationDeliveryService::class)->deliver(sesTestMessage($this->business, $this->client));
    expect($message->provider)->toBe('ses')->and($message->status)->toBe('failed')->and($message->last_error_code)->toBe('ses_outcome_unknown')->and($message->attempt_count)->toBe(1)->and($message->next_attempt_at)->toBeNull()->and(app(CommunicationSupportService::class)->canReplay($message))->toBeFalse()->and(count($handler))->toBe(1);
});

it('accepts signed SES delivery and bounce evidence idempotently without storing callback contents', function () {
    $message = sesTestMessage($this->business, $this->client);
    $message->update(['provider' => 'ses', 'provider_message_id' => 'ses-accepted', 'status' => 'sent', 'sent_at' => now(), 'provider_state_at' => now()]);
    $this->travel(2)->seconds();
    $envelope = signedSesEnvelope(['notificationType' => 'Delivery', 'mail' => ['messageId' => 'ses-accepted'], 'delivery' => ['timestamp' => now()->toIso8601String()]]);
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertNoContent();
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertNoContent();
    expect($message->fresh()->status)->toBe('delivered')->and(CommunicationProviderEvent::query()->count())->toBe(1);
    $this->travel(2)->seconds();
    $bounce = signedSesEnvelope(['eventType' => 'Bounce', 'mail' => ['messageId' => 'ses-accepted'], 'bounce' => ['timestamp' => now()->toIso8601String()]]);
    $this->postJson(route('communications.webhooks.ses'), $bounce)->assertNoContent();
    expect($message->fresh()->status)->toBe('failed')->and($message->fresh()->sent_at)->not->toBeNull()->and(app(CommunicationSupportService::class)->canReplay($message->fresh()))->toBeFalse();
    expect(CommunicationSuppression::query()->where('business_id', $this->business->id)->where('recipient_hash', $message->recipient_hash)->exists())->toBeTrue();
    $this->travelBack();
});

it('rejects unsigned, wrong-topic and redirected certificates before accepting any callback', function () {
    $this->postJson(route('communications.webhooks.ses'), [])->assertBadRequest();
    $envelope = signedSesEnvelope(['notificationType' => 'Delivery', 'mail' => ['messageId' => 'unknown']]);
    $envelope['TopicArn'] = 'arn:aws:sns:us-east-1:123456789012:other-topic';
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertBadRequest();
    Http::assertNothingSent();
    $envelope['TopicArn'] = config('communications.ses.sns_topic_arn');
    $envelope['SigningCertURL'] = 'https://evil.example.test/cert.pem';
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertBadRequest();
    Http::assertNothingSent();
    expect(CommunicationProviderEvent::query()->count())->toBe(0);
});

it('keeps local email simulation separate from SES delivery', function () {
    config(['communications.email_transport_mode' => 'fake']);
    $handler = new MockHandler([]);
    $provider = new SesEmailProvider(sesTestClient($handler));
    expect($provider->name())->toBe('fake')->and($provider->send(new OutboundCommunication('sarah@example.test', 'test', 'test', str_repeat('a', 64), 'correlation'))->providerMessageId)->toStartWith('fake-email-');
    expect($handler->getLastCommand())->toBeNull();
});

it('confirms only a signed subscription for the explicitly configured SNS topic', function () {
    $url = 'https://sns.us-east-1.amazonaws.com/?'.http_build_query(['Action' => 'ConfirmSubscription', 'TopicArn' => config('communications.ses.sns_topic_arn'), 'Token' => 'synthetic-token']);
    $envelope = signedSesEnvelope([], ['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => $url, 'Token' => 'synthetic-token']);
    Http::fake([$url => Http::response('<ConfirmSubscriptionResponse/>')]);
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertNoContent();
    Http::assertSent(fn ($request) => $request->url() === $url);
    $evil = signedSesEnvelope([], ['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'https://evil.example.test/subscribe', 'Token' => 'synthetic-token']);
    $this->postJson(route('communications.webhooks.ses'), $evil)->assertBadRequest();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.test'));
});

it('asks SNS to retry a delivery callback that races the provider response', function () {
    $message = sesTestMessage($this->business, $this->client);
    $message->update(['provider' => 'ses', 'status' => 'sending']);
    $envelope = signedSesEnvelope(['eventType' => 'Delivery', 'mail' => ['messageId' => 'ses-accepted', 'tags' => ['client_message' => [$message->idempotency_key]]], 'delivery' => ['timestamp' => now()->toIso8601String()]]);
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertStatus(503);
    expect(CommunicationProviderEvent::query()->count())->toBe(0);
    $message->update(['provider_message_id' => 'ses-accepted', 'status' => 'sent']);
    $this->postJson(route('communications.webhooks.ses'), $envelope)->assertNoContent();
    expect($message->fresh()->status)->toBe('delivered');
});

it('does not move uncertain historical provider attempts to SES automatically', function () {
    $handler = new MockHandler([]);
    app()->instance(SesClient::class, sesTestClient($handler));
    $message = sesTestMessage($this->business, $this->client);
    $message->update(['provider' => 'resend', 'status' => 'retried', 'attempt_count' => 1, 'last_error_code' => 'resend_http_503']);
    $result = app(CommunicationDeliveryService::class)->deliver($message);
    expect($result->last_error_code)->toBe('provider_changed_requires_review')->and($result->status)->toBe('failed')->and($handler->getLastCommand())->toBeNull();
});
