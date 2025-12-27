<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default filesystem disk to use for storing exported CSV files.
    |
    */

    'disk' => env('REPORTABLE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Output Path
    |--------------------------------------------------------------------------
    |
    | The default directory path within the disk where reports will be saved.
    |
    */

    'output_path' => env('REPORTABLE_OUTPUT_PATH', 'reports'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue
    |--------------------------------------------------------------------------
    |
    | The default queue that reportable jobs will be dispatched to.
    |
    */

    'queue' => env('REPORTABLE_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | The default queue connection that reportable jobs will use.
    |
    */

    'connection' => env('REPORTABLE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of rows to process at a time when exporting large datasets.
    | Set to a lower value if you're running into memory issues.
    |
    */

    'chunk_size' => env('REPORTABLE_CHUNK_SIZE', 1000),

];
