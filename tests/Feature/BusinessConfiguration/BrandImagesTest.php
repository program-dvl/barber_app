<?php

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Support\Audit\AuditWriter;
use App\Support\Files\TenantFilePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('private');
});

it('saves and privately previews logo and cover before allowing explicit audited removal', function (string $kind, string $field) {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $url = route('business.configuration.branding.store', $business);
    $this->actingAs($owner)->post($url, ['kind' => $kind, 'asset' => UploadedFile::fake()->image('image.png', 320, 240), 'existing_image' => null])->assertRedirect()->assertSessionHasNoErrors();
    $path = $business->fresh()->{$field};
    expect($path)->not->toBeNull();
    $media = route('business.configuration.booking-preview.media', [$business, $kind]);
    $this->get($media)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->delete(route('business.configuration.branding.destroy', [$business, $kind]), ['existing_image' => $path])->assertSessionHasNoErrors();
    $this->delete(route('business.configuration.branding.destroy', [$business, $kind]), ['existing_image' => $path])->assertSessionHasNoErrors();
    expect($business->fresh()->{$field})->toBeNull()->and($business->fresh()->configuration_published_at)->toBeNull();
    $this->get($media)->assertNotFound();
    expect(AuditEvent::query()->where('action', 'configuration.brand_asset.removed')->count())->toBe(1);
})->with([['logo', 'logo_path'], ['cover', 'cover_image_path']]);

it('rejects stale image replacement and removal without changing a newer image', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $business->update(['logo_path' => 'configuration/branding/newer.png']);
    $this->actingAs($owner)->post(route('business.configuration.branding.store', $business), ['kind' => 'logo', 'asset' => UploadedFile::fake()->image('image.png', 320, 240), 'existing_image' => 'configuration/branding/older.png'])->assertSessionHasErrors('existing_image');
    $this->delete(route('business.configuration.branding.destroy', [$business, 'logo']), ['existing_image' => 'configuration/branding/older.png'])->assertSessionHasErrors('existing_image');
    expect($business->fresh()->logo_path)->toBe('configuration/branding/newer.png');
});

it('keeps image changes within business settings permission and all branch access', function () {
    [$owner, $business, $membership] = createTenantMembership();
    activateTestSubscription($business);
    $business->update(['logo_path' => 'configuration/branding/saved.png']);
    [$outsider] = createTenantMembership();
    $url = route('business.configuration.branding.destroy', [$business, 'logo']);
    $this->actingAs($outsider)->delete($url, ['existing_image' => $business->logo_path])->assertForbidden();
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Receptionist, $owner, 'Image permission test.');
    $this->actingAs($owner)->delete($url, ['existing_image' => $business->logo_path])->assertForbidden();
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Manager, $owner, 'Scoped manager image test.');
    Location::factory()->create(['business_id' => $business->id]);
    $this->delete($url, ['existing_image' => $business->logo_path])->assertForbidden();
    expect($business->fresh()->logo_path)->toBe('configuration/branding/saved.png');
});

it('rejects decoded image dimensions before storage for logos and covers', function (string $kind, int $width, int $height) {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $this->actingAs($owner)->post(route('business.configuration.branding.store', $business), ['kind' => $kind, 'asset' => UploadedFile::fake()->image('image.png', $width, $height), 'existing_image' => null])->assertSessionHasErrors('asset');
    expect($business->fresh()->logo_path)->toBeNull()->and($business->fresh()->cover_image_path)->toBeNull()->and(Storage::disk('private')->allFiles())->toBe([]);
})->with([['logo', 31, 100], ['cover', 159, 300], ['logo', 6001, 100], ['cover', 4000, 4000]]);

it('preserves transparent logo pixels while producing a new metadata-free PNG', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $image = imagecreatetruecolor(80, 80);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 20, 20, 60, 60, imagecolorallocatealpha($image, 30, 80, 160, 0));
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    $this->actingAs($owner)->post(route('business.configuration.branding.store', $business), ['kind' => 'logo', 'asset' => UploadedFile::fake()->createWithContent('logo.png', $png), 'existing_image' => null])->assertSessionHasNoErrors();
    $path = $business->fresh()->logo_path;
    expect($path)->toEndWith('.png');
    $bytes = Storage::disk('private')->get(TenantFilePath::private($business, $path));
    $result = imagecreatefromstring($bytes);
    expect(imagecolorsforindex($result, imagecolorat($result, 0, 0))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($result, imagecolorat($result, 40, 40))['alpha'])->toBe(0);
    imagedestroy($result);
});

it('orients a phone cover and strips EXIF metadata while normalizing to JPEG', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $image = imagecreatetruecolor(200, 300);
    imagefill($image, 0, 0, imagecolorallocate($image, 240, 10, 10));
    imagefilledrectangle($image, 0, 150, 199, 299, imagecolorallocate($image, 10, 10, 240));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();
    imagedestroy($image);
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0".pack('v', 6)."\0\0\0\0\0\0";
    $phonePhoto = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $this->actingAs($owner)->post(route('business.configuration.branding.store', $business), ['kind' => 'cover', 'asset' => UploadedFile::fake()->createWithContent('phone.jpg', $phonePhoto), 'existing_image' => null])->assertSessionHasNoErrors();
    $bytes = Storage::disk('private')->get(TenantFilePath::private($business, $business->fresh()->cover_image_path));
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 300, 1 => 200, 2 => IMAGETYPE_JPEG])->and($bytes)->not->toContain('Exif');
    $result = imagecreatefromstring($bytes);
    expect(imagecolorsforindex($result, imagecolorat($result, 20, 20))['blue'])->toBeGreaterThan(200);
    imagedestroy($result);
});

it('removes a newly written image when the audited database update fails', function () {
    [$owner, $business] = createTenantMembership();
    activateTestSubscription($business);
    $this->instance(AuditWriter::class, Mockery::mock(AuditWriter::class, function ($mock) {
        $mock->shouldReceive('write')->once()->andThrow(new RuntimeException('Synthetic audit failure.'));
    }));
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($owner)->post(route('business.configuration.branding.store', $business), ['kind' => 'logo', 'asset' => UploadedFile::fake()->image('logo.png', 100, 100), 'existing_image' => null]);
        $this->fail('Expected the synthetic audit failure.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Synthetic audit failure.');
    }
    expect($business->fresh()->logo_path)->toBeNull()->and(Storage::disk('private')->allFiles())->toBe([]);
});
