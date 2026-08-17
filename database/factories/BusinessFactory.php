<?php

namespace Database\Factories;

use App\Domain\PlatformAccess\Enums\BusinessStatus;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'status' => BusinessStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Business $business) => app(BusinessAccessBootstrapper::class)->bootstrap($business));
    }
}
