<?php
return [
    'allowed_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 
        'jpg', 'jpeg', 'png', 'gif',
        'zip', 'rar', '7z',
        'txt', 'csv'
    ],
    'max_file_size' => 50 * 1024 * 1024 * 1024, // 50 Go
    'upload_dir' => __DIR__ . '/../uploads/',
    'temp_dir' => __DIR__ . '/../temp/'
];
