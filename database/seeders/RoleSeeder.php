<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->warn('Business roles are provisioned per Business; global User roles are intentionally not seeded.');
    }
}
