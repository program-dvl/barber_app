<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('business_type');
            $table->string('brand_color', 7)->nullable()->after('cover_image_path');
        });

        Schema::table('onboarding_sessions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('schema_version')->default(2)->after('business_id');
            $table->json('answers')->nullable()->after('completed_steps');
            $table->json('generated_data')->nullable()->after('answers');
            $table->timestamp('personalized_at')->nullable()->after('last_saved_at');
            $table->timestamp('guided_completed_at')->nullable()->after('personalized_at');
        });

        // Accounts that existed before guided onboarding keep their current setup
        // experience. Starter data is only generated after a new owner explicitly
        // completes the new review screen.
        DB::table('onboarding_sessions')->update([
            'schema_version' => 1,
            'guided_completed_at' => DB::raw('COALESCE(published_at, last_saved_at, created_at)'),
        ]);

        $now = now();
        DB::table('businesses')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('onboarding_sessions')
                ->whereColumn('onboarding_sessions.business_id', 'businesses.id'))
            ->select(['id', 'created_at', 'updated_at', 'configuration_published_at'])
            ->orderBy('id')
            ->chunkById(250, function ($businesses) use ($now): void {
                DB::table('onboarding_sessions')->insertOrIgnore($businesses->map(fn ($business): array => [
                    'business_id' => $business->id,
                    'schema_version' => 1,
                    'current_step' => $business->configuration_published_at ? 'publish' : 'business_details',
                    'completed_steps' => json_encode([]),
                    'answers' => null,
                    'generated_data' => null,
                    'started_at' => $business->created_at ?? $now,
                    'last_saved_at' => $business->updated_at ?? $now,
                    'personalized_at' => null,
                    'guided_completed_at' => $business->configuration_published_at ?? $business->updated_at ?? $business->created_at ?? $now,
                    'previewed_at' => null,
                    'published_at' => $business->configuration_published_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table): void {
            $table->dropColumn(['schema_version', 'answers', 'generated_data', 'personalized_at', 'guided_completed_at']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['description', 'brand_color']);
        });
    }
};
