<?php

use App\Domain\HelpCenter\HelpCatalog;
use App\Domain\HelpCenter\HelpSearch;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$queries = ['How do I add another location?', 'staff', 'employee', 'add worker', 'How do I invite staff?', 'locaton', 'service not available for booking', 'no time slots', 'cancel subscription', 'forgot password', 'refund payment', 'stock inventory', 'appointment reminders'];
$results = [];
foreach ($queries as $query) {
    $search = new HelpSearch(new HelpCatalog);
    $start = hrtime(true);
    $matches = $search->query($query, 8);
    $results[] = ['question' => $query, 'milliseconds' => round((hrtime(true) - $start) / 1e6, 2), 'first_result' => $matches[0]['slug'] ?? null];
}
echo json_encode(['scope' => 'Local PHP in-process search; fresh catalogue per query. No HTTP, provider, target topology or load certification.', 'articles' => count((new HelpCatalog)->articles()), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
