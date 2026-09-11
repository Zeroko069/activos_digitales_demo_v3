@echo off
title Activos Digitales - Inicio
cd /d "%~dp0"

echo ============================================
echo INICIANDO ACTIVOS DIGITALES
echo ============================================
echo.

docker compose up -d

if errorlevel 1 (
    echo.
    echo ERROR: No fue posible iniciar los servicios.
    echo Verifique que Docker Desktop este abierto.
    pause
    exit /b 1
)

echo.
echo Esperando que la aplicacion inicie...
timeout /t 10 /nobreak >nul

echo.
docker compose ps

echo.
echo Verificando aplicacion...

curl.exe --connect-timeout 5 --max-time 10 ^
http://localhost:8085/health.php

if errorlevel 1 (
    echo.
    echo La aplicacion aun no responde.
    echo Mostrando los ultimos registros:
    echo.
    docker compose logs --no-color --tail=100 app
    pause
    exit /b 1
)

echo.
echo ============================================
echo APLICACION INICIADA CORRECTAMENTE
echo ============================================
echo.
echo Aplicacion:
echo http://localhost:8085/login.php
echo.
echo phpMyAdmin:
echo http://localhost:8086
echo.

start "" http://localhost:8085/login.php

pause