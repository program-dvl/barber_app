<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientAttachmentAccessLink;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Models\ClientFormTemplate;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Services\ClientAttachmentService;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\ClientRecords\Services\ClientMergeService;
use App\Domain\ClientRecords\Services\ClientPrivacyWorkflowService;
use App\Domain\ClientRecords\Services\ClientRecordService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PublicBooking\Models\PublicAppointmentLink;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('private'));
afterEach(fn () => app(TenantContext::class)->clear());

/** @return array{user:User,business:Business,membership:Membership,location:Location} */
function clientTenant(StarterRole $role = StarterRole::Owner): array
{
    [$user, $business, $membership] = createTenantMembership($role);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    return compact('user', 'business', 'membership', 'location');
}

function clientAppointment(array $tenant, string $name, string $mobile, ?string $email = null, ?Client $client = null): Appointment
{
    static $sequence = 0;
    $sequence++;

    return Appointment::query()->create([
        'business_id' => $tenant['business']->id, 'location_id' => $tenant['location']->id, 'client_id' => $client?->id,
        'idempotency_key' => 'crm-appointment-'.$sequence, 'request_hash' => hash('sha256', 'crm-'.$sequence),
        'status' => 'confirmed', 'source' => 'reception', 'client_name' => $name, 'client_mobile' => $mobile, 'client_email' => $email,
        'starts_at_utc' => now()->addDays($sequence), 'ends_at_utc' => now()->addDays($sequence)->addHour(),
        'time_zone' => 'Asia/Kolkata', 'local_starts_at' => '2026-08-20 10:00:00 +05:30', 'local_ends_at' => '2026-08-20 11:00:00 +05:30',
        'price_minor' => 3500, 'currency_code' => 'INR', 'confirmed_at' => now(),
    ]);
}

it('uses conservative normalized identity rules and creates review candidates for spelling variation', function () {
    $tenant = clientTenant();
    $first = clientAppointment($tenant, 'Jordan Lee', '+91 99999 99999', 'Jordan@Example.test');
    app(ClientIdentityService::class)->linkAppointment($first);
    $replay = $first->fresh();
    app(ClientIdentityService::class)->linkAppointment($replay);
    expect(Client::query()->where('business_id', $tenant['business']->id)->count())->toBe(1)
        ->and($replay->client_id)->not->toBeNull();

    $variation = clientAppointment($tenant, 'Jorden Lee', '+91-99999-99999', 'other@example.test');
    app(ClientIdentityService::class)->linkAppointment($variation);
    $candidate = ClientDuplicateCandidate::query()->where('business_id', $tenant['business']->id)->first();
    expect(Client::query()->where('business_id', $tenant['business']->id)->count())->toBe(2)
        ->and($variation->fresh()->client_id)->not->toBe($first->fresh()->client_id)
        ->and($candidate->status)->toBe('pending')
        ->and($candidate->reasons)->toContain('same_normalized_mobile')
        ->and($candidate->reasons)->toContain('similar_name');
});

it('rejects stale concurrent profile edits and revokes vulnerable appointment links after contact changes', function () {
    $tenant = clientTenant();
    $client = Client::factory()->create(['business_id' => $tenant['business']->id]);
    $appointment = clientAppointment($tenant, $client->name, $client->mobile, $client->email, $client);
    $issued = app(SecureAppointmentLinkService::class)->issue($appointment, 'contact');

    app(TenantContext::class)->run($tenant['business'], $tenant['membership'], function () use ($client): void {
        $first = app(ClientIdentityService::class)->updateProfile($client, ['mobile' => '+91 81111 11111'], 1, 'Client requested correction.');
        expect($first->version)->toBe(2);
        expect(fn () => app(ClientIdentityService::class)->updateProfile($client, ['email' => 'stale@example.test'], 1, 'Stale edit.'))
            ->toThrow(ValidationException::class, 'changed by someone else');
    });
    expect(PublicAppointmentLink::query()->where('token_hash', hash('sha256', $issued['token']))->first()->revoked_at)->not->toBeNull();
});

