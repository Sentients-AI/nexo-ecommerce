<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Idempotency\Models\IdempotencyKey;
use App\Shared\Alerting\AlertTrigger;
use App\Shared\Alerting\Enums\AlertTriggerStatus;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use UnitEnum;

final class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected string $view = 'filament.pages.system-health';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'System Health';

    protected static ?string $navigationLabel = 'System Health';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function getFailedJobs(): Collection
    {
        return DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(50)
            ->get();
    }

    public function getFailedJobsCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public function getQueueStats(): array
    {
        $pending = DB::table('jobs')->count();

        return [
            'pending' => $pending,
            'failed' => $this->getFailedJobsCount(),
        ];
    }

    public function getIdempotencyConflicts(): Collection
    {
        if (! class_exists(IdempotencyKey::class)) {
            return collect();
        }

        // Look for duplicate keys with different fingerprints (potential conflicts)
        // or responses indicating conflicts (4xx status codes)
        return IdempotencyKey::query()
            ->where('created_at', '>', now()->subDay())
            ->where(function ($query): void {
                $query->whereIn('response_code', [409, 422, 400])
                    ->orWhere('response_body', 'like', '%conflict%');
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    public function getRecentErrors(): Collection
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return collect();
        }
        $file = new SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - 500);
        $file->seek($startLine);

        $errors = [];
        $currentError = null;

        while (! $file->eof()) {
            $line = $file->fgets();

            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\].*\.(ERROR|CRITICAL|ALERT|EMERGENCY):(.*)/', $line, $matches)) {
                if ($currentError) {
                    $errors[] = $currentError;
                }
                $currentError = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => mb_trim($matches[3]),
                    'stack' => '',
                ];
            } elseif ($currentError && str_starts_with($line, '#')) {
                $currentError['stack'] .= $line;
            }
        }

        if ($currentError) {
            $errors[] = $currentError;
        }

        return collect(array_slice(array_reverse($errors), 0, 20));
    }

    public function getDatabaseStats(): array
    {
        try {
            return [
                'orders' => DB::table('orders')->count(),
                'payment_intents' => DB::table('payment_intents')->count(),
                'refunds' => DB::table('refunds')->count(),
                'products' => DB::table('products')->count(),
                'stocks' => DB::table('stocks')->count(),
                'audit_logs' => DB::table('audit_logs')->count(),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getCacheStats(): array
    {
        return [
            'driver' => config('cache.default'),
        ];
    }

    public function getActiveAlerts(): Collection
    {
        return AlertTrigger::query()
            ->with('definition')
            ->where('status', AlertTriggerStatus::Active)
            ->orderBy('triggered_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function getActiveAlertsCount(): int
    {
        return AlertTrigger::query()
            ->where('status', AlertTriggerStatus::Active)
            ->count();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reindex_search')
                ->label('Re-index Search')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Re-index Search')
                ->modalDescription('This will re-import all searchable models (Products, Categories, Orders) into the search index. Existing index data is preserved.')
                ->action(function (): void {
                    Artisan::call('scout:import-all');
                    Notification::make()
                        ->title('Search index updated')
                        ->body('All searchable models have been re-indexed successfully.')
                        ->success()
                        ->send();
                }),

            Action::make('reindex_search_fresh')
                ->label('Fresh Re-index')
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Fresh Re-index')
                ->modalDescription('This will flush all existing search indexes and re-import all data from scratch. This cannot be undone and may take a while on large datasets.')
                ->action(function (): void {
                    Artisan::call('scout:import-all', ['--fresh' => true]);
                    Notification::make()
                        ->title('Search index rebuilt')
                        ->body('All search indexes have been flushed and re-imported successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
