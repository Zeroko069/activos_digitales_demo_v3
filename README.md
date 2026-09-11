# Activos Digitales – Base de Prueba v3

Aplicación independiente de NOVA para probar la administración de cuentas, licencias y facturas.

## Inicio recomendado

1. Abra Docker Desktop y espere a que termine de iniciar.
2. Ejecute `reparar-acceso.bat` la primera vez que use esta versión.
3. El archivo solo abrirá el navegador después de comprobar que la aplicación responde.
4. Ingrese en `http://localhost:8085/login.php`.

Credenciales de la aplicación:

- Usuario: `admin@prueba.local`
- Contraseña: `Prueba2026!`

phpMyAdmin:

- Dirección: `http://localhost:8086`
- Usuario automático: `activos_test`
- Contraseña configurada: `ActivosTest2026!`

## Cargar el Excel

La versión v3 no importa el Excel antes de iniciar Apache. Esto evita que el navegador muestre `ERR_EMPTY_RESPONSE` mientras se procesan miles de filas.

Cuando ya pueda abrir la aplicación, ejecute:

`cargar-datos-prueba.bat`

La carga se ejecuta con la aplicación disponible y puede tardar varios minutos.

## Diagnóstico

Ejecute `diagnostico.bat`. Se creará `diagnostico-activos-digitales.txt` con el estado y los logs de Docker, aplicación, MariaDB y phpMyAdmin.

## Reinicio

- `iniciar-prueba.bat`: inicia sin borrar datos.
- `reparar-acceso.bat`: elimina y reconstruye únicamente este ambiente local.
- `detener-prueba.bat`: detiene los servicios.
