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
        echo "parseActualizarPagosAsignacion no pudo determinar su ruta absoluta";
        exit;
    }

    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarPagosAsignacion.php", "", $_SERVER["argv"][0]);
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
actualizarMontoTotalPago([
    'coleccionAsignacion' => 'cuAsignacionesGestionAP',
    'coleccionPagos'      => 'cbPagos',
    'coleccionPeriodos'   => 'control_carga_periodo'
]);

function actualizarMontoTotalPago($config)
{
    $coleccionAsignacion = $config['coleccionAsignacion'];
    $coleccionPagos       = $config['coleccionPagos'];
    $coleccionPeriodos    = $config['coleccionPeriodos'];

    // --- 1. TRAER PERIODOS ACTIVOS ---
    $mongoPeriodos = new MYMONGODB();

    $condPeriodo = ['activo' => 1];

    $totalPeriodos = $mongoPeriodos->buscar($coleccionPeriodos, $condPeriodo);

    $procesados = [];

    $totalAsigProcesadas = 0;
    $totalActualizados   = 0;
    $sinCambios           = 0;
    $sinPagos              = 0;

    $mongoAsig  = new MYMONGODB();
    $mongoPag   = new MYMONGODB();
    $mdbUpdate  = new MYMONGODB();

    $tiempoInicio = microtime(true);

    if ($totalPeriodos > 0) {

        while ($rowPeriodo = $mongoPeriodos->siguiente()) {

            $cartera  = (string)$rowPeriodo['cartera'];
            $periodo  = (int)$rowPeriodo['periodo'];
            $fecha    = (int)$rowPeriodo['fecha'];
            $fechaFin = (int)$rowPeriodo['fechaFin'];

            $key = $cartera . "_" . $periodo . "_" . $fecha . "_" . $fechaFin;

            if (in_array($key, $procesados)) {
                continue;
            }

            $procesados[] = $key;

            // --- 2. TRAER ASIGNACIONES QUE HACEN MATCH CON ESTE PERIODO ---
            $condicionAsignacion = [
                'cubAG_carteraId'    => $cartera,
                'cubAG_ciclo'        => $periodo,
                'cubAG_fechaPeriodo' => $fecha,
            ];

            $camposAsignacion = [
                '_id',
                'cubAG_numFactura',
                'cubAG_carteraId',
                'cubAG_ciclo',
                'cubAG_fechaInicio',
                'cubAG_montoTotalPago',
            ];

            $totalAsignaciones = $mongoAsig->buscar(
                $coleccionAsignacion,
                $condicionAsignacion,
                $camposAsignacion
            );

            if ($totalAsignaciones > 0) {

                while ($datos = $mongoAsig->siguiente()) {

                    $totalAsigProcesadas++;

                    // --- 3. SUMAR PAGOS DE cbPagos ---
                    $condPag = [
                        'pagos_numFactura' => $datos['cubAG_numFactura'],
                        'pagos_carteraId'  => $datos['cubAG_carteraId'],
                        'pagos_periodo'    => (int)$datos['cubAG_ciclo'],
                        'pagos_proceso'    => ['$gte' => (int)$datos['cubAG_fechaInicio']],
                    ];

                    $camposPag = ['pagos_monto'];

                    $totalPag = $mongoPag->buscar($coleccionPagos, $condPag, $camposPag);

                    $totalMonto = 0;

                    if ($totalPag > 0) {
                        while ($docPag = $mongoPag->siguiente()) {
                            $totalMonto += isset($docPag['pagos_monto']) ? (float)$docPag['pagos_monto'] : 0;
                        }
                    } else {
                        $sinPagos++;
                    }

                    $nuevoMonto = round($totalMonto, 2);
                    $montoActual = isset($datos['cubAG_montoTotalPago']) ? round((float)$datos['cubAG_montoTotalPago'], 2) : null;

                    // Solo actualizar si el monto calculado es diferente al actual
                    if ($montoActual === $nuevoMonto) {
                        $sinCambios++;
                        continue;
                    }

                    $criteria = [
                        '_id' => $datos['_id']
                    ];

                    $newRow = [
                        'cubAG_montoTotalPago' => $nuevoMonto
                    ];

                    $mdbUpdate->actualizar(
                        $coleccionAsignacion,
                        $criteria,
                        $newRow
                    );

                    $totalActualizados++;
                }
            }
        }
    }

    $transcurridoFinal = round(microtime(true) - $tiempoInicio, 2);

    echo "
        <hr>
        Colección asignación: $coleccionAsignacion <br>
        Colección pagos: $coleccionPagos <br><br>

        Periodos activos procesados: " . count($procesados) . " <br>
        Total asignaciones procesadas: $totalAsigProcesadas <br>
        Total actualizadas: $totalActualizados <br>
        Sin cambios (ya estaban al día): $sinCambios <br>
        Sin pagos encontrados: $sinPagos <br>
        Tiempo total: {$transcurridoFinal}s <br>
    ";
}

echo "EJECUCION_COMPLETA";

?>