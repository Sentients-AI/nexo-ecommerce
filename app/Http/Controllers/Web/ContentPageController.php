<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Content\Models\ContentPage;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ContentPageController extends Controller
{
    public function show(string $locale, string $slug): Response
    {
        $page = ContentPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Content/Page', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
                'meta_description' => $page->meta_description,
                'updated_at' => $page->updated_at?->toDateString(),
            ],
        ]);
    }
}
