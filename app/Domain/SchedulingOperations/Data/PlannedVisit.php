<?php

namespace App\Domain\SchedulingOperations\Data;

use Carbon\CarbonImmutable;

final readonly class PlannedVisit
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(
        public CarbonImmutable $startsAtUtc,
        public CarbonImmutable $endsAtUtc,
        public string $timeZone,
        public array $lines,
        public int $priceMinor,
        public string $currencyCode,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $lines = array_map(function (array $line): array {
            $line['starts_at_utc'] = $line['starts_at_utc']->toIso8601String();
            $line['ends_at_utc'] = $line['ends_at_utc']->toIso8601String();
            $line['segments'] = array_map(function (array $segment): array {
                $segment['starts_at_utc'] = $segment['starts_at_utc']->toIso8601String();
                $segment['ends_at_utc'] = $segment['ends_at_utc']->toIso8601String();

                return $segment;
            }, $line['segments']);
            $line['resource_claims'] = array_map(function (array $claim): array {
                $claim['starts_at_utc'] = $claim['starts_at_utc']->toIso8601String();
                $claim['ends_at_utc'] = $claim['ends_at_utc']->toIso8601String();

                return $claim;
            }, $line['resource_claims']);

            return $line;
        }, $this->lines);

        return [
            'starts_at_utc' => $this->startsAtUtc->toIso8601String(),
            'ends_at_utc' => $this->endsAtUtc->toIso8601String(),
            'local_starts_at' => $this->startsAtUtc->setTimezone($this->timeZone)->toIso8601String(),
            'local_ends_at' => $this->endsAtUtc->setTimezone($this->timeZone)->toIso8601String(),
            'time_zone' => $this->timeZone,
            'price_minor' => $this->priceMinor,
            'currency_code' => $this->currencyCode,
            'lines' => $lines,
        ];
    }
}
