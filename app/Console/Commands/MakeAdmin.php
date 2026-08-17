<?php

namespace App\Console\Commands;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Models\User;
use Filament\Commands\MakeUserCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakeAdmin extends MakeUserCommand
{
    protected $signature = 'make:admin
                            {--name= : The name of the user}
                            {--email= : A valid and unique email address}
                            {--password= : The password for the user (min. 8 characters)}
                            {--panel=admin : The panel to create the user for}';

    /**
     * Returns user data.
     */
    protected function getUserData(): array
    {
        return [
            'name' => $this->options['name'] ?? text(
                label: 'Name',
                required: true,
            ),

            'email' => $this->options['email'] ?? text(
                label: 'Email address',
                required: true,
                validate: fn (string $email): ?string => match (true) {
                    ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                    User::where('email', $email)->exists() => 'A user with this email address already exists',
                    default => null,
                },
            ),

            'password' => Hash::make($this->options['password'] ?? password(
                label: 'Password',
                required: true,
            )),
        ];
    }

    /**
     * Create a platform user and a separate platform role assignment.
     */
    protected function createUser(): Authenticatable&Model
    {
        $user = $this->getUserModel()::create($this->getUserData());

        PlatformRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::Administrator,
            'reason' => 'Created through the make:admin bootstrap command.',
        ]);

        return $user;
    }

    public function handle(): int
    {
        parent::handle();

        return static::SUCCESS;
    }
}
