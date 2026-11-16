<?php
// Kopiere diese Datei nach config.php und passe sie an.
// config.php wird beim Deployment nicht überschrieben.
return [
    'env' => 'dev',                         // dev | prod
    // 'base_url' => 'https://notfall.example.ch',
    // 'secret_key' => '<base64 von 32 Zufallsbytes>',   // sonst wird data/secret.key erzeugt
    'default_retention_days' => 7,
    'mail' => [
        'transport' => 'file',              // file (DEV-Postausgang) | smtp | mail
        'from' => 'noreply@example.ch',
        'from_name' => 'EmergencyContacts',
        // 'smtp_host' => 'smtp.example.ch',
        // 'smtp_port' => 587,
        // 'smtp_secure' => 'tls',
        // 'smtp_user' => '',
        // 'smtp_pass' => '',
    ],
];
