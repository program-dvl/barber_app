<?php

namespace App\Actions\Auth;

use App\Domain\Billing\Models\OwnerRegistrationIntent;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Jetstream;

class CompleteGoogleRegistration
{
    public function __construct(private readonly PublicPricingCatalog $catalog) {}

    /**
     * @param  array<string, mixed>  $google
     * @param  array<string, mixed>  $input
     */
    public function handle(array $google, array $input): User
    {
        $input['name'] = $input['name'] ?? $google['name'];
        $input['email'] = $google['email'];
        $input['selected_plan'] = data_get($google, 'selection.plan');
        $input['selected_interval'] = data_get($google, 'selection.interval');

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : [],
            'selected_plan' => ['nullable', 'string', 'max:32'],
            'selected_interval' => ['nullable', 'string', 'max:16'],
        ])->after(function ($validator) use ($input): void {
            $plan = $input['selected_plan'] ?? null;
            $interval = $input['selected_interval'] ?? null;

            if (($plan !== null || $interval !== null) && ! $this->catalog->validSelection($plan, $interval)) {
                $validator->errors()->add('selected_plan', 'The selected public plan or billing interval is no longer available.');
            }
        })->validate();

        $selection = $this->catalog->validSelection(
            $validated['selected_plan'] ?? null,
            $validated['selected_interval'] ?? null,
        );

        return DB::transaction(function () use ($google, $validated, $selection): User {
            $accountExists = SocialAccount::query()
                ->where('provider', 'google')
                ->where('account_id', $google['account_id'])
                ->lockForUpdate()
                ->exists();
            $emailExists = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
                ->lockForUpdate()
                ->exists();

            if ($accountExists || $emailExists) {
                throw ValidationException::withMessages([
                    'google' => 'An account now exists for this Google identity. Return to sign in and continue securely.',
                ]);
            }

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            OwnerRegistrationIntent::query()->create([
                'user_id' => $user->getKey(),
                'business_name' => $validated['business_name'],
                'selected_plan_code' => $selection['plan'] ?? null,
                'selected_billing_interval' => $selection['interval'] ?? null,
                'status' => 'pending',
            ]);

            $user->socialAccounts()->create([
                'provider' => 'google',
                'account_id' => $google['account_id'],
                'token' => null,
            ]);

            event(new Verified($user));

            return $user;
        }, 3);
    }
}
