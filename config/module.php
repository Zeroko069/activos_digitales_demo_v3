<?php
declare(strict_types=1);

return [
    /*
     * Configuración general de la aplicación
     */
    'app_name' => getenv('APP_NAME')
        ?: 'Activos Digitales',

    'environment' => getenv('APP_ENV')
        ?: 'testing',

    'base_url' => rtrim(
        getenv('APP_BASE_URL') ?: '',
        '/'
    ),

    'storage_path' => getenv('APP_STORAGE_PATH')
        ?: dirname(__DIR__) . '/storage',

    'max_pdf_bytes' => (int)(
        getenv('MAX_PDF_BYTES')
        ?: 10 * 1024 * 1024
    ),

    'timezone' => getenv('APP_TIMEZONE')
        ?: 'America/Bogota',

    /*
     * Conexión con MariaDB
     */
    'database' => [
        'host' => getenv('DB_HOST')
            ?: 'database',

        'port' => (int)(
            getenv('DB_PORT')
            ?: 3306
        ),

        'name' => getenv('DB_DATABASE')
            ?: 'activos_digitales_test',

        'username' => getenv('DB_USERNAME')
            ?: 'activos_test',

        'password' => getenv('DB_PASSWORD')
            ?: 'ActivosTest2026!',

        'charset' => 'utf8mb4',
    ],

    /*
     * Permisos por rol
     */
    'roles' => [
        'ADMIN' => [
            'dashboard.ver',

            'cuentas.ver',
            'cuentas.editar',

            'licencias.ver',
            'licencias.editar',

            'novedades.ver',
            'novedades.crear',
            'novedades.editar',

            'respaldos.ver',
            'respaldos.crear',
            'respaldos.editar',

            'facturas.ver',
            'facturas.crear',
            'facturas.editar',
            'facturas.conciliar',
            'facturas.aprobar',
        ],

        'TIC' => [
            'dashboard.ver',

            'cuentas.ver',
            'cuentas.editar',

            'licencias.ver',
            'licencias.editar',

            'novedades.ver',
            'novedades.crear',
            'novedades.editar',

            'respaldos.ver',
            'respaldos.crear',
            'respaldos.editar',

            'facturas.ver',
            'facturas.conciliar',
        ],

        'FINANCIERO' => [
            'dashboard.ver',

            'cuentas.ver',

            'licencias.ver',

            'novedades.ver',

            'respaldos.ver',

            'facturas.ver',
            'facturas.crear',
            'facturas.editar',
            'facturas.conciliar',
            'facturas.aprobar',
        ],

        'AUDITOR' => [
            'dashboard.ver',

            'cuentas.ver',

            'licencias.ver',

            'novedades.ver',

            'respaldos.ver',

            'facturas.ver',
        ],
    ],
];