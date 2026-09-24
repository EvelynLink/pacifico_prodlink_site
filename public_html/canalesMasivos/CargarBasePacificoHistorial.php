<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

/**
 * Pasa o una coleccion historica cada dia la carga del pacifico
 * 
 * https://portcoll-qa.zona-link.com/canalesMasivos/CargarBasePacificoHistorial.php
 */

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "CargarBasePacificoHistorial no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/CargarBasePacificoHistorial.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

$mongo = new MYMONGODB();
$mongo2 = new MYMONGODB();
$mongo3 = new MYMONGODB();

print_h("Inicio " . (date("d/m/Y H:i:s")));

$c = $mongo->buscar("cbCargaEtl", ["carteraEtl_procesadoHistorico" => ['$exists' => false]]);
$dinamico = date("dmY", time());
//$dinamico = "25102025";
$busca = date("md", time());
//$busca = "1025";

print_h($c . " encontrados para " . $dinamico);
$procesado = 0;
if ($c > 0) {
    while ($carga = $mongo->siguiente()) {
        if (strpos($carga["carteraEtl_nombreArchivoCompleto"], $busca) !== false) {
            if (isset($carga["carteraEtl_factura"]) && isset($carga["carteraEtl_cedula"])) {
                $c = $mongo2->buscar("cbCargaEtlDetalleCargas", ["carteraEtl_factura" => $carga["carteraEtl_factura"]]);
                if ($c > 0) {
                    $r = $mongo2->siguiente();
                    $registro = [
                        "carga_" . $dinamico => 1,
                        //"monto_" . $dinamico => $carga["carteraEtl_deudaNeta"]
                        "detalle_" . $dinamico => [
                            "deudaNeta" => $carga["carteraEtl_deudaNeta"],
                            "saldoCapital" => $carga["carteraEtl_saldoCapital"],
                            "diasMora" => intval($carga["carteraEtl_diasMora"]),
                            "fechaPeriodo" => isset($carga["carteraEtl_fechaPeriodo"]) ? intval($carga["carteraEtl_fechaPeriodo"]) : 0,
                            "periodo" => isset($carga["carteraEtl_periodo"]) ? intval($carga["carteraEtl_periodo"]) : "",
                            "producto" => $carga["carteraEtl_producto"]
                        ]
                    ];
                    $h = $mongo2->actualizar("cbCargaEtlDetalleCargas", ["_id" => $r["_id"]], $registro);
                    $procesado = $h > 0 ? $procesado + 1 : $procesado;
                } else {
                    $registro = [
                        "carteraEtl_cedula" => $carga["carteraEtl_cedula"],
                        "carteraEtl_primerNombre" => $carga["carteraEtl_primerNombre"] ?? "",
                        "carteraEtl_segundoNombre" => $carga["carteraEtl_segundoNombre"] ?? "",
                        "carteraEtl_apellidoPaterno" => $carga["carteraEtl_apellidoPaterno"] ?? "",
                        "carteraEtl_apellidoMaterno" => $carga["carteraEtl_apellidoMaterno"] ?? "",
                        "carteraEtl_factura" => $carga["carteraEtl_factura"],
                        "carteraEtl_carteraId" => $carga["carteraEtl_carteraId"],
                        "carteraEtl_carteraNombre" => $carga["carteraEtl_carteraNombre"],
                        "carga_" . $dinamico => 1,
                        "detalle_" . $dinamico => [
                            "deudaNeta" => $carga["carteraEtl_deudaNeta"],
                            "saldoCapital" => $carga["carteraEtl_saldoCapital"],
                            "diasMora" => intval($carga["carteraEtl_diasMora"]),
                            "fechaPeriodo" => intval($carga["carteraEtl_fechaPeriodo"]),
                            "periodo" => intval($carga["carteraEtl_periodo"]),
                            "producto" => $carga["carteraEtl_producto"]
                        ]
                    ];
                    $h = $mongo2->guardar("cbCargaEtlDetalleCargas", $registro);
                    $procesado = $h != 0 ? $procesado + 1 : $procesado;
                }
                $mongo3->actualizar("cbCargaEtl", ["_id" => $carga["_id"]], ["carteraEtl_procesadoHistorico" => 1]);
                //$mongo3->crearIndice("cbCargaEtl", ["carga_" . $dinamico]);
            } else {
                print_h("sin factura y/o cedula");
            }
        } else {
            print_h("Archivo no tiene la fecha de " . $busca);
        }
    }
}

print_h($procesado . " procesados");

print_h("Fin " . (date("d/m/Y H:i:s")));

echo "EJECUCION_COMPLETA";

?>
<?php
//_FIN_DE_ARCHIVO 
?>