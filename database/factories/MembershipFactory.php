<?php

namespace Database\Factories;

use App\Domain\PlatformAccess\Enums\MembershipStatus;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'public_id' => (string) Str::ulid(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => MembershipStatus::Revoked,
            'revoked_at' => now(),
            'revocation_reason' => 'Factory-created revoked membership',
        ]);
    }
}
