<?php

return [
    // Temporary uploads stay private even when final media uses a public disk.
    'temporary_file_upload' => [
        'disk' => 'private',
        'rules' => ['required', 'file', 'max:12288'],
    ],
];
