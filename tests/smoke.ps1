# End-to-End-Smoketest über HTTP:  .\tests\smoke.ps1 -BaseUrl http://localhost:8081
# Mit bestehender Datenbank ein Admin-Konto angeben, das die Test-Organisatorin freischaltet:
#   .\tests\smoke.ps1 -BaseUrl http://localhost:8081 -AdminEmail admin@example.ch -AdminPassword ...
# Ohne Admin-Angaben wird angenommen, dass die DB leer ist (erstes Konto wird Admin).
param(
    [Parameter(Mandatory = $true)][string]$BaseUrl,
    [string]$AdminEmail = '',
    [string]$AdminPassword = ''
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$fail = 0
function Check($name, $cond) {
    if ($cond) { Write-Host "  ok   $name" } else { Write-Host "  FAIL $name" -ForegroundColor Red; $script:fail++ }
}
function Csrf($html) { if ($html -match 'name="_csrf" value="([^"]+)"') { $Matches[1] } else { throw 'Kein CSRF-Token' } }
function Get-Page($session, $path) { Invoke-WebRequest "$BaseUrl/$path" -WebSession $session -UseBasicParsing -MaximumRedirection 5 }
function Post-Page($session, $path, $body) { Invoke-WebRequest "$BaseUrl/$path" -WebSession $session -UseBasicParsing -Method Post -Body $body -MaximumRedirection 5 }

$id = Get-Random
$orgMail = "orga$id@example.ch"

# Organisator:in registrieren
$org = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Get-Page $org 'index.php?r=lang&l=de'
Check 'Startseite erreichbar' ($r.StatusCode -eq 200 -and $r.Content -match 'class="brand"')
$r = Get-Page $org 'index.php?r=register'
$r = Post-Page $org 'index.php?r=register' @{ _csrf = (Csrf $r.Content); name = 'Olga Orga'; email = $orgMail; password = 'sehrgeheim123'; password2 = 'sehrgeheim123' }
Check 'Konto erstellt' ($r.Content -match 'Hallo Olga Orga')

# Vereinsauftritt
$r = Get-Page $org 'index.php?r=theme.css'
Check 'Theme-CSS mit Vereinsfarbe' ($r.Content -match '--primary:#[0-9a-fA-F]{6}')
$r = Get-Page $org 'index.php?r=legal'
Check 'Impressum & Datenschutz erreichbar' ($r.Content -match 'Impressum' -and $r.Content -match 'Datenschutz')

if ($AdminEmail) {
    # Neues Konto darf noch keine Events anlegen
    $code = try { (Get-Page $org 'index.php?r=event/new').StatusCode } catch { [int]$_.Exception.Response.StatusCode }
    Check 'Ohne Freigabe kein Event' ($code -eq 403)
    $code = try { (Get-Page $org 'index.php?r=admin').StatusCode } catch { [int]$_.Exception.Response.StatusCode }
    Check 'Mitglied hat keinen Admin-Zugang' ($code -eq 403)

    # Admin schaltet frei
    $adm = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Get-Page $adm 'index.php?r=login'
    $r = Post-Page $adm 'index.php?r=login' @{ _csrf = (Csrf $r.Content); email = $AdminEmail; password = $AdminPassword }
    $r = Get-Page $adm 'index.php?r=admin'
    Check 'Admin-Einstellungen erreichbar' ($r.Content -match 'name="club_name"')
    $r = Get-Page $adm "index.php?r=admin/users&q=$orgMail"
    $uid = if ($r.Content -match 'name="user_id" value="(\d+)"') { $Matches[1] } else { $null }
    $r = Post-Page $adm "index.php?r=admin/users&q=$orgMail" @{ _csrf = (Csrf $r.Content); user_id = $uid; role = 'organizer' }
    Check 'Als Organisator:in freigegeben' ($r.Content -match 'Olga Orga ist jetzt Organisator')
}

# Event erstellen
$r = Get-Page $org 'index.php?r=event/new'
$start = (Get-Date).ToString('yyyy-MM-dd'); $end = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
$r = Post-Page $org 'index.php?r=event/new' @{ _csrf = (Csrf $r.Content); title = "Smoketest $id"; location = 'Bern'; starts_on = $start; ends_on = $end; description = 'Test'; retention_days = '3'; registration_open = '1' }
Check 'Event erstellt' ($r.Content -match "Smoketest $id" -and $r.Content -match 'Anmeldelink')
$regUrl = if ($r.Content -match 'id="reglink"' -and $r.Content -match 'value="([^"]*r=reg&amp;t=[^"]+)"') { [System.Net.WebUtility]::HtmlDecode($Matches[1]) } else { $null }
Check 'Anmeldelink vorhanden' ($null -ne $regUrl)
$eventId = if ($r.BaseResponse.ResponseUri -match 'id=(\d+)') { $Matches[1] } elseif ($r.Content -match 'r=event%2Fedit&amp;id=(\d+)') { $Matches[1] } else { $null }
$regPath = $regUrl.Substring($regUrl.IndexOf('index.php'))

# Anonyme Anmeldung
$anon = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Get-Page $anon $regPath
Check 'Anmeldeseite zeigt Auswahl' ($r.Content -match 'Ohne Konto fortfahren')
$r = Get-Page $anon "$regPath&mode=anon"
$body = @{ _csrf = (Csrf $r.Content); first_name = 'Anna'; last_name = 'Anonym'; notify_email = "anna$id@example.ch"
    'contacts[0][name]' = 'Beat'; 'contacts[0][relation]' = 'Vater'; 'contacts[0][phone]' = '+41 79 111 22 33'
    allergies = 'Penicillin'; consent = '1' }
$r = Post-Page $anon "$regPath&mode=anon" $body
Check 'Anonyme Anmeldung gespeichert' ($r.Content -match 'Vielen Dank')
$editPath = if ($r.Content -match 'value="[^"]*(index\.php\?r=mine&amp;k=[^"]+)"') { [System.Net.WebUtility]::HtmlDecode($Matches[1]) } else { $null }
Check 'Persönlicher Link angezeigt' ($null -ne $editPath)

# Anmeldung ohne Kontakt wird abgelehnt
$r = Get-Page $anon "$regPath&mode=anon"
$r = Post-Page $anon "$regPath&mode=anon" @{ _csrf = (Csrf $r.Content); first_name = 'Ohne'; last_name = 'Kontakt'; notify_email = "x$id@example.ch"; consent = '1' }
Check 'Ohne Notfallkontakt abgelehnt' ($r.Content -match 'mindestens einen Notfallkontakt')

# Übersicht für das Team
$r = Get-Page $org "index.php?r=event&id=$eventId"
Check 'Übersicht zeigt Anmeldung' ($r.Content -match 'Anna Anonym' -and $r.Content -match 'angegeben')
Check 'Übersicht zeigt keine medizinischen Inhalte' (-not ($r.Content -match 'Penicillin'))

# Notfallzugriff
$regId = if ($r.Content -match 'r=emergency&amp;id=\d+&amp;reg=(\d+)') { $Matches[1] } else { $null }
$r = Get-Page $org "index.php?r=emergency&id=$eventId&reg=$regId"
$csrf = Csrf $r.Content
$r = Post-Page $org "index.php?r=emergency&id=$eventId&reg=$regId" @{ _csrf = $csrf; reason = 'Kurz'; confirm = '1' }
Check 'Zu kurze Begründung abgelehnt' ($r.Content -match 'mindestens 5 Zeichen' -and -not ($r.Content -match 'Penicillin'))
$r = Post-Page $org "index.php?r=emergency&id=$eventId&reg=$regId" @{ _csrf = $csrf; reason = 'Smoketest Notfall'; confirm = '1' }
Check 'Notfalldaten angezeigt' ($r.Content -match 'Penicillin' -and $r.Content -match '\+41 79 111 22 33')

# Teilnehmende sieht Zugriff
$r = Get-Page $anon $editPath
Check 'Teilnehmende sieht Zugriff im Protokoll' ($r.Content -match 'Smoketest Notfall' -and $r.Content -match 'Olga Orga')

# DEV-Postausgang
$r = Get-Page $org 'index.php?r=dev/mail'
Check 'Benachrichtigung im Postausgang' ($r.Content -match "anna$id@example.ch" -and $r.Content -match 'Notfallzugriff auf deine Angaben')

# Fremde haben keinen Zugriff
$r = try { Get-Page $anon "index.php?r=event&id=$eventId" } catch { $_.Exception.Response }
Check 'Ohne Login keine Event-Übersicht' (-not ($r.Content -match 'Anna Anonym'))

# Sprachen
foreach ($l in @(@('en', 'Emergency contacts'), @('fr', 'Se connecter'), @('it', 'Accedi'))) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Get-Page $s "index.php?r=lang&l=$($l[0])"
    Check "Sprache $($l[0])" ($r.Content -match [regex]::Escape($l[1]))
}

# Geschützte Pfade
foreach ($p in 'data/emergencycontacts.sqlite', 'data/secret.key', 'app/bootstrap.php') {
    $code = try { (Invoke-WebRequest "$BaseUrl/$p" -UseBasicParsing).StatusCode } catch { [int]$_.Exception.Response.StatusCode }
    Check "Gesperrt: $p ($code)" ($code -ge 400)
}

# Aufräumen: Anmeldung löschen
$r = Get-Page $anon $editPath
$r = Post-Page $anon $editPath @{ _csrf = (Csrf $r.Content); action = 'delete' }
Check 'Anmeldung gelöscht' ($r.Content -match 'gelöscht')

Write-Host ""
Write-Host "Fehler: $fail"
exit $fail
