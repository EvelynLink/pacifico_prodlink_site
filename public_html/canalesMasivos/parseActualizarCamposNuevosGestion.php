<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCamposNuevosGestion no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCamposNuevosGestion.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}



// Backfill de campos de createData(): abierto/cantidad_abierto (EMAIL), horaInicio/horaFin (TELEFONICA),
// fechaProgramacion (los 3 canales) y fechaGestion corregido a ws_estadoEnvioFecha (WHATSAPP).
define('CONFIG_CUBOS', [
    'COBRANZA' => ['coleccion' => 'cuGestionCobranzaMysql', 'prefijo' => 'cubGC'],
    'VENTAS'   => ['coleccion' => 'cuGestionVentas',        'prefijo' => 'cubGV'],
]);
define('TAMANIO_LOTE', 5000);
define('MAX_LINEAS_EN_PANTALLA', 200);

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

$fechaDesde     = new DateTime(FILTRO_FECHA_GESTION_DESDE, new DateTimeZone(FILTRO_FECHA_GESTION_TZ));
$timestampDesde = $fechaDesde->getTimestamp();

foreach (CONFIG_CUBOS as $etiqueta => $config) {
    backfillCorreoYHorario($etiqueta, $config['coleccion'], $config['prefijo'], $timestampDesde, $aplicar);
}

echo "EJECUCION_COMPLETA";


// ============================================================================
// Funciones
// ============================================================================

