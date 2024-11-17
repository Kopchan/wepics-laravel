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
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['GET', 'PUT', 'PATCH', 'POST', 'DELETE']))
            DB::enableQueryLog();

        return $next($request);
    }

    public function terminate(Request $request, $response)
    {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'PUT', 'PATCH', 'POST', 'DELETE']))
            return;

        $dbQueries = collect(DB::getQueryLog());
        $dbQueryStrings = [];
        foreach ($dbQueries as $dbQuery) $dbQueryStrings[] =
            str_replace('?', $dbQuery['bindings'][0] ?? '?', $dbQuery['query']) . ";\n";

        $code = $response->getStatusCode();
        $sign = $response->isSuccessful() ? "🟢" : "🔴";

        $uri    = $request->getPathInfo();
        $origin = $request->headers->get('origin') ?? "NO_ORIGIN";
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
        $message = "$sign $code $method\t👤$userId 📱$device 📦$platform 🌍$browser $origin $uri"
            . ($reqQuery ? "?$reqQuery" : '')
            . (!empty($dbQueryStrings) ? "\n" : '')
            . implode('', $dbQueryStrings);

        Log::channel('http-request')->log('info', $message);
    }
}
