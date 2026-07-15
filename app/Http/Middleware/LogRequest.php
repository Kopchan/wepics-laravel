<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class LogRequest
{
    public function handle(Request $request, Closure $next)
    {
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
