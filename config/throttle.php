<?php

return [
    // Throttle settings for authentication-related actions
    'verify_email' => [
        'limit' => 5,
        'decay' => 60,
    ],
    'login' => [
        'limit' => 5,
        'decay' => 180,
    ],
    'forgot_password' => [
        'limit' => 3,
        'decay' => 60,
    ],
];
