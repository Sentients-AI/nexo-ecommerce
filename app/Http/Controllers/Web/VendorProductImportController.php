<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Product\Actions\ImportProductsFromCsvAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorProductImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Vendor/ProductImport');
    }

    public function store(Request $request, ImportProductsFromCsvAction $action): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $action->execute($request->file('csv'));

        return redirect()->route('vendor.products.import')
            ->with('import_result', $result);
    }
}
