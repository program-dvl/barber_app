<?php

use App\Domain\Billing\Models\BillingPlanPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('creates two Paddle products and four active recurring price mappings', function () {
    config(['billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    $productCount = 0;
    $priceCount = 0;

    Http::fake(function (ClientRequest $request) use (&$productCount, &$priceCount) {
        if (str_contains($request->url(), '/products')) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => []]);
            }

            $productCount++;

            return Http::response(['data' => ['id' => "pro_test_{$productCount}"]], 201);
        }

        if (str_contains($request->url(), '/prices/')) {
            return Http::response(['error' => ['detail' => 'Price not found.']], 404);
        }

        if (str_ends_with($request->url(), '/prices') && $request->method() === 'POST') {
            $priceCount++;

            return Http::response(['data' => ['id' => "pri_test_{$priceCount}"]], 201);
        }

        return Http::response(['error' => ['detail' => 'Unexpected Paddle request.']], 500);
    });

    $this->artisan('billing:sync-paddle-catalog', ['--apply' => true])
        ->expectsOutputToContain('Paddle catalog synchronized')
        ->assertExitCode(0);

    expect($productCount)->toBe(2)
        ->and($priceCount)->toBe(4)
        ->and(BillingPlanPrice::query()->where('provider', 'paddle')->where('is_active', true)->whereNull('effective_until')->count())->toBe(4)
        ->and(BillingPlanPrice::query()->where('provider', 'paddle')->where('is_active', true)->pluck('amount_minor')->sort()->values()->all())
        ->toBe([5000, 10000, 50000, 100000]);
});

it('does not write to Paddle or change local price records without --apply', function () {
    config(['billing.paddle.api_key' => 'pdl_sdbx_apikey_test']);
    $before = BillingPlanPrice::query()->count();

    Http::fake(fn () => Http::response(['data' => []]));

    $this->artisan('billing:sync-paddle-catalog')
        ->expectsOutputToContain('Dry run only')
        ->assertExitCode(0);

    expect(BillingPlanPrice::query()->count())->toBe($before);
    Http::assertNotSent(fn (ClientRequest $request) => in_array($request->method(), ['POST', 'PATCH'], true));
});
