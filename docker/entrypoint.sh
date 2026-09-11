#!/bin/sh
set -u

cd /var/www/html

mkdir -p \
    /var/www/html/storage \
    /var/www/html/storage/logs \
    /var/www/html/storage/facturas

chmod -R 777 /var/www/html/storage 2>/dev/null || true

LOG_FILE="/var/www/html/storage/logs/inicio.log"

echo "==============================================" > "$LOG_FILE"
echo "INICIO DE ACTIVOS DIGITALES" >> "$LOG_FILE"
date >> "$LOG_FILE"
echo "==============================================" >> "$LOG_FILE"

DB_HOST="${DB_HOST:-database}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-activos_digitales_test}"
DB_USERNAME="${DB_USERNAME:-activos_test}"
DB_PASSWORD="${DB_PASSWORD:-ActivosTest2026!}"

echo "Esperando MariaDB..."
echo "Esperando MariaDB..." >> "$LOG_FILE"

INTENTO=0

while true; do
    if php -r '
        $host = getenv("DB_HOST") ?: "database";
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE") ?: "activos_digitales_test";
        $username = getenv("DB_USERNAME") ?: "activos_test";
        $password = getenv("DB_PASSWORD") ?: "ActivosTest2026!";

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]
            );

            $pdo->query("SELECT 1");
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    '; then
        echo "MariaDB disponible."
        echo "MariaDB disponible." >> "$LOG_FILE"
        break
    fi

    INTENTO=$((INTENTO + 1))

    if [ "$INTENTO" -ge 60 ]; then
        echo "ERROR: MariaDB no respondió después de 120 segundos."
        echo "ERROR: MariaDB no respondió." >> "$LOG_FILE"
        cat "$LOG_FILE"
        exit 1
    fi

    echo "Esperando la base de datos... intento $INTENTO"
    sleep 2
done

echo "Verificando si el esquema ya existe..."

SCHEMA_READY="$(
    php -r '
        $host = getenv("DB_HOST") ?: "database";
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE") ?: "activos_digitales_test";
        $username = getenv("DB_USERNAME") ?: "activos_test";
        $password = getenv("DB_PASSWORD") ?: "ActivosTest2026!";

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = :database
                   AND table_name = :table"
            );

            $stmt->execute([
                ":database" => $database,
                ":table" => "ad_cuentas"
            ]);

            echo (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            echo "0";
        }
    '
)"

if [ "$SCHEMA_READY" = "1" ]; then
    echo "La base ya está instalada. Se omiten migraciones."
    echo "La base ya está instalada. Se omiten migraciones." >> "$LOG_FILE"
else
    echo "La base no está instalada. Ejecutando instalación..."
    echo "Ejecutando instalación..." >> "$LOG_FILE"

    if ! php \
        -d display_errors=1 \
        -d error_reporting=E_ALL \
        /var/www/html/bin/install.php >> "$LOG_FILE" 2>&1
    then
        echo ""
        echo "ERROR DURANTE LA INSTALACIÓN"
        echo "=============================================="
        cat "$LOG_FILE"
        echo "=============================================="
        exit 1
    fi

    echo "Instalación completada."
    echo "Instalación completada." >> "$LOG_FILE"
fi

echo "Validando configuración de Apache..."

if ! apache2ctl -t >> "$LOG_FILE" 2>&1; then
    echo ""
    echo "ERROR EN LA CONFIGURACIÓN DE APACHE"
    echo "=============================================="
    cat "$LOG_FILE"
    echo "=============================================="
    exit 1
fi

chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true

echo "Apache validado correctamente."
echo "Iniciando Apache..."
echo "Iniciando Apache..." >> "$LOG_FILE"

exec apache2-foreground