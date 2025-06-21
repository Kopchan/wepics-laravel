<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogValidationErrors
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response->status() === 422 && $request->isMethod('post')) {
            Log::error('Validation failed', [
                'errors' => json_decode($response->getContent(), true),
                'input' => $request->all(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }
}
