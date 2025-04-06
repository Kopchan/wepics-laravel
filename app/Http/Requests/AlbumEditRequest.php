<?php

namespace App\Http\Requests;

class AlbumEditRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'customName' => ['nullable', 'string', 'min:1'],
            'name'       => ['nullable', 'string', 'min:1', 'not_regex:/\\/?%*:|"<>/'], // pathName
        ];
    }
}
