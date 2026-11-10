<#
  DiSSENT one-click demo start (Windows PowerShell 5.1+)

  Usage (from the DiSSENT-clean folder):
    powershell -ExecutionPolicy Bypass -File .\start-demo.ps1

  What it does:
    1. Checks Docker is running
    2. Makes sure .env holds DISSENT_API_KEY (creates a random one if missing; never prints it)
    3. Starts the website, MySQL and phpMyAdmin (docker compose in this folder)
    4. Starts Kafka/Zookeeper if nothing is listening on 9092, and DiSSENT's own MongoDB
       container ("mongo", host port 27018). It never uses another project's MongoDB.
    5. Warns if the Hyperledger Fabric network is not running
    6. Opens securecomm (8081) and securecomm2 (8082) in their own windows with the key set
    7. Waits until everything answers, then opens the homepage
#>

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

# 2. Shared API key -------------------------------------------------------------
Write-Step 'Loading DISSENT_API_KEY from .env'
$keyLine = $null
if (Test-Path $EnvFile) {
    $keyLine = Get-Content $EnvFile | Where-Object { $_ -match '^DISSENT_API_KEY=.+' } | Select-Object -First 1
}
if (-not $keyLine) {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $newKey = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    Add-Content -Path $EnvFile -Value "DISSENT_API_KEY=$newKey" -Encoding ascii
    $keyLine = "DISSENT_API_KEY=$newKey"
    Write-Warn '.env had no DISSENT_API_KEY, so a new random key was created.'
}
# Set for this process; the Java windows started below inherit it (the key never appears on a command line).
$env:DISSENT_API_KEY = $keyLine -replace '^DISSENT_API_KEY=', ''
Write-Ok 'Key loaded (value hidden)'

# 3. Website + MySQL + phpMyAdmin --------------------------------------------------
Write-Step 'Starting website, MySQL and phpMyAdmin'
Push-Location $Root
try {
    docker compose up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'docker compose up failed for the website stack.' }
} finally { Pop-Location }
Write-Ok 'Website containers started'

# 4. Kafka + MongoDB ----------------------------------------------------------------
Write-Step 'Starting Kafka (9092) and DiSSENT MongoDB (27018)'
# Always DiSSENT's own "mongo" container; "up -d" is a no-op if it is already running as configured.
$needed = @('mongo')
if (Test-Port 9092) { Write-Ok 'Kafka already running' } else { $needed += 'zookeeper', 'kafka' }
Push-Location (Join-Path $JavaRoot 'securecomm')
try {
    docker compose up -d @needed
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
        Write-Warn "Port $($svc.Port) is already in use, so $($svc.Name) looks like it is already running (skipped). If it was started without DISSENT_API_KEY, close it and run this script again."
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
Start-Process $HomeUrl
