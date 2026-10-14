<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['app.url'=>'http://127.0.0.1:8033']);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8033');
$renderer = app(App\Domain\Communications\Services\ClientNotificationEmail::class);
$variables = ['client_name'=>'Sarah', 'business_name'=>'Juniper House', 'service_name'=>'Cut & finish', 'staff_name'=>'Avery', 'location_name'=>'Juniper · Downtown', 'appointment_date'=>'17 October 2026', 'appointment_time'=>'17:00', 'time_zone'=>'America/Toronto', 'booking_reference'=>'BK-1042', 'location_address'=>'18 Example Lane, Toronto, ON M5V 1A1', 'arrival_instructions'=>'Suite 2, second floor. Entrance beside the courtyard. Use the rear parking lot.', 'directions_link'=>'https://www.google.com/maps/dir/?api=1&destination=43.65,-79.38', 'action_link'=>'https://example.test/your-secure-link', 'currency'=>'CAD', 'amount'=>'65.00', 'queue_estimate'=>'15 minutes'];
foreach(App\Domain\Communications\Services\NotificationWorkspaceCatalog::automations() as $type => $row){
    $html = $renderer->render($type, $variables, 'Sample plain-text alternative', preg_replace_callback('/\{\{([a-z_]+)\}\}/', fn($m)=>$variables[$m[1]]??'', App\Domain\Communications\Services\TemplateVariableCatalog::defaults($type)['subject']));
    file_put_contents(__DIR__.'/emails/'.$type.'.html',$html);
}
echo 'Rendered 12 sample emails without provider access.'.PHP_EOL;
