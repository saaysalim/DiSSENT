<#
  DiSSENT one-click demo start (Windows PowerShell 5.1+)

  Usage (from the DiSSENT-clean folder):
    powershell -ExecutionPolicy Bypass -File .\start-demo.ps1
    powershell -ExecutionPolicy Bypass -File .\start-demo.ps1 -Share   # also a public demo link

  -Share starts a Cloudflare quick tunnel and restarts the website with APP_DEBUG=0 and
  APP_URL set to the public https://....trycloudflare.com address, so reset links are
  never shown on screen and emails link to the public address.
  Run the script again without -Share afterwards to go back to local settings.

  What it does:
    1. Checks Docker is running
    2. Makes sure .env holds DISSENT_API_KEY and API_CRYPTO_AES_KEY_BASE64 (creates random
       ones if missing; never prints them)
    3. Starts the website, MySQL and phpMyAdmin (docker compose in this folder)
    4. Starts Kafka/Zookeeper if nothing is listening on 9092 (stopped containers that already
       exist are started with "docker start"), and DiSSENT's own MongoDB
       container ("mongo", host port 27018). It never uses another project's MongoDB.
    5. Warns if the Hyperledger Fabric network is not running
    6. Opens securecomm (8081) and securecomm2 (8082) in their own windows with the key set
    7. Waits until everything answers, then opens the homepage
#>

param(
    # Public demo link through a Cloudflare quick tunnel.
    [switch]$Share,
    # Path to cloudflared.exe (only needed if it is not on PATH or in Program Files).
    [string]$Cloudflared = ''
)

$ErrorActionPreference = 'Stop'

$Root      = $PSScriptRoot
$JavaRoot  = Join-Path $Root 'Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main'
$EnvFile   = Join-Path $Root '.env'
$HomeUrl   = 'http://localhost:8888/Pages/home.php'

function Write-Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Write-Ok($text)   { Write-Host "    OK  $text" -ForegroundColor Green }
function Write-Warn($text) { Write-Host "    !!  $text" -ForegroundColor Yellow }

function Test-Port([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if ($async.AsyncWaitHandle.WaitOne(500)) { $client.EndConnect($async); return $true }
        return $false
    } catch { return $false } finally { $client.Close() }
}

# Returns the HTTP status code (401 counts as "running" for the API key-protected services), or 0.
function Get-HttpStatus([string]$Url) {
    try {
        return [int](Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 3).StatusCode
    } catch {
        if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
        return 0
    }
}

function Wait-Http([string]$Name, [string]$Url, [int[]]$OkCodes, [int]$TimeoutSec) {
    $deadline = (Get-Date).AddSeconds($TimeoutSec)
    while ((Get-Date) -lt $deadline) {
        if ($OkCodes -contains (Get-HttpStatus $Url)) { Write-Ok "$Name is up"; return $true }
        Start-Sleep -Seconds 3
    }
    Write-Warn "$Name did not answer within $TimeoutSec s ($Url). Check its window/logs."
    return $false
}

# 1. Docker -------------------------------------------------------------------
Write-Step 'Checking Docker'
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Docker is not running. Start Docker Desktop, wait until it is ready, then run this again.' -ForegroundColor Red
    exit 1
}
Write-Ok 'Docker is running'

# 2. Keys from .env ---------------------------------------------------------------
# Reads NAME=value from .env. If it is missing, makes a random value with $MakeValue and saves it.
# Set for this process; the Java windows started below inherit it (keys never appear on a command line).
function Use-EnvKey([string]$Name, [scriptblock]$MakeValue) {
    $line = $null
    if (Test-Path $EnvFile) {
        $line = Get-Content $EnvFile | Where-Object { $_ -match "^$Name=.+" } | Select-Object -First 1
    }
    if (-not $line) {
        $line = "$Name=$(& $MakeValue)"
        Add-Content -Path $EnvFile -Value $line -Encoding ascii
        Write-Warn ".env had no $Name, so a new random one was created."
    }
    Set-Item -Path "env:$Name" -Value ($line -replace "^$Name=", '')
    Write-Ok "$Name loaded (value hidden)"
}

function New-RandomBytes([int]$Count) {
    $bytes = New-Object byte[] $Count
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return ,$bytes
}

