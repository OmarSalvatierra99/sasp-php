param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path,
    [string]$EnvFile = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($EnvFile)) {
    $EnvFile = Join-Path $ProjectRoot "deploy\sasp-report.windows.env"
}

if (-not (Test-Path -LiteralPath $EnvFile)) {
    throw "No existe archivo de entorno: $EnvFile"
}

Get-Content -LiteralPath $EnvFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    $parts = $line.Split("=", 2)
    if ($parts.Count -ne 2) { return }
    $name = $parts[0].Trim()
    $value = $parts[1].Trim()
    if ($name -ne "") {
        [System.Environment]::SetEnvironmentVariable($name, $value, "Process")
    }
}

$pythonCmd = Get-Command python -ErrorAction SilentlyContinue
if (-not $pythonCmd) {
    $pythonCmd = Get-Command py -ErrorAction SilentlyContinue
}
if (-not $pythonCmd) {
    throw "No se encontró Python (python/py) en PATH"
}

$scriptPath = Join-Path $ProjectRoot "scripts\send_server_report.py"
if (-not (Test-Path -LiteralPath $scriptPath)) {
    throw "No se encontró script: $scriptPath"
}

Push-Location $ProjectRoot
try {
    if ($pythonCmd.Name -ieq "py") {
        & py $scriptPath
    } else {
        & python $scriptPath
    }
    if ($LASTEXITCODE -ne 0) {
        throw "La ejecución de reporte devolvió código $LASTEXITCODE"
    }
} finally {
    Pop-Location
}
