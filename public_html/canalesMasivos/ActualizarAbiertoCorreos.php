<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "ActualizarAbiertoCorreos no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/ActualizarAbiertoCorreos.php", "", $_SERVER["argv"][0]);
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
$db = new MYSQLDB();
$db1 = new MYSQLDB();
$db2 = new MYSQLDB();

print_h("Inicio " . (date("d/m/Y H:i:s")));
$ayer = strtotime("-2 day");

$c = $mongo->buscar("cbEnvioMails", [
    "cem_procesadoLeido" => [
        '$exists' => false
    ],
    "cem_susFechaEnvio" => [
        '$gt' => 0,
        '$lte' => $ayer
    ]
], [], ["_id" => -1], 3000);

print_h($c . " encontrados para procesar");
$procesado = 0;
$encontrados = 0;
$noencontrados = 0;
$actualizado = 0;
$noactualizado = 0;
if ($c > 0) {
    while ($correo = $mongo->siguiente()) {
        $direccion = $correo["cem_susEmail"];
        $campania = $correo["cem_susCampaniaId"];
        $fechaEnvio = $correo["cem_susFechaEnvio"];
        $desde = strtotime("-2 minutes", $fechaEnvio);
        $hasta = strtotime("+2 minutes", $fechaEnvio);
        $evento = $correo["cem_susLlamadaId"];
        $c1 = $mongo2->buscar("cbEnvioMailsLectura", ["correo" => $direccion, "campania" => intval($campania),  "fechaEnvio" => ['$gte' => $desde, '$lte' => $hasta]]);
        if ($c1 > 0) {
            $encontrados++;
            $lectura = $mongo2->siguientex();
            $fechaLectura = $lectura["fecha"];
            $item = [
                "procesado" => $fechaLectura,
                "enviado" => $fechaLectura,
                "abierto" => $fechaLectura,
                "cantidad_abierto" => 1,
                "cem_procesadoLeido" => time()
            ];
            $sql = $db->mkSQL("SELECT * FROM alenvelopes WHERE alEnvelopes_to LIKE %Q AND alEnvelopes_when>=%N AND alEnvelopes_when<=%N", "%" . $direccion . "%", $desde, $hasta);
            if ($db->query($sql)) {
                while ($row = $db->fetchRow()) {
                    $sql1 = $db1->mkSQL("UPDATE alenvelopes SET alEnvelopes_estadoEnvio=%Q, alEnvelopes_leido=%N, alEnvelopes_clic=%N WHERE alEnvelopes_id = %N", "enviado", $fechaLectura, $fechaLectura, $row["alEnvelopes_id"]);
                    if ($db1->query($sql1) > 0) {
                        $actualizado++;
                    } else {
                        $noactualizado++;
                    }
                }
            }
            $sql2 = $db2->mkSQL("SELECT * FROM scllamadas WHERE scLlamadas_id=%N", $correo['cem_susLlamadaId']);
            if ($db2->query($sql2)) {
                $row2 = $db2->fetchRow();
                $db2->query($db2->mkSQL("update scllamadas set scLlamadas_texto=%Q WHERE scLlamadas_id=%N", trim($row2['scLlamadas_texto']) . ' [Lectura ' . date('d/m/Y H:i:s', $fechaLectura) . ']', $correo['cem_susLlamadaId']));
            }
            $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $correo["_id"]], $item);
        } else {
            $noencontrados++;
            $item = [
                "procesado" => 0,
                "enviado" => 0,
                "abierto" => 0,
                "cantidad_abierto" => 0,
                "cem_procesadoLeido" => time()
            ];
            $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $correo["_id"]], $item);
        }
        $procesado++;
    }
}

print_h($procesado . " procesados");
print_h("   " . $encontrados . " encontrados");
print_h("       " . $actualizado . " actualizados");
print_h("       " . $noactualizado . " no actualizados");
print_h("   " . $noencontrados . " no encontrados");

print_h("Fin " . (date("d/m/Y H:i:s")));

echo "EJECUCION_COMPLETA";

?><?php
    //_FIN_DE_ARCHIVO 
    ?>