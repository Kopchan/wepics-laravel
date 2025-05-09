<?php

namespace App\Http\Requests;

use App\Models\AgeRating;
use Illuminate\Validation\Rule;

class AlbumUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'displayName'   => ['nullable', 'string', 'min:1'],                             // displayName
            'pathName'      => ['nullable', 'string', 'min:1', 'not_regex:/\\/?%*:|"<>/'], // pathName
            'urlName'       => ['nullable', 'string', 'min:1', 'regex:/^[A-Za-z0-9-]+$/'],   // urlName
            'ageRatingId'   => ['nullable', 'integer', Rule::exists(AgeRating::class, 'id')],
            'orderLevel'    => ['nullable', 'integer'],
            'viewSettings'  => ['nullable', 'string'],
            'guestAllow'    => ['nullable', 'boolean'],
        ];
    }
}
