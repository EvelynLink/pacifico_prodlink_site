<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
$fecprocesado = time();
if ($origen == "") { //si es ejecutado por servicio
    chdir(__DIR__);
    require_once("/home/portcollqa/_configBasico.inc.php");
} else { //si es ejecutado por browser
    require_once("../comunes/top.inc.php");
    $baseUrl = BASEURL;
}
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '2048M');
require_once("../comunes/classes/class.mymongodb.php");
require_once("../comunes/classes/class.clase.php");
require_once("../sc/apis/class.scAPI.php");

$regitraPago = (isset($argv[2])) ? $argv[2] : 0;
$carteraId = (isset($argv[1])) ? $argv[1] : '';
echo 'Procesa Pago (' . $regitraPago . ') - Cartera (' . $carteraId . ')    ';
if (($carteraId == '' || intval($carteraId) < 2) && $regitraPago == 0) {
    die('Debe especificar una cartera');
}
$scapi = new scAPI(); //instancio el API
$mongo = new MYMONGODB();
$mongo3 = new MYMONGODB();
$mongoCRM = new MYMONGODB();

//$cartera='30';
//$fecprocesado=1510760683;
//goto procesaPagos;

$condition = array(
    array('$match' => array('cbConf_tipo' => 'tr_MORA_CARTERA')),
    array('$lookup' =>
        array('from' => 'cbTramos', 'localField' => 'cbConf_cbTramosId', 'foreignField' => '_id', 'as' => 'datos'))
);
$mongo->agregar('cbConfig', $condition);
$tramo_bd=[];
while($t=$mongo->siguiente()){
    $tramo_bd[]=$t;
}
//$tramo_bd = $tramo_bd['result'];
$condition = array(
    array('$match' => array('cbConf_tipo' => 'tr_SALDO_CARTERA')),
    array('$lookup' =>
        array('from' => 'cbTramos', 'localField' => 'cbConf_cbTramosId', 'foreignField' => '_id', 'as' => 'datos'))
);
 $mongo->agregar('cbConfig', $condition);
$tramo_bdSaldo=[];
while($t=$mongo->siguiente()){
    $tramo_bdSaldo[]=$t;
}

