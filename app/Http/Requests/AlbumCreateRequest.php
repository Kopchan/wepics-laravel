<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlbumCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'customName' => ['nullable', 'string', 'min:1'],
            'name'       => ['required', 'string', 'min:1', 'not_regex:/\\/?%*:|"<>/'], // pathName
        ];
    }
}
