<?php
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarCicloFechaPeriodoVentas no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarCicloFechaPeriodoVentas.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Backfill: rellena cubGV_ciclo y cubGV_fechaPeriodo en las gestiones de VENTAS
// que se quedaron sin esos campos (por el bug del cubo), usando el periodo/ciclo
// vigente en control_carga_periodo para cada cartera, siempre que la gestion
// sea posterior o igual a la fecha de inicio de ese periodo.

actualizarCicloFechaPeriodo('VENTAS', [
    'coleccionGestion' => 'cuGestionVentas',
    'prefGestion'      => 'cubGV'
]);



function actualizarCicloFechaPeriodo($tipoCartera, $config)
{
    $mongo = new MYMONGODB();
    $db = new MYSQLDB();

    $carteras = [];
    $procesados = [];

    // TRAER CARTERAS DEL TIPO INDICADO
    $sql = $db->mkSQL(
        'SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo=%Q',
        $tipoCartera
    );

    $db->query($sql);

    while ($rowMysql = $db->fetchRow()) {
        $carteras[] = (string)$rowMysql['cobCartera_id'];
    }

    // CONTROL PERIODOS ACTIVOS
    $condPeriodo = ['activo' => 1];

    $mongo->buscar("control_carga_periodo", $condPeriodo);

    while ($row = $mongo->siguiente()) {

        $cartera = (string)$row["cartera"];

        if (!in_array($cartera, $carteras)) {
            continue;
        }

        $periodo  = (int)$row["periodo"];
        $fecha    = (int)$row["fecha"];
        $fechaFin = (int)$row["fechaFin"];

        $key = $cartera . "_" . $periodo . "_" . $fecha . "_" . $fechaFin;

        if (in_array($key, $procesados)) {
            continue;
        }

        $procesados[] = $key;

        // Normalizamos a inicio del dia, igual que hace el proceso original
        $desde = strtotime(date('Y-m-d', $fecha) . " 00:00:00");

        $prefGest = $config['prefGestion'];

        // CONDICION: gestiones de ESTA cartera que no tienen ciclo o fechaPeriodo
        // (cubre el campo inexistente, en null o en cadena vacia por si el cubo
        // los dejo de alguna de esas tres formas), y cuya fecha de gestion sea
        // >= a la fecha de inicio del periodo vigente.
        $condGestion = [
            $prefGest . "_carteraId" => (string)$cartera,
            '$or' => [
                [$prefGest . "_ciclo"        => ['$exists' => false]],
                [$prefGest . "_ciclo"        => null],
                [$prefGest . "_ciclo"        => ""],
                [$prefGest . "_fechaPeriodo" => ['$exists' => false]],
                [$prefGest . "_fechaPeriodo" => null],
                [$prefGest . "_fechaPeriodo" => ""],
            ],
            $prefGest . "_fechaGestion" => [
                '$gte' => (int)$desde
            ]
        ];

        $mongoGestion = new MYMONGODB();

        $totalSinCiclo = $mongoGestion->buscar(
            $config['coleccionGestion'],
            $condGestion
        );

        $actualizados = 0;
        $idsActualizados = [];

        if ($totalSinCiclo > 0) {

            while ($doc = $mongoGestion->siguiente()) {

                $criteria = [
                    '_id' => $doc['_id']
                ];

                $newRow = [
                    $prefGest . '_ciclo'        => $periodo,
                    $prefGest . '_fechaPeriodo' => $fecha,
                ];

                $mdbUpdate = new MYMONGODB();

                $mdbUpdate->actualizar(
                    $config['coleccionGestion'],
                    $criteria,
                    $newRow
                );

                $idsActualizados[] = (string)$doc['_id'];
                $actualizados++;
            }

            echo "
                <hr>

                Tipo: $tipoCartera <br>
                Cartera: $cartera <br>
                Periodo (ciclo): $periodo <br>

                Fecha periodo: " . date('Y-m-d', $fecha) . "<br>
                Fecha fin: " . date('Y-m-d', $fechaFin) . "<br><br>

                Total gestiones sin ciclo/fechaPeriodo (con fechaGestion >= inicio periodo): $totalSinCiclo <br>
                Actualizadas: $actualizados <br><br>

                IDs actualizados:<br>
            " . implode("<br>", $idsActualizados) . " <br>
            ";
        }
    }
}

echo "EJECUCION_COMPLETA";
