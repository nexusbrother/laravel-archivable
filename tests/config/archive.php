<?php

return [
    'enable' => env('ARCHIVE_ENABLE', false),

    'schedule_daily_at' => [
        'archive_structure_sync' => env('ARCHIVE_SCHEDULE_DAILY_AT', '09:00'),
        'archive'                => env('ARCHIVE_SCHEDULE_DAILY_AT', '09:10'),
    ],

    'default_chunk_size' => 1000,

    'db' => env('ARCHIVE_DB_CONNECTION', 'archive'),

    'model_paths' => [
        app_path('Models'),
        env('ARCHIVE_MODEL_PATHS', ''),
    ],
];
