<?php

namespace App\Actions\Fortify;

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $catalog = app(PublicPricingCatalog::class);
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            'selected_plan' => ['nullable', 'string', 'max:32'],
            'selected_interval' => ['nullable', 'string', 'max:16'],
            'selected_locations' => ['nullable', 'integer', 'min:1', 'max:'.config('capacity-billing.max_locations')],
            'selected_staff' => ['nullable', 'integer', 'min:1', 'max:'.config('capacity-billing.max_staff')],
        ])->after(function ($validator) use ($catalog, $input): void {
            $plan = $input['selected_plan'] ?? null;
            $interval = $input['selected_interval'] ?? null;
            if (($plan !== null || $interval !== null || isset($input['selected_locations']) || isset($input['selected_staff'])) && ! $catalog->validSelection($plan, $interval, $input['selected_locations'] ?? 1, $input['selected_staff'] ?? 1)) {
                $validator->errors()->add('selected_plan', 'The selected public plan or billing interval is no longer available.');
            }
        })->validate();

        $selection = $catalog->validSelection($input['selected_plan'] ?? null, $input['selected_interval'] ?? null, $input['selected_locations'] ?? 1, $input['selected_staff'] ?? 1);

        return DB::transaction(function () use ($input, $selection): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            OwnerRegistrationIntent::query()->create([
                'user_id' => $user->getKey(),
                'business_name' => $input['business_name'],
                'selected_plan_code' => $selection['plan'] ?? null,
                'selected_billing_interval' => $selection['interval'] ?? null,
                'selected_locations' => $selection['locations'] ?? null,
                'selected_staff' => $selection['staff'] ?? null,
                'status' => 'pending',
            ]);

            return $user;
        });
    }
}
