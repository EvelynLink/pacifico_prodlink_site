<?php
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCamposCreditoAsignacion no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCamposCreditoAsignacion.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Backfill de los campos del cubo de asignaciones (cuAsignacionesGestionAP) que
// salen de cbCreditos, para todas las carteras del cubo y solo en el periodo
// actual (control_carga_periodo activo:1).
// Para propagar un campo nuevo del cubo basta con sumarlo a MAPA_CAMPOS_CREDITO.

// campo del cubo => campo de cbCreditos
define('MAPA_CAMPOS_CREDITO', [
    'cubAG_tipoCredito' => 'cre_tipoCredito',
]);

define('COLECCION_ASIGNACION', 'cuAsignacionesGestionAP');
define('COLECCION_CREDITOS', 'cbCreditos');

$camposCredito = array_values(MAPA_CAMPOS_CREDITO);

$totalActualizados = 0;
$sinCredito        = [];

$mdbActivo = new MYMONGODB();
$mdbActivo->buscar('control_carga_periodo', ['activo' => 1]);

while ($rowPeriodo = $mdbActivo->siguiente()) {

    $cartera = (string)$rowPeriodo['cartera'];
    $periodo = (int)$rowPeriodo['periodo'];
    $fecha   = (int)$rowPeriodo['fecha'];

    $condicionAsignacion = [
        'cubAG_carteraId'    => $cartera,
        'cubAG_ciclo'        => $periodo,
        'cubAG_fechaPeriodo' => $fecha,
    ];

    $mongoAsig = new MYMONGODB();
    $totalAsignaciones = $mongoAsig->buscar(
        COLECCION_ASIGNACION,
        $condicionAsignacion,
        array_merge(['_id', 'cubAG_numFactura', 'cubAG_cbcreId'], array_keys(MAPA_CAMPOS_CREDITO))
    );

    if ($totalAsignaciones <= 0) {
        continue;
    }

    $actualizadosPeriodo = 0;

    while ($datos = $mongoAsig->siguiente()) {

        $etiqueta = "Cartera {$cartera} | Periodo {$periodo} | Factura " . ($datos['cubAG_numFactura'] ?? '');

        $idCredito = normalizarIdCredito($datos['cubAG_cbcreId'] ?? null);

        if ($idCredito === null) {
            $sinCredito[] = $etiqueta . " (sin cubAG_cbcreId valido)";
            continue;
        }

        $mongoCred = new MYMONGODB();
        $existeCredito = $mongoCred->buscar(COLECCION_CREDITOS, ['_id' => $idCredito], $camposCredito, [], 1);

        if ($existeCredito <= 0) {
            $sinCredito[] = $etiqueta . " (credito no encontrado en " . COLECCION_CREDITOS . ")";
            continue;
        }

        $credito = $mongoCred->siguiente();

        // Solo se escriben los campos que faltan o que cambiaron
        $newRow = [];
        foreach (MAPA_CAMPOS_CREDITO as $campoCubo => $campoCredito) {

            $valorNuevo = (string)($credito[$campoCredito] ?? '');

            if (array_key_exists($campoCubo, $datos) && (string)$datos[$campoCubo] === $valorNuevo) {
                continue;
            }

            $newRow[$campoCubo] = $valorNuevo;
        }

        if (empty($newRow)) {
            continue;
        }

        $mdbUpdate = new MYMONGODB();
        $mdbUpdate->actualizar(COLECCION_ASIGNACION, ['_id' => $datos['_id']], $newRow);

        $actualizadosPeriodo++;
    }

    $totalActualizados += $actualizadosPeriodo;

    echo "Cartera {$cartera}, periodo {$periodo}: {$totalAsignaciones} asignacion(es), {$actualizadosPeriodo} actualizada(s)\n";
}

echo "\n============================================================\n";
echo "Campos procesados: " . implode(', ', array_keys(MAPA_CAMPOS_CREDITO)) . "\n";
echo "Total actualizados: {$totalActualizados}\n";

if (!empty($sinCredito)) {
    echo "Asignaciones sin credito asociado (" . count($sinCredito) . "):\n";
    echo implode("\n", $sinCredito) . "\n";
}

// cubAG_cbcreId guarda el _id de cbCreditos: puede volver como ObjectId o como
// string segun como se haya guardado. Devuelve null si no sirve para buscar.
function normalizarIdCredito(mixed $id): mixed
{
    if (is_object($id)) {
        return $id;
    }

    if (is_string($id) && preg_match('/^[a-f0-9]{24}$/i', $id)) {
        return new MongoDB\BSON\ObjectId($id);
    }

    return null;
}

echo "EJECUCION_COMPLETA";

// _FIN_DE_ARCHIVO
