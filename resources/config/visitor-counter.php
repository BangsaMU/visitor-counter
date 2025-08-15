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
];
