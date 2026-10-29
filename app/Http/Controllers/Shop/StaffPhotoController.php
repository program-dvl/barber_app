<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessConfiguration\Services\BookingSlugManager;
use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\BusinessConfiguration\Services\StaffPhotoService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Support\Files\TenantFilePath;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffPhotoController extends Controller
{
    public function __construct(private readonly StaffPhotoService $photos) {}

    public function store(Request $request, Business $business, StaffProfile $staffProfile)
    {
        $this->photos->authorize($business, $staffProfile);
        $data = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'profile_revision' => ['required', 'string', 'size:64']],
            ['photo.image' => 'Choose a JPEG, PNG or WebP photo.', 'photo.mimes' => 'Choose a JPEG, PNG or WebP photo.', 'photo.max' => 'Choose a photo smaller than 5 MB.']);
        $this->photos->save($business, $staffProfile, $request->file('photo'), $data['profile_revision'], $request->user());

        return back()->with('status', 'Team photo saved. It appears online when this team member is visible for booking.');
    }

    public function destroy(Request $request, Business $business, StaffProfile $staffProfile)
    {
        $this->photos->authorize($business, $staffProfile);
        $data = $request->validate(['profile_revision' => ['required', 'string', 'size:64']]);
        $this->photos->save($business, $staffProfile, null, $data['profile_revision'], $request->user());

        return back()->with('status', 'Team photo removed. Their initials will appear instead.');
    }

    public function show(Business $business, StaffProfile $staffProfile, string $version)
    {
        $this->photos->authorize($business, $staffProfile, false);

        return $this->image($business, $staffProfile, $version);
    }

    public function preview(Business $business, StaffProfile $staffProfile, string $version)
    {
        abort_unless((int) $staffProfile->business_id === (int) $business->id, 404);
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);

        return $this->image($business, $staffProfile, $version);
    }

    public function publicShow(string $slug, string $staff, string $version, BookingSlugManager $slugs)
    {
        $business = $slugs->resolve($slug);
        abort_unless($business?->configuration_published_at && $business->online_booking_enabled && $business->isActive(), 404);
        $profile = StaffProfile::query()->where('business_id', $business->id)->where('public_id', $staff)->where('status', 'active')->where('online_visible', true)->firstOrFail();

        return $this->image($business, $profile, $version);
    }

    private function image(Business $business, StaffProfile $staff, string $version)
    {
        $current = $this->photos->version($staff);
        abort_unless($current && hash_equals($current, $version), 404);
        $key = TenantFilePath::private($business, $staff->photo_path);
        abort_unless(Storage::disk('private')->exists($key), 404);

        return response(Storage::disk('private')->get($key), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
