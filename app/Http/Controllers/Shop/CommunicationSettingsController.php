<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Communications\Models\CommunicationConversation;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSenderProfile;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Providers\SesEmailProvider;
use App\Domain\Communications\Services\CommunicationSenderService;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\CommunicationTemplateRenderer;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\Communications\Services\ConversationReplyService;
use App\Domain\Communications\Services\NotificationWorkspaceCatalog;
use App\Domain\Communications\Services\NotificationWorkspaceQuery;
use App\Domain\Communications\Services\TemplateVariableCatalog;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationSettingsController extends Controller
{
    public function page(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates, CommunicationSenderService $senders, EntitlementEvaluator $entitlements): Response
    {
        $this->authorizeView($context);
        $request->validate(['client' => ['nullable', 'string', 'max:26'], 'appointment' => ['nullable', 'string', 'max:26']]);
        $senders->ensurePlatformProfile($business, 'sms');

        return Inertia::render('Communications/Index', $this->pageData($business, $templates, $senders, $entitlements));
    }

    public function index(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates, CommunicationSenderService $senders, EntitlementEvaluator $entitlements): JsonResponse
    {
        $this->authorizeView($context);
        $request->validate(['client' => ['nullable', 'string', 'max:26'], 'appointment' => ['nullable', 'string', 'max:26']]);
        $senders->ensurePlatformProfile($business, 'sms');

        return response()->json($this->pageData($business, $templates, $senders, $entitlements));
    }

    public function update(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates, EntitlementEvaluator $entitlements): JsonResponse|RedirectResponse
    {
        $this->authorizeSettings($context);
        $data = $request->validate([
            'default_locale' => ['required', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'reminder_offsets_minutes' => ['required', 'array', 'size:1'],
            'reminder_offsets_minutes.*' => ['integer', 'in:1440'],
            'quiet_hours_start' => ['required', 'date_format:H:i'], 'quiet_hours_end' => ['required', 'date_format:H:i'],
            'marketing_enabled' => ['required', 'declined'],
            'mobile_channel' => ['required', 'in:sms'],
            'fallback_mobile_channel' => ['prohibited'],
            'smart_fallback_enabled' => ['required', 'declined'],
            'sender_mode' => ['required', 'in:platform,branded'],
            'two_way_enabled' => ['required', 'declined'],
            'settings_revision' => ['required', 'string', 'size:64'],
        ]);
        if ($data['sender_mode'] === 'branded') {
            abort_unless($entitlements->decide($business, 'messaging.branded_sender')->allowed, 403, 'Branded messaging requires an eligible plan.');
            abort_unless(CommunicationSenderProfile::query()->where('business_id', $business->id)->where('mode', 'branded')->where('channel', $data['mobile_channel'])->where('status', 'active')->exists(), 422, 'The branded sender must be ready before it can be activated.');
        }
        $data = [...$data,
            'mobile_channel' => 'sms',
            'fallback_mobile_channel' => null,
            'smart_fallback_enabled' => false,
            'two_way_enabled' => false,
            'marketing_enabled' => false,
            'reminder_offsets_minutes' => config('communications.default_reminder_offsets_minutes', [1440]),
        ];
        $settings = $templates->settings($business);
        DB::transaction(function () use ($settings, $data, $business): void {
            $settings = $settings->newQuery()->lockForUpdate()->findOrFail($settings->id);
            if (! hash_equals($this->settingsRevision($settings), $data['settings_revision'])) {
                throw ValidationException::withMessages(['settings_revision' => 'Preferences changed in another session. Refresh and review the latest settings before saving.']);
            }
            unset($data['settings_revision']);
            $before = $settings->only(array_keys($data));
            $settings->fill($data);
            if ($settings->isDirty()) {
                $settings->save();
                app(AuditWriter::class)->write('communication.settings_updated', $business, target: $settings, before: $before, after: $settings->only(array_keys($data)));
            }
        });

        return $request->expectsJson()
            ? response()->json(['settings' => [...$settings->fresh()->only(array_diff(array_keys($data), ['settings_revision'])), 'settings_revision' => $this->settingsRevision($settings->fresh())]])
            : back()->with('status', 'Client notification preferences saved.');
    }

    public function requestBrandedSender(Request $request, Business $business, TenantContext $context, EntitlementEvaluator $entitlements): RedirectResponse|JsonResponse
    {
        $this->authorizeSettings($context);
        abort_unless($entitlements->decide($business, 'messaging.branded_sender')->allowed, 403, 'Branded messaging requires an eligible plan.');
        if (! in_array(config('communications.transport_mode'), ['fake', 'live'], true)) {
            throw ValidationException::withMessages([
                'channels' => 'This provider mode uses a shared or unsupported sender identity. Complete branded text-line onboarding in the live environment; test mode can only use ClipperDesk notifications.',
            ]);
        }
        $data = $request->validate([
            'channels' => ['required', 'array', 'size:1'],
            'channels.*' => ['required', 'distinct', 'in:sms'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);
        $simulation = config('communications.transport_mode', 'fake') === 'fake';
        DB::transaction(function () use ($data, $business, $simulation): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            foreach ($data['channels'] as $channel) {
                if (CommunicationSenderProfile::query()->where('business_id', $business->id)->where('channel', $channel)->where('mode', 'branded')->whereIn('status', ['active', 'pending'])->exists()) {
                    continue;
                }
                $identifier = $simulation ? 'fake:'.$business->public_id.':'.$channel : null;
                $profile = CommunicationSenderProfile::query()->updateOrCreate([
                    'business_id' => $business->id, 'channel' => $channel, 'mode' => 'branded',
                ], [
                    'provider' => $simulation ? 'fake' : 'twilio', 'status' => $simulation ? 'active' : 'pending',
                    'country_code' => $business->country_code, 'display_name' => $data['display_name'],
                    'sender_identifier' => $identifier,
                    'sender_identifier_hash' => $identifier ? CommunicationSenderService::identifierHash($channel, $identifier) : null,
                    'capabilities' => ['outbound' => true, 'inbound' => $channel === 'sms', 'templates' => false, 'simulation' => $simulation],
                    'metadata' => ['onboarding_stage' => $simulation ? 'simulated_ready' : 'provider_verification_required'],
                    'verified_at' => $simulation ? now() : null,
                ]);
                app(AuditWriter::class)->write('communication.sender_setup_requested', $business, target: $profile, after: $profile->only(['channel', 'mode', 'status']));
            }
        });

        $message = $simulation
            ? 'Your branded text line is ready in local simulation. Production verification is still required before live sending.'
            : 'Branded text-line setup has started. Provider and business verification must complete before activation.';

        return $request->expectsJson() ? response()->json(['status' => $message], 202) : back()->with('status', $message);
    }

    public function reply(Request $request, Business $business, CommunicationConversation $communicationConversation, TenantContext $context, ConversationReplyService $replies): RedirectResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationConversation->business_id === $business->id, 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'client_request_id' => ['required', 'uuid'],
        ]);
        $replies->send($business, $communicationConversation, $data['body'], $data['client_request_id']);

        return back()->with('status', 'Reply queued for delivery.');
    }

    public function storeTemplate(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        $data = $request->validate([
            'intent_type' => ['required', Rule::in(array_keys(NotificationWorkspaceCatalog::automations()))], 'channel' => ['required', 'in:email,sms'],
            'locale' => ['required', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'], 'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'], 'fallbacks' => ['array'], 'fallbacks.*' => ['nullable', 'string', 'max:255'],
            'base_version' => ['required', 'integer', 'min:0'], 'provider_template_id' => ['prohibited'], 'provider_template_status' => ['prohibited'],
        ]);
        $variables = app(CommunicationTemplateRenderer::class)->variables($data['subject'] ?? '', $data['body']);
        if (array_diff($variables, NotificationWorkspaceCatalog::variables($data['intent_type']))) {
            throw ValidationException::withMessages(['body' => 'This variable is not available for this notification. Choose from the variable picker.']);
        }
        if (preg_match('/<\/?[a-z][^>]*>/i', $data['body'])) {
            throw ValidationException::withMessages(['body' => 'Use plain message text. HTML formatting is not supported.']);
        }
        $template = DB::transaction(function () use ($business, $data, $templates) {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $latest = (int) CommunicationTemplate::query()->where('business_id', $business->id)->where('intent_type', $data['intent_type'])->where('channel', $data['channel'])->where('locale', $data['locale'])->max('version');
            if ($latest !== $data['base_version']) {
                throw ValidationException::withMessages(['body' => 'This template changed in another session. Close and refresh before editing again. Your text has not been overwritten.']);
            }
            $template = $templates->save($business, $data['intent_type'], $data['channel'], $data['locale'], $data['subject'] ?? null, $data['body'], $data['fallbacks'] ?? []);
            app(AuditWriter::class)->write('communication.template_saved', $business, target: $template, after: $template->only(['intent_type', 'channel', 'locale', 'version', 'status']));

            return $template;
        });

        return response()->json(['template' => $template], 201);
    }

    public function preview(Request $request, Business $business, CommunicationTemplate $communicationTemplate, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        $data = $request->validate(['variables' => ['array'], 'variables.*' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['preview' => $templates->preview($business, $communicationTemplate, $data['variables'] ?? [])]);
    }

    public function publish(Business $business, CommunicationTemplate $communicationTemplate, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);

        abort_unless($communicationTemplate->business_id === $business->id, 404);
        abort_unless(in_array($communicationTemplate->channel, ['email', 'sms'], true) && array_key_exists($communicationTemplate->intent_type, NotificationWorkspaceCatalog::automations()), 422);
        $template = DB::transaction(function () use ($business, $communicationTemplate, $templates) {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $latest = CommunicationTemplate::query()->where('business_id', $business->id)->where('intent_type', $communicationTemplate->intent_type)->where('channel', $communicationTemplate->channel)->where('locale', $communicationTemplate->locale)->max('version');
            if ($communicationTemplate->version !== (int) $latest) {
                throw ValidationException::withMessages(['template' => 'A newer draft exists. Refresh and review it before publishing.']);
            }
            if ($communicationTemplate->fresh()->status === 'published') {
                return $communicationTemplate->fresh();
            }
            $variables = app(CommunicationTemplateRenderer::class)->variables($communicationTemplate->subject ?? '', $communicationTemplate->body);
            if (array_diff($variables, NotificationWorkspaceCatalog::variables($communicationTemplate->intent_type))) {
                throw ValidationException::withMessages(['template' => 'This draft contains variables unavailable for this notification. Edit and save it before publishing.']);
            }
            $template = $templates->publish($business, $communicationTemplate);
            app(AuditWriter::class)->write('communication.template_published', $business, target: $template, after: $template->only(['intent_type', 'channel', 'locale', 'version', 'status']));

            return $template;
        });

        return response()->json(['template' => $template]);
    }

    public function diagnostic(Business $business, CommunicationMessage $communicationMessage, TenantContext $context, CommunicationSupportService $support): JsonResponse
    {
        $this->authorizeView($context);
        abort_unless(app(NotificationWorkspaceQuery::class)->base($business, $context->membership())->whereKey($communicationMessage->id)->exists(), 404);

        return response()->json(['diagnostic' => $support->diagnostic($communicationMessage->load('intent'))]);
    }

    public function replay(Request $request, Business $business, CommunicationMessage $communicationMessage, TenantContext $context, CommunicationSupportService $support): JsonResponse|RedirectResponse
    {
        $this->authorizeSettings($context);
        abort_unless(app(NotificationWorkspaceQuery::class)->base($business, $context->membership())->whereKey($communicationMessage->id)->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $message = $support->replay($communicationMessage->load(['intent', 'business']), $data['reason']);
        $message->refresh();

        return $request->expectsJson()
            ? response()->json(['message' => app(NotificationWorkspaceQuery::class)->row($message->load(['intent', 'client']), $context->membership())])
            : back()->with('status', 'Message queued again after the provider setup change.');
    }

    public function history(Request $request, Business $business, TenantContext $context, NotificationWorkspaceQuery $query): JsonResponse
    {
        $this->authorizeView($context);
        $filters = $request->validate([
            'channel' => ['nullable', 'in:email,sms'], 'status' => ['nullable', 'in:queued,retried,sending,sent,delivered,failed,suppressed'],
            'type' => ['nullable', Rule::in(array_keys(NotificationWorkspaceCatalog::automations()))],
            'search' => ['nullable', 'string', 'max:120'], 'client' => ['nullable', 'string', 'max:26'], 'appointment' => ['nullable', 'string', 'max:26'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json(['history' => $query->history($business, $context->membership(), $filters)]);
    }

    private function authorizeView(TenantContext $context): void
    {
        $membership = $context->membership();
        abort_unless($membership && ($membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web')
            || ($membership->hasPermissionTo(PermissionName::CalendarViewAll->value, 'web') && $membership->hasPermissionTo(PermissionName::ClientView->value, 'web') && $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web'))), 403);
    }

    private function authorizeSettings(TenantContext $context): void
    {
        abort_unless($context->membership()?->hasPermissionTo(PermissionName::SettingsManage->value, 'web'), 403);
    }

    private function settingsRevision(CommunicationSetting $settings): string
    {
        return hash('sha256', json_encode($settings->only(['sender_mode', 'default_locale', 'quiet_hours_start', 'quiet_hours_end'])));
    }

    /** @return array<string, mixed> */
    private function pageData(Business $business, CommunicationTemplateService $templates, CommunicationSenderService $senders, EntitlementEvaluator $entitlements): array
    {
        $membership = app(TenantContext::class)->membership();
        $manage = $membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web');
        $settings = $templates->settings($business);
        $allowance = $entitlements->value($business, 'messaging.monthly_allowance');
        $visibleSettings = $settings->only(['email_provider', 'mobile_provider', 'sender_mode', 'default_locale', 'quiet_hours_start', 'quiet_hours_end']);
        $visibleSettings = [...$visibleSettings,
            'email_provider' => 'ses',
            'settings_revision' => $this->settingsRevision($settings),
            'mobile_channel' => 'sms',
            'fallback_mobile_channel' => null,
            'smart_fallback_enabled' => false,
            'two_way_enabled' => false,
            'reminder_offsets_minutes' => config('communications.default_reminder_offsets_minutes', [1440]),
            'marketing_enabled' => false,
        ];

        return [
            'business' => $business->only(['public_id', 'name', 'country_code', 'locale', 'time_zone']),
            'settings' => $visibleSettings,
            'senderReadiness' => $senders->readiness($business),
            'capabilities' => [
                'manage' => $manage,
                'contact' => $membership->hasPermissionTo(PermissionName::ClientContactView->value, 'web'),
                'branded_sender' => $manage && $entitlements->decide($business, 'messaging.branded_sender')->allowed,
            ],
            'usage' => [
                'used' => $entitlements->usage($business, 'messaging.monthly_allowance'),
                'included' => is_numeric($allowance) ? (int) $allowance : 0,
            ],
            'automations' => collect(NotificationWorkspaceCatalog::automations())->map(fn ($row, $type) => ['type' => $type, ...$row])->values(),
            'sampleValues' => NotificationWorkspaceCatalog::samples($business),
            'variableCatalog' => collect(NotificationWorkspaceCatalog::automations())->mapWithKeys(fn ($row, $type) => [$type => NotificationWorkspaceCatalog::variables($type)]),
            'defaults' => collect(NotificationWorkspaceCatalog::automations())->mapWithKeys(fn ($row, $type) => [$type => TemplateVariableCatalog::defaults($type)]),
            'templates' => $manage ? CommunicationTemplate::query()->where('business_id', $business->id)->whereIn('channel', ['email', 'sms'])->whereIn('intent_type', array_keys(NotificationWorkspaceCatalog::automations()))->whereIn('id', CommunicationTemplate::query()->selectRaw('MAX(id)')->where('business_id', $business->id)->groupBy('intent_type', 'channel', 'locale', 'status'))->orderByDesc('version')->get()->map->only(['public_id', 'intent_type', 'channel', 'locale', 'version', 'status', 'subject', 'body', 'variables', 'fallbacks']) : [],
            'overview' => app(NotificationWorkspaceQuery::class)->overview($business, $membership),
            'historyFilters' => ['client' => request()->query('client') ?: '', 'appointment' => request()->query('appointment') ?: ''],
            'history' => app(NotificationWorkspaceQuery::class)->history($business, $membership, ['client' => request()->query('client') ?: '', 'appointment' => request()->query('appointment') ?: '']),
            'emailReadiness' => ['configured' => SesEmailProvider::configured(), 'simulated' => config('communications.email_transport_mode') === 'fake', 'delivery_updates' => filled(config('communications.ses.sns_topic_arn')), 'label' => SesEmailProvider::configured() ? 'Sending configured' : 'Needs setup'],
        ];
    }
}
