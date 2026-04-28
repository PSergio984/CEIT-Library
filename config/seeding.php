<?php

$superAdminEmail = trim((string) env('SEED_SUPER_ADMIN_EMAIL', ''));

return [
    'super_admin' => [
        'email' => $superAdminEmail !== '' ? $superAdminEmail : 'superadmin@plv.edu.ph',
        'password' => env('SEED_SUPER_ADMIN_PASSWORD'),
    ],
];
