<?php

namespace App\Domain\ClientRecords\Services;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Models\ClientTag;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientRecordService
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function addNote(Client $client, string $kind, string $visibility, string $content, ?StaffProfile $author = null, ?int $appointmentId = null, bool $important = false): ClientNote
    {
        if (! in_array($kind, ClientNote::KINDS, true) || ! in_array($visibility, ['standard', 'sensitive'], true) || trim($content) === '') {
            throw ValidationException::withMessages(['note' => 'Choose a valid note type, visibility, and content.']);
        }
        abort_unless(! $author || $author->business_id === $client->business_id, 404);
        $note = ClientNote::query()->create([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'appointment_id' => $appointmentId,
            'authored_by_staff_profile_id' => $author?->id,
            'kind' => $kind,
            'visibility' => $visibility,
            'content' => trim($content),
            'is_important' => $important || $kind === 'warning',
        ]);
        $this->audit->write('client.note_added', $client->business, null, $note, null, [], [
            'kind' => $kind, 'visibility' => $visibility, 'important' => $note->is_important,
        ], [], 'client_records');

        return $note;
    }

    /** @param list<string> $tagNames @param list<int> $preferredServiceIds */
    public function syncPreferences(Client $client, array $tagNames, array $preferredServiceIds): void
    {
        $tags = collect($tagNames)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::lower($name))
            ->values();
        if ($tags->count() > 20) {
            throw ValidationException::withMessages(['tags' => 'A client can have at most 20 tags.']);
        }

        $tagIds = $tags->map(function (string $name) use ($client): int {
            $slug = Str::slug($name);
            if ($slug === '') {
                throw ValidationException::withMessages(['tags' => 'Each tag must contain letters or numbers.']);
            }

            return ClientTag::query()->firstOrCreate(
                ['business_id' => $client->business_id, 'slug' => $slug],
                ['name' => Str::limit($name, 80, '')],
            )->id;
        })->all();

        $validServiceIds = $client->business->services()->whereKey($preferredServiceIds)->pluck('id')->all();
        if (count(array_unique($preferredServiceIds)) !== count($validServiceIds)) {
            throw ValidationException::withMessages(['preferred_services' => 'Every preferred service must belong to this business.']);
        }

        $client->tags()->syncWithPivotValues($tagIds, ['business_id' => $client->business_id]);
        $client->preferredServices()->syncWithPivotValues($validServiceIds, ['business_id' => $client->business_id]);
        $this->audit->write('client.preferences_updated', $client->business, null, $client, null, [], [
            'tag_count' => count($tagIds), 'preferred_service_count' => count($validServiceIds),
        ], [], 'client_records');
    }

    /** @return array<string,mixed> */
    public function historySummary(Client $client): array
    {
        $appointments = $client->appointments()->with('serviceLines.segments')->get();
        $completed = $appointments->where('status', 'completed');
        $now = now();

        return [
            'visit_count' => $completed->count(),
            'last_visit' => $completed->sortByDesc('starts_at_utc')->first()?->starts_at_utc?->toIso8601String(),
            'next_appointment' => $appointments->filter(fn ($appointment) => $appointment->starts_at_utc->isAfter($now) && ! str_starts_with($appointment->status, 'cancelled'))->sortBy('starts_at_utc')->first()?->starts_at_utc?->toIso8601String(),
            'cancellations' => $appointments->filter(fn ($appointment) => str_starts_with($appointment->status, 'cancelled'))->count(),
            'no_shows' => $appointments->where('status', 'no_show')->count(),
            'lifetime_spend_minor' => 0,
            'financial_history_status' => 'awaiting_checkout_ledger',
            'appointments' => $appointments,
        ];
    }
}
