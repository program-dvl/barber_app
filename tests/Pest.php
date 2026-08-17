<?php

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    // Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create one unambiguous active User-to-Business membership with a starter role.
 *
 * @return array{0: User, 1: Business, 2: Membership}
 */
function createTenantMembership(StarterRole $role = StarterRole::Owner): array
{
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->create([
        'business_id' => $business->getKey(),
        'user_id' => $user->getKey(),
    ]);

    app(MembershipAccessManager::class)
        ->assignStarterRole($membership, $role, $user, 'Test fixture role assignment.');

    return [$user, $business, $membership->fresh()];
}
