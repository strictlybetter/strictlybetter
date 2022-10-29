<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Externals
    |--------------------------------------------------------------------------
    |
    | This file is for storing urls to external services outside of our control.
    |
    */

    // Browser is hinted to preconnect to these urls for faster image loads
    'preconnect' => [
        'https://cards.scryfall.io'
    ],

    // Important Scryfall urls where we can download data
    'scryfall' => [
        'bulk-data' => 'https://api.scryfall.com/bulk-data',
        'catalog' => 'https://api.scryfall.com/catalog'
    ],

    // Shown on some pages as author / contact email
    'author_email' => env('MAIL_AUTHOR', '')
    
];
