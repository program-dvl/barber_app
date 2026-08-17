<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Services\ClientMergeService;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientDuplicateController extends Controller
{
    public function preview(Request $request, Business $business, int $candidate, ClientMergeService $merges, TenantContext $context): JsonResponse
    {
        $candidate = ClientDuplicateCandidate::query()->where('business_id', $business->id)->findOrFail($candidate);
        $survivor = Client::query()->where('business_id', $business->id)->where('public_id', $request->string('survivor')->toString())->firstOrFail();

        return response()->json($merges->preview($candidate, $survivor, $context->membership()));
    }

    public function merge(Request $request, Business $business, int $candidate, ClientMergeService $merges, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'survivor' => ['required', 'string'], 'survivor_version' => ['required', 'integer'],
            'duplicate_version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['accepted'],
        ]);
        $candidate = ClientDuplicateCandidate::query()->where('business_id', $business->id)->findOrFail($candidate);
        $survivor = Client::query()->where('business_id', $business->id)->where('public_id', $data['survivor'])->firstOrFail();
        $merged = $merges->merge($candidate, $survivor, $context->membership(), $data['survivor_version'], $data['duplicate_version'], $data['reason']);

        return redirect()->route('business.clients.show', [$business, $merged])->with('status', 'Client records merged with relationships and evidence preserved.');
    }
}