it('updates tenant-scoped tags and preferred employee and services from the client profile', function () {
    $tenant = clientTenant();
    $client = Client::factory()->create(['business_id' => $tenant['business']->id]);
    $staff = StaffProfile::factory()->create([
        'business_id' => $tenant['business']->id, 'membership_id' => $tenant['membership']->id,
        'user_id' => $tenant['user']->id, 'display_name' => 'Asha Stylist',
    ]);
    $service = Service::query()->create([
        'business_id' => $tenant['business']->id, 'name' => 'Signature cut',
        'price_minor' => 3500, 'currency_code' => 'INR', 'duration_minutes' => 45,
    ]);

    $this->actingAs($tenant['user'])->patch(route('business.clients.update', [$tenant['business'], $client]), [
        'name' => $client->name, 'email' => $client->email, 'mobile' => $client->mobile,
        'date_of_birth' => null, 'referral_source' => 'Neighbour',
        'preferred_staff' => $staff->public_id, 'preferred_services' => [$service->public_id],
        'tags' => ['VIP', 'Colour client'], 'preferences' => ['notes' => 'Quiet appointment'],
        'communication_preferences' => ['email'], 'version' => 1, 'reason' => 'Client confirmed preferences.',
    ])->assertRedirect();

    $client->refresh();
    expect($client->preferred_staff_profile_id)->toBe($staff->id)
        ->and($client->preferredServices()->pluck('services.id')->all())->toBe([$service->id])
        ->and($client->tags()->orderBy('name')->pluck('client_tags.name')->all())->toBe(['Colour client', 'VIP'])
        ->and($client->preferences['notes'])->toBe('Quiet appointment')
        ->and($client->communication_preferences)->toBe(['email'])
        ->and($client->version)->toBe(2);
});

it('previews and merges every current client relationship while preserving immutable snapshots', function () {
    $tenant = clientTenant();
    app(TenantContext::class)->activate($tenant['business'], $tenant['membership']);
    $survivor = Client::factory()->create(['business_id' => $tenant['business']->id, 'email' => null, 'normalized_email' => null]);
    $duplicate = Client::factory()->create(['business_id' => $tenant['business']->id, 'mobile' => $survivor->mobile, 'normalized_mobile' => $survivor->normalized_mobile]);
    $appointment = clientAppointment($tenant, $duplicate->name, $duplicate->mobile, $duplicate->email, $duplicate);
    ClientNote::query()->create(['business_id' => $tenant['business']->id, 'client_id' => $duplicate->id, 'kind' => 'allergy', 'visibility' => 'sensitive', 'content' => 'PPD sensitivity', 'is_important' => true]);
    ClientConsent::query()->create(['business_id' => $tenant['business']->id, 'client_id' => $duplicate->id, 'appointment_id' => $appointment->id, 'type' => 'treatment', 'status' => 'granted', 'source' => 'form', 'wording' => 'I consent to treatment.', 'occurred_at' => now()]);
    $template = ClientFormTemplate::query()->create(['business_id' => $tenant['business']->id, 'name' => 'Consent', 'purpose' => 'treatment']);
    $forms = app(ClientFormService::class);
    $v1 = $forms->publish($template, 'Treatment consent', 'Please review.', [['id' => 'accept', 'label' => 'I accept', 'type' => 'yes_no', 'required' => true], ['id' => 'sign', 'label' => 'Signature', 'type' => 'signature', 'required' => true]], []);
    $request = $forms->request($duplicate, $template, $appointment);
    $submission = $forms->submit($request, ['accept' => 'yes'], 'Jordan Lee', ['method' => 'staff_assisted']);
    $attachment = app(ClientAttachmentService::class)->store($duplicate, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 'before.png', 'before', 'sensitive');
    $privacy = app(ClientPrivacyWorkflowService::class)->submit($duplicate, 'export');
    $preferredService = Service::query()->create(['business_id' => $tenant['business']->id, 'name' => 'Colour refresh', 'price_minor' => 4500, 'currency_code' => 'INR', 'duration_minutes' => 60]);
    app(ClientRecordService::class)->syncPreferences($duplicate, ['Colour client'], [$preferredService->id]);
    $candidate = ClientDuplicateCandidate::query()->create(['business_id' => $tenant['business']->id, 'first_client_id' => min($survivor->id, $duplicate->id), 'second_client_id' => max($survivor->id, $duplicate->id), 'status' => 'pending', 'confidence' => 90, 'reasons' => ['same_normalized_mobile'], 'detected_at' => now()]);

    $merges = app(ClientMergeService::class);
    $preview = $merges->preview($candidate, $survivor, $tenant['membership']);
    expect($preview['relationship_counts'])->toMatchArray(['appointments' => 1, 'client_consents' => 1, 'client_notes' => 1, 'client_form_requests' => 1, 'client_form_submissions' => 1, 'client_attachments' => 1, 'client_privacy_requests' => 1, 'client_tag_assignments' => 1, 'client_preferred_services' => 1]);
    $merged = $merges->merge($candidate, $survivor, $tenant['membership'], $survivor->version, $duplicate->version, 'Same person confirmed by client.');
    expect($appointment->fresh()->client_id)->toBe($merged->id)
        ->and($submission->fresh()->client_id)->toBe($merged->id)
        ->and($attachment->fresh()->client_id)->toBe($merged->id)
        ->and($privacy->fresh()->client_id)->toBe($merged->id)
        ->and($merged->tags()->pluck('client_tags.name')->all())->toBe(['Colour client'])
        ->and($merged->preferredServices()->pluck('services.id')->all())->toBe([$preferredService->id])
        ->and($duplicate->fresh()->status)->toBe('merged')
        ->and($candidate->fresh()->preview_snapshot['relationship_counts']['appointments'])->toBe(1)
        ->and($submission->wording_snapshot['version'])->toBe($v1->version);
});

