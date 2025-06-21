<?php

namespace App\Http\Requests;

use App\Http\Requests\ApiRequest;

class InvitationCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'expires_at' => 'nullable|date|after:now',
            'timeLimit'  => 'nullable|integer|min:1',
            'joinLimit'  => 'nullable|integer|min:1',
        ];
    }
}
