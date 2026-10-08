<?php

namespace App\Http\Middleware;

use App\Support\QueryPerf;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class LogRequest
{
    /** Collapse hashes/ids so per-route stats group properly. */
    public static function normalizePath(string $path): string
    {
        return preg_replace('~[A-Za-z0-9_-]{20,}~', '{hash}', $path) ?? $path;
    }

    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('perf_started_at', microtime(true));
        QueryPerf::reset();

        if (!config('logging.dbQueries', false))
            return $next($request);

        try {
            $method = strtoupper($request->getMethod());
            if (in_array($method, ['GET', 'PUT', 'PATCH', 'POST', 'DELETE']))
                DB::enableQueryLog();
        }
        catch (\Exception $e) {
            Log::error($e);
        }
        return $next($request);
    }

    public function terminate(Request $request, $response)
    {
        try {
            // Always-on compact perf line (default channel -> stderr in prod).
            $startedAt = $request->attributes->get('perf_started_at', microtime(true));
            $ms = (int) round((microtime(true) - $startedAt) * 1000);
            [$qCount, $dbMs] = QueryPerf::snapshot();
            $thumbMs = $request->attributes->get('thumb_ms');
            $slow = $ms >= config('logging.slow_request_ms', 1000) ? ' 🐌SLOW' : '';
            $message = "⏱ {$request->getMethod()} {$request->getPathInfo()} {$response->getStatusCode()} {$ms}ms 🗄{$qCount}q/{$dbMs}ms"
                . ($thumbMs !== null ? " 🖼{$thumbMs}ms" : '')
                . $slow;
            if ($slow !== '')
                Log::warning($message);
            else
                Log::info($message);

            QueryPerf::pushRequest([
                't' => time(),
                'route' => $request->route()?->getName(),
                'path' => self::normalizePath($request->getPathInfo()),
                'method' => $request->getMethod(),
                'status' => $response->getStatusCode(),
                'ms' => $ms, 'db_q' => $qCount, 'db_ms' => $dbMs,
                'thumb_ms' => $thumbMs, 'slow' => $slow !== '',
            ]);
        }
        catch (\Exception $exception) {
            Log::error($exception);
        }

        if (!config('logging.dbQueries', false))
            return;

        try {
            $method = strtoupper($request->getMethod());
            if (!in_array($method, ['GET', 'PUT', 'PATCH', 'POST', 'DELETE']))
                return;

            $dbQueries = DB::getQueryLog();
            $dbQueryStrings = [];
            foreach ($dbQueries as $dbQuery) {
                $query = $dbQuery['query'];
                $bindings = $dbQuery['bindings'];

                // Заменяем вопросительные знаки на значения параметров
                foreach ($bindings as $binding)
                    $query = preg_replace('/\?/', "'" . addslashes($binding) . "'", $query, 1);

                $dbQueryStrings[] = $query . ";\n";
            }

            $code = $response->getStatusCode();
            $sign = $response->isRedirection()      ? "🔵" : (
                $response->isInformational()        ? "🟣" : (
                    $response->isSuccessful()       ? "🟢" : (
                        $response->isClientError()  ? "🟡" : "🔴"
                    )
                )
            );

            $uri = $request->getPathInfo();
            $ip = str_pad($request->ip(), 16);

            $origin = $request->header('Origin')
                   ?? $request->header('Host')
                   ?? $request->header('Referer')
                   ?? "NO_ORIGIN ";

            $userId = $request->user()?->id ??
                ($request->has('sign')
                    ? explode('_', $request->sign)[0]
                    : "GUEST  ");

            if (is_numeric($userId))
                $userId = str_pad($userId, 7, '0', STR_PAD_LEFT);

            $agent = new Agent();
            $agent->setUserAgent($request->headers->get('User-Agent'));
            $device   = str_pad($agent->device()  , 10);
            $platform = str_pad($agent->platform(), 10);
            $browser  = str_pad($agent->browser() , 10);

            $reqQuery = $request->getQueryString();
            $message = "$sign $code $method\t👤$ip 🆔$userId 📱$device 📦$platform 🌍$browser $origin$uri"
                . ($reqQuery ? "?$reqQuery" : '')
                . (!empty($dbQueryStrings) ? "\n" : '')
                . implode('', $dbQueryStrings);

            Log::channel('http-request')->log('info', $message);
        }
        catch (\Exception $exception) {
            Log::error($exception);
        }
    }
}
