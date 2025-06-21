<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'images'        => 'required|array|min:1',
            'images.*.file' => 'required|file',
            'images.*.date' => 'nullable|date',
            'images.*.name' => [
                'nullable',
                'regex:~^[^/\\\\:*?"<>|]+$~u',
            ],
        ];
    }
}