//print_h($tramo_bd);
//print_h($tramo_bdSaldo);
//exit;
$controlCI = '';
$fecprocesa = strtotime(date('Ymd', time()));
$cursor = $mongo->agregar("cbCargaEtlTablaAmortizacion", [], ['tamEtl_carteraId' => (string) $carteraId, "tamEtl_procesado" => (int) 0, "tamEtl_estado" => (int) 1], ['tamEtl_cedula' => 1, 'tamEtl_factura' => 1, 'tamEtl_diasMora' => -1]);
$monto = $intM = $gtoC = $cuotaAtrazada = 0;
$losdiasMora = -1;
$cartera = '';
$carteraNom = '';
$laci = '';
$lafac = '';
while ($row = $mongo->siguiente()) {
//    print_h($row);
//    die();
    $cedula = trim($row['tamEtl_cedula']);
    $diasMora = intval($row['tamEtl_diasMora']);
//    if (intval($row['tamEtl_diasMora']) == 0) {
//        $y = ($fecprocesa - strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaVenc']->sec))) / (60 * 60 * 24);
//        $y = abs($y);
//        $diasMora = intval($y);
//    }
//    if (strlen($cedula) == 9 || strlen($cedula) == 12) {
//        $cedula = '0' . $cedula;
//    }

    if ($controlCI == '') {
        $co = 1;
        if (intval($regitraPago) == 1) {
            $co = $fecprocesado;
        }
        $mongo3->actualizar('cbTablaAmortizacion', ['tam_DocPagado' => '', 'tam_carteraId' => (string) $carteraId, 'tam_inactivo' => (int) 0], ['tam_inactivo' => (int) $co]);
        $controlCI = trim($cedula . $row['tamEtl_factura']);
        $laci = $cedula;
        $lafac = $row['tamEtl_factura'];
    }
    $lafecVenc=0;
//    print_H('antes');
    if ($controlCI != trim($cedula . $row['tamEtl_factura'])) {
//        print_H('entra if');
        $mongo3->buscar('cbCreditos', ['cre_carteraId' => (string) $carteraId,  'cre_cedula' => (string) $laci, 'cre_factura' => (string) $lafac]);
        $row3 = $mongo3->siguiente();
//        $cartera = $row3['cre_carteraId'];
        $carteraNom = $row3['cre_nombreCartera'];
        $mongo3->actualizar('cbCreditos', ['cre_cedula' => (string) $laci, 'cre_factura' => (string) $lafac], [
            'cre_fechaVencimiento' => $lafecVenc,
            'cre_diasMoraFactura' => (int) $losdiasMora,
            'cre_tramoMora' => tramoMora($tramo_bd, $losdiasMora, $carteraId),
            'cre_tramoSaldo' => tramoSaldo($tramo_bdSaldo, $monto, $carteraId),
            'cre_cuotasAtrasadas' => (int) $cuotaAtrazada,
            'cre_interesMora' => (float) $intM,
            'cre_gastosCobranza' => (float) $gtoC,
            'cre_deudaNeta' => (float) $monto,
            'cre_pagado' => (int) 0,
            'cre_inactivo' => (int) 0,
            'cre_fechaActualizacion' => (int) time()
                ]
        );
        $controlCI = trim($cedula . $row['tamEtl_factura']);
        $monto = $intM = $gtoC = $cuotaAtrazada = 0;
        $losdiasMora = -1;
        if ($losdiasMora < $diasMora) {
            $losdiasMora = $diasMora;
//            $lafecVenc = strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaVenc']->sec));
            $lafecVenc=0;
            if(isset($row['tamEtl_fechaVenc'])){
                $lafecVenc =strtotime($row['tamEtl_fechaVenc']->toDateTime()->format('Y-m-d H:i:s'));
            } 
        }
        if ($row['tamEtl_diasMora'] >= 0) {
            $monto += $row['tamEtl_cuota'];
            $intM += $row['tamEtl_interesMora'];
            $gtoC += (isset($row['tamEtl_gastosCob'])) ? $row['tamEtl_gastosCob'] : 0;
            $cuotaAtrazada++;
        }
        $laci = $cedula;
        $lafac = $row['tamEtl_factura'];
    } else {
//        print_H('entra else');
        if ($losdiasMora < $diasMora) {
            $losdiasMora = $diasMora;
//            $lafecVenc = strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaVenc']->sec));
            $lafecVenc=0;
            if(isset($row['tamEtl_fechaVenc'])){
                $lafecVenc =strtotime($row['tamEtl_fechaVenc']->toDateTime()->format('Y-m-d H:i:s'));
            } 
        }
        if ($row['tamEtl_diasMora'] >= 0) {
            $monto += $row['tamEtl_cuota'];
            $intM += $row['tamEtl_interesMora'];
            $gtoC += (isset($row['tamEtl_gastosCob'])) ? $row['tamEtl_gastosCob'] : 0;
            $cuotaAtrazada++;
//            $lafecVenc = strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaVenc']->sec));
            $lafecVenc=0;
            if(isset($row['tamEtl_fechaVenc'])){
                $lafecVenc =strtotime($row['tamEtl_fechaVenc']->toDateTime()->format('Y-m-d H:i:s'));
            } 
        }
    }
//    $nombres = (isset($row['tamEtl_nombres']))?trim($row['tamEtl_nombres']):'';

    $data = [
        "tam_fechaConcesion" => (int) (isset($row['tamEtl_fechaConcesion'])) ? strtotime($row['tamEtl_fechaConcesion']->toDateTime()->format('Y-m-d H:i:s'))/*strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaConcesion']->sec))*/ : 0,
        "tam_valorOrig" => (float) (isset($row['tamEtl_valorOrig'])) ? $row['tamEtl_valorOrig'] : 0,
        "tam_totalCuotas" => (int) $row['tamEtl_totalCuotas'],
        "tam_fechaVenc" => (int) strtotime($row['tamEtl_fechaVenc']->toDateTime()->format('Y-m-d H:i:s')),//strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaVenc']->sec)),
        "tam_nroCuota" => (int) $row['tamEtl_nroCuota'],
        "tam_diasCuota" => (int) $row['tamEtl_diasCuota'],
        "tam_cuota" => (float) $row['tamEtl_cuota'],
        "tam_principal" => (float) (isset($row['tamEtl_principal'])) ? $row['tamEtl_principal'] : 0,
        "tam_interes" => (float) $row['tamEtl_interes'],
        "tam_saldo" => (float) $row['tamEtl_saldo'],
        "tam_fechaPago" => (int) ($row['tamEtl_fechaPago'] != '' && $row['tamEtl_fechaPago'] != 'NA') ? strtotime($row['tamEtl_fechaPago']->toDateTime()->format('Y-m-d H:i:s'))/*strtotime(date('Y-m-d H:i:s', $row['tamEtl_fechaPago']->sec))*/ : 0,
        "tam_diasMora" => (int) $diasMora,
        "tam_tasa" => (float) $row['tamEtl_tasa'],
        "tam_interesMora" => (float) $row['tamEtl_interesMora'],
        "tam_gastosCob" => (float) (isset($row['tamEtl_gastosCob'])) ? $row['tamEtl_gastosCob'] : 0,
        "tam_ivaGastosCob" => (float) $row['tamEtl_ivaGastosCob'],
        "tam_calificacion" => "",
        "tam_cxc" => (float) 0,
        "tam_DocPagado" => "",
        "tam_inactivo" => (int) 0,
        "tam_producto" => "",
        "tam_fechaActHist" => (int) 0,
        "tam_fecha" => (int) $fecprocesado
    ];
    $r = $mongo3->buscar('cbTablaAmortizacion', ["tam_cedula" => (string) $cedula, "tam_factura" => (string) $row['tamEtl_factura'], "tam_nroCuota" => (int) $row['tamEtl_nroCuota']]);
    if ($r == 0) {
        $en = $mongo3->buscar('cbCreditos', ['cre_cedula' => (string) $cedula]);
        if ($en > 0) {
            $row3 = $mongo3->siguiente();
        }
        $a = array_merge([
            "usUsuarios_id" => (int) $row3['usUsuarios_id'],
            "tam_cedula" => (string) $cedula,
            "tam_factura" => (string) $row['tamEtl_factura'],
            "tam_nombres" => $row3['cre_nombres'],
            "tam_apellidos" => $row3['cre_apellidos'],
            "tam_carteraId" => (string) $carteraId
                ], $data);

        $mongo3->guardar('cbTablaAmortizacion', $a);
    } else {
        $row3 = $mongo3->siguiente();
        $mongo3->actualizar('cbTablaAmortizacion', ["_id" => $row3['_id']], $data);
    }
    $mongo3->actualizar('cbCargaEtlTablaAmortizacion', ['_id' => $row['_id']], ['tamEtl_procesado' => (int) $fecprocesado]);
}
$mongo3->buscar('cbCreditos', ['cre_carteraId' => (string) $carteraId, 'cre_cedula' => (string) $laci, 'cre_factura' => (string) $lafac]);
$row3 = $mongo3->siguiente();
//$cartera = $row3['cre_carteraId'];
$carteraNom = $row3['cre_nombreCartera'];
//print_H($lafecVenc);
$mongo3->actualizar('cbCreditos', ['cre_cedula' => (string) $laci, 'cre_factura' => (string) $lafac], [
    'cre_fechaVencimiento' => $lafecVenc,
    'cre_diasMoraFactura' => (int) $losdiasMora,
    'cre_tramoMora' => tramoMora($tramo_bd, $losdiasMora, $carteraId),
    'cre_tramoSaldo' => tramoSaldo($tramo_bdSaldo, $monto, $carteraId),
    'cre_cuotasAtrasadas' => (int) $cuotaAtrazada,
    'cre_interesMora' => (float) $intM,
    'cre_gastosCobranza' => (float) $gtoC,
    'cre_deudaNeta' => (float) $monto,
    'cre_pagado' => (int) 0,
    'cre_inactivo' => (int) 0,
    'cre_fechaActualizacion' => (int) time()
        ]
);
procesaPagos:
if (intval($regitraPago) == 1) {
    $cant = $mongo->buscar('cbTablaAmortizacion', ['tam_carteraId' => (string) $carteraId, 'tam_inactivo' => (int) $fecprocesado]);
    if ($cant > 0) {
        while ($row = $mongo->siguiente()) {
            $pagos = array(
                "pagos_cedula" => (string) $row["tam_cedula"],
                "pagos_nombres" => (string) trim($row["tam_apellidos"] . ' ' . $row["tam_nombres"]),
                "pagos_carteraId" => $carteraId,
                "pagos_cartera" => $carteraNom,
                "pagos_usuarioCarga" => (int) intval(0),
                "pagos_usuarioCargaFecha" => (int) intval(0),
                "pagos_usuarioEdit" => (int) intval(0),
                "pagos_usuarioEditFecha" => (int) intval(0),
                "pagos_bandera" => "regularizado",
                "pagos_estado" => "CashManagement",
                "pagos_idPadre" => '',
                "pagos_agenteId" => (int) intval(0),
                "pagos_inactivo" => (int) intval(0),
                "pagos_numComprobante" => '',
                "pagos_numFactura" => $row["tam_factura"],
                "pagos_monto" => (float) floatval($row["tam_cuota"]),
                "pagos_fechaPago" => (int) intval($fecprocesado),
                "pagos_horaPago" => '',
                "pagos_tramoMora" => tramoMora($tramo_bd, $row["tam_diasMora"], $carteraId),
                "pagos_ultimo" => (int) intval(0),
                "pagos_tercero" => (int) intval(0));
//            $id = $mongo3->buscar('cbPagos', ["pagos_cedula" => (string) $row["tam_cedula"], "pagos_numFactura" => $row["tam_factura"], "pagos_monto" => (float) floatval($row["tam_cuota"])]);
//            if ($id == 0) {
            $id = $mongo3->guardar('cbPagos', $pagos);
            $mongo3->buscar('cbCreditos', [
                'cre_carteraId' => (string) $carteraId,
                'cre_cedula' => (string) $row['tam_cedula'],
                'cre_factura' => (string) $row["tam_factura"]
                    ], [], ['_id' => -1]);

            $r = $mongo3->siguiente();
            $monto = $r['cre_deudaNeta'] - $row["tam_cuota"];
            $abo = 1;
            $pag = 0;
            if ($monto <= 0) {
                $monto = 0;
                $abo = 0;
                $pag = 1;
            }
            $mongo3->actualizar('cbCreditos', ['_id' => $r['_id']], [
                'cre_gastosCobranza' => (float) 0,
                'cre_interesMora' => (float) 0,
                'cre_deudaOriginal' => (float) floatval($row["tam_cuota"]),
                'cre_deudaNeta' => (float) $monto,
                'cre_pagado' => (int) $pag,
                'cre_abono' => (int) $abo,
                'cre_inactivo' => (int) 0,
                'cre_fechaActualizacion' => (int) time()
            ]);
//            }
            $mongo3->actualizar('cbTablaAmortizacion', ['_id' => $row['_id']], ['tam_fechaPago' => $fecprocesado, 'tam_DocPagado' => $id, 'tam_inactivo' => (int) 0]);
        }
    }
}

