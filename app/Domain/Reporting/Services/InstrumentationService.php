<?php

namespace App\Domain\Reporting\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\Reporting\Models\InstrumentationEvent;
use DomainException;

class InstrumentationService
{
    private const ALLOWED_DIMENSIONS = ['acquisition_channel', 'plan', 'business_type', 'staff_band', 'geography', 'cohort', 'location_public_id', 'outcome', 'source'];

    /** @param array<string,string|int|bool|null> $dimensions */
    public function record(?Business $business, string $eventName, string $idempotencyKey, array $dimensions = [], ?string $subjectKey = null): InstrumentationEvent
    {
        if (! isset(MetricCatalog::instrumentation()[$eventName])) {
            throw new DomainException('Unknown instrumentation event.');
        }
        if (array_diff(array_keys($dimensions), self::ALLOWED_DIMENSIONS)) {
            throw new DomainException('Instrumentation dimensions must be privacy-safe catalog fields.');
        }
        foreach ($dimensions as $value) {
            if (is_string($value) && (str_contains($value, '@') || preg_match('/\+?\d[\d\s-]{7,}/', $value))) {
                throw new DomainException('Instrumentation dimensions cannot contain direct contact data.');
            }
        }

        $tenantIdempotencyKey = hash('sha256', ($business?->id ?? 'platform').'|'.$idempotencyKey);

        return InstrumentationEvent::query()->firstOrCreate(
            ['event_name' => $eventName, 'idempotency_key' => $tenantIdempotencyKey],
            ['business_id' => $business?->id, 'metric_version' => MetricCatalog::VERSION, 'subject_hash' => $subjectKey ? hash_hmac('sha256', $subjectKey, (string) config('app.key')) : null, 'dimensions' => $dimensions, 'occurred_at' => now()],
        );
    }
}
