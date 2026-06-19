$ErrorActionPreference = 'Continue'

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$RuntimeDir = Join-Path $ProjectRoot '.runtime'
$TunnelPidFile = Join-Path $RuntimeDir 'cloudflared.pid'
$TunnelUrlFile = Join-Path $RuntimeDir 'public-url.txt'
$TunnelStdout = Join-Path $RuntimeDir 'cloudflared.stdout.log'
$TunnelStderr = Join-Path $RuntimeDir 'cloudflared.stderr.log'

if (Test-Path $TunnelPidFile) {
    try {
        $pid = [int](Get-Content $TunnelPidFile -ErrorAction Stop)
        $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
        if ($process) {
            Stop-Process -Id $pid -Force
            Write-Host "Stopped public tunnel."
        }
    } catch {
        Write-Host "Could not read tunnel PID file."
    }
    Remove-Item $TunnelPidFile -Force -ErrorAction SilentlyContinue
}

Remove-Item $TunnelUrlFile, $TunnelStdout, $TunnelStderr -Force -ErrorAction SilentlyContinue

& (Join-Path $ProjectRoot 'stop-live.ps1')
