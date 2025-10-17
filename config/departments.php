<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Department Mappings
    |--------------------------------------------------------------------------
    |
    | This file contains the mapping between department slugs and their
    | full names. This mapping is used across the application for routing
    | and filtering academic papers by department.
    |
    */

    'mapping' => [
        'it' => 'Information Technology',
        'ce' => 'Civil Engineering',
        'ee' => 'Electrical Engineering',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valid Department Names
    |--------------------------------------------------------------------------
    |
    | List of valid department names that can be used for filtering.
    | This is used for validation purposes.
    |
    */

    'valid_names' => [
        'Information Technology',
        'Civil Engineering',
        'Electrical Engineering',
    ],

    /*
    |--------------------------------------------------------------------------
    | Department Icons
    |--------------------------------------------------------------------------
    |
    | Mapping of department names to their respective icon assets.
    |
    */

    'icons' => [
        'Information Technology' => 'images/vits.png',
        'Civil Engineering' => 'images/aces.png',
        'Electrical Engineering' => 'images/ees.png',
    ],
];
