<?php

namespace Database\Factories;

use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $name = fake()->name();
        $email = fake()->unique()->safeEmail();
        $mobile = '+91'.fake()->numerify('9#########');

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'normalized_name' => ClientIdentityNormalizer::name($name),
            'email' => $email,
            'normalized_email' => ClientIdentityNormalizer::email($email),
            'mobile' => $mobile,
            'normalized_mobile' => ClientIdentityNormalizer::mobile($mobile),
            'communication_preferences' => ['email' => true],
            'preferences' => [],
            'marketing_status' => 'unknown',
            'status' => 'active',
            'version' => 1,
        ];
    }
}
