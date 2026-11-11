<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createOrResume('marketplace_geographies', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('country', 2);
            $t->string('region', 60);
            $t->string('city', 100);
            $t->string('name');
            $t->string('source');
            $t->string('reviewer');
            $t->timestamp('reviewed_at');
            $t->timestamps();
            $t->unique(['country', 'region', 'city'], 'mp_geo_path_unique');
        });
        $this->createOrResume('marketplace_location_geographies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained('businesses');
            $t->foreignId('location_id')->unique()->constrained('locations');
            $t->foreignId('geography_id')->constrained('marketplace_geographies');
            $t->string('address_hash', 64);
            $t->foreignId('reviewed_by')->constrained('users');
            $t->timestamp('address_public_at');
            $t->timestamps();
            $t->foreign(['location_id', 'business_id'])->references(['id', 'business_id'])->on('locations');
        });
        $this->createOrResume('marketplace_service_concepts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained('businesses');
            $t->foreignId('service_id')->constrained('services');
            $t->string('concept', 80);
            $t->string('source', 40);
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['service_id', 'concept']);
            $t->foreign(['service_id', 'business_id'])->references(['id', 'business_id'])->on('services');
        });
        $this->createOrResume('marketplace_projection_work', function (Blueprint $t) {
            $t->foreignId('business_id')->primary()->constrained('businesses');
            $t->unsignedBigInteger('generation')->default(1);
            $t->unsignedBigInteger('projected_generation')->default(0);
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('retry_at')->nullable()->index();
            $t->timestamp('first_listed_at')->nullable();
            $t->string('failure_reference')->nullable();
            $t->timestamps();
        });
        $this->createOrResume('marketplace_public_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained('businesses');
            $t->foreignId('location_id')->constrained('locations');
            $t->foreignId('service_id')->constrained('services');
            $t->foreignId('geography_id')->nullable()->constrained('marketplace_geographies');
            $t->string('slug');
            $t->string('business_name');
            $t->string('service_name');
            $t->string('address', 1000)->nullable();
            $t->string('currency', 3);
            $t->unsignedBigInteger('price_minor');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedInteger('review_count')->default(0);
            $t->decimal('review_average', 3, 2)->nullable();
            $t->unsignedBigInteger('generation');
            $t->json('facts');
            $t->string('content_hash', 64);
            $t->timestamp('materially_changed_at');
            $t->timestamp('first_listed_at');
            $t->timestamp('valid_until')->index();
            $t->timestamps();
            $t->unique(['business_id', 'location_id', 'service_id'], 'mp_offer_path_unique');
            $t->index(['geography_id', 'business_id'], 'mp_offer_geo_business');
            $t->index(['currency', 'price_minor']);
            $t->index(['latitude', 'longitude']);
            $t->foreign(['location_id', 'business_id'])->references(['id', 'business_id'])->on('locations');
            $t->foreign(['service_id', 'business_id'])->references(['id', 'business_id'])->on('services');
        });
        $this->createOrResume('marketplace_offer_terms', function (Blueprint $t) {
            $t->foreignId('offer_id')->constrained('marketplace_public_offers')->cascadeOnDelete();
            $t->string('kind', 20);
            $t->string('term', 80);
            $t->primary(['offer_id', 'kind', 'term']);
            $t->index(['kind', 'term', 'offer_id']);
        });
        $this->createOrResume('marketplace_seo_pages', function (Blueprint $t) {
            $t->id();
            $t->string('path')->unique();
            $t->string('kind', 20);
            $t->foreignId('business_id')->nullable()->constrained('businesses');
            $t->foreignId('geography_id')->nullable()->constrained('marketplace_geographies');
            $t->string('term', 80)->nullable();
            $t->string('title');
            $t->text('context')->nullable();
            $t->string('status', 20)->default('draft');
            $t->string('approved_hash', 64)->nullable();
            $t->string('inventory_hash', 64)->nullable();
            $t->string('reviewer')->nullable();
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('low_supply_since')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('materially_changed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'kind', 'id']);
        });
        $this->createOrResume('marketplace_booking_attributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained('businesses');
            $t->foreignId('flow_id')->unique()->constrained('public_booking_flows');
            $t->foreignId('appointment_id')->nullable()->unique()->constrained('appointments');
            $t->foreignId('root_appointment_id')->nullable()->constrained('appointments');
            $t->string('policy_version', 30);
            $t->string('entry', 30);
            $t->string('first_source', 30)->default('unknown');
            $t->string('last_source', 30)->default('unknown');
            $t->string('owner_source', 30)->default('unknown');
            $t->string('confidence', 30)->default('unknown');
            $t->json('evidence')->nullable();
            $t->timestamp('observed_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->foreign(['appointment_id', 'business_id'], 'mp_attribution_appointment_tenant_fk')->references(['id', 'business_id'])->on('appointments');
            $t->index(['business_id', 'confirmed_at']);
        });
        $this->createOrResume('marketplace_search_performance', function (Blueprint $t) {
            $t->id();
            $t->string('property');
            $t->date('day');
            $t->string('path');
            $t->unsignedInteger('impressions');
            $t->unsignedInteger('clicks');
            $t->decimal('position', 10, 4)->nullable();
            $t->string('source');
            $t->string('reviewer');
            $t->timestamps();
            $t->unique(['property', 'day', 'path'], 'mp_search_aggregate_unique');
        });
    }

    private function createOrResume(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);

            return;
        }

        // MySQL commits CREATE TABLE before subsequent ALTER statements. Resume
        // a failed DDL run by adding only missing constraints and indexes.
        $blueprint = new Blueprint(Schema::getConnection(), $table, $definition);
        $columns = Schema::getColumnListing($table);
        foreach ($blueprint->getColumns() as $column) {
            if (! in_array($column->name, $columns, true)) {
                throw new RuntimeException("Cannot resume {$table}: expected column {$column->name} is missing.");
            }
            foreach (['unique', 'index', 'primary'] as $kind) {
                if ($column->{$kind}) {
                    $blueprint->{$kind}([$column->name], is_string($column->{$kind}) ? $column->{$kind} : null);
                }
            }
        }
        foreach ($blueprint->getCommands() as $command) {
            if ($command->name === 'foreign') {
                $exists = collect(Schema::getForeignKeys($table))->contains(fn ($key) => $key['columns'] === $command->columns
                    && $key['foreign_table'] === $command->on
                    && $key['foreign_columns'] === (array) $command->references
                );
                if (! $exists) {
                    Schema::table($table, function (Blueprint $repair) use ($command) {
                        $foreign = $repair->foreign($command->columns, $command->index)
                            ->references($command->references)->on($command->on);
                        if ($command->onDelete) {
                            $foreign->onDelete($command->onDelete);
                        }
                        if ($command->onUpdate) {
                            $foreign->onUpdate($command->onUpdate);
                        }
                    });
                }
            } elseif (in_array($command->name, ['index', 'unique', 'primary'], true)) {
                $exists = collect(Schema::getIndexes($table))->contains(fn ($index) => $index['columns'] === $command->columns
                    && ($command->name !== 'unique' || $index['unique'])
                    && ($command->name !== 'primary' || $index['primary'])
                );
                if (! $exists) {
                    Schema::table($table, fn (Blueprint $repair) => $repair->{$command->name}($command->columns, $command->index));
                }
            }
        }
    }

    public function down(): void
    {
        // Deployment recovery disables flags; it never rolls back visit/attribution history.
        foreach (['marketplace_search_performance', 'marketplace_booking_attributions', 'marketplace_seo_pages', 'marketplace_offer_terms', 'marketplace_public_offers', 'marketplace_projection_work', 'marketplace_service_concepts', 'marketplace_location_geographies', 'marketplace_geographies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