Write-Step 'Loading keys from .env'
# Website <-> Java services.
Use-EnvKey 'DISSENT_API_KEY' { -join ((New-RandomBytes 32) | ForEach-Object { $_.ToString('x2') }) }
# AES-256 key shared by securecomm and securecomm2 (32 bytes, base64). They refuse to start without it.
Use-EnvKey 'API_CRYPTO_AES_KEY_BASE64' { [Convert]::ToBase64String((New-RandomBytes 32)) }

# 2b. Public demo link (-Share) ------------------------------------------------------
if ($Share) {
    Write-Step 'Starting a Cloudflare quick tunnel (-Share)'
    if (-not $Cloudflared) {
        $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
        if ($cmd) { $Cloudflared = $cmd.Source }
        elseif (Test-Path 'C:\Program Files (x86)\cloudflared\cloudflared.exe') { $Cloudflared = 'C:\Program Files (x86)\cloudflared\cloudflared.exe' }
        elseif (Test-Path 'C:\Program Files\cloudflared\cloudflared.exe') { $Cloudflared = 'C:\Program Files\cloudflared\cloudflared.exe' }
    }
    if (-not $Cloudflared -or -not (Test-Path $Cloudflared)) {
        Write-Host 'cloudflared was not found. Install it with: winget install --id Cloudflare.cloudflared' -ForegroundColor Red
        exit 1
    }

    $tunnelLog = Join-Path $env:TEMP 'dissent-tunnel.log'
    $tunnelOut = Join-Path $env:TEMP 'dissent-tunnel.out.log'
    Remove-Item $tunnelLog, $tunnelOut -ErrorAction SilentlyContinue
    $tunnel = Start-Process -FilePath $Cloudflared -ArgumentList @('tunnel', '--no-autoupdate', '--url', 'http://localhost:8888') `
        -RedirectStandardError $tunnelLog -RedirectStandardOutput $tunnelOut -WindowStyle Hidden -PassThru

    # cloudflared prints the public address after a few seconds.
    $publicUrl = $null
    $deadline = (Get-Date).AddSeconds(45)
    while (-not $publicUrl -and (Get-Date) -lt $deadline -and -not $tunnel.HasExited) {
        Start-Sleep -Seconds 1
        foreach ($file in $tunnelLog, $tunnelOut) {
            if (-not $publicUrl -and (Test-Path $file)) {
                # (api.trycloudflare.com is the service cloudflared talks to, not the link.)
                $found = Select-String -Path $file -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' -AllMatches |
                    ForEach-Object { $_.Matches } | ForEach-Object { $_.Value } |
                    Where-Object { $_ -ne 'https://api.trycloudflare.com' } | Select-Object -First 1
                if ($found) { $publicUrl = $found }
            }
        }
    }
    if (-not $publicUrl) {
        if (-not $tunnel.HasExited) { Stop-Process -Id $tunnel.Id -Force }
        Write-Host "The tunnel did not give a public address. See $tunnelLog" -ForegroundColor Red
        exit 1
    }

    # Process settings win over .env in docker compose, so the website gets these.
    $env:APP_DEBUG = '0'
    $env:APP_URL = $publicUrl
    Write-Ok "Tunnel running (process $($tunnel.Id)): $publicUrl"
    Write-Ok "The website will use APP_DEBUG=0 and APP_URL=$publicUrl"
}

# 3. Website + MySQL + phpMyAdmin --------------------------------------------------
Write-Step 'Starting website, MySQL and phpMyAdmin'
Push-Location $Root
try {
    docker compose up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'docker compose up failed for the website stack.' }
} finally { Pop-Location }
Write-Ok 'Website containers started'

if ($Share) {
    # Never share the site while reset links could be shown on screen.
    $debugValue = (docker exec dissent-web printenv APP_DEBUG) 2>$null
    if ($debugValue -ne '0') {
        if (-not $tunnel.HasExited) { Stop-Process -Id $tunnel.Id -Force }
        Write-Host "The website still has APP_DEBUG=$debugValue, so the tunnel was stopped." -ForegroundColor Red
        exit 1
    }
    Write-Ok 'Checked: the website runs with APP_DEBUG=0'
}

# 4. Kafka + MongoDB ----------------------------------------------------------------
Write-Step 'Starting Kafka (9092) and DiSSENT MongoDB (27018)'
# Always DiSSENT's own "mongo" container; "up -d" is a no-op if it is already running as configured.
$needed = @('mongo')
if (Test-Port 9092) {
    Write-Ok 'Kafka already running'
} else {
    # Containers named "zookeeper" / "kafka" may already exist but be stopped. Creating them again
    # would fail with "container name already in use", so start the existing ones instead.
    $existing = @(docker ps -a --format '{{.Names}}')
    foreach ($name in 'zookeeper', 'kafka') {
        if ($existing -contains $name) {
            docker start $name | Out-Null
            if ($LASTEXITCODE -ne 0) { throw "Could not start the existing container '$name'. See: docker logs $name" }
            Write-Ok "Started existing container: $name"
        } else {
            $needed += $name
        }
    }
}
Push-Location (Join-Path $JavaRoot 'securecomm')
try {
    # --no-deps: only create what is listed; zookeeper/kafka are handled above.
    docker compose up -d --no-deps @needed
    if ($LASTEXITCODE -ne 0) { throw "Could not start: $($needed -join ', ')" }
} finally { Pop-Location }
if (Test-Port 27018) { Write-Ok 'DiSSENT MongoDB is up on 27018' }
else { Write-Warn 'Nothing answers on port 27018. Check: docker ps --filter name=^mongo$' }

# 5. Hyperledger Fabric ------------------------------------------------------------
Write-Step 'Checking Hyperledger Fabric (orderer 7050, peers 7051 / 9051)'
$fabricDown = @(7050, 7051, 9051) | Where-Object { -not (Test-Port $_) }
if ($fabricDown) {
    Write-Warn "Fabric is not reachable on port(s) $($fabricDown -join ', ')."
    Write-Warn 'Start the Fabric test-network first (see the Fabric section of the README); report approval will fail until it is up.'
} else {
    Write-Ok 'Fabric network is running'
}

# 6. Spring Boot services --------------------------------------------------------------
Write-Step 'Starting Spring Boot services'
$services = @(
    @{ Name = 'securecomm';  Port = 8081 },
    @{ Name = 'securecomm2'; Port = 8082 }
)
foreach ($svc in $services) {
    if (Test-Port $svc.Port) {
        Write-Warn "Port $($svc.Port) is already in use, so $($svc.Name) looks like it is already running (skipped). If it was started without DISSENT_API_KEY or API_CRYPTO_AES_KEY_BASE64, close it and run this script again."
        continue
    }
    $dir = Join-Path $JavaRoot $svc.Name
    $title = "$($svc.Name) :$($svc.Port)"
    Start-Process powershell -WorkingDirectory $dir -ArgumentList @(
        '-NoExit', '-ExecutionPolicy', 'Bypass', '-Command',
        "`$Host.UI.RawUI.WindowTitle = '$title'; .\mvnw.cmd spring-boot:run"
    ) | Out-Null
    Write-Ok "$($svc.Name) starting in a new window"
}

