<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Services\ClientMergeService;
use App\Domain\ClientRecords\Services\ClientPrivacyWorkflowService;
use App\Domain\ClientReviews\Models\ClientReview;
use App\Domain\ClientReviews\Services\ClientReviewService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\PublicBooking\Models\PublicAppointmentLink;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function reviewVisit(string $status = 'completed'): array
{
    $business = Business::factory()->create(['name' => 'Review Studio', 'booking_slug' => 'review-'.strtolower((string) Str::ulid()), 'status' => 'active', 'configuration_published_at' => now(), 'time_zone' => 'Asia/Kolkata']);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $user, 'Review test.');
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    $appointment = Appointment::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'status' => $status, 'source' => 'phone',
        'client_name' => 'Jordan Secretname', 'client_mobile' => '+919876543210', 'client_email' => 'private@example.test',
        'starts_at_utc' => now()->subHours(2), 'ends_at_utc' => now()->subHour(), 'completed_at' => $status === 'completed' ? now()->subMinutes(55) : null,
        'local_starts_at' => '2026-10-29 10:00:00', 'local_ends_at' => '2026-10-29 11:00:00', 'time_zone' => 'Asia/Kolkata', 'price_minor' => 2500, 'currency_code' => 'INR',
        'idempotency_key' => 'review-'.Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid()),
    ]);
    $issued = app(SecureAppointmentLinkService::class)->issue($appointment, 'review');
    $link = PublicAppointmentLink::query()->where('token_hash', hash('sha256', $issued['token']))->firstOrFail();

    return compact('business', 'location', 'user', 'membership', 'appointment', 'issued', 'link');
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29T12:43:21.784321+05:30'));
});
afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('saves private feedback by default and safely replays the same submission', function () {
    $visit = reviewVisit();
    $data = ['rating' => 2, 'body' => 'The visit felt rushed.', 'public_consent' => false];
    $service = app(ClientReviewService::class);
    $review = $service->submit($visit['link'], $data);
    expect($service->submit($visit['link']->fresh(), $data)->id)->toBe($review->id)
        ->and(ClientReview::query()->count())->toBe(1)
        ->and($visit['link']->fresh()->used_at)->not->toBeNull()
        ->and($service->publicSummary($visit['business']))->toMatchArray(['count' => 0, 'average' => null, 'items' => []]);
    expect(fn () => $service->submit($visit['link']->fresh(), [...$data, 'rating' => 5]))->toThrow(ValidationException::class, 'already saved');
});

it('publishes consented reviews of every rating with initials and no appointment or client identifiers', function () {
    $visit = reviewVisit();
    $service = app(ClientReviewService::class);
    $service->submit($visit['link'], ['rating' => 1, 'body' => '<script>alert("unsafe")</script> Honest feedback.', 'public_consent' => true]);
    $other = reviewVisit();
    $service->submit($other['link'], ['rating' => 5, 'body' => 'Other business feedback.', 'public_consent' => true]);
    $summary = $service->publicSummary($visit['business']);
    expect($summary['count'])->toBe(1)->and($summary['average'])->toBe(1.0)
        ->and($summary['items'][0])->toMatchArray(['rating' => 1, 'author_initials' => 'JS', 'verified_visit' => true])
        ->and($summary['items'][0])->not->toHaveKeys(['appointment_id', 'client_id', 'email', 'mobile', 'services', 'client_name'])
        ->and(json_encode($summary))->not->toContain('Secretname', 'private@example.test', 'Other business');
});

it('lets the client withdraw public display consent without deleting or changing feedback', function () {
    $visit = reviewVisit();
    $service = app(ClientReviewService::class);
    $review = $service->submit($visit['link'], ['rating' => 4, 'body' => 'A kind team.', 'public_consent' => true]);
    $service->withdrawPublicConsent($visit['link']->fresh());
    $service->withdrawPublicConsent($visit['link']->fresh());
    expect($review->fresh()->public_consent)->toBeFalse()->and($review->fresh()->body)->toBe('A kind team.')
        ->and($service->publicSummary($visit['business'])['count'])->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'client_review.public_consent_withdrawn')->count())->toBe(1);
});

