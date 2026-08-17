<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\Reporting\Services\ReportExportService;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionLedger $ledger, private readonly ReportExportService $exports, private readonly TenantContext $tenancy, private readonly AuditWriter $audit) {}

    public function rule(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::SettingsManage->value), 403);
        $data = $request->validate(['staff_profile_id' => ['nullable', 'integer'], 'service_id' => ['nullable', 'integer'], 'kind' => ['required', 'in:service_percentage,product_percentage,fixed_service'], 'rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'], 'amount_minor' => ['nullable', 'integer', 'min:0'], 'currency_code' => ['required', 'string', 'size:3'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from'], 'reason' => ['required', 'string', 'max:1000']]);
        $membership = $this->tenancy->membership();
        $rule = $this->ledger->createRule([...$data, 'business_id' => $business->id, 'currency_code' => strtoupper($data['currency_code']), 'created_by_membership_id' => $membership->id]);
        $this->audit->write('commission.rule.created', $business, $request->user(), $rule, $data['reason'], [], $rule->only(['kind', 'staff_profile_id', 'service_id', 'rate_bps', 'amount_minor', 'effective_from', 'effective_to']));

        return response()->json(['rule' => $rule], 201);
    }

    public function statement(Request $request, Business $business, StaffProfile $staff)
    {
        abort_unless($staff->business_id === $business->id, 404);
        $membership = $this->tenancy->membership();
        $canAll = $request->user()->can(PermissionName::CommissionsViewAll->value);
        abort_unless($canAll || ($request->user()->can(PermissionName::CommissionsViewOwn->value) && $membership->staffProfile?->is($staff)), 403);
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        $from = CarbonImmutable::parse($data['start_date'], $business->time_zone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($data['end_date'], $business->time_zone)->endOfDay()->utc();

        return response()->json(['statement' => $this->ledger->statement($business->id, $staff->id, $from, $to), 'fresh_at' => now()->utc()->toIso8601String(), 'time_zone' => $business->time_zone]);
    }

    public function adjust(Request $request, Business $business, StaffProfile $staff)
    {
        abort_unless($staff->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::CommissionsViewAll->value), 403);
        $data = $request->validate(['ledger' => ['required', 'in:commission,tip'], 'amount_minor' => ['required', 'integer', 'not_in:0'], 'currency_code' => ['required', 'string', 'size:3'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'string', 'max:160']]);
        $membership = $this->tenancy->membership();
        $entry = $data['ledger'] === 'commission' ? $this->ledger->adjustCommission($staff, $data['amount_minor'], strtoupper($data['currency_code']), $membership, $data['reason'], $data['idempotency_key']) : $this->ledger->adjustTip($staff, $data['amount_minor'], strtoupper($data['currency_code']), $membership, $data['reason'], $data['idempotency_key']);
        $this->audit->write("{$data['ledger']}.manager_adjustment", $business, $request->user(), $entry, $data['reason'], [], ['staff_profile_id' => $staff->public_id, 'amount_minor' => $entry->amount_minor]);

        return response()->json(['entry' => $entry]);
    }

    public function payroll(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::CommissionsViewAll->value), 403);
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'staff_ids' => ['array'], 'staff_ids.*' => ['integer']]);
        $export = $this->exports->queue($business, $this->tenancy->membership(), 'payroll', $data);

        return response()->json(['export' => $export], 202);
    }
}
