<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use App\Enums\SortType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlbumImagesRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('types') && is_string($this->types)) {
            $this->merge([
                'types' => array_filter(explode(',', $this->types)), // удаляет пустые элементы
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'page'    => 'int|min:1',
            'limit'   => 'int|min:1',
            'sort'    => [Rule::enum(SortType::class)],
            'tags'    => 'string',
            'types'   => 'array',
            'types.*' => ['required', Rule::enum(MediaType::class)],
            'reverse' => 'nullable',
            'nested'  => 'nullable|string',
            'seed'    => 'nullable|int|max_digits:10',
        ];
    }
}
