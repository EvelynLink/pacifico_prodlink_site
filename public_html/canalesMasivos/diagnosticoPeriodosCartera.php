<?
// ============================================================================
// Script de SOLO LECTURA para ver que hay realmente en control_carga_periodo
// para una cartera especifica: todos sus registros (periodo, activo, fecha,
// fechaFin), ordenados cronologicamente. No modifica nada.
//
// USO:
//   Web:  diagnosticoPeriodosCartera.php?cartera=2121
//   CLI:  php diagnosticoPeriodosCartera.php --cartera=2121
// ============================================================================

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "diagnosticoPeriodosCartera no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/diagnosticoPeriodosCartera.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// ----------------------------------------------------------------------------
// Obtener cartera pedida
// ----------------------------------------------------------------------------
$cartera = null;

if (php_sapi_name() === 'cli') {
    if (isset($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            if (strpos($arg, '--cartera=') === 0) {
                $cartera = substr($arg, strlen('--cartera='));
            }
        }
    }
} else {
    if (isset($_GET['cartera'])) {
        $cartera = $_GET['cartera'];
    }
}

if ($cartera === null || $cartera === '') {
    echo "Falta indicar la cartera.\n";
    echo "Uso web: diagnosticoPeriodosCartera.php?cartera=2121\n";
    echo "Uso CLI: php diagnosticoPeriodosCartera.php --cartera=2121\n";
    exit;
}

echo "============================================================\n";
echo "control_carga_periodo para cartera = {$cartera}\n";
echo "Fecha de hoy: " . date('Y-m-d') . "\n";
echo "============================================================\n";

// En control_carga_periodo el campo 'cartera' se guarda como int, a
// diferencia de los cubos/asignaciones donde es string. Se prueba primero
// como int (que es como esta guardado ahi) y, si no aparece nada, se
// reintenta como string por si hay registros viejos con el otro tipo.
$cartera = trim((string)$cartera);
$tipoUsado = 'int';

$mdb = new MYMONGODB();
$total = $mdb->buscar(
    'control_carga_periodo',
    ['cartera' => (int)$cartera],
    [],
    ['fecha' => 1],
    0
);

if ($total == 0) {
    $tipoUsado = 'string';
    $mdb = new MYMONGODB();
    $total = $mdb->buscar(
        'control_carga_periodo',
        ['cartera' => (string)$cartera],
        [],
        ['fecha' => 1],
        0
    );
}

echo "Tipo de dato usado para 'cartera' en la consulta: {$tipoUsado}\n";
echo "Total de registros encontrados: {$total}\n";
echo "------------------------------------------------------------\n";
printf("%-6s | %-6s | %-12s | %-12s | %-24s | %s\n", 'activo', 'periodo', 'fecha', 'fechaFin', 'contiene hoy?', '_id');
echo "------------------------------------------------------------\n";

$ahora = time();
$numero = 0;

while ($row = $mdb->siguiente()) {

    $numero++;

    $periodo  = (int)($row['periodo'] ?? 0);
    $activo   = (int)($row['activo'] ?? 0);
    $fecha    = (int)($row['fecha'] ?? 0);
    $fechaFin = (int)($row['fechaFin'] ?? 0);
    $id       = (string)($row['_id'] ?? '');

    $contieneHoy = ($ahora >= $fecha && $ahora <= $fechaFin) ? 'SI' : '';

    printf(
        "%-6s | %-6s | %-12s | %-12s | %-24s | %s\n",
        $activo,
        $periodo,
        date('Y-m-d', $fecha),
        date('Y-m-d', $fechaFin),
        $contieneHoy,
        $id
    );
}

echo "------------------------------------------------------------\n";
echo "Registros listados: {$numero}\n";
echo "============================================================\n";

echo "EJECUCION_COMPLETA";

?><? //_FIN_DE_ARCHIVO
    ?>
