<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;

/**
 * Perf panel data (admin only). Reads Redis ring buffers filled by
 * App\Support\QueryPerf on every request. No DB load by itself.
 */
class PerfController extends Controller
{
    public function summary()
    {
        try {
            $requests = Redis::lrange('perf:requests', 0, -1) ?: [];
            $slowQueries = Redis::lrange('perf:slow_queries', 0, -1) ?: [];
        }
        catch (\Throwable) {
            return response()->json(['routes' => [], 'slow_queries' => [], 'recent_slow' => [], 'error' => 'redis unavailable']);
        }

        $requests = array_values(array_filter(array_map(
            fn ($r) => json_decode($r, true), $requests
        )));

        return response()->json(self::summarize($requests, array_map(
            fn ($r) => json_decode($r, true), $slowQueries
        )));
    }

    public function clear()
    {
        try {
            $deleted = Redis::del('perf:requests', 'perf:slow_queries');
        }
        catch (\Throwable) {
            return response()->json(['deleted' => 0, 'error' => 'redis unavailable']);
        }
        return response()->json(['deleted' => $deleted]);
    }

    /** Pure aggregation, unit-testable without Redis. */    public static function summarize(array $requests, array $slowQueries): array
    {
        $routes = [];
        $recentSlow = [];
        foreach ($requests as $r) {
            if (!is_array($r) || !isset($r['ms'])) continue;
            $name = $r['route'] ?? null;
            $path = $r['path'] ?? '?';
            $key = ($r['method'] ?? '?') . ' ' . ($name ?: $path);
            $routes[$key]['ms'][] = $r['ms'];
            $routes[$key]['path'] ??= $path;
            if (!empty($r['slow'])) {
                $routes[$key]['slow'] = ($routes[$key]['slow'] ?? 0) + 1;
                if (count($recentSlow) < 20) $recentSlow[] = $r;
            }
        }

        $table = [];
        foreach ($routes as $route => $g) {
            sort($g['ms']);
            $n = count($g['ms']);
            $table[] = [
                'route' => $route,
                'path' => $g['path'] ?? '?',
                'count' => $n,
                'avg_ms' => round(array_sum($g['ms']) / $n, 1),
                'p50_ms' => self::percentile($g['ms'], 50),
                'p95_ms' => self::percentile($g['ms'], 95),
                'max_ms' => end($g['ms']),
                'slow' => $g['slow'] ?? 0,
            ];
        }
        usort($table, fn ($a, $b) => $b['p95_ms'] <=> $a['p95_ms']);

        return [
            'routes' => $table,
            'slow_queries' => array_values(array_filter($slowQueries)),
            'recent_slow' => $recentSlow,
        ];
    }

    protected static function percentile(array $sorted, int $p): float|int
    {
        $n = count($sorted);
        if ($n === 0) return 0;
        return $sorted[min($n - 1, (int) ceil($p / 100 * $n) - 1)];
    }
}
