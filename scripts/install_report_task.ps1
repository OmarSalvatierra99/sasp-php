param(
    [string]$TaskName = "SASP-Report-Incompatibilidades",
    [string]$Schedule = "Hourly",
    [string]$At = "08:00"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$runner = Join-Path $projectRoot "scripts\run_report.ps1"
$envExample = Join-Path $projectRoot "deploy\sasp-report.windows.env.example"
$envFile = Join-Path $projectRoot "deploy\sasp-report.windows.env"

if (-not (Test-Path -LiteralPath $envFile)) {
    Copy-Item -LiteralPath $envExample -Destination $envFile -Force
    Write-Host "Se creó $envFile. Edita EMAIL_PASS antes de activar en producción."
}

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$runner`""

switch ($Schedule.ToLowerInvariant()) {
    "hourly" { $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(1) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration ([TimeSpan]::MaxValue) }
    "daily"  { $trigger = New-ScheduledTaskTrigger -Daily -At $At }
    default   { throw "Schedule no soportado. Usa Hourly o Daily." }
}

$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Write-Host "Tarea instalada: $TaskName"
Write-Host "Para ejecutar manualmente: schtasks /Run /TN \"$TaskName\""
