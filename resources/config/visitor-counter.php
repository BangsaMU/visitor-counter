<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode pencatatan visitor
    |--------------------------------------------------------------------------
    | Pilihan:
    | - unique_daily : hanya catat sekali per IP per hari
    | - log_all      : catat semua request
    */
    'mode' => env('VISITOR_COUNTER_MODE', 'log_all'),
    'cache_time' => env('VISITOR_COUNTER_CACHE_TIME', 10),
];
