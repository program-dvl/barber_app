<?php

namespace Database\Factories;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::ulid(),
            'name' => fake()->unique()->company().' location',
            'time_zone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ];
    }
}
