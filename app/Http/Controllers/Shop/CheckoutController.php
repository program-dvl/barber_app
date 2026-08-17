<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Services\CashCloseService;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout, private readonly ReceiptService $receipts, private readonly CashCloseService $cash, private readonly AuditWriter $audit, private readonly TenantContext $tenancy) {}

    public function index(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $appointments = Appointment::query()->where('business_id', $business->id)->whereIn('status', ['checked_in', 'in_service', 'completed'])->with('client')->latest('starts_at_utc')->limit(30)->get()->map(fn (Appointment $appointment) => ['public_id' => $appointment->public_id, 'reference' => $appointment->booking_reference, 'client' => $appointment->client?->name ?? $appointment->client_name, 'status' => $appointment->status, 'price_minor' => $appointment->price_minor, 'currency_code' => $appointment->currency_code]);
        $sales = Sale::query()->where('business_id', $business->id)->latest()->limit(12)->get()->map->only(['public_id', 'status', 'total_minor', 'paid_minor', 'balance_minor', 'currency_code']);

        return Inertia::render('Shop/Checkout', compact('appointments', 'sales'));
    }

    public function open(Request $request, Business $business, Appointment $appointment)
    {
        abort_unless($appointment->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $data = $request->validate(['lines' => ['array'], 'lines.*.kind' => ['nullable', 'in:addon,product,service'], 'lines.*.product_public_id' => ['nullable', 'string'], 'lines.*.description' => ['required_without:lines.*.product_public_id', 'nullable', 'string', 'max:255'], 'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'], 'lines.*.unit_price_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0', 'max:100000'], 'lines.*.discount_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.staff_profile_id' => ['nullable', 'integer'], 'tips' => ['array'], 'tips.*.staff_profile_id' => ['nullable', 'integer'], 'tips.*.amount_minor' => ['required', 'integer', 'min:0'], 'discount_approved' => ['boolean']]);
        $lines = collect($data['lines'] ?? [])->map(function (array $line) use ($business): array {
            if (! empty($line['product_public_id'])) {
                $product = InventoryProduct::query()->forBusiness($business)->where('public_id', $line['product_public_id'])->firstOrFail();
                unset($line['product_public_id']);
                $line['kind'] = 'product';
                $line['source_id'] = $product->id;
            }

            return $line;
        })->all();
        $sale = $this->checkout->openForAppointment($appointment, $lines, $data['tips'] ?? [], (bool) ($data['discount_approved'] ?? false));

        return response()->json(['sale' => $sale->load('lines')]);
    }

    public function tender(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $data = $request->validate(['method' => ['required', 'in:cash,card,upi,bank_transfer,payment_link,custom,pay_later'], 'amount_minor' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:128'], 'provider' => ['nullable', 'string', 'max:32'], 'provider_reference' => ['nullable', 'string', 'max:191'], 'evidence' => ['array']]);
        $payment = $this->checkout->recordTender($sale, $data['method'], $data['amount_minor'], $data['idempotency_key'], $data['evidence'] ?? [], $data['provider'] ?? null, $data['provider_reference'] ?? null);
        $receipt = $sale->fresh()->status === 'completed' ? $this->receipts->issue($sale->fresh()) : null;

        return response()->json(['payment' => $payment, 'sale' => $sale->fresh(), 'receipt' => $receipt]);
    }

    public function refund(Request $request, Business $business, Sale $sale, PaymentTransaction $payment)
    {
        abort_unless($sale->business_id === $business->id && $payment->business_id === $business->id && $payment->sale_id === $sale->id, 404);
        abort_unless($request->user()->can(PermissionName::RefundIssue->value), 403);
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:128'], 'reason' => ['required', 'string', 'max:1000'], 'kind' => ['nullable', 'in:refund,void'], 'line_refunds' => ['array'], 'line_refunds.*.sale_line_id' => ['required', 'integer'], 'line_refunds.*.amount_minor' => ['required', 'integer', 'min:0'], 'line_refunds.*.quantity' => ['nullable', 'integer', 'min:0'], 'line_refunds.*.disposition' => ['nullable', 'in:restock,write_off,customer_keeps,not_applicable']]);
        $refund = $this->checkout->refund($sale, $payment, $data['amount_minor'], $data['idempotency_key'], $data['reason'], $data['line_refunds'] ?? [], $data['kind'] ?? 'refund');
        $this->audit->write('sale.refund.issued', $business, $request->user(), $refund, $data['reason'], [], ['sale_id' => $sale->public_id, 'amount_minor' => $refund->amount_minor]);

        return response()->json(['refund' => $refund, 'sale' => $sale->fresh()]);
    }

    public function receipt(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::RevenueView->value), 403);
        $receipt = $this->receipts->issue($sale);

        return view('receipts.sale', compact('receipt'));
    }

    public function close(Request $request, Business $business, Location $location)
    {
        abort_unless($location->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::CashCloseManage->value), 403);
        $data = $request->validate(['business_date' => ['required', 'date'], 'opening_cash_minor' => ['required', 'integer', 'min:0'], 'actual_cash_minor' => ['required', 'integer', 'min:0'], 'variance_reason' => ['nullable', 'string', 'max:1000']]);
        $membership = $this->tenancy->membership();
        $close = $this->cash->close($location, CarbonImmutable::parse($data['business_date'], $location->time_zone), $data['opening_cash_minor'], $data['actual_cash_minor'], $data['variance_reason'] ?? null, $membership->id);
        $this->audit->write('cash.close.completed', $business, $request->user(), $close, $data['variance_reason'] ?? null, [], ['expected_cash_minor' => $close->expected_cash_minor, 'actual_cash_minor' => $close->actual_cash_minor, 'variance_minor' => $close->variance_minor]);

        return response()->json(['cash_close' => $close]);
    }
}
