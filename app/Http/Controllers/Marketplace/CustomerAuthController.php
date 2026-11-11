<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Services\CustomerConversationReturn;
use App\Http\Controllers\Controller;
use App\Mail\Marketplace\SignInLink;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class CustomerAuthController extends Controller
{
    public function show(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect('/marketplace/account');
        }

        return Inertia::render('Marketplace/SignIn', ['sent' => (bool) $request->session()->get('marketplace_link_sent'), 'confirming' => false, 'retryAt' => $request->session()->get('marketplace_link_retry_at'), 'googleEnabled' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'))]);
    }

    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'marketplace-login:'.hash_hmac('sha256', $email, config('app.key'));
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('marketplace_retry_after', RateLimiter::availableIn($key))->withErrors(['email' => 'Please wait '.RateLimiter::availableIn($key).' seconds before requesting another link.']);
        }
        RateLimiter::hit($key, 60);
        $token = bin2hex(random_bytes(32));
        $request->session()->forget('marketplace_google_pending');
        $request->session()->put('marketplace_link_retry_at', now()->addMinute()->getTimestampMs());
        $id = DB::table('marketplace_login_links')->insertGetId([
            'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            // Use the configured first-party origin, never an arbitrary Host header.
            Mail::to($email)->send(new SignInLink(rtrim(config('app.url'), '/').'/marketplace/sign-in/link/'.$token));
        } catch (\Throwable $exception) {
            DB::table('marketplace_login_links')->where('id', $id)->update(['used_at' => now()]);
            report($exception);

            return back()->withErrors(['email' => 'We couldn’t send your link. Your bookings are unchanged. Please wait a minute and try again, or contact '.config('brand.support_email').'.']);
        }
        $request->session()->put('marketplace_link_sent', true);
        $request->session()->put('marketplace_link_retry_at', now()->addMinute()->getTimestampMs());

        return redirect('/marketplace/sign-in');
    }

    public function preview(Request $request, string $token)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect()->route('marketplace.account');
        }
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $request->session()->put('marketplace_pending_login', $token);

        return redirect('/marketplace/sign-in/confirm');
    }

    public function confirmation(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect()->route('marketplace.account');
        }
        $link = DB::table('marketplace_login_links')->where('token_hash', hash('sha256', (string) $request->session()->get('marketplace_pending_login')))->first();
        $valid = $link && ! $link->used_at && now()->lt($link->expires_at);
        $email = $valid ? explode('@', $link->email) : null;

        return Inertia::render('Marketplace/SignIn', [
            'confirming' => true, 'valid' => (bool) $valid,
            'maskedEmail' => $email ? $link->email : null,
            'needsTerms' => $valid && ! Customer::query()->where('email', $link->email)->where('terms_version', 'marketplace-2026-11-08')->exists(),
        ]);
    }

    public function consume(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect()->route('marketplace.account');
        }
        $customer = DB::transaction(function () use ($request) {
            $link = DB::table('marketplace_login_links')->where('token_hash', hash('sha256', (string) $request->session()->get('marketplace_pending_login')))->lockForUpdate()->first();
            if (! $link || $link->used_at || now()->gte($link->expires_at)) {
                return null;
            }
            $existing = Customer::query()->where('email', $link->email)->first();
            if (! $existing || $existing->terms_version !== 'marketplace-2026-11-08') {
                $request->validate(['accepted' => ['accepted']]);
            }
            DB::table('marketplace_login_links')->where('id', $link->id)->update(['used_at' => now(), 'updated_at' => now()]);
            $customer = Customer::query()->firstOrCreate(['email' => $link->email], ['email_verified_at' => now()]);
            $google = $request->session()->get('marketplace_google_pending');
            if ($google && hash_equals($google['token_hash'] ?? '', $link->token_hash) && $google['email'] === $link->email && $google['expires_at'] > now()->timestamp) {
                $customer->forceFill(['google_id' => $google['id'], 'name' => $customer->name ?: $google['name']])->save();
            }
            if ($customer->terms_version !== 'marketplace-2026-11-08') {
                $customer->forceFill(['terms_version' => 'marketplace-2026-11-08', 'terms_accepted_at' => now()])->save();
            }
            app(AuditWriter::class)->write('marketplace.customer.signed_in', target: $customer, metadata: ['customer_public_id' => $customer->public_id, 'terms_version' => 'marketplace-2026-11-08'], source: 'marketplace_customer');

            return $customer;
        });
        if (! $customer) {
            return redirect('/marketplace/sign-in/confirm')->withErrors(['email' => 'This link has expired or was already used. Request a new sign-in link.']);
        }
        Auth::guard('marketplace')->login($customer, true);
        $request->session()->regenerate();
        $request->session()->forget(['marketplace_pending_login', 'marketplace_link_sent', 'marketplace_google_pending']);

        return redirect(app(CustomerConversationReturn::class)->destination($request));
    }

    public function logout(Request $request)
    {
        Auth::guard('marketplace')->logout();
        $request->session()->forget(['marketplace_pending_login', 'marketplace_link_sent', 'marketplace_google_pending', 'marketplace_conversation_return']);
        $request->session()->regenerate();

        return redirect('/marketplace');
    }
}
