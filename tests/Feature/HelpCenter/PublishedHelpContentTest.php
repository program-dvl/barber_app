<?php

use App\Domain\HelpCenter\HelpCatalog;
use App\Domain\HelpCenter\HelpSearch;

it('publishes a connected corpus across every supported help category with valid routes and media', function () {
    $catalog = app(HelpCatalog::class);
    expect($catalog->validate())->toBe([]);
    foreach ($catalog->categories() as $category) {
        expect($category['article_count'])->toBeGreaterThan(0);
    }
    foreach ($catalog->articles() as $article) {
        $text = $article['title'].' '.$article['summary'].' '.implode(' ', array_column($article['sections'], 'body'));
        expect($text)->not->toMatch('/\b(?:FR-\d|ADR-\d|OPEN-\d|TODO|FIXME)\b/');
    }
});

it('finds relevant published guidance for realistic customer questions', function (string $question, string $article) {
    $matches = app(HelpSearch::class)->query($question, 3);
    expect(array_column($matches, 'slug'))->toContain($article);
})->with([
    ['How do I add another location?', 'business-location'],
    ['staff', 'team-add-member'],
    ['employee', 'team-add-member'],
    ['team member', 'team-add-member'],
    ['add worker', 'team-add-member'],
    ['How do I invite staff?', 'team-invite-login'],
    ['locaton', 'business-location'],
    ['service not available for booking', 'services-online-availability'],
    ['no time slots', 'appointments-no-slots'],
    ['cancel subscription', 'billing-cancel'],
    ['forgot password', 'account-recovery'],
    ['refund payment', 'checkout-refund'],
    ['stock inventory', 'inventory-availability'],
    ['appointment reminders', 'notifications-preferences'],
]);
