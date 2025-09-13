<?php
return [
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;dbname=hireme;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'mail' => [
        'host'       => 'mail.ga2wellness.com',
        'port'       => 587,
        'username'   => 'choongyunxian@ga2wellness.com',
        'password'   => '+60123095550',
        'secure'     => 'tls',
        'from_email' => 'choongyunxian@ga2wellness.com',
        'from_name'  => 'HireMe Admin',
    ],
    'stripe' => [
        'secret_key'      => 'sk_test_51S6cp6Is8dFmRPkxfdTzbqZelYloc0mX1RnZi8fCcwwf8SSknly87czq7kqJx8tSf1jGg3XpNrP3GZTVq5m5tMUv00BMBb1XYB',
        'publishable_key' => 'pk_test_51S6cp6Is8dFmRPkxA9GTi4qC15dxdAz2L9qtmkiGZS4BIP3Zp1gVfmiUhKIOfbnDrBfzCdeV009ZlwgprWtjj3ik00JlJCZbzp',
        'currency'        => 'myr',
        'webhook_secret'  => 'whsec_xxx',
    ],
];
