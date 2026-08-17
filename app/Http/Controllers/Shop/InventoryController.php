<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Models\ProductCategory;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\Inventory\Services\ProductCatalogService;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Http\Controllers\Controller;
use App\Support\Audit\AuditWriter;
use App\Support\Exports\TenantExportName;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryLedger $inventory, private readonly ProductCatalogService $catalog, private readonly TenantContext $tenancy, private readonly AuditWriter $audit) {}

    public function index(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::InventoryManage->value), 403);
        $membership = $this->tenancy->membership();
        $allLocationIds = Location::query()->forBusiness($business)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $locationIds = $membership->hasRole('owner', 'web') ? $allLocationIds : $membership->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $allLocations = count($locationIds) === count($allLocationIds) && array_diff($allLocationIds, $locationIds) === [];
        $levels = DB::table('inventory_levels')->where('business_id', $business->id)->whereIn('location_id', $locationIds)->select('inventory_product_id')->selectRaw('sum(current_stock) as scoped_stock')->groupBy('inventory_product_id')->pluck('scoped_stock', 'inventory_product_id');
        $products = InventoryProduct::query()->forBusiness($business)->with('category')->withCount(['movements' => fn ($query) => $query->whereIn('location_id', $locationIds)])->orderBy('name')->paginate(50)->through(function (InventoryProduct $product) use ($levels, $allLocations): array {
            $stock = isset($levels[$product->id]) ? (int) $levels[$product->id] : ($allLocations ? $product->current_stock : 0);

            return [...$product->only(['public_id', 'name', 'sku', 'barcode', 'sale_price_minor', 'cost_minor', 'tax_rate_bps', 'currency_code', 'status', 'low_stock_threshold']), 'current_stock' => $stock, 'category' => $product->category?->name, 'low_stock' => $stock <= $product->low_stock_threshold, 'movement_count' => $product->movements_count];
        });

        return Inertia::render('Shop/Inventory', ['products' => $products, 'freshAt' => now()->utc()->toIso8601String(), 'timeZone' => $business->time_zone, 'locations' => Location::query()->forBusiness($business)->whereIn('id', $locationIds)->get(['public_id', 'name', 'time_zone'])]);
    }

    public function store(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::InventoryManage->value), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'], 'sku' => ['required', 'string', 'max:96'], 'barcode' => ['nullable', 'string', 'max:128'], 'sale_price_minor' => ['required', 'integer', 'min:0'], 'cost_minor' => ['required', 'integer', 'min:0'], 'tax_rate_bps' => ['required', 'integer', 'min:0', 'max:100000'], 'currency_code' => ['required', 'string', 'size:3'], 'status' => ['required', 'in:active,inactive'], 'low_stock_threshold' => ['required', 'integer', 'min:0']]);
        $category = empty($data['category']) ? null : ProductCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => trim($data['category'])], ['status' => 'active']);
        $product = InventoryProduct::query()->create([...$data, 'business_id' => $business->id, 'product_category_id' => $category?->id, 'currency_code' => strtoupper($data['currency_code']), 'current_stock' => 0]);

        return response()->json(['product' => $product], 201);
    }

    public function receipt(Request $request, Business $business, InventoryProduct $product)
    {
        return $this->movement($request, $business, $product, true);
    }

    public function adjustment(Request $request, Business $business, InventoryProduct $product)
    {
        return $this->movement($request, $business, $product, false);
    }

    public function import(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::InventoryManage->value), 403);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], 'location_id' => ['required', 'string']]);
        $location = Location::query()->forBusiness($business)->where('public_id', $data['location_id'])->firstOrFail();
        abort_unless($this->tenancy->membership()->hasRole('owner', 'web') || $this->tenancy->membership()->locations()->where('locations.id', $location->id)->exists(), 403);
        $result = $this->catalog->importCsv($business, (string) file_get_contents($request->file('file')->getRealPath()), $location);

        return response()->json($result, $result['errors'] === [] ? 200 : 422);
    }

    public function export(Request $request, Business $business)
    {
        abort_unless($request->user()->can(PermissionName::InventoryManage->value) && $request->user()->can(PermissionName::ExportCreate->value), 403);

        $membership = $this->tenancy->membership();
        $locationIds = $membership->hasRole('owner', 'web') ? null : $membership->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

        return response($this->catalog->exportCsv($business, $locationIds), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.TenantExportName::make($business, 'products').'"']);
    }

    private function movement(Request $request, Business $business, InventoryProduct $product, bool $receipt)
    {
        abort_unless($product->business_id === $business->id, 404);
        abort_unless($request->user()->can(PermissionName::InventoryManage->value), 403);
        $data = $request->validate(['location_id' => ['required', 'string'], 'quantity' => ['required', 'integer', $receipt ? 'min:1' : 'not_in:0'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'string', 'max:160']]);
        $location = Location::query()->forBusiness($business)->where('public_id', $data['location_id'])->firstOrFail();
        $membership = $this->tenancy->membership();
        abort_unless($membership->hasRole('owner', 'web') || $membership->locations()->where('locations.id', $location->id)->exists(), 403);
        $movement = $receipt ? $this->inventory->receive($product, $location, $data['quantity'], $membership, $data['reason'], $data['idempotency_key']) : $this->inventory->adjust($product, $location, $data['quantity'], $membership, $data['reason'], $data['idempotency_key']);
        $this->audit->write('inventory.stock.'.($receipt ? 'received' : 'adjusted'), $business, $request->user(), $movement, $data['reason'], ['quantity_before' => $movement->quantity_before], ['quantity_after' => $movement->quantity_after, 'delta' => $movement->quantity_delta]);

        return response()->json(['movement' => $movement, 'product' => $product->fresh()]);
    }
}
