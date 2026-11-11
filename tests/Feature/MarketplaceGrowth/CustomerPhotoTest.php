<?php

use App\Domain\Marketplace\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
    config(['inertia.ssr.enabled' => false]);
});
function growthPhotoCustomer(string $email): Customer
{
    return Customer::query()->create(['email' => $email, 'name' => 'Photo Customer', 'email_verified_at' => now()]);
}

it('normalizes customer photos privately and displays them in the customers own navigation only', function () {
    $customer = growthPhotoCustomer('photo@example.test');
    $photo = UploadedFile::fake()->image('avatar.png', 400, 300);
    $this->actingAs($customer, 'marketplace')->post('/marketplace/profile/photo', ['photo' => $photo])->assertRedirect('/marketplace/profile')->assertSessionHasNoErrors();
    $path = $customer->fresh()->photo_path;
    Storage::disk('private')->assertExists($path);
    $bytes = Storage::disk('private')->get($path);
    $info = getimagesizefromstring($bytes);
    expect([$info[0], $info[1], $info['mime']])->toBe([256, 256, 'image/jpeg']);
    expect($bytes)->not->toContain('Exif');
    $url = $customer->fresh()->photoUrl();
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get('/marketplace/profile')->assertInertia(fn (Assert $p) => $p->where('profile.photo_url', $url)->where('marketplaceCustomer.photo_url', $url)->missing('profile.photo_path')->missing('marketplaceCustomer.photo_path'));
    $other = growthPhotoCustomer('other-photo@example.test');
    $this->actingAs($other, 'marketplace')->get($url)->assertNotFound();
    expect($customer->fresh()->photo_path)->toBe($path);
});
it('rejects unsafe small oversized and invalid photos without changing saved profile details', function () {
    $customer = growthPhotoCustomer('validate-photo@example.test');
    $this->actingAs($customer, 'marketplace');
    foreach ([UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->image('tiny.jpg', 30, 30), UploadedFile::fake()->image('big.jpg')->size(5121)] as $file) {
        $this->post('/marketplace/profile/photo', ['photo' => $file])->assertSessionHasErrors('photo');
    }
    expect($customer->fresh()->photo_path)->toBeNull()->and($customer->fresh()->name)->toBe('Photo Customer');
});
it('removes a photo with initials fallback while retaining the customer account', function () {
    $customer = growthPhotoCustomer('remove-photo@example.test');
    $this->actingAs($customer, 'marketplace')->post('/marketplace/profile/photo', ['photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200)])->assertSessionHasNoErrors();
    $this->delete('/marketplace/profile/photo')->assertRedirect('/marketplace/profile');
    $this->delete('/marketplace/profile/photo')->assertRedirect('/marketplace/profile');
    expect($customer->fresh()->photo_path)->toBeNull()->and(Customer::count())->toBe(1);
    $this->get('/marketplace/profile/photo')->assertNotFound();
});
it('keeps customer photos inaccessible to guests and business-only sessions', function () {
    $this->get('/marketplace/profile/photo')->assertRedirect('/marketplace/sign-in');
    $this->post('/marketplace/profile/photo', ['photo' => UploadedFile::fake()->image('avatar.jpg')])->assertRedirect('/marketplace/sign-in');
});
