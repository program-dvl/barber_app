<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Domain\BusinessConfiguration\Services\BusinessSetupAccess;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessPhotoController extends Controller
{
    public function __construct(private readonly BusinessPhotoGallery $gallery) {}

    private function authorizeGallery(Business $business, bool $write = false): void
    {
        abort_unless(app(BusinessSetupAccess::class)->allows($business, app(TenantContext::class)->membership()), 403);
        if ($write) {
            app(EntitlementEvaluator::class)->authorize($business, 'branding.custom', 'use');
        }
    }

    public function index(Request $request, Business $business)
    {
        $this->authorizeGallery($business);

        try {
            return response()->json($this->gallery->presentation($business, true));
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'code' => 'GALLERY_UNAVAILABLE',
                'message' => 'Salon photos are temporarily unavailable. Your other setup details are saved. You can continue setup and add photos later.',
                'support_reference' => (string) ($request->attributes->get('correlation_id') ?: Str::uuid()),
                'support_email' => config('brand.support_email'),
            ], 503);
        }
    }

    public function store(Request $request, Business $business)
    {
        $this->authorizeGallery($business, true);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:180'], 'revision' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $this->gallery->store($business, $request->file('photo'), $data['caption'] ?? null, $data['revision'], $data['idempotency_key']);

        return response()->json($this->gallery->presentation($business, true));
    }

    public function update(Request $request, Business $business, string $photo)
    {
        $this->authorizeGallery($business, true);
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:180'], 'position' => ['required', 'integer', 'between:0,7'],
            'revision' => ['required', 'string', 'size:64'],
        ]);
        $this->gallery->update($business, $photo, $data['caption'] ?? null, $data['position'], $data['revision']);

        return response()->json($this->gallery->presentation($business, true));
    }

    public function destroy(Request $request, Business $business, string $photo)
    {
        $this->authorizeGallery($business, true);
        $data = $request->validate(['revision' => ['required', 'string', 'size:64']]);
        $this->gallery->remove($business, $photo, $data['revision']);

        return response()->json($this->gallery->presentation($business, true));
    }

    public function media(Business $business, string $photo)
    {
        $this->authorizeGallery($business);

        return $this->gallery->image($business, $photo);
    }
}
