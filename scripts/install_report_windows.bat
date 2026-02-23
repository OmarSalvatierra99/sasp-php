@echo off
setlocal ENABLEDELAYEDEXPANSION

set "SCRIPT_DIR=%~dp0"
for %%I in ("%SCRIPT_DIR%..") do set "PROJECT_ROOT=%%~fI"
set "ENV_EXAMPLE=%PROJECT_ROOT%\deploy\sasp-report.windows.env.example"
set "ENV_FILE=%PROJECT_ROOT%\deploy\sasp-report.windows.env"
set "INSTALL_PS1=%PROJECT_ROOT%\scripts\install_report_task.ps1"
set "RUN_PS1=%PROJECT_ROOT%\scripts\run_report.ps1"

if not exist "%ENV_FILE%" (
  if not exist "%ENV_EXAMPLE%" (
    echo No existe archivo de ejemplo: "%ENV_EXAMPLE%"
    exit /b 1
  )
  copy /Y "%ENV_EXAMPLE%" "%ENV_FILE%" >nul
)

echo.
echo Configurando EMAIL_PASS en: "%ENV_FILE%"
set /p EMAIL_PASS=Ingresa APP PASSWORD de Gmail: 
if "%EMAIL_PASS%"=="" (
  echo EMAIL_PASS vacio. Cancelado.
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "
$envFile = '%ENV_FILE%';
$pass = '%EMAIL_PASS%';
$content = Get-Content -LiteralPath $envFile;
$found = $false;
for ($i = 0; $i -lt $content.Count; $i++) {
  if ($content[$i] -match '^EMAIL_PASS=') {
    $content[$i] = 'EMAIL_PASS=' + $pass;
    $found = $true;
  }
}
if (-not $found) { $content += 'EMAIL_PASS=' + $pass }
Set-Content -LiteralPath $envFile -Value $content -Encoding UTF8;
"
if errorlevel 1 (
  echo Error actualizando EMAIL_PASS.
  exit /b 1
)

echo.
echo Instalando tarea programada...
powershell -NoProfile -ExecutionPolicy Bypass -File "%INSTALL_PS1%" -Schedule Hourly
if errorlevel 1 (
  echo Error instalando tarea programada.
  exit /b 1
)

echo.
echo Ejecutando prueba de envio...
powershell -NoProfile -ExecutionPolicy Bypass -File "%RUN_PS1%"
if errorlevel 1 (
  echo La prueba fallo. Revisa conectividad SMTP y credenciales.
  exit /b 1
)

echo.
echo Instalacion completada correctamente.
echo Tarea: SASP-Report-Incompatibilidades
exit /b 0
