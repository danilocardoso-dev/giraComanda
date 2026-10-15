<?php

return [
    'establishment_name' => env('ESTABLISHMENT_NAME', 'GiraComanda'),
    'general_due_day' => (int) env('GENERAL_DUE_DAY', 10),

    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'Administrador'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

    'initial_establishment_owner' => [
        'name' => env('INITIAL_ESTABLISHMENT_OWNER_NAME', 'Proprietário do Estabelecimento'),
        'email' => env('INITIAL_ESTABLISHMENT_OWNER_EMAIL'),
        'password' => env('INITIAL_ESTABLISHMENT_OWNER_PASSWORD'),
    ],
];
