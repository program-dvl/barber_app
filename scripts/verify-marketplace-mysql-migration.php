<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$server = DB::connection('mysql');
$connection = $server->getConfig();
if (! $app->environment('local') || ! in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "This fixture requires local MySQL.\n");
    exit(1);
}
$fixture = 'codex_mpg_migration_'.bin2hex(random_bytes(5));
$created = false;
$failed = false;
try {
    $server->statement('CREATE DATABASE `'.$fixture.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    unset($connection['name'], $connection['read'], $connection['write']);
    $connection['database'] = $fixture;
    $connection['url'] = null;
    config(['database.connections.mpg_migration_test' => $connection, 'marketplace-growth.enabled' => false, 'mail.default' => 'array']);
    DB::setDefaultConnection('mpg_migration_test');
    Schema::clearResolvedInstance('db.schema');
    if (DB::connection()->getName() !== 'mpg_migration_test'
        || DB::connection()->getDatabaseName() !== $fixture
        || DB::selectOne('SELECT DATABASE() AS name')->name !== $fixture) {
        throw new RuntimeException('Synthetic migration connection was not isolated.');
    }
    $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_11_08_000005_create_marketplace_local_growth.php'));
    if (Artisan::call('migrate', ['--database' => 'mpg_migration_test', '--path' => $paths, '--realpath' => true, '--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    $source = file_get_contents(database_path('migrations/2026_11_08_000005_create_marketplace_local_growth.php'));
    $source = str_replace('mp_attribution_appointment_tenant_fk', 'marketplace_booking_attributions_appointment_id_business_id_foreign', $source);
    $reproduction = tempnam(sys_get_temp_dir(), 'mpg-mysql-reproduction-');
    file_put_contents($reproduction, $source);
    try {
        $old = require $reproduction;
    } finally {
        unlink($reproduction);
    }
    try {
        $old->up();
        throw new RuntimeException('Original MySQL failure did not reproduce.');
    } catch (QueryException $error) {
        if (($error->errorInfo[1] ?? null) !== 1059) {
            throw $error;
        }
        echo "Reproduced original MySQL 1059 after partial table creation.\n";
    }
    $id = DB::table('marketplace_geographies')->insertGetId(['public_id' => (string) Str::ulid(), 'country' => 'ca', 'region' => 'on', 'city' => 'preserved-fixture', 'name' => 'Preserved synthetic row', 'source' => 'isolated migration test', 'reviewer' => 'test', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    if (Artisan::call('migrate', ['--database' => 'mpg_migration_test', '--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    if (DB::table('marketplace_geographies')->where('id', $id)->value('name') !== 'Preserved synthetic row') {
        throw new RuntimeException('Existing row was not preserved.');
    }
    $verify = function () {
        $keys = collect(Schema::getForeignKeys('marketplace_booking_attributions'));
        if (! $keys->contains(fn ($key) => $key['columns'] === ['appointment_id', 'business_id'] && $key['foreign_table'] === 'appointments')) {
            throw new RuntimeException('Missing attribution tenant foreign key.');
        }
        $indexes = collect(Schema::getIndexes('marketplace_booking_attributions'));
        foreach ([['flow_id'], ['appointment_id']] as $columns) {
            if (! $indexes->contains(fn ($index) => $index['columns'] === $columns && $index['unique'])) {
                throw new RuntimeException('Missing attribution uniqueness.');
            }
        }
        foreach ([['expires_at'], ['business_id', 'confirmed_at']] as $columns) {
            if (! $indexes->contains(fn ($index) => $index['columns'] === $columns)) {
                throw new RuntimeException('Missing attribution lookup index.');
            }
        }
        foreach (Schema::getTableListing() as $table) {
            if (! str_contains($table, 'marketplace_')) {
                continue;
            }
            foreach ([...Schema::getIndexes($table), ...Schema::getForeignKeys($table)] as $entry) {
                if (strlen($entry['name']) > 64) {
                    throw new RuntimeException('Oversized MySQL identifier.');
                }
            }
        }
    };
    $verify();
    echo "Resumed migration, preserved existing row, restored tenant FK, unique keys and lookup indexes.\n";
    $new = require database_path('migrations/2026_11_08_000005_create_marketplace_local_growth.php');
    $new->up();
    $verify();
    echo "Repeated migration is idempotent.\n";
    $new->down();
    $new->up();
    $verify();
    echo "Fresh MySQL growth migration passes; all Marketplace constraint/index identifiers fit 64 characters.\n";
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'Migration regression failed: '.$error->getMessage().PHP_EOL);
} finally {
    DB::purge('mpg_migration_test');
    if ($created) {
        $server->statement('DROP DATABASE `'.$fixture.'`');
        echo "Removed isolated synthetic migration database.\n";
    }
}
exit($failed ? 1 : 0);
