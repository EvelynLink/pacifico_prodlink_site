<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '3096M');

chdir(__DIR__);

require_once("/home/cnta/_configBasico.inc.php");
require_once("../functions/basic.php");
require_once("../comunes/classes/class.mymongodb.php");
require_once("../comunes/classes/class.API.php");
require_once("../sc/apis/class.scAPI.php");
require_once("../cobranza/apis/class.cbAPI.php");
require_once("../comunes/classes/class.myredisdb.php");
$miapi = new API();
$repClavePublica = 'k7F4llwzTyJADzi6eIYeyV6NhPH6Rs9SdFggN1jydC9DmfLnamJRY5kuwuqpZtNw';
$repClavePrivada = 'PJ|>Zjir2:cV7gAu?0Nt9=k*W2@SPo_IJZ=i!8gGu-X{lVwK?xLc45IiKM</=}Ft';
$metodo = "api_obtenerRCTelefonos";
$token = $miapi->generaToken($repClavePrivada, $metodo);
$arr = [
    "endPointHost" => "http://bdds.espaciolink.com",
    "clavePublica" => $repClavePublica,
    "token" => $token,
    "metodo" => $metodo,
    "identificador" => '0922019468'
];
$bd_datos = json_decode_from_utf8($miapi->comWS($arr));
$bd_datos = $bd_datos[0];
print_h($bd_datos);
exit;

if (!function_exists("ss_timing_start")) {

    function ss_timing_start($name = 'default') {
        global $ss_timing_start_times;
        $ss_timing_start_times[$name] = explode(' ', microtime());
    }

    function ss_timing_stop($name = 'default') {
        global $ss_timing_stop_times;
        $ss_timing_stop_times[$name] = explode(' ', microtime());
    }

    function ss_timing_current($name = 'default') {
        global $ss_timing_start_times, $ss_timing_stop_times;
        if (!isset($ss_timing_start_times[$name])) {
            return 0;
        }
        if (!isset($ss_timing_stop_times[$name])) {
            $stop_time = explode(' ', microtime());
        } else {
            $stop_time = $ss_timing_stop_times[$name];
        }
        // do the big numbers first so the small ones aren't lost
        $current = $stop_time[1] - $ss_timing_start_times[$name][1];
        $current += $stop_time[0] - $ss_timing_start_times[$name][0];
        return $current;
    }

    ss_timing_start();
}
$redis = new MYREDISDB();

$mongo1 = new MYMONGODB();

//require_once("../comunes/classes/class.API.php");
//$miapi = new API();
//$repClavePublica = 'k7F4llwzTyJADzi6eIYeyV6NhPH6Rs9SdFggN1jydC9DmfLnamJRY5kuwuqpZtNw';
//$repClavePrivada = 'PJ|>Zjir2:cV7gAu?0Nt9=k*W2@SPo_IJZ=i!8gGu-X{lVwK?xLc45IiKM</=}Ft';
//$metodo = "api_obtenerTelOperadorasSendudoGradoConsaguidad";
//$token = $miapi->generaToken($repClavePrivada, $metodo);
//$arr = [
//    "endPointHost" => "https://bdds.espaciolink.com",
//    "clavePublica" => $repClavePublica,
//    "token" => $token,
//    "metodo" => $metodo,
//    "identificador" => '1715552715'
//];
//$resultado = json_decode_from_utf8($miapi->comWS($arr));
//print_h($resultado);
//exit;
//$redis->delete('procesaIvestigacion3');
//exit;
resultados();
//actualizacion();
exit;

function actualizacion() {
    $redis = new MYREDISDB();
    $mongo = new MYMONGODB();
    $mongo3 = new MYMONGODB();
    $mongo4 = new MYMONGODB();
    $mongo5 = new MYMONGODB();

    $procesa = 0;
    $cantReg = 1000;
    $val = array();
    print_h("entra inicial");



    $cursor = $mongo->buscar('errores14', array(), array(), array());
    print_h('cantidad REGISTROS A PROCESAR ' . $cursor);
    $contador = 0;
    if ($cursor > 0) {

        print_h("entro");
        while ($row1 = $mongo->siguiente()) {
            print_h("servicio " . trim($row1['SERVICE']) . "     factura " . trim($row1['CUCFACT']));

            $condition = array(
                'cre_numServicio' => (string) trim($row1['SERVICE']),
                'cre_periodo' => (int) 14,
                'cre_inactivo' => (int) 1,
                'cre_factura' => (string) trim($row1['CUCFACT']));
            $cursor1 = $mongo3->buscar('cbCreditos', $condition);
            if ($cursor1 > 0) {

                $row = $mongo3->siguiente();
                print_h("entro a la cbpagos " . $row['pagos_cedula']);
//                exit;
                $contador++;
                $mongo5->actualizar('cbCreditos', array('_id' => new MongoId($row['id'])), array('$set' => array('cre_saldoCuota' => (double) $row1['VALOR_SIGECO'], 'cre_deudaInteres' => (double) $row1['VALOR_SIGECO'], 'cre_deudaNeta' => (double) $row1['VALOR_SIGECO'])));
            }
        }
        print_h("Total de regitros procesados" . $contador);
    }
}

function resultados() {
    $redis = new MYREDISDB();
    $mongo = new MYMONGODB();
    $mongo3 = new MYMONGODB();
    $mongo4 = new MYMONGODB();
    $mongo5 = new MYMONGODB();
    $mongo6 = new MYMONGODB();


    print_h("entra inicial");
    $criterio = array('procesado_Match' => array('$exists' => false), "cre_tieneFijo" => (int) 0, "cre_tieneMovil" => (int) 0);
    $cursor = $mongo->buscar('CNT_PRE_Carga_Deuda_Final', $criterio, array(), array('cre_deudaNeta' => -1), $cantReg);
    print_h('cantidad REGISTROS A PROCESAR ' . $cursor);
    if ($cursor > 0) {
        while ($row = $mongo->siguiente()) {
            $cedula = $row["cre_cedula"];
            $criterioCedula = array("cre_cedula" => $cedula);
            $cursorMatch = $mongo4->buscar('CNT_PRE_Carga_ResultadoInvestigacion', $criterioCedula, array(), array('cre_deudaNeta' => -1));
            if ($cursorMatch > 0) {
                print_h("actualizo cliente");
                $row1 = $mongo4->siguiente();
                $mongo5->actualizar('CNT_PRE_Carga_ResultadoInvestigacion', array('_id' => new MongoId($row1['id'])), array('$set' => array('procesado_Match' => (int) 1, 'fechaMatch' => (int) intval(time()))));
                $cursorPagado = $mongo6->buscar('cbCreditos', $criterioCedula, array(), array());
                if ($cursorPagado > 0) {
                    print_h("actualizo pago");
                    $row2 = $mongo6->siguiente();
                    $mongo5->actualizar('CNT_PRE_Carga_ResultadoInvestigacion', array('_id' => new MongoId($row1['id'])), array('$set' => array('cre_pagado' => (int) $row2['cre_pagado'])));
                }
            }

            $mongo3->actualizar('CNT_PRE_Carga_Deuda_Final', array('_id' => new MongoId($row['id'])), array('$set' => array('procesado_Match' => (int) 1)));
        }
    }
}

?><?

//_FIN_DE_ARCHIVO ?>