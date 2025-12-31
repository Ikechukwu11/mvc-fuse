<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fuse Layout
    |--------------------------------------------------------------------------
    |
    | The default layout used when rendering full-page components.
    |
    */
    'layout' => 'layouts/main',

    /*
    |--------------------------------------------------------------------------
    | Loading Indicator
    |--------------------------------------------------------------------------
    |
    | Configuration for the loading indicator during navigation or actions.
    |
    */
    'loading' => [
        'enabled' => true,
        'color' => '#4F46E5', // Indigo 600
        'height' => '3px',
        'spinner' => true, // Circular loader at top right
    ],
];
