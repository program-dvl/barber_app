<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalog_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->uuid('command_key');
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('service_id');
            $table->timestamp('created_at');
            $table->unique(['business_id', 'command_key']);
            $table->foreign(['service_id', 'business_id'], 'catalog_command_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_catalog_commands');
    }
};
