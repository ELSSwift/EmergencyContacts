<?php
// Kopiere diese Datei nach config.php und passe sie an.
// config.php wird beim Deployment nicht überschrieben.
//
// Hier steht nur, was der Server vor dem Start wissen muss. Alles andere
// (Vereinsname, Adresse der Website, E-Mail-Versand inkl. SMTP-Server, Sprachen …)
// stellen Admins im Tool unter „Verein verwalten“ ein. Die Werte unter 'mail'
// dienen nur als Startwerte, solange dort nichts gespeichert wurde.
return [
    'env' => 'dev',                         // dev | prod
    // 'data_dir' => 'D:/emergencycontacts-data',
    // 'secret_key' => '<base64 von 32 Zufallsbytes>',   // sonst wird data/secret.key erzeugt
    'mail' => [
        'transport' => 'file',              // Startwert: file (Postausgang) | smtp | mail
        'from' => 'noreply@example.ch',
    ],
];
