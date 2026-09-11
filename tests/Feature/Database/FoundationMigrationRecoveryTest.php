<?php

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('resumes partially applied foundation migrations without duplicating the billing catalog', function () {
    $catalogCounts = [
        'plans' => DB::table('billing_plans')->count(),
        'definitions' => DB::table('entitlement_definitions')->count(),
        'entitlements' => DB::table('billing_plan_entitlements')->count(),
    ];

    Schema::drop('owner_registration_intents');
    Schema::drop('audit_events');
    Schema::drop('platform_role_assignments');

    $platformMigration = require database_path('migrations/2026_08_11_000003_create_platform_access_foundation.php');
    $billingMigration = require database_path('migrations/2026_08_11_000004_create_subscription_entitlement_foundation.php');

    $platformMigration->up();
    $billingMigration->up();
    $platformMigration->up();
    $billingMigration->up();

    expect(Schema::hasTable('platform_role_assignments'))->toBeTrue()
        ->and(Schema::hasTable('audit_events'))->toBeTrue()
        ->and(Schema::hasTable('owner_registration_intents'))->toBeTrue()
        ->and(Schema::hasColumn('personal_access_tokens', 'business_id'))->toBeTrue()
        ->and(Schema::hasColumn('personal_access_tokens', 'membership_id'))->toBeTrue()
        ->and(DB::table('billing_plans')->count())->toBe($catalogCounts['plans'])
        ->and(DB::table('entitlement_definitions')->count())->toBe($catalogCounts['definitions'])
        ->and(DB::table('billing_plan_entitlements')->count())->toBe($catalogCounts['entitlements']);
});

it('repairs a missing inventory levels table without rerunning historical migrations', function () {
    Schema::drop('inventory_levels');

    $repair = require database_path('migrations/2026_08_16_000018_restore_missing_inventory_levels.php');
    $repair->up();
    $repair->up();

    expect(Schema::hasTable('inventory_levels'))->toBeTrue()
        ->and(Schema::hasColumns('inventory_levels', [
            'business_id',
            'location_id',
            'inventory_product_id',
            'current_stock',
        ]))->toBeTrue()
        ->and(DB::table('inventory_levels')->count())->toBe(0);
});

it('repairs the active owner location foundation without broadening staff access', function () {
    [, $business, $ownerMembership] = createTenantMembership(StarterRole::Owner);
    $staffUser = User::factory()->create();
    $staffMembership = Membership::factory()->create([
        'business_id' => $business->getKey(),
        'user_id' => $staffUser->getKey(),
    ]);
    app(MembershipAccessManager::class)->assignStarterRole(
        $staffMembership,
        StarterRole::Receptionist,
        $staffUser,
        'Migration recovery fixture.',
    );
    $location = Location::factory()->create([
        'business_id' => $business->getKey(),
        'name' => $business->name,
        'status' => 'inactive',
        'is_active' => false,
    ]);

    $repair = require database_path('migrations/2026_08_30_000003_backfill_owner_location_assignments.php');
    $repair->up();
    $repair->up();

    expect($location->fresh()->is_active)->toBeTrue()
        ->and($location->fresh()->status)->toBe('active')
        ->and(DB::table('locations')->where('business_id', $business->getKey())->count())->toBe(1)
        ->and(DB::table('location_membership')
            ->where('business_id', $business->getKey())
            ->where('location_id', $location->getKey())
            ->where('membership_id', $ownerMembership->getKey())
            ->count())->toBe(1)
        ->and(DB::table('location_membership')
            ->where('business_id', $business->getKey())
            ->where('membership_id', $staffMembership->getKey())
            ->count())->toBe(0);
});
