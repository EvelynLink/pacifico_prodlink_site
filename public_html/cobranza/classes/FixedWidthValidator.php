<?php
/**
 * FixedWidthValidator.php
 *
 * Clase reutilizable para validar archivos planos de ancho fijo (posicional).
 * Cubre las 4 reglas típicas de este tipo de validaciones:
 *
 *   1) Formato de cada campo en cada línea (tipo de dato, longitud, patrón, valores permitidos).
 *   2) Unicidad de uno o varios campos (o su concatenación) dentro de las líneas de detalle.
 *   3) Formato especial de la primera línea (encabezado / header).
 *   4) Totales correctos en la última línea (trailer), comparados contra lo acumulado
 *      en las líneas de detalle (conteo de registros, sumatorias, etc).
 *
 * El script NO conoce de antemano la estructura de tu archivo: todo se define
 * en un arreglo de configuración (ver ejemplo al final de este archivo y en
 * ejemplo_uso.php). Así puedes reutilizar la clase para cualquier layout.
 */

class FixedWidthValidator
{
    /** @var array Configuración del layout (header, detail, trailer, unique_rules, totals) */
    private array $config;

    /** @var array Lista de errores encontrados */
    private array $errors = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Valida un archivo completo.
     *
     * @param string $filePath Ruta al archivo plano.
     * @return array ['valido' => bool, 'total_errores' => int, 'errores' => array]
     */
    public function validate(string $filePath): array
    {
        $this->errors = [];

        if (!file_exists($filePath)) {
            $this->addError(0, 'ARCHIVO', "No se encontró el archivo: $filePath");
            return $this->buildResult();
        }

        // FILE_IGNORE_NEW_LINES quita \n y \r\n, pero conserva espacios de padding
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            $this->addError(0, 'ARCHIVO', "No se pudo leer el archivo: $filePath");
            return $this->buildResult();
        }

        // Quitar líneas completamente vacías al final del archivo (frecuente por EOF)
        while (!empty($lines) && trim(end($lines)) === '') {
            array_pop($lines);
        }

        $totalLines = count($lines);

        // El header y el trailer son OPCIONALES: solo se esperan si el layout
        // los define en $config['header'] / $config['trailer']. Esto permite
        // reutilizar la clase también para archivos que son 100% detalle
        // (como el CO1), sin tener que inventar un header/trailer que no existe.
        $hasHeader  = isset($this->config['header']);
        $hasTrailer = isset($this->config['trailer']);
        $minLines   = ($hasHeader ? 1 : 0) + ($hasTrailer ? 1 : 0) + 1; // +1 para al menos 1 detalle

        if ($totalLines < $minLines) {
            $this->addError(0, 'ESTRUCTURA', "El archivo tiene $totalLines línea(s) y se esperaban al menos $minLines según el layout configurado (header/trailer/detalle).");
            return $this->buildResult();
        }

        $headerLine  = $hasHeader ? $lines[0] : null;
        $trailerLine = $hasTrailer ? $lines[$totalLines - 1] : null;

        $detailStart = $hasHeader ? 1 : 0;
        $detailCount = $totalLines - ($hasHeader ? 1 : 0) - ($hasTrailer ? 1 : 0);
        $detailLines = array_slice($lines, $detailStart, $detailCount);

        // --- Regla 3: formato especial de la primera línea (si aplica) ---
        if ($hasHeader) {
            $this->validateLineFormat($headerLine, 1, 'header');
        }

        // --- Regla 1 (detalle) + Regla 2 (unicidad) + acumulación para Regla 4 ---
        $uniqueSeen   = [];
        $accumulators = $this->initAccumulators();

        foreach ($detailLines as $idx => $line) {
            $lineNumber = $idx + 1 + ($hasHeader ? 1 : 0); // ajusta según si hay header

            $this->validateLineFormat($line, $lineNumber, 'detail');
            $this->validateUniqueness($line, $lineNumber, $uniqueSeen);
            $this->accumulate($line, $accumulators);
        }

        // --- Regla 1 (trailer, si aplica) ---
        if ($hasTrailer) {
            $this->validateLineFormat($trailerLine, $totalLines, 'trailer');

            // --- Regla 4: totales del trailer contra lo acumulado ---
            $this->validateTotals($trailerLine, $totalLines, $accumulators);
        }

