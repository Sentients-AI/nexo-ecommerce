<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class VendorStorefrontController extends Controller
{
    public function index(): Response
    {
        $tenant = Context::get('tenant');

        return Inertia::render('Vendor/Storefront', [
            'storefront' => $tenant ? [
                'name' => $tenant->name,
                'description' => $tenant->description,
                'logo_path' => $tenant->logo_path,
                'banner_path' => $tenant->banner_path,
                'accent_color' => $tenant->accent_color ?? '#6747f5',
                'social_links' => $tenant->social_links ?? [],
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'logo_path' => ['nullable', 'url', 'max:500'],
            'banner_path' => ['nullable', 'url', 'max:500'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'social_links.twitter' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.tiktok' => ['nullable', 'url', 'max:255'],
        ]);

        $tenant = Context::get('tenant');
        $tenant->update([
            'description' => $validated['description'] ?? null,
            'logo_path' => $validated['logo_path'] ?? null,
            'banner_path' => $validated['banner_path'] ?? null,
            'accent_color' => $validated['accent_color'] ?? null,
            'social_links' => array_filter([
                'twitter' => $validated['social_links']['twitter'] ?? null,
                'instagram' => $validated['social_links']['instagram'] ?? null,
                'facebook' => $validated['social_links']['facebook'] ?? null,
                'tiktok' => $validated['social_links']['tiktok'] ?? null,
            ]),
        ]);

        return back()->with('success', 'Storefront updated.');
    }
}
