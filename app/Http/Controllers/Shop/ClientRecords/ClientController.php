<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\ClientRecords\Services\ClientRecordService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, Business $business, TenantContext $context): Response
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ClientView->value, 'web'), 403);
        $canContact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $search = trim($request->string('search')->toString());
        $query = Client::query()->where('business_id', $business->id)->where('status', 'active')
            ->when($search !== '', function ($query) use ($search, $canContact): void {
                $normalized = mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $search) ?: $search);
                $query->where(function ($query) use ($normalized, $canContact): void {
                    $query->where('normalized_name', 'like', '%'.$normalized.'%');
                    if ($canContact) {
                        $query->orWhere('normalized_email', 'like', '%'.$normalized.'%')
                            ->orWhere('normalized_mobile', 'like', '%'.preg_replace('/\D+/', '', $normalized).'%');
                    }
                });
            });
        if (! $membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && ! $membership->hasRole('owner', 'web')) {
            abort_unless($membership->staffProfile, 403);
            $query->whereHas('appointments.segments', fn ($query) => $query->where('staff_profile_id', $membership->staffProfile->id));
        }
        $clients = $query->withCount(['appointments as visit_count' => fn ($query) => $query->where('status', 'completed')])
            ->orderBy('name')->paginate(30)->withQueryString()->through(fn (Client $client) => [
                'public_id' => $client->public_id, 'name' => $client->name,
                'mobile' => $canContact ? $client->mobile : null, 'email' => $canContact ? $client->email : null,
                'visit_count' => $client->visit_count, 'marketing_status' => $client->marketing_status,
            ]);

        return Inertia::render('Clients/Index', [
            'businessLabel' => $business->name, 'clients' => $clients, 'filters' => ['search' => $search],
            'duplicateCount' => ClientDuplicateCandidate::query()->where('business_id', $business->id)->where('status', 'pending')->count(),
            'canContact' => $canContact,
        ]);
    }

    public function show(Request $request, Business $business, Client $client, ClientRecordService $records, TenantContext $context, AuditWriter $audit): Response
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('view', $client);
        $membership = $context->membership();
        $canContact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $canSensitive = $request->user()->can('viewSensitive', $client);
        $notes = $client->notes()->with('author')->when(! $canSensitive, fn ($query) => $query->where('visibility', 'standard'))->get();
        if ($canSensitive && $notes->contains('visibility', 'sensitive')) {
            $audit->write('client.sensitive_context_viewed', $business, $request->user(), $client, null, [], [], [
                'sensitive_note_count' => $notes->where('visibility', 'sensitive')->count(),
            ], 'client_records');
        }
        $history = $records->historySummary($client);
        $appointments = $history['appointments']->map(fn ($appointment) => [
            'public_id' => $appointment->public_id, 'reference' => $appointment->booking_reference,
            'status' => $appointment->status, 'starts_at' => $appointment->starts_at_utc->toIso8601String(),
            'services' => $appointment->serviceLines->map(fn ($line) => [
                'name' => $line->name,
                'performers' => $line->segments->map(fn ($segment) => $segment->staff?->display_name)->filter()->unique()->values(),
            ]),
        ]);

        return Inertia::render('Clients/Show', [
            'businessLabel' => $business->name,
            'client' => [
                'public_id' => $client->public_id, 'name' => $client->name,
                'mobile' => $canContact ? $client->mobile : null, 'email' => $canContact ? $client->email : null,
                'date_of_birth' => $client->date_of_birth?->toDateString(), 'preferences' => $client->preferences ?? [],
                'communication_preferences' => $client->communication_preferences ?? [], 'referral_source' => $client->referral_source,
                'marketing_status' => $client->marketing_status, 'version' => $client->version,
                'preferred_staff' => $client->preferredStaff?->public_id,
                'preferred_services' => $client->preferredServices()->pluck('services.public_id'),
                'tags' => $client->tags()->pluck('client_tags.name'),
            ],
            'summary' => collect($history)->except('appointments'), 'appointments' => $appointments,
            'notes' => $notes->map(fn ($note) => [
                'id' => $note->id, 'kind' => $note->kind, 'visibility' => $note->visibility,
                'content' => $note->content, 'important' => $note->is_important,
                'author' => $note->author?->display_name ?? 'Former or unavailable staff', 'created_at' => $note->created_at->toIso8601String(),
            ]),
            'consents' => $client->consents()->get()->map->only(['type', 'status', 'source', 'policy_version', 'wording', 'occurred_at']),
            'communications' => $client->communications()->with('intent')->get()->map(fn ($message) => [
                'intent' => $message->intent->intent_type, 'channel' => $message->channel, 'category' => $message->category,
                'status' => $message->status, 'legal_basis' => $message->legal_basis,
                'queued_at' => $message->queued_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
            ]),
            'forms' => $client->formRequests()->with('version')->get()->map(fn ($form) => ['public_id' => $form->public_id, 'title' => $form->version->title, 'version' => $form->version->version, 'status' => $form->status, 'requested_at' => $form->requested_at->toIso8601String(), 'completed_at' => $form->completed_at?->toIso8601String()]),
            'formTemplates' => ClientFormTemplate::query()->with(['versions', 'services'])->where('business_id', $business->id)->where('status', 'published')->orderBy('name')->get()->map(function ($template): array {
                $version = $template->versions->firstWhere('version', $template->current_version);

                return [
                    ...$template->only(['public_id', 'name', 'purpose', 'current_version']),
                    'title' => $version?->title, 'introduction' => $version?->introduction,
                    'fields' => $version?->fields ?? [],
                    'services' => $template->services->pluck('public_id'),
                ];
            }),
            'staffOptions' => StaffProfile::query()->where('business_id', $business->id)->where('status', 'active')->orderBy('display_name')->get()->map->only(['public_id', 'display_name']),
            'serviceOptions' => Service::query()->where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get()->map->only(['public_id', 'name']),
            'attachments' => $request->user()->can('viewAttachments', $client) ? $client->attachments()->get()->map->only(['public_id', 'kind', 'original_name', 'mime_type', 'size_bytes', 'visibility', 'created_at']) : [],
            'privacyRequests' => $request->user()->can('managePrivacy', $client) ? $client->privacyRequests()->get()->map->only(['public_id', 'type', 'status', 'requested_at', 'due_at', 'result_summary']) : [],
            'duplicates' => ClientDuplicateCandidate::query()->with(['firstClient', 'secondClient'])->where('business_id', $business->id)->where('status', 'pending')->where(fn ($query) => $query->where('first_client_id', $client->id)->orWhere('second_client_id', $client->id))->get()->map(fn ($candidate) => ['id' => $candidate->id, 'other' => $candidate->first_client_id === $client->id ? $candidate->secondClient->only(['public_id', 'name', 'version']) : $candidate->firstClient->only(['public_id', 'name', 'version']), 'confidence' => $candidate->confidence, 'reasons' => $candidate->reasons]),
            'permissions' => [
                'update' => $request->user()->can('update', $client), 'addNote' => $request->user()->can('addNote', $client),
                'sensitive' => $canSensitive, 'attachments' => $request->user()->can('viewAttachments', $client),
                'forms' => $request->user()->can('manageForms', $client), 'privacy' => $request->user()->can('managePrivacy', $client),
                'merge' => $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web'),
            ],
        ]);
    }

    public function update(Request $request, Business $business, Client $client, ClientIdentityService $identity, ClientRecordService $records): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('update', $client);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'referral_source' => ['nullable', 'string', 'max:255'], 'preferences' => ['nullable', 'array'],
            'preferences.notes' => ['nullable', 'string', 'max:2000'],
            'communication_preferences' => ['nullable', 'array', 'max:3'],
            'communication_preferences.*' => ['string', 'distinct', 'in:email,sms,whatsapp'],
            'version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'],
            'preferred_staff' => ['nullable', 'string', 'max:26'],
            'preferred_services' => ['array', 'max:20'], 'preferred_services.*' => ['string', 'max:26'],
            'tags' => ['array', 'max:20'], 'tags.*' => ['string', 'max:80'],
        ]);
        $preferredStaffId = null;
        if (filled($data['preferred_staff'] ?? null)) {
            $preferredStaffId = StaffProfile::query()->where('business_id', $business->id)
                ->where('public_id', $data['preferred_staff'])->value('id');
            abort_unless($preferredStaffId, 422);
        }
        $preferredServiceIds = Service::query()->where('business_id', $business->id)
            ->whereIn('public_id', $data['preferred_services'] ?? [])->pluck('id')->all();
        if (count(array_unique($data['preferred_services'] ?? [])) !== count($preferredServiceIds)) {
            throw ValidationException::withMessages(['preferred_services' => 'Every preferred service must belong to this business.']);
        }
        DB::transaction(function () use ($client, $data, $preferredStaffId, $preferredServiceIds, $identity, $records): void {
            $updated = $identity->updateProfile($client, [...$data, 'preferred_staff_profile_id' => $preferredStaffId], $data['version'], $data['reason']);
            $records->syncPreferences($updated, $data['tags'] ?? [], $preferredServiceIds);
        });

        return back()->with('status', 'Client profile updated with history preserved.');
    }
}
