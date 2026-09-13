<?php

namespace App\Http\Middleware;

use App\Domain\PlatformAccess\Services\WorkspaceAccessService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkspaceFeature
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WorkspaceAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($feature === 'module') {
            $feature = (string) $request->route('module');
        }

        $decision = $this->access->decide(
            $this->tenant->business(),
            $this->tenant->membership(),
            $feature,
        );

        if ($decision['allowed']) {
            return $next($request);
        }

        $status = match ($decision['status']) {
            'upgrade_required' => Response::HTTP_PAYMENT_REQUIRED,
            'setup_required' => Response::HTTP_CONFLICT,
            default => Response::HTTP_FORBIDDEN,
        };
        $canManageBilling = (bool) data_get(
            $this->access->decide($this->tenant->business(), $this->tenant->membership(), 'subscription-billing'),
            'allowed',
        );
        $canManageSetup = (bool) data_get(
            $this->access->decide($this->tenant->business(), $this->tenant->membership(), 'settings'),
            'allowed',
        );
        $action = match ($decision['status']) {
            'upgrade_required' => $canManageBilling ? [
                'label' => 'View plans',
                'url' => route('business.billing.show', $this->tenant->business()),
            ] : null,
            'setup_required' => $canManageSetup ? [
                'label' => 'Continue business setup',
                'url' => route('business.configuration.show', $this->tenant->business()),
            ] : null,
            default => null,
        };
        $title = match ($decision['status']) {
            'upgrade_required' => 'Upgrade your plan to unlock this area',
            'setup_required' => 'Finish setting up your business',
            default => "You don't have permission to access this area",
        };

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $decision['reason'],
                'code' => $decision['status'],
                'feature' => $feature,
                'action' => $action,
            ], $status);
        }

        return Inertia::render('Access/Unavailable', [
            'businessLabel' => $this->tenant->business()->name,
            'state' => [
                'code' => $decision['status'],
                'title' => $title,
                'description' => $decision['reason'],
                'action' => $action,
            ],
        ])->toResponse($request)->setStatusCode($status);
    }
}
