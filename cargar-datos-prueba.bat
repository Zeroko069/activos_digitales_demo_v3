@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo ============================================================
echo CARGA DEL EXCEL DE PRUEBA
echo ============================================================
echo La aplicacion seguira disponible mientras se procesa el archivo.
echo Este proceso puede tardar varios minutos.
echo.

set "ARCHIVO=%~1"
if "%ARCHIVO%"=="" set "ARCHIVO=Control_Usuarios.xlsx"

if not exist "sample_data\%ARCHIVO%" (
  echo ERROR: No se encontro el archivo "sample_data\%ARCHIVO%".
  echo.
  echo Uso: cargar-datos-prueba.bat [nombre-del-archivo.xlsx]
  pause
  exit /b 1
)

echo Archivo a importar: sample_data\%ARCHIVO%
echo.

docker compose ps --status running app | findstr /I "app" >nul
if errorlevel 1 (
  echo ERROR: La aplicacion no esta iniciada. Ejecute primero iniciar-prueba.bat.
  pause
  exit /b 1
)

docker compose exec -T app php bin/import_control_usuarios.php "sample_data/%ARCHIVO%"
if errorlevel 1 (
  echo.
  echo La carga no se completo. Puede que el archivo ya hubiera sido importado.
  echo Para revisar el detalle ejecute diagnostico.bat.
  pause
  exit /b 1
)

docker compose exec -T app php bin/attach_invoice_pdf.php sample_data/FE10626.pdf FE10626

echo.
echo Carga terminada. Actualice la aplicacion en el navegador.
start "" http://localhost:8085/
pause