# 7. Wait and open ---------------------------------------------------------------------
Write-Step 'Waiting for everything to answer'
Wait-Http 'Website'     $HomeUrl                                 @(200)      60  | Out-Null
Wait-Http 'securecomm'  'http://localhost:8081/api/verification/integrity' @(200, 401) 180 | Out-Null
Wait-Http 'securecomm2' 'http://localhost:8082/api/verification/mirror'    @(200, 401) 180 | Out-Null

Write-Host "`nDiSSENT is ready:" -ForegroundColor Green
Write-Host "  Website     $HomeUrl"
Write-Host '  phpMyAdmin  http://127.0.0.1:8089'
Write-Host '  Emails      http://127.0.0.1:8025 (Mailpit test inbox)'
Write-Host '  APIs        http://localhost:8081 (securecomm), http://localhost:8082 (securecomm2)'
if ($Share) {
    Write-Host "`nPublic demo link (share this):" -ForegroundColor Green
    Write-Host "  $publicUrl/Pages/home.php"
    Write-Host "  The link works while this laptop is on and online. Stop sharing with:"
    Write-Host "  Stop-Process -Id $($tunnel.Id)"
    Write-Host '  then run .\start-demo.ps1 again (without -Share) to go back to local settings.'
}
Start-Process $HomeUrl
