@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo ============================================================
echo REPARACION COMPLETA - ACTIVOS DIGITALES V3
echo ============================================================
echo Este proceso elimina SOLO los contenedores y volumenes locales
echo de esta prueba. No modifica NOVA ni otras bases.
echo.
choice /M "Desea continuar"
if errorlevel 2 exit /b 0

where docker >nul 2>&1
if errorlevel 1 (
  echo ERROR: Docker Desktop no esta instalado.
  pause
  exit /b 1
)
docker info >nul 2>&1
if errorlevel 1 (
  echo ERROR: Docker Desktop no esta iniciado.
  pause
  exit /b 1
)

echo [1/4] Eliminando ambiente anterior...
docker compose down -v --remove-orphans
if errorlevel 1 goto error

echo [2/4] Reconstruyendo sin cache...
docker compose build --no-cache app
if errorlevel 1 goto error

echo [3/4] Iniciando servicios...
docker compose up -d database app phpmyadmin
if errorlevel 1 goto error

echo [4/4] Esperando que la aplicacion responda...
call :esperar_app
if errorlevel 1 goto error

echo.
echo ============================================================
echo REPARACION TERMINADA Y APLICACION VERIFICADA
echo ============================================================
docker compose ps
echo.
echo Aplicacion: http://localhost:8085/login.php
echo Usuario: admin@prueba.local
echo Clave: Prueba2026!
echo phpMyAdmin: http://localhost:8086
start "" http://localhost:8085/login.php
pause
exit /b 0

:esperar_app
set /a INTENTOS=0
:loop_app
set /a INTENTOS+=1
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $r=Invoke-WebRequest -UseBasicParsing -Uri 'http://localhost:8085/health.php' -TimeoutSec 4; if($r.StatusCode -eq 200){exit 0}else{exit 1} } catch { exit 1 }" >nul 2>&1
if not errorlevel 1 exit /b 0
if %INTENTOS% GEQ 60 exit /b 1
timeout /t 5 /nobreak >nul
goto loop_app

:error
echo.
echo ERROR: La reparacion no termino correctamente.
docker compose ps
echo.
echo LOG DE LA APLICACION:
docker compose logs --tail=250 app
echo.
echo LOG DE LA BASE:
docker compose logs --tail=120 database
pause
exit /b 1