it('keeps submitted wording answers signatures identity and appointment immutable across later template versions', function () {
    $tenant = clientTenant();
    $client = Client::factory()->create(['business_id' => $tenant['business']->id]);
    $appointment = clientAppointment($tenant, $client->name, $client->mobile, $client->email, $client);
    $template = ClientFormTemplate::query()->create(['business_id' => $tenant['business']->id, 'name' => 'Patch test', 'purpose' => 'patch_test']);
    $forms = app(ClientFormService::class);
    $v1 = $forms->publish($template, 'Patch test consent', 'Original wording', [['id' => 'done', 'label' => 'Patch test completed?', 'type' => 'yes_no', 'required' => true], ['id' => 'signature', 'label' => 'Sign here', 'type' => 'signature', 'required' => true]], []);
    $request = $forms->request($client, $template, $appointment);
    $link = $forms->issueSecureLink($request);
    $resolved = $forms->resolveSecureLink($link['token']);
    $submission = $forms->submitSecure($resolved, ['done' => 'yes'], 'Jordan Lee');
    $calendar = app(CalendarQuery::class)->calendar(new CalendarFilter(
        $tenant['business']->id, $tenant['location']->id, 'day',
        $appointment->starts_at_utc->setTimezone($tenant['location']->time_zone),
    ));
    $calendarEvent = collect($calendar['events'])->firstWhere('id', $appointment->public_id);
    $service = Service::query()->create(['business_id' => $tenant['business']->id, 'name' => 'Colour service', 'price_minor' => 5000, 'currency_code' => 'INR', 'duration_minutes' => 60]);
    $this->actingAs($tenant['user'])->post(route('business.clients.forms.publish', $tenant['business']), [
        'template' => $template->public_id, 'name' => $template->name, 'purpose' => $template->purpose,
        'title' => 'Patch test consent', 'introduction' => 'Changed future wording',
        'fields' => [['id' => 'done', 'label' => 'Was the patch test clear?', 'type' => 'yes_no', 'required' => true, 'options' => []]],
        'services' => [$service->public_id],
    ])->assertRedirect();

    expect($submission->wording_snapshot['version'])->toBe($v1->version)
        ->and($submission->wording_snapshot['introduction'])->toBe('Original wording')
        ->and($submission->answers['done'])->toBe('yes')
        ->and($submission->signature_hash)->toBe(hash('sha256', 'Jordan Lee'))
        ->and($submission->submitted_identity_snapshot['appointment_public_id'])->toBe($appointment->public_id)
        ->and($calendarEvent['forms'])->toBe(['requested' => 1, 'completed' => 1, 'pending' => 0, 'status' => 'completed'])
        ->and($template->fresh()->current_version)->toBe(2)
        ->and($template->services()->pluck('services.id')->all())->toBe([$service->id]);
    expect(fn () => $submission->update(['signature_hash' => 'changed']))->toThrow(LogicException::class, 'immutable');
    expect(fn () => $forms->resolveSecureLink($link['token']))->toThrow(HttpException::class);
});

