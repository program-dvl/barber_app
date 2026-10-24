<?php

use App\Domain\ClientRecords\Models\Client;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BusinessSetupReviewSeeder;
use Database\Seeders\CheckoutWorkspaceReviewSeeder;
use Database\Seeders\ClientWorkspaceReviewSeeder;
use Database\Seeders\FrontDeskDaySeeder;
use Database\Seeders\NotificationWorkspaceReviewSeeder;
use Database\Seeders\ReportsWorkspaceReviewSeeder;
use Database\Seeders\ServiceCatalogReviewSeeder;
use Database\Seeders\WalkInWorkspaceReviewSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Creates a new isolated SQLite fixture; never reads or changes the business database.
$root = dirname(__DIR__, 2);
$directory = $root.'/tmp/help-center';
@mkdir($directory, 0700, true);
$database = $directory.'/help-capture-v2-service-catalog-review-business-setup-review-notification-workspace-review.sqlite';
if (file_exists($database) && ! in_array('--resume', $argv, true)) {
    fwrite(STDERR, "Capture database already exists. Keep it or choose a new capture directory; no data was overwritten.\n");
    exit(1);
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'COMMUNICATIONS_TRANSPORT_MODE' => 'fake', 'COMMUNICATIONS_IMMEDIATE_EVENT_DISPATCH' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
touch($database);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database || ! app()->environment('testing')) {
    throw new RuntimeException('Isolated capture configuration required.');
}
Artisan::call('migrate', ['--force' => true]);
foreach ([FrontDeskDaySeeder::class, WalkInWorkspaceReviewSeeder::class, ServiceCatalogReviewSeeder::class, ClientWorkspaceReviewSeeder::class, CheckoutWorkspaceReviewSeeder::class, ReportsWorkspaceReviewSeeder::class, NotificationWorkspaceReviewSeeder::class, BusinessSetupReviewSeeder::class] as $seeder) {
    if ($seeder === NotificationWorkspaceReviewSeeder::class) {
        $business = Business::query()->where('slug', 'good-hours-demo-tenant')->firstOrFail();
        Client::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Sarah Alexandra Montgomery-Wellington', 'email' => 'sarah@example.test', 'mobile' => '+14155552671'], ['normalized_name' => 'sarah alexandra montgomery-wellington', 'normalized_email' => 'sarah@example.test', 'normalized_mobile' => '+14155552671', 'status' => 'active']);
    }
    if (Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]) !== 0) {
        throw new RuntimeException('Fixture seeding failed: '.$seeder);
    }
}
$password = Str::random(40);
$users = User::query()->get();
foreach ($users as $user) {
    $user->forceFill(['password' => Hash::make($password), 'email_verified_at' => now()])->save();
}
$businesses = Business::query()->get()->keyBy('slug');
foreach ($businesses as $business) {
    $business->update(['name' => $business->slug === 'good-hours-demo-tenant' ? 'Pine & Palm Studio' : 'Atelier Studio']);
}
$workspaces = $businesses->map(fn ($business) => $business->public_id)->all();
file_put_contents($directory.'/private-capture.json', json_encode(['email' => 'owner@pine-palm.example.test', 'password' => $password, 'workspaces' => $workspaces, 'fixture_date' => CarbonImmutable::now('Asia/Kolkata')->next('Monday')->toDateString()], JSON_PRETTY_PRINT));
chmod($directory.'/private-capture.json', 0600);
echo "Isolated synthetic capture database prepared; private browser sign-in stored locally.\n";
