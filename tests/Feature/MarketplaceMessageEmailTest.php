<?php

use App\Domain\AccountNotifications\Models\EmailNotificationDelivery;
use App\Domain\Marketplace\Jobs\SendMessageEmail;
use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\Marketplace\Models\Message;
use App\Domain\Marketplace\Services\BookingMessages;
use App\Domain\Marketplace\Services\BookingOwnership;
use App\Domain\Marketplace\Services\CustomerConversationReturn;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use App\Notifications\BookingMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/MarketplaceFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    config(['inertia.ssr.enabled' => false, 'mail.default' => 'array', 'brand.website_url' => 'https://example.test']);
    Queue::fake();
    $this->withoutVite();
});
function messageEmailBooking(array $f, ?Customer $customer = null): CustomerBooking
{
    $customer ??= Customer::query()->create(['name' => 'Synthetic Customer', 'email' => (string) Str::uuid().'@example.test', 'email_verified_at' => now()]);
    $visit = Appointment::query()->create(['business_id' => $f['business']->id, 'location_id' => $f['location']->id, 'status' => 'confirmed', 'source' => 'online', 'client_name' => $customer->name, 'client_email' => $customer->email, 'starts_at_utc' => now()->addDay(), 'ends_at_utc' => now()->addDay()->addHour(), 'local_starts_at' => now()->addDay(), 'local_ends_at' => now()->addDay()->addHour(), 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR', 'price_minor' => 3500, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid())]);

    return app(BookingOwnership::class)->attach($customer, $visit);
}
function messageEmailSend(CustomerBooking $booking, string $sender = 'customer', ?string $key = null): Message
{
    return app(BookingMessages::class)->send($booking, $sender, 'Private message text must remain in Messages.', $key ?? (string) Str::uuid());
}

it('commits one durable alert per new message and repairs a lost queue dispatch without replaying sent mail', function () {
    $b = messageEmailBooking(marketplaceBookablePath());
    $key = (string) Str::uuid();
    $message = messageEmailSend($b, 'customer', $key);
    expect(messageEmailSend($b, 'customer', $key)->id)->toBe($message->id);
    Queue::assertPushed(SendMessageEmail::class, 1);
    expect(EmailNotificationDelivery::count())->toBe(1)->and(EmailNotificationDelivery::first()->status)->toBe('queued');
    $this->artisan('marketplace:message-email-sweep')->assertSuccessful();
    Queue::assertPushed(SendMessageEmail::class, 2);
    EmailNotificationDelivery::query()->update(['status' => 'accepted']);
    $this->artisan('marketplace:message-email-sweep')->assertSuccessful();
    Queue::assertPushed(SendMessageEmail::class, 2);
});

it('rolls back the message and alert together and never queues an uncommitted message', function () {
    $b = messageEmailBooking(marketplaceBookablePath());
    DB::beginTransaction();
    messageEmailSend($b);
    Queue::assertNotPushed(SendMessageEmail::class);
    DB::rollBack();
    expect(Message::count())->toBe(0)->and(EmailNotificationDelivery::count())->toBe(0);
    Queue::assertNotPushed(SendMessageEmail::class);
});

it('sends customer alerts only to the main active verified owner with an authenticated tenant link', function () {
    $f = marketplaceBookablePath();
    $other = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b);
    Notification::fake();
    (new SendMessageEmail($message->id))->handle();
    Notification::assertSentTo($f['user'], BookingMessageNotification::class, function ($notice) use ($f, $b) {
        expect($notice->businessId())->toBe($f['business']->id)->and($notice->toBusiness)->toBeTrue();
        expect($notice->conversationUrl)->toBe('https://example.test'.route('business.marketplace.thread', [$f['business'], $b->appointment->public_id], false));

        return true;
    });
    Notification::assertNotSentTo($other['user'], BookingMessageNotification::class);
    Notification::assertCount(1);
    // A fake notification transport cannot certify provider acceptance.
    expect(EmailNotificationDelivery::first()->status)->toBe('unknown');
    (new SendMessageEmail($message->id))->handle();
    Notification::assertCount(1);
});

it('delivers business replies through the real array mail channel with an inline Marketplace logo and no message body', function () {
    $f = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b, 'business');
    (new SendMessageEmail($message->id))->handle();
    (new SendMessageEmail($message->id))->handle();
    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);
    $mail = $sent->first()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe($b->customer->email);
    expect($mail->getHtmlBody())->toContain('New message from', 'Clipperdesk', '/marketplace/account/bookings/'.$b->appointment->public_id.'/messages')
        ->not->toContain('Private message text', '/appointments/secure/', $b->customer->email);
    expect($mail->getAttachments())->toHaveCount(1);
    expect($mail->getAttachments()[0]->getMediaSubtype())->toBe('png');
    expect(EmailNotificationDelivery::first()->status)->toBe('accepted')->and(EmailNotificationDelivery::first()->attempts)->toBe(1);
});

