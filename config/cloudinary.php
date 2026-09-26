<?php

return [

    'cloud_url' => env('CLOUDINARY_URL'),

    // Package root lookups
    'key'        => env('CLOUDINARY_API_KEY'),
    'secret'     => env('CLOUDINARY_API_SECRET'),
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),

    // Package nested 'cloud' lookups (covers line 65 requirements)
    'cloud' => [
        'key'        => env('CLOUDINARY_API_KEY'),
        'secret'     => env('CLOUDINARY_API_SECRET'),
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    'upload_route'  => env('CLOUDINARY_UPLOAD_ROUTE'),
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),

];