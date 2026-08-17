<?php

namespace Tests\Feature;

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $response = $this->put('/user/profile-information', [
            'name' => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_page_renders_without_an_active_business_context(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/user/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Profile/Show'));
    }

    public function test_profile_page_exposes_only_the_users_own_billing_workspace(): void
    {
        [$owner, $business] = createTenantMembership(StarterRole::Owner);
        [, $otherBusiness] = createTenantMembership(StarterRole::Owner);

        $this->actingAs($owner)
            ->get('/user/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Show')
                ->where('tenant', null)
                ->has('account.workspaces', 1)
                ->where('account.workspaces.0.public_id', $business->public_id)
                ->where('account.workspaces.0.name', $business->name)
                ->where('account.workspaces.0.can_manage_billing', true)
            );
    }
}
