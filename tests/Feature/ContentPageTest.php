<?php

declare(strict_types=1);

use App\Domain\Content\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->setUpTenant();
});

describe('GET /en/pages/{slug}', function (): void {
    it('renders a published content page', function (): void {
        ContentPage::query()->create([
            'title' => 'About Us',
            'slug' => 'about',
            'body' => '<p>Our story.</p>',
            'is_published' => true,
        ]);

        $this->get('/en/pages/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Content/Page')
                ->where('page.title', 'About Us')
                ->where('page.slug', 'about')
            );
    });

    it('returns 404 for unpublished page', function (): void {
        ContentPage::query()->create([
            'title' => 'Draft',
            'slug' => 'draft-page',
            'is_published' => false,
        ]);

        $this->get('/en/pages/draft-page')->assertNotFound();
    });

    it('returns 404 for unknown slug', function (): void {
        $this->get('/en/pages/does-not-exist')->assertNotFound();
    });

    it('is accessible without authentication', function (): void {
        ContentPage::query()->create([
            'title' => 'FAQ',
            'slug' => 'faq',
            'is_published' => true,
        ]);

        $this->get('/en/pages/faq')->assertOk();
    });

    it('includes meta description in page data', function (): void {
        ContentPage::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'meta_description' => 'Get in touch with us',
            'is_published' => true,
        ]);

        $this->get('/en/pages/contact')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('page.meta_description', 'Get in touch with us')
            );
    });
});
