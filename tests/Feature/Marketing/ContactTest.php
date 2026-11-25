<?php

use App\Mail\SalesEnquiryMail;
use App\Models\SalesEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function contactPayload(string $token): array
{
    return ['submission_id' => $token, 'name' => 'Example Owner', 'email' => 'owner@example.test', 'business' => 'Example Studio', 'industry' => 'salons', 'message' => 'We would like to discuss moving our salon appointments.', 'website' => ''];
}

it('renders a crawlable contact form with private no-store session content', function () {
    config(['inertia.ssr.enabled' => false]);
    $response = $this->get('/contact?industry=salons')->assertOk()->assertSee('A good fit starts');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertSee('name="submission_id"', false)->assertDontSee(config('sales-contact.bcc'));
    $this->get('/sitemap.xml')->assertSee(route('marketing.contact'));
});

it('saves an encrypted enquiry and emails both owner destinations with safe reply-to exactly once', function () {
    Mail::fake();
    $token = (string) Str::uuid();
    $this->withSession(['sales_contact_token' => $token]);
    $this->post('/contact', contactPayload($token))->assertRedirect(route('marketing.contact'));
    $this->post('/contact', contactPayload($token))->assertRedirect(route('marketing.contact'));
    expect(SalesEnquiry::count())->toBe(1);
    $enquiry = SalesEnquiry::first();
    expect($enquiry->delivery_status)->toBe('sent')->and($enquiry->getRawOriginal('email'))->not->toContain('owner@example.test');
    Mail::assertSent(SalesEnquiryMail::class, function ($mail) {
        return $mail->hasTo('sales@clipperdesk.com') && $mail->hasBcc('dhaval.prajapati333@gmail.com')
            && $mail->envelope()->replyTo[0]->address === 'owner@example.test';
    });
    Mail::assertSentCount(1);
});

it('keeps an enquiry after mail failure and does not resend on browser retry', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Provider unavailable'));
    $token = (string) Str::uuid();
    $this->withSession(['sales_contact_token' => $token]);
    $this->post('/contact', contactPayload($token))->assertRedirect()->assertSessionHas('sales_contact_receipt.delayed', true);
    $this->post('/contact', contactPayload($token))->assertRedirect();
    expect(SalesEnquiry::first()->delivery_status)->toBe('failed')->and(SalesEnquiry::first()->message)->toContain('moving our salon');
});

it('rejects invalid, bot and cross-session submissions without sending', function () {
    Mail::fake();
    $token = (string) Str::uuid();
    $this->withSession(['sales_contact_token' => $token]);
    $this->post('/contact', [...contactPayload($token), 'email' => 'bad', 'message' => 'short'])->assertSessionHasErrors(['email', 'message']);
    $this->post('/contact', [...contactPayload($token), 'website' => 'spam'])->assertSessionHasErrors('website');
    $this->post('/contact', contactPayload((string) Str::uuid()))->assertStatus(419);
    expect(SalesEnquiry::count())->toBe(0);
    Mail::assertNothingSent();
});

it('returns a recoverable cooldown without losing form values', function () {
    Mail::fake();
    $token = (string) Str::uuid();
    $key = 'sales-contact:'.hash('sha256', '127.0.0.1');
    RateLimiter::clear($key);
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 600);
    }
    $this->withSession(['sales_contact_token' => $token])->post('/contact', contactPayload($token))
        ->assertSessionHasErrors(['message', 'retry_after'])->assertSessionHasInput('message');
    RateLimiter::clear($key);
    Mail::assertNothingSent();
});

it('requires verified non-delivery before an operator retries a saved enquiry', function () {
    Mail::fake();
    $item = SalesEnquiry::create(['reference' => (string) Str::uuid(), 'name' => 'Owner', 'email' => 'owner@example.test', 'message' => 'Please help with setup.', 'delivery_status' => 'failed']);
    $this->travel(11)->minutes();
    $this->artisan('sales:enquiries', ['reference' => $item->reference, '--retry' => true])->assertFailed();
    Mail::assertNothingSent();
    $this->artisan('sales:enquiries', ['reference' => $item->reference, '--retry' => true, '--delivery-checked' => true])->assertSuccessful();
    expect($item->fresh()->delivery_status)->toBe('sent');
    $this->artisan('sales:enquiries', ['reference' => $item->reference, '--retry' => true, '--delivery-checked' => true])->assertFailed();
    Mail::assertSentCount(1);
});

it('starts a distinct enquiry only when the visitor requests one', function () {
    $token = (string) Str::uuid();
    $this->withSession(['sales_contact_token' => $token, 'sales_contact_receipt' => ['reference' => $token, 'delayed' => false]])
        ->post('/contact/new')->assertRedirect(route('marketing.contact'))->assertSessionMissing('sales_contact_receipt');
    $this->get('/contact')->assertOk();
    expect(session('sales_contact_token'))->not->toBe($token);
});
