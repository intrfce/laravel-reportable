<?php

return [

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
    |
    */

    'chunk_size' => env('REPORTABLE_CHUNK_SIZE', 1000),

];
