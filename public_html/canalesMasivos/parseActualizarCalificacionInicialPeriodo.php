<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCalificacionInicialPeriodo no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCalificacionInicialPeriodo.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Backfill de cubAG_calificacionAcelerada, solo COBRANZA, solo periodo activo
// Proyecta la mora del primer dia del historial hasta fechaFin y le asigna el tramo (igual que el cubo)

$coleccionAsignacion = 'cuAsignacionesGestionAP';
$coleccionHistorial  = 'avHistorialAsignacionesDiarias';

// true: recalcula todo el periodo activo; false: solo los que no tienen el campo
$recalcularTodo = true;

$tramosMora = cargarTramosMora();

$db = new MYSQLDB();

$carteras = [];
$sql = $db->mkSQL('SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo=%Q', 'COBRANZA');
$db->query($sql);
while ($rowMysql = $db->fetchRow()) {
    $carteras[] = (string)$rowMysql['cobCartera_id'];
}

$totalActualizados = 0;
$sinHistorial       = [];
$sinDiasMora        = [];

$mdbActivo = new MYMONGODB();
$mdbActivo->buscar('control_carga_periodo', ['activo' => 1]);

while ($rowPeriodo = $mdbActivo->siguiente()) {

    $cartera = (string)$rowPeriodo['cartera'];

    if (!in_array($cartera, $carteras)) {
        continue;
    }

    $periodo  = (int)$rowPeriodo['periodo'];
    $fecha    = (int)$rowPeriodo['fecha'];
    $fechaFin = (int)($rowPeriodo['fechaFin'] ?? 0);

    $condicionAsignacion = [
        'cubAG_carteraId'           => $cartera,
        'cubAG_ciclo'               => $periodo,
        'cubAG_fechaPeriodo'        => $fecha,
    ];
    if (!$recalcularTodo) {
        $condicionAsignacion['cubAG_calificacionAcelerada'] = ['$exists' => false];
    }

    $mongoAsig = new MYMONGODB();
    $totalAsignaciones = $mongoAsig->buscar($coleccionAsignacion, $condicionAsignacion);

    if ($totalAsignaciones <= 0) {
        continue;
    }

    echo "Cartera {$cartera}, periodo {$periodo}: {$totalAsignaciones} asignacion(es) a calcular\n";

    while ($datos = $mongoAsig->siguiente()) {

        // Match con el historial: factura, cartera, ciclo y fechaPeriodo
        $condicionHistorial = [
            'avHistAsig_numFactura'    => (string)$datos['cubAG_numFactura'],
            'avHistAsig_carteraId'     => $cartera,
            'avHistAsig_ciclo'         => $periodo,
            'avHistAsig_fechaPeriodo'  => $fecha,
        ];

        $mongoHist = new MYMONGODB();

        // El primer dia cargado es el de menor fechaCreacion
        $existeHistorial = $mongoHist->buscar(
            $coleccionHistorial,
            $condicionHistorial,
            [],
            ['avHistAsig_fechaCreacion' => 1],
            1
        );

        if ($existeHistorial <= 0) {
            $sinHistorial[] = "Cartera {$cartera} | Periodo {$periodo} | Factura " . $datos['cubAG_numFactura'];
            continue;
        }

        $docHistorial = $mongoHist->siguiente();

        // Sin dias de mora en el historial no se inventa un valor: se reporta y se salta
        if (!isset($docHistorial['avHistAsig_diasMora'])) {
            $sinDiasMora[] = "Cartera {$cartera} | Periodo {$periodo} | Factura " . $datos['cubAG_numFactura'];
            continue;
        }

        $acel = calificacionAcelerada(
            (int)$docHistorial['avHistAsig_diasMora'],
            (string)($datos['cubAG_producto'] ?? ''),
            $cartera,
            $fecha,
            $fechaFin,
            (string)($docHistorial['avHistAsig_riesgo'] ?? ''),
            $tramosMora
        );

        $criteria = ['_id' => $datos['_id']];
        $newRow   = [
            'cubAG_calificacionAcelerada' => $acel['calificacion'],
            'cubAG_diasMoraProyectados'   => $acel['dias'],
        ];

        $mdbUpdate = new MYMONGODB();
        $mdbUpdate->actualizar($coleccionAsignacion, $criteria, $newRow);

        $totalActualizados++;
    }
}

