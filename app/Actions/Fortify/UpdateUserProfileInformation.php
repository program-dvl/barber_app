<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $oldEmail = $user->email;
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
        Notification::route('mail', $oldEmail)->notify(new EmailAddressChangedNotification(
            accountName: $user->name,
            newEmailMasked: $this->maskEmail($input['email']),
        ));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', Str::lower($email), 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, min(8, mb_strlen($local) - mb_strlen($visible)))).'@'.$domain;
    }
}
