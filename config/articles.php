<?php

return [
    // Preserve existing public S3 media deployments; local media must use the
    // public disk rather than storage/app, which the public link does not serve.
    'disk' => env('ARTICLE_MEDIA_DISK', env('FILESYSTEM_DISK') === 's3' ? 's3' : 'public'),
    'image_max_kb' => 5120,
];
