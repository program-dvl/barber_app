<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_action_links', function (Blueprint $table): void {
            $table->string('short_token_hash', 64)->nullable()->unique();
            $table->text('short_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_action_links', function (Blueprint $table): void {
            $table->dropUnique(['short_token_hash']);
            $table->dropColumn(['short_token_hash', 'short_token']);
        });
    }
};
