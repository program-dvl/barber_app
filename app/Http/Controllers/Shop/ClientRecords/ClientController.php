<?php

namespace App\Http\Controllers\Shop\ClientRecords;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\ClientRecords\Services\ClientRecordService;
use App\Domain\ClientRecords\Services\ClientWorkspaceQuery;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\Communications\Services\NotificationWorkspaceCatalog;
use App\Domain\Communications\Services\NotificationWorkspaceQuery;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Http\Controllers\Controller;
use App\Rules\E164Phone;
use App\Support\Audit\AuditWriter;
use App\Support\Regional\CountryCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, Business $business, TenantContext $context, CountryCatalog $countries, ClientWorkspaceQuery $workspace): Response
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ClientView->value, 'web'), 403);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'relationship' => ['nullable', 'in:upcoming,unbooked,returning,new,no_shows,lapsed,duplicates'],
            'staff' => ['nullable', 'string', 'max:26'],
            'sort' => ['nullable', 'in:name,recent,last_visit,next_appointment,visits'],
        ]);
        abort_if(($data['relationship'] ?? '') === 'duplicates' && ! $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web'), 403);
        $filters = ['search' => trim($data['search'] ?? ''), 'relationship' => $data['relationship'] ?? '', 'staff' => $data['staff'] ?? '', 'sort' => $data['sort'] ?? 'name'];

        return Inertia::render('Clients/Index', [
            'businessLabel' => $business->name,
            'clients' => $workspace->directory($membership, $filters), 'filters' => $filters,
            'duplicateCount' => $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web') ? ClientDuplicateCandidate::query()->where('business_id', $business->id)->where('status', 'pending')->count() : 0,
            'canContact' => $workspace->contact($membership), 'canMerge' => $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web'),
            'canCreate' => $membership->hasPermissionTo(PermissionName::ClientManage->value, 'web'),
            'staffOptions' => StaffProfile::query()->where('business_id', $business->id)->orderBy('display_name')->get()->map->only(['public_id', 'display_name']),
            'countries' => $countries->countries(),
        ]);
    }

    public function matches(Request $request, Business $business, TenantContext $context, ClientWorkspaceQuery $workspace)
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ClientManage->value, 'web') && $workspace->contact($membership), 403);
        $data = $request->validate(['mobile' => ['nullable', 'string', 'max:32'], 'email' => ['nullable', 'string', 'max:255']]);

        return response()->json(['clients' => $workspace->matches($membership, $data['mobile'] ?? null, $data['email'] ?? null)
            ->map->only(['public_id', 'name', 'mobile', 'email'])]);
    }

    public function store(Request $request, Business $business, ClientIdentityService $identity, TenantContext $context, ClientWorkspaceQuery $workspace): RedirectResponse
    {
        $membership = $context->membership();
        abort_unless($membership?->hasPermissionTo(PermissionName::ClientManage->value, 'web'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'required_without:email', 'string', 'max:32', new E164Phone],
            'email' => ['nullable', 'required_without:mobile', 'email', 'max:255'],
            'referral_source' => ['nullable', 'string', 'max:255'],
            'duplicate_confirmed' => ['nullable', 'boolean'],
        ]);
        $matches = $workspace->matches($membership, $data['mobile'] ?? null, $data['email'] ?? null);
        $exact = $matches->contains(fn ($c) => $c->normalized_name === ClientIdentityNormalizer::name($data['name']));
        if (! $exact && $matches->isNotEmpty() && ! ($data['duplicate_confirmed'] ?? false)) {
            throw ValidationException::withMessages(['duplicate_confirmed' => 'This contact already belongs to a client. Review the existing profile or confirm this is a different person.']);
        }
        $result = $identity->createManual($business, $data);

        return redirect()->route('business.clients.show', [$business, $result['client']])
            ->with('status', $result['created']
                ? 'Client added. You can now book, add preferences, or record service context.'
                : 'An existing client with the same name and contact details was opened instead of creating a duplicate.');
    }

    public function show(Request $request, Business $business, Client $client, ClientRecordService $records, TenantContext $context, AuditWriter $audit, ClientWorkspaceQuery $workspace): Response
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('view', $client);
        $membership = $context->membership();
        $canContact = $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web');
        $canSensitive = $request->user()->can('viewSensitive', $client);
        $notes = $client->notes()->with('author')->when(! $canSensitive, fn ($query) => $query->where('visibility', 'standard'))->get();
        $unlinkedAuthorIds = $notes->filter(fn ($note) => ! $note->author)->pluck('id');
        $auditedAuthors = $unlinkedAuthorIds->isEmpty() ? collect() : AuditEvent::query()
            ->where('business_id', $business->id)->where('action', 'client.note_added')
            ->where('auditable_type', (new ClientNote)->getMorphClass())->whereIn('auditable_id', $unlinkedAuthorIds)
            ->with('actor:id,name')->get(['auditable_id', 'actor_user_id'])->keyBy('auditable_id');
        if ($canSensitive && $notes->contains('visibility', 'sensitive')) {
            $audit->write('client.sensitive_context_viewed', $business, $request->user(), $client, null, [], [], [
                'sensitive_note_count' => $notes->where('visibility', 'sensitive')->count(),
            ], 'client_records');
        }
        $projection = $workspace->profile($client, $membership);

        return Inertia::render('Clients/Show', [
            'businessLabel' => $business->name,
            ...$projection,
            'section' => in_array($request->input('section'), $workspace->finance($membership) ? ['overview', 'visits', 'payments', 'notes', 'records'] : ['overview', 'visits', 'notes', 'records'], true) ? $request->input('section') : 'overview',
            'client' => [
                'public_id' => $client->public_id, 'name' => $client->name, 'status' => $client->status, 'created_at' => $client->created_at->toIso8601String(),
                'mobile' => $canContact ? $client->mobile : null, 'email' => $canContact ? $client->email : null,
                'date_of_birth' => $client->date_of_birth?->toDateString(), 'preferences' => $client->preferences ?? [],
                'communication_preferences' => $client->communication_preferences ?? [], 'referral_source' => $client->referral_source,
                'marketing_status' => $client->marketing_status, 'version' => $client->version,
                'preferred_staff' => $client->preferredStaff?->public_id, 'preferred_staff_name' => $client->preferredStaff?->display_name,
                'preferred_service_names' => $client->preferredServices()->pluck('services.name'),
                'preferred_services' => $client->preferredServices()->pluck('services.public_id'),
                'tags' => $client->tags()->pluck('client_tags.name'),
            ],
            'notes' => $notes->map(fn ($note) => [
                'id' => $note->id, 'kind' => $note->kind, 'visibility' => $note->visibility,
                'content' => $note->content, 'important' => $note->is_important,
                'author' => $note->author?->display_name ?? $auditedAuthors->get($note->id)?->actor?->name ?? 'Author not recorded', 'created_at' => $note->created_at->toIso8601String(),
            ]),
            'consents' => $client->consents()->get()->map->only(['type', 'status', 'source', 'policy_version', 'wording', 'occurred_at']),
            'communicationHistoryUrl' => app(WorkspaceAccessService::class)->decide($business, $membership, 'communications')['allowed'] ? route('business.communications.page', ['business' => $business->public_id, 'client' => $client->public_id]) : null,
            'communications' => $canContact ? app(NotificationWorkspaceQuery::class)->base($business, $membership)->where('client_id', $client->id)->with('intent')->latest('id')->limit(6)->get()->map(fn ($message) => [
                'intent' => $message->intent->intent_type, 'channel' => $message->channel, 'category' => $message->category,
                'status' => $message->status, 'legal_basis' => $message->legal_basis,
                'simulated' => $message->provider === 'fake',
                'issue' => NotificationWorkspaceCatalog::issue($message->last_error_code ?: $message->suppression_reason),
                'queued_at' => $message->queued_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
            ]) : [],
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
            'attachments' => $request->user()->can('viewAttachments', $client) ? $client->attachments()->when(! $canSensitive, fn ($q) => $q->where('visibility', 'standard'))->get()->map->only(['public_id', 'kind', 'original_name', 'mime_type', 'size_bytes', 'visibility', 'created_at']) : [],
            'privacyRequests' => $request->user()->can('managePrivacy', $client) ? $client->privacyRequests()->get()->map->only(['public_id', 'type', 'status', 'requested_at', 'due_at', 'result_summary']) : [],
            'duplicates' => $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web') ? ClientDuplicateCandidate::query()->with(['firstClient', 'secondClient'])->where('business_id', $business->id)->where('status', 'pending')->where(fn ($query) => $query->where('first_client_id', $client->id)->orWhere('second_client_id', $client->id))->get()->map(fn ($candidate) => ['id' => $candidate->id, 'other' => $candidate->first_client_id === $client->id ? $candidate->secondClient->only(['public_id', 'name', 'version']) : $candidate->firstClient->only(['public_id', 'name', 'version']), 'confidence' => $candidate->confidence, 'reasons' => $candidate->reasons]) : [],
            'permissions' => [
                'contact' => $canContact, 'finance' => $workspace->finance($membership),
                'book' => $client->status === 'active' && ($membership->hasPermissionTo(PermissionName::AppointmentsManageAll->value, 'web') || $membership->hasPermissionTo(PermissionName::AppointmentsManageOwn->value, 'web')),
                'walkIn' => $client->status === 'active' && $membership->hasPermissionTo(PermissionName::WalkInsManage->value, 'web'),
                'update' => $client->status === 'active' && $request->user()->can('update', $client), 'addNote' => $request->user()->can('addNote', $client),
                'sensitive' => $canSensitive, 'attachments' => $request->user()->can('viewAttachments', $client),
                'forms' => $request->user()->can('manageForms', $client), 'privacy' => $request->user()->can('managePrivacy', $client),
                'merge' => $membership->hasPermissionTo(PermissionName::ClientMerge->value, 'web'),
            ],
        ]);
    }

    public function update(Request $request, Business $business, Client $client, ClientIdentityService $identity, ClientRecordService $records, TenantContext $context): RedirectResponse
    {
        abort_unless($client->business_id === $business->id, 404);
        $this->authorize('update', $client);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32', new E164Phone], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'referral_source' => ['nullable', 'string', 'max:255'], 'preferences' => ['nullable', 'array'],
            'preferences.notes' => ['nullable', 'string', 'max:2000'],
            'communication_preferences' => ['nullable', 'array', 'max:3'],
            'communication_preferences.*' => ['string', 'distinct', 'in:email,sms'],
            'version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'],
            'preferred_staff' => ['nullable', 'string', 'max:26'],
            'preferred_services' => ['array', 'max:20'], 'preferred_services.*' => ['string', 'max:26'],
            'tags' => ['array', 'max:20'], 'tags.*' => ['string', 'max:80'],
        ]);
        if (! $context->membership()->hasPermissionTo(PermissionName::ClientContactView->value, 'web')) {
            unset($data['email'], $data['mobile']);
        }
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
            if (array_key_exists('communication_preferences', $data)) {
                $existing = $client->communication_preferences ?? [];
                $saved = array_is_list($existing) ? array_fill_keys($existing, true) : $existing;
                $data['communication_preferences'] = array_replace($saved, [
                    'email' => in_array('email', $data['communication_preferences'] ?? [], true),
                    'sms' => in_array('sms', $data['communication_preferences'] ?? [], true),
                ]);
            }
            $updated = $identity->updateProfile($client, [...$data, 'preferred_staff_profile_id' => $preferredStaffId], $data['version'], $data['reason']);
            $records->syncPreferences($updated, $data['tags'] ?? [], $preferredServiceIds);
        });

        return back()->with('status', 'Client details saved.');
    }
}
