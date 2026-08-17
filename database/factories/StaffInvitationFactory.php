<?php

namespace Database\Factories;

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StaffInvitation> */
class StaffInvitationFactory extends Factory
{
    protected $model = StaffInvitation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::ulid(),
            'invited_by_membership_id' => fn (array $attributes) => Membership::factory()->create([
                'business_id' => $attributes['business_id'],
            ])->getKey(),
            'role_id' => fn (array $attributes) => BusinessRole::query()
                ->where('business_id', $attributes['business_id'])
                ->where('name', StarterRole::Receptionist->value)
                ->value('id'),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
        ];
    }
}
