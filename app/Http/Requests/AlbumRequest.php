<?php

namespace App\Http\Requests;

use App\Enums\SortType;
use Illuminate\Validation\Rule;

class AlbumRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            //'page'      => 'int|min:1',
            //'limit'     => 'int|min:1',
            'sort'      => [Rule::enum(SortType::class)],
            'images'    => 'int|min:0',
            'reverse'   => 'nullable',
        ];
    }
}
