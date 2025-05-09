<?php

return [
    'upload_disable_percentage' => (int) env('UPLOAD_DISABLE_PERCENTAGE', 90),
    'allowed_upload_mimes'  => explode(',', env('ALLOWED_UPLOAD_MIMES', 'jpeg,jpg,png,gif')),
    'allowed_preview_sizes' => array_map('intval',
        explode(',', env('ALLOWED_PREVIEW_SIZES', '144,240,360,480,720,1080'))
    ),
];
