<?php

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Business::query()->orderBy('id')->eachById(function (Business $business): void {
            app(BusinessAccessBootstrapper::class)->bootstrap($business);
        });
    }

    public function down(): void
    {
        // Role/permission assignments are live access history; do not remove them on rollback.
    }
};
