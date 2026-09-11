<?php
declare(strict_types=1);

/**
 * Lector XLSX mínimo sin dependencias externas.
 * Usa ZipArchive cuando está disponible y PharData como respaldo.
 * Está orientado a archivos tabulares como Control_Usuarios.xlsx.
 */
final class SimpleXlsxReader
{
    private ZipArchive|PharData $archive;
    private bool $usesZipArchive = false;
    private array $sharedStrings = [];
    private array $sheetPaths = [];

    public function __construct(private readonly string $filePath)
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('No existe el archivo XLSX: ' . $filePath);
        }

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new RuntimeException('No fue posible abrir el archivo XLSX.');
            }
            $this->archive = $zip;
            $this->usesZipArchive = true;
        } else {
            try {
                $this->archive = new PharData($filePath);
            } catch (Throwable $e) {
                throw new RuntimeException('No fue posible abrir el XLSX. Habilite ext-zip o Phar.', 0, $e);
            }
        }

        $this->loadSharedStrings();
        $this->loadSheetPaths();
    }

    public function __destruct()
    {
        if ($this->usesZipArchive) {
            $this->archive->close();
        }
    }

    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /**
     * Devuelve filas asociativas usando la primera fila como encabezado.
     * El campo interno `_row` conserva el número de fila de Excel.
     */
    public function rows(string $sheetName): Generator
    {
        $path = $this->sheetPaths[$sheetName] ?? null;
        if ($path === null) {
            throw new InvalidArgumentException('No existe la hoja: ' . $sheetName);
        }

        $xml = $this->getEntry($path);
        if ($xml === null) {
            throw new RuntimeException('No fue posible leer la hoja: ' . $sheetName);
        }

        if (!preg_match_all('/<row\b([^>]*)>(.*?)<\/row>/si', $xml, $rowMatches, PREG_SET_ORDER)) {
            return;
        }

        $headers = [];
        foreach ($rowMatches as $rowMatch) {
            $attrs = $this->parseAttributes($rowMatch[1]);
            $rowNumber = isset($attrs['r']) ? (int)$attrs['r'] : 0;
            $cells = $this->parseRow($rowMatch[2]);

            if ($headers === []) {
                foreach ($cells as $column => $value) {
                    $headers[$column] = trim((string)$value);
                }
                continue;
            }

            $record = [];
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }
                $record[$header] = $cells[$column] ?? null;
            }
            $record['_row'] = $rowNumber;
            yield $record;
        }
    }

    private function parseRow(string $rowXml): array
    {
        $result = [];
        if (!preg_match_all('/<c\b([^>]*)>(.*?)<\/c>|<c\b([^>]*)\/>/si', $rowXml, $matches, PREG_SET_ORDER)) {
            return $result;
        }

        foreach ($matches as $match) {
            $attributeText = $match[1] !== '' ? $match[1] : ($match[3] ?? '');
            $body = $match[2] ?? '';
            $attrs = $this->parseAttributes($attributeText);
            $reference = $attrs['r'] ?? '';
            $column = preg_replace('/\d+/', '', $reference) ?: '';
            if ($column === '') {
                continue;
            }

            $type = $attrs['t'] ?? '';
            $value = null;
            if ($type === 'inlineStr') {
                $value = $this->extractTextNodes($body);
            } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $valueMatch)) {
                $raw = $this->decodeXmlText($valueMatch[1]);
                if ($type === 's') {
                    $value = $this->sharedStrings[(int)$raw] ?? $raw;
                } elseif ($type === 'b') {
                    $value = $raw === '1';
                } else {
                    $value = $raw;
                }
            }
            $result[$column] = $value;
        }
        return $result;
    }

    private function getEntry(string $path): ?string
    {
        if ($this->usesZipArchive) {
            $content = $this->archive->getFromName($path);
            return $content === false ? null : $content;
        }
        try {
            if (!isset($this->archive[$path])) {
                return null;
            }
            return $this->archive[$path]->getContent();
        } catch (Throwable) {
            return null;
        }
    }

    private function loadSharedStrings(): void
    {
        $xml = $this->getEntry('xl/sharedStrings.xml');
        if ($xml === null) {
            return;
        }
        if (!preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $xml, $matches)) {
            return;
        }
        foreach ($matches[1] as $siBody) {
            $this->sharedStrings[] = $this->extractTextNodes($siBody);
        }
    }

    private function loadSheetPaths(): void
    {
        $workbookXml = $this->getEntry('xl/workbook.xml');
        $relsXml = $this->getEntry('xl/_rels/workbook.xml.rels');
        if ($workbookXml === null || $relsXml === null) {
            throw new RuntimeException('El XLSX no contiene la estructura esperada.');
        }

        $relMap = [];
        if (preg_match_all('/<Relationship\b([^>]*)\/?\s*>/si', $relsXml, $relMatches)) {
            foreach ($relMatches[1] as $attributeText) {
                $attrs = $this->parseAttributes($attributeText);
                if (isset($attrs['Id'], $attrs['Target'])) {
                    $relMap[$attrs['Id']] = $attrs['Target'];
                }
            }
        }

        if (preg_match_all('/<sheet\b([^>]*)\/?\s*>/si', $workbookXml, $sheetMatches)) {
            foreach ($sheetMatches[1] as $attributeText) {
                $attrs = $this->parseAttributes($attributeText);
                $name = $attrs['name'] ?? null;
                $rid = $attrs['r:id'] ?? null;
                if ($name === null || $rid === null || !isset($relMap[$rid])) {
                    continue;
                }
                $target = ltrim(str_replace('\\', '/', $relMap[$rid]), '/');
                $path = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                $this->sheetPaths[$this->decodeXmlText($name)] = $path;
            }
        }
    }

    private function parseAttributes(string $attributeText): array
    {
        $attrs = [];
        if (preg_match_all('/([A-Za-z_][A-Za-z0-9_:\-\.]*)\s*=\s*("([^"]*)"|\'([^\']*)\')/s', $attributeText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = $match[3] !== '' ? $match[3] : ($match[4] ?? '');
                $attrs[$match[1]] = $this->decodeXmlText($value);
            }
        }
        return $attrs;
    }

    private function extractTextNodes(string $xml): string
    {
        $parts = [];
        if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $xml, $matches)) {
            foreach ($matches[1] as $part) {
                $parts[] = $this->decodeXmlText($part);
            }
        }
        return implode('', $parts);
    }

    private function decodeXmlText(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
