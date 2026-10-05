<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\PlatformAccess\Models\Business;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationTemplateService
{
    public function __construct(private readonly CommunicationTemplateRenderer $renderer) {}

    public function settings(Business|int $business): CommunicationSetting
    {
        $businessId = $business instanceof Business ? $business->id : $business;
        $locale = $business instanceof Business
            ? ($business->locale ?: 'en-US')
            : (Business::query()->whereKey($businessId)->value('locale') ?: 'en-US');

        return CommunicationSetting::query()->firstOrCreate(['business_id' => $businessId], [
            'email_provider' => 'ses', 'mobile_provider' => 'twilio',
            'default_locale' => $locale, 'mobile_channel' => 'sms', 'fallback_mobile_channel' => null,
            'sender_mode' => 'platform', 'smart_fallback_enabled' => false, 'two_way_enabled' => false,
            'reminder_offsets_minutes' => config('communications.default_reminder_offsets_minutes', [1440]),
            'quiet_hours_start' => '21:00', 'quiet_hours_end' => '08:00',
            'marketing_enabled' => false,
        ]);
    }

    public function defaultTemplate(int $businessId, string $intent, string $channel, string $locale = 'en-US'): CommunicationTemplate
    {
        $defaults = TemplateVariableCatalog::defaults($intent);
        $variables = $this->renderer->variables($defaults['subject'], $defaults['body']);
        $providerId = $channel === 'whatsapp' ? config('communications.twilio.content_sids.'.$intent) : null;

        $mobileSimulation = in_array(config('communications.transport_mode', 'fake'), ['fake', 'whatsapp_sandbox'], true);
        $publishable = in_array($channel, ['email', 'sms'], true) || $intent === 'conversation_reply' || $providerId || $mobileSimulation;

        return CommunicationTemplate::query()->firstOrCreate([
            'business_id' => $businessId, 'intent_type' => $intent, 'channel' => $channel, 'locale' => $locale, 'version' => 1,
        ], [
            'public_id' => (string) Str::ulid(), 'status' => $publishable ? 'published' : 'draft',
            'subject' => $defaults['subject'], 'body' => $defaults['body'], 'variables' => $variables,
            'fallbacks' => collect(TemplateVariableCatalog::SAFE_FALLBACKS)->only($variables)->all(),
            'provider_template_id' => $providerId, 'provider_template_status' => $providerId ? 'approved' : null,
            'published_at' => $publishable ? now() : null,
        ]);
    }

    public function resolve(int $businessId, string $intent, string $channel, string $locale): CommunicationTemplate
    {
        $settings = $this->settings($businessId);
        $locales = array_unique([$locale, $settings->default_locale, 'en-US', 'en']);
        foreach ($locales as $candidate) {
            $template = CommunicationTemplate::query()->where('business_id', $businessId)->where('intent_type', $intent)
                ->where('channel', $channel)->where('locale', $candidate)->where('status', 'published')->orderByDesc('version')->first();
            if ($template) {
                return $template;
            }
        }

        $fallback = $this->defaultTemplate($businessId, $intent, $channel, $settings->default_locale ?: 'en-US');
        if ($fallback->status === 'published') {
            return $fallback;
        }

        foreach ($locales as $candidate) {
            $template = CommunicationTemplate::query()->where('business_id', $businessId)->where('intent_type', $intent)
                ->where('channel', $channel)->where('locale', $candidate)->orderByDesc('version')->first();
            if ($template) {
                return $template;
            }
        }

        return $fallback;
    }

    /** @param array<string,string> $fallbacks */
    public function save(Business $business, string $intent, string $channel, string $locale, ?string $subject, string $body, array $fallbacks = [], ?string $providerTemplateId = null, ?string $providerStatus = null): CommunicationTemplate
    {
        abort_unless(in_array($intent, TemplateVariableCatalog::intents(), true), 422);
        abort_unless(in_array($channel, ['email', 'sms', 'whatsapp'], true), 422);
        $variables = $this->renderer->variables((string) $subject, $body);
        $unknown = array_diff($variables, TemplateVariableCatalog::ALLOWED);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => 'Unsupported template variables: '.implode(', ', $unknown)]);
        }
        if ($channel === 'email' && blank($subject)) {
            throw ValidationException::withMessages(['subject' => 'Email templates require a subject.']);
        }
        // A first draft must not occupy the lazily-created system default's
        // version. Until publication, new events still resolve a live default.
        if (in_array($channel, ['email', 'sms'], true)) {
            $this->defaultTemplate($business->id, $intent, $channel, $locale);
        }
        $version = (int) CommunicationTemplate::query()->where('business_id', $business->id)->where('intent_type', $intent)->where('channel', $channel)->where('locale', $locale)->max('version') + 1;

        return CommunicationTemplate::query()->create([
            'business_id' => $business->id, 'intent_type' => $intent, 'channel' => $channel, 'locale' => $locale,
            'version' => $version, 'subject' => $subject, 'body' => $body, 'variables' => $variables,
            'fallbacks' => collect($fallbacks)->only($variables)->all(), 'provider_template_id' => $providerTemplateId,
            'provider_template_status' => $providerStatus, 'status' => 'draft',
        ]);
    }

    public function publish(Business $business, CommunicationTemplate $template): CommunicationTemplate
    {
        abort_unless($template->business_id === $business->id, 404);
        $simulation = in_array(config('communications.transport_mode', 'fake'), ['fake', 'whatsapp_sandbox'], true);
        if ($template->channel === 'whatsapp' && $template->intent_type !== 'conversation_reply' && ! $simulation && (blank($template->provider_template_id) || $template->provider_template_status !== 'approved')) {
            throw ValidationException::withMessages(['provider_template_id' => 'WhatsApp templates must have an approved provider Content SID before publishing.']);
        }
        $this->renderer->render($template, collect($template->variables)->mapWithKeys(fn ($name) => [$name => $template->fallbacks[$name] ?? 'Preview value'])->all());
        $template->forceFill(['status' => 'published', 'published_at' => now()])->save();

        return $template->fresh();
    }

    /** @return array{subject:string,body:string,variables:array<string,string>} */
    public function preview(Business $business, CommunicationTemplate $template, array $variables): array
    {
        abort_unless($template->business_id === $business->id, 404);

        return $this->renderer->render($template, $variables);
    }
}
