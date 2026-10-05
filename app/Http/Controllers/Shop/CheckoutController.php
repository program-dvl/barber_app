<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\MoneyCommerce\Models\Deposit;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Services\CashCloseService;
use App\Domain\MoneyCommerce\Services\CheckoutBasketService;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\MoneyCommerce\Services\CheckoutWorkspaceQuery;
use App\Domain\MoneyCommerce\Services\ReceiptService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout, private readonly ReceiptService $receipts, private readonly CashCloseService $cash, private readonly AuditWriter $audit, private readonly TenantContext $tenancy, private readonly CheckoutWorkspaceQuery $workspace, private readonly CheckoutBasketService $basket) {}

    public function index(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value) || $request->user()->can(PermissionName::RevenueView->value), 403);
        $filters = $request->validate(['location' => ['nullable', 'string'], 'search' => ['nullable', 'string', 'max:100'], 'ready_search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:open,completed,refunded'], 'method' => ['nullable', 'in:cash,card,upi,bank_transfer,payment_link,custom'], 'staff' => ['nullable', 'integer'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'section' => ['nullable', 'in:checkout,sales']]);
        $locations = $business->locations()->whereIn('id', $this->accessibleLocationIds($business))->get(['id', 'public_id', 'name', 'time_zone']);
        $locationIds = $locations->pluck('id')->all();
        if ($request->filled('location')) {
            $location = $locations->firstWhere('public_id', $filters['location']);
            abort_unless($location, 404);
            $locationIds = [$location->id];
        }
        $canCheckout = $request->user()->can(PermissionName::CheckoutManage->value);
        $canHistory = $request->user()->can(PermissionName::RevenueView->value);
        $ready = Appointment::query()->where('business_id', $business->id)->whereIn('location_id', $locationIds)->where('status', 'completed')
            ->where(fn ($query) => $query->whereDoesntHave('sale')->orWhereHas('sale', fn ($sale) => $sale->where('status', 'open')->where('balance_minor', '>', 0)));
        $readyCount = $canCheckout ? (clone $ready)->count() : 0;
        $selectedAppointment = $canCheckout ? $request->string('appointment')->toString() : '';
        $linked = $selectedAppointment !== '' ? Appointment::query()->where('business_id', $business->id)->whereIn('location_id', $locationIds)->where('status', 'completed')->where('public_id', $selectedAppointment)->with(['client', 'sale', 'location', 'serviceLines.primaryStaff'])->firstOrFail() : null;
        if ($request->filled('ready_search')) {
            $ready->where(fn ($q) => $q->where('client_name', 'like', '%'.$filters['ready_search'].'%')->orWhere('booking_reference', 'like', '%'.$filters['ready_search'].'%')->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$filters['ready_search'].'%')));
        }
        $records = $canCheckout ? (clone $ready)->with(['client', 'sale', 'location', 'serviceLines.primaryStaff'])->latest('starts_at_utc')->limit(30)->get() : collect();
        if ($linked && (! $linked->sale || $linked->sale->status === 'open')) {
            $records = $records->prepend($linked)->unique('id');
        }
        $appointments = $records->map(fn ($appointment) => $this->workspace->appointment($appointment))->values();
        $query = Sale::query()->where('business_id', $business->id)->whereIn('location_id', $locationIds)->with(['appointment.client', 'appointment.location', 'lines', 'transactions']);
        if ($request->filled('search')) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('public_id', 'like', $term)->orWhereHas('appointment', fn ($a) => $a->where('booking_reference', 'like', $term)->orWhere('client_name', 'like', $term)->orWhere('public_id', 'like', $term)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)))->orWhereIn('id', DB::table('sale_receipts')->where('business_id', $business->id)->where('receipt_number', 'like', $term)->select('sale_id')));
        }
        if ($request->filled('status')) {
            $filters['status'] === 'refunded' ? $query->where('refunded_minor', '>', 0) : $query->where('status', $filters['status']);
        }
        if ($request->filled('method')) {
            $query->whereHas('transactions', fn ($q) => $q->where('kind', 'payment')->where('method', $filters['method']));
        }
        if ($request->filled('staff')) {
            $query->whereHas('lines', fn ($q) => $q->where('staff_profile_id', $filters['staff']));
        }
        if ($request->filled('from') || $request->filled('to')) {
            $query->where(function ($q) use ($locations, $filters, $locationIds) {
                foreach ($locations->whereIn('id', $locationIds) as $location) {
                    $q->orWhere(function ($branch) use ($location, $filters) {
                        $branch->where('location_id', $location->id);
                        if (! empty($filters['from'])) {
                            $branch->where('created_at', '>=', CarbonImmutable::parse($filters['from'], $location->time_zone)->startOfDay()->utc());
                        }
                        if (! empty($filters['to'])) {
                            $branch->where('created_at', '<', CarbonImmutable::parse($filters['to'], $location->time_zone)->addDay()->startOfDay()->utc());
                        }
                    });
                }
            });
        }
        $pagination = $canHistory ? $query->latest('id')->paginate(20)->withQueryString()->appends(['section' => 'sales']) : null;
        $staffNames = $pagination ? StaffProfile::query()->forBusiness($business)->whereIn('id', collect($pagination->items())->flatMap(fn ($sale) => $sale->lines->pluck('staff_profile_id'))->filter()->unique())->pluck('display_name', 'id') : collect();
        $sales = $pagination ? collect($pagination->items())->map(fn ($sale) => [...$sale->only(['public_id', 'status', 'total_minor', 'paid_minor', 'balance_minor', 'refunded_minor', 'currency_code']), 'created_at' => $sale->created_at->toIso8601String(), 'client' => $sale->appointment?->client?->name ?? $sale->appointment?->client_name, 'reference' => $sale->appointment?->booking_reference, 'appointment_public_id' => $sale->appointment?->public_id, 'location' => $sale->appointment?->location?->name, 'time_zone' => $sale->appointment?->time_zone ?? 'UTC', 'staff' => $sale->lines->pluck('staff_profile_id')->map(fn ($id) => $staffNames->get($id))->filter()->unique()->values()->all(), 'methods' => $sale->transactions->where('kind', 'payment')->pluck('method')->unique()->values()->all()]) : collect();
        $permissions = ['checkout' => $canCheckout, 'history' => $canHistory, 'discount' => $request->user()->can(PermissionName::DiscountApply->value), 'override' => $this->tenancy->membership()?->hasRole(['owner', 'manager'], 'web') ?? false, 'refund' => $request->user()->can(PermissionName::RefundIssue->value), 'calendar' => $request->user()->canAny([PermissionName::CalendarViewAll->value, PermissionName::CalendarViewOwn->value]), 'queue' => $request->user()->can(PermissionName::WalkInsManage->value), 'retail' => app(EntitlementEvaluator::class)->decide($business, 'inventory.enabled')->allowed];

        return Inertia::render('Shop/Checkout', ['appointments' => $appointments, 'sales' => $sales, 'selectedAppointment' => $selectedAppointment, 'selectedVisit' => $linked ? $this->workspace->appointment($linked) : null, 'selectedSale' => $request->string('sale')->toString(), 'readyCount' => $readyCount, 'locations' => $locations->map->only(['public_id', 'name', 'time_zone']), 'filters' => $filters, 'permissions' => $permissions, 'salesPagination' => $pagination ? ['current_page' => $pagination->currentPage(), 'last_page' => $pagination->lastPage(), 'total' => $pagination->total(), 'prev' => $pagination->previousPageUrl(), 'next' => $pagination->nextPageUrl()] : null, 'staffFilters' => $canHistory ? StaffProfile::query()->forBusiness($business)->whereHas('locations', fn ($q) => $q->whereIn('locations.id', $locationIds))->orderBy('display_name')->get(['id', 'display_name']) : []]);
    }

    public function visit(Request $request, Business $business, Appointment $appointment)
    {
        $this->assertAppointment($request, $business, $appointment);

        return response()->json($this->workspace->visit($appointment));
    }

    public function catalogue(Request $request, Business $business, Appointment $appointment)
    {
        $this->assertAppointment($request, $business, $appointment);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'kind' => ['required', 'in:service,product']]);
        if ($data['kind'] === 'product') {
            app(EntitlementEvaluator::class)->authorize($business, 'inventory.enabled');
        }

        return response()->json(['items' => $this->workspace->catalogue($appointment, $data['search'] ?? '', $data['kind'])]);
    }

    public function preview(Request $request, Business $business, Appointment $appointment)
    {
        $this->assertAppointment($request, $business, $appointment);
        $data = $this->basketData($request);
        if (collect($data['items'])->contains(fn ($item) => ! empty($item['product_public_id']))) {
            app(EntitlementEvaluator::class)->authorize($business, 'inventory.enabled');
        }
        try {
            $quote = $this->basket->preview($appointment, $data);
            $quote['lines'] = collect($quote['lines'])->map(fn ($line) => collect($line)->only(['kind', 'description', 'quantity', 'unit_price_minor', 'discount_minor', 'tax_rate_bps', 'tax_minor', 'line_total_minor'])->all())->all();

            return response()->json(['quote' => $quote]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }
    }

    public function prepare(Request $request, Business $business, Appointment $appointment)
    {
        $this->assertAppointment($request, $business, $appointment);
        $data = $this->basketData($request);
        $data += $request->validate(['quote_key' => ['required', 'string', 'size:64']]);
        if (collect($data['items'])->contains(fn ($item) => ! empty($item['product_public_id']))) {
            app(EntitlementEvaluator::class)->authorize($business, 'inventory.enabled');
        }
        try {
            $sale = $this->basket->prepare($appointment, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }
        $this->issueCompletedReceipt($sale);

        return response()->json(['sale' => $this->workspace->sale($sale)]);
    }

    public function show(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->canAny([PermissionName::CheckoutManage->value, PermissionName::RevenueView->value]), 403);

        return response()->json(['sale' => $this->workspace->sale($sale)]);
    }

    public function payments(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100'], 'received_confirmed' => ['required', 'accepted'], 'payments' => ['required', 'array', 'min:1', 'max:8'], 'payments.*.method' => ['required', 'in:cash,card,upi,bank_transfer,payment_link,custom'], 'payments.*.amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'], 'payments.*.reference' => ['nullable', 'string', 'max:191']]);
        try {
            DB::transaction(function () use ($data, $sale, $request, $business) {
                $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);
                $hash = hash('sha256', json_encode($data['payments'], JSON_THROW_ON_ERROR));
                $existing = PaymentTransaction::query()->where('business_id', $sale->business_id)->where('idempotency_key', $data['idempotency_key'].'-0')->first();
                if ($existing) {
                    if ($existing->sale_id !== $sale->id || data_get($existing->evidence, 'command_hash') !== $hash) {
                        throw new DomainException('This key already belongs to a different payment. Reload the sale.');
                    }

                    return;
                }
                if (array_sum(array_column($data['payments'], 'amount_minor')) > $locked->balance_minor) {
                    throw new DomainException('Payments exceed the current balance. Reload and review the sale.');
                }
                foreach ($data['payments'] as $index => $payment) {
                    $transaction = $this->checkout->recordTender($locked, $payment['method'], $payment['amount_minor'], $data['idempotency_key'].'-'.$index, ['recorded_in' => 'checkout_workspace', 'manually_confirmed' => true, 'actor_user_id' => $request->user()->id, 'actor_membership_id' => $this->tenancy->membership()->id, 'actor_name' => $request->user()->name, 'command_hash' => $hash, 'reference' => $payment['reference'] ?? null]);
                    $this->audit->write('sale.payment.recorded', $business, $request->user(), $transaction, after: ['sale_id' => $sale->public_id, 'method' => $payment['method'], 'amount_minor' => $payment['amount_minor']]);
                }
                $this->issueCompletedReceipt($sale->fresh());
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage().' No payment records from this request were committed.']);
        }

        return response()->json(['sale' => $this->workspace->sale($sale->fresh())]);
    }

    public function applyDeposit(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        try {
            DB::transaction(function () use ($sale, $business, $request) {
                $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
                if ($sale->status === 'completed' && $sale->deposit_applied_minor > 0) {
                    return;
                }
                if ($sale->status !== 'open') {
                    throw new DomainException('Only an open sale can apply a deposit.');
                }
                $before = $sale->deposit_applied_minor;
                foreach (Deposit::query()->where('business_id', $business->id)->where('appointment_id', $sale->appointment_id)->where('currency_code', $sale->currency_code)->verified()->orderBy('id')->lockForUpdate()->get() as $deposit) {
                    if ($sale->balance_minor <= 0) {
                        break;
                    }
                    $sale = $this->checkout->applyDeposit($sale, $deposit, min($sale->balance_minor, $deposit->remainingMinor()), "workspace:{$sale->id}:deposit:{$deposit->id}");
                }
                $this->checkout->completeIfPaid($sale);
                if ($sale->deposit_applied_minor > $before) {
                    $this->audit->write('sale.deposit.applied', $business, $request->user(), $sale, after: ['deposit_applied_minor' => $sale->deposit_applied_minor]);
                }
                $this->issueCompletedReceipt($sale->fresh());
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['deposit' => $exception->getMessage()]);
        }

        return response()->json(['sale' => $this->workspace->sale($sale->fresh())]);
    }

    private function basketData(Request $request): array
    {
        return $request->validate(['items' => ['required', 'array', 'min:1', 'max:80'], 'items.*.booked_line_id' => ['nullable', 'integer'], 'items.*.service_public_id' => ['nullable', 'string'], 'items.*.product_public_id' => ['nullable', 'string'], 'items.*.staff_profile_id' => ['nullable', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'], 'items.*.unit_price_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'], 'items.*.discount_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'], 'tips' => ['array', 'max:20'], 'tips.*.staff_profile_id' => ['required', 'integer'], 'tips.*.amount_minor' => ['required', 'integer', 'min:0', 'max:1000000000'], 'reason' => ['nullable', 'string', 'max:1000'], 'apply_deposit' => ['boolean']]);
    }

    private function assertAppointment(Request $request, Business $business, Appointment $appointment): void
    {
        abort_unless($appointment->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $appointment->location_id);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        abort_unless($appointment->status === 'completed', 422, 'Complete the appointment before checkout.');
    }

    private function issueCompletedReceipt(Sale $sale): void
    {
        if ($sale->status !== 'completed') {
            return;
        }
        $this->receipts->issue($sale);
        if ($sale->appointment_id) {
            OperationalNotificationEvent::query()->firstOrCreate(['business_id' => $sale->business_id, 'idempotency_key' => 'sale:'.$sale->id.':payment-receipt'], ['event_type' => 'payment.receipt', 'subject_type' => Appointment::class, 'subject_id' => $sale->appointment_id, 'payload' => ['amount_minor' => $sale->total_minor, 'currency' => $sale->currency_code, 'sale_public_id' => $sale->public_id], 'status' => 'pending', 'occurred_at' => now()]);
        }
    }

    public function open(Request $request, Business $business, Appointment $appointment)
    {
        abort_unless($appointment->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $appointment->location_id);
        abort_unless($appointment->status === 'completed', 422, 'Complete the appointment before checkout.');
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $data = $request->validate(['lines' => ['array'], 'lines.*.kind' => ['nullable', 'in:addon,product,service'], 'lines.*.product_public_id' => ['nullable', 'string'], 'lines.*.description' => ['required_without:lines.*.product_public_id', 'nullable', 'string', 'max:255'], 'lines.*.quantity' => ['required_with:lines', 'integer', 'min:1'], 'lines.*.unit_price_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.tax_rate_bps' => ['nullable', 'integer', 'min:0', 'max:100000'], 'lines.*.discount_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.staff_profile_id' => ['nullable', 'integer'], 'tips' => ['array'], 'tips.*.staff_profile_id' => ['nullable', 'integer'], 'tips.*.amount_minor' => ['required', 'integer', 'min:0'], 'discount_approved' => ['boolean']]);
        if (! empty($data['lines']) || ! empty($data['tips']) || ! empty($data['discount_approved'])) {
            throw ValidationException::withMessages(['checkout' => 'Review catalogue items and adjustments through the checkout preview before saving.']);
        }
        $sale = $this->checkout->openForAppointment($appointment);

        return response()->json(['sale' => $this->workspace->sale($sale)]);
    }

    public function tender(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->can(PermissionName::CheckoutManage->value), 403);
        $data = $request->validate(['method' => ['required', 'in:cash,card,upi,bank_transfer,payment_link,custom,pay_later'], 'amount_minor' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:128'], 'provider' => ['nullable', 'string', 'max:32'], 'provider_reference' => ['nullable', 'string', 'max:191'], 'evidence' => ['array']]);
        if (! empty($data['provider']) || ! empty($data['provider_reference'])) {
            throw ValidationException::withMessages(['payment' => 'Provider evidence must come from a verified payment integration.']);
        }
        if ($data['method'] === 'pay_later') {
            throw ValidationException::withMessages(['payment' => 'Leave the sale open for pay later. No money has been received.']);
        }
        try {
            $payment = $this->checkout->recordTender($sale, $data['method'], $data['amount_minor'], $data['idempotency_key'], ['actor_user_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'manually_confirmed' => true]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        $completedSale = $sale->fresh();
        $this->issueCompletedReceipt($completedSale);

        return response()->json(['payment' => $payment->only(['public_id', 'method', 'amount_minor', 'status']), 'sale' => $this->workspace->sale($completedSale)]);
    }

    public function refund(Request $request, Business $business, Sale $sale, PaymentTransaction $payment)
    {
        abort_unless($sale->business_id === $business->id && $payment->business_id === $business->id && $payment->sale_id === $sale->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->can(PermissionName::RefundIssue->value), 403);
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:128'], 'reason' => ['required', 'string', 'max:1000'], 'kind' => ['nullable', 'in:refund,void'], 'line_refunds' => ['array'], 'line_refunds.*.sale_line_id' => ['required', 'integer'], 'line_refunds.*.amount_minor' => ['required', 'integer', 'min:0'], 'line_refunds.*.quantity' => ['nullable', 'integer', 'min:0'], 'line_refunds.*.disposition' => ['nullable', 'in:restock,write_off,customer_keeps,not_applicable']]);
        $request->validate(['returned_confirmed' => ['required', 'accepted']]);
        if ($payment->provider) {
            throw ValidationException::withMessages(['refund' => 'This provider payment needs the approved provider refund and reconciliation workflow. No refund was submitted.']);
        }
        try {
            $refund = DB::transaction(function () use ($sale, $payment, $data, $business, $request) {
                $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
                $already = PaymentTransaction::query()->where('business_id', $business->id)->where('idempotency_key', $data['idempotency_key'])->exists();
                $refund = $this->checkout->refund($sale, $payment, $data['amount_minor'], $data['idempotency_key'], $data['reason'], $data['line_refunds'] ?? [], $data['kind'] ?? 'refund', ['actor_user_id' => $request->user()->id, 'actor_membership_id' => $this->tenancy->membership()->id, 'actor_name' => $request->user()->name, 'manually_confirmed' => true]);
                if (! $already) {
                    $this->audit->write('sale.refund.issued', $business, $request->user(), $refund, $data['reason'], [], ['sale_id' => $sale->public_id, 'amount_minor' => $refund->amount_minor]);
                }

                return $refund;
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['refund' => $exception->getMessage().' No refund record was committed.']);
        }

        return response()->json(['refund' => $refund->only(['public_id', 'amount_minor', 'kind']), 'sale' => $this->workspace->sale($sale->fresh())]);
    }

    public function receipt(Request $request, Business $business, Sale $sale)
    {
        abort_unless($sale->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $sale->location_id);
        abort_unless($request->user()->canAny([PermissionName::RevenueView->value, PermissionName::CheckoutManage->value]), 403);
        abort_unless($sale->status === 'completed', 422, 'A final receipt is available after the sale is fully paid.');
        $receipt = $this->receipts->issue($sale);

        $sale->loadMissing('appointment.business');

        return view('receipts.sale', [
            'receipt' => $receipt,
            'receiptTimeZone' => $sale->appointment?->time_zone ?? 'UTC',
            'receiptLocale' => $sale->appointment?->business?->locale ?? 'en',
        ]);
    }

    public function close(Request $request, Business $business, Location $location)
    {
        abort_unless($location->business_id === $business->id, 404);
        $this->assertLocationAccess($business, $location->id);
        abort_unless($request->user()->can(PermissionName::CashCloseManage->value), 403);
        $data = $request->validate(['business_date' => ['required', 'date'], 'opening_cash_minor' => ['required', 'integer', 'min:0'], 'actual_cash_minor' => ['required', 'integer', 'min:0'], 'variance_reason' => ['nullable', 'string', 'max:1000']]);
        $membership = $this->tenancy->membership();
        $close = $this->cash->close($location, CarbonImmutable::parse($data['business_date'], $location->time_zone), $data['opening_cash_minor'], $data['actual_cash_minor'], $data['variance_reason'] ?? null, $membership->id);
        $this->audit->write('cash.close.completed', $business, $request->user(), $close, $data['variance_reason'] ?? null, [], ['expected_cash_minor' => $close->expected_cash_minor, 'actual_cash_minor' => $close->actual_cash_minor, 'variance_minor' => $close->variance_minor]);

        return response()->json(['cash_close' => $close]);
    }

    /** @return list<int> */
    private function accessibleLocationIds(Business $business): array
    {
        $membership = $this->tenancy->membership();
        abort_unless($membership && $membership->business_id === $business->id && $membership->isActive(), 403);

        return $membership->hasRole('owner', 'web')
            ? $business->locations()->pluck('id')->all()
            : $membership->locations()->where('locations.business_id', $business->id)->pluck('locations.id')->all();
    }

    private function assertLocationAccess(Business $business, int $locationId): void
    {
        abort_unless(in_array($locationId, $this->accessibleLocationIds($business), true), 403);
    }
}
