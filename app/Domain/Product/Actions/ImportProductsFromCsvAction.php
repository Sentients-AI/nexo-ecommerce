<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Category\Models\Category;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @phpstan-type ImportResult array{imported: int, skipped: int, errors: array<int, string>}
 */
final class ImportProductsFromCsvAction
{
    private const REQUIRED_HEADERS = ['name', 'sku', 'price'];

    /**
     * @return ImportResult
     */
    public function execute(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Could not open the uploaded file.']];
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV file is empty.']];
        }

        $headers = array_map(trim(...), array_map(strtolower(...), $headers));

        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);

        if ($missingHeaders !== []) {
            fclose($handle);

            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Missing required columns: '.implode(', ', $missingHeaders).'. Required: name, sku, price'],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $row++;

            if (count($values) !== count($headers)) {
                $errors[] = "Row {$row}: column count mismatch, skipped.";
                $skipped++;

                continue;
            }

            $data = array_combine($headers, array_map(trim(...), $values));

            $result = $this->importRow($row, $data);

            if ($result === true) {
                $imported++;
            } elseif (is_string($result)) {
                $errors[] = $result;
                $skipped++;
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function importRow(int $row, array $data): true|string
    {
        $name = $data['name'] ?? '';
        $sku = $data['sku'] ?? '';
        $priceRaw = $data['price'] ?? '';

        if ($name === '' || $sku === '' || $priceRaw === '') {
            return "Row {$row}: name, sku, and price are required.";
        }

        if (! is_numeric($priceRaw) || (float) $priceRaw < 0) {
            return "Row {$row}: price must be a non-negative number.";
        }

        if (Product::query()->where('sku', $sku)->exists()) {
            return "Row {$row}: SKU '{$sku}' already exists, skipped.";
        }

        $categoryId = null;
        if (! empty($data['category'])) {
            $category = Category::query()->where('name', $data['category'])->first();
            if ($category) {
                $categoryId = $category->id;
            } else {
                return "Row {$row}: category '{$data['category']}' not found.";
            }
        }

        if ($categoryId === null) {
            $fallback = Category::query()->first();
            if ($fallback === null) {
                return "Row {$row}: no categories exist — please create a category first.";
            }
            $categoryId = $fallback->id;
        }

        $priceCents = (int) round((float) $priceRaw * 100);

        $product = Product::create([
            'name' => $name,
            'sku' => $sku,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'price_cents' => $priceCents,
            'category_id' => $categoryId,
            'is_active' => isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
        ]);

        $stockQty = isset($data['stock']) && is_numeric($data['stock']) ? (int) $data['stock'] : 0;

        Stock::create([
            'product_id' => $product->id,
            'quantity_available' => $stockQty,
            'quantity_reserved' => 0,
        ]);

        return true;
    }
}
