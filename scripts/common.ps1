$ErrorActionPreference = 'Stop'
$metadata = Join-Path $env:LOCALAPPDATA 'CenaStore\runtimes\p00-runtime.json'
if (-not (Test-Path -LiteralPath $metadata)) {
    throw 'Run scripts/setup-local-runtime.ps1 first, or use system PHP 8.4 / Composer / Node 22 directly as documented.'
}
$CenaRuntime = Get-Content -LiteralPath $metadata -Raw | ConvertFrom-Json
$env:Path = (Split-Path $CenaRuntime.php) + ';' + (Split-Path $CenaRuntime.node) + ';' + $env:Path