function backfillCorreoYHorario(string $etiqueta, string $coleccionCubo, string $pref, int $timestampDesde, bool $aplicar): void
{
    echo "============================================================\n";
    echo "CUBO: {$etiqueta} ({$coleccionCubo})\n";
    echo "============================================================\n";

    $dirLog = __DIR__ . '/logs';
    if (!is_dir($dirLog)) {
        @mkdir($dirLog, 0775, true);
    }
    $rutaLog = $dirLog . '/actualizarCamposNuevosGestion_' . $etiqueta . '_' . date('Ymd_His') . ($aplicar ? '_APLICADO' : '_SIMULACION') . '.log';
    $fpLog = @fopen($rutaLog, 'a');

    if ($fpLog) {
        echo "Detalle completo de cambios se va guardando en: {$rutaLog}\n";
    } else {
        echo "AVISO: no se pudo crear el archivo de log en {$rutaLog}; el detalle solo se vera en pantalla (limitado).\n";
    }
    echo "------------------------------------------------------------\n";

    // TELEFONICA (horaInicio/horaFin), EMAIL (abierto/cantidad_abierto) y
    // WHATSAPP (fechaGestion) tienen campos que rellenar/corregir aqui.
    $campos = [
        '_id',
        $pref . '_numFactura',
        $pref . '_canal',
        $pref . '_avId',
        $pref . '_llamadaId',
        $pref . '_fechaGestion',
        $pref . '_fechaProgramacion',
        $pref . '_abierto',
        $pref . '_cantidad_abierto',
        $pref . '_horaInicio',
        $pref . '_horaFin',
    ];

    $condicionBase = [
        $pref . '_fechaGestion' => ['$gte' => $timestampDesde],
        $pref . '_canal'        => ['$in' => ['TELEFONICA', 'EMAIL', 'WHATSAPP']],
    ];

    echo "Filtro aplicado: {$pref}_fechaGestion >= " . FILTRO_FECHA_GESTION_DESDE
        . " (" . FILTRO_FECHA_GESTION_TZ . ", timestamp {$timestampDesde}), canal TELEFONICA, EMAIL o WHATSAPP\n";
    echo "------------------------------------------------------------\n";

    $ultimoId       = null;
    $totalRevisados = 0;
    $totalConCambio = 0;
    $totalAplicados = 0;
    $totalSinMatch  = 0;

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

            $canal = (string)($doc[$pref . '_canal'] ?? '');
            $nuevosDatos = [];
            $detalleCambio = '';

            if ($canal === 'TELEFONICA') {

                // Mismo match usado en createData(): scllamadas.scLlamadas_id
                // = cubXX_llamadaId, join a sccdr por scLlamadas_cdrId.
                $idLlamada = (int)($doc[$pref . '_llamadaId'] ?? 0);
                $avId = $doc[$pref . '_avId'] ?? null;
                if ($idLlamada <= 0 || empty($avId)) {
                    $totalSinMatch++;
                    continue;
                }

                $dbCdr = new MYSQLDB();
                $sqlCdr = $dbCdr->mkSQL(
                    "SELECT sc1.scCDR_horaIni AS horaInicio, sc1.scCDR_horaFin AS horaFin
                     FROM scllamadas
                     LEFT JOIN sccdr sc1 ON sc1.scCDR_id = scllamadas.scLlamadas_cdrId
                     WHERE scllamadas.scLlamadas_id = %N",
                    $idLlamada
                );
                $dbCdr->query($sqlCdr);
                $rowCdr = $dbCdr->fetchRow() ?: [];

                // av_fecha vive en el documento original de avProgramadas (cubXX_avId).
                $mdbAv = new MYMONGODB();
                $mdbAv->buscar(
                    'avProgramadas',
                    ['_id' => new MongoDB\BSON\ObjectId((string)$avId)],
                    ['av_fecha'],
                    [],
                    1
                );
                $rowAv = $mdbAv->siguiente() ?: [];

                $nuevoHoraInicio  = (int)($rowCdr['horaInicio'] ?? 0);
                $nuevoHoraFin     = (int)($rowCdr['horaFin'] ?? 0);
                $actualHoraInicio = (int)($doc[$pref . '_horaInicio'] ?? 0);
                $actualHoraFin    = (int)($doc[$pref . '_horaFin'] ?? 0);

                $nuevoFechaProgramacion  = (int)($rowAv['av_fecha'] ?? 0);
                $actualFechaProgramacion = (int)($doc[$pref . '_fechaProgramacion'] ?? 0);

                // Si el campo existe pero no quedo guardado como entero (ej.
                // texto de una corrida vieja), se reescribe aunque el valor
                // numerico ya sea el mismo, para corregir el tipo en Mongo.
                $tipoIncorrecto = (array_key_exists($pref . '_horaInicio', $doc) && !is_int($doc[$pref . '_horaInicio']))
                    || (array_key_exists($pref . '_horaFin', $doc) && !is_int($doc[$pref . '_horaFin']));

                $sinCambios = !$tipoIncorrecto
                    && $nuevoHoraInicio === $actualHoraInicio
                    && $nuevoHoraFin === $actualHoraFin
                    && $nuevoFechaProgramacion === $actualFechaProgramacion;

                if ($sinCambios) {
                    continue;
                }

                $nuevosDatos = [
                    $pref . '_horaInicio'        => $nuevoHoraInicio,
                    $pref . '_horaFin'           => $nuevoHoraFin,
                    $pref . '_fechaProgramacion' => $nuevoFechaProgramacion,
                ];
                $detalleCambio = sprintf(
                    "horaInicio: %d -> %d | horaFin: %d -> %d | fechaProgramacion: %d -> %d%s",
                    $actualHoraInicio,
                    $nuevoHoraInicio,
                    $actualHoraFin,
                    $nuevoHoraFin,
                    $actualFechaProgramacion,
                    $nuevoFechaProgramacion,
                    $tipoIncorrecto ? " (corrigiendo tipo a entero)" : ""
                );
            } elseif ($canal === 'EMAIL') {

                // Mismo match usado en createData(): cubXX_avId es el _id del
                // documento original en cbEnvioMails.
                $avId = $doc[$pref . '_avId'] ?? null;
                if (empty($avId)) {
                    $totalSinMatch++;
                    continue;
                }

                $mdbMail = new MYMONGODB();
                $mdbMail->buscar(
                    'cbEnvioMails',
                    ['_id' => new MongoDB\BSON\ObjectId((string)$avId)],
                    ['abierto', 'cantidad_abierto', 'cem_susFechaAsignacion'],
                    [],
                    1
                );
                $rowMail = $mdbMail->siguiente() ?: [];

                $nuevoAbierto          = (int)($rowMail['abierto'] ?? 0);
                $nuevoCantidadAbierto  = (int)($rowMail['cantidad_abierto'] ?? 0);
                $actualAbierto         = (int)($doc[$pref . '_abierto'] ?? 0);
                $actualCantidadAbierto = (int)($doc[$pref . '_cantidad_abierto'] ?? 0);

                $nuevoFechaProgramacion  = (int)($rowMail['cem_susFechaAsignacion'] ?? 0);
                $actualFechaProgramacion = (int)($doc[$pref . '_fechaProgramacion'] ?? 0);

                // Misma correccion de tipo que en TELEFONICA: si ya existe
                // pero no es entero, se reescribe aunque el valor coincida.
                $tipoIncorrecto = (array_key_exists($pref . '_abierto', $doc) && !is_int($doc[$pref . '_abierto']))
                    || (array_key_exists($pref . '_cantidad_abierto', $doc) && !is_int($doc[$pref . '_cantidad_abierto']));

                $sinCambios = !$tipoIncorrecto
                    && $nuevoAbierto === $actualAbierto
                    && $nuevoCantidadAbierto === $actualCantidadAbierto
                    && $nuevoFechaProgramacion === $actualFechaProgramacion;

                if ($sinCambios) {
                    continue;
                }

                $nuevosDatos = [
                    $pref . '_abierto'           => $nuevoAbierto,
                    $pref . '_cantidad_abierto'  => $nuevoCantidadAbierto,
                    $pref . '_fechaProgramacion' => $nuevoFechaProgramacion,
                ];
                $detalleCambio = sprintf(
                    "abierto: %d -> %d | cantidad_abierto: %d -> %d | fechaProgramacion: %d -> %d%s",
                    $actualAbierto,
                    $nuevoAbierto,
                    $actualCantidadAbierto,
                    $nuevoCantidadAbierto,
                    $actualFechaProgramacion,
                    $nuevoFechaProgramacion,
                    $tipoIncorrecto ? " (corrigiendo tipo a entero)" : ""
                );
            } elseif ($canal === 'WHATSAPP') {

                // Mismo match usado en createData(): cubXX_avId es el _id del
                // documento original en avProgramadasWhatsApp.
                $avId = $doc[$pref . '_avId'] ?? null;
                if (empty($avId)) {
                    $totalSinMatch++;
                    continue;
                }

                $mdbWhats = new MYMONGODB();
                $mdbWhats->buscar(
                    'avProgramadasWhatsApp',
                    ['_id' => new MongoDB\BSON\ObjectId((string)$avId)],
                    ['ws_fecha', 'ws_estadoEnvioFecha'],
                    [],
                    1
                );
                $rowWhats = $mdbWhats->siguiente() ?: [];

                $nuevoFechaProgramacion  = (int)($rowWhats['ws_fecha'] ?? 0);
                $nuevoFechaGestion       = (int)($rowWhats['ws_estadoEnvioFecha'] ?? 0);
                $actualFechaProgramacion = (int)($doc[$pref . '_fechaProgramacion'] ?? 0);
                $actualFechaGestion      = (int)($doc[$pref . '_fechaGestion'] ?? 0);

                if ($nuevoFechaProgramacion === $actualFechaProgramacion && $nuevoFechaGestion === $actualFechaGestion) {
                    continue;
                }

                $nuevosDatos = [
                    $pref . '_fechaProgramacion' => $nuevoFechaProgramacion,
                    $pref . '_fechaGestion'      => $nuevoFechaGestion,
                ];
                $detalleCambio = sprintf(
                    "fechaProgramacion: %d -> %d | fechaGestion: %d -> %d",
                    $actualFechaProgramacion,
                    $nuevoFechaProgramacion,
                    $actualFechaGestion,
                    $nuevoFechaGestion
                );
            } else {
                continue;
            }

            $totalConCambio++;

            $fechaGestion = (int)($doc[$pref . '_fechaGestion'] ?? 0);

            $linea = sprintf(
                "[%s] _id=%s | Factura=%s | Canal=%s | FechaGestion=%s | %s",
                $aplicar ? 'APLICADO' : 'SIMULADO',
                (string)($doc['_id'] ?? ''),
                (string)($doc[$pref . '_numFactura'] ?? ''),
                $canal,
                $fechaGestion ? date('Y-m-d H:i:s', $fechaGestion) : '',
                $detalleCambio
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

    echo "============================================================\n";
    echo "RESUMEN {$etiqueta}\n";
    echo "Filtro: {$pref}_fechaGestion >= " . FILTRO_FECHA_GESTION_DESDE . " (" . FILTRO_FECHA_GESTION_TZ . "), canal TELEFONICA, EMAIL o WHATSAPP\n";
    echo "Total documentos revisados (dentro del filtro): {$totalRevisados}\n";
    echo "Documentos sin dato para hacer match (sin llamadaId/avId): {$totalSinMatch}\n";
    echo "Documentos con cambio detectado:        {$totalConCambio}\n";
    echo "Documentos actualizados en Mongo:       {$totalAplicados}" . ($aplicar ? "\n" : " (0 porque estas en modo simulacion)\n");
    if ($fpLog) {
        echo "Log completo:                           {$rutaLog}\n";
    }
    if (!$aplicar && $totalConCambio > 0) {
        echo "\nPara aplicar estos cambios de verdad, vuelve a correr con ?aplicar=1 (web) o --aplicar (CLI).\n";
    }
    echo "============================================================\n";
}

?><? //_FIN_DE_ARCHIVO
    ?>
