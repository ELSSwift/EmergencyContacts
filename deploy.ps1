# Deployment auf den lokalen IIS (als Administrator ausführen)
#   powershell -ExecutionPolicy Bypass -File .\deploy.ps1
#   powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Target C:\inetpub\emergencycontacts -Env prod
param(
    [string]$Target = 'C:\inetpub\emergencycontactsdev',
    [ValidateSet('dev', 'prod')][string]$Env = 'dev',
    [string]$LogFile = ''
)

$ErrorActionPreference = 'Stop'
if ($LogFile) { Start-Transcript -Path $LogFile -Force | Out-Null }
try {
    $repo = $PSScriptRoot
    $src = Join-Path $repo 'src'
    Write-Host "Deploy $src -> $Target ($Env)"

    if (-not (Test-Path $Target)) { New-Item -ItemType Directory -Path $Target | Out-Null }

    # Dateien spiegeln; data\ und config.php bleiben auf dem Ziel unangetastet
    robocopy $src $Target /MIR /XD (Join-Path $src 'data') data /XF config.php /NFL /NDL /NJH /NP | Out-Host
    if ($LASTEXITCODE -ge 8) { throw "robocopy fehlgeschlagen ($LASTEXITCODE)" }

    $config = Join-Path $Target 'config.php'
    if (-not (Test-Path $config)) {
        $sample = Get-Content (Join-Path $src 'config.sample.php') -Raw
        $sample = $sample -replace "'env' => 'dev'", "'env' => '$Env'"
        [IO.File]::WriteAllText($config, $sample, [Text.UTF8Encoding]::new($false))
        Write-Host "config.php angelegt (env=$Env)"
    }

    # Schreibrechte für IIS auf data\ (SQLite-DB, Sessions, Mail-Ablage)
    $data = Join-Path $Target 'data'
    if (-not (Test-Path $data)) { New-Item -ItemType Directory -Path $data | Out-Null }
    icacls $data /grant '*S-1-5-32-568:(OI)(CI)M' '*S-1-5-17:(OI)(CI)M' /T /Q | Out-Host   # IIS_IUSRS, IUSR
    if ($LASTEXITCODE -ne 0) { throw "icacls fehlgeschlagen ($LASTEXITCODE)" }

    $commit = (git -C $repo rev-parse --short HEAD 2>$null)
    $branch = (git -C $repo rev-parse --abbrev-ref HEAD 2>$null)
    $dirty = if (git -C $repo status --porcelain 2>$null) { ' (mit lokalen Änderungen)' } else { '' }
    "$(Get-Date -Format s) $branch@$commit$dirty" | Set-Content (Join-Path $data 'deployed.txt') -Encoding UTF8

    Write-Host "Fertig: $branch@$commit$dirty"
} catch {
    Write-Host "FEHLER: $_" -ForegroundColor Red
    $global:LASTEXITCODE = 1
} finally {
    if ($LogFile) { Stop-Transcript | Out-Null }
}
exit $LASTEXITCODE
