<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientFormController extends Controller
{
    public function publish(Request $request, Business $business, ClientFormService $forms, TenantContext $context): RedirectResponse
    {
        abort_unless($context->membership()?->hasPermissionTo(PermissionName::ClientFormsManage->value, 'web'), 403);
        $data = $request->validate([
            'template' => ['nullable', 'string', 'max:26'], 'name' => ['required', 'string', 'max:255'], 'purpose' => ['required', 'string', 'max:48'],
            'title' => ['required', 'string', 'max:255'], 'introduction' => ['nullable', 'string', 'max:10000'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'fields.*.label' => ['required', 'string', 'max:500'],
            'fields.*.type' => ['required', Rule::in(ClientFormService::FIELD_TYPES)],
            'fields.*.required' => ['boolean'], 'fields.*.options' => ['array', 'max:50'],
            'fields.*.options.*' => ['string', 'max:255'],
            'services' => ['nullable', 'array', 'max:100'], 'services.*' => ['string', 'distinct', 'max:26'],
        ]);
        $serviceIds = Service::query()->where('business_id', $business->id)
            ->whereIn('public_id', $data['services'] ?? [])->pluck('id')->all();
        if (count(array_unique($data['services'] ?? [])) !== count($serviceIds)) {
            throw ValidationException::withMessages(['services' => 'Every associated service must belong to this business.']);
        }
        $template = isset($data['template'])
            ? ClientFormTemplate::query()->where('business_id', $business->id)->where('public_id', $data['template'])->firstOrFail()
            : ClientFormTemplate::query()->create(['business_id' => $business->id, 'name' => $data['name'], 'purpose' => $data['purpose']]);
        $forms->publish($template, $data['title'], $data['introduction'] ?? null, $data['fields'], $serviceIds, $context->membership()?->staffProfile?->id);

        return back()->with('status', 'Form template published as a new immutable version.');
    }

    public function request(Request $request, Business $business, Client $client, ClientFormService $forms): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('manageForms', $client);
        $data = $request->validate(['template' => ['required', 'string'], 'appointment' => ['nullable', 'string']]);
        $template = ClientFormTemplate::query()->where('business_id', $business->id)->where('public_id', $data['template'])->firstOrFail();
        $formRequest = $forms->requestForAppointmentReference($client, $template, $data['appointment'] ?? null);
        $issued = $forms->issueSecureLink($formRequest);

        return back()->with('status', 'Pre-appointment form requested with an expiring secure link.')
            ->with('form_url', route('client-forms.view', $issued['token']));
    }
}
