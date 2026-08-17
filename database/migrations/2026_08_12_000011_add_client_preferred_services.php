<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_preferred_services', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();
            $table->primary(['business_id', 'client_id', 'service_id'], 'client_preferred_service_pk');
            $table->foreign(['client_id', 'business_id'], 'client_preferred_service_client_fk')
                ->references(['id', 'business_id'])->on('clients')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'client_preferred_service_service_fk')
                ->references(['id', 'business_id'])->on('services')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_preferred_services');
    }
};
