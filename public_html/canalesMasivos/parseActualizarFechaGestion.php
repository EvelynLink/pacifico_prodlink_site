<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');
ignore_user_abort(true); // el proceso sigue corriendo en el servidor aunque se cierre el navegador

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarFechaGestion no pudo determinar su ruta absoluta";
        exit;
    }

    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarFechaGestion.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Llamada principal
actualizarFechaGestion([
    'coleccionOrigen'  => 'avProgramadas',
    'coleccionDestino' => 'cuGestionCobranzaMysql',
    'campoMatch'       => 'cubGC_avId'
]);

function actualizarFechaGestion($config)
{
    $coleccionOrigen  = $config['coleccionOrigen'];
    $coleccionDestino = $config['coleccionDestino'];
    $campoMatch       = $config['campoMatch'];

    $mongoOrigen = new MYMONGODB();

    $condicionOrigen = [
        'av_eventoFecha' => [
            '$gte' => 1784782800,
            '$lte' => 1784869199
        ]
    ];

    $camposOrigen = [
        '_id',
        'av_evento',
        'av_fechaFinLlamada',
        'av_fechaGeneraLlamada',
        'av_eventoFecha'
    ];

    $totalOrigen = $mongoOrigen->buscar(
        $coleccionOrigen,
        $condicionOrigen,
        $camposOrigen
    );

    $totalProcesados = 0;
    $totalActualizados = 0;
    $sinMatch = 0;
    $sinCambios = 0;

    // Conexiones reutilizables (se crean UNA sola vez, no en cada vuelta del loop)
    $mongoDestino = new MYMONGODB();
    $mdbUpdate    = new MYMONGODB();
    $dbLlam       = new MYSQLDB();

    $tiempoInicio = microtime(true);

    if ($totalOrigen > 0) {

        while ($doc = $mongoOrigen->siguiente()) {

            $totalProcesados++;

            $avId = $doc['_id'];

            $condicionDestino = [
                $campoMatch => $avId
            ];

            $camposDestino = [
                '_id',
                'cubGC_fechaGestion',
                'cubGC_llamadaId'
            ];

            $totalDestino = $mongoDestino->buscar(
                $coleccionDestino,
                $condicionDestino,
                $camposDestino
            );

            if ($totalDestino > 0) {

                while ($rowDestino = $mongoDestino->siguiente()) {

                    // ¿Evento principal?
                    $esPrincipal = (
                        isset($doc['av_evento']) &&
                        isset($rowDestino['cubGC_llamadaId']) &&
                        (int)$doc['av_evento'] === (int)$rowDestino['cubGC_llamadaId']
                    );

                    if ($esPrincipal) {

                        if (!empty($doc['av_fechaFinLlamada'])) {
                            $fechaGestion = (int)$doc['av_fechaFinLlamada'];
                        } elseif (!empty($doc['av_fechaGeneraLlamada'])) {
                            $fechaGestion = (int)$doc['av_fechaGeneraLlamada'];
                        } else {
                            $fechaGestion = !empty($doc['av_eventoFecha'])
                                ? (int)$doc['av_eventoFecha']
                                : null;
                        }

                    } else {

                        // Reintento: obtener fecha desde MySQL (conexión reutilizada)
                        $sql = $dbLlam->mkSQL(
                            "SELECT scLlamadas_fechaCreacion
                             FROM scllamadas
                             WHERE scLlamadas_id=%N",
                            $rowDestino['cubGC_llamadaId']
                        );

                        $dbLlam->query($sql);
                        $rowSql = $dbLlam->fetchRow() ?: [];

                        $fechaGestion = !empty($rowSql['scLlamadas_fechaCreacion'])
                            ? (int)$rowSql['scLlamadas_fechaCreacion']
                            : null;
                    }

                    $fechaActual = $rowDestino['cubGC_fechaGestion'] ?? null;

                    // Si la fecha calculada es igual a la que ya tiene, no hace falta actualizar
                    if ((int)$fechaActual === (int)$fechaGestion) {
                        $sinCambios++;
                        continue;
                    }

                    $criteria = [
                        '_id' => $rowDestino['_id']
                    ];

                    $newRow = [
                        'cubGC_fechaGestion' => $fechaGestion
                    ];

                    $mdbUpdate->actualizar(
                        $coleccionDestino,
                        $criteria,
                        $newRow
                    );

                    $totalActualizados++;
                }

            } else {
                $sinMatch++;
            }
        }
    }

    $transcurridoFinal = round(microtime(true) - $tiempoInicio, 2);

    echo "
        <hr>
        Colección origen: $coleccionOrigen <br>
        Colección destino: $coleccionDestino <br><br>

        Total documentos avProgramadas procesados: $totalProcesados <br>
        Total documentos actualizados en $coleccionDestino: $totalActualizados <br>
        Sin cambios: $sinCambios <br>
        Sin match en $coleccionDestino: $sinMatch <br>
        Tiempo total: {$transcurridoFinal}s <br>
    ";
}

echo "EJECUCION_COMPLETA";

?>
