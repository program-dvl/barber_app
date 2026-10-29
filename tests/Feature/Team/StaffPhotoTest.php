<?php

use App\Domain\BusinessConfiguration\Services\StaffPhotoService;
use App\Domain\BusinessConfiguration\Services\StaffWorkforceManager;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Files\TenantFilePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('private');
});

function staffPhotoFixture(StarterRole $role = StarterRole::Owner): array
{
    [$user, $business, $membership] = createTenantMembership($role);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $business->update(['booking_slug' => 'team-photos-'.$business->id, 'configuration_published_at' => now(), 'online_booking_enabled' => true]);

    return compact('user', 'business', 'membership', 'location', 'staff');
}

function staffPhotoRevision(StaffProfile $staff): string
{
    return app(StaffWorkforceManager::class)->profileRevision($staff->fresh(['locations']));
}

it('saves an optional real portrait privately and exposes versioned photo URLs without storage keys', function () {
    $p = staffPhotoFixture();
    $revision = staffPhotoRevision($p['staff']);
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $p['staff']]), [
        'photo' => UploadedFile::fake()->image('portrait.png', 1600, 1200), 'profile_revision' => $revision,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $staff = $p['staff']->fresh();
    expect($staff->photo_path)->toStartWith('configuration/team/'.$staff->public_id.'/')->toEndWith('.jpg');
    $bytes = Storage::disk('private')->get(TenantFilePath::private($p['business'], $staff->photo_path));
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 1200, 1 => 900, 2 => IMAGETYPE_JPEG]);
    expect(staffPhotoRevision($staff))->not->toBe($revision);
    $photos = app(StaffPhotoService::class);
    $this->get($photos->teamUrl($p['business'], $staff))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('business.team.index', $p['business']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('staff.0.photo_url', $photos->teamUrl($p['business'], $staff))->missing('staff.0.photo_path'));
    expect(AuditEvent::query()->where('action', 'staff.photo.updated')->count())->toBe(1);
});

it('rejects stale photo edits and repeated removal cannot clear a newer portrait', function () {
    $p = staffPhotoFixture();
    $url = route('business.team.photo.store', [$p['business'], $p['staff']]);
    $revision = staffPhotoRevision($p['staff']);
    $this->actingAs($p['user'])->post($url, ['photo' => UploadedFile::fake()->image('first.jpg', 200, 200), 'profile_revision' => $revision])->assertSessionHasNoErrors();
    $oldUrl = app(StaffPhotoService::class)->url($p['business'], $p['staff']->fresh());
    $current = staffPhotoRevision($p['staff']);
    $this->post($url, ['photo' => UploadedFile::fake()->image('second.jpg', 240, 200), 'profile_revision' => $revision])->assertSessionHasErrors('profile_revision');
    $this->delete(route('business.team.photo.destroy', [$p['business'], $p['staff']]), ['profile_revision' => $revision])->assertSessionHasErrors('profile_revision');
    $this->post($url, ['photo' => UploadedFile::fake()->image('second.jpg', 240, 200), 'profile_revision' => $current])->assertSessionHasNoErrors();
    $this->get($oldUrl)->assertNotFound();
    $currentUrl = app(StaffPhotoService::class)->url($p['business'], $p['staff']->fresh());
    $this->delete(route('business.team.photo.destroy', [$p['business'], $p['staff']]), ['profile_revision' => staffPhotoRevision($p['staff'])])->assertSessionHasNoErrors();
    expect($p['staff']->fresh()->photo_path)->toBeNull();
    $this->get($currentUrl)->assertNotFound();
    expect(AuditEvent::query()->where('action', 'staff.photo.removed')->count())->toBe(1);
});

