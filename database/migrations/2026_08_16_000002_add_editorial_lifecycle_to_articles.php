<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('status', 24)->default('draft')->after('active')->index();
            $table->string('excerpt', 320)->nullable()->after('content');
            $table->string('topic', 64)->nullable()->after('excerpt')->index();
            $table->string('content_owner')->nullable()->after('topic');
            $table->timestamp('published_at')->nullable()->after('content_owner')->index();
            $table->timestamp('materially_updated_at')->nullable()->after('published_at');
            $table->timestamp('reviewed_at')->nullable()->after('materially_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['status', 'excerpt', 'topic', 'content_owner', 'published_at', 'materially_updated_at', 'reviewed_at']);
        });
    }
};