        return $this->buildResult();
    }

    // -----------------------------------------------------------------
    // Regla 1: formato de campos
    // -----------------------------------------------------------------

    private function validateLineFormat(string $line, int $lineNumber, string $lineType): void
    {
        if (!isset($this->config[$lineType])) {
            return; // no se definió configuración para este tipo de línea
        }

        $def = $this->config[$lineType];

        // Longitud total de línea (opcional pero recomendado)
        if (isset($def['line_length']) && strlen($line) !== $def['line_length']) {
            $this->addError(
                $lineNumber,
                strtoupper($lineType),
                "Longitud de línea incorrecta. Esperado: {$def['line_length']}, encontrado: " . strlen($line)
            );
        }

        foreach ($def['fields'] as $fieldName => $fieldDef) {
            $raw = $this->extractField($line, $fieldDef['start'], $fieldDef['length']);
            $this->validateFieldValue($raw, $fieldDef, $fieldName, $lineNumber, $lineType);
        }
    }

    private function extractField(string $line, int $start, int $length): string
    {
        // $start es 1-based (como se documenta un layout normalmente)
        return substr($line, $start - 1, $length);
    }

    private function validateFieldValue(string $raw, array $def, string $fieldName, int $lineNumber, string $lineType): void
    {
        $trim     = $def['trim'] ?? true;
        $value    = $trim ? trim($raw) : $raw;
        $required = $def['required'] ?? false;
        $category = strtoupper($lineType);

        if ($value === '') {
            if ($required) {
                $this->addError($lineNumber, $category, "Campo '$fieldName' es obligatorio y está vacío.");
            }
            return; // vacío y opcional: no se valida el tipo
        }

        $type = $def['type'] ?? 'alphanumeric';

        switch ($type) {
            case 'alpha':
                if (!preg_match('/^[A-Za-zÀ-ÿ\s]+$/u', $value)) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' debe ser alfabético. Valor recibido: '$raw'");
                }
                break;

            case 'numeric':
                // numérico puro (ej: cédulas, códigos), se valida sobre el campo crudo con padding de ceros
                if (!preg_match('/^[0-9]+$/', $raw)) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' debe ser numérico. Valor recibido: '$raw'");
                }
                break;

            case 'decimal':
                // Numérico con N decimales implícitos (ej: 000000012345 con 2 decimales = 123.45)
                $decimals = $def['decimals'] ?? 2;
                if (!preg_match('/^[0-9]+$/', $raw)) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' debe ser numérico con $decimals decimales implícitos. Valor recibido: '$raw'");
                }
                break;

            case 'date':
                $format = $def['format'] ?? 'Ymd';
                $dt = DateTime::createFromFormat($format, $value);
                $errInfo = DateTime::getLastErrors();
                $tieneErrores = $errInfo && ($errInfo['warning_count'] > 0 || $errInfo['error_count'] > 0);
                if (!$dt || $tieneErrores) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' no es una fecha válida en formato '$format'. Valor recibido: '$raw'");
                }
                break;

            case 'alphanumeric':
                if (!preg_match('/^[A-Za-z0-9À-ÿ\s\.\-\_\/]*$/u', $value)) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' contiene caracteres no permitidos. Valor recibido: '$raw'");
                }
                break;

            case 'regex':
                $pattern = $def['pattern'] ?? '/.*/';
                if (!preg_match($pattern, $value)) {
                    $this->addError($lineNumber, $category, "Campo '$fieldName' no cumple el patrón esperado ($pattern). Valor recibido: '$raw'");
                }
                break;

            default:
                // sin validación de tipo específica
                break;
        }

        if (isset($def['allowed_values']) && !in_array($value, $def['allowed_values'], true)) {
            $allowed = implode(', ', $def['allowed_values']);
            $this->addError($lineNumber, $category, "Campo '$fieldName' tiene un valor no permitido ('$raw'). Valores permitidos: $allowed");
        }
    }

    // -----------------------------------------------------------------
    // Regla 2: unicidad de campos o concatenación de campos
    // -----------------------------------------------------------------

    private function validateUniqueness(string $line, int $lineNumber, array &$seen): void
    {
        if (!isset($this->config['unique_rules']) || !isset($this->config['detail']['fields'])) {
            return;
        }

        $detailFields = $this->config['detail']['fields'];

        foreach ($this->config['unique_rules'] as $ruleIndex => $rule) {
            $parts = [];
            foreach ($rule['fields'] as $fieldName) {
                if (!isset($detailFields[$fieldName])) {
                    continue;
                }
                $fieldDef = $detailFields[$fieldName];
                $parts[]  = trim($this->extractField($line, $fieldDef['start'], $fieldDef['length']));
            }

            $key   = implode('|', $parts);
            $label = $rule['label'] ?? implode(' + ', $rule['fields']);

            if (!isset($seen[$ruleIndex])) {
                $seen[$ruleIndex] = [];
            }

            if (isset($seen[$ruleIndex][$key])) {
                $this->addError(
                    $lineNumber,
                    'UNICIDAD',
                    "Valor duplicado para '$label': '$key' (ya se usó en la línea {$seen[$ruleIndex][$key]})"
                );
            } else {
                $seen[$ruleIndex][$key] = $lineNumber;
            }
        }
    }

    // -----------------------------------------------------------------
    // Regla 4: totales del trailer
    // -----------------------------------------------------------------

    private function initAccumulators(): array
    {
        $acc = [];
        foreach ($this->config['totals'] ?? [] as $idx => $totalDef) {
            $acc[$idx] = 0;
        }
        return $acc;
    }

    private function accumulate(string $line, array &$acc): void
    {
        if (!isset($this->config['totals']) || !isset($this->config['detail']['fields'])) {
            return;
        }

        $detailFields = $this->config['detail']['fields'];

        foreach ($this->config['totals'] as $idx => $totalDef) {
            if ($totalDef['type'] === 'count') {
                $acc[$idx]++;
                continue;
            }

            if ($totalDef['type'] === 'sum') {
                $sourceField = $totalDef['source_field'];
                if (!isset($detailFields[$sourceField])) {
                    continue;
                }
                $fieldDef = $detailFields[$sourceField];
                $raw      = trim($this->extractField($line, $fieldDef['start'], $fieldDef['length']));

                if (!is_numeric($raw)) {
                    continue; // el error de formato ya se reportó en validateFieldValue
                }

                $numericValue = (float) $raw;

                // Si el campo fuente es tipo 'decimal', el valor crudo trae los decimales implícitos
                if (($fieldDef['type'] ?? '') === 'decimal') {
                    $decimals = $fieldDef['decimals'] ?? 2;
                    $numericValue = $numericValue / (10 ** $decimals);
                }

                $acc[$idx] += $numericValue;
            }
        }
    }

    private function validateTotals(string $trailerLine, int $lineNumber, array $acc): void
    {
        if (!isset($this->config['totals']) || !isset($this->config['trailer']['fields'])) {
            return;
        }

        $trailerFields = $this->config['trailer']['fields'];

        foreach ($this->config['totals'] as $idx => $totalDef) {
            $trailerFieldName = $totalDef['trailer_field'];
            if (!isset($trailerFields[$trailerFieldName])) {
                continue;
            }

            $fieldDef = $trailerFields[$trailerFieldName];
            $raw      = trim($this->extractField($trailerLine, $fieldDef['start'], $fieldDef['length']));
            $label    = $totalDef['label'] ?? $trailerFieldName;

            if (!is_numeric($raw)) {
                $this->addError($lineNumber, 'TOTALES', "El campo de total '$label' no es numérico. Valor recibido: '$raw'");
                continue;
            }

            $reportedRaw = (float) $raw;
            $decimals    = $totalDef['decimals'] ?? ($fieldDef['decimals'] ?? 0);
            $reported    = $decimals > 0 ? $reportedRaw / (10 ** $decimals) : $reportedRaw;
            $expected    = $acc[$idx];

            $tolerance = 0.005; // tolerancia por redondeo en decimales
            if (abs($reported - $expected) > $tolerance) {
                $this->addError(
                    $lineNumber,
                    'TOTALES',
                    "Total '$label' no coincide. Esperado: " . number_format($expected, $decimals, '.', '') .
                    ", encontrado en el trailer: " . number_format($reported, $decimals, '.', '')
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // Utilitarios
    // -----------------------------------------------------------------

    private function addError(int $line, string $category, string $message): void
    {
        $this->errors[] = [
            'linea'     => $line,
            'categoria' => $category,
            'mensaje'   => $message,
        ];
    }

    private function buildResult(): array
    {
        return [
            'valido'        => count($this->errors) === 0,
            'total_errores' => count($this->errors),
            'errores'       => $this->errors,
        ];
    }
}
