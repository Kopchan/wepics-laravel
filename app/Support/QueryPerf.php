<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Always-on lightweight query accounting without the memory cost
 * of DB::enableQueryLog(). Reset per request from LogRequest middleware.
 * Registered per app boot (fresh in fpm, once per worker in Octane/queue).
 */
class QueryPerf
{
    protected static int $count = 0;
    protected static float $totalMs = 0.0;

    public static function registerListener(): void
    {
        DB::listen(function ($query) {
            self::$count++;
            self::$totalMs += $query->time ?? 0;

            $slowMs = (int) config('logging.slow_db_ms', 200);
            if ($slowMs > 0 && $query->time >= $slowMs) {
                $bindings = mb_substr(json_encode($query->bindings) ?: '', 0, 200);
                Log::warning("🐌 slow query {$query->time}ms [{$query->connectionName}] {$query->sql} $bindings");
                self::pushSlowQuery($query->time, $query->connectionName, $query->sql, $query->bindings);
            }
        });
    }

    public static function reset(): void
    {
        self::$count = 0;
        self::$totalMs = 0.0;
    }

    /** @return array{int, float} [query count, total ms] */
    public static function snapshot(): array
    {
        return [self::$count, round(self::$totalMs, 1)];
    }

    /** Ring buffer of recent requests for the perf panel (never breaks the request). */
    public static function pushRequest(array $record): void
    {
        if (!config('logging.perf_panel', true)) return;
        try {
            Redis::connection()->pipeline(function ($pipe) use ($record) {
                $pipe->lpush('perf:requests', json_encode($record));
                $pipe->ltrim('perf:requests', 0, 499);
            });
        }
        catch (\Throwable) {
        }
    }

    public static function pushSlowQuery(float $ms, string $connection, string $sql, array $bindings): void
    {
        if (!config('logging.perf_panel', true)) return;
        try {
            Redis::connection()->pipeline(function ($pipe) use ($ms, $connection, $sql, $bindings) {
                $pipe->lpush('perf:slow_queries', json_encode([
                    't' => time(), 'ms' => $ms, 'conn' => $connection,
                    'sql' => mb_substr($sql, 0, 500),
                    'bindings' => mb_substr(json_encode($bindings) ?: '', 0, 200),
                ]));
                $pipe->ltrim('perf:slow_queries', 0, 199);
            });
        }
        catch (\Throwable) {
        }
    }
}
