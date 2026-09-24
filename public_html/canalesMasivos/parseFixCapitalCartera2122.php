<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseFixCapitalCartera2122 no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseFixCapitalCartera2122.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// SCRIPT TEMPORAL, solo cartera 2122
// En el historial de esta cartera, capital y deuda quedaron invertidos
// Por eso aqui se toma avHistAsig_deudaNetaActual como el capital real
// Correr una sola vez y luego borrar/dejar este archivo de lado

$coleccionAsignacion = 'cuAsignacionesGestionAP';
$coleccionHistorial  = 'avHistorialAsignacionesDiarias';
$carteraFija          = '2122';

$totalActualizados = 0;
$sinHistorial       = [];

$mdbActivo = new MYMONGODB();
$mdbActivo->buscar('control_carga_periodo', ['activo' => 1, 'cartera' => (int)$carteraFija]);

while ($rowPeriodo = $mdbActivo->siguiente()) {

    $periodo = (int)$rowPeriodo['periodo'];
    $fecha   = (int)$rowPeriodo['fecha'];

    // Aqui SIN el filtro de exists, se pisa lo que ya se haya guardado antes
    $condicionAsignacion = [
        'cubAG_carteraId'    => $carteraFija,
        'cubAG_ciclo'        => $periodo,
        'cubAG_fechaPeriodo' => $fecha,
    ];

    $mongoAsig = new MYMONGODB();
    $totalAsignaciones = $mongoAsig->buscar($coleccionAsignacion, $condicionAsignacion);

    if ($totalAsignaciones <= 0) {
        continue;
    }

    echo "Cartera {$carteraFija}, periodo {$periodo}: {$totalAsignaciones} asignacion(es) a corregir\n";

    while ($datos = $mongoAsig->siguiente()) {

        $condicionHistorial = [
            'avHistAsig_numFactura'   => (string)$datos['cubAG_numFactura'],
            'avHistAsig_carteraId'    => $carteraFija,
            'avHistAsig_ciclo'        => $periodo,
            'avHistAsig_fechaPeriodo' => $fecha,
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
            $sinHistorial[] = "Periodo {$periodo} | Factura " . $datos['cubAG_numFactura'];
            continue;
        }

        $docHistorial = $mongoHist->siguiente();

        // Aqui esta el fix: se toma deudaNetaActual en vez de capitalActual
        $capitalInicialPeriodo = (float)($docHistorial['avHistAsig_deudaNetaActual'] ?? 0);

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
