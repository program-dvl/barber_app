<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->text('name');
            $table->text('email');
            $table->text('business')->nullable();
            $table->string('industry', 80)->nullable();
            $table->text('message');
            $table->string('delivery_status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_enquiries');
    }
};
