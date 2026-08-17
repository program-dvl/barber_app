<?php

namespace Database\Seeders;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\BusinessPermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            BusinessPermission::findOrCreate($permission->value, 'web');
        }
    }
}
