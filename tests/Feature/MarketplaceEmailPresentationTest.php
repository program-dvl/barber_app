<?php

use App\Domain\Communications\Services\ClientNotificationEmail;
use App\Mail\Marketplace\SignInLink;
use App\Models\User;
use App\Support\Mail\MarketplaceEmailBrand;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;

it('delivers the Marketplace sign-in logo as a related PNG instead of a development-host image', function () {
    config(['brand.website_url' => 'http://barber_app.test', 'mail.mailers.array.transport' => 'array']);
    $sent = Mail::mailer('array')->to('synthetic@example.test')->send(new SignInLink('https://example.test/synthetic-sign-in'));
    $email = $sent->getSymfonySentMessage()->getOriginalMessage();
    $images = $email->getAttachments();
    expect($images)->toHaveCount(1);
    expect($images[0]->getMediaType().'/'.$images[0]->getMediaSubtype())->toBe('image/png');
    expect($images[0]->getDisposition())->toBe('inline');
    expect(base64_decode($images[0]->bodyToString()))->toBe(file_get_contents(public_path('images/brand/'.MarketplaceEmailBrand::FILENAME)));
    expect($email->getHtmlBody())->toContain('cid:'.$images[0]->getContentId(), 'https://example.test/synthetic-sign-in')
        ->not->toContain('src="http://barber_app.test', 'src="data:', 'src="http://localhost');
    expect($email->getTextBody())->toContain('15 minutes', 'https://example.test/synthetic-sign-in')->not->toContain('cid:', 'base64');
});

it('renders a self-contained logo preview and a consistent ivory surround without inherited blue panels', function () {
    $html = (new SignInLink('https://example.test/synthetic-sign-in'))->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $image = $dom->getElementsByTagName('img')->item(0);
    expect($image)->not->toBeNull();
    $source = $image->getAttribute('src');
    expect($source)->toStartWith('data:image/png;base64,');
    expect(base64_decode(substr($source, strlen('data:image/png;base64,'))))->toBe(file_get_contents(public_path('images/brand/'.MarketplaceEmailBrand::FILENAME)));
    foreach (['body', 'wrapper', 'content', 'body-cell'] as $area) {
        $node = $area === 'body' ? $dom->getElementsByTagName('body')->item(0) : $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.($area === 'body-cell' ? 'body' : $area).' ")]')->item(0);
        expect($node)->not->toBeNull();
        expect($node->getAttribute('style'))->toMatch('/background-color:\s*#faf9f5/i');
    }
    expect($html)->toContain('Sign in to Clipperdesk', 'saved businesses')->not->toContain('#f2f3f8', '#eef2ff', '#4b5871', 'CUSTOMER ACCOUNT');
});

it('keeps text branding useful without an image and scopes the palette to Marketplace mail', function () {
    $html = view('emails.partials.marketplace-brand', ['url' => '/marketplace', 'logoSrc' => ''])->render();
    expect($html)->toContain('Clipperdesk', 'MARKETPLACE')->not->toContain('<img');
    $owner = new User(['name' => 'Synthetic Owner']);
    $businessHtml = app(Markdown::class)->theme('default')->render('emails.welcome', ['user' => $owner])->toHtml();
    expect($businessHtml)->toContain('clipperdesk-mark-CDKNGozz.svg')->not->toContain('cid:'.MarketplaceEmailBrand::FILENAME);
});

it('uses the same embedded logo reference for client appointment and receipt HTML', function () {
    foreach (['booking_confirmation', 'appointment_changed', 'appointment_cancelled', 'appointment_reminder', 'payment_receipt', 'visit_review', 'waitlist_opening', 'queue_update'] as $type) {
        $html = app(ClientNotificationEmail::class)->render($type, ['business_name' => 'Synthetic Studio'], 'Saved plain wording', 'Sample update');
        expect($html)->toContain('src="'.MarketplaceEmailBrand::SOURCE.'"', 'Synthetic Studio', '#faf9f5')->not->toContain('src="http');
    }
});
