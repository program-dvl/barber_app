<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\Jobs\SendMessageEmail;
use App\Notifications\BookingMessageNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SweepMarketplaceMessageEmails extends Command
{
    protected $signature = 'marketplace:message-email-sweep';

    protected $description = 'Dispatch unsent message alerts; never replay accepted or uncertain mail';

    public function handle(): int
    {
        if (! config('marketplace.message_email_enabled')) {
            $this->info('Message email alerts are disabled. Saved messages are unchanged.');

            return self::SUCCESS;
        }
        if (! Schema::hasColumn('marketplace_messages', 'email_notification_id')) {
            $this->info('Message email migration has not been applied.');

            return self::SUCCESS;
        }
        DB::table('email_notification_deliveries')->where('notification_type', BookingMessageNotification::class)->where('status', 'sending')->where('sending_at', '<', now()->subMinutes(15))->update(['status' => 'unknown', 'last_error_code' => 'mail_worker_interrupted']);
        $query = DB::table('marketplace_messages as m')->join('email_notification_deliveries as d', 'd.notification_id', '=', 'm.email_notification_id')->where('d.status', 'queued')->select('m.id');
        $count = 0;
        foreach ($query->lazyById(100, 'm.id', 'id') as $row) {
            SendMessageEmail::dispatch($row->id);
            $count++;
        }
        $this->info("Dispatched {$count} pending message alerts. Historical and uncertain deliveries were not replayed.");

        return self::SUCCESS;
    }
}