it('does not permit feedback until service is completed', function (string $status) {
    $visit = reviewVisit($status);
    expect(fn () => app(ClientReviewService::class)->submit($visit['link'], ['rating' => 5, 'public_consent' => true]))->toThrow(ValidationException::class, 'after the business');
    expect(ClientReview::query()->count())->toBe(0)->and($visit['link']->fresh()->used_at)->toBeNull();
})->with(['confirmed', 'in_service', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled']);

it('rejects expired revoked and wrong purpose review links', function (string $condition) {
    $visit = reviewVisit();
    $visit['link']->update(match ($condition) {
        'expired' => ['expires_at' => now()->subSecond()], 'revoked' => ['revoked_at' => now()], default => ['purpose' => 'contact']
    });
    try {
        app(ClientReviewService::class)->submit($visit['link']->fresh(), ['rating' => 5, 'public_consent' => true]);
        $this->fail('Invalid link accepted.');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe($condition === 'purpose' ? 404 : 410);
    }
    expect(ClientReview::query()->count())->toBe(0);
})->with(['expired', 'revoked', 'purpose']);

it('enforces one review per tenant appointment at the database boundary', function () {
    $visit = reviewVisit();
    $review = app(ClientReviewService::class)->submit($visit['link'], ['rating' => 3, 'public_consent' => true]);
    expect(fn () => ClientReview::query()->create(Arr::except($review->getAttributes(), ['id', 'public_id', 'created_at', 'updated_at'])))->toThrow(QueryException::class);
});

it('rejects a cross-business appointment review relationship at the database boundary', function () {
    $visit = reviewVisit();
    $other = reviewVisit();
    $review = app(ClientReviewService::class)->submit($visit['link'], ['rating' => 3, 'public_consent' => false]);
    $attributes = Arr::except($review->getAttributes(), ['id', 'public_id', 'created_at', 'updated_at']);
    $attributes['business_id'] = $other['business']->id;
    $attributes['public_id'] = (string) Str::ulid();
    expect(fn () => DB::table('client_reviews')->insert($attributes))->toThrow(QueryException::class);
});

it('renders feedback in the same secure appointment journey and accepts a purpose bound post', function () {
    $visit = reviewVisit();
    $view = app(SecureAppointmentLinkService::class)->issue($visit['appointment'], 'view');
    $this->get(route('public.appointment.view', $view['token']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('review.eligible', true)->has('actions.review')->missing('actions.reschedule')->missing('actions.cancel'));
    $url = route('public.appointment.mutate', [$visit['issued']['token'], 'review']);
    $this->from(route('public.appointment.action', [$visit['issued']['token'], 'review']))->post($url, ['rating' => 5, 'body' => 'Professional and welcoming.', 'public_consent' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect(ClientReview::query()->count())->toBe(1);
    $this->get(route('public.appointment.action', [$visit['issued']['token'], 'review']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('review.saved.rating', 5)->where('review.saved.public_consent', false));
});

it('issues a review link only to an authorized operator for a completed same tenant appointment', function () {
    $visit = reviewVisit();
    $this->actingAs($visit['user'])->postJson(route('business.appointments.review-link', [$visit['business'], $visit['appointment']->public_id]))->assertOk()->assertJsonStructure(['url', 'expires_at'])->assertHeader('Cache-Control', 'no-store, private');
    $other = reviewVisit();
    $this->actingAs($visit['user'])->postJson(route('business.appointments.review-link', [$visit['business'], $other['appointment']->public_id]))->assertNotFound();
    $visit['appointment']->update(['status' => 'cancelled_by_shop']);
    $this->postJson(route('business.appointments.review-link', [$visit['business'], $visit['appointment']->public_id]))->assertUnprocessable();
});

it('denies operators who have no appointment management permission', function () {
    $visit = reviewVisit();
    app(MembershipAccessManager::class)->assignStarterRole($visit['membership'], StarterRole::Accountant, $visit['user'], 'Read-only review test.');
    $this->actingAs($visit['user'])->postJson(route('business.appointments.review-link', [$visit['business'], $visit['appointment']->public_id]))->assertForbidden();
});

it('denies review link access to an operator assigned only to another branch', function () {
    $visit = reviewVisit();
    $otherLocation = Location::factory()->create(['business_id' => $visit['business']->id]);
    app(MembershipAccessManager::class)->assignStarterRole($visit['membership'], StarterRole::Manager, $visit['user'], 'Branch-only test.');
    $visit['membership']->locations()->syncWithPivotValues([$otherLocation->id], ['business_id' => $visit['business']->id]);
    $this->actingAs($visit['user'])->postJson(route('business.appointments.review-link', [$visit['business'], $visit['appointment']->public_id]))->assertForbidden();
});

it('shows private feedback to the authorized appointment manager in Calendar', function () {
    $visit = reviewVisit();
    app(ClientReviewService::class)->submit($visit['link'], ['rating' => 2, 'body' => 'Private suggestion for the team.', 'public_consent' => false]);
    $this->actingAs($visit['user'])->get(route('business.calendar', ['business' => $visit['business'], 'location' => $visit['location']->public_id, 'date' => '2026-10-29']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('calendar.events.0.review.rating', 2)->where('calendar.events.0.review.body', 'Private suggestion for the team.')->where('calendar.events.0.review.public_consent', false));
});

it('pages every public rating without exposing private feedback and hides unpublished businesses', function () {
    $visit = reviewVisit();
    app(ClientReviewService::class)->submit($visit['link'], ['rating' => 2, 'body' => 'A public review.', 'public_consent' => true]);
    for ($number = 0; $number < 13; $number++) {
        $appointment = $visit['appointment']->replicate(['public_id', 'booking_reference']);
        $appointment->idempotency_key = 'paged-review-'.$number;
        $appointment->save();
        $issued = app(SecureAppointmentLinkService::class)->issue($appointment, 'review');
        $link = PublicAppointmentLink::query()->where('token_hash', hash('sha256', $issued['token']))->firstOrFail();
        app(ClientReviewService::class)->submit($link, ['rating' => $number % 5 + 1, 'body' => $number === 0 ? 'Never public feedback.' : 'Public feedback '.$number, 'public_consent' => $number !== 0]);
    }
    $summary = app(ClientReviewService::class)->publicSummary($visit['business']);
    expect($summary['count'])->toBe(13)->and($summary['items'])->toHaveCount(12)->and($summary['next_url'])->not->toBeNull();
    $page = $this->getJson($summary['next_url'])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('next_url', null)->json();
    expect(array_intersect(array_column($summary['items'], 'id'), array_column($page['items'], 'id')))->toBe([])
        ->and(json_encode([$summary, $page]))->not->toContain('Never public feedback.', 'private@example.test');
    $visit['business']->update(['configuration_published_at' => null]);
    $this->getJson($summary['next_url'])->assertNotFound();
});

it('returns saved review state for JSON submission and consent withdrawal', function () {
    $visit = reviewVisit();
    $url = route('public.appointment.mutate', [$visit['issued']['token'], 'review']);
    $this->postJson($url, ['rating' => 4, 'body' => 'Clear advice.', 'public_consent' => true])->assertOk()->assertJsonPath('review.saved.public_consent', true);
    $this->postJson($url, ['operation' => 'withdraw_public_consent'])->assertOk()->assertJsonPath('review.saved.public_consent', false);
});

it('preserves submitted feedback and consent evidence through a reviewed client merge and privacy export', function () {
    $visit = reviewVisit();
    $survivor = Client::factory()->create(['business_id' => $visit['business']->id]);
    $duplicate = Client::factory()->create(['business_id' => $visit['business']->id]);
    $visit['appointment']->update(['client_id' => $duplicate->id]);
    $review = app(ClientReviewService::class)->submit($visit['link'], ['rating' => 3, 'body' => 'My personal experience.', 'public_consent' => true]);
    app(ClientReviewService::class)->withdrawPublicConsent($visit['link']->fresh());
    $originalInitials = $review->author_initials;
    app(TenantContext::class)->activate($visit['business'], $visit['membership']);
    $candidate = ClientDuplicateCandidate::query()->create(['business_id' => $visit['business']->id, 'first_client_id' => $survivor->id, 'second_client_id' => $duplicate->id, 'status' => 'pending', 'confidence' => 90, 'reasons' => ['client_confirmed_duplicate'], 'detected_at' => now()]);
    $merges = app(ClientMergeService::class);
    expect($merges->preview($candidate, $survivor, $visit['membership'])['relationship_counts']['client_reviews'])->toBe(1);
    $merges->merge($candidate, $survivor, $visit['membership'], $survivor->version, $duplicate->version, 'Client confirmed the duplicate.');
    expect($review->fresh()->client_id)->toBe($survivor->id)->and($review->fresh()->body)->toBe('My personal experience.')->and($review->fresh()->author_initials)->toBe($originalInitials);
    $dataset = app(ClientPrivacyWorkflowService::class)->exportDataset($survivor->fresh());
    expect($dataset['data']['visit_reviews'])->toHaveCount(1)
        ->and($dataset['data']['visit_reviews'][0])->toMatchArray(['rating' => 3, 'body' => 'My personal experience.', 'public_consent' => false])
        ->and($dataset['data']['visit_reviews'][0]['public_consent_evidence'])->toMatchArray(['initial_choice' => true, 'wording' => ClientReviewService::PUBLIC_CONSENT_WORDING])
        ->and($dataset['data']['visit_reviews'][0]['public_consent_withdrawn_at'])->not->toBeNull()
        ->and($dataset['data']['visit_reviews'][0])->not->toHaveKeys(['request_hash', 'token_hash']);
    expect(app(ClientPrivacyWorkflowService::class)->exportDataset($duplicate->fresh())['data']['visit_reviews'])->toBe([]);
});
