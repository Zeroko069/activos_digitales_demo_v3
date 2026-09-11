<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$sendJson = static function (
    array $data,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
};

try {
    /*
     * No usamos ad_require_permission porque esa función
     * puede responder HTML. Un API siempre debe responder JSON.
     */
    if (!ad_is_authenticated()) {
        $sendJson([
            'ok' => false,
            'codigo' => 'SESION_VENCIDA',
            'message' => 'La sesión ha vencido. Ingrese nuevamente.',
        ], 401);
    }

    if (!ad_can('cuentas.editar')) {
        $sendJson([
            'ok' => false,
            'codigo' => 'SIN_PERMISO',
            'message' => 'No tiene permiso para consultar funcionarios.',
        ], 403);
    }

    $pais = strtoupper(
        trim((string)($_GET['pais'] ?? 'CO'))
    );

    $documento = trim(
        (string)($_GET['documento'] ?? '')
    );

    /*
     * Elimina puntos, espacios, guiones, comas
     * y cualquier otro carácter no numérico.
     */
    $documento = preg_replace(
        '/\D+/',
        '',
        $documento
    ) ?? '';

    if (!in_array($pais, ['CO', 'PE'], true)) {
        $sendJson([
            'ok' => false,
            'codigo' => 'PAIS_INVALIDO',
            'message' => 'El país seleccionado no es válido.',
        ], 422);
    }

    if ($documento === '') {
        $sendJson([
            'ok' => false,
            'codigo' => 'DOCUMENTO_VACIO',
            'message' => 'Debe ingresar el número de documento.',
        ], 422);
    }

    /*
     * No se exige que la fuente coincida exactamente.
     * Esto evita que un registro existente deje de encontrarse
     * porque fue actualizado por otra hoja del Excel.
     *
     * El campo fuente se utiliza solo para priorizar:
     * Colombia: MatrizHDUniclass.
     * Perú: PLANTA PERU.
     */
    $fuentePrioritaria = $pais === 'CO'
        ? 'MatrizHDUniclass'
        : 'PLANTA PERU';

    $sql = <<<'SQL'
        SELECT
            p.id,
            p.pais,
            p.numero_documento,
            p.nombre_completo,
            p.cargo,
            p.ciudad,
            p.proceso,
            p.cliente,
            p.proyecto,
            p.razon_social_id,
            p.fuente,
            rs.nombre AS razon_social
        FROM ad_personas p
        LEFT JOIN ad_razones_sociales rs
            ON rs.id = p.razon_social_id
        WHERE p.pais = :pais
          AND REGEXP_REPLACE(
                COALESCE(p.numero_documento, ''),
                '[^0-9]',
                ''
              ) = :documento
        ORDER BY
            CASE
                WHEN p.fuente = :fuente_prioritaria THEN 0
                ELSE 1
            END,
            p.id DESC
        LIMIT 1
    SQL;

    $stmt = db()->prepare($sql);

    $stmt->execute([
        ':pais' => $pais,
        ':documento' => $documento,
        ':fuente_prioritaria' => $fuentePrioritaria,
    ]);

    $persona = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$persona) {
        $sendJson([
            'ok' => false,
            'codigo' => 'USUARIO_NO_ENCONTRADO',
            'message' => 'Usuario no encontrado',
        ], 404);
    }

    $nombre = trim(
        (string)($persona['nombre_completo'] ?? '')
    );

    if ($nombre === '') {
        $sendJson([
            'ok' => false,
            'codigo' => 'NOMBRE_NO_DISPONIBLE',
            'message' => 'El funcionario existe, pero no tiene nombre registrado.',
        ], 422);
    }

    $sendJson([
        'ok' => true,
        'codigo' => 'FUNCIONARIO_ENCONTRADO',
        'message' => 'Funcionario encontrado',

        'persona' => [
            'id' => (int)$persona['id'],

            'pais' => (string)$persona['pais'],

            'numero_documento' =>
                (string)$persona['numero_documento'],

            'nombre_completo' => $nombre,

            'cargo' =>
                trim((string)($persona['cargo'] ?? '')),

            'ciudad' =>
                trim((string)($persona['ciudad'] ?? '')),

            'proceso' =>
                trim((string)($persona['proceso'] ?? '')),

            'cliente' =>
                trim((string)($persona['cliente'] ?? '')),

            'proyecto' =>
                trim((string)($persona['proyecto'] ?? '')),

            'razon_social_id' =>
                $persona['razon_social_id'] !== null
                    ? (int)$persona['razon_social_id']
                    : null,

            'razon_social' =>
                trim((string)($persona['razon_social'] ?? '')),

            'fuente' =>
                trim((string)($persona['fuente'] ?? '')),
        ],
    ]);
} catch (Throwable $e) {
    error_log(
        'persona_por_documento.php: '
        . $e->getMessage()
    );

    $respuesta = [
        'ok' => false,
        'codigo' => 'ERROR_INTERNO',
        'message' => 'No fue posible realizar la consulta',
    ];

    /*
     * Como es una base de prueba, devolvemos temporalmente
     * el error técnico para poder identificarlo en pantalla.
     */
    if (ad_config('environment') === 'testing') {
        $respuesta['detalle'] = $e->getMessage();
    }

    $sendJson($respuesta, 500);
}