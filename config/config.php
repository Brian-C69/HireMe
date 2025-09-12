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
];
