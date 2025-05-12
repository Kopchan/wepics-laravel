<?php

namespace App\Http\Requests;

use App\Models\AgeRating;
use App\Models\Album;
use Illuminate\Validation\Rule;

class AlbumUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        $album = Album::where('hash', $this->route('album_hash'))->first();

        return [
            'displayName' => ['nullable', 'string', 'min:1', 'max:255'],
            'pathName' => [
                'nullable',
                'string',
                'min:1',
                'max:127',
                'not_regex:/\\/?%*:|"<>/'
            ],
            'urlName' => [
                'nullable',
                'string',
                'min:1',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique(Album::class, 'alias')->ignore($album?->id),
            ],
            'ageRatingId'   => [
                'nullable',
                'integer',
                Rule::exists(AgeRating::class, 'id')
            ],
            'orderLevel'    => ['nullable', 'integer'],
            'viewSettings'  => ['nullable', 'string'],
            'guestAllow'    => ['nullable', 'boolean'],
        ];
    }
}
