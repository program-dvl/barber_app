<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTableIfMissing('businesses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        $this->createBusinessPermissionTablesWhenMissing();

        $this->createTableIfMissing('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('time_zone', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'name']);
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'is_active']);
        });

        $this->createTableIfMissing('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('status', 24)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'status']);
        });

        $this->createTableIfMissing('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('membership_id')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('title')->nullable();
            $table->text('biography')->nullable();
            $table->string('status', 24)->default('active');
            $table->boolean('online_visible')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'status']);
            $table->foreign(['membership_id', 'business_id'])->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
        });

        $this->createTableIfMissing('location_membership', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('membership_id');
            $table->timestamps();
            $table->primary(['business_id', 'location_id', 'membership_id']);
            $table->foreign(['location_id', 'business_id'])->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->foreign(['membership_id', 'business_id'])->references(['id', 'business_id'])->on('memberships')->cascadeOnDelete();
        });

        $this->createTableIfMissing('location_staff_profile', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('staff_profile_id');
            $table->timestamps();
            $table->primary(['business_id', 'location_id', 'staff_profile_id']);
            $table->foreign(['location_id', 'business_id'])->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'])->references(['id', 'business_id'])->on('staff_profiles')->cascadeOnDelete();
        });

        $this->createTableIfMissing('staff_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->unsignedBigInteger('invited_by_membership_id');
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['staff_profile_id', 'business_id'])->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->foreign(['invited_by_membership_id', 'business_id'])->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
            $table->foreign(['role_id', 'business_id'])->references(['id', config('permission.column_names.team_foreign_key')])->on(config('permission.table_names.roles'))->restrictOnDelete();
            $table->index(['business_id', 'email']);
            $table->unique(['id', 'business_id']);
        });

        $this->createTableIfMissing('location_staff_invitation', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('staff_invitation_id');
            $table->timestamps();
            $table->primary(['business_id', 'location_id', 'staff_invitation_id']);
            $table->foreign(['location_id', 'business_id'], 'location_invitation_location_fk')->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->foreign(['staff_invitation_id', 'business_id'], 'location_invitation_invitation_fk')->references(['id', 'business_id'])->on('staff_invitations')->cascadeOnDelete();
        });

        $this->createTableIfMissing('platform_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 48)->index();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'role', 'revoked_at'], 'platform_role_active_lookup');
        });

        $this->createTableIfMissing('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('actor_platform_role', 48)->nullable();
            $table->string('action', 128)->index();
            $table->nullableMorphs('auditable');
            $table->string('source', 32);
            $table->uuid('correlation_id')->index();
            $table->string('ip_address_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->index(['business_id', 'occurred_at']);
            $table->index(['business_id', 'action']);
        });

        $this->addTenantColumnsToPersonalAccessTokens();

        $this->migrateLegacyPlatformAdministrators();
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'membership_id']);
            $table->dropConstrainedForeignId('membership_id');
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('platform_role_assignments');
        Schema::dropIfExists('location_staff_invitation');
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('location_staff_profile');
        Schema::dropIfExists('location_membership');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('locations');
        $this->dropBusinessPermissionTables();
        Schema::dropIfExists('businesses');
    }

    private function createBusinessPermissionTablesWhenMissing(): void
    {
        $tables = config('permission.table_names');
        $businessKey = config('permission.column_names.team_foreign_key');
        $modelKey = config('permission.column_names.model_morph_key');

        $this->createTableIfMissing($tables['permissions'], function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        $this->createTableIfMissing($tables['roles'], function (Blueprint $table) use ($businessKey): void {
            $table->id();
            $table->foreignId($businessKey)->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique([$businessKey, 'name', 'guard_name']);
            $table->unique(['id', $businessKey]);
        });

        $this->createTableIfMissing($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $businessKey, $modelKey): void {
            $table->foreignId('permission_id')->constrained($tables['permissions'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->unsignedBigInteger($businessKey);
            $table->index([$modelKey, 'model_type']);
            $table->index($businessKey);
            $table->primary([$businessKey, 'permission_id', $modelKey, 'model_type'], 'business_model_permissions_primary');
        });

        $this->createTableIfMissing($tables['model_has_roles'], function (Blueprint $table) use ($tables, $businessKey, $modelKey): void {
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger($modelKey);
            $table->unsignedBigInteger($businessKey);
            $table->index([$modelKey, 'model_type']);
            $table->index($businessKey);
            $table->primary([$businessKey, 'role_id', $modelKey, 'model_type'], 'business_model_roles_primary');
        });

        $this->createTableIfMissing($tables['role_has_permissions'], function (Blueprint $table) use ($tables): void {
            $table->foreignId('permission_id')->constrained($tables['permissions'])->cascadeOnDelete();
            $table->foreignId('role_id')->constrained($tables['roles'])->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
    }

    private function dropBusinessPermissionTables(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['role_has_permissions']);
        Schema::dropIfExists($tables['model_has_roles']);
        Schema::dropIfExists($tables['model_has_permissions']);
        Schema::dropIfExists($tables['roles']);
        Schema::dropIfExists($tables['permissions']);
    }

    private function createTableIfMissing(string $table, callable $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    private function addTenantColumnsToPersonalAccessTokens(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'business_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->foreignId('business_id')->nullable()->after('tokenable_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('personal_access_tokens', 'membership_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->foreignId('membership_id')->nullable()->after('business_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('personal_access_tokens', ['business_id', 'membership_id'])) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->index(['business_id', 'membership_id']);
            });
        }
    }

    private function migrateLegacyPlatformAdministrators(): void
    {
        $administratorIds = collect();

        if (Schema::hasColumn('users', 'is_admin')) {
            $administratorIds = DB::table('users')->where('is_admin', true)->pluck('id');
        }

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $administratorIds = $administratorIds->merge(
                DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'admin')
                    ->where('model_has_roles.model_type', User::class)
                    ->pluck('model_has_roles.model_id')
            );
        }

        foreach ($administratorIds->unique() as $userId) {
            $alreadyMigrated = DB::table('platform_role_assignments')
                ->where('user_id', $userId)
                ->where('role', 'platform_administrator')
                ->whereNull('revoked_at')
                ->exists();

            if ($alreadyMigrated) {
                continue;
            }

            DB::table('platform_role_assignments')->insert([
                'user_id' => $userId,
                'role' => 'platform_administrator',
                'reason' => 'Migrated from the Larafast administrator flag or role.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
