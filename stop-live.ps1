$ErrorActionPreference = 'Continue'

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$DocumentRoot = Split-Path -Parent $ProjectRoot
$RuntimeDir = Join-Path $ProjectRoot '.runtime'
$PhpPidFile = Join-Path $RuntimeDir 'php-server.pid'
$MysqlPidFile = Join-Path $RuntimeDir 'mysql.pid'
$MysqlAdmin = if ($env:MYSQLADMIN_EXE) { $env:MYSQLADMIN_EXE } else { 'C:\xampp\mysql\bin\mysqladmin.exe' }
$AppPort = if ($env:APP_PORT) { [int]$env:APP_PORT } else { 8080 }

function Get-ProjectPhpServer {
    Get-CimInstance Win32_Process |
        Where-Object {
            $_.Name -ieq 'php.exe' -and
            $_.CommandLine -like "*-S*127.0.0.1:$AppPort*" -and
            $_.CommandLine -like "*$DocumentRoot*"
        } |
        Select-Object -First 1
}

if (Test-Path $PhpPidFile) {
    $phpPid = [int](Get-Content $PhpPidFile -ErrorAction SilentlyContinue)
    $phpProcess = Get-Process -Id $phpPid -ErrorAction SilentlyContinue
    if ($phpProcess) {
        Stop-Process -Id $phpPid -Force
        Write-Host "Stopped PHP server."
    }
    Remove-Item $PhpPidFile -Force -ErrorAction SilentlyContinue
}

$projectPhpServer = Get-ProjectPhpServer
if ($projectPhpServer) {
    Stop-Process -Id $projectPhpServer.ProcessId -Force
    Write-Host "Stopped project PHP server."
}

if (Test-Path $MysqlPidFile) {
    if (Test-Path $MysqlAdmin) {
        & $MysqlAdmin -uroot shutdown 2>$null
        Write-Host "Stopped database."
    } else {
        $mysqlPid = [int](Get-Content $MysqlPidFile -ErrorAction SilentlyContinue)
        $mysqlProcess = Get-Process -Id $mysqlPid -ErrorAction SilentlyContinue
        if ($mysqlProcess) {
            Stop-Process -Id $mysqlPid -Force
            Write-Host "Stopped database process."
        }
    }
    Remove-Item $MysqlPidFile -Force -ErrorAction SilentlyContinue
}

Write-Host "Done."
