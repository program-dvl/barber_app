<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceResourceRequirement;
use App\Domain\BusinessConfiguration\Models\ServiceSegment;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PublicBooking\Models\WaitlistMatch;
use App\Domain\PublicBooking\Models\WaitlistRequest;
use App\Domain\PublicBooking\Services\WaitlistService;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Contracts\AvailabilityQuery;
use App\Domain\SchedulingOperations\Contracts\BookingCommitCommand;
use App\Domain\SchedulingOperations\Contracts\CalendarQuery;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldCommand;
use App\Domain\SchedulingOperations\Contracts\CapacityHoldExpiryCommand;
use App\Domain\SchedulingOperations\Data\AvailabilitySearch;
use App\Domain\SchedulingOperations\Data\BookingLineRequest;
use App\Domain\SchedulingOperations\Data\BookingRequest;
use App\Domain\SchedulingOperations\Data\CalendarFilter;
use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Domain\SchedulingOperations\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (getenv('BOOKING_MYSQL_INTEGRATION') !== '1') {
        $this->markTestSkipped('Set BOOKING_MYSQL_INTEGRATION=1 to run destructive tests against the dedicated barber_app_booking_test database.');
    }
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('The pcntl extension is required for genuinely parallel requests.');
    }

    $connection = config('database.connections.mysql');
    $connection['database'] = getenv('BOOKING_MYSQL_DATABASE') ?: 'barber_app_booking_test';
    config([
        'database.default' => 'booking_mysql',
        'database.connections.booking_mysql' => $connection,
    ]);
    DB::purge('booking_mysql');
    DB::setDefaultConnection('booking_mysql');
    Artisan::call('migrate:fresh', ['--database' => 'booking_mysql', '--force' => true]);
});

/** @return array{business:Business,location:Location,staff:StaffProfile,service:Service,resource:PhysicalResource} */
function mysqlSchedulingPath(int $resourceQuantity = 1, bool $segments = false): array
{
    $business = Business::factory()->create([
        'appointment_interval_minutes' => 15, 'time_zone' => 'Asia/Kolkata', 'currency_code' => 'INR',
    ]);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);
    LocationHour::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => 1,
        'opens_at' => '09:00', 'closes_at' => '18:00', 'sequence' => 1,
    ]);
    $staff = mysqlAssignStaff($business, $location, null, 'Primary');
    $service = Service::query()->create([
        'business_id' => $business->id, 'kind' => 'service', 'name' => 'Concurrency service',
        'price_minor' => 5000, 'currency_code' => 'INR', 'duration_minutes' => $segments ? 30 : 60,
        'processing_minutes' => $segments ? 30 : 0, 'cleanup_minutes' => $segments ? 15 : 0,
        'minimum_notice_minutes' => 0, 'maximum_advance_days' => 60, 'client_eligibility' => 'all',
        'is_active' => true, 'online_visible' => true,
    ]);
    $service->locations()->attach($location->id, ['business_id' => $business->id, 'is_eligible' => true]);
    StaffServiceAssignment::query()->create([
        'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
        'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
    ]);
    if ($segments) {
        foreach ([
            ['active', 1, 30, true], ['processing', 2, 30, false], ['cleanup', 3, 15, true],
        ] as [$kind, $sequence, $minutes, $occupiesStaff]) {
            ServiceSegment::query()->create([
                'business_id' => $business->id, 'service_id' => $service->id, 'kind' => $kind,
                'sequence' => $sequence, 'duration_minutes' => $minutes, 'occupies_staff' => $occupiesStaff,
            ]);
        }
    }
    $resource = PhysicalResource::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'type' => 'chair',
        'name' => 'Shared chairs', 'quantity' => $resourceQuantity, 'is_active' => true,
    ]);

    return compact('business', 'location', 'staff', 'service', 'resource');
}

