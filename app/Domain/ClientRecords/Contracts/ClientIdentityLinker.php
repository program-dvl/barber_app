<?php

namespace App\Domain\ClientRecords\Contracts;

use App\Domain\SchedulingOperations\Models\Appointment;

interface ClientIdentityLinker
{
    public function linkAppointment(Appointment $appointment): void;

    /** @param array{name?:string,mobile?:string,email?:string} $contact */
    public function synchronizeAppointmentContact(Appointment $appointment, array $contact): void;
}
