<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\Marketplace\Models\Customer;
use App\Domain\Marketplace\Services\CustomerConversationReturn;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class CustomerGoogleController extends Controller
{
    public function redirect(Request $request)
    {
        if (Auth::guard('marketplace')->check()) {
            return redirect()->route('marketplace.account');
        }
        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret')) || ! filled(config('services.google.redirect'))) {
            return redirect()->route('marketplace.sign-in')->withErrors(['google' => 'Google sign-in is unavailable right now. You can still sign in with an email link.']);
        }
        $request->session()->put(SocialiteController::CONTEXT_SESSION_KEY, ['audience' => 'marketplace', 'started_at' => now()->timestamp]);

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request)
    {
        $context = $request->session()->pull(SocialiteController::CONTEXT_SESSION_KEY);
        try {
            // Socialite checks the OAuth state; never use stateless sign-in.
            $google = Socialite::driver('google')->user();
            if (! $context || ($context['audience'] ?? '') !== 'marketplace' || now()->timestamp - (int) ($context['started_at'] ?? 0) > 900) {
                throw new \RuntimeException('Expired marketplace OAuth context.');
            }
            $id = trim((string) $google->getId());
            $email = mb_strtolower(trim((string) $google->getEmail()));
            $verified = filter_var(data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false)), FILTER_VALIDATE_BOOL);
            if (! $id || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $verified) {
                return $this->failed('Choose a Google account with a verified email address, or use an email link.');
            }
            $customer = Customer::query()->where('google_id', $id)->first();
            if ($customer && $customer->email !== $email) {
                return $this->failed('Your Google email has changed. Sign in with your original email link and contact support to update your account.');
            }
            $customer ??= Customer::query()->where('email', $email)->first();
            if ($customer?->google_id && $customer->google_id !== $id) {
                return $this->failed('This account uses a different Google sign-in. Use an email link to open your account.');
            }
            if ($customer && $customer->terms_version === 'marketplace-2026-11-08') {
                DB::transaction(function () use ($customer, $id) {
                    $customer->forceFill(['google_id' => $id])->save();
                    app(AuditWriter::class)->write('marketplace.customer.google_signed_in', target: $customer, metadata: ['customer_public_id' => $customer->public_id], source: 'marketplace_customer');
                });
                Auth::guard('marketplace')->login($customer, true);
                $request->session()->regenerate();

                return redirect(app(CustomerConversationReturn::class)->destination($request));
            }
            // New customers review terms before their account is created.
            $token = bin2hex(random_bytes(32));
            DB::table('marketplace_login_links')->insert(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
            $request->session()->put('marketplace_pending_login', $token);
            $request->session()->put('marketplace_google_pending', ['token_hash' => hash('sha256', $token), 'id' => $id, 'email' => $email, 'name' => mb_substr(trim((string) $google->getName()), 0, 100), 'expires_at' => now()->addMinutes(15)->timestamp]);

            return redirect()->route('marketplace.sign-in.confirm');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('Google couldn’t complete sign-in. Please try again or use an email link.');
        }
    }

    private function failed(string $message)
    {
        return redirect()->route('marketplace.sign-in')->withErrors(['google' => $message]);
    }
}
