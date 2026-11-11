<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->string('terms_version', 40)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_customers', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at']));
    }
};
