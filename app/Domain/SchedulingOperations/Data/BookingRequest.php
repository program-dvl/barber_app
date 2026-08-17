<?php

namespace App\Domain\SchedulingOperations\Data;

use Carbon\CarbonImmutable;

final readonly class BookingRequest
{
    /** @param list<BookingLineRequest> $lines */
    public function __construct(
        public int $businessId,
        public int $locationId,
        public CarbonImmutable $startsAtUtc,
        public array $lines,
        public string $source = 'online',
        public string $clientEligibility = 'existing',
        public ?CarbonImmutable $asOfUtc = null,
        public ?string $capacityOwnerKey = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $clientName = null,
        public ?string $clientMobile = null,
        public ?string $internalNotes = null,
        public array $overrideRuleCodes = [],
        public ?string $overrideReason = null,
        public ?string $clientEmail = null,
        public ?string $clientDateOfBirth = null,
        public ?string $referralSource = null,
        public array $communicationPreferences = [],
        public bool $marketingOptIn = false,
        public ?string $specialRequest = null,
        public array $publicPolicySnapshot = [],
    ) {}

    public function now(): CarbonImmutable
    {
        return ($this->asOfUtc ?? CarbonImmutable::now())->utc();
    }

    /** @return array<string, mixed> */
    public function normalized(): array
    {
        return [
            'business_id' => $this->businessId,
            'location_id' => $this->locationId,
            'starts_at_utc' => $this->startsAtUtc->utc()->format('Y-m-d\TH:i:s\Z'),
            'lines' => array_map(fn (BookingLineRequest $line) => $line->toArray(), $this->lines),
            'source' => $this->source,
            'client_eligibility' => $this->clientEligibility,
            'capacity_owner_key' => $this->capacityOwnerKey,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'client_name' => $this->clientName,
            'client_mobile' => $this->clientMobile,
            'internal_notes' => $this->internalNotes,
            'override_rule_codes' => array_values(array_unique($this->overrideRuleCodes)),
            'override_reason' => $this->overrideReason,
            'client_email' => $this->clientEmail,
            'client_date_of_birth' => $this->clientDateOfBirth,
            'referral_source' => $this->referralSource,
            'communication_preferences' => $this->communicationPreferences,
            'marketing_opt_in' => $this->marketingOptIn,
            'special_request' => $this->specialRequest,
            'public_policy_snapshot' => $this->publicPolicySnapshot,
        ];
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->normalized(), JSON_THROW_ON_ERROR));
    }
}
