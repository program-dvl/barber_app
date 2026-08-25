<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->text('token')->nullable()->change();
            $table->unique(['provider', 'account_id'], 'social_accounts_provider_account_unique');
            $table->unique(['user_id', 'provider'], 'social_accounts_user_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropUnique('social_accounts_provider_account_unique');
            $table->dropUnique('social_accounts_user_provider_unique');
            $table->text('token')->nullable(false)->change();
        });
    }
};
