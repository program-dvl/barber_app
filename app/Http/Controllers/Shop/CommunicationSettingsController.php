<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\CommunicationSupportService;
use App\Domain\Communications\Services\CommunicationTemplateService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationSettingsController extends Controller
{
    public function index(Business $business, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);

        return response()->json([
            'settings' => $templates->settings($business)->only(['email_provider', 'mobile_channel', 'mobile_provider', 'default_locale', 'reminder_offsets_minutes', 'quiet_hours_start', 'quiet_hours_end', 'marketing_enabled']),
            'templates' => CommunicationTemplate::query()->where('business_id', $business->id)->orderBy('intent_type')->orderBy('channel')->orderByDesc('version')->get()->map->only(['public_id', 'intent_type', 'channel', 'locale', 'version', 'status', 'subject', 'body', 'variables', 'fallbacks', 'provider_template_id', 'provider_template_status']),
        ]);
    }

    public function update(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        $data = $request->validate([
            'default_locale' => ['required', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'reminder_offsets_minutes' => ['required', 'array', 'min:1', 'max:5'],
            'reminder_offsets_minutes.*' => ['integer', 'min:5', 'max:10080', 'distinct'],
            'quiet_hours_start' => ['required', 'date_format:H:i'], 'quiet_hours_end' => ['required', 'date_format:H:i'],
            'marketing_enabled' => ['required', 'boolean'],
        ]);
        $settings = $templates->settings($business);
        $settings->update($data);

        return response()->json(['settings' => $settings->fresh()]);
    }

    public function storeTemplate(Request $request, Business $business, TenantContext $context, CommunicationTemplateService $templates): JsonResponse
    {
        $this->authorizeSettings($context);
        $data = $request->validate([
            'intent_type' => ['required', 'string', 'max:48'], 'channel' => ['required', 'in:email,whatsapp'],
            'locale' => ['required', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'], 'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'], 'fallbacks' => ['array'], 'fallbacks.*' => ['nullable', 'string', 'max:255'],
            'provider_template_id' => ['nullable', 'string', 'max:255'], 'provider_template_status' => ['nullable', 'in:pending,approved,rejected,paused,disabled'],
        ]);
        $template = $templates->save($business, $data['intent_type'], $data['channel'], $data['locale'], $data['subject'] ?? null, $data['body'], $data['fallbacks'] ?? [], $data['provider_template_id'] ?? null, $data['provider_template_status'] ?? null);

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

        return response()->json(['template' => $templates->publish($business, $communicationTemplate)]);
    }

    public function diagnostic(Business $business, CommunicationMessage $communicationMessage, TenantContext $context, CommunicationSupportService $support): JsonResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationMessage->business_id === $business->id, 404);

        return response()->json(['diagnostic' => $support->diagnostic($communicationMessage->load('intent'))]);
    }

    public function replay(Request $request, Business $business, CommunicationMessage $communicationMessage, TenantContext $context, CommunicationSupportService $support): JsonResponse
    {
        $this->authorizeSettings($context);
        abort_unless($communicationMessage->business_id === $business->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return response()->json(['message' => $support->replay($communicationMessage->load(['intent', 'business']), $data['reason'])]);
    }

    private function authorizeSettings(TenantContext $context): void
    {
        abort_unless($context->membership()?->hasPermissionTo(PermissionName::SettingsManage->value, 'web'), 403);
    }
}
