<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteGoogleRegistration;
use App\Domain\Billing\Services\OwnerOnboardingService;
use App\Domain\Billing\Services\PublicPricingCatalog;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialiteController extends Controller
{
    public const CONTEXT_SESSION_KEY = 'auth.google.context';

    public const REGISTRATION_SESSION_KEY = 'auth.google.pending_registration';

    private const REGISTRATION_TTL_MINUTES = 10;

    public function redirect(Request $request, PublicPricingCatalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'intent' => ['nullable', 'in:login,register'],
            'plan' => ['nullable', 'string', 'max:32'],
            'interval' => ['nullable', 'string', 'max:16'],
        ]);

        if (! $this->isConfigured()) {
            return $this->redirectWithError($request, 'Google sign-in is temporarily unavailable. Please use your email and password.');
        }

        $intent = $data['intent'] ?? 'login';
        $selection = $intent === 'register'
            ? $catalog->validSelection($data['plan'] ?? null, $data['interval'] ?? null)
            : null;

        $request->session()->put(self::CONTEXT_SESSION_KEY, [
            'intent' => $intent,
            'selection' => $selection,
            'started_at' => now()->timestamp,
        ]);
        $request->session()->forget(self::REGISTRATION_SESSION_KEY);

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, OwnerOnboardingService $onboarding): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->redirectWithError($request, 'Your Google sign-in session expired. Please try again.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectWithError($request, 'Google could not complete sign-in. Please try again or use your email and password.');
        }

        $accountId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $name = trim((string) $googleUser->getName());
        $emailVerified = filter_var(
            data_get($googleUser->user, 'email_verified', data_get($googleUser->user, 'verified_email', false)),
            FILTER_VALIDATE_BOOL,
        );

        if ($accountId === '' || $email === '' || ! $emailVerified) {
            return $this->redirectWithError($request, 'ClipperDesk requires a Google account with a verified email address.');
        }

        try {
            $user = $this->resolveExistingUser($accountId, $email);
        } catch (ValidationException $exception) {
            return $this->redirectWithError($request, $exception->errors()['google'][0]);
        }

        if ($user) {
            // Repair a previously interrupted verified-owner bootstrap before the user lands on the dashboard.
            if ($user->hasVerifiedEmail()) {
                $onboarding->complete($user);
            }
            $response = $this->authenticate($request, $user);
            $request->session()->forget([self::CONTEXT_SESSION_KEY, self::REGISTRATION_SESSION_KEY]);

            return $response;
        }

        $context = $request->session()->get(self::CONTEXT_SESSION_KEY, []);
        $request->session()->put(self::REGISTRATION_SESSION_KEY, [
            'account_id' => $accountId,
            'email' => $email,
            'name' => $name !== '' ? Str::limit($name, 255, '') : Str::before($email, '@'),
            'selection' => $context['selection'] ?? null,
            'issued_at' => now()->timestamp,
        ]);

        return redirect()->route('register');
    }

    public function completeRegistration(
        Request $request,
        CompleteGoogleRegistration $registration,
    ): RedirectResponse {
        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            return redirect()->route('register')->withErrors([
                'google' => 'Your Google signup session expired. Please continue with Google again.',
            ]);
        }

        $user = $registration->handle($pending, $request->all());

        $request->session()->forget([self::CONTEXT_SESSION_KEY, self::REGISTRATION_SESSION_KEY]);
        $response = $this->authenticate($request, $user);

        return $response;
    }

    /** @return array<string, mixed>|null */
    public static function sharedPendingRegistration(Request $request): ?array
    {
        $pending = $request->session()->get(self::REGISTRATION_SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['issued_at']) || now()->timestamp - (int) $pending['issued_at'] > self::REGISTRATION_TTL_MINUTES * 60) {
            $request->session()->forget(self::REGISTRATION_SESSION_KEY);

            return null;
        }

        return [
            'name' => $pending['name'],
            'email' => $pending['email'],
            'selected_plan' => data_get($pending, 'selection.plan'),
            'selected_interval' => data_get($pending, 'selection.interval'),
            'expires_at' => now()->createFromTimestamp((int) $pending['issued_at'])
                ->addMinutes(self::REGISTRATION_TTL_MINUTES)
                ->toIso8601String(),
        ];
    }

    private function resolveExistingUser(string $accountId, string $email): ?User
    {
        return DB::transaction(function () use ($accountId, $email): ?User {
            $account = SocialAccount::query()
                ->with('user')
                ->where('provider', 'google')
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if ($account) {
                $user = $account->user;

                if (! $user->hasVerifiedEmail() && hash_equals(Str::lower($user->email), $email)) {
                    $user->markEmailAsVerified();
                    event(new Verified($user));
                }

                return $user;
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return null;
            }

            $otherGoogleAccount = $user->socialAccounts()
                ->where('provider', 'google')
                ->lockForUpdate()
                ->first();

            if ($otherGoogleAccount && ! hash_equals($otherGoogleAccount->account_id, $accountId)) {
                throw ValidationException::withMessages([
                    'google' => 'This ClipperDesk account is already linked to another Google account. Use email sign-in or reset your password.',
                ]);
            }

            if (! $otherGoogleAccount) {
                $user->socialAccounts()->create([
                    'provider' => 'google',
                    'account_id' => $accountId,
                    'token' => null,
                ]);
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                event(new Verified($user));
            }

            return $user;
        }, 3);
    }

    /** @return array<string, mixed>|null */
    private function pendingRegistration(Request $request): ?array
    {
        self::sharedPendingRegistration($request);

        $pending = $request->session()->get(self::REGISTRATION_SESSION_KEY);

        return is_array($pending) ? $pending : null;
    }

    private function authenticate(Request $request, User $user): RedirectResponse
    {
        if (Features::enabled(Features::twoFactorAuthentication())
            && filled($user->two_factor_secret)
            && filled($user->two_factor_confirmed_at)) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    private function redirectWithError(Request $request, string $message): RedirectResponse
    {
        $intent = data_get($request->session()->get(self::CONTEXT_SESSION_KEY), 'intent', $request->string('intent')->toString());
        $route = $intent === 'register' ? 'register' : 'login';

        return redirect()->route($route)->withErrors(['google' => $message]);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
