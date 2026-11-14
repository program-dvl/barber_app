<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\Launch\LaunchArea;
use App\Domain\Marketplace\LocalGrowth\Services\PublicOfferQuery;
use App\Mail\Marketplace\LaunchUpdate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Explicit operator review avoids treating ambiguous typed city names as geography. */
class SendMarketplaceLaunchUpdate extends Command
{
    protected $signature = 'marketplace:launch-update {area : Exact requested city, region and country} {slug : Eligible business booking slug} {--send : Deliver after checking the preview} {--location-reviewed : Attest that this business serves the requested area}';

    protected $description = 'Preview a one-time, subscribed area launch update; sending requires an operator location review';

    public function handle(PublicOfferQuery $offers): int
    {
        $offer = $offers->query()->where('o.slug', $this->argument('slug'))->first();
        if (! $offer) {
            $this->error('No currently eligible public business has that booking slug. Nothing sent.');

            return self::FAILURE;
        }
        $recipients = fn () => DB::table('marketplace_launch_subscriptions')->where('area_key', LaunchArea::key($this->argument('area')))
            ->whereNotNull('subscribed_at')->whereNull('unsubscribed_at')->whereNull('attempted_at')->where('subscribed_at', '>', now()->subYear());
        $this->info('Business: '.$offer->business_name.' | Address: '.$offer->address);
        $this->info('Requested area: '.$this->argument('area').' | Subscribed requests: '.$recipients()->count());
        if (! $this->option('send')) {
            $this->info('Preview only. Verify the area, country and business location before using --send --location-reviewed.');

            return self::SUCCESS;
        }
        if (! $this->option('location-reviewed')) {
            $this->error('Location review is required. Nothing sent.');

            return self::FAILURE;
        }
        $sent = $uncertain = 0;
        foreach ($recipients()->orderBy('id')->cursor() as $row) {
            // Live eligibility, consent and one-time claim are rechecked for every send.
            if (! $offers->query()->where('o.slug', $this->argument('slug'))->exists()) {
                break;
            }
            $claimed = $recipients()->where('id', $row->id)->update(['attempted_at' => now(), 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $unsubscribe = hash_hmac('sha256', 'launch-unsubscribe:'.$row->id, config('app.key'));
            try {
                Mail::to(Crypt::decryptString($row->email))->send(new LaunchUpdate($row->area, url('/marketplace/salons/'.$offer->slug), route('marketplace.launch.unsubscribe', $unsubscribe), $offer->business_name));
                DB::table('marketplace_launch_subscriptions')->where('id', $row->id)->update(['notified_at' => now(), 'updated_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                // SMTP may have accepted a message before an error. Never resend automatically.
                $uncertain++;
            }
        }
        $this->info("Sent: {$sent}. Delivery requiring operator review: {$uncertain}. No automatic retries.");

        return $uncertain ? self::FAILURE : self::SUCCESS;
    }
}
