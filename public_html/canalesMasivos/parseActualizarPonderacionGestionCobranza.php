<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarPonderacionGestionCobranza no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarPonderacionGestionCobranza.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}



// Cubos a recalcular: la tabla cobmaparbolgst (MySQL) es la misma para los
// dos, solo cambia la coleccion Mongo y el prefijo de sus campos.
define('CONFIG_CUBOS', [
    'COBRANZA' => ['coleccion' => 'cuGestionCobranzaMysql', 'prefijo' => 'cubGC'],
    'VENTAS'   => ['coleccion' => 'cuGestionVentas',        'prefijo' => 'cubGV'],
]);
define('TAMANIO_LOTE', 5000);
define('MAX_LINEAS_EN_PANTALLA', 200);

// Filtro de alcance: barrido completo (todas las tipificaciones y sin importar
// la ponderacion actual) pero acotado a la gestion desde esta fecha en adelante,
// para no tener que recorrer millones de registros historicos.
define('FILTRO_FECHA_GESTION_DESDE', '2026-08-01 00:00:00');
define('FILTRO_FECHA_GESTION_TZ', 'America/Guayaquil');

// ----------------------------------------------------------------------------
// Determinar modo: simulacion (por defecto) o aplicar cambios reales
// ----------------------------------------------------------------------------
$aplicar = false;
if (php_sapi_name() === 'cli') {
    $aplicar = isset($argv) && in_array('--aplicar', $argv, true);
} else {
    $aplicar = isset($_GET['aplicar']) && $_GET['aplicar'] == '1';
}

echo "============================================================\n";
echo $aplicar
    ? "MODO: APLICANDO CAMBIOS REALES\n"
    : "MODO: SIMULACION (no se modifica nada). Agrega ?aplicar=1 (web) o --aplicar (CLI) para aplicar de verdad.\n";
echo "============================================================\n";

// cobmaparbolgst es la misma tabla MySQL para ambos cubos: se carga una sola
// vez y se reutiliza, en vez de repetir la consulta por cada cubo.
$mapaPonderacion = cargarMapaPonderacion();

foreach (CONFIG_CUBOS as $etiqueta => $config) {
    recalcularPonderacionGestion($etiqueta, $config['coleccion'], $config['prefijo'], $mapaPonderacion, $aplicar);
}

echo "EJECUCION_COMPLETA";


// ============================================================================
// Funciones
// ============================================================================

/**
 * Carga cobmaparbolgst completa en memoria (tabla MySQL compartida por
 * los cubos de ventas y cobranza).
 */
function cargarMapaPonderacion(): array
{
    $db = new MYSQLDB();

    $mapaPonderacion = [];
    $duplicados = 0;

    $sql = "SELECT cobMapArbolGst_id, cobMapArbolGst_campaniaId,
                    cobMapArbolGst_ramaOrigenNombre, cobMapArbolGst_carteraId,
                    cobMapArbolGst_ponderacion
             FROM cobmaparbolgst";
    $db->query($sql);

    while ($row = $db->fetchRow()) {
        $clave = construirClaveMapa(
            $row['cobMapArbolGst_campaniaId'],
            $row['cobMapArbolGst_ramaOrigenNombre'],
            $row['cobMapArbolGst_carteraId']
        );

        if (isset($mapaPonderacion[$clave])) {
            $duplicados++;
        }

        $mapaPonderacion[$clave] = [
            'id'          => $row['cobMapArbolGst_id'],
            'ponderacion' => (int)$row['cobMapArbolGst_ponderacion'],
        ];
    }

    echo "cobmaparbolgst cargado en memoria: " . count($mapaPonderacion) . " combinaciones (campania+rama+cartera).\n";
    if ($duplicados > 0) {
        echo "AVISO: se encontraron {$duplicados} combinaciones duplicadas en cobmaparbolgst (se uso la ultima fila leida para cada una).\n";
    }
    echo "------------------------------------------------------------\n";

    return $mapaPonderacion;
}

