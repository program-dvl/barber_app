<?php

use App\Domain\BusinessConfiguration\Models\BusinessPhoto;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantFilePath;
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
    $this->business->update(['booking_slug' => 'gallery-example']);
    $this->actingAs($this->owner);
});

function galleryMember($business, StarterRole $role): User
{
    $user = User::factory()->create();
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, $role, $user, 'Gallery access fixture.');

    return $user;
}

function addGalleryPhoto($test, ?string $caption = null): array
{
    $response = $test->postJson(route('business.configuration.photos.store', $test->business), [
        'photo' => UploadedFile::fake()->image('salon.png', 320, 240), 'caption' => $caption,
        'revision' => app(BusinessPhotoGallery::class)->revision($test->business), 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    return $response->json();
}

it('has an optional empty gallery without changing publication or readiness fields', function () {
    $before = $this->business->fresh()->getAttributes();
    $this->getJson(route('business.configuration.photos.index', $this->business))->assertOk()->assertJsonPath('photos', [])->assertJsonPath('limit', 8);
    expect($this->business->fresh()->getAttributes())->toBe($before);
});

it('stores normalized private photos and replays uploads without duplicate records or audits', function () {
    $file = UploadedFile::fake()->image('salon.png', 320, 240);
    $payload = ['photo' => $file, 'caption' => 'Our styling space', 'revision' => app(BusinessPhotoGallery::class)->revision($this->business), 'idempotency_key' => (string) Str::uuid()];
    $route = route('business.configuration.photos.store', $this->business);
    $response = $this->postJson($route, $payload)->assertOk()->assertJsonCount(1, 'photos');
    $this->postJson($route, $payload)->assertOk()->assertJsonCount(1, 'photos');
    expect(BusinessPhoto::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'configuration.gallery.photo_added')->count())->toBe(1);
    $photo = BusinessPhoto::query()->firstOrFail();
    Storage::disk('private')->assertExists(TenantFilePath::private($this->business, $photo->path));
    expect($response->json('photos.0'))->toHaveKeys(['public_id', 'caption', 'url'])->not->toHaveKey('path');
    $this->get($response->json('photos.0.url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
    $payload['photo'] = UploadedFile::fake()->image('other.png', 480, 320);
    $this->postJson($route, $payload)->assertUnprocessable()->assertJsonValidationErrors('photo');
});

it('rejects invalid raster sizes formats large files and overlong captions', function () {
    $base = ['revision' => app(BusinessPhotoGallery::class)->revision($this->business), 'idempotency_key' => (string) Str::uuid()];
    $route = route('business.configuration.photos.store', $this->business);
    foreach ([UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), UploadedFile::fake()->image('tiny.png', 20, 20), UploadedFile::fake()->image('wide.png', 6001, 160), UploadedFile::fake()->image('big.png', 320, 240)->size(6000)] as $file) {
        $this->postJson($route, [...$base, 'photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }
    $this->postJson($route, [...$base, 'photo' => UploadedFile::fake()->image('salon.png', 320, 240), 'caption' => str_repeat('a', 181)])->assertUnprocessable()->assertJsonValidationErrors('caption');
    expect(BusinessPhoto::query()->count())->toBe(0)->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('preserves phone photo orientation while discarding embedded EXIF metadata', function () {
    $jpeg = UploadedFile::fake()->image('phone.jpg', 320, 240)->get();
    $exif = "Exif\0\0II\x2A\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00\x00\x00\x00\x00";
    $file = UploadedFile::fake()->createWithContent('phone.jpg', substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
    $this->postJson(route('business.configuration.photos.store', $this->business), [
        'photo' => $file, 'revision' => app(BusinessPhotoGallery::class)->revision($this->business), 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();
    $photo = BusinessPhoto::query()->firstOrFail();
    $stored = Storage::disk('private')->get(TenantFilePath::private($this->business, $photo->path));
    $size = getimagesizefromstring($stored);
    expect([$size[0], $size[1]])->toBe([240, 320])->and(str_contains($stored, 'Exif'))->toBeFalse();
});

it('orders and captions photos while rejecting stale edits', function () {
    $first = addGalleryPhoto($this, 'First');
    $second = addGalleryPhoto($this, 'Second');
    $photo = $second['photos'][1];
    $this->putJson(route('business.configuration.photos.update', [$this->business, $photo['public_id']]), [
        'caption' => 'Featured space', 'position' => 0, 'revision' => $second['revision'],
    ])->assertOk()->assertJsonPath('photos.0.public_id', $photo['public_id'])->assertJsonPath('photos.0.caption', 'Featured space');
    $this->putJson(route('business.configuration.photos.update', [$this->business, $first['photos'][0]['public_id']]), [
        'caption' => 'Stale edit', 'position' => 0, 'revision' => $second['revision'],
    ])->assertUnprocessable()->assertJsonValidationErrors('revision');
    expect(app(BusinessPhotoGallery::class)->photos($this->business)->pluck('caption')->all())->toBe(['Featured space', 'First']);
});

it('limits uploads to eight without orphaning a ninth image', function () {
    for ($i = 0; $i < 8; $i++) {
        addGalleryPhoto($this);
    }
    $this->postJson(route('business.configuration.photos.store', $this->business), [
        'photo' => UploadedFile::fake()->image('ninth.png', 320, 240), 'revision' => app(BusinessPhotoGallery::class)->revision($this->business), 'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    expect(BusinessPhoto::query()->count())->toBe(8)->and(Storage::disk('private')->allFiles())->toHaveCount(8);
});

it('keeps gallery reads writes and files within tenant and settings scope', function () {
    $gallery = addGalleryPhoto($this);
    $photoId = $gallery['photos'][0]['public_id'];
    [$outsider, $other] = createTenantMembership();
    activateTestSubscription($other);
    $this->actingAs($outsider)->getJson(route('business.configuration.photos.index', $this->business))->assertForbidden();
    $this->get($gallery['photos'][0]['url'])->assertForbidden();
    $this->deleteJson(route('business.configuration.photos.destroy', [$other, $photoId]), ['revision' => app(BusinessPhotoGallery::class)->revision($other)])->assertNotFound();
    $this->actingAs(galleryMember($this->business, StarterRole::Receptionist))->getJson(route('business.configuration.photos.index', $this->business))->assertForbidden();
    $branchManager = galleryMember($this->business, StarterRole::Manager);
    $locations = Location::factory()->count(2)->create(['business_id' => $this->business->id]);
    $branchManager->memberships()->first()->locations()->attach($locations[0]->id, ['business_id' => $this->business->id]);
    $this->actingAs($branchManager)->getJson(route('business.configuration.photos.index', $this->business))->assertForbidden();
    $this->deleteJson(route('business.configuration.photos.destroy', [$this->business, $photoId]), ['revision' => $gallery['revision']])->assertForbidden();
    expect(BusinessPhoto::query()->count())->toBe(1);
});

it('serves only published active photos and immediately hides removed and paused photos', function () {
    $gallery = addGalleryPhoto($this);
    $id = $gallery['photos'][0]['public_id'];
    $public = route('public.booking.photos.media', [$this->business->booking_slug, $id]);
    $this->get($public)->assertNotFound();
    $this->business->update(['configuration_published_at' => now(), 'online_booking_enabled' => true]);
    $this->get($public)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->business->update(['online_booking_enabled' => false]);
    $this->get($public)->assertNotFound();
    $this->business->update(['online_booking_enabled' => true]);
    $delete = route('business.configuration.photos.destroy', [$this->business, $id]);
    $this->deleteJson($delete, ['revision' => $gallery['revision']])->assertOk()->assertJsonPath('photos', []);
    $this->deleteJson($delete, ['revision' => $gallery['revision']])->assertOk();
    $this->get($public)->assertNotFound();
    $this->get($gallery['photos'][0]['url'])->assertNotFound();
    expect(AuditEvent::query()->where('action', 'configuration.gallery.photo_removed')->count())->toBe(1);
});

it('cleans up the private file when a failed audit rolls back the upload', function () {
    $revision = app(BusinessPhotoGallery::class)->revision($this->business);
    $writer = Mockery::mock(AuditWriter::class);
    $writer->shouldReceive('write')->once()->andThrow(new RuntimeException('Simulated audit write failure.'));
    $this->app->instance(AuditWriter::class, $writer);
    $this->postJson(route('business.configuration.photos.store', $this->business), [
        'photo' => UploadedFile::fake()->image('rollback.png', 320, 240), 'revision' => $revision, 'idempotency_key' => (string) Str::uuid(),
    ])->assertServerError();
    expect(BusinessPhoto::query()->count())->toBe(0)->and(Storage::disk('private')->allFiles())->toBe([]);
});
