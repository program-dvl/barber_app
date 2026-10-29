<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('author_initials', 12);
            $table->boolean('public_consent')->default(false);
            $table->json('public_consent_evidence');
            $table->string('request_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamp('public_consent_withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'appointment_id']);
            $table->foreign(['appointment_id', 'business_id'])->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['client_id', 'business_id'])->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'public_consent', 'submitted_at']);
            $table->index(['business_id', 'public_consent', 'public_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reviews');
    }
};
