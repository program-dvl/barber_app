<?php

namespace App\Http\Controllers\Shop;

use App\Domain\ClientReviews\Services\ClientReviewService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Services\SchedulingRecordLookup;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentReviewLinkController extends Controller
{
    public function __invoke(Request $request, Business $business, string $appointment, SchedulingRecordLookup $records, ClientReviewService $reviews, SecureAppointmentLinkService $links, AuditWriter $audit): JsonResponse
    {
        $record = $records->appointment($business->id, $appointment);
        $this->authorize('update', $record);
        $reviews->assertEligible($record);
        $issued = $links->issue($record->loadMissing('business'), 'review', CarbonImmutable::now()->addDays(7));
        $audit->write('client_review.link_issued', $business, $request->user(), $record, null, [], [], ['expires_at' => $issued['expires_at']], 'calendar');

        return response()->json(['url' => route('public.appointment.action', [$issued['token'], 'review']), 'expires_at' => $issued['expires_at']], 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
