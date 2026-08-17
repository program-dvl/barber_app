<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Models\ProductCategory;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProductCatalogService
{
    public function __construct(private readonly InventoryLedger $inventory) {}

    /** @return array{created:int,updated:int,errors:list<array{row:int,message:string}>} */
    public function importCsv(Business $business, string $csv, ?Location $openingLocation = null): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream);
        $required = ['name', 'category', 'sku', 'barcode', 'sale_price_minor', 'cost_minor', 'tax_rate_bps', 'currency_code', 'status', 'current_stock', 'low_stock_threshold'];
        if ($header !== $required) {
            throw new DomainException('Product CSV header does not match the version 1 template.');
        }
        $created = $updated = 0;
        $errors = [];
        $rowNumber = 1;
        while (($values = fgetcsv($stream)) !== false) {
            $rowNumber++;
            try {
                if (count($values) !== count($required)) {
                    throw new DomainException('Column count does not match the template.');
                }
                $row = array_combine($required, $values);
                if (trim($row['name']) === '' || trim($row['sku']) === '' || ! in_array($row['status'], ['active', 'inactive'], true)) {
                    throw new DomainException('Name, SKU, and a valid status are required.');
                }
                foreach (['sale_price_minor', 'cost_minor', 'tax_rate_bps', 'current_stock', 'low_stock_threshold'] as $integer) {
                    if (filter_var($row[$integer], FILTER_VALIDATE_INT) === false || (int) $row[$integer] < 0) {
                        throw new DomainException("{$integer} must be a non-negative integer.");
                    }
                }
                DB::transaction(function () use ($business, $row, $openingLocation, &$created, &$updated): void {
                    $category = trim($row['category']) === '' ? null : ProductCategory::query()->firstOrCreate(
                        ['business_id' => $business->id, 'name' => trim($row['category'])],
                        ['status' => 'active'],
                    );
                    $existing = InventoryProduct::query()->forBusiness($business)->where('sku', trim($row['sku']))->first();
                    $product = InventoryProduct::query()->updateOrCreate(
                        ['business_id' => $business->id, 'sku' => trim($row['sku'])],
                        ['product_category_id' => $category?->id, 'name' => trim($row['name']), 'barcode' => trim($row['barcode']) ?: null, 'sale_price_minor' => (int) $row['sale_price_minor'], 'cost_minor' => (int) $row['cost_minor'], 'tax_rate_bps' => (int) $row['tax_rate_bps'], 'currency_code' => strtoupper($row['currency_code']), 'status' => $row['status'], 'current_stock' => $existing?->current_stock ?? 0, 'low_stock_threshold' => (int) $row['low_stock_threshold']],
                    );
                    if (! $existing && (int) $row['current_stock'] > 0) {
                        $location = $openingLocation ?? Location::query()->forBusiness($business)->where('is_active', true)->orderBy('id')->first();
                        if (! $location) {
                            throw new DomainException('Opening stock import requires an active location.');
                        }
                        $this->inventory->importOpeningStock($product, $location, (int) $row['current_stock'], 'product-import:'.hash('sha256', $row['sku'].':'.$row['current_stock']));
                    }
                    $existing ? $updated++ : $created++;
                });
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }
        fclose($stream);

        return compact('created', 'updated', 'errors');
    }

    /** @param list<int>|null $locationIds */
    public function exportCsv(Business $business, ?array $locationIds = null): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['name', 'category', 'sku', 'barcode', 'sale_price_minor', 'cost_minor', 'tax_rate_bps', 'currency_code', 'status', 'current_stock', 'low_stock_threshold']);
        $allLocationIds = Location::query()->forBusiness($business)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $scope = $locationIds ?? $allLocationIds;
        $allLocations = count($scope) === count($allLocationIds) && array_diff($allLocationIds, $scope) === [];
        $levels = DB::table('inventory_levels')->where('business_id', $business->id)->whereIn('location_id', $scope)->select('inventory_product_id')->selectRaw('sum(current_stock) as scoped_stock')->groupBy('inventory_product_id')->pluck('scoped_stock', 'inventory_product_id');
        InventoryProduct::query()->forBusiness($business)->with('category')->orderBy('sku')->each(function (InventoryProduct $product) use ($stream, $levels, $allLocations): void {
            $stock = isset($levels[$product->id]) ? (int) $levels[$product->id] : ($allLocations ? $product->current_stock : 0);
            fputcsv($stream, [$product->name, $product->category?->name, $product->sku, $product->barcode, $product->sale_price_minor, $product->cost_minor, $product->tax_rate_bps, $product->currency_code, $product->status, $stock, $product->low_stock_threshold]);
        });
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
