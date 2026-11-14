<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\Launch\LaunchArea;
use App\Domain\Marketplace\Launch\LaunchSignup;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LaunchUpdatesController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['area' => ['required', 'string', 'min:2', 'max:150'], 'email' => ['required', 'email:rfc', 'max:254'], 'consent' => ['required', 'accepted']]);
        $email = mb_strtolower(trim($data['email']));
        $hash = hash_hmac('sha256', $email, config('app.key'));
        $area = LaunchArea::normalize($data['area']);
        $key = 'mp-launch:'.$hash.LaunchArea::key($area);
        $receiptKey = 'marketplace_launch_receipts.'.hash('sha256', $hash.LaunchArea::key($area));
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $previous = $request->session()->get($receiptKey);

            // A normal resend wait is informational; it must not claim a new email was sent.
            return back()->with('launchUpdate', [
                'state' => $previous['state'] ?? 'waiting',
                'retryAt' => ($previous['state'] ?? '') === 'subscribed' ? 0 : now()->addSeconds(RateLimiter::availableIn($key))->getTimestampMs(),
                'reference' => $previous['reference'] ?? null,
            ]);
        }
        $lock = Cache::lock('mp-launch-enroll:'.$hash, 30);
        if (! $lock->get()) {
            return back()->with('launchUpdate', ['state' => 'waiting', 'retryAt' => now()->addSeconds(30)->getTimestampMs()]);
        }
        try {
            RateLimiter::hit($key, 60);
            try {
                $receipt = app(LaunchSignup::class)->subscribe($email, $hash, $area);
            } catch (\Throwable $e) {
                // Own missing schema/storage/configuration faults; never leave a raw 500 or a fake send receipt.
                $reference = (string) Str::ulid();
                Log::warning('Marketplace area signup unavailable', ['reference' => $reference, 'exception_type' => class_basename($e)]);
                $receipt = ['state' => 'unavailable', 'retryAt' => now()->addMinute()->getTimestampMs(), 'reference' => $reference];
            }
            $request->session()->put($receiptKey, $receipt);

            return back()->with('launchUpdate', $receipt);
        } finally {
            $lock->release();
        }
    }

    public function show(string $token, Request $request)
    {
        $unsubscribe = $request->routeIs('marketplace.launch.unsubscribe');
        $row = DB::table('marketplace_launch_subscriptions')->where($unsubscribe ? 'unsubscribe_hash' : 'confirm_hash', hash('sha256', $token))->first();
        $valid = $row && ($unsubscribe || (! $row->unsubscribed_at && CarbonImmutable::parse($row->confirm_expires_at)->isFuture()));

        return $this->page(['valid' => (bool) $valid, 'mode' => $unsubscribe ? 'unsubscribe' : 'confirm', 'area' => $valid ? $row->area : null, 'token' => $valid ? $token : null, 'done' => $unsubscribe ? (bool) $row?->unsubscribed_at : (bool) $row?->confirmed_at]);
    }

    public function confirm(string $token)
    {
        $changed = DB::table('marketplace_launch_subscriptions')->where('confirm_hash', hash('sha256', $token))->where('confirm_expires_at', '>', now())->whereNull('unsubscribed_at')->whereNull('confirmed_at')->update(['confirmed_at' => now(), 'subscribed_at' => now(), 'subscription_method' => 'email-confirmation', 'confirmation_token' => null, 'updated_at' => now()]);
        $confirmed = $changed || DB::table('marketplace_launch_subscriptions')->where('confirm_hash', hash('sha256', $token))->whereNotNull('confirmed_at')->whereNull('unsubscribed_at')->exists();

        return $this->page(['mode' => 'confirm', 'valid' => (bool) $confirmed, 'done' => (bool) $confirmed]);
    }

    public function unsubscribe(string $token)
    {
        $row = DB::table('marketplace_launch_subscriptions')->where('unsubscribe_hash', hash('sha256', $token))->first();
        if ($row) {
            RateLimiter::clear('mp-launch:'.$row->email_hash.$row->area_key);
        }
        $changed = DB::table('marketplace_launch_subscriptions')->where('unsubscribe_hash', hash('sha256', $token))->update(['unsubscribed_at' => now(), 'confirm_hash' => null, 'confirmation_token' => null, 'updated_at' => now()]);

        return $this->page(['mode' => 'unsubscribe', 'valid' => (bool) $changed, 'done' => (bool) $changed]);
    }

    private function page(array $props)
    {
        request()->attributes->set('force_noindex', true);

        return Inertia::render('Marketplace/LaunchConfirmation', $props)->toResponse(request())
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive')->header('Referrer-Policy', 'no-referrer');
    }
}
