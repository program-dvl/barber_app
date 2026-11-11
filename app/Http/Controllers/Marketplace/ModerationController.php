<?php

namespace App\Http\Controllers\Marketplace;

use App\Domain\PlatformAccess\Enums\PlatformCapability;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\PlatformAuthorizer;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModerationController extends Controller
{
    public function index(Request $request, PlatformAuthorizer $authorizer)
    {
        $authorizer->authorize($request->user(), PlatformCapability::NotesManage);
        $reports = DB::table('marketplace_reports')->join('businesses', 'businesses.id', '=', 'marketplace_reports.business_id')->select('marketplace_reports.*', 'businesses.name as salon')->orderByDesc('marketplace_reports.id')->paginate(25);
        $reports->through(fn ($r) => ['reference' => $r->public_id, 'salon' => $r->salon, 'reason' => str_replace('_', ' ', $r->reason), 'details' => Crypt::decryptString($r->details), 'status' => $r->status, 'created_at' => $r->created_at, 'url' => route('platform.marketplace.reports.update', $r->public_id)]);
        app(AuditWriter::class)->write('marketplace.reports.viewed', metadata: ['count' => $reports->count()]);

        return Inertia::render('Marketplace/Reports', ['reports' => $reports]);
    }

    public function update(Request $request, string $reference, PlatformAuthorizer $authorizer)
    {
        $authorizer->authorize($request->user(), PlatformCapability::NotesManage);
        $data = $request->validate(['status' => ['required', 'in:open,resolved'], 'note' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($reference, $data) {
            $report = DB::table('marketplace_reports')->where('public_id', $reference)->lockForUpdate()->first();
            abort_unless($report, 404);
            DB::table('marketplace_reports')->where('id', $report->id)->update(['status' => $data['status'], 'updated_at' => now()]);
            app(AuditWriter::class)->write('marketplace.concern.updated', Business::query()->findOrFail($report->business_id), reason: $data['note'], before: ['status' => $report->status], after: ['status' => $data['status']], metadata: ['reference' => $reference]);
        });

        return back()->with('marketplace_status', 'Concern updated.');
    }
}
