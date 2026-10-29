<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('reports unavailable photo storage without exposing database details or pretending the gallery is empty', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    Schema::drop('business_photos');

    $response = $this->actingAs($owner)->getJson(route('business.configuration.photos.index', $business))
        ->assertStatus(503)->assertJsonPath('code', 'GALLERY_UNAVAILABLE')
        ->assertJsonPath('support_email', config('brand.support_email'))
        ->assertJsonMissingPath('photos');

    expect($response->json('support_reference'))->toBeString()->not->toBeEmpty();
    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('business_photos')->not->toContain('migration');
});
