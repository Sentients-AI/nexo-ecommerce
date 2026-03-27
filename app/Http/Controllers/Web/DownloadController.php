<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Product\Models\ProductDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadController
{
    /**
     * Validate the token and stream the file to the customer.
     * No authentication required — the token itself is the credential.
     */
    public function show(Request $request, string $token): StreamedResponse
    {
        $download = ProductDownload::query()
            ->with('product')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_if($download->isExpired(), 410, 'This download link has expired.');
        abort_if($download->isExhausted(), 410, 'This download link has reached its maximum download count.');

        $download->increment('download_count');
        $download->update(['last_downloaded_at' => now()]);

        $path = $download->product->download_file_path;

        abort_unless(Storage::disk('private')->exists($path), 404, 'File not found.');

        return Storage::disk('private')->download($path);
    }
}
