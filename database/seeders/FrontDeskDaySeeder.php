<?php

namespace Database\Seeders;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Models\ScheduleBlock;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Domain\SchedulingOperations\Services\ScheduleBlockService;
use App\Domain\SchedulingOperations\Services\WalkInQueueService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class FrontDeskDaySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GoodHoursDemoSeeder::class);
        $business = Business::query()->where('slug', 'good-hours-demo-tenant')->firstOrFail();
        $location = Location::query()->where('business_id', $business->id)->where('name', 'Indiranagar Studio')->firstOrFail();
        $service = Service::query()->where('business_id', $business->id)->where('name', 'Signature cut')->firstOrFail();
        $staff = collect([
            StaffProfile::query()->where('business_id', $business->id)->where('display_name', 'Aria')->firstOrFail(),
            $this->staff($business, $location, $service, 'Dev', 'Stylist'),
            $this->staff($business, $location, $service, 'Mina', 'Barber'),
        ]);
        $localDate = CarbonImmutable::now($location->time_zone)->next('Monday')->startOfDay();
        $asOf = $localDate->subDay()->utc();
        $appointments = [
            ['09:00', 0, 'Priya Shah', 'in_service'],
            ['09:00', 1, 'Noah Williams', 'checked_in'],
            ['09:45', 2, 'Anika Rao', 'arrived'],
            ['10:00', 0, 'Maya Singh', 'confirmed'],
            ['10:30', 1, 'Rohan Mehta', 'late'],
            ['11:00', 2, 'Sam Patel', 'confirmed'],
            ['13:00', 0, 'Ira Bose', 'confirmed'],
            ['14:00', 1, 'Leah Thomas', 'confirmed'],
        ];
        foreach ($appointments as $index => [$time, $staffIndex, $clientName, $status]) {
            $starts = CarbonImmutable::parse($localDate->toDateString().' '.$time, $location->time_zone)->utc();
            $appointment = app(BookingCommitCommand::class)->commit(new BookingRequest(
                $business->id,
                $location->id,
                $starts,
                [new BookingLineRequest($service->id, $staff[$staffIndex]->id, [], false)],
                'reception',
                'existing',
                $asOf,
                null,
                'seeder',
                null,
                $clientName,
                '+91 90000 '.str_pad((string) ($index + 1000), 5, '0', STR_PAD_LEFT),
                $index === 4 ? 'Client called to say they are running late.' : null,
            ), 'front-desk-day-'.$localDate->toDateString().'-'.$index);
            $this->advanceStatus($appointment, $status, $localDate, $index);
        }

        if (! ScheduleBlock::query()
            ->where('business_id', $business->id)->where('staff_profile_id', $staff[2]->id)->where('label', 'Team huddle')->exists()) {
            app(ScheduleBlockService::class)->create(
                $business->id,
                $location->id,
                $staff[2]->id,
                'personal_block',
                'Team huddle',
                CarbonImmutable::parse($localDate->toDateString().' 17:00', $location->time_zone)->utc(),
                CarbonImmutable::parse($localDate->toDateString().' 17:30', $location->time_zone)->utc(),
                'Production-like daily operations fixture.',
                'seeder',
            );
        }

        if (! WalkInEntry::query()->where('business_id', $business->id)->where('client_name', 'Walk-in: Alex')->exists()) {
            app(WalkInQueueService::class)->add(
                $business->id,
                $location->id,
                $service->id,
                'Walk-in: Alex',
                '+91 90000 02001',
                null,
                CarbonImmutable::parse($localDate->toDateString().' 09:10', $location->time_zone)->utc(),
                'First available barber.',
                'reception',
                'seeder',
                null,
            );
        }
        if (! WalkInEntry::query()->where('business_id', $business->id)->where('client_name', 'Walk-in: Nisha')->exists()) {
            app(WalkInQueueService::class)->add(
                $business->id,
                $location->id,
                $service->id,
                'Walk-in: Nisha',
                '+91 90000 02002',
                $staff[1]->id,
                CarbonImmutable::parse($localDate->toDateString().' 09:15', $location->time_zone)->utc(),
                null,
                'reception',
                'seeder',
                null,
            );
        }

        $this->command?->info('Front-desk day seeded for '.$localDate->toDateString().' '.$location->time_zone.'.');
    }

    private function staff(Business $business, Location $location, Service $service, string $name, string $title): StaffProfile
    {
        $staff = StaffProfile::query()->firstOrCreate(['business_id' => $business->id, 'display_name' => $name], [
            'title' => $title, 'status' => 'active', 'online_visible' => true,
        ]);
        $staff->locations()->syncWithoutDetaching([$location->id => ['business_id' => $business->id]]);
        StaffServiceAssignment::query()->firstOrCreate([
            'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
        ], ['is_qualified' => true, 'is_active' => true, 'online_visible' => true]);
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            StaffAvailabilityRule::query()->firstOrCreate([
                'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id,
                'kind' => 'working', 'day_of_week' => $day, 'sequence' => 1,
            ], ['starts_at' => '09:00', 'ends_at' => '17:30']);
        }

        return $staff;
    }

    private function advanceStatus(mixed $appointment, string $target, CarbonImmutable $date, int $index): void
    {
        $paths = [
            'arrived' => ['arrived'],
            'checked_in' => ['arrived', 'checked_in'],
            'in_service' => ['arrived', 'in_service'],
            'late' => ['late'],
        ];
        foreach ($paths[$target] ?? [] as $step => $status) {
            if ($appointment->status === $status || $appointment->status === $target) {
                continue;
            }
            $appointment = app(AppointmentLifecycleCommand::class)->transition(
                $appointment,
                $status,
                'front-desk-status-'.$date->toDateString().'-'.$index.'-'.$step,
                $appointment->version,
                'seeder',
                reason: $status === 'late' ? 'Client called ahead.' : null,
            );
        }
    }
}
