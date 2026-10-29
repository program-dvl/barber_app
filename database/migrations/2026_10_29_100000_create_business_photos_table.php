<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('path');
            $table->string('caption', 180)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->uuid('idempotency_key');
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'deleted_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_photos');
    }
};
