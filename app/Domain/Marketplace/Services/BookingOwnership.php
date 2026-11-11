<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Models\CustomerBooking;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingOwnership
{
    /** Verified customer email is the approved account-to-booking identity boundary.
     * Tenant Client records remain separate; existing ownership is never reassigned.
     */
    public function synchronize(Customer $customer): void
    {
        if (! $customer->email_verified_at) {
            return;
        }
        Appointment::query()->whereRaw('LOWER(TRIM(client_email)) = ?', [$customer->email])
            ->whereNotIn('id', CustomerBooking::query()->select('appointment_id'))
            ->orderBy('id')->chunkById(100, function ($appointments) use ($customer) {
                foreach ($appointments as $appointment) {
                    $this->attach($customer, $appointment);
                }
            });
    }

    public function attach(Customer $customer, Appointment $appointment): CustomerBooking
    {
        return DB::transaction(function () use ($customer, $appointment) {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if (mb_strtolower(trim((string) $appointment->client_email)) !== $customer->email) {
                throw ValidationException::withMessages(['booking' => 'Use the customer account with the same email as this booking. Your booking is unchanged.']);
            }
            $existing = CustomerBooking::query()->where('appointment_id', $appointment->id)->first();
            if ($existing) {
                abort_unless($existing->customer_id === $customer->id && $existing->business_id === $appointment->business_id, 404);

                return $existing;
            }
            $booking = CustomerBooking::query()->create(['customer_id' => $customer->id, 'business_id' => $appointment->business_id, 'appointment_id' => $appointment->id]);
            app(AuditWriter::class)->write('marketplace.booking.linked', $appointment->business, target: $appointment, metadata: ['customer_public_id' => $customer->public_id], source: 'marketplace_customer');

            return $booking;
        });
    }
}
