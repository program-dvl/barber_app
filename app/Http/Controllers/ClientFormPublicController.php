<?php

namespace App\Http\Controllers;

use App\Domain\ClientRecords\Services\ClientFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientFormPublicController extends Controller
{
    public function view(string $token, ClientFormService $forms): Response
    {
        $link = $forms->resolveSecureLink($token);
        $request = $link->request;

        return Inertia::render('Booking/ClientForm', [
            'token' => $token, 'title' => $request->version->title, 'introduction' => $request->version->introduction,
            'fields' => $request->version->fields, 'appointmentReference' => $request->appointment?->booking_reference,
            'businessName' => $request->business->name,
            'expiresAt' => $link->expires_at?->toIso8601String(),
        ]);
    }

    public function submit(Request $request, string $token, ClientFormService $forms): RedirectResponse
    {
        $link = $forms->resolveSecureLink($token);
        $data = $request->validate(['answers' => ['nullable', 'array'], 'signature' => ['nullable', 'string', 'max:10000']]);
        $forms->submitSecure($link, $data['answers'] ?? [], $data['signature'] ?? null);

        return redirect()->route('client-forms.completed');
    }
}
