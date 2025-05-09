<?php

namespace App\Http\Requests;

class SettingsEditRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'key' => 'required|string|in:' . implode(',', [
                'upload_disable_percentage',
                'allowed_upload_mimes',
                'allowed_preview_sizes',
            ]),
            'value' => 'required|string',
        ];
    }
}
