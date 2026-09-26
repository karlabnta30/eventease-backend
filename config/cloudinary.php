<?php

return [

    'cloud_url' => env('CLOUDINARY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Root-level keys (Fixes the Undefined array key "key" error)
    |--------------------------------------------------------------------------
    */
    'key'        => env('CLOUDINARY_API_KEY'),
    'secret'     => env('CLOUDINARY_API_SECRET'),
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Nested cloud array
    |--------------------------------------------------------------------------
    */
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'key'        => env('CLOUDINARY_API_KEY'),
        'secret'     => env('CLOUDINARY_API_SECRET'),
    ],

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    'upload_route'  => env('CLOUDINARY_UPLOAD_ROUTE'),
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),

];