<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use App\Enums\SortAlbumType;
use App\Enums\SortType;
use Illuminate\Validation\Rule;

class AlbumRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('types') && is_string($this->types)) {
            $this->merge([
                'types' => array_filter(explode(',', $this->types)), // удаляет пустые элементы
            ]);
        }
        if ($this->filled('ratings') && is_string($this->ratings)) {
            $this->merge([
                'ratings' => array_filter(explode(',', $this->ratings)), // удаляет пустые элементы
            ]);
        }
    }

    public function rules(): array
    {
        return [
          //'page'          => 'int|min:1',
          //'limit'         => 'int|min:1',
            'sort'          => [Rule::enum(SortType::class)],
            'sortAlbums'    => [Rule::enum(SortAlbumType::class)],
            'images'        => 'int|min:0',
            'reverse'       => 'nullable',
            'reverseAlbums' => 'nullable',
            'disrespect'    => 'nullable',
            'simple'        => 'nullable',
            'ratings'       => 'array',
            'ratings.*'     => ['required', 'int'],
            'types'         => 'array',
            'types.*'       => ['required', Rule::enum(MediaType::class)],
            'seed'          => 'nullable|int|max_digits:10',
        ];
    }
}
