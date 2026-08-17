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

        return CommunicationSetting::query()->firstOrCreate(['business_id' => $businessId], [
            'default_locale' => 'en-IN', 'reminder_offsets_minutes' => [1440, 120],
            'quiet_hours_start' => '21:00', 'quiet_hours_end' => '08:00',
        ]);
    }

    public function defaultTemplate(int $businessId, string $intent, string $channel, string $locale = 'en-IN'): CommunicationTemplate
    {
        $defaults = TemplateVariableCatalog::defaults($intent);
        $variables = $this->renderer->variables($defaults['subject'], $defaults['body']);
        $providerId = $channel === 'whatsapp' ? config('communications.twilio.content_sids.'.$intent) : null;

        return CommunicationTemplate::query()->firstOrCreate([
            'business_id' => $businessId, 'intent_type' => $intent, 'channel' => $channel, 'locale' => $locale, 'version' => 1,
        ], [
            'public_id' => (string) Str::ulid(), 'status' => $channel === 'email' || $providerId ? 'published' : 'draft',
            'subject' => $defaults['subject'], 'body' => $defaults['body'], 'variables' => $variables,
            'fallbacks' => collect(TemplateVariableCatalog::SAFE_FALLBACKS)->only($variables)->all(),
            'provider_template_id' => $providerId, 'provider_template_status' => $providerId ? 'approved' : null,
            'published_at' => $channel === 'email' || $providerId ? now() : null,
        ]);
    }

    public function resolve(int $businessId, string $intent, string $channel, string $locale): CommunicationTemplate
    {
        $settings = $this->settings($businessId);
        $locales = array_unique([$locale, $settings->default_locale, 'en-IN']);
        foreach ($locales as $candidate) {
            $template = CommunicationTemplate::query()->where('business_id', $businessId)->where('intent_type', $intent)
                ->where('channel', $channel)->where('locale', $candidate)->where('status', 'published')->orderByDesc('version')->first();
            if ($template) {
                return $template;
            }
        }

        $fallback = $this->defaultTemplate($businessId, $intent, $channel, 'en-IN');
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
        abort_unless(in_array($channel, ['email', 'whatsapp'], true), 422);
        $variables = $this->renderer->variables((string) $subject, $body);
        $unknown = array_diff($variables, TemplateVariableCatalog::ALLOWED);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => 'Unsupported template variables: '.implode(', ', $unknown)]);
        }
        if ($channel === 'email' && blank($subject)) {
            throw ValidationException::withMessages(['subject' => 'Email templates require a subject.']);
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
        if ($template->channel === 'whatsapp' && (blank($template->provider_template_id) || $template->provider_template_status !== 'approved')) {
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