it('enforces sensitive-note permissions and tenant-scoped client routes', function () {
    $ownerTenant = clientTenant();
    $client = Client::factory()->create(['business_id' => $ownerTenant['business']->id]);
    ClientNote::query()->create(['business_id' => $ownerTenant['business']->id, 'client_id' => $client->id, 'kind' => 'warning', 'visibility' => 'sensitive', 'content' => 'Private warning', 'is_important' => true]);
    $receptionist = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $ownerTenant['business']->id, 'user_id' => $receptionist->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Receptionist, $ownerTenant['user'], 'Reception access.');
    $membership->locations()->syncWithPivotValues([$ownerTenant['location']->id], ['business_id' => $ownerTenant['business']->id]);

    $this->actingAs($receptionist)->get(route('business.clients.show', [$ownerTenant['business'], $client]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Clients/Show')->has('notes', 0)->where('permissions.sensitive', false));
    $this->actingAs($ownerTenant['user'])->get(route('business.clients.show', [$ownerTenant['business'], $client]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('notes', 1)->where('notes.0.content', 'Private warning'));
    expect(AuditEvent::query()->where('business_id', $ownerTenant['business']->id)
        ->where('action', 'client.sensitive_context_viewed')->exists())->toBeTrue();

    $other = clientTenant();
    $this->actingAs($ownerTenant['user'])->get(route('business.clients.show', [$ownerTenant['business'], Client::factory()->create(['business_id' => $other['business']->id])]))->assertNotFound();
});

it('keeps files tenant-private and rejects expired bearer links', function () {
    $first = clientTenant();
    $second = clientTenant();
    $client = Client::factory()->create(['business_id' => $first['business']->id]);
    $otherClient = Client::factory()->create(['business_id' => $second['business']->id]);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    $service = app(ClientAttachmentService::class);
    app(TenantContext::class)->activate($first['business'], $first['membership']);
    $attachment = $service->store($client, $png, 'before.png', 'before');
    expect(fn () => $service->store($otherClient, $png, 'other.png', 'after'))->toThrow(AccessDeniedHttpException::class);
    $issued = $service->issueDownload($attachment, 5);
    expect($service->contents($service->resolve($issued['token'])))->toBe($png);
    ClientAttachmentAccessLink::query()->where('token_hash', hash('sha256', $issued['token']))->update(['expires_at' => now()->subMinute()]);
    expect(fn () => $service->resolve($issued['token']))->toThrow(HttpException::class);
});

it('completes minimized export correction and withdrawal while policy-blocking destructive anonymization', function () {
    $tenant = clientTenant();
    app(TenantContext::class)->activate($tenant['business'], $tenant['membership']);
    $client = Client::factory()->create(['business_id' => $tenant['business']->id, 'marketing_status' => 'subscribed']);
    clientAppointment($tenant, $client->name, $client->mobile, $client->email, $client);
    ClientNote::query()->create(['business_id' => $tenant['business']->id, 'client_id' => $client->id, 'kind' => 'warning', 'visibility' => 'sensitive', 'content' => 'Not exported internal note', 'is_important' => true]);
    $workflow = app(ClientPrivacyWorkflowService::class);

    $export = $workflow->completeExport($workflow->submit($client, 'export'), $tenant['membership']);
    $dataset = json_decode(app(ClientAttachmentService::class)->contents($export->exportAttachment), true, flags: JSON_THROW_ON_ERROR);
    expect($export->status)->toBe('completed')
        ->and($dataset['data'])->toHaveKeys(['profile', 'appointments', 'consents', 'submitted_forms', 'attachment_metadata', 'financial_history'])
        ->and($dataset['manifest']['intentionally_omitted'])->toContain('internal_staff_notes_and_warnings')
        ->and(json_encode($dataset))->not->toContain('Not exported internal note')
        ->and(json_encode($dataset))->not->toContain('token_hash');

    $correction = $workflow->submit($client->fresh(), 'correction', ['changes' => ['email' => 'corrected@example.test']]);
    expect($workflow->completeCorrection($correction, $tenant['membership'])->status)->toBe('completed')
        ->and($client->fresh()->email)->toBe('corrected@example.test');
    $withdrawal = $workflow->submit($client->fresh(), 'consent_withdrawal', ['consent_type' => 'marketing']);
    $workflow->completeConsentWithdrawal($withdrawal, $tenant['membership']);
    expect($client->fresh()->marketing_status)->toBe('withdrawn')
        ->and(ClientConsent::query()->where('client_id', $client->id)->where('status', 'withdrawn')->exists())->toBeTrue();
    $deletion = $workflow->reviewDeletionAnonymization($workflow->submit($client->fresh(), 'deletion_anonymization'), $tenant['membership']);
    expect($deletion->status)->toBe('blocked_policy')
        ->and($deletion->result_summary['destructive_changes_applied'])->toBeFalse()
        ->and($client->fresh()->status)->toBe('active');
});
