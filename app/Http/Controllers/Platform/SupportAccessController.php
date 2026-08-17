<?php

namespace App\Http\Controllers\Platform;

use App\Domain\PlatformAccess\Enums\PlatformCapability;
use App\Domain\PlatformAccess\Enums\SupportScope;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\SupportAccessGrant;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Domain\PlatformAccess\Services\PlatformAuthorizer;
use App\Domain\PlatformAccess\Services\PlatformFailureRecoveryService;
use App\Domain\PlatformAccess\Services\PlatformTenantQuery;
use App\Domain\PlatformAccess\Services\SupportAccessService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportAccessController extends Controller
{
    public function __construct(private readonly PlatformAuthorizer $authorization, private readonly SupportAccessService $support) {}

    public function index(Request $request): JsonResponse|Response
    {
        $this->authorization->authorize($request->user(), PlatformCapability::TenantView);
        $data = ['grants' => SupportAccessGrant::query()->with(['business:id,public_id,name', 'operator:id,name'])->latest()->limit(100)->get()->map(fn ($grant) => [
            'public_id' => $grant->public_id, 'business' => $grant->business->only(['public_id', 'name']), 'operator' => $grant->operator->only(['id', 'name']),
            'ticket_reference' => $grant->ticket_reference, 'reason' => $grant->reason, 'scopes' => $grant->scopes,
            'expires_at' => $grant->expires_at->toIso8601String(), 'revoked_at' => $grant->revoked_at?->toIso8601String(), 'active' => $grant->isActive(),
        ])];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Platform/SupportAccess', $data);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorization->authorize($request->user(), PlatformCapability::SupportGrantManage);
        $data = $request->validate([
            'operator_user_id' => ['required', 'integer', 'exists:users,id'], 'ticket_reference' => ['required', 'string', 'min:3', 'max:96'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'], 'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', Rule::enum(SupportScope::class), 'distinct'], 'expires_at' => ['required', 'date', 'after:now'],
        ]);
        $grant = $this->support->grant($business, User::findOrFail($data['operator_user_id']), $request->user(), $data['ticket_reference'], $data['reason'], $data['scopes'], CarbonImmutable::parse($data['expires_at']));

        return response()->json(['grant' => $grant], 201);
    }

    public function enter(Request $request, SupportAccessGrant $grant): JsonResponse
    {
        $this->authorization->authorize($request->user(), PlatformCapability::SupportEnter);
        $session = $this->support->enter($grant, $request->user(), $request);

        return response()->json(['session' => ['public_id' => $session->public_id, 'business_public_id' => $grant->business->public_id, 'operator' => $request->user()->name, 'ticket_reference' => $grant->ticket_reference, 'reason' => $grant->reason, 'scopes' => $grant->scopes, 'expires_at' => $grant->expires_at->toIso8601String()]]);
    }

    public function leave(Request $request, SupportAccessSession $supportSession): JsonResponse
    {
        $this->support->leave($supportSession, $request->user());
        $request->session()->forget(['support_access_session_id', 'support_access_session_token']);

        return response()->json(['ended' => true]);
    }

    public function revoke(Request $request, SupportAccessGrant $grant): JsonResponse
    {
        $this->authorization->authorize($request->user(), PlatformCapability::SupportGrantManage);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->support->revoke($grant, $request->user(), $data['reason']);

        return response()->json(['revoked' => true]);
    }

    public function business(Request $request, Business $business, PlatformTenantQuery $tenants): JsonResponse
    {
        $session = $this->support->requireSession($request, $business, SupportScope::AccountSummary);

        return response()->json(['support_context' => $this->context($session), 'business' => $tenants->summary($business)]);
    }

    public function failures(Request $request, Business $business, PlatformFailureRecoveryService $failures): JsonResponse
    {
        $session = $this->support->requireSession($request, $business, SupportScope::WebhookFailures);

        return response()->json(['support_context' => $this->context($session), 'failures' => $failures->list($business->id)]);
    }

    public function replay(Request $request, Business $business, string $type, int $id, PlatformFailureRecoveryService $failures): JsonResponse
    {
        $scope = $type === 'notification' ? SupportScope::Communications : SupportScope::WebhookFailures;
        $session = $this->support->requireSession($request, $business, $scope);
        $data = $request->validate(['operation_key' => ['required', 'string', 'min:8', 'max:100'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return response()->json(['support_context' => $this->context($session), 'replay' => $failures->replay($type, $id, $data['operation_key'], $request->user(), $data['reason'], $business->id)]);
    }

    private function context(SupportAccessSession $session): array
    {
        return ['operator' => $session->operator->name, 'operator_user_id' => $session->operator_user_id, 'ticket_reference' => $session->grant->ticket_reference, 'reason' => $session->grant->reason, 'scopes' => $session->grant->scopes, 'expires_at' => $session->grant->expires_at->toIso8601String()];
    }
}