function recalcularPonderacionGestion(string $etiqueta, string $coleccionCubo, string $pref, array $mapaPonderacion, bool $aplicar): void
{
    echo "============================================================\n";
    echo "CUBO: {$etiqueta} ({$coleccionCubo})\n";
    echo "============================================================\n";

    // ------------------------------------------------------------------
    // Preparar log de cambios
    // ------------------------------------------------------------------
    $dirLog = __DIR__ . '/logs';
    if (!is_dir($dirLog)) {
        @mkdir($dirLog, 0775, true);
    }
    $rutaLog = $dirLog . '/actualizarPonderacionCubo_' . $etiqueta . '_' . date('Ymd_His') . ($aplicar ? '_APLICADO' : '_SIMULACION') . '.log';
    $fpLog = @fopen($rutaLog, 'a');

    if ($fpLog) {
        echo "Detalle completo de cambios se va guardando en: {$rutaLog}\n";
    } else {
        echo "AVISO: no se pudo crear el archivo de log en {$rutaLog}; el detalle solo se vera en pantalla (limitado).\n";
    }
    echo "------------------------------------------------------------\n";

    // ------------------------------------------------------------------
    // Recorrer, en lotes, TODOS los documentos del cubo
    // (cualquier tipificacion_respuesta1, cualquier ponderacion actual)
    // cuya gestion sea desde FILTRO_FECHA_GESTION_DESDE en adelante.
    // ------------------------------------------------------------------
    $campos = [
        '_id',
        $pref . '_numFactura',
        $pref . '_carteraId',
        $pref . '_campaniaId',
        $pref . '_tipificacion_respuesta2',
        $pref . '_ponderacion',
        $pref . '_cobmaparbolgstId',
        $pref . '_fechaGestion',
    ];

    $fechaDesde        = new DateTime(FILTRO_FECHA_GESTION_DESDE, new DateTimeZone(FILTRO_FECHA_GESTION_TZ));
    $timestampDesde    = $fechaDesde->getTimestamp();

    $condicionBase = [
        $pref . '_fechaGestion' => ['$gte' => $timestampDesde],
    ];

    echo "Filtro aplicado: {$pref}_fechaGestion >= " . FILTRO_FECHA_GESTION_DESDE
        . " (" . FILTRO_FECHA_GESTION_TZ . ", timestamp {$timestampDesde})\n";
    echo "Se revisan TODAS las tipificaciones y TODAS las ponderaciones actuales (barrido completo del rango de fecha).\n";
    echo "------------------------------------------------------------\n";

    $ultimoId = null;
    $totalRevisados = 0;
    $totalConCambio = 0;
    $totalAplicados = 0;
    $totalSinTipificacion = 0;

    while (true) {

        $condicion = $condicionBase;
        if ($ultimoId !== null) {
            $condicion['_id'] = ['$gt' => $ultimoId];
        }

        $mdbCubo = new MYMONGODB();
        $mdbCubo->buscar($coleccionCubo, $condicion, $campos, ['_id' => 1], TAMANIO_LOTE);

        $numRows = 0;

        while ($doc = $mdbCubo->siguiente()) {

            $numRows++;
            $totalRevisados++;
            $ultimoId = $doc['_id'] ?? $ultimoId;

            $campaniaId = (int)($doc[$pref . '_campaniaId'] ?? 0);
            $carteraId  = (int)($doc[$pref . '_carteraId'] ?? 0);
            $rama       = (string)($doc[$pref . '_tipificacion_respuesta2'] ?? '');

            if ($rama === '') {
                $totalSinTipificacion++;
            }

            $clave = construirClaveMapa($campaniaId, $rama, $carteraId);
            $match = $mapaPonderacion[$clave] ?? null;

            $ponderacionActual = (int)($doc[$pref . '_ponderacion'] ?? 0);
            $mapIdActual        = $doc[$pref . '_cobmaparbolgstId'] ?? null;

            if ($match !== null) {
                $nuevaPonderacion = (int)$match['ponderacion'];
                $nuevoMapId       = $match['id'];
            } else {
                // Sin match en cobmaparbolgst: se deja en 0 / vacio,
                // igual que el comportamiento original de createData().
                $nuevaPonderacion = 0;
                $nuevoMapId       = null;
            }

            $cambioPonderacion = $nuevaPonderacion !== $ponderacionActual;
            $cambioMapId       = (string)$nuevoMapId !== (string)$mapIdActual;

            if (!$cambioPonderacion && !$cambioMapId) {
                continue;
            }

            $totalConCambio++;

            $fechaGestion = (int)($doc[$pref . '_fechaGestion'] ?? 0);

            $linea = sprintf(
                "[%s] _id=%s | Factura=%s | Cartera=%s | Campania=%s | FechaGestion=%s | Rama=%s | Ponderacion: %d -> %d | cobMapArbolGstId: %s -> %s",
                $aplicar ? 'APLICADO' : 'SIMULADO',
                (string)($doc['_id'] ?? ''),
                (string)($doc[$pref . '_numFactura'] ?? ''),
                $carteraId,
                $campaniaId,
                $fechaGestion ? date('Y-m-d H:i:s', $fechaGestion) : '',
                $rama,
                $ponderacionActual,
                $nuevaPonderacion,
                (string)($mapIdActual ?? ''),
                (string)($nuevoMapId ?? '')
            );

            if ($fpLog) {
                fwrite($fpLog, $linea . PHP_EOL);
            }
            if ($totalConCambio <= MAX_LINEAS_EN_PANTALLA) {
                echo $linea . "\n";
            } elseif ($totalConCambio === MAX_LINEAS_EN_PANTALLA + 1) {
                echo "... (mas cambios, se siguen guardando en el log) ...\n";
            }

            if ($aplicar) {
                $mdbUpdate = new MYMONGODB();
                $nuevosDatos = [
                    $pref . '_ponderacion'      => $nuevaPonderacion,
                    $pref . '_cobmaparbolgstId' => $nuevoMapId,
                ];
                $mdbUpdate->actualizar($coleccionCubo, ['_id' => $doc['_id']], $nuevosDatos);
                $totalAplicados++;
            }
        }

        if ($numRows < TAMANIO_LOTE) {
            break;
        }

        echo "-- Progreso [{$etiqueta}]: revisados {$totalRevisados} | con cambio {$totalConCambio} | aplicados {$totalAplicados} --\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    if ($fpLog) {
        fclose($fpLog);
    }

    // ------------------------------------------------------------------
    // Resumen final
    // ------------------------------------------------------------------
    echo "============================================================\n";
    echo "RESUMEN {$etiqueta}\n";
    echo "Filtro: {$pref}_fechaGestion >= " . FILTRO_FECHA_GESTION_DESDE . " (" . FILTRO_FECHA_GESTION_TZ . "), todas las tipificaciones y ponderaciones\n";
    echo "Total documentos revisados (dentro del filtro): {$totalRevisados}\n";
    echo "Documentos sin tipificacion_respuesta2: {$totalSinTipificacion}\n";
    echo "Documentos con cambio detectado:        {$totalConCambio}\n";
    echo "Documentos actualizados en Mongo:       {$totalAplicados}" . ($aplicar ? "\n" : " (0 porque estas en modo simulacion)\n");
    if ($fpLog) {
        echo "Log completo:                           {$rutaLog}\n";
    }
    if (!$aplicar && $totalConCambio > 0) {
        echo "\nPara aplicar estos cambios de verdad, vuelve a correr con ?aplicar=1 (web) o --aplicar (CLI).\n";
    }
    if ($aplicar && $totalAplicados > 0) {
        echo "\nRecuerda correr despues parseActualizarNoGestionadas.php para propagar la ponderacion corregida hacia la asignacion.\n";
    }
    echo "============================================================\n";
}

/**
 * Construye la clave de busqueda para el mapa en memoria de cobmaparbolgst.
 * Se normaliza la rama (trim + mayusculas) porque MySQL, segun el collation
 * de la columna, suele comparar cobMapArbolGst_ramaOrigenNombre de forma
 * insensible a mayusculas/minusculas; esto replica ese comportamiento.
 */
function construirClaveMapa($campaniaId, $rama, $carteraId): string
{
    return (int)$campaniaId . '|' . strtoupper(trim((string)$rama)) . '|' . (int)$carteraId;
}

?><? //_FIN_DE_ARCHIVO
    ?>
