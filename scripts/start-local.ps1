. "$PSScriptRoot/common.ps1"
$projectRoot = Split-Path $PSScriptRoot
$existing = Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue
if ($existing) {
    $listener = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $existing[0].OwningProcess)
    if ($listener.CommandLine -notlike ('*' + $projectRoot + '*')) {
        throw 'Port 8000 is owned by another process; choose a different port with php artisan serve.'
    }
} else {
    Start-Process -FilePath $CenaRuntime.php -ArgumentList ('"' + (Join-Path $projectRoot 'artisan') + '"'), 'serve', '--host=127.0.0.1', '--port=8000', '--no-reload' -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'storage/logs/server.out.log') -RedirectStandardError (Join-Path $projectRoot 'storage/logs/server.error.log') | Out-Null
}
Write-Output 'Cena Store: http://127.0.0.1:8000 (development server only).'
