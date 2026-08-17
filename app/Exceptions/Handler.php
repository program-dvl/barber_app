<?php

namespace App\Exceptions;

use App\Domain\SchedulingOperations\Exceptions\BookingRuleViolation;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        if (! $request->expectsJson() && in_array($response->getStatusCode(), [404, 410, 500, 503], true)) {
            $request->attributes->set('force_noindex', true);

            return Inertia::render('Error', ['status' => $response->getStatusCode()])->toResponse($request)->setStatusCode($response->getStatusCode());
        }

        return $response;
    }
}