function tramoMora($tramo_bd, $dmora, $cartera) {
    $trMora = '';
    foreach ($tramo_bd as $tr) {
        if (intval($cartera) == intval($tr['cbConf_id'])) {
            if ($tr['datos'][0]['tr_tramoInicio'] == 0 && $dmora >= $tr['datos'][0]['tr_tramoInicio'] && $dmora <= $tr['datos'][0]['tr_tramoFin']) {
                $trMora = $tr['datos'][0]['tr_tramo'];
            }
            if ($dmora > $tr['datos'][0]['tr_tramoInicio'] && $dmora <= $tr['datos'][0]['tr_tramoFin']) {
                $trMora = $tr['datos'][0]['tr_tramo'];
            }
            if ($dmora > $tr['datos'][0]['tr_tramoInicio'] && $tr['datos'][0]['tr_tramoFin'] == 999999) {
                $trMora = $tr['datos'][0]['tr_tramo'];
            }
            if ($dmora < $tr['datos'][0]['tr_tramoFin'] && $tr['datos'][0]['tr_tramoInicio'] == 999999) {
                $trMora = $tr['datos'][0]['tr_tramo'];
            }
            if ($trMora != '') {
                break;
            }
        }
    }
    return $trMora;
}

function tramoSaldo($tramo_bd, $saldo, $cartera) {
    $trSaldo = '';
    foreach ($tramo_bd as $tr) {
        if (intval($cartera) == intval($tr['cbConf_id'])) {
            if ($tr['datos'][0]['tr_tramoInicio'] == 0 && $saldo >= $tr['datos'][0]['tr_tramoInicio'] && $saldo <= $tr['datos'][0]['tr_tramoFin']) {
                $trSaldo = $tr['datos'][0]['tr_tramo'];
            }
            if ($saldo > $tr['datos'][0]['tr_tramoInicio'] && $saldo <= $tr['datos'][0]['tr_tramoFin']) {
                $trSaldo = $tr['datos'][0]['tr_tramo'];
            }
            if ($saldo > $tr['datos'][0]['tr_tramoInicio'] && $tr['datos'][0]['tr_tramoFin'] == 999999) {
                $trSaldo = $tr['datos'][0]['tr_tramo'];
            }
            if ($saldo < $tr['datos'][0]['tr_tramoFin'] && $tr['datos'][0]['tr_tramoInicio'] == 999999) {
                $trSaldo = $tr['datos'][0]['tr_tramo'];
            }
            if ($trSaldo != '') {
                break;
            }
        }
    }
    return $trSaldo;
}

echo "EJECUCION_COMPLETA";
//exit;
?><? //_FIN_DE_ARCHIVO                                                                                                          ?>
 