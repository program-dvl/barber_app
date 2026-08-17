<?php

namespace App\Domain\PublicBooking\Services;

use App\Domain\PublicBooking\Models\PublicAppointmentLink;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;

class SecureAppointmentLinkService
{
    public const PURPOSES = ['view', 'reschedule', 'cancel', 'rebook', 'contact', 'waitlist', 'payment_status'];

    /** @return array{token:string,expires_at:string,purpose:string} */
    public function issue(Appointment $appointment, string $purpose, ?CarbonImmutable $expiresAt = null): array
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException('Unsupported appointment-link purpose.');
        }
        $token = bin2hex(random_bytes(32));
        $expires = ($expiresAt ?? CarbonImmutable::now()->addMinutes((int) ($appointment->business?->public_link_ttl_minutes ?: 10080)))->utc();
        PublicAppointmentLink::query()->create([
            'business_id' => $appointment->business_id,
            'appointment_id' => $appointment->id,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        return ['token' => $token, 'expires_at' => $expires->toIso8601String(), 'purpose' => $purpose];
    }

    public function resolve(string $token, string $purpose, bool $consume = false): PublicAppointmentLink
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            abort(404);
        }
        $link = PublicAppointmentLink::query()->with(['appointment.location', 'appointment.serviceLines.segments'])
            ->where('token_hash', hash('sha256', $token))->first();
        abort_unless($link && hash_equals($link->purpose, $purpose), 404);
        abort_if($link->revoked_at || $link->expires_at->isPast() || ($consume && $link->used_at), 410, 'This secure link has expired. Request a new link from the business.');
        if ($consume) {
            $link->forceFill(['used_at' => now()])->save();
        }

        return $link;
    }

    public function revokeAppointment(Appointment $appointment, ?string $exceptPurpose = null): int
    {
        return PublicAppointmentLink::query()->where('business_id', $appointment->business_id)->where('appointment_id', $appointment->id)
            ->when($exceptPurpose, fn ($query) => $query->where('purpose', '!=', $exceptPurpose))
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, string> */
    public function actionUrls(Appointment $appointment): array
    {
        return collect(['reschedule', 'cancel', 'rebook', 'contact', 'waitlist', 'payment_status'])
            ->mapWithKeys(function (string $purpose) use ($appointment): array {
                $issued = $this->issue($appointment, $purpose, CarbonImmutable::now()->addMinutes(30));

                return [$purpose => route('public.appointment.action', ['token' => $issued['token'], 'purpose' => $purpose])];
            })->all();
    }
}
