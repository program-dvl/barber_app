<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_article_feedback', function (Blueprint $table): void {
            $table->id();
            $table->string('article_slug', 120);
            $table->date('revision');
            $table->char('visitor_hash', 64);
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['article_slug', 'revision', 'visitor_hash'], 'help_feedback_revision_visitor_unique');
            $table->index(['article_slug', 'revision', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_feedback');
    }
};
