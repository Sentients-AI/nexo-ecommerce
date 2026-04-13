<?php

declare(strict_types=1);

use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionExperiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

describe('PromotionExperiment model', function (): void {
    it('can be created with name and hypothesis', function (): void {
        $experiment = PromotionExperiment::query()->create([
            'name' => 'Summer Sale Test',
            'hypothesis' => '% discount drives more conversions than $ discount',
            'is_active' => true,
        ]);

        expect($experiment->name)->toBe('Summer Sale Test')
            ->and($experiment->is_active)->toBeTrue();
    });

    it('has variant A and B promotions via relationship', function (): void {
        $experiment = PromotionExperiment::query()->create([
            'name' => 'Test Experiment',
            'is_active' => true,
        ]);

        $promoA = Promotion::factory()->create([
            'experiment_id' => $experiment->id,
            'variant' => 'A',
        ]);
        $promoB = Promotion::factory()->create([
            'experiment_id' => $experiment->id,
            'variant' => 'B',
        ]);

        expect($experiment->variants()->count())->toBe(2)
            ->and($experiment->variantA()->first()->id)->toBe($promoA->id)
            ->and($experiment->variantB()->first()->id)->toBe($promoB->id);
    });

    it('associates promotion with experiment via experiment() relationship', function (): void {
        $experiment = PromotionExperiment::query()->create([
            'name' => 'Test',
            'is_active' => true,
        ]);

        $promo = Promotion::factory()->create([
            'experiment_id' => $experiment->id,
            'variant' => 'A',
        ]);

        expect($promo->experiment->id)->toBe($experiment->id)
            ->and($promo->variant)->toBe('A');
    });

    it('can be deactivated', function (): void {
        $experiment = PromotionExperiment::query()->create([
            'name' => 'Done experiment',
            'is_active' => true,
        ]);
        $experiment->update(['is_active' => false]);

        expect($experiment->fresh()->is_active)->toBeFalse();
    });
});
