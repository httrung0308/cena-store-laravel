


#Requires -Version 7.0
param(
    [string]$MySqlBin = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin',
    [string]$MailpitBinary = 'C:\laragon\bin\mailpit\1.22.3\mailpit.exe'
)
$ErrorActionPreference = 'Stop'
$runtimeRoot = Join-Path $env:LOCALAPPDATA 'CenaStore\runtimes'
New-Item -ItemType Directory -Path $runtimeRoot -Force | Out-Null

function Get-VerifiedFile([string[]]$Urls, [string]$Destination, [string]$Sha256) {
    if (-not (Test-Path -LiteralPath $Destination)) {
        $downloaded = $false
        foreach ($url in $Urls) {
            try {
                Invoke-WebRequest -Uri $url -OutFile $Destination
                $downloaded = $true
                break
            } catch {
                if ($url -eq $Urls[-1]) { throw }
            }
        }
        if (-not $downloaded) { throw 'Download failed.' }
    }
    if ((Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash.ToLowerInvariant() -ne $Sha256) {
        throw ('Checksum mismatch: ' + $Destination)
    }
}

$phpArchive = 'php-8.4.27-1-nts-Win32-vs17-x64.zip'
$phpHash = 'dbae154a0a89791f36c3d48e68bae797878333e7d6cb5721e74617de59b63ab7'
$phpZip = Join-Path $runtimeRoot $phpArchive
Get-VerifiedFile @(('https://windows.php.net/downloads/releases/' + $phpArchive), ('https://windows.php.net/downloads/releases/archives/' + $phpArchive)) $phpZip $phpHash
$phpDirectory = Join-Path $runtimeRoot 'php-8.4.27-1'
if (-not (Test-Path -LiteralPath (Join-Path $phpDirectory 'php.exe'))) {
    Expand-Archive -LiteralPath $phpZip -DestinationPath $phpDirectory
}
$nodeHash = 'cbc74b5e0982c9dbb41525fd8fb5dcd32fbee237863f7829072f4639a1ce5f20'
$nodeZip = Join-Path $runtimeRoot 'node-v22.23.4-win-x64.zip'
Get-VerifiedFile @('https://nodejs.org/dist/v22.23.4/node-v22.23.4-win-x64.zip') $nodeZip $nodeHash
$nodeDirectory = Join-Path $runtimeRoot 'node-v22.23.4-win-x64'
if (-not (Test-Path -LiteralPath (Join-Path $nodeDirectory 'node.exe'))) {
    Expand-Archive -LiteralPath $nodeZip -DestinationPath $runtimeRoot
}
$composerPath = Join-Path $runtimeRoot 'composer-2.9.4.phar'
Get-VerifiedFile @('https://getcomposer.org/download/2.9.4/composer.phar') $composerPath 'd3eb5c4cb2e708267dac5f9a76d2a57b07836c7ea68783a1a02bd6d94753ea80'
$caPath = Join-Path $runtimeRoot 'cacert.pem'
if (-not (Test-Path -LiteralPath $caPath)) { Invoke-WebRequest 'https://curl.se/ca/cacert.pem' -OutFile $caPath }
$ini = @('date.timezone=UTC', 'memory_limit=512M', 'display_errors=On', 'error_reporting=E_ALL', ('extension_dir="' + $phpDirectory.Replace('\', '/') + '/ext"'), ('curl.cainfo="' + $caPath.Replace('\', '/') + '"'), ('openssl.cafile="' + $caPath.Replace('\', '/') + '"'))
foreach ($extension in @('curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'zip', 'exif')) { $ini += 'extension=' + $extension }
Set-Content -LiteralPath (Join-Path $phpDirectory 'php.ini') -Value $ini -Encoding utf8NoBOM
$mysql = Join-Path $MySqlBin 'mysql.exe'
$mysqld = Join-Path $MySqlBin 'mysqld.exe'
foreach ($binary in @($mysql, $mysqld, $MailpitBinary)) {
    if (-not (Test-Path -LiteralPath $binary)) { throw ('Supply existing MySQL 8.4 / Mailpit binaries via parameters: ' + $binary) }
}
$info = [ordered]@{ php = (Join-Path $phpDirectory 'php.exe'); phpArchiveSha256 = $phpHash; composer = $composerPath; node = (Join-Path $nodeDirectory 'node.exe'); nodeArchiveSha256 = $nodeHash; npm = (Join-Path $nodeDirectory 'npm.cmd'); mysql = $mysql; mysqld = $mysqld; mailpit = $MailpitBinary }
$info | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimeRoot 'p00-runtime.json') -Encoding utf8NoBOM
& $info.php --version
if ($LASTEXITCODE -ne 0) { throw 'PHP failed; install the Microsoft VC++ 2015-2022 x64 runtime if missing.' }
& $info.node --version
if ($LASTEXITCODE -ne 0) { throw 'Node failed.' }
Write-Output 'Portable runtimes ready. System PATH and Laragon data have not been changed.'
