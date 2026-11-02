#Requires -Version 7.0
param(
    [string]$MySqlBin = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin',
    [string]$MailpitBinary = 'C:\laragon\bin\mailpit\1.22.3\mailpit.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot

function Invoke-ProjectStep {
    param([string]$Label, [string]$ScriptName, [string[]]$ScriptArguments = @())

    Write-Host "`n$Label"
    & (Join-Path $PSHOME 'pwsh.exe') -NoProfile -File (Join-Path $PSScriptRoot $ScriptName) @ScriptArguments
    if ($LASTEXITCODE -ne 0) {
        throw "Buoc '$Label' that bai. Sua loi phia tren roi chay lai setup-project.ps1."
    }
}

foreach ($binary in @((Join-Path $MySqlBin 'mysql.exe'), (Join-Path $MySqlBin 'mysqld.exe'), $MailpitBinary)) {
    if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) {
        throw "Khong tim thay $binary. Xem README; truyen -MySqlBin va -MailpitBinary theo may cua ban."
    }
}

Push-Location $projectRoot
try {
    Invoke-ProjectStep '1/7 - Chuan bi PHP, Composer, Node' 'setup-local-runtime.ps1' @('-MySqlBin', $MySqlBin, '-MailpitBinary', $MailpitBinary)
    Invoke-ProjectStep '2/7 - Mo MySQL va Mailpit local' 'start-local-services.ps1'
    Invoke-ProjectStep '3/7 - Tao env neu chua co (giu nguyen env va APP_KEY da co)' 'php.ps1' @('scripts/configure-env.php', '--local')
    Invoke-ProjectStep '4/7 - Cai thu vien PHP theo lockfile' 'composer.ps1' @('install', '--no-interaction', '--prefer-dist')
    Invoke-ProjectStep '5/7 - Cai va build giao dien theo lockfile' 'npm.ps1' @('ci')
    Invoke-ProjectStep 'Build assets' 'npm.ps1' @('run', 'build')
    Invoke-ProjectStep '6/7 - Migrate va seed roles, tinh/phuong' 'php.ps1' @('artisan', 'migrate', '--no-interaction')
    Invoke-ProjectStep 'Seed roles' 'php.ps1' @('artisan', 'db:seed', '--class=CoreFoundationSeeder', '--no-interaction')
    Invoke-ProjectStep 'Seed tinh/phuong' 'php.ps1' @('artisan', 'db:seed', '--class=AdministrativeUnitSeeder', '--no-interaction')
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public/storage'))) {
        Invoke-ProjectStep 'Lien ket anh public' 'php.ps1' @('artisan', 'storage:link', '--no-interaction')
    }
    Invoke-ProjectStep '7/7 - Mo web' 'start-local.ps1'
    Write-Host "`nSan sang: http://127.0.0.1:8000 - xem README de tao tai khoan demo va gui PR."
} finally {
    Pop-Location
}
