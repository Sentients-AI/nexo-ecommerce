<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
    $this->withoutMiddleware(ValidateCsrfToken::class);
    // Ensure a fallback category exists (category_id is NOT NULL on products)
    Category::factory()->create(['name' => 'General']);
});

function makeCsv(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($path, $content);

    return new UploadedFile($path, 'products.csv', 'text/csv', null, true);
}

describe('Vendor product import page', function () {
    it('renders the import page', function () {
        $this->get('/vendor/products/import')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/ProductImport'));
    });
});

describe('CSV import', function () {
    it('imports products from a valid CSV', function () {
        $csv = makeCsv("name,sku,price,stock\nBlue Widget,SKU-001,29.99,10\nRed Gadget,SKU-002,49.99,5");

        $response = $this->post('/vendor/products/import', ['csv' => $csv]);

        $response->assertRedirect('/vendor/products/import');

        expect(Product::query()->where('sku', 'SKU-001')->exists())->toBeTrue()
            ->and(Product::query()->where('sku', 'SKU-002')->exists())->toBeTrue();

        $product = Product::query()->where('sku', 'SKU-001')->first();
        expect((int) $product->price_cents)->toBe(2999)
            ->and(Stock::query()->where('product_id', $product->id)->value('quantity_available'))->toBe(10);
    });

    it('resolves category by name', function () {
        $category = Category::factory()->create(['name' => 'Widgets']);
        $csv = makeCsv("name,sku,price,category\nBlue Widget,SKU-003,19.99,Widgets");

        $this->post('/vendor/products/import', ['csv' => $csv]);

        $product = Product::query()->where('sku', 'SKU-003')->first();
        expect($product->category_id)->toBe($category->id);
    });

    it('skips rows with duplicate SKUs and reports the error', function () {
        Product::factory()->create(['sku' => 'DUPE-SKU']);
        $csv = makeCsv("name,sku,price\nDuplicate,DUPE-SKU,9.99");

        $response = $this->post('/vendor/products/import', ['csv' => $csv])
            ->assertRedirect('/vendor/products/import');

        $response->assertSessionHas('import_result', fn ($result) => $result['skipped'] === 1 && count($result['errors']) === 1);
    });

    it('rejects a CSV with missing required columns', function () {
        $csv = makeCsv("name,price\nBlue Widget,9.99");

        $response = $this->post('/vendor/products/import', ['csv' => $csv])
            ->assertRedirect('/vendor/products/import');

        $response->assertSessionHas('import_result', fn ($result) => $result['imported'] === 0 && count($result['errors']) === 1);
    });

    it('skips rows with invalid price', function () {
        $csv = makeCsv("name,sku,price\nBad Widget,SKU-BAD,notanumber");

        $response = $this->post('/vendor/products/import', ['csv' => $csv])
            ->assertRedirect('/vendor/products/import');

        $response->assertSessionHas('import_result', fn ($result) => $result['skipped'] === 1);
    });

    it('rejects non-CSV files', function () {
        $file = UploadedFile::fake()->create('products.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->post('/vendor/products/import', ['csv' => $file])
            ->assertSessionHasErrors('csv');
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $csv = makeCsv("name,sku,price\nBlue Widget,SKU-001,9.99");

        $this->post('/vendor/products/import', ['csv' => $csv])
            ->assertRedirect();
    });
});
