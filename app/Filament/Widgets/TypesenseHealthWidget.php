<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Config;
use Throwable;

final class TypesenseHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 99;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $reachable = $this->isTypesenseReachable();

        if (! $reachable) {
            return [
                Stat::make('Typesense', 'Unreachable')
                    ->description('Cannot connect to the Typesense server')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle'),
            ];
        }

        return [
            Stat::make('Typesense', 'Connected')
                ->description('Search index is reachable')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Products Indexed', number_format($this->countIndexed(Product::class)))
                ->description('Documents in Products index')
                ->color('primary')
                ->icon('heroicon-o-shopping-bag'),

            Stat::make('Categories Indexed', number_format($this->countIndexed(Category::class)))
                ->description('Documents in Categories index')
                ->color('primary')
                ->icon('heroicon-o-tag'),

            Stat::make('Orders Indexed', number_format($this->countIndexed(Order::class)))
                ->description('Documents in Orders index')
                ->color('primary')
                ->icon('heroicon-o-document-text'),
        ];
    }

    private function isTypesenseReachable(): bool
    {
        try {
            $host = Config::get('scout.typesense.client-settings.nodes.0.host', 'localhost');
            $port = Config::get('scout.typesense.client-settings.nodes.0.port', 8108);
            $protocol = Config::get('scout.typesense.client-settings.nodes.0.protocol', 'http');

            $context = stream_context_create(['http' => ['timeout' => 2]]);
            $response = @file_get_contents("{$protocol}://{$host}:{$port}/health", false, $context);

            return $response !== false && str_contains($response, 'ok');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    private function countIndexed(string $modelClass): int
    {
        try {
            $raw = $modelClass::search('*')->options(['per_page' => 1])->raw();

            return is_array($raw) && isset($raw['found']) ? (int) $raw['found'] : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
