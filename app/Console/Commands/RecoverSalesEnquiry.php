<?php

namespace App\Console\Commands;

use App\Models\SalesEnquiry;
use App\Services\DeliverSalesEnquiry;
use Illuminate\Console\Command;

class RecoverSalesEnquiry extends Command
{
    protected $signature = 'sales:enquiries {reference?} {--retry : Retry after checking provider delivery records} {--delivery-checked : Confirm the original message was not delivered}';

    protected $description = 'List unresolved sales enquiries or safely retry one after checking delivery';

    public function handle(DeliverSalesEnquiry $delivery): int
    {
        if (! $this->argument('reference')) {
            $this->table(['Reference', 'Status', 'Created'], SalesEnquiry::where('delivery_status', '!=', 'sent')->oldest()->limit(100)->get()->map(fn ($item) => [$item->reference, $item->delivery_status, $item->created_at->toIso8601String()])->all());

            return self::SUCCESS;
        }
        $item = SalesEnquiry::where('reference', $this->argument('reference'))->first();
        if (! $item) {
            $this->error('Enquiry not found.');

            return self::FAILURE;
        }
        $this->info('Delivery status: '.$item->delivery_status);
        if (! $this->option('retry')) {
            return self::SUCCESS;
        }
        if (! $this->option('delivery-checked') || $item->delivery_status === 'sent' || $item->updated_at->isAfter(now()->subMinutes(10))) {
            $this->error('Retry requires an unsent enquiry older than ten minutes and --delivery-checked after confirming non-delivery with the mail provider.');

            return self::FAILURE;
        }
        $claimed = SalesEnquiry::whereKey($item->id)->where('delivery_status', $item->delivery_status)->where('updated_at', $item->updated_at)->update(['delivery_status' => 'pending', 'updated_at' => now()]);
        if (! $claimed) {
            return self::FAILURE;
        }
        $delivery->send($item->fresh());
        $sent = $item->fresh()->delivery_status === 'sent';
        $this->info($sent ? 'Message sent.' : 'Delivery still needs attention. The enquiry remains saved.');

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
