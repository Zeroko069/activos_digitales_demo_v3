@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set ARCHIVO=diagnostico-activos-digitales.txt
(
  echo FECHA: %date% %time%
  echo.
  echo ===== DOCKER VERSION =====
  docker version
  echo.
  echo ===== CONTENEDORES =====
  docker compose ps -a
  echo.
  echo ===== LOG APP =====
  docker compose logs --tail=400 app
  echo.
  echo ===== LOG DATABASE =====
  docker compose logs --tail=250 database
  echo.
  echo ===== LOG PHPMYADMIN =====
  docker compose logs --tail=150 phpmyadmin
  echo.
  echo ===== PRUEBA HTTP =====
  powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -UseBasicParsing -Uri 'http://localhost:8085/health.php' -TimeoutSec 10 | Format-List StatusCode,Content } catch { $_ | Format-List * -Force }"
) > "%ARCHIVO%" 2>&1

echo Diagnostico guardado en:
echo %CD%\%ARCHIVO%
notepad "%ARCHIVO%"
pause