echo "\n============================================================\n";
echo "Total actualizados: {$totalActualizados}\n";

if (!empty($sinHistorial)) {
    echo "Sin match en {$coleccionHistorial} (" . count($sinHistorial) . "):\n";
    echo implode("\n", $sinHistorial) . "\n";
}

if (!empty($sinDiasMora)) {
    echo "Historial sin avHistAsig_diasMora (" . count($sinDiasMora) . "):\n";
    echo implode("\n", $sinDiasMora) . "\n";
}

echo "EJECUCION_COMPLETA";

// Misma regla que cuPGasignacionesGestion::calificacionAcelerada(): mora + dias entre fechaInicio y fechaFin.
// Sin fechas del periodo se deja el riesgo del primer dia.
function calificacionAcelerada($diasMora, $producto, $carteraId, $fechaInicio, $fechaFin, $riesgoInicial, $tramosMora) {
    if ($fechaInicio <= 0 || $fechaFin <= 0) {
        return ['dias' => $diasMora, 'calificacion' => $riesgoInicial];
    }
    $diasPeriodo = (int)round((strtotime(date('Y-m-d', $fechaFin)) - strtotime(date('Y-m-d', $fechaInicio))) / 86400);
    $diasProyectados = $diasMora + max(0, $diasPeriodo);

    return [
        'dias'         => $diasProyectados,
        'calificacion' => buscarTramoMora($diasProyectados, $producto, $carteraId, $tramosMora),
    ];
}

// Misma logica que calificacion() del ETL: primero tramos del producto, si no, los de la cartera.
function buscarTramoMora($mora, $producto, $carteraId, $tramosMora) {
    $tramos = [];
    if ($producto !== '' && isset($tramosMora['producto'][$producto])) {
        $tramos = $tramosMora['producto'][$producto];
    } elseif ($carteraId !== '' && isset($tramosMora['cartera'][$carteraId])) {
        $tramos = $tramosMora['cartera'][$carteraId];
    }

    foreach ($tramos as $tr) {
        $inicio = $tr['tr_tramoInicio'];
        $fin    = $tr['tr_tramoFin'];
        if (($inicio != 9999999 && $fin != 9999999 && $mora >= $inicio && $mora <= $fin)
            || ($fin == 9999999 && $mora >= $inicio)
            || ($inicio == 9999999 && $mora <= $fin)) {
            return (string)$tr['tr_tramo'];
        }
    }
    return '';
}

// Tramos MORA con el mismo aggregate del ETL, cargados una sola vez.
function cargarTramosMora() {
    $tramosMora = ['producto' => [], 'cartera' => []];
    $condition = [
        ['$match' => ['tr_tipoTramo' => 'MORA']],
        ['$lookup' => [
            'from'         => 'cbConfig',
            'localField'   => '_id',
            'foreignField' => 'cbConf_cbTramosId',
            'as'           => 'data',
        ]],
        ['$unwind' => '$data'],
        ['$match' => ['data.cbConf_tipo' => 'tr_MORA_CARTERA']],
        ['$lookup' => [
            'from'         => 'cbConfig',
            'localField'   => 'data.cbConf_cbTramosId',
            'foreignField' => 'cbConf_cbTramosId',
            'as'           => 'data1',
        ]],
        ['$unwind' => '$data1'],
        ['$match' => ['data1.cbConf_tipo' => 'tr_MORA_PRODUCTO']],
    ];

    $mdbTr = new MYMONGODB();
    $mdbTr->agregar('cbTramos', $condition);
    while ($tr = $mdbTr->siguiente()) {
        $tramo = [
            'tr_tramo'       => $tr['tr_tramo'] ?? '',
            'tr_tramoInicio' => $tr['tr_tramoInicio'] ?? null,
            'tr_tramoFin'    => $tr['tr_tramoFin'] ?? null,
        ];
        if (isset($tr['data']['cbConf_id'])) {
            $tramosMora['cartera'][$tr['data']['cbConf_id']][] = $tramo;
        }
        if (isset($tr['data1']['cbConf_carteraNombre'])) {
            $tramosMora['producto'][$tr['data1']['cbConf_carteraNombre']][] = $tramo;
        }
    }
    return $tramosMora;
}
