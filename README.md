# EmergencyContacts

Webanwendung, mit der Teilnehmende eines Events bzw. Vereinsmitglieder bei der Anmeldung Notfallkontakte und medizinische Informationen hinterlegen. Das Organisationsteam kann im Notfall darauf zugreifen – jeder Zugriff wird protokolliert und der betroffenen Person per E-Mail gemeldet. Umgesetzt in PHP (≥ 8.1) und SQLite, ohne externe Abhängigkeiten. Sprachen: DE, EN, FR, IT.

## Funktionen

- **Anmeldung anonym oder mit Konto**: Ohne Konto gibt es einen persönlichen Link zum Ändern/Löschen. Mit Konto werden die Angaben gespeichert und bei weiteren Events vorausgefüllt.
- **Events organisieren** (Login nötig): Event mit Anmeldelink, Team-Mitglieder (bestehende Konten) hinzufügen, Zugriffsdauer nach Event-Ende pro Event konfigurierbar (0–90 Tage).
- **Vollständigkeitsübersicht**: Das Team sieht pro Person nur, ob ein Notfallkontakt erfasst ist, über welche Wege (Telefon/E-Mail) dieser erreichbar ist und ob medizinische Angaben gemacht wurden (angegeben / keine relevanten / nicht ausgefüllt) – nicht die Inhalte.
- **Notfallzugriff**: Einzelperson oder Notfallliste aller Teilnehmenden, mit Pflicht-Begründung und Bestätigung. Jeder Zugriff wird protokolliert (für Team und Teilnehmende sichtbar) und an die von der Person angegebene Benachrichtigungsadresse gemeldet.
- **Aufbewahrung**: Nach Ablauf der Zugriffsfrist erlischt der Team-Zugriff; anonyme Anmeldungen (inkl. Protokolleinträge) werden gelöscht. Anmeldungen mit Konto bleiben im Konto. Die Bereinigung läuft stündlich bei Aufrufen und kann zusätzlich per `php cli/cleanup.php` geplant werden.
- **Sicherheit**: Personendaten AES-256-GCM-verschlüsselt in der DB, Passwörter mit `password_hash`, CSRF-Schutz, restriktive CSP, `data/` und `app/` über `web.config`/`.htaccess` gesperrt.

## Für den Verein personalisieren

Unter **Verein verwalten** (nur für Admins) pflegt der Verein:

- Name, Logo (PNG/JPG/WebP, max. 1 MB), Vereinsfarbe, Kontakt-E-Mail und Website
- angebotene Sprachen, Standardsprache und Standard-Zugriffsdauer für neue Events
- Titel und Begrüssungstext der Startseite pro Sprache
- Impressum und Datenschutzerklärung (pro Sprache), im Footer verlinkt
- Adresse der Website (für Links in E-Mails)
- **E-Mail-Versand**: Versandart (SMTP-Server, PHP `mail()` oder nur im Postausgang ablegen), Absenderadresse und -name, Antwortadresse, SMTP-Server/Port/Verschlüsselung/Benutzer/Passwort (verschlüsselt gespeichert) – mit Knopf für eine Testmail

In `config.php` bleibt nur, was der Server vor dem Start braucht: Betriebsmodus (`env`), Datenverzeichnis und Schlüssel. Die Mailwerte dort gelten nur als Startwerte.

Der Vereinsname erscheint auch als Absender und in der Signatur der E-Mails. Notfall- und Lösch-Elemente bleiben unabhängig von der Vereinsfarbe rot.

**Rollen:** *Mitglied* (eigene Anmeldungen), *Organisator:in* (darf Events anlegen), *Admin* (zusätzlich Einstellungen und Rollen). Jede Person kann ein Konto erstellen; Organisator:innen werden unter **Benutzer & Rollen** freigegeben. Dort können Admins auch Konten löschen: Anmeldungen an laufenden Events bleiben dabei anonym bis zum Ablauf der Zugriffsfrist bestehen, offene Events der Person gehen an den löschenden Admin über. Das erste Konto einer neuen Installation wird automatisch Admin. Rollen lassen sich auch auf dem Server setzen:

```bash
php cli/users.php list
```

```bash
php cli/users.php role vorstand@example.ch admin
```

## Struktur

```
src/                 Web-Root (wird deployt)
  index.php          Front-Controller (Routing über ?r=…)
  app/               Logik, Views, Sprachdateien (nicht öffentlich)
  assets/            CSS, JS, Icon
  cli/cleanup.php    Aufbewahrung manuell/geplant ausführen
  cli/users.php      Benutzer auflisten, Rollen setzen
  config.sample.php  Vorlage für config.php
  data/              SQLite-DB, Schlüssel, Sessions, Mail-Ablage (zur Laufzeit erzeugt)
tests/run.php        Unit-Tests (php tests/run.php)
tests/smoke.ps1      End-to-End-Test über HTTP
deploy.ps1           Deployment auf den lokalen IIS
```

## Lokal entwickeln

```bash
php -S 127.0.0.1:8765 -t src
```

Tests:

```bash
php tests/run.php
```

```bash
powershell -ExecutionPolicy Bypass -File tests/smoke.ps1 -BaseUrl http://127.0.0.1:8765 -AdminEmail <admin> -AdminPassword <passwort>
```

## Deployment (IIS)

In einer PowerShell **als Administrator**:

```bash
powershell -ExecutionPolicy Bypass -File deploy.ps1
```

Standardziel ist `C:\inetpub\emergencycontactsdev` (DEV, erreichbar unter http://localhost:8081). Das Skript spiegelt `src/`, lässt `data/` und `config.php` auf dem Ziel unverändert, legt beim ersten Mal `config.php` an und gibt `IIS_IUSRS`/`IUSR` Schreibrechte auf `data/`.

Auf DEV werden E-Mails nicht versendet, sondern unter `data/mail` abgelegt und im **DEV-Postausgang** (`index.php?r=dev/mail`, nach Login) angezeigt. Für echten Versand unter **Verein verwalten → E-Mail-Versand** den SMTP-Server eintragen und mit der Testmail prüfen.

**Wichtig:** `data/secret.key` verschlüsselt alle Personendaten – ohne diese Datei sind die Daten nicht lesbar. Für Produktion den Schlüssel als `secret_key` in `config.php` setzen und sicher sichern.
