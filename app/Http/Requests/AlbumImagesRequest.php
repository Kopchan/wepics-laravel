<?php

namespace App\Http\Requests;

use App\Enums\SortType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlbumImagesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'page'      => 'int|min:1',
            'limit'     => 'int|min:1',
            'sort'      => [Rule::enum(SortType::class)],
            'tags'      => 'string',
            'reverse'   => 'nullable',
            'recursive' => 'nullable|string',
        ];
    }
}
