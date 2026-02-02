<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Metrics\MetricsRecorder;
use App\Shared\Performance\PerformanceBudgets;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class MeasureRequestDuration
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = (microtime(true) - $startTime) * 1000;
        $routeName = $request->route()?->getName() ?? 'unknown';
        $method = $request->method();

        // Record the metric
        MetricsRecorder::histogram('http_request_duration_ms', $durationMs, [
            'route' => $routeName,
            'method' => $method,
            'status' => (string) $response->getStatusCode(),
        ]);

        // Check against performance budget
        if (PerformanceBudgets::exceedsBudget($routeName, $durationMs)) {
            $budget = PerformanceBudgets::forRoute($routeName);

            Log::warning('Request exceeded performance budget', [
                'route' => $routeName,
                'method' => $method,
                'duration_ms' => round($durationMs, 2),
                'budget_ms' => $budget,
                'exceeded_by_ms' => round($durationMs - $budget, 2),
            ]);

            MetricsRecorder::increment('http_request_budget_exceeded_total', [
                'route' => $routeName,
            ]);
        }

        // Add timing header for debugging
        $response->headers->set('X-Response-Time', sprintf('%.2fms', $durationMs));

        return $response;
    }
}
