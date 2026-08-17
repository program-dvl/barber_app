<?php

namespace Database\Factories;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StaffProfile> */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::ulid(),
            'display_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->e164PhoneNumber(),
            'title' => fake()->randomElement(['Barber', 'Stylist', 'Receptionist']),
            'status' => 'active',
            'online_visible' => true,
        ];
    }
}
