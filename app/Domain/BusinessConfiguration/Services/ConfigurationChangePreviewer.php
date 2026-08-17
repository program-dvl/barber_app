<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Contracts\AppointmentImpactSource;
use App\Domain\BusinessConfiguration\Models\ConfigurationChangePreview;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Model;

class ConfigurationChangePreviewer
{
    public function __construct(private readonly AppointmentImpactSource $source) {}

    /** @param array<string, mixed> $proposedChange */
    public function preview(Business $business, Model $subject, string $changeType, array $proposedChange): ConfigurationChangePreview
    {
        if ((int) $subject->business_id !== (int) $business->getKey()) {
            throw new \InvalidArgumentException('Impact preview subject must belong to the business.');
        }
        $ids = array_values(array_unique($this->source->affectedAppointmentIds($business, $subject, $changeType, $proposedChange)));

        return ConfigurationChangePreview::query()->create([
            'business_id' => $business->getKey(), 'change_type' => $changeType,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'proposed_change' => $proposedChange, 'affected_appointment_ids' => $ids,
            'affected_count' => count($ids), 'status' => 'previewed', 'expires_at' => now()->addHour(),
        ]);
    }
}
