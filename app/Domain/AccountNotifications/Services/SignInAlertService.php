<?php

namespace App\Domain\AccountNotifications\Services;

use App\Domain\AccountNotifications\Models\AccountLoginActivity;
use App\Models\User;
use App\Notifications\NewSignInNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SignInAlertService
{
    public function record(Login $event, Request $request): void
    {
        if (! $event->user instanceof User || ! $event->user->hasVerifiedEmail()) {
            return;
        }

        $mode = (string) config('account-notifications.login_alerts', 'new_device');
        if (! in_array($mode, ['new_device', 'always'], true)) {
            return;
        }

        $user = $event->user;
        $userAgent = Str::limit((string) $request->userAgent(), 1024, '');
        $ipAddress = Str::limit((string) $request->ip(), 45, '');
        $fingerprint = hash_hmac('sha256', Str::lower($userAgent ?: 'unknown-browser'), (string) config('app.key'));
        $deviceLabel = $this->deviceLabel($userAgent);

        $activity = AccountLoginActivity::query()->createOrFirst(
            ['user_id' => $user->getKey(), 'fingerprint' => $fingerprint],
            [
                'device_label' => $deviceLabel,
                'ip_address' => $ipAddress ?: null,
                'user_agent' => $userAgent ?: null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'sign_in_count' => 1,
            ],
        );
        $isNew = $activity->wasRecentlyCreated;

        if (! $isNew) {
            $activity->forceFill([
                'device_label' => $deviceLabel,
                'ip_address' => $ipAddress ?: null,
                'user_agent' => $userAgent ?: null,
                'last_seen_at' => now(),
                'sign_in_count' => $activity->sign_in_count + 1,
            ])->save();
        }

        AccountLoginActivity::query()
            ->where('user_id', $user->getKey())
            ->whereKeyNot($activity->getKey())
            ->where('last_seen_at', '<', now()->subDays((int) config('account-notifications.login_history_days', 180)))
            ->delete();

        if ($mode !== 'always' && ! $isNew) {
            return;
        }

        $membership = $user->memberships()->active()->with('business')->oldest('id')->first();
        $timeZone = $membership?->business?->time_zone ?: config('app.timezone', 'UTC');
        $signedInAt = now()->timezone($timeZone)->format('M j, Y \a\t g:i A T');

        $user->notify(new NewSignInNotification(
            deviceLabel: $deviceLabel,
            ipAddress: $ipAddress ?: 'Unavailable',
            signedInAt: $signedInAt,
            newDevice: $isNew,
            businessId: $membership?->business_id,
        ));

        $activity->forceFill(['last_notified_at' => now()])->save();

    }

    private function deviceLabel(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $platform = match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh'), str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
