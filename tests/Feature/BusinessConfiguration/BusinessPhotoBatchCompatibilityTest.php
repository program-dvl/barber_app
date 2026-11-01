<?php

use App\Domain\Billing\Models\BusinessEntitlementOverride;
use App\Domain\Billing\Models\EntitlementDefinition;
use App\Domain\BusinessConfiguration\Models\BusinessPhoto;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Domain\PlatformAccess\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('private');
    [$this->owner, $this->business] = createTenantMembership();
    activateTestSubscription($this->business);
    $this->actingAs($this->owner);
});

it('replays an uncertain upload after later photos fill the gallery without duplicating it', function () {
    $route = route('business.configuration.photos.store', $this->business);
    $first = [
        'photo' => UploadedFile::fake()->image('first.png', 320, 240),
        'caption' => 'First selected photo',
        'revision' => app(BusinessPhotoGallery::class)->revision($this->business),
        'idempotency_key' => (string) Str::uuid(),
    ];
    $gallery = $this->postJson($route, $first)->assertOk()->json();

    for ($index = 1; $index < BusinessPhotoGallery::LIMIT; $index++) {
        $gallery = $this->postJson($route, [
            'photo' => UploadedFile::fake()->image("selected-{$index}.png", 320, 240),
            'caption' => "Selected photo {$index}",
            'revision' => $gallery['revision'],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->json();
    }

    $this->postJson($route, $first)->assertOk()
        ->assertJsonCount(BusinessPhotoGallery::LIMIT, 'photos')
        ->assertJsonPath('revision', $gallery['revision'])
        ->assertJsonPath('photos.0.caption', 'First selected photo');

    expect(BusinessPhoto::query()->count())->toBe(BusinessPhotoGallery::LIMIT)
        ->and(Storage::disk('private')->allFiles())->toHaveCount(BusinessPhotoGallery::LIMIT)
        ->and(AuditEvent::query()->where('action', 'configuration.gallery.photo_added')->count())->toBe(BusinessPhotoGallery::LIMIT);
});

it('keeps prior uploads and their revision when a selected middle image is invalid', function () {
    $route = route('business.configuration.photos.store', $this->business);
    $upload = fn (string $name, string $revision, int $size = 320) => [
        'photo' => UploadedFile::fake()->image($name, $size, $size),
        'caption' => $name,
        'revision' => $revision,
        'idempotency_key' => (string) Str::uuid(),
    ];
    $first = $this->postJson($route, $upload('first.png', app(BusinessPhotoGallery::class)->revision($this->business)))
        ->assertOk()->json();

    $this->postJson($route, $upload('too-small.png', $first['revision'], 20))
        ->assertUnprocessable()->assertJsonValidationErrors('photo');

    expect(app(BusinessPhotoGallery::class)->revision($this->business))->toBe($first['revision']);

    $this->postJson($route, $upload('third.png', $first['revision']))
        ->assertOk()->assertJsonCount(2, 'photos')
        ->assertJsonPath('photos.0.caption', 'first.png')
        ->assertJsonPath('photos.1.caption', 'third.png');

    expect(Storage::disk('private')->allFiles())->toHaveCount(2)
        ->and(AuditEvent::query()->where('action', 'configuration.gallery.photo_added')->count())->toBe(2);
});

it('rechecks upload entitlement between selected photos while keeping saved photos readable', function () {
    $route = route('business.configuration.photos.store', $this->business);
    $gallery = $this->postJson($route, [
        'photo' => UploadedFile::fake()->image('first.png', 320, 240),
        'revision' => app(BusinessPhotoGallery::class)->revision($this->business),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->json();

    BusinessEntitlementOverride::query()->create([
        'business_id' => $this->business->id,
        'entitlement_definition_id' => EntitlementDefinition::query()->where('key', 'branding.custom')->firstOrFail()->id,
        'value' => false,
        'effective_from' => now()->subMinute(),
        'changed_by_user_id' => $this->owner->id,
        'change_reason' => 'Simulate an entitlement change while multiple photos are selected.',
    ]);

    $this->postJson($route, [
        'photo' => UploadedFile::fake()->image('second.png', 320, 240),
        'revision' => $gallery['revision'],
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    $this->getJson(route('business.configuration.photos.index', $this->business))
        ->assertOk()->assertJsonCount(1, 'photos')->assertJsonPath('revision', $gallery['revision']);
    $this->get($gallery['photos'][0]['url'])->assertOk();

    expect(BusinessPhoto::query()->count())->toBe(1)
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1)
        ->and(AuditEvent::query()->where('action', 'configuration.gallery.photo_added')->count())->toBe(1);
});
