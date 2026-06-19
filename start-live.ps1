$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$DocumentRoot = Split-Path -Parent $ProjectRoot
$RuntimeDir = Join-Path $ProjectRoot '.runtime'
$PhpPidFile = Join-Path $RuntimeDir 'php-server.pid'
$MysqlPidFile = Join-Path $RuntimeDir 'mysql.pid'

$PhpExe = if ($env:PHP_EXE) { $env:PHP_EXE } elseif (Get-Command php -ErrorAction SilentlyContinue) { (Get-Command php).Source } else { 'C:\xampp\php\php.exe' }
$MysqlExe = if ($env:MYSQLD_EXE) { $env:MYSQLD_EXE } else { 'C:\xampp\mysql\bin\mysqld.exe' }
$MysqlClient = if ($env:MYSQL_EXE) { $env:MYSQL_EXE } else { 'C:\xampp\mysql\bin\mysql.exe' }
$MysqlDefaults = if ($env:MYSQL_DEFAULTS_FILE) { $env:MYSQL_DEFAULTS_FILE } else { 'C:\xampp\mysql\bin\my.ini' }

$AppPort = if ($env:APP_PORT) { [int]$env:APP_PORT } else { 8080 }
$MysqlPort = 3306
$DbName = if ($env:DB_NAME) { $env:DB_NAME } else { 'school_fees_system' }
$Url = "http://localhost:$AppPort/account3/"

function Get-ProjectPhpServer {
    Get-CimInstance Win32_Process |
        Where-Object {
            $_.Name -ieq 'php.exe' -and
            $_.CommandLine -like "*-S*127.0.0.1:$AppPort*" -and
            $_.CommandLine -like "*$DocumentRoot*"
        } |
        Select-Object -First 1
}

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

if (-not (Test-Path $PhpExe)) {
    throw "PHP was not found. Set PHP_EXE or install PHP. Tried: $PhpExe"
}

if (-not (Test-Path $MysqlExe)) {
    throw "MariaDB/MySQL was not found. Set MYSQLD_EXE or install MySQL. Tried: $MysqlExe"
}

New-Item -ItemType Directory -Force -Path $RuntimeDir | Out-Null

if (-not (Test-TcpPort -HostName '127.0.0.1' -Port $MysqlPort)) {
    Write-Host "Starting database..."
    $mysqlArgs = @("--defaults-file=$MysqlDefaults", '--standalone')
    $mysqlProcess = Start-Process -FilePath $MysqlExe -ArgumentList $mysqlArgs -WorkingDirectory (Split-Path -Parent $MysqlExe) -WindowStyle Hidden -PassThru
    Set-Content -Path $MysqlPidFile -Value $mysqlProcess.Id

    if (-not (Wait-ForPort -HostName '127.0.0.1' -Port $MysqlPort -Seconds 25)) {
        throw "Database did not start on port $MysqlPort. Check C:\xampp\mysql\data\mysql_error.log"
    }
} else {
    Write-Host "Database is already running."
}

if (Test-Path $MysqlClient) {
    & $MysqlClient -uroot -e "CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null

    $tableCount = & $MysqlClient -uroot -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DbName';"
    if ([int]$tableCount -eq 0) {
        $schemaFile = Join-Path $ProjectRoot 'database\school_management.sql'
        if (Test-Path $schemaFile) {
            Write-Host "Importing initial database schema..."
            Get-Content $schemaFile | & $MysqlClient -uroot $DbName
        }
    }
}

if (-not (Test-TcpPort -HostName '127.0.0.1' -Port $AppPort)) {
    Write-Host "Starting PHP server..."
    $serverArgs = "-S 127.0.0.1:$AppPort -t `"$DocumentRoot`""
    $phpProcess = Start-Process -FilePath $PhpExe -ArgumentList $serverArgs -WorkingDirectory $ProjectRoot -WindowStyle Hidden -PassThru
    Set-Content -Path $PhpPidFile -Value $phpProcess.Id

    if (-not (Wait-ForPort -HostName '127.0.0.1' -Port $AppPort -Seconds 10)) {
        throw "PHP server did not start on port $AppPort."
    }
} else {
    Write-Host "PHP server is already running on port $AppPort."
    $existingServer = Get-ProjectPhpServer
    if ($existingServer) {
        Set-Content -Path $PhpPidFile -Value $existingServer.ProcessId
    }
}

Write-Host ""
Write-Host "Your app is live locally:"
Write-Host $Url
Write-Host ""
Write-Host "To stop it later, run: .\stop-live.ps1"
