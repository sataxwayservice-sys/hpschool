$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$RuntimeDir = Join-Path $ProjectRoot '.runtime'
$TunnelDir = Join-Path $RuntimeDir 'cloudflared'
$TunnelExe = Join-Path $TunnelDir 'cloudflared.exe'
$TunnelPidFile = Join-Path $RuntimeDir 'cloudflared.pid'
$TunnelStdout = Join-Path $RuntimeDir 'cloudflared.stdout.log'
$TunnelStderr = Join-Path $RuntimeDir 'cloudflared.stderr.log'
$TunnelUrlFile = Join-Path $RuntimeDir 'public-url.txt'
$OriginUrl = if ($env:PUBLIC_ORIGIN_URL) { $env:PUBLIC_ORIGIN_URL } else { 'http://127.0.0.1:8080/account3/' }

function Test-TcpPort {
    param([string]$HostName, [int]$Port)

    try {
        $client = [System.Net.Sockets.TcpClient]::new()
        $async = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne(1000, $false)) {
            $client.Close()
            return $false
        }
        $client.EndConnect($async)
        $client.Close()
        return $true
    } catch {
        return $false
    }
}

function Wait-ForPort {
    param([string]$HostName, [int]$Port, [int]$Seconds = 20)

    $deadline = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $deadline) {
        if (Test-TcpPort -HostName $HostName -Port $Port) {
            return $true
        }
        Start-Sleep -Milliseconds 500
    }
    return $false
}

New-Item -ItemType Directory -Force -Path $RuntimeDir | Out-Null
New-Item -ItemType Directory -Force -Path $TunnelDir | Out-Null

& (Join-Path $ProjectRoot 'start-live.ps1')

if (-not (Test-TcpPort -HostName '127.0.0.1' -Port 8080)) {
    if (-not (Wait-ForPort -HostName '127.0.0.1' -Port 8080 -Seconds 10)) {
        throw 'Local site is not available on http://127.0.0.1:8080/account3/'
    }
}

if (-not (Test-Path $TunnelExe)) {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $downloadUrl = 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
    Write-Host "Downloading Cloudflare tunnel client..."
    Invoke-WebRequest -Uri $downloadUrl -OutFile $TunnelExe
}

$existingPid = $null
if (Test-Path $TunnelPidFile) {
    try {
        $existingPid = [int](Get-Content $TunnelPidFile -ErrorAction Stop)
    } catch {
        $existingPid = $null
    }
}

if ($existingPid) {
    $existingProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
    if ($existingProcess) {
        Write-Host "Public tunnel is already running."
        if (Test-Path $TunnelUrlFile) {
            $savedUrl = (Get-Content $TunnelUrlFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
            if ($savedUrl) {
                Write-Host ""
                Write-Host "Public live link:"
                Write-Host $savedUrl
            }
        }
        Write-Host ""
        Write-Host "To stop it later, run: .\stop-public.ps1"
        return
    }
    Remove-Item $TunnelPidFile -Force -ErrorAction SilentlyContinue
}

Remove-Item $TunnelStdout, $TunnelStderr, $TunnelUrlFile -Force -ErrorAction SilentlyContinue

$arguments = @(
    'tunnel'
    '--no-autoupdate'
    '--url'
    $OriginUrl
)

Write-Host "Starting public tunnel..."
$process = Start-Process -FilePath $TunnelExe -ArgumentList $arguments -WorkingDirectory $TunnelDir -WindowStyle Hidden -RedirectStandardOutput $TunnelStdout -RedirectStandardError $TunnelStderr -PassThru
Set-Content -Path $TunnelPidFile -Value $process.Id

$publicUrl = $null
for ($i = 0; $i -lt 60; $i++) {
    Start-Sleep -Seconds 1
    $combined = ''
    if (Test-Path $TunnelStdout) {
        $combined += Get-Content $TunnelStdout -Raw -ErrorAction SilentlyContinue
    }
    if (Test-Path $TunnelStderr) {
        $combined += "`n" + (Get-Content $TunnelStderr -Raw -ErrorAction SilentlyContinue)
    }

    if ($combined -match 'https://[a-zA-Z0-9.-]+\.trycloudflare\.com') {
        $publicUrl = $matches[0]
        break
    }
}

if ($publicUrl) {
    Set-Content -Path $TunnelUrlFile -Value $publicUrl
    Write-Host ""
    Write-Host "Public live link:"
    Write-Host $publicUrl
    Write-Host ""
    Write-Host "This link stays up while the tunnel process keeps running on this machine."
    Write-Host "To stop it later, run: .\stop-public.ps1"
} else {
    Write-Warning "Tunnel started, but the public URL was not detected yet."
    Write-Host "Check these logs:"
    Write-Host $TunnelStdout
    Write-Host $TunnelStderr
}