function mysqlAssignStaff(Business $business, Location $location, ?Service $service, string $name): StaffProfile
{
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'display_name' => $name]);
    $staff->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);
    StaffAvailabilityRule::query()->create([
        'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'location_id' => $location->id,
        'kind' => 'working', 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00',
    ]);
    if ($service) {
        StaffServiceAssignment::query()->create([
            'business_id' => $business->id, 'staff_profile_id' => $staff->id, 'service_id' => $service->id,
            'is_qualified' => true, 'is_active' => true, 'online_visible' => true,
        ]);
    }

    return $staff;
}

function mysqlRequest(array $path, int $staffId, string $source = 'online', array $segmentStaff = [], ?CarbonImmutable $asOf = null): BookingRequest
{
    $starts = CarbonImmutable::parse('2026-08-31 10:00:00', 'Asia/Kolkata')->utc();

    return new BookingRequest(
        $path['business']->id, $path['location']->id, $starts,
        [new BookingLineRequest($path['service']->id, $staffId, $segmentStaff, false)],
        $source, 'existing', $asOf ?? CarbonImmutable::parse('2026-08-25T00:00:00Z'),
    );
}

/**
 * Run callbacks behind a pipe barrier so every child starts after all children
 * exist. Each child reconnects to MySQL; no PDO handle is shared after fork.
 *
 * @param  callable(int): array<string, mixed>  $attempt
 * @return list<array<string, mixed>>
 */
function parallelMysqlAttempts(int $count, callable $attempt): array
{
    $children = [];
    for ($index = 0; $index < $count; $index++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Unable to create concurrency barrier.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork concurrency worker.');
        }
        if ($pid === 0) {
            fclose($pair[0]);
            fread($pair[1], 1);
            DB::purge('booking_mysql');
            DB::setDefaultConnection('booking_mysql');
            try {
                $result = ['ok' => true, ...$attempt($index)];
            } catch (BookingRuleViolation $violation) {
                $result = ['ok' => false, 'code' => $violation->ruleCode];
            } catch (Throwable $throwable) {
                $result = ['ok' => false, 'code' => $throwable::class, 'message' => $throwable->getMessage()];
            }
            fwrite($pair[1], json_encode($result, JSON_THROW_ON_ERROR));
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        $children[] = ['pid' => $pid, 'pipe' => $pair[0]];
    }

    foreach ($children as $child) {
        fwrite($child['pipe'], 'G');
    }
    $results = [];
    foreach ($children as $child) {
        $payload = stream_get_contents($child['pipe']);
        fclose($child['pipe']);
        pcntl_waitpid($child['pid'], $status);
        $results[] = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }
    DB::purge('booking_mysql');
    DB::setDefaultConnection('booking_mysql');

    return $results;
}

it('serializes online-online and online-reception races on one staff member', function () {
    foreach ([['online', 'online'], ['online', 'reception']] as $sources) {
        Artisan::call('migrate:fresh', ['--database' => 'booking_mysql', '--force' => true]);
        $path = mysqlSchedulingPath();
        $results = parallelMysqlAttempts(2, function (int $index) use ($path, $sources): array {
            $appointment = app(BookingCommitCommand::class)->commit(
                mysqlRequest($path, $path['staff']->id, $sources[$index]),
                'race-'.$sources[0].'-'.$sources[1].'-'.$index,
            );

            return ['appointment_id' => $appointment->id];
        });
        expect(collect($results)->where('ok', true))->toHaveCount(1)
            ->and(collect($results)->where('ok', false)->pluck('code')->all())->toBe(['STAFF_UNAVAILABLE'])
            ->and(Appointment::query()->count())->toBe(1);
    }
})->group('mysql-booking');

it('allows exactly one concurrent client profile edit from the same version', function () {
    $business = Business::factory()->create();
    $client = Client::factory()->create(['business_id' => $business->id]);
    $results = parallelMysqlAttempts(2, function (int $index) use ($client): array {
        $current = Client::query()->findOrFail($client->id);
        $updated = app(ClientIdentityService::class)->updateProfile(
            $current,
            ['email' => 'contender-'.$index.'@example.test'],
            1,
            'Concurrent client profile test.',
        );

        return ['version' => $updated->version];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false)->first()['message'])->toContain('changed by someone else')
        ->and(Client::query()->findOrFail($client->id)->version)->toBe(2);
})->group('mysql-booking');

