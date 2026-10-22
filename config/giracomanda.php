<?php

return [
    'establishment_name' => env('ESTABLISHMENT_NAME', 'GiraComanda'),
    'general_due_day' => (int) env('GENERAL_DUE_DAY', 10),
    'subscription' => [
        'monthly_amount_cents' => (int) env('SUBSCRIPTION_MONTHLY_AMOUNT_CENTS', 19700),
        'allow_test_settle' => (bool) env('SUBSCRIPTION_ALLOW_TEST_SETTLE', true),
        'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 7),
        'pix_key' => env('SUBSCRIPTION_PIX_KEY'),
        'pix_copy_paste' => env('SUBSCRIPTION_PIX_COPY_PASTE'),
        'support_whatsapp' => env('SUBSCRIPTION_SUPPORT_WHATSAPP'),
    ],

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
