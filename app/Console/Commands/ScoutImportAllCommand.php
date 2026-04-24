<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Illuminate\Console\Command;

final class ScoutImportAllCommand extends Command
{
    protected $signature = 'scout:import-all
                            {--fresh : Flush existing index before importing (scout:flush + scout:import)}';

    protected $description = 'Import all searchable models into the search index';

    /** @var array<class-string> */
    private array $models = [
        Product::class,
        Category::class,
        Order::class,
    ];

    public function handle(): int
    {
        $fresh = $this->option('fresh');

        foreach ($this->models as $model) {
            $shortName = class_basename($model);

            if ($fresh) {
                $this->info("Flushing {$shortName}...");
                $model::removeAllFromSearch();
            }

            $this->info("Importing {$shortName}...");
            $model::makeAllSearchable();

            $this->info("✓ {$shortName} indexed.");
        }

        $this->info('All searchable models imported successfully.');

        return self::SUCCESS;
    }
}