it('serializes staff-free resource-busy and pooled resource quantity races', function () {
    foreach ([1 => 2, 2 => 3] as $quantity => $attempts) {
        Artisan::call('migrate:fresh', ['--database' => 'booking_mysql', '--force' => true]);
        $path = mysqlSchedulingPath($quantity);
        ServiceResourceRequirement::query()->create([
            'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
            'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
        ]);
        $staffIds = [$path['staff']->id];
        for ($i = 1; $i < $attempts; $i++) {
            $staffIds[] = mysqlAssignStaff($path['business'], $path['location'], $path['service'], 'Contender '.$i)->id;
        }
        $results = parallelMysqlAttempts($attempts, function (int $index) use ($path, $staffIds, $quantity): array {
            $appointment = app(BookingCommitCommand::class)->commit(mysqlRequest($path, $staffIds[$index]), 'resource-'.$quantity.'-'.$index);

            return ['appointment_id' => $appointment->id];
        });
        expect(collect($results)->where('ok', true))->toHaveCount($quantity)
            ->and(collect($results)->where('ok', false)->pluck('code')->unique()->all())->toBe(['RESOURCE_UNAVAILABLE'])
            ->and(Appointment::query()->count())->toBe($quantity);
    }
})->group('mysql-booking');

it('prevents partial multi-segment handoff commits under parallel contention', function () {
    $path = mysqlSchedulingPath(segments: true);
    $secondPrimary = mysqlAssignStaff($path['business'], $path['location'], $path['service'], 'Second primary');
    $handoff = mysqlAssignStaff($path['business'], $path['location'], $path['service'], 'Shared handoff');
    $primaryIds = [$path['staff']->id, $secondPrimary->id];
    $results = parallelMysqlAttempts(2, function (int $index) use ($path, $primaryIds, $handoff): array {
        $request = mysqlRequest($path, $primaryIds[$index], segmentStaff: [3 => $handoff->id]);
        $appointment = app(BookingCommitCommand::class)->commit($request, 'segment-race-'.$index);

        return ['appointment_id' => $appointment->id];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(Appointment::query()->count())->toBe(1)
        ->and(Appointment::query()->firstOrFail()->segments()->count())->toBe(3);
})->group('mysql-booking');

it('releases an expired hold safely while a competitor commits', function () {
    $path = mysqlSchedulingPath();
    $request = mysqlRequest($path, $path['staff']->id, asOf: CarbonImmutable::parse('2026-08-25T00:00:00Z'));
    app(CapacityHoldCommand::class)->hold($request, 'expiring-hold', 30);
    $afterExpiry = CarbonImmutable::parse('2026-08-25T00:00:31Z');
    $results = parallelMysqlAttempts(2, function (int $index) use ($path, $afterExpiry): array {
        if ($index === 0) {
            return ['expired' => app(CapacityHoldExpiryCommand::class)->expire($afterExpiry, $path['business']->id)];
        }
        $request = mysqlRequest($path, $path['staff']->id, asOf: $afterExpiry);
        $appointment = app(BookingCommitCommand::class)->commit($request, 'after-expiry-competitor');

        return ['appointment_id' => $appointment->id];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(2)
        ->and(Appointment::query()->count())->toBe(1);
})->group('mysql-booking');

it('confirms an unchanged hold after MySQL normalizes JSON snapshot key order', function () {
    $path = mysqlSchedulingPath();
    $request = mysqlRequest($path, $path['staff']->id);
    $hold = app(CapacityHoldCommand::class)->hold($request, 'mysql-json-hold', 600);
    $appointment = app(BookingCommitCommand::class)->confirmHold(
        $path['business']->id,
        $hold->public_id,
        'mysql-json-confirm',
        $request->now()->addMinute(),
    );

    expect($appointment->status)->toBe('confirmed')
        ->and($hold->fresh()->status)->toBe('confirmed');
})->group('mysql-booking');

it('returns one appointment for genuinely parallel duplicate commands', function () {
    $path = mysqlSchedulingPath();
    $results = parallelMysqlAttempts(2, function () use ($path): array {
        $appointment = app(BookingCommitCommand::class)->commit(mysqlRequest($path, $path['staff']->id), 'parallel-duplicate');

        return ['appointment_id' => $appointment->id];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(2)
        ->and(collect($results)->pluck('appointment_id')->unique())->toHaveCount(1)
        ->and(Appointment::query()->count())->toBe(1);
})->group('mysql-booking');

it('rejects a stale search result after another request claims it', function () {
    $path = mysqlSchedulingPath();
    $date = CarbonImmutable::parse('2026-08-31', 'Asia/Kolkata');
    $slots = app(AvailabilityQuery::class)->search(new AvailabilitySearch(
        $path['business']->id, $path['location']->id, $date, $date,
        [new BookingLineRequest($path['service']->id, $path['staff']->id, [], false)],
        limit: 10, asOfUtc: CarbonImmutable::parse('2026-08-25T00:00:00Z'),
    ));
    expect($slots)->not->toBeEmpty();

    $results = parallelMysqlAttempts(2, function (int $index) use ($path): array {
        $appointment = app(BookingCommitCommand::class)->commit(mysqlRequest($path, $path['staff']->id), 'stale-'.$index);

        return ['appointment_id' => $appointment->id];
    });
    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false)->pluck('code')->all())->toBe(['STAFF_UNAVAILABLE']);
})->group('mysql-booking');

it('measures representative MySQL search and commit query performance', function () {
    $path = mysqlSchedulingPath(2);
    ServiceResourceRequirement::query()->create([
        'business_id' => $path['business']->id, 'service_id' => $path['service']->id,
        'physical_resource_id' => $path['resource']->id, 'quantity' => 1,
    ]);
    $date = CarbonImmutable::parse('2026-08-31', 'Asia/Kolkata');
    DB::flushQueryLog();
    DB::enableQueryLog();
    $searchStarted = hrtime(true);
    $slots = app(AvailabilityQuery::class)->search(new AvailabilitySearch(
        $path['business']->id, $path['location']->id, $date, $date,
        [new BookingLineRequest($path['service']->id, null, [], true)],
        limit: 20, asOfUtc: CarbonImmutable::parse('2026-08-25T00:00:00Z'),
    ));
    $searchMs = (hrtime(true) - $searchStarted) / 1_000_000;
    $searchQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $commitStarted = hrtime(true);
    app(BookingCommitCommand::class)->commit(mysqlRequest($path, $path['staff']->id), 'performance-commit');
    $commitMs = (hrtime(true) - $commitStarted) / 1_000_000;
    $commitQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($slots)->toHaveCount(20)
        ->and($searchMs)->toBeLessThan(500.0)
        ->and($commitMs)->toBeLessThan(2000.0)
        ->and($searchQueries)->toBeLessThan(800)
        ->and($commitQueries)->toBeLessThan(100);

    test()->addToAssertionCount(1);
    fwrite(STDERR, sprintf("\nBOOKING_PERF search_ms=%.2f search_queries=%d commit_ms=%.2f commit_queries=%d\n", $searchMs, $searchQueries, $commitMs, $commitQueries));
})->group('mysql-booking');

it('serializes stale concurrent lifecycle edits without losing history', function () {
    $path = mysqlSchedulingPath();
    $appointment = app(BookingCommitCommand::class)->commit(mysqlRequest($path, $path['staff']->id, 'reception'), 'lifecycle-original');
    $results = parallelMysqlAttempts(2, function (int $index) use ($appointment): array {
        $status = $index === 0 ? 'arrived' : 'late';
        $updated = app(AppointmentLifecycleCommand::class)->transition(
            $appointment,
            $status,
            'lifecycle-race-'.$index,
            1,
            'calendar',
            reason: $status === 'late' ? 'Client called ahead.' : null,
        );

        return ['appointment_id' => $updated->id, 'status' => $updated->status];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false)->pluck('code')->all())->toBe(['STALE_APPOINTMENT'])
        ->and($appointment->fresh()->version)->toBe(2)
        ->and($appointment->fresh()->statusHistory()->count())->toBe(2)
        ->and($appointment->fresh()->changes()->count())->toBe(1);
})->group('mysql-booking');

it('atomically awards a controlled waitlist batch to only one parallel claimant', function () {
    $path = mysqlSchedulingPath();
    $path['business']->update(['waitlist_offer_batch_size' => 2]);
    $appointment = app(BookingCommitCommand::class)->commit(mysqlRequest($path, $path['staff']->id), 'waitlist-race-original');
    $waitlist = app(WaitlistService::class);
    foreach ([['First', '+911'], ['Second', '+912']] as [$name, $mobile]) {
        $waitlist->create($path['business'], $path['location'], $path['service'], $path['staff'], [
            'client_name' => $name, 'client_mobile' => $mobile, 'client_email' => strtolower($name).'@example.test',
            'acceptable_from' => '2026-08-31', 'acceptable_until' => '2026-08-31',
            'time_from' => '09:00', 'time_until' => '12:00', 'notification_method' => 'email',
        ]);
    }
    $cancelled = app(AppointmentLifecycleCommand::class)->transition($appointment, 'cancelled_by_shop', 'waitlist-race-cancel', 1, 'calendar', reason: 'Opening for concurrency evidence.');
    $offers = $waitlist->offerForOpening($cancelled);
    expect($offers)->toHaveCount(2);
    $tokens = collect($offers)->pluck('token')->all();
    $results = parallelMysqlAttempts(2, function (int $index) use ($tokens): array {
        $claimed = app(WaitlistService::class)->claim($tokens[$index]);

        return ['appointment_id' => $claimed->id];
    });

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(WaitlistMatch::query()->where('status', 'claimed')->count())->toBe(1)
        ->and(WaitlistRequest::query()->where('status', 'booked')->count())->toBe(1)
        ->and(Appointment::query()->where('starts_at_utc', $appointment->starts_at_utc)->where('status', 'confirmed')->count())->toBe(1);
})->group('mysql-booking');

it('measures a production-engine calendar projection with status and block cues', function () {
    $path = mysqlSchedulingPath();
    $staff = [$path['staff']];
    for ($index = 1; $index < 8; $index++) {
        $staff[] = mysqlAssignStaff($path['business'], $path['location'], $path['service'], 'Calendar staff '.$index);
    }
    foreach ($staff as $index => $member) {
        foreach ([9, 11, 13, 15] as $hour) {
            $starts = CarbonImmutable::parse(sprintf('2026-08-31 %02d:00:00', $hour), 'Asia/Kolkata')->utc();
            app(BookingCommitCommand::class)->commit(new BookingRequest(
                $path['business']->id, $path['location']->id, $starts,
                [new BookingLineRequest($path['service']->id, $member->id, [], false)],
                'reception', 'existing', CarbonImmutable::parse('2026-08-25T00:00:00Z'),
            ), 'calendar-load-'.$index.'-'.$hour);
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = hrtime(true);
    $calendar = app(CalendarQuery::class)->calendar(new CalendarFilter(
        $path['business']->id,
        $path['location']->id,
        'staff',
        CarbonImmutable::parse('2026-08-31', 'Asia/Kolkata'),
    ));
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($calendar['events'])->toHaveCount(32)
        ->and($elapsedMs)->toBeLessThan(500.0)
        ->and($queries)->toBeLessThan(20)
        ->and(collect($calendar['events'])->every(fn (array $event) => isset($event['statusLabel'], $event['statusCue'])))->toBeTrue();

    fwrite(STDERR, sprintf("\nCALENDAR_PERF events=%d ms=%.2f queries=%d\n", count($calendar['events']), $elapsedMs, $queries));
})->group('mysql-booking');
