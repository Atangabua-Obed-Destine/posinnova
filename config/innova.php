<?php

/*
|--------------------------------------------------------------------------
| I-NNOVA POS: what every installation has in common
|--------------------------------------------------------------------------
|
| Every new installation (php artisan innova:install) gets the same
| superadmin, in its own business "I-NNOVA", and the same package for the
| client's business. See INSTALL.md.
|
*/

return [

    'brand' => 'I-NNOVA POS',

    'superadmin' => [
        'username' => 'kentojava',
        'first_name' => 'I-NNOVA',
        'last_name' => 'Support',
        'business_name' => 'I-NNOVA',

        // A one-way bcrypt hash of the superadmin password, never the password itself:
        // this repository is public. Set or change it with
        //     php artisan innova:superadmin-password
        // then commit and push. Installs pick it up at install, or with
        //     php artisan innova:superadmin
        'password_hash' => '$2y$12$H6n5.tfOKJ0YAfiSGMqn1ub12YTlyC8DlCu/HPqvW7DDKGGHB6hrG',
    ],

    // The package the client's business is subscribed to (all features, free)
    'package' => [
        'name' => 'I-NNOVA',
        'description' => 'All features',
        'years' => 7,
    ],

    // Defaults of the "create the business" page an installation ends with
    'defaults' => [
        'currency_code' => 'XAF',
        'country' => 'Cameroon',
        'time_zone' => 'Africa/Douala',
        'language' => 'en',
    ],
];
