<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCompromisoGestionCobranza no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCompromisoGestionCobranza.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}



define('COLECCION_CUBO_COBRANZA', 'cuGestionCobranzaMysql');
define('COLECCION_ORIGEN_AVPROGRAMADAS', 'avProgramadas');
define('TAMANIO_LOTE', 5000);
define('MAX_LINEAS_EN_PANTALLA', 200);

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
    ? "MODO: APLICANDO CAMBIOS REALES en " . COLECCION_CUBO_COBRANZA . "\n"
    : "MODO: SIMULACION (no se modifica nada). Agrega ?aplicar=1 (web) o --aplicar (CLI) para aplicar de verdad.\n";
echo "============================================================\n";

actualizarCompromisoGestionCobranza($aplicar);

echo "EJECUCION_COMPLETA";


// ============================================================================
// Funciones
// ============================================================================

/**
 * Recorre avProgramadas buscando los documentos que ya tienen
 * av_tipificacion.compromiso y/o av_tipificacion.montoCompromiso, y
 * actualiza (con $set liviano, sin reprocesar todo el registro) esos dos
 * campos en cada fila correspondiente de cuGestionCobranzaMysql, ubicada
 * por cubGC_avId = avProgramadas._id.
 */
function actualizarCompromisoGestionCobranza(bool $aplicar): void
{
    // ------------------------------------------------------------------
    // 1) Preparar log de cambios
    // ------------------------------------------------------------------
    $dirLog = __DIR__ . '/logs';
    if (!is_dir($dirLog)) {
        @mkdir($dirLog, 0775, true);
    }
    $rutaLog = $dirLog . '/actualizarCompromisoCubo_' . date('Ymd_His') . ($aplicar ? '_APLICADO' : '_SIMULACION') . '.log';
    $fpLog = @fopen($rutaLog, 'a');

    if ($fpLog) {
        echo "Detalle completo de cambios se va guardando en: {$rutaLog}\n";
    } else {
        echo "AVISO: no se pudo crear el archivo de log en {$rutaLog}; el detalle solo se vera en pantalla (limitado).\n";
    }
    echo "------------------------------------------------------------\n";

    // ------------------------------------------------------------------
    // 2) Recorrer, en lotes por _id, solo avProgramadas con compromiso
    //    y/o montoCompromiso presentes en av_tipificacion
    // ------------------------------------------------------------------
    $campos = [
        '_id',
        'av_factura',
        'av_carteraId',
        'av_tipificacion',
    ];

    $condicionBase = [
        '$or' => [
            ['av_tipificacion.compromiso'      => ['$nin' => [null, '']]],
            ['av_tipificacion.montoCompromiso' => ['$exists' => true, '$ne' => null]],
        ],
    ];

    echo "Filtro aplicado sobre " . COLECCION_ORIGEN_AVPROGRAMADAS . ": av_tipificacion.compromiso o av_tipificacion.montoCompromiso presentes\n";
    echo "IMPORTANTE: para que Mongo no recorra la coleccion completa (~8M docs) al aplicar\n";
    echo "este filtro, debe existir un indice parcial (son pocos registros) sobre\n";
    echo "av_tipificacion.compromiso y av_tipificacion.montoCompromiso (ver mensaje del chat).\n";
    echo "------------------------------------------------------------\n";

    $ultimoId = null;
    $totalAvRevisados = 0;
    $totalAvSinMatchEnCubo = 0;
    $totalCuboRevisados = 0;
    $totalCuboConCambio = 0;
    $totalCuboAplicados = 0;

    while (true) {

        $condicion = $condicionBase;
        if ($ultimoId !== null) {
            $condicion['_id'] = ['$gt' => $ultimoId];
        }

        $mdbAvProg = new MYMONGODB();
        $mdbAvProg->buscar(COLECCION_ORIGEN_AVPROGRAMADAS, $condicion, $campos, ['_id' => 1], TAMANIO_LOTE);

        $numRows = 0;

        while ($doc = $mdbAvProg->siguiente()) {

            $numRows++;
            $totalAvRevisados++;
            $ultimoId = $doc['_id'] ?? $ultimoId;

            $avId = $doc['_id'] ?? null;
            if ($avId === null) {
                continue;
            }

            $compromiso = $doc['av_tipificacion']['compromiso'] ?? null;
            $montoCompromiso = isset($doc['av_tipificacion']['montoCompromiso'])
                ? (float)$doc['av_tipificacion']['montoCompromiso']
                : null;

            if (($compromiso === null || $compromiso === '') && $montoCompromiso === null) {
                continue;
            }

            // Puede haber varias filas en el cubo para un mismo avProgramadas
            // (evento principal + reintentos), todas comparten cubGC_avId.
            $mdbCubo = new MYMONGODB();
            $condCubo = ['cubGC_avId' => new MongoDB\BSON\ObjectId((string)$avId)];
            $campCubo = [
                '_id',
                'cubGC_numFactura',
                'cubGC_carteraId',
                'cubGC_tipificacion_compromiso',
                'cubGC_tipificacion_montoCompromiso',
            ];
            $mdbCubo->buscar(COLECCION_CUBO_COBRANZA, $condCubo, $campCubo, [], 50);

            $huboMatch = false;

            while ($docCubo = $mdbCubo->siguiente()) {

                $huboMatch = true;
                $totalCuboRevisados++;

                $compromisoActual = (string)($docCubo['cubGC_tipificacion_compromiso'] ?? '');
                $compromisoNuevo  = (string)($compromiso ?? '');
                $cambioCompromiso = $compromisoNuevo !== $compromisoActual;

                $montoActual = (float)($docCubo['cubGC_tipificacion_montoCompromiso'] ?? 0);
                $montoNuevo  = (float)($montoCompromiso ?? 0);
                $cambioMonto = abs($montoNuevo - $montoActual) > 0.001;

                if (!$cambioCompromiso && !$cambioMonto) {
                    continue;
                }

                $totalCuboConCambio++;

                $linea = sprintf(
                    "[%s] _id=%s | avId=%s | Factura=%s | Cartera=%s | Compromiso: '%s' -> '%s' | MontoCompromiso: %s -> %s",
                    $aplicar ? 'APLICADO' : 'SIMULADO',
                    (string)($docCubo['_id'] ?? ''),
                    (string)$avId,
                    (string)($docCubo['cubGC_numFactura'] ?? ''),
                    (string)($docCubo['cubGC_carteraId'] ?? ''),
                    $compromisoActual,
                    $compromisoNuevo,
                    $montoActual,
                    $montoNuevo
                );

                if ($fpLog) {
                    fwrite($fpLog, $linea . PHP_EOL);
                }
                if ($totalCuboConCambio <= MAX_LINEAS_EN_PANTALLA) {
                    echo $linea . "\n";
                } elseif ($totalCuboConCambio === MAX_LINEAS_EN_PANTALLA + 1) {
                    echo "... (mas cambios, se siguen guardando en el log) ...\n";
                }

                if ($aplicar) {
                    $mdbUpdate = new MYMONGODB();
                    $nuevosDatos = [
                        'cubGC_tipificacion_compromiso'      => $compromisoNuevo,
                        'cubGC_tipificacion_montoCompromiso' => $montoNuevo,
                    ];
                    $mdbUpdate->actualizar(COLECCION_CUBO_COBRANZA, ['_id' => $docCubo['_id']], $nuevosDatos);
                    $totalCuboAplicados++;
                }
            }

            if (!$huboMatch) {
                $totalAvSinMatchEnCubo++;
            }
        }

        if ($numRows < TAMANIO_LOTE) {
            break;
        }

        echo "-- Progreso: avProgramadas revisados {$totalAvRevisados} | filas cubo con cambio {$totalCuboConCambio} | aplicados {$totalCuboAplicados} --\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    if ($fpLog) {
        fclose($fpLog);
    }

    // ------------------------------------------------------------------
    // 3) Resumen final
    // ------------------------------------------------------------------
    echo "============================================================\n";
    echo "RESUMEN\n";
    echo "Filtro: avProgramadas con av_tipificacion.compromiso o av_tipificacion.montoCompromiso\n";
    echo "Documentos avProgramadas revisados (dentro del filtro): {$totalAvRevisados}\n";
    echo "Documentos avProgramadas sin fila correspondiente en el cubo: {$totalAvSinMatchEnCubo}\n";
    echo "Filas del cubo revisadas (por cubGC_avId):              {$totalCuboRevisados}\n";
    echo "Filas del cubo con cambio detectado:                    {$totalCuboConCambio}\n";
    echo "Filas del cubo actualizadas en Mongo:                   {$totalCuboAplicados}" . ($aplicar ? "\n" : " (0 porque estas en modo simulacion)\n");
    if ($fpLog) {
        echo "Log completo:                                           {$rutaLog}\n";
    }
    if (!$aplicar && $totalCuboConCambio > 0) {
        echo "\nPara aplicar estos cambios de verdad, vuelve a correr con ?aplicar=1 (web) o --aplicar (CLI).\n";
    }
    echo "============================================================\n";
}

?><? //_FIN_DE_ARCHIVO
    ?>
