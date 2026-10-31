$ErrorActionPreference='Stop'
$r=Get-Content -LiteralPath "$env:LOCALAPPDATA\CenaStore\runtimes\p00-runtime.json" -Raw | ConvertFrom-Json
$serviceRoot=Join-Path $env:LOCALAPPDATA 'CenaStore\services'
$mysqlRoot=Join-Path $serviceRoot 'mysql84'
$dataDirectory=Join-Path $mysqlRoot 'data'
$credentialsPath=Join-Path $serviceRoot 'local-credentials.json'
New-Item -ItemType Directory -Path $mysqlRoot -Force | Out-Null
if (-not (Test-Path -LiteralPath $credentialsPath)) {
    if (Test-Path -LiteralPath (Join-Path $dataDirectory 'mysql')) { throw 'Existing isolated MySQL data has no credentials metadata; refusing to reinitialize.' }
    $settings=[ordered]@{provisioned=$false;admin_password=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32));app_password=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32));test_password=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))}
    $settings | ConvertTo-Json | Set-Content -LiteralPath $credentialsPath -Encoding utf8NoBOM
    $acl=[Security.AccessControl.FileSecurity]::new()
    $acl.SetAccessRuleProtection($true,$false)
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.WindowsIdentity]::GetCurrent().Name,'FullControl','Allow'))
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new('SYSTEM','FullControl','Allow'))
    Set-Acl -LiteralPath $credentialsPath -AclObject $acl
}
$mysqlBase=Split-Path (Split-Path $r.mysqld)
$iniPath=Join-Path $mysqlRoot 'my.ini'
$ini=@('[mysqld]',('basedir="'+$mysqlBase.Replace('\','/')+'"'),('datadir="'+$dataDirectory.Replace('\','/')+'"'),'port=3307','bind-address=127.0.0.1','mysqlx=OFF',('log-error="'+(Join-Path $mysqlRoot 'mysql-error.log').Replace('\','/')+'"'),('pid-file="'+(Join-Path $mysqlRoot 'mysql.pid').Replace('\','/')+'"'),'character-set-server=utf8mb4','collation-server=utf8mb4_unicode_ci','default-time-zone=+00:00')
Set-Content -LiteralPath $iniPath -Value $ini -Encoding ascii
if (-not (Test-Path -LiteralPath (Join-Path $dataDirectory 'mysql'))) {
    & $r.mysqld "--defaults-file=$iniPath" --initialize-insecure
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed; inspect isolated mysql-error.log.' }
}
$existing=Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue
if ($existing) {
    $owner = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $existing[0].OwningProcess)
    if ($owner.ExecutablePath -ne $r.mysqld -or $owner.CommandLine -notlike ('*' + $iniPath + '*')) {
        throw 'Port 3307 belongs to a different server; refusing to provision it.'
    }
}
if (-not $existing) {
    Start-Process -FilePath $r.mysqld -ArgumentList ('--defaults-file="'+$iniPath+'"') -WorkingDirectory $mysqlRoot -WindowStyle Hidden | Out-Null
}
$ready=$false
for($attempt=0;$attempt -lt 40;$attempt++) {
    if(Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue){$ready=$true;break}
    Start-Sleep -Milliseconds 500
}
if(-not $ready){throw 'Isolated MySQL failed to bind port3307.'}
& $r.php (Join-Path $PSScriptRoot 'provision-local-database.php') $credentialsPath
if($LASTEXITCODE -ne 0){throw 'Database provisioning failed.'}
$smtpListener = Get-NetTCPConnection -State Listen -LocalPort 1025 -ErrorAction SilentlyContinue
if ($smtpListener) {
    $smtpOwner = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $smtpListener[0].OwningProcess)
    if ($smtpOwner.ExecutablePath -ne $r.mailpit -or $smtpOwner.CommandLine -notlike ('*' + $serviceRoot + '*')) {
        throw 'Port 1025 belongs to another SMTP process.'
    }
} else {
    if (Get-NetTCPConnection -State Listen -LocalPort 8025 -ErrorAction SilentlyContinue) {
        throw 'Port 8025 is already occupied.'
    }
    Start-Process -FilePath $r.mailpit -ArgumentList '--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--database',(Join-Path $serviceRoot 'mailpit.db') -WorkingDirectory $serviceRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $serviceRoot 'mailpit.out.log') -RedirectStandardError (Join-Path $serviceRoot 'mailpit.error.log') | Out-Null
}
Write-Output 'MySQL isolated on127.0.0.1:3307; Mailpit SMTP1025/UI8025. Other Laragon data untouched.'
