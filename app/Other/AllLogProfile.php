<?php

namespace App\Other;

use Illuminate\Http\Request;
use Spatie\HttpLogger\LogProfile;

class AllLogProfile implements LogProfile
{
    public function shouldLogRequest(Request $request): bool
    {
        return in_array(strtolower($request->method()), ['get', 'post', 'put', 'patch', 'delete']);
    }
}
