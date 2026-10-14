<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\Communications\Models\CommunicationConversation;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationSetting;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Providers\SesEmailProvider;
use App\Domain\Communications\Services\ClientNotificationEmail;
use App\Domain\Communications\Services\CommunicationDeliveryService;
use App\Domain\Communications\Services\CommunicationSenderService;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\CommunicationTemplateRenderer;
use App\Domain\Communications\Services\CommunicationTemplateService;
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
            'sender_mode' => ['required', 'in:platform'],
            'email_enabled' => ['required', 'boolean'], 'sms_enabled' => ['required', 'boolean'],
            'two_way_enabled' => ['required', 'declined'],
            'settings_revision' => ['required', 'string', 'size:64'],
        ]);
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
                foreach (['email', 'sms'] as $channel) {
                    if (! $data[$channel.'_enabled']) {
                        app(CommunicationDeliveryService::class)->pauseChannel($business->id, $channel);
                    }
                }
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
        abort(410, 'Client notifications use the ClipperDesk sender.');
    }

    public function reply(Request $request, Business $business, CommunicationConversation $communicationConversation, TenantContext $context): RedirectResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationConversation->business_id === $business->id, 404);
        abort(410, 'Client messaging is managed by ClipperDesk.');
    }

    public function storeTemplate(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        abort(410, 'Client notification content is managed by ClipperDesk.');
    }

    public function preview(Request $request, Business $business, CommunicationTemplate $communicationTemplate, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationTemplate->business_id === $business->id, 404);
        abort(410, 'Client notification content is managed by ClipperDesk.');
    }

    public function publish(Business $business, CommunicationTemplate $communicationTemplate, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationTemplate->business_id === $business->id, 404);
        abort(410, 'Client notification content is managed by ClipperDesk.');
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

    public function example(Request $request, Business $business, TenantContext $context): JsonResponse
    {
        $this->authorizeView($context);
        $types = collect(NotificationWorkspaceCatalog::automations())->reject(fn ($row) => $row['prepared'] ?? false)->keys()->all();
        $data = $request->validate(['type' => ['required', Rule::in($types)]]);
        $defaults = TemplateVariableCatalog::defaults($data['type']);
        $variables = NotificationWorkspaceCatalog::samples($business);
        $template = new CommunicationTemplate([
            'subject' => $defaults['subject'], 'body' => config('brand.product_name').': '.$defaults['body'],
            'variables' => app(CommunicationTemplateRenderer::class)->variables($defaults['subject'], $defaults['body']),
        ]);
        $rendered = app(CommunicationTemplateRenderer::class)->render($template, $variables);

        return response()->json([
            'subject' => $rendered['subject'], 'text' => $rendered['body'],
            'html' => app(ClientNotificationEmail::class)->render($data['type'], $variables, $rendered['body'], $rendered['subject']),
        ])->header('Cache-Control', 'private, no-store');
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
        return hash('sha256', json_encode($settings->only(['email_enabled', 'sms_enabled', 'default_locale', 'quiet_hours_start', 'quiet_hours_end'])));
    }

    /** @return array<string, mixed> */
    private function pageData(Business $business, CommunicationTemplateService $templates, CommunicationSenderService $senders, EntitlementEvaluator $entitlements): array
    {
        $membership = app(TenantContext::class)->membership();
        $manage = $membership->hasPermissionTo(PermissionName::SettingsManage->value, 'web');
        $settings = $templates->settings($business);
        $allowance = $entitlements->value($business, 'messaging.monthly_allowance');
        $visibleSettings = $settings->only(['email_provider', 'mobile_provider', 'sender_mode', 'email_enabled', 'sms_enabled', 'default_locale', 'quiet_hours_start', 'quiet_hours_end']);
        $visibleSettings = [...$visibleSettings,
            'email_provider' => 'ses',
            'sender_mode' => 'platform',
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
                'branded_sender' => false,
            ],
            'usage' => [
                'used' => $entitlements->usage($business, 'messaging.monthly_allowance'),
                'included' => is_numeric($allowance) ? (int) $allowance : 0,
            ],
            'automations' => collect(NotificationWorkspaceCatalog::automations())->reject(fn ($row) => $row['prepared'] ?? false)->map(fn ($row, $type) => ['type' => $type, ...$row])->values(),
            'overview' => app(NotificationWorkspaceQuery::class)->overview($business, $membership),
            'historyFilters' => ['client' => request()->query('client') ?: '', 'appointment' => request()->query('appointment') ?: ''],
            'history' => app(NotificationWorkspaceQuery::class)->history($business, $membership, ['client' => request()->query('client') ?: '', 'appointment' => request()->query('appointment') ?: '']),
            'emailReadiness' => ['configured' => SesEmailProvider::configured(), 'simulated' => config('communications.email_transport_mode') === 'fake', 'delivery_updates' => filled(config('communications.ses.sns_topic_arn')), 'label' => SesEmailProvider::configured() ? 'Sending configured' : 'Needs setup'],
        ];
    }
}
