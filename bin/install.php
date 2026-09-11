<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/bootstrap.php';

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($quote === null && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") $i++;
            $buffer .= "\n";
            continue;
        }
        if ($quote === null && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") $i++;
            $buffer .= "\n";
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
            $i++;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') $statements[] = $statement;
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') $statements[] = trim($buffer);
    return $statements;
}

try {
    $files = array_merge(
        glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [],
        glob(dirname(__DIR__) . '/database/seeds/*.sql') ?: []
    );
    sort($files, SORT_NATURAL);

    $pdo = db();
    foreach ($files as $file) {
        fwrite(STDOUT, 'Ejecutando ' . basename($file) . "...\n");
        $sql = file_get_contents($file);
        if ($sql === false) throw new RuntimeException('No se pudo leer ' . $file);
        foreach (splitSqlStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
    fwrite(STDOUT, "Instalación completada.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
