<?php

namespace App\Domain\ClientReviews\Services;

use App\Domain\ClientReviews\Models\ClientReview;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Models\PublicAppointmentLink;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientReviewService
{
    public const PUBLIC_CONSENT_WORDING = 'Show my rating and review on this business’s booking page with my initials.';

    public function __construct(private readonly AuditWriter $audit) {}

    public function eligible(Appointment $appointment): bool
    {
        return $appointment->status === 'completed' && $appointment->completed_at && $appointment->completed_at->lessThanOrEqualTo(now());
    }

    public function existing(Appointment $appointment): ?ClientReview
    {
        return ClientReview::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)->first();
    }

    public function submit(PublicAppointmentLink $link, array $data): ClientReview
    {
        $data = Validator::make($data, ['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:5000'], 'public_consent' => ['required', 'boolean']])->validate();

        return DB::transaction(function () use ($link, $data): ClientReview {
            // The appointment lock serializes every purpose link for the same visit.
            $appointment = Appointment::query()->where('business_id', $link->business_id)->lockForUpdate()->findOrFail($link->appointment_id);
            $currentLink = PublicAppointmentLink::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($link->id);
            $this->assertLink($currentLink, $appointment);
            $this->assertEligible($appointment);
            $body = trim((string) ($data['body'] ?? ''));
            $hash = hash_hmac('sha256', json_encode([(int) $data['rating'], $body, (bool) ($data['public_consent'] ?? false)], JSON_THROW_ON_ERROR), (string) config('app.key'));
            $existing = $this->existing($appointment);
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['review' => 'Your feedback is already saved. Reopen this appointment page to see it or manage public display.']);
                }

                return $existing;
            }
            abort_if($currentLink->used_at, 410, 'This link has already been used. Ask the business for a new review link.');
            $initials = collect(preg_split('/\s+/u', trim($appointment->client_name ?: 'Guest')))->filter()->take(2)
                ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
            $review = ClientReview::query()->create([
                'business_id' => $appointment->business_id, 'appointment_id' => $appointment->id, 'client_id' => $appointment->client_id,
                'rating' => $data['rating'], 'body' => $body ?: null, 'author_initials' => $initials ?: 'G',
                'public_consent' => $data['public_consent'] ?? false, 'request_hash' => $hash, 'submitted_at' => now(),
                'public_consent_evidence' => ['version' => '2026-10-29', 'wording' => self::PUBLIC_CONSENT_WORDING, 'initial_choice' => (bool) $data['public_consent'], 'source' => 'secure_appointment_link', 'occurred_at' => now()->utc()->toIso8601String()],
            ]);
            $currentLink->update(['used_at' => now()]);
            $this->audit->write('client_review.submitted', $appointment->business, null, $review, null, [], ['rating' => $review->rating, 'public_consent' => $review->public_consent], [], 'public_booking');

            return $review;
        }, 3);
    }

    public function withdrawPublicConsent(PublicAppointmentLink $link): ClientReview
    {
        return DB::transaction(function () use ($link): ClientReview {
            $appointment = Appointment::query()->where('business_id', $link->business_id)->lockForUpdate()->findOrFail($link->appointment_id);
            $currentLink = PublicAppointmentLink::query()->where('business_id', $appointment->business_id)->lockForUpdate()->findOrFail($link->id);
            $this->assertLink($currentLink, $appointment);
            $review = $this->existing($appointment);
            abort_unless($review, 404);
            if ($review->public_consent) {
                $review->update(['public_consent' => false, 'public_consent_withdrawn_at' => now()]);
                $this->audit->write('client_review.public_consent_withdrawn', $appointment->business, null, $review, null, ['public_consent' => true], ['public_consent' => false], [], 'public_booking');
            }

            return $review;
        }, 3);
    }

    public function privatePayload(Appointment $appointment): array
    {
        $review = $this->existing($appointment);

        return ['eligible' => $this->eligible($appointment), 'saved' => $review ? [
            'rating' => $review->rating, 'body' => $review->body, 'public_consent' => $review->public_consent,
            'author_initials' => $review->author_initials,
        ] : null];
    }

    /** Public projection deliberately excludes appointment, service and client identities. */
    public function publicSummary(Business $business, int $limit = 12): array
    {
        $query = ClientReview::query()->where('business_id', $business->id)->where('public_consent', true);

        return ['count' => (clone $query)->count(), 'average' => ($average = (clone $query)->avg('rating')) !== null ? round((float) $average, 1) : null,
            ...$this->publicPage($business, null, $limit)];
    }

    /** Keyset pagination keeps reads bounded and never traverses private feedback. */
    public function publicPage(Business $business, ?string $before = null, int $limit = 12): array
    {
        $limit = max(1, min($limit, 12));
        $records = ClientReview::query()->where('business_id', $business->id)->where('public_consent', true)
            ->when($before, fn ($query) => $query->where('public_id', '<', $before))
            ->orderByDesc('public_id')->limit($limit + 1)->get();
        $items = $records->take($limit);

        return ['items' => $items
            ->map(fn (ClientReview $review) => ['id' => $review->public_id, 'rating' => $review->rating, 'body' => $review->body,
                'author_initials' => $review->author_initials, 'date' => $review->submitted_at->setTimezone($business->time_zone ?: 'UTC')->toDateString(),
                'verified_visit' => true])->values()->all(),
            'next_url' => $records->count() > $limit ? route('public.booking.reviews', ['slug' => $business->booking_slug, 'before' => $items->last()->public_id]) : null];
    }

    public function assertEligible(Appointment $appointment): void
    {
        if (! $this->eligible($appointment)) {
            throw ValidationException::withMessages(['review' => 'Feedback opens after the business marks your service complete.']);
        }
    }

    private function assertLink(PublicAppointmentLink $link, Appointment $appointment): void
    {
        abort_unless($link->purpose === 'review' && $link->business_id === $appointment->business_id && $link->appointment_id === $appointment->id, 404);
        abort_if($link->revoked_at || $link->expires_at->lessThanOrEqualTo(now()), 410, 'This review link has expired. Ask the business for a new review link.');
    }
}
