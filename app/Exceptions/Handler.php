<?php

namespace App\Exceptions;

use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use App\Support\Seo\PublicPage;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tighten\Ziggy\Ziggy;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Scheduling rules are expected operational feedback, never an error page.
     */
    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof BookingRuleViolation) {
            if ($request->expectsJson()) {
                return response()->json($exception->toDomainError(), 422);
            }

            return back()->withErrors([
                'booking' => $exception->getMessage(),
                'booking_rule' => $exception->ruleCode,
            ]);
        }

        $response = parent::render($request, $exception);
        if (($request->is('marketplace', 'marketplace/*') || ($request->routeIs('business.marketplace.*') && ! $request->isMethod('GET'))) && in_array($response->getStatusCode(), [401, 403, 404, 409, 410, 419, 429, 500, 503], true)) {
            $status = $response->getStatusCode();
            $reference = (string) $request->attributes->get('correlation_id', 'marketplace');
            if ($request->expectsJson()) {
                return response()->json(['message' => 'We couldn’t complete this step. Your saved bookings are unchanged. Contact Clipperdesk support with reference '.$reference.'.', 'reference' => $reference], $status, $status === 429 ? ['Retry-After' => $response->headers->get('Retry-After', 60)] : []);
            }
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return back()->with('marketplace_retry_after', $status === 429 ? max(1, (int) $response->headers->get('Retry-After', 60)) : null)->with('marketplace_blocked', in_array($status, [401, 403, 419, 500, 503], true))->withErrors(['marketplace' => $status === 429 ? 'Please wait before trying again. Your entered work is saved on this page.' : ($status === 410 ? 'This private link has expired. Ask the salon for a fresh appointment link, then add your booking again.' : 'We couldn’t complete this step. Your entered work remains on this page. Contact '.config('brand.support_email').' with reference '.$reference.'.')]);
            }

            return Inertia::render('Marketplace/Unavailable', ['status' => $status, 'reference' => $reference,
                'ziggy' => [...(new Ziggy)->toArray(), 'location' => $request->url()],
            ])->toResponse($request)->setStatusCode($status);
        }

        if (! $request->expectsJson()
            && $response->getStatusCode() === 403
            && $request->attributes->has('tenant_business')) {
            $business = $request->attributes->get('tenant_business');

            return Inertia::render('Access/Unavailable', [
                'businessLabel' => $business->name,
                'state' => [
                    'code' => 'permission_denied',
                    'title' => "You don't have permission to access this area",
                    'description' => 'Your current business role does not permit this action. Contact your business administrator if you believe your access should be updated.',
                    'action' => null,
                ],
            ])->toResponse($request)->setStatusCode(403);
        }

        if ($request->expectsJson() && $response->getStatusCode() === 403) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'permission_denied',
            ], 403);
        }

        if (! $request->expectsJson() && in_array($response->getStatusCode(), [403, 404, 410, 500, 503], true)) {
            $request->attributes->set('force_noindex', true);

            $status = $response->getStatusCode();
            $title = match ($status) {
                403 => 'You do not have access to this page',
                404 => 'Page not found',
                410 => 'This page is no longer available',
                503 => 'ClipperDesk is temporarily unavailable',
                default => 'Something went wrong',
            };

            $privateCustomerPage = $request->is('appointments/secure/*', 'waitlist-offers/*', 'client-forms/secure/*', 'client-forms/completed');

            return PublicPage::render($privateCustomerPage ? 'Marketplace/Unavailable' : 'Error', [
                'status' => $status,
                ...($privateCustomerPage ? ['privateBooking' => true, 'reference' => (string) $request->attributes->get('correlation_id', 'marketplace')] : []),
                // Exception rendering may bypass the normal shared-props middleware.
                // Supply the same route definitions so the SSR error page can render.
                'ziggy' => [...(new Ziggy)->toArray(), 'location' => $request->url()],
                'seo' => ['title' => $title.' | ClipperDesk', 'description' => 'The requested page is unavailable. Return to ClipperDesk to continue or try again later.', 'canonical' => null],
            ])->toResponse($request)->setStatusCode($status);
        }

        return $response;
    }
}
