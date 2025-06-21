<?php

namespace App\Http\Requests;

use App\Models\AgeRating;
use App\Models\Album;
use Illuminate\Validation\Rule;

class AlbumCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        $rules = [
            'customName'    => ['nullable', 'string', 'min:1'], // displayName
            'name'          => ['required', 'string', 'min:1', 'not_regex:/^(?!\.{2}).*[\\/?%*:|"<>]/'], // pathName
            'age_rating'    => ['nullable', 'integer', Rule::exists(AgeRating::class, 'id')],
            'order_level'   => ['nullable', 'integer'],
            'view_settings' => ['nullable', 'string'],
        ];
        $user = request()->user();
        if ($user->is_admin) {
            $rules['alias'] = [
                'nullable',
                'string',
                'min:1',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique(Album::class, 'alias'),
            ];
            $rules['name'] = [
                'required',
                'string',
                'min:1',
                'max:127',
                'not_regex:/\\/?%*:|"<>/'
            ];
        }
        return $rules;
    }
}
