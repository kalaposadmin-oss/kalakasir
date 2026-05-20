<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductImportService
{
    private const SUPPORTED_EXTENSIONS = ['csv', 'txt', 'xls', 'xlsx'];

    private const FIELD_LABELS = [
        'ignore' => 'Abaikan',
        'sku' => 'SKU / Barcode',
        'name' => 'Nama Produk',
        'item_type' => 'Tipe Produk',
        'price' => 'Harga Jual',
        'hpp_average' => 'HPP / Modal',
        'qty_on_hand' => 'Stok Awal',
        'track_stock' => 'Gunakan Stok',
        'category' => 'Kategori',
        'supplier' => 'Supplier',
        'unit' => 'Satuan',
        'min_stock' => 'Stok Minimum',
    ];

    private const HEADER_ALIASES = [
        'sku' => 'sku',
        'barcode' => 'sku',
        'kode' => 'sku',
        'kode produk' => 'sku',
        'kode barang' => 'sku',
        'name' => 'name',
        'nama' => 'name',
        'nama produk' => 'name',
        'nama barang' => 'name',
        'produk' => 'name',
        'product' => 'name',
        'product name' => 'name',
        'item type' => 'item_type',
        'product type' => 'item_type',
        'tipe' => 'item_type',
        'tipe produk' => 'item_type',
        'tipe barang' => 'item_type',
        'jenis' => 'item_type',
        'jenis produk' => 'item_type',
        'jenis barang' => 'item_type',
        'price' => 'price',
        'harga' => 'price',
        'harga jual' => 'price',
        'selling price' => 'price',
        'hpp' => 'hpp_average',
        'hpp average' => 'hpp_average',
        'hpp_average' => 'hpp_average',
        'modal' => 'hpp_average',
        'harga modal' => 'hpp_average',
        'cost' => 'hpp_average',
        'unit cost' => 'hpp_average',
        'qty' => 'qty_on_hand',
        'quantity' => 'qty_on_hand',
        'qty on hand' => 'qty_on_hand',
        'qty_on_hand' => 'qty_on_hand',
        'stock' => 'qty_on_hand',
        'stok' => 'qty_on_hand',
        'stok awal' => 'qty_on_hand',
        'initial stock' => 'qty_on_hand',
        'track stock' => 'track_stock',
        'track_stock' => 'track_stock',
        'gunakan stok' => 'track_stock',
        'pakai stok' => 'track_stock',
        'category' => 'category',
        'kategori' => 'category',
        'supplier' => 'supplier',
        'suplier' => 'supplier',
        'nama supplier' => 'supplier',
        'nama suplier' => 'supplier',
        'pemasok' => 'supplier',
        'vendor' => 'supplier',
        'unit' => 'unit',
        'satuan' => 'unit',
        'min stock' => 'min_stock',
        'min_stock' => 'min_stock',
        'stok minimum' => 'min_stock',
        'minimum stock' => 'min_stock',
    ];

    private ?array $productColumns = null;

    private ?array $categoryColumns = null;

    private array $categoryCache = [];

    private array $supplierCache = [];

    public static function supportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    public static function fieldLabels(): array
    {
        return self::FIELD_LABELS;
    }

    public static function suggestFieldForHeader(string $header): string
    {
        $key = self::normalizeHeader($header);

        if (array_key_exists($key, self::HEADER_ALIASES)) {
            return self::HEADER_ALIASES[$key];
        }

        if (str_contains($key, 'track') || str_contains($key, 'gunakan stok') || str_contains($key, 'pakai stok')) {
            return 'track_stock';
        }

        if ((str_contains($key, 'min') || str_contains($key, 'minimum')) && (str_contains($key, 'stock') || str_contains($key, 'stok'))) {
            return 'min_stock';
        }

        if (str_contains($key, 'sku') || str_contains($key, 'barcode') || str_contains($key, 'kode')) {
            return 'sku';
        }

        if ((str_contains($key, 'tipe') || str_contains($key, 'jenis') || str_contains($key, 'type'))
            && (str_contains($key, 'produk') || str_contains($key, 'product') || str_contains($key, 'barang') || str_contains($key, 'item') || in_array($key, ['tipe', 'jenis', 'type'], true))) {
            return 'item_type';
        }

        if (str_contains($key, 'hpp') || str_contains($key, 'modal') || str_contains($key, 'cost')) {
            return 'hpp_average';
        }

        if (str_contains($key, 'harga') || str_contains($key, 'price')) {
            return 'price';
        }

        if (str_contains($key, 'qty') || str_contains($key, 'stock') || str_contains($key, 'stok')) {
            return 'qty_on_hand';
        }

        if (str_contains($key, 'kategori') || str_contains($key, 'category')) {
            return 'category';
        }

        if (str_contains($key, 'supplier') || str_contains($key, 'suplier') || str_contains($key, 'pemasok') || str_contains($key, 'vendor')) {
            return 'supplier';
        }

        if (str_contains($key, 'nama') || str_contains($key, 'name') || str_contains($key, 'produk') || str_contains($key, 'product')) {
            return 'name';
        }

        if (str_contains($key, 'satuan') || str_contains($key, 'unit')) {
            return 'unit';
        }

        return 'ignore';
    }

    /**
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, total_rows: int}
     */
    public function parseFile(string $path, ?string $extension = null, ?int $limit = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('File import tidak ditemukan.');
        }

        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new RuntimeException('Tipe file tidak didukung. Gunakan CSV, TXT, XLS, atau XLSX.');
        }

        return in_array($extension, ['csv', 'txt'], true)
            ? $this->parseCsv($path, $limit)
            : $this->parseSpreadsheet($path, $limit);
    }

    /**
     * @param  array<int|string, string|null>  $mapping
     * @return array{created:int, updated:int, skipped:int, errors:list<string>, total_rows:int}
     */
    public function importProducts(string $tenantId, string $path, array $mapping, ?string $userId = null): array
    {
        $parsed = $this->parseFile($path);
        $normalizedMapping = $this->normalizeMapping($mapping);

        if (! in_array('name', array_values($normalizedMapping), true)) {
            throw new RuntimeException('Kolom Nama Produk wajib dipetakan.');
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'total_rows' => $parsed['total_rows'],
        ];

        foreach ($parsed['rows'] as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            try {
                $mapped = $this->mapRow($row, $normalizedMapping);

                if ($this->rowIsEmpty($mapped)) {
                    continue;
                }

                $result = DB::transaction(function () use ($tenantId, $mapped, $userId) {
                    return $this->upsertProduct($tenantId, $mapped, $userId);
                });

                $summary[$result]++;
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = 'Baris '.$rowNumber.': '.$exception->getMessage();
            }
        }

        Cache::forget("products:index:{$tenantId}");
        Cache::forget("categories:index:{$tenantId}");

        return $summary;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->lower()
            ->replaceMatches('/[_\-]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return (string) $header;
    }

    /**
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, total_rows: int}
     */
    private function parseCsv(string $path, ?int $limit): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membuka file CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return ['headers' => [], 'rows' => [], 'total_rows' => 0];
        }

        $skipFirstLine = false;
        $delimiter = $this->detectCsvDelimiter($firstLine);
        if (preg_match('/^(?:\xEF\xBB\xBF)?sep=(.)\s*$/i', trim($firstLine), $matches) === 1) {
            $delimiter = $matches[1];
            $skipFirstLine = true;
        }
        rewind($handle);
        if ($skipFirstLine) {
            fgets($handle);
        }

        $headers = [];
        $rows = [];
        $totalRows = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === []) {
                $headers = array_values(array_map(fn ($value) => $this->cleanCellValue($value), $data));
                if ($headers !== []) {
                    $headers[0] = (string) Str::of((string) $headers[0])->replace("\xEF\xBB\xBF", '');
                }
                continue;
            }

            $row = $this->normalizeRow($data, count($headers));
            if ($this->numericRowIsEmpty($row)) {
                continue;
            }

            $totalRows++;
            if ($limit === null || count($rows) < $limit) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows];
    }

    /**
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, total_rows: int}
     */
    private function parseSpreadsheet(string $path, ?int $limit): array
    {
        if (! class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new RuntimeException('Import XLS/XLSX membutuhkan paket phpoffice/phpspreadsheet. Gunakan CSV atau pasang paket tersebut di server.');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rows = [];
        $totalRows = 0;

        foreach ($sheet->getRowIterator() as $sheetRow) {
            $cellIterator = $sheetRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $values = [];
            foreach ($cellIterator as $cell) {
                $values[] = $this->cleanCellValue($cell->getFormattedValue());
            }

            if ($headers === []) {
                $headers = array_values(array_map(fn ($value) => $this->cleanCellValue($value), $values));
                continue;
            }

            $row = $this->normalizeRow($values, count($headers));
            if ($this->numericRowIsEmpty($row)) {
                continue;
            }

            $totalRows++;
            if ($limit === null || count($rows) < $limit) {
                $rows[] = $row;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => $totalRows];
    }

    private function detectCsvDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $scores = [];

        foreach ($candidates as $candidate) {
            $scores[$candidate] = substr_count($line, $candidate);
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function cleanCellValue(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private function normalizeRow(array $values, int $headerCount): array
    {
        $row = [];

        for ($index = 0; $index < $headerCount; $index++) {
            $row[$index] = array_key_exists($index, $values)
                ? $this->cleanCellValue($values[$index])
                : null;
        }

        return $row;
    }

    /**
     * @param  array<int|string, string|null>  $mapping
     * @return array<int, string>
     */
    private function normalizeMapping(array $mapping): array
    {
        $normalized = [];
        $allowed = array_keys(self::FIELD_LABELS);

        foreach ($mapping as $index => $field) {
            $target = is_string($field) ? trim($field) : 'ignore';
            $normalized[(int) $index] = in_array($target, $allowed, true) ? $target : 'ignore';
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $mapping
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $mapping): array
    {
        $mapped = [];

        foreach ($row as $index => $value) {
            $target = $mapping[$index] ?? 'ignore';
            if ($target === 'ignore') {
                continue;
            }

            $mapped[$target] = $this->cleanCellValue($value);
        }

        return $mapped;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function numericRowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanCellValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function rowIsEmpty(array $mapped): bool
    {
        foreach ($mapped as $value) {
            if ($this->cleanCellValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertProduct(string $tenantId, array $mapped, ?string $userId): string
    {
        $name = $this->cleanCellValue($mapped['name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Nama produk wajib diisi.');
        }

        $sku = $this->cleanCellValue($mapped['sku'] ?? '');
        $product = null;

        if ($sku !== '') {
            $product = Product::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->first();
        }

        if (! $product) {
            $product = Product::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->first();
        }

        if ($sku === '') {
            $sku = $product?->sku ?: $this->buildUniqueSku($tenantId, $name);
        }

        $conflictingSku = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->when($product, fn ($query) => $query->where('id', '!=', $product->id))
            ->exists();

        if ($conflictingSku) {
            throw new RuntimeException("SKU {$sku} sudah dipakai produk lain.");
        }

        $now = CarbonImmutable::now('UTC');
        $price = array_key_exists('price', $mapped)
            ? $this->normalizeMoney($mapped['price'])
            : (float) ($product?->price ?? 0);
        $hppAverage = array_key_exists('hpp_average', $mapped)
            ? $this->normalizeMoney($mapped['hpp_average'])
            : (float) ($product?->hpp_average ?? 0);
        $initialStock = $this->normalizeQty($mapped['qty_on_hand'] ?? null);
        $itemTypeMapped = array_key_exists('item_type', $mapped);
        $itemType = $itemTypeMapped
            ? $this->normalizeItemType($mapped['item_type'], $product)
            : $this->resolveCurrentItemType($product);
        $productMode = $this->resolveProductModeForImport($itemType, $product);
        $productionMode = $this->resolveProductionModeForImport($itemType, $productMode, $product);
        $trackStockDefault = $product ? (bool) $product->track_stock : true;
        if ($itemType === 'service') {
            $trackStockDefault = false;
        }
        if ($initialStock > 0 && ! array_key_exists('track_stock', $mapped)) {
            $trackStockDefault = true;
        }
        $trackStock = array_key_exists('track_stock', $mapped)
            ? $this->normalizeBoolean($mapped['track_stock'], $trackStockDefault)
            : $trackStockDefault;
        if ($itemType === 'service') {
            $trackStock = false;
        }
        $categoryId = array_key_exists('category', $mapped)
            ? $this->resolveCategoryId($tenantId, $mapped['category'], $now)
            : $product?->category_id;
        $supplierId = array_key_exists('supplier', $mapped)
            ? $this->resolveSupplierId($tenantId, $mapped['supplier'], $now)
            : $product?->supplier_id;
        $minStock = array_key_exists('min_stock', $mapped)
            ? $this->normalizeQty($mapped['min_stock'])
            : (float) ($product?->min_stock ?? 0);

        if ($itemType === 'service') {
            $minStock = 0;
        }

        if ($itemType === 'service' && $initialStock > 0) {
            throw new RuntimeException('Stok awal tidak berlaku untuk produk jasa / service.');
        }

        if ($initialStock > 0 && $hppAverage <= 0) {
            throw new RuntimeException('HPP / modal wajib lebih dari 0 saat stok awal diisi.');
        }

        if ($initialStock > 0 && ! $trackStock) {
            throw new RuntimeException('Gunakan Stok harus aktif saat stok awal diisi.');
        }

        if ($productionMode === Product::PRODUCTION_MODE_BATCH && ! $trackStock) {
            throw new RuntimeException('Produk batch production wajib Gunakan Stok aktif.');
        }

        $createAttributes = $this->filterProductColumns([
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'sku' => $sku,
            'name' => $name,
            'price' => $price,
            'hpp_average' => $hppAverage,
            'min_stock' => $minStock,
            'unit' => array_key_exists('unit', $mapped) ? $this->nullableString($mapped['unit'], 30) : null,
            'supplier_id' => $supplierId,
            'is_active' => true,
            'track_stock' => $trackStock,
            'product_mode' => $productMode,
            'production_mode' => $productionMode,
            'item_type' => $itemType,
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($product) {
            $updateAttributes = [
                'sku' => $sku,
                'name' => $name,
                'price' => $price,
                'hpp_average' => $hppAverage,
                'track_stock' => $trackStock,
                'item_type' => $itemType,
                'product_mode' => $productMode,
                'production_mode' => $productionMode,
                'updated_by' => $userId,
                'updated_at' => $now,
            ];

            if (array_key_exists('category', $mapped)) {
                $updateAttributes['category_id'] = $categoryId;
            }

            if (array_key_exists('supplier', $mapped)) {
                $updateAttributes['supplier_id'] = $supplierId;
            }

            if (array_key_exists('min_stock', $mapped)) {
                $updateAttributes['min_stock'] = $minStock;
            } elseif ($itemType === 'service') {
                $updateAttributes['min_stock'] = 0;
            }

            if (array_key_exists('unit', $mapped)) {
                $updateAttributes['unit'] = $this->nullableString($mapped['unit'], 30);
            }

            if ($itemTypeMapped && in_array($itemType, ['raw_material', 'service'], true)) {
                $updateAttributes['bom_items'] = json_encode([]);
                $updateAttributes['recipe_output_qty'] = null;
            }

            DB::table('products')
                ->where('tenant_id', $tenantId)
                ->where('id', $product->id)
                ->update($this->filterProductColumns($updateAttributes));

            $productId = (string) $product->id;
            $result = 'updated';
        } else {
            $productId = (string) Str::uuid();
            $createAttributes['id'] = $productId;

            DB::table('products')->insert($createAttributes);
            $result = 'created';
        }

        if ($initialStock > 0 && $trackStock) {
            $this->postInitialStock(
                tenantId: $tenantId,
                productId: $productId,
                qty: $initialStock,
                unitCost: $hppAverage,
                actorId: $userId,
                now: $now,
            );
        }

        return $result;
    }

    private function normalizeItemType(mixed $value, ?Product $product): string
    {
        $raw = $this->cleanCellValue($value);
        if ($raw === '') {
            return $this->resolveCurrentItemType($product);
        }

        $normalized = Str::of($raw)
            ->lower()
            ->replaceMatches('/[_\-\/]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        $aliases = [
            'finished_good' => [
                'finished good',
                'barang jual',
                'barang jadi',
                'produk jual',
                'produk jadi',
                'jual',
                'jualan',
                'barang',
                'produk',
            ],
            'raw_material' => [
                'raw material',
                'bahan baku',
                'bahan mentah',
                'bahan',
                'material',
                'raw',
                'add on',
                'addon',
            ],
            'packaging' => [
                'packaging',
                'kemasan',
                'bungkus',
                'box',
                'dus',
                'label',
                'cup',
            ],
            'supply' => [
                'supply',
                'supplies',
                'perlengkapan',
                'habis pakai',
                'consumable',
            ],
            'small_tool' => [
                'small tool',
                'alat kecil',
                'peralatan kecil',
                'tools',
                'tool',
            ],
            'service' => [
                'service',
                'jasa',
                'jasa service',
                'layanan',
            ],
        ];

        foreach ($aliases as $itemType => $values) {
            if (in_array($normalized, $values, true)) {
                return $itemType;
            }
        }

        throw new RuntimeException('Tipe Produk tidak dikenali. Gunakan barang_jual, bahan_baku, packaging, perlengkapan, alat_kecil, atau jasa.');
    }

    private function resolveCurrentItemType(?Product $product): string
    {
        if (! $product) {
            return 'finished_good';
        }

        $itemType = ProductItemTypeMapper::resolve(
            is_string($product->item_type ?? null) ? $product->item_type : null,
            (string) ($product->product_mode ?? 'default')
        );

        return in_array($itemType, ProductItemTypeMapper::ITEM_TYPES, true)
            ? $itemType
            : 'finished_good';
    }

    private function resolveProductModeForImport(string $itemType, ?Product $product): string
    {
        if (ProductItemTypeMapper::isStockedNonSellable($itemType)) {
            return 'add_on';
        }

        if ($itemType === 'service') {
            return 'default';
        }

        $currentMode = (string) ($product?->product_mode ?? '');
        if ($currentMode !== ''
            && $currentMode !== 'add_on'
            && in_array($currentMode, ProductItemTypeMapper::PRODUCT_MODES, true)) {
            return $currentMode;
        }

        return 'default';
    }

    private function resolveProductionModeForImport(string $itemType, string $productMode, ?Product $product): string
    {
        if ($itemType !== 'finished_good') {
            return Product::PRODUCTION_MODE_NONE;
        }

        if ($productMode === 'add_on') {
            return Product::PRODUCTION_MODE_NONE;
        }

        $currentMode = (string) ($product?->production_mode ?? '');
        if ($currentMode !== '' && in_array($currentMode, Product::PRODUCTION_MODES, true)) {
            return $currentMode;
        }

        return Product::PRODUCTION_MODE_NONE;
    }

    private function buildUniqueSku(string $tenantId, string $name): string
    {
        $base = Str::upper(Str::slug($name));
        if ($base === '') {
            $base = 'SKU-'.Str::upper(Str::random(6));
        }

        $base = Str::limit($base, 80, '');
        $sku = $base;
        $counter = 1;

        while (DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->exists()) {
            $counter++;
            $sku = $base.'-'.$counter;
        }

        return $sku;
    }

    private function resolveCategoryId(string $tenantId, mixed $value, CarbonImmutable $now): ?string
    {
        $category = $this->cleanCellValue($value);
        if ($category === '') {
            return null;
        }

        if (Str::isUuid($category)) {
            $categoryId = Category::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $category)
                ->value('id');

            if ($categoryId) {
                return (string) $categoryId;
            }
        }

        $cacheKey = mb_strtolower($category);
        $this->categoryCache[$tenantId] ??= $this->loadCategoryCache($tenantId);

        if (array_key_exists($cacheKey, $this->categoryCache[$tenantId])) {
            return $this->categoryCache[$tenantId][$cacheKey];
        }

        $slug = $this->buildUniqueCategorySlug($tenantId, $category);
        $categoryId = (string) Str::uuid();

        $payload = $this->filterCategoryColumns([
            'id' => $categoryId,
            'tenant_id' => $tenantId,
            'name' => $category,
            'slug' => $slug,
            'description' => null,
            'icon' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('categories')->insert($payload);
        $this->categoryCache[$tenantId][$cacheKey] = $categoryId;

        return $categoryId;
    }

    private function resolveSupplierId(string $tenantId, mixed $value, CarbonImmutable $now): ?string
    {
        $supplier = $this->cleanCellValue($value);
        if ($supplier === '') {
            return null;
        }

        if (Str::isUuid($supplier)) {
            $supplierId = Supplier::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $supplier)
                ->value('id');

            if ($supplierId) {
                return (string) $supplierId;
            }
        }

        $cacheKey = mb_strtolower($supplier);
        $this->supplierCache[$tenantId] ??= $this->loadSupplierCache($tenantId);

        if (array_key_exists($cacheKey, $this->supplierCache[$tenantId])) {
            return $this->supplierCache[$tenantId][$cacheKey];
        }

        $supplierId = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => Str::limit($supplier, 255, ''),
            'balance' => 0,
            'created_at' => $now,
        ]);

        $this->supplierCache[$tenantId][$cacheKey] = $supplierId;

        return $supplierId;
    }

    private function buildUniqueCategorySlug(string $tenantId, string $name): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'kategori-'.substr(md5($name), 0, 8);
        }

        $slug = $baseSlug;
        $counter = 1;

        while (DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    private function loadCategoryCache(string $tenantId): array
    {
        return DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => (string) $id])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function loadSupplierCache(string $tenantId): array
    {
        return DB::table('suppliers')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => (string) $id])
            ->all();
    }

    private function postInitialStock(
        string $tenantId,
        string $productId,
        float $qty,
        float $unitCost,
        ?string $actorId,
        CarbonImmutable $now
    ): void {
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first()
            ?: Location::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->first();

        if (! $location) {
            throw new RuntimeException('Lokasi stok tidak ditemukan untuk mencatat stok awal.');
        }

        app(OpeningBalancePostingService::class)->postInventoryOnlyOpening(
            tenantId: $tenantId,
            openingDate: $now->toDateString(),
            locationId: (string) $location->id,
            productId: $productId,
            receipts: [[
                'product_variant_id' => null,
                'reference_item_id' => null,
                'qty' => $qty,
                'unit_cost' => $unitCost,
            ]],
            actorId: $actorId,
            context: [
                'source' => 'product_import_initial_stock',
                'reference_note' => 'Stok awal dari import produk',
            ]
        );
    }

    private function normalizeMoney(mixed $value): float
    {
        return max(0.0, $this->normalizeNumber($value));
    }

    private function normalizeQty(mixed $value): float
    {
        return max(0.0, $this->normalizeNumber($value));
    }

    private function normalizeNumber(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $raw = preg_replace('/[^\d,\.\-]/', '', $raw) ?: '0';
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $raw = str_replace($thousandSeparator, '', $raw);
            $raw = str_replace($decimalSeparator, '.', $raw);

            return (float) $raw;
        }

        foreach ([',', '.'] as $separator) {
            if (substr_count($raw, $separator) !== 1) {
                continue;
            }

            $parts = explode($separator, $raw);
            if (strlen($parts[1] ?? '') === 3 && strlen($parts[0] ?? '') <= 3) {
                return (float) str_replace($separator, '', $raw);
            }

            return (float) str_replace($separator, '.', $raw);
        }

        if (substr_count($raw, ',') > 1) {
            return (float) str_replace(',', '', $raw);
        }

        if (substr_count($raw, '.') > 1) {
            return (float) str_replace('.', '', $raw);
        }

        return (float) $raw;
    }

    private function normalizeBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $normalized = Str::of((string) $value)->lower()->trim()->toString();

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'ya', 'iya', 'aktif', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'tidak', 'nonaktif', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $string = $this->cleanCellValue($value);

        return $string === ''
            ? null
            : Str::limit($string, $maxLength, '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterProductColumns(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip($this->productColumns()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterCategoryColumns(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip($this->categoryColumns()));
    }

    /**
     * @return list<string>
     */
    private function productColumns(): array
    {
        return $this->productColumns ??= Schema::getColumnListing('products');
    }

    /**
     * @return list<string>
     */
    private function categoryColumns(): array
    {
        return $this->categoryColumns ??= Schema::getColumnListing('categories');
    }
}
