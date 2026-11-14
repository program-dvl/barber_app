<?php

namespace App\Domain\Marketplace\Launch;

use App\Mail\Marketplace\LaunchUpdate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LaunchSignup
{
    public function subscribe(string $email, string $hash, string $area): array
    {
        $row = DB::table('marketplace_launch_subscriptions')->where('email_hash', $hash)->where('area_key', LaunchArea::key($area))->first();
        $active = $row?->subscribed_at && ! $row->unsubscribed_at;
        if ($active) {
            return ['state' => 'subscribed', 'retryAt' => 0];
        }
        $id = $row?->id ?? (string) Str::ulid();
        $unsubscribe = hash_hmac('sha256', 'launch-unsubscribe:'.$id, config('app.key'));
        DB::table('marketplace_launch_subscriptions')->updateOrInsert(['email_hash' => $hash, 'area_key' => LaunchArea::key($area)], [
            'id' => $id, 'email' => Crypt::encryptString($email), 'area' => $area,
            'confirm_hash' => null, 'confirmation_token' => null, 'confirm_expires_at' => null,
            'unsubscribe_hash' => hash('sha256', $unsubscribe),
            'consent_version' => 'area-launch-v2-direct', 'consented_at' => now(),
            'subscribed_at' => now(), 'subscription_method' => 'direct-opt-in', 'unsubscribed_at' => null,
            'attempted_at' => null, 'notified_at' => null, 'welcome_failure_reference' => null,
            'created_at' => $row?->created_at ?? now(), 'updated_at' => now(),
        ]);
        // Welcome mail is informational. Its delivery never gates the saved subscription.
        // Limit it per recipient across areas so repeated signups cannot trigger repeated welcomes.
        $welcomeKey = 'mp-launch-welcome:'.$hash;
        if (! RateLimiter::tooManyAttempts($welcomeKey, 1)) {
            RateLimiter::hit($welcomeKey, 86400);
            try {
                Mail::to($email)->send(new LaunchUpdate($area, url('/marketplace'), route('marketplace.launch.unsubscribe', $unsubscribe), welcome: true));
                DB::table('marketplace_launch_subscriptions')->where('id', $id)->update(['welcome_sent_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                $reference = (string) Str::ulid();
                DB::table('marketplace_launch_subscriptions')->where('id', $id)->update(['welcome_failure_reference' => $reference, 'updated_at' => now()]);
                Log::warning('Marketplace welcome email needs delivery review', ['reference' => $reference, 'transport' => config('mail.default'), 'exception_type' => class_basename($e)]);
            }
        }

        return ['state' => 'subscribed', 'retryAt' => 0];
    }
}
