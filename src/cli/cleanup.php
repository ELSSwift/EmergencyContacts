<?php
declare(strict_types=1);

/*
 * Aufbewahrung ausführen (z. B. täglich per Windows-Aufgabenplanung):
 *   php.exe C:\inetpub\emergencycontactsdev\cli\cleanup.php
 * Die App führt die Bereinigung zusätzlich stündlich bei normalen Aufrufen aus.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$r = run_retention();
echo date('c') . " Events abgeschlossen: {$r['events']}, anonyme Anmeldungen gelöscht: {$r['deleted']}\n";