it('does not send to an unverified owner or fall back to the public business email', function () {
    $f = marketplaceBookablePath();
    $f['user']->forceFill(['email_verified_at' => null])->save();
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b);
    Notification::fake();
    (new SendMessageEmail($message->id))->handle();
    Notification::assertNothingSent();
    expect(EmailNotificationDelivery::first()->status)->toBe('skipped');
});

it('rechecks changed recipients paused conversations read messages and inactive businesses before delivery', function (string $change) {
    $f = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b, 'business');
    match ($change) {
        'changed-email' => $b->appointment->update(['client_email' => 'changed@example.test']),
        'paused' => $b->update(['messages_paused' => true]),
        'read' => $message->update(['read_at' => now()]),
        'inactive' => $f['business']->update(['status' => 'suspended']),
    };
    Notification::fake();
    (new SendMessageEmail($message->id))->handle();
    Notification::assertNothingSent();
    expect(EmailNotificationDelivery::first()->status)->toBe('skipped');
})->with(['changed-email', 'paused', 'read', 'inactive']);

it('keeps the saved message when email fails and never blindly replays an uncertain delivery', function () {
    $b = messageEmailBooking(marketplaceBookablePath());
    $message = messageEmailSend($b, 'business');
    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('Synthetic provider outage'));
    (new SendMessageEmail($message->id))->handle();
    (new SendMessageEmail($message->id))->handle();
    expect(Message::find($message->id)->body)->toBe('Private message text must remain in Messages.');
    expect(EmailNotificationDelivery::first()->status)->toBe('unknown');
    $this->artisan('marketplace:message-email-sweep')->assertSuccessful();
    Queue::assertPushed(SendMessageEmail::class, 1);
});

it('reclassifies interrupted delivery conservatively without replaying historical messages', function () {
    $b = messageEmailBooking(marketplaceBookablePath());
    Message::query()->create(['booking_id' => $b->id, 'sender' => 'customer', 'body' => 'Historical', 'idempotency_key' => (string) Str::uuid()]);
    $message = messageEmailSend($b);
    EmailNotificationDelivery::query()->update(['status' => 'sending', 'sending_at' => now()->subMinutes(16)]);
    $this->artisan('marketplace:message-email-sweep')->assertSuccessful();
    expect(EmailNotificationDelivery::count())->toBe(1)->and(EmailNotificationDelivery::first()->status)->toBe('unknown');
    Queue::assertPushed(SendMessageEmail::class, 1);
});

it('opens a focused scoped inbox and keeps publication controls in authorized Business setup settings', function () {
    $f = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $other = messageEmailBooking(marketplaceBookablePath());
    messageEmailSend($b);
    messageEmailSend($other);
    $this->actingAs($f['user'])->get(route('business.marketplace.index', $f['business']))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $p) => $p->component('Marketplace/BusinessInbox')->has('inbox.threads.data', 1)->where('inbox.threads.data.0.id', $b->appointment->public_id)->where('inbox.threads.data.0.unread', 1)->missing('listing')->missing('stats')->missing('publication'));
    $this->get(route('business.marketplace.thread', [$f['business'], $b->appointment->public_id]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('inbox.threads.data.0.unread', 0)->has('messages', 1));
    $this->get(route('business.marketplace.index', $f['business']).'?view=unread')->assertInertia(fn (Assert $p) => $p->has('inbox.threads.data', 0));
    $this->get(route('business.marketplace.index', $f['business']).'?q=does-not-exist')->assertInertia(fn (Assert $p) => $p->has('inbox.threads.data', 0));
    $this->get(route('business.marketplace.settings', $f['business']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('listing')->missing('threads'));
});

