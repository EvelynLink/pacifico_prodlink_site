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
$mysql = new MYSQLDB();

print_h("Inicio " . (date("d/m/Y H:i:s")));

$archivo = "f05ed48f-8059-45c6-a267-7623d591be82";
$coleccion = "tmp_sendgrid_" . $archivo;

// $fp = fopen('../web/cacheFolder/' . $archivo . '.csv', 'r');

// if ($fp === false) {
//     print_h("Error! No se pudo abrir el archivo");
// } else {
//     $fila = 0;
//     $encabezado = [];
//     //$encabezado = ["processed", "message_id", "event", "api_key_id", "recv_message_id", "credential_id", "subject", "from", "email", "asm_group_id", "template_id", "originating_ip", "reason", "outbound_ip", "outbound_ip_type", "mx", "attempt", "url", "user_agent", "type", "is_unique", "username", "categories", "marketing_campaign_id", "marketing_campaign_name", "marketing_campaign_split_id", "marketing_campaign_version", "unique_args"];
//     while ($csv_line = fgetcsv($fp, 1024)) {
//         if ($fila == 0) {
//             foreach ($csv_line as $k) {
//                 $encabezado[] = $k;
//             }
//         } else {
//             $filaguardar = [];
//             foreach ($encabezado as $k => $enc) {
//                 $filaguardar[$enc] = $csv_line[$k];
//             }
//             //$filaguardar["archivo"] = $archivo . ".csv";
//             $mongo->guardar($coleccion, $filaguardar);
//         }
//         $fila++;
//     }
//     print_h("Total filas guardadas " . $fila);
// }

$primero = strtotime("first day of last month");
$ultimo = strtotime("last day of last month");

$c = $mongo->buscar("cbEnvioMails", ["cem_procesadoLeido" => ['$exists' => false], "cem_susFechaEnvio" => ['$gte' => $primero, '$lte' => $ultimo]], [], ["_id" => 1], 1000);
print_h($c . " a procesar");
$procesados = 0;
$sindatos = 0;
$sinalerts = 0;
$rechazosg = 0;
$i = 1;
while ($r = $mongo->siguiente()) {
    $item = [
        "procesado" => 0,
        "enviado" => 0,
        "abierto" => 0,
        "cantidad_abierto" => 0,
        "cem_procesadoLeido" => time()
    ];
    //busco en el email alerts
    $hace5minutos = strtotime("2 minutes ago", $r["cem_susFechaEnvio"]);
    $pasado5minutos = strtotime("+ 2 minutes", $r["cem_susFechaEnvio"]);
    $sql = $mysql->mkSQL("SELECT * FROM alenvelopes WHERE alEnvelopes_to=%Q AND alEnvelopes_when >= %Q AND alEnvelopes_when <= %Q", $r["cem_susEmail"], $hace5minutos, $pasado5minutos);
    if ($mysql->query($sql) > 0) {
        $rp = 0;
        while ($row = $mysql->fetchRow()) {
            $rp++;
            if ($rp > 1) {
                print_h("repite");
            }
            if (strpos($row["alEnvelopes_extra"], "No hay direcciones válidas") === false) {
                $x2 = $mongo2->buscar($coleccion, ["recv_message_id" => $row["alEnvelopes_extra"]]);
                if ($x2 > 0) {
                    // print_h($r["cem_susEmail"]);
                    // print_h($row["alEnvelopes_extra"]);
                    while ($r1 = $mongo2->siguientex()) {
                        if ($r1["event"] == "processed") {
                            $item["procesado"] = strtotime($r1["processed"]);
                        }
                        if ($r1["event"] == "delivered") {
                            $item["enviado"] = strtotime($r1["processed"]);
                        }
                        if ($r1["event"] == "open") {
                            $item["cantidad_abierto"]++;
                            $item["abierto"] = strtotime($r1["processed"]);
                        }
                    }
                    //print_h($item);
                    $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $r["_id"]], $item);
                    $procesados++;
                } else {
                    $x3 = $mongo2->buscar($coleccion, ["message_id" => $row["alEnvelopes_extra"]]);
                    if ($x3 > 0) {
                        while ($r1 = $mongo2->siguientex()) {
                            if ($r1["event"] == "processed") {
                                $item["procesado"] = strtotime($r1["processed"]);
                            }
                            if ($r1["event"] == "delivered") {
                                $item["enviado"] = strtotime($r1["processed"]);
                            }
                            if ($r1["event"] == "open") {
                                $item["cantidad_abierto"]++;
                                $item["abierto"] = strtotime($r1["processed"]);
                            }
                        }
                        //print_h($item);
                        $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $r["_id"]], $item);
                        $procesados++;
                    } else {
                        $item["detalleError"] = "Sin detalle en reporte sendgrid";
                        $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $r["_id"]], $item);
                        $sindatos++;
                    }
                }
            } else {
                $item["detalleError"] = "No enviado a sendgrid";
                $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $r["_id"]], $item);
                $rechazosg++;
            }
        }
    } else {
        $item["detalleError"] = "No existe registro en alenvelopes";
        $act = $mongo3->actualizar("cbEnvioMails", ["_id" => $r["_id"]], $item);
        $sinalerts;
    }

    $i++;
}
print_h("Procesados " . $procesados);
print_h("Sin datos " . $sindatos);
print_h("Sin alerts " . $sinalerts);
print_h("Rechazo sendgrid " . $rechazosg);

print_h("Fin " . (date("d/m/Y H:i:s")));

echo "<script>window.setTimeout(()=>{window.location.reload();}, 2000);</script>";

echo "EJECUCION_COMPLETA";

?><?php
    //_FIN_DE_ARCHIVO 
    ?>