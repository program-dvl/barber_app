<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('resumes a partial growth migration without deleting existing rows and restores missing attribution keys', function () {
    $geoId = DB::table('marketplace_geographies')->insertGetId(['public_id' => (string) Str::ulid(), 'country' => 'ca', 'region' => 'on', 'city' => 'retained-city', 'name' => 'Retained City', 'source' => 'synthetic fixture', 'reviewer' => 'Synthetic reviewer', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    Schema::table('marketplace_booking_attributions', function (Blueprint $table) {
        $table->dropForeign(['appointment_id', 'business_id']);
        $table->dropUnique(['flow_id']);
        $table->dropUnique(['appointment_id']);
        $table->dropIndex(['expires_at']);
        $table->dropIndex(['business_id', 'confirmed_at']);
    });
    Schema::drop('marketplace_search_performance');
    $migration = require database_path('migrations/2026_11_08_000005_create_marketplace_local_growth.php');
    $migration->up();
    $migration->up();
    expect(DB::table('marketplace_geographies')->where('id', $geoId)->value('name'))->toBe('Retained City');
    expect(Schema::hasTable('marketplace_search_performance'))->toBeTrue();
    $keys = collect(Schema::getForeignKeys('marketplace_booking_attributions'));
    expect($keys->contains(fn ($key) => $key['columns'] === ['appointment_id', 'business_id'] && $key['foreign_table'] === 'appointments'))->toBeTrue();
    $indexes = collect(Schema::getIndexes('marketplace_booking_attributions'));
    foreach ([['flow_id'], ['appointment_id']] as $columns) {
        expect($indexes->contains(fn ($index) => $index['columns'] === $columns && $index['unique']))->toBeTrue();
    }
    foreach ([['expires_at'], ['business_id', 'confirmed_at']] as $columns) {
        expect($indexes->contains(fn ($index) => $index['columns'] === $columns))->toBeTrue();
    }
});
