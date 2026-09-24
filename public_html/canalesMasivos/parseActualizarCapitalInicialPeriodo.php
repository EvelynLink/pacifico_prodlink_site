<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCapitalInicialPeriodo no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCapitalInicialPeriodo.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Backfill de cubAG_capitalInicialPeriodo, solo COBRANZA, solo periodo activo
// Toma el capital del primer dia cargado en avHistorialAsignacionesDiarias

$coleccionAsignacion = 'cuAsignacionesGestionAP';
$coleccionHistorial  = 'avHistorialAsignacionesDiarias';

$db = new MYSQLDB();

$carteras = [];
$sql = $db->mkSQL('SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo=%Q', 'COBRANZA');
$db->query($sql);
while ($rowMysql = $db->fetchRow()) {
    $carteras[] = (string)$rowMysql['cobCartera_id'];
}

$totalActualizados = 0;
$sinHistorial       = [];

$mdbActivo = new MYMONGODB();
$mdbActivo->buscar('control_carga_periodo', ['activo' => 1]);

while ($rowPeriodo = $mdbActivo->siguiente()) {

    $cartera = (string)$rowPeriodo['cartera'];

    if (!in_array($cartera, $carteras)) {
        continue;
    }

    $periodo  = (int)$rowPeriodo['periodo'];
    $fecha    = (int)$rowPeriodo['fecha'];

    // Solo asignaciones de este periodo que todavia no tienen el campo
    $condicionAsignacion = [
        'cubAG_carteraId'           => $cartera,
        'cubAG_ciclo'               => $periodo,
        'cubAG_fechaPeriodo'        => $fecha,
        'cubAG_capitalInicialPeriodo' => ['$exists' => false],
    ];

    $mongoAsig = new MYMONGODB();
    $totalAsignaciones = $mongoAsig->buscar($coleccionAsignacion, $condicionAsignacion);

    if ($totalAsignaciones <= 0) {
        continue;
    }

    echo "Cartera {$cartera}, periodo {$periodo}: {$totalAsignaciones} asignacion(es) sin capitalInicialPeriodo\n";

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

        $capitalInicialPeriodo = (float)($docHistorial['avHistAsig_capitalActual'] ?? 0);

        $criteria = ['_id' => $datos['_id']];
        $newRow   = ['cubAG_capitalInicialPeriodo' => $capitalInicialPeriodo];

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

echo "EJECUCION_COMPLETA";
