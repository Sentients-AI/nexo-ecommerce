<?php

declare(strict_types=1);

use App\Domain\Promotion\Actions\GenerateBulkCodesAction;
use App\Domain\Promotion\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

describe('GenerateBulkCodesAction', function (): void {
    it('generates the requested number of promotions', function (): void {
        $template = Promotion::factory()->create(['name' => 'Bulk Template', 'is_active' => true]);

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 5);

        expect($generated)->toHaveCount(5)
            ->and(Promotion::query()->count())->toBe(6); // 5 new + 1 template
    });

    it('all generated codes share the same batch_id', function (): void {
        $template = Promotion::factory()->create();

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 3);
        $batchIds = $generated->pluck('batch_id')->unique();

        expect($batchIds)->toHaveCount(1)
            ->and($batchIds->first())->not->toBeNull();
    });

    it('applies prefix to generated codes', function (): void {
        $template = Promotion::factory()->create();

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 5, prefix: 'SUMMER');

        $generated->each(function (Promotion $promo): void {
            expect($promo->code)->toStartWith('SUMMER-');
        });
    });

    it('generates codes without prefix when none is given', function (): void {
        $template = Promotion::factory()->create();

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 3);

        $generated->each(function (Promotion $promo): void {
            expect($promo->code)->not->toContain('-');
        });
    });

    it('generated codes are all unique', function (): void {
        $template = Promotion::factory()->create();

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 20);
        $codes = $generated->pluck('code');

        expect($codes->unique()->count())->toBe(20);
    });

    it('cloned promotions inherit template discount settings', function (): void {
        $template = Promotion::factory()->create([
            'discount_type' => 'percentage',
            'discount_value' => 2000, // 20%
        ]);

        $generated = app(GenerateBulkCodesAction::class)->execute($template, count: 2);

        $generated->each(function (Promotion $promo) use ($template): void {
            expect($promo->discount_type->value)->toBe($template->discount_type->value)
                ->and($promo->discount_value)->toBe($template->discount_value);
        });
    });
});