it('resumes a message alert after sign in without accepting arbitrary return URLs or bypassing ownership', function () {
    $f = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $url = '/marketplace/account/bookings/'.$b->appointment->public_id.'/messages';
    $this->get($url.'?return=https://evil.example')->assertRedirect('/marketplace/sign-in');
    $token = bin2hex(random_bytes(32));
    DB::table('marketplace_login_links')->insert(['email' => $b->customer->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
    $this->get('/marketplace/sign-in/link/'.$token);
    $this->post('/marketplace/sign-in/confirm', ['accepted' => true])->assertRedirect($url);
    $this->get($url)->assertOk();
    expect(session()->has('marketplace_conversation_return'))->toBeFalse();
    $other = messageEmailBooking($f);
    $this->get('/marketplace/account/bookings/'.$other->appointment->public_id.'/messages')->assertNotFound();
    $this->withSession(['marketplace_conversation_return' => ['path' => 'https://evil.example', 'expires_at' => now()->addMinute()->timestamp]]);
    expect(app(CustomerConversationReturn::class)->destination(request()))->toBe('/marketplace/account');
});

it('bounds inbox retrieval to one page with aggregate unread and latest-message queries', function () {
    $f = marketplaceBookablePath();
    for ($i = 0; $i < 100; $i++) {
        $b = messageEmailBooking($f);
        messageEmailSend($b);
    }
    $this->actingAs($f['user']);
    DB::enableQueryLog();
    $r = $this->get(route('business.marketplace.index', $f['business']))->assertOk();
    $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select') && str_contains($q['query'], 'marketplace_messages'));
    DB::disableQueryLog();
    $r->assertInertia(fn (Assert $p) => $p->has('inbox.threads.data', 25)->where('inbox.threads.total', 100)->where('inbox.threads.data.0.unread', 1));
    expect($queries->count())->toBeLessThanOrEqual(3);
});

it('never falls back to another tenant owner when the intended business owner is revoked', function () {
    $f = marketplaceBookablePath();
    marketplaceBookablePath();
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b);
    $f['membership']->update(['revoked_at' => now()]);
    Notification::fake();
    (new SendMessageEmail($message->id))->handle();
    Notification::assertNothingSent();
    expect(EmailNotificationDelivery::first()->status)->toBe('skipped');
});

it('renders actual Business inbox conversation and private appointment HTML with their respective shared shells', function () {
    if (! getenv('MPG_TEST_LIVE_SSR')) {
        $this->markTestSkipped('Requires the rebuilt local SSR server.');
    }
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => 'http://127.0.0.1:13714']);
    $f = marketplaceBookablePath();
    $b = messageEmailBooking($f);
    messageEmailSend($b);
    $this->actingAs($f['user'])->get(route('business.marketplace.index', $f['business']))->assertOk()
        ->assertSee('class="business-messages"', false)->assertSee('Select a conversation')->assertSee('Search customer or booking reference')
        ->assertDontSee('Meet your next regular')->assertDontSee('Booking entry activity')->assertDontSee('class="market"', false);
    $this->get(route('business.marketplace.thread', [$f['business'], $b->appointment->public_id]))->assertOk()
        ->assertSee('business-thread', false)->assertSee('Private message text must remain in Messages.')->assertSee('Send message')->assertSee('Booking details');
    Auth::guard('web')->logout();
    $links = app(SecureAppointmentLinkService::class);
    foreach (['view', 'cancel', 'reschedule', 'rebook', 'contact', 'payment_status', 'review'] as $purpose) {
        $token = $links->issue($b->appointment, $purpose)['token'];
        $url = '/appointments/secure/'.$token.($purpose === 'view' ? '' : '/'.$purpose);
        $this->get($url)->assertOk()->assertSee('Clipperdesk Marketplace home')->assertSee('mp-self-service-main', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('ClipperDesk booking home');
    }
});

it('disables new and queued email work without erasing conversations or delivery history', function () {
    $b = messageEmailBooking(marketplaceBookablePath());
    $first = messageEmailSend($b);
    config(['marketplace.message_email_enabled' => false]);
    messageEmailSend($b);
    Notification::fake();
    (new SendMessageEmail($first->id))->handle();
    $this->artisan('marketplace:message-email-sweep')->assertSuccessful();
    expect(Message::count())->toBe(2)->and(EmailNotificationDelivery::count())->toBe(1)->and(EmailNotificationDelivery::first()->status)->toBe('queued');
    Queue::assertPushed(SendMessageEmail::class, 1);
    Notification::assertNothingSent();
});

it('uses the Business email stream for a single main owner even when a second owner exists', function () {
    $f = marketplaceBookablePath();
    $second = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $f['business']->id, 'user_id' => $second->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $f['user'], 'Synthetic second owner');
    $b = messageEmailBooking($f);
    $message = messageEmailSend($b);
    (new SendMessageEmail($message->id))->handle();
    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);
    $mail = $sent->first()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe($f['user']->email);
    expect($mail->getHtmlBody())->toContain('New customer message', 'Open conversation')->not->toContain('Private message text', '/appointments/secure/', 'MARKETPLACE');
    expect(EmailNotificationDelivery::first()->status)->toBe('accepted')->and(EmailNotificationDelivery::first()->user_id)->toBe($f['user']->id);
});

it('keeps receptionists inside their assigned branches and prevents access to listing settings', function () {
    $f = marketplaceBookablePath();
    $allowed = messageEmailBooking($f);
    $location = Location::factory()->create(['business_id' => $f['business']->id]);
    $hidden = messageEmailBooking([...$f, 'location' => $location]);
    messageEmailSend($allowed);
    messageEmailSend($hidden);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $member = Membership::factory()->create(['business_id' => $f['business']->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($member, StarterRole::Receptionist, $f['user'], 'Synthetic branch scope');
    $member->locations()->syncWithPivotValues([$f['location']->id], ['business_id' => $f['business']->id]);
    $this->actingAs($user)->get(route('business.marketplace.index', $f['business']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('inbox.threads.data', 1)->where('inbox.threads.data.0.id', $allowed->appointment->public_id));
    $this->get(route('business.marketplace.thread', [$f['business'], $hidden->appointment->public_id]))->assertForbidden();
    $this->get(route('business.marketplace.settings', $f['business']))->assertForbidden();
});
