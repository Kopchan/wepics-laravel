<?php

return [
    'upload_disable_percentage' => (int) env('UPLOAD_DISABLE_PERCENTAGE', 90),
    'default_quota_bytes'       => (int) env('DEFAULT_QUOTA_BYTES', 0),
    'allowed_image_extensions'  => explode(',', env('ALLOWED_EXT_IMAGE', 'jpeg,jpg,png,gif,webp,avif')),
    'allowed_video_extensions'  => explode(',', env('ALLOWED_EXT_VIDEO', 'mp4,webm,mkv')),
    'allowed_audio_extensions'  => explode(',', env('ALLOWED_EXT_AUDIO', 'mp3,ogg,flac')),
    'allowed_preview_sizes' => array_map('intval',
        explode(',', env('ALLOWED_PREVIEW_SIZES', '144,240,360,480,720,1080'))
    ),
];
