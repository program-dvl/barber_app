<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Services\ClientRecordService;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientNoteController extends Controller
{
    public function store(Request $request, Business $business, Client $client, ClientRecordService $records, TenantContext $context): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('addNote', $client);
        $data = $request->validate([
            'kind' => ['required', Rule::in(ClientNote::KINDS)], 'visibility' => ['required', Rule::in(['standard', 'sensitive'])],
            'content' => ['required', 'string', 'max:10000'], 'appointment_id' => ['nullable', 'integer'], 'important' => ['nullable', 'boolean'],
        ]);
        if ($data['visibility'] === 'sensitive') {
            abort_unless($request->user()->can('viewSensitive', $client), 403);
        }
        $records->addNote($client, $data['kind'], $data['visibility'], $data['content'], $context->membership()?->staffProfile, $data['appointment_id'] ?? null, (bool) ($data['important'] ?? false));

        return back()->with('status', 'Client note added with author and timestamp.');
    }
}