it('rejects disguised vector files tiny portraits and oversized uploads without changing the profile', function () {
    $p = staffPhotoFixture();
    $url = route('business.team.photo.store', [$p['business'], $p['staff']]);
    $this->actingAs($p['user']);
    foreach ([UploadedFile::fake()->createWithContent('portrait.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->image('tiny.png', 99, 99), UploadedFile::fake()->image('wide.png', 6001, 100), UploadedFile::fake()->image('large.jpg', 200, 200)->size(5121)] as $file) {
        $this->post($url, ['photo' => $file, 'profile_revision' => staffPhotoRevision($p['staff'])])->assertSessionHasErrors('photo');
    }
    expect($p['staff']->fresh()->photo_path)->toBeNull()->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('protects photo writes by tenant role and every assigned branch', function () {
    $p = staffPhotoFixture();
    $other = staffPhotoFixture();
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $other['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => staffPhotoRevision($other['staff'])])->assertNotFound();
    $reception = staffPhotoFixture(StarterRole::Receptionist);
    $this->actingAs($reception['user'])->post(route('business.team.photo.store', [$reception['business'], $reception['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => staffPhotoRevision($reception['staff'])])->assertForbidden();
    $manager = staffPhotoFixture(StarterRole::Manager);
    $branch = Location::factory()->create(['business_id' => $manager['business']->id]);
    $manager['staff']->locations()->attach($branch, ['business_id' => $manager['business']->id]);
    $this->actingAs($manager['user'])->post(route('business.team.photo.store', [$manager['business'], $manager['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => staffPhotoRevision($manager['staff'])])->assertForbidden();
    expect($p['staff']->fresh()->photo_path)->toBeNull()->and($other['staff']->fresh()->photo_path)->toBeNull();
});

it('serves public photos only for a published active business and visible active staff while preview remains authorized', function () {
    $p = staffPhotoFixture();
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $p['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => staffPhotoRevision($p['staff'])])->assertSessionHasNoErrors();
    $photos = app(StaffPhotoService::class);
    $staff = $p['staff']->fresh();
    $url = $photos->url($p['business'], $staff);
    $preview = $photos->url($p['business'], $staff, true);
    $this->get($url)->assertOk();
    $p['staff']->update(['online_visible' => false]);
    $this->get($url)->assertNotFound();
    $this->get($preview)->assertOk();
    $p['staff']->update(['online_visible' => true, 'status' => 'inactive']);
    $this->get($url)->assertNotFound();
    $p['staff']->update(['status' => 'active']);
    foreach ([['configuration_published_at' => null], ['configuration_published_at' => now(), 'online_booking_enabled' => false], ['online_booking_enabled' => true, 'status' => 'suspended']] as $changes) {
        $p['business']->update($changes);
        $this->get($url)->assertNotFound();
    }
    $p['business']->update(['status' => 'active']);
    [$outsider] = createTenantMembership();
    $this->actingAs($outsider)->get($preview)->assertForbidden();
    $this->get($photos->teamUrl($p['business'], $staff))->assertForbidden();
});

it('allows scoped read-only photos while denying unassigned branches and unrelated own-calendar profiles', function () {
    $p = staffPhotoFixture(StarterRole::Receptionist);
    $staff = $p['staff'];
    $staff->update(['photo_path' => 'configuration/team/'.$staff->public_id.'/example.jpg']);
    Storage::disk('private')->put(TenantFilePath::private($p['business'], $staff->photo_path), 'synthetic-photo');
    $url = app(StaffPhotoService::class)->teamUrl($p['business'], $staff);
    $this->actingAs($p['user'])->get($url)->assertOk();
    $staff->locations()->detach();
    $this->get($url)->assertForbidden();
    $own = staffPhotoFixture(StarterRole::BarberStylist);
    $other = StaffProfile::factory()->create(['business_id' => $own['business']->id, 'photo_path' => 'configuration/team/other.jpg']);
    $other->locations()->attach($own['location'], ['business_id' => $own['business']->id]);
    Storage::disk('private')->put(TenantFilePath::private($own['business'], $other->photo_path), 'synthetic-photo');
    $this->actingAs($own['user'])->get(app(StaffPhotoService::class)->teamUrl($own['business'], $other))->assertForbidden();
    $own['staff']->update(['photo_path' => 'configuration/team/own.jpg']);
    Storage::disk('private')->put(TenantFilePath::private($own['business'], $own['staff']->photo_path), 'synthetic-photo');
    $this->get(app(StaffPhotoService::class)->teamUrl($own['business'], $own['staff']))->assertOk();
});

it('invalidates a previously reviewed profile after a photo change without overwriting other profile fields', function () {
    $p = staffPhotoFixture();
    $revision = staffPhotoRevision($p['staff']);
    $before = $p['staff']->only(['display_name', 'email', 'mobile', 'title', 'biography', 'status', 'online_visible']);
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $p['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => $revision])->assertSessionHasNoErrors();
    $this->patch(route('business.team.profile.update', [$p['business'], $p['staff']]), [...$before, 'display_name' => 'Stale edited name', 'location_ids' => [$p['location']->public_id], 'profile_revision' => $revision])->assertSessionHasErrors('profile_revision');
    expect($p['staff']->fresh()->only(array_keys($before)))->toBe($before);
});

it('preserves the existing portrait and gives a useful error when photo storage is unavailable', function () {
    $p = staffPhotoFixture();
    $p['staff']->update(['photo_path' => 'configuration/team/existing.jpg']);
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->with('private')->andReturn($disk);
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $p['staff']]), ['photo' => UploadedFile::fake()->image('portrait.jpg', 200, 200), 'profile_revision' => staffPhotoRevision($p['staff'])])->assertSessionHasErrors('photo');
    expect($p['staff']->fresh()->photo_path)->toBe('configuration/team/existing.jpg')
        ->and(AuditEvent::query()->where('action', 'staff.photo.updated')->count())->toBe(0);
});

it('keeps phone portrait orientation while stripping its EXIF metadata', function (int $orientation, string $topLeft) {
    $p = staffPhotoFixture();
    $image = imagecreatetruecolor(200, 300);
    imagefilledrectangle($image, 0, 0, 99, 149, imagecolorallocate($image, 240, 10, 10));
    imagefilledrectangle($image, 100, 0, 199, 149, imagecolorallocate($image, 10, 240, 10));
    imagefilledrectangle($image, 0, 150, 99, 299, imagecolorallocate($image, 10, 10, 240));
    imagefilledrectangle($image, 100, 150, 199, 299, imagecolorallocate($image, 240, 240, 10));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0".pack('v', $orientation)."\0\0\0\0\0\0";
    $phonePhoto = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $this->actingAs($p['user'])->post(route('business.team.photo.store', [$p['business'], $p['staff']]), ['photo' => UploadedFile::fake()->createWithContent('phone.jpg', $phonePhoto), 'profile_revision' => staffPhotoRevision($p['staff'])])->assertSessionHasNoErrors();
    $bytes = Storage::disk('private')->get(TenantFilePath::private($p['business'], $p['staff']->fresh()->photo_path));
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 300, 1 => 200])->and($bytes)->not->toContain('Exif');
    $result = imagecreatefromstring($bytes);
    $color = imagecolorsforindex($result, imagecolorat($result, 20, 20));
    imagedestroy($result);
    foreach (['red', 'green', 'blue'] as $channel) {
        expect($color[$channel])->{$channel === $topLeft || ($topLeft === 'yellow' && in_array($channel, ['red', 'green'], true)) ? 'toBeGreaterThan' : 'toBeLessThan'}(100);
    }
})->with([[5, 'red'], [6, 'blue'], [7, 'yellow']]);
