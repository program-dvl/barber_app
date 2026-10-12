<?php

namespace App\Support\Legal;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class PrivacyNotice
{
    /** @return list<string> */
    public function blockers(): array
    {
        $blockers = [];
        foreach (['operator_name', 'operator_address', 'privacy_email', 'effective_at', 'approval_reference', 'processing_countries', 'providers.hosting', 'providers.storage', 'providers.account_email'] as $field) {
            if (! filled(config('privacy.'.$field))) {
                $blockers[] = 'Missing approved '.$field;
            }
        }
        if (config('communications.transport_mode') === 'live' && config('communications.live_send_enabled')) {
            foreach (['providers.client_email', 'providers.sms'] as $field) {
                if (! filled(config('privacy.'.$field))) {
                    $blockers[] = 'Missing approved '.$field.' for live communications';
                }
            }
        }
        if (! filter_var(config('privacy.privacy_email'), FILTER_VALIDATE_EMAIL)) {
            $blockers[] = 'Privacy email must be a valid monitored address';
        }
        $url = config('privacy.canonical_url');
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_PATH) !== '/privacy-policy' || parse_url($url, PHP_URL_QUERY)
            || parse_url($url, PHP_URL_FRAGMENT) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)) {
            $blockers[] = 'Canonical must be an HTTPS privacy-policy URL without credentials, query or fragment';
        }
        $date = config('privacy.effective_at');
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || ! strtotime($date) || date('Y-m-d', strtotime($date)) !== $date) {
            $blockers[] = 'Effective date must be a valid ISO date';
        } elseif (CarbonImmutable::parse($date, 'Asia/Kolkata')->startOfDay()->isFuture()) {
            $blockers[] = 'Effective date has not been reached';
        }
        if (! config('privacy.approved')) {
            $blockers[] = 'This policy version has not been approved';
        }
        if (! config('privacy.operations_verified')) {
            $blockers[] = 'Provider inventory, rights intake, retention and transfer review are not verified';
        }

        return $blockers;
    }

    public function isPublished(): bool
    {
        return $this->blockers() === [];
    }

    /** @return array{html:string,sections:list<array{id:string,label:string}>} */
    public function content(): array
    {
        $markdown = file_get_contents(resource_path('markdown/policy.md'));
        abort_if($markdown === false, 500);
        // Display configured product windows, not fixed promises or erasure deadlines.
        $markdown = str_replace('{{termination_export_days}}', (string) max(0, (int) config('billing.export_days_after_termination')), $markdown);
        $markdown = str_replace('{{login_history_days}}', (string) max(30, min(3650, (int) config('account-notifications.login_history_days'))), $markdown);
        $sections = [];
        $markdown = preg_replace_callback('/^## (.+)$/m', function ($match) use (&$sections) {
            $id = Str::slug($match[1]);
            $sections[] = ['id' => $id, 'label' => $match[1]];

            return '<h2 id="'.$id.'" tabindex="-1">'.e($match[1]).'</h2>';
        }, $markdown);

        $html = Str::markdown($markdown, ['allow_unsafe_links' => false]);
        $html = str_replace('{{privacy_email}}', e(config('privacy.privacy_email')), $html);

        return ['html' => $html, 'sections' => $sections];
    }
}
