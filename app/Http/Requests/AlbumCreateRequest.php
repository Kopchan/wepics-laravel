<?php

namespace App\Http\Requests;

use App\Models\AgeRating;
use Illuminate\Validation\Rule;

class AlbumCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'customName'    => ['nullable', 'string', 'min:1'],                             // displayName
            'name'          => ['required', 'string', 'min:1', 'not_regex:/\\/?%*:|"<>/'], // pathName
            'alias'         => ['nullable', 'string', 'min:1', 'regex:/^[a-z0-9-]+$/'],   // urlName
            'age_rating'    => ['nullable', 'string', Rule::exists(AgeRating::class, 'code')],
            'order_level'   => ['nullable', 'integer'],
            'view_settings' => ['nullable', 'string'],
        ];
    }
}
