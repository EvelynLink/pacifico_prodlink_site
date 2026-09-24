<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//require_once "../comunes/top.inc.php";
$origen = "";
//ini_get 
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

if ($origen == "") { //si es ejecutado por servicio
    chdir(__DIR__);
    require_once("/home/pacifico/_configBasico.inc.php");
    //	require_once(BASEFOLDER."comunes/classes/class.mysqldb.php");
    //        require_once(BASEFOLDER."comunes/classes/class.mymongodb.php");
    //	require_once(BASEFOLDER."functions/basic.php");
    //        require_once(BASEFOLDER."comunes/classes/class.clase.php");
} else { //si es ejecutado por browser
    require_once("../comunes/top.inc.php");
    $baseUrl = BASEURL;
}
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once "../comunes/classes/class.coCompleteAngular.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once("../comunes/classes/class.clase.php");
require_once("../usuarios/classes/class.usuario.php");
require_once("../usuarios/classes/class.usTelf.php");
require_once("../usuarios/classes/class.usDireccion.php");
require_once("../cobranza/classes/class.cobCartera.php");
// require_once("../functions/sha256.inc.php");
//ini_set("max_execution_time", "290004390000");
//ini_set('memory_limit', '2000M');
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '4096M');
// $idCartera = '2119';
$categoriaCarga = $argv[1];
switch ($categoriaCarga) {
    case "Activacion":
        $coleccionDeCarga = "cbCargaActivacionEtl";
        break;
    default:
        $coleccionDeCarga = "cbCargaEtl";
        break;
}
$idCartera = $argv[2];
$cargaInicial = isset($argv[3]) ? intval($argv[3]) : '';    // valor 1 para carga inicial y no registre fecha de pago
$mongo2 = new MYMONGODB();
$inactiva = 'N';
// if (isset($argv[3]) && $argv[3] != '') {
//     $inactiva = $argv[3];
// }
//if($inactiva!='S'&&$idCartera==39){
//        $mongo2->actualizar('cbCreditos',['cre_carteraId'=>(string)$idCartera], ['cre_asignadoCargaId' =>'','cre_asignadoCargaNombre'=>'']);
//    }
echo "\ncoleccion de carga " . $coleccionDeCarga . "\n";
echo "\ncartera " . $idCartera . "\n";

procesaArchivo($idCartera, $inactiva, $cargaInicial, $coleccionDeCarga);

echo "\ntermino\n";

actCreditosMySQL($idCartera);
exit;

function procesaArchivo($idCartera, $inactiva, $cargaInicial, $coleccionDeCarga)
{
    //API consulta datos en bdds
    $mongo33 = new MYMONGODB();
    $mongo = new MYMONGODB();
    $mongoCRM = new MYMONGODB();
    $mongoInicio = new MYMONGODB();
    $mongo1 = new MYMONGODB();
    $mongo2 = new MYMONGODB();
    $db = new MYSQLDB();
    echo "\ninicio\n";

    $tramo_bd_mora = [];
    $condition = array(
        array('$match' => array("tr_tipoTramo" => "MORA")),
        array(
            '$lookup' =>
            array('from' => 'cbConfig', 'localField' => '_id', 'foreignField' => 'cbConf_cbTramosId', 'as' => 'data')
        ),
        array('$unwind' => '$data'),
        array('$match' => array("data.cbConf_tipo" => 'tr_MORA_CARTERA')),
        array(
            '$lookup' =>
            array('from' => 'cbConfig', 'localField' => 'data.cbConf_cbTramosId', 'foreignField' => 'cbConf_cbTramosId', 'as' => 'data1')
        ),
        array('$unwind' => '$data1'),
        array('$match' => array("data1.cbConf_tipo" => 'tr_MORA_PRODUCTO'))
    );

    $mongo->agregar('cbTramos', $condition);
    while ($tr = $mongo->siguiente()) {
        unset($tr['_id']);
        unset($tr['data']['_id']);
        unset($tr['data']['cbConf_cbTramosId']);
        unset($tr['data1']['_id']);
        unset($tr['data1']['cbConf_cbTramosId']);
        $tramo_bd_mora[] = $tr;
    }

    $condition = array(
        array('$match' => array('cbConf_tipo' => 'tr_SALDO_CARTERA')),
        array(
            '$lookup' =>
            array('from' => 'cbTramos', 'localField' => 'cbConf_cbTramosId', 'foreignField' => '_id', 'as' => 'datos')
        )
    );
    $mongo->agregar('cbConfig', $condition);
    $tramo_bd_saldo = [];
    while ($t = $mongo->siguiente()) {
        $tramo_bd_saldo[] = $t;
    }

    require_once("../comunes/classes/class.API.php");
    $miapi = new API(); //instancio el API que necesito
    $clavePublica = 'k7F4llwzTyJADzi6eIYeyV6NhPH6Rs9SdFggN1jydC9DmfLnamJRY5kuwuqpZtNw';
    $clavePrivada = 'PJ|>Zjir2:cV7gAu?0Nt9=k*W2@SPo_IJZ=i!8gGu-X{lVwK?xLc45IiKM</=}Ft';
    //      *************************

    $provincias = [];
    $sql = $db->mkSQL("SELECT usProvincias_id,trim(usProvincias_nombre) usProvincias_nombre FROM usprovincias ");
    if ($db->query($sql)) {
        while ($row = $db->fetchRow()) {
            $provincias[strtoupper($row['usProvincias_nombre'])] = $row['usProvincias_id'];
        }
    }

    global $lang;
    $usuario = new Usuario();
    $cartera = new cobCartera();
    $cantidadT = 0;

    $condicion = ["carteraEtl_procesado" => (int) 999, "carteraEtl_estado" => (int) 1, "carteraEtl_carteraId" => strval($idCartera)];
    $curT = $mongoInicio->buscar($coleccionDeCarga, $condicion);
    $rr = $mongoInicio->siguiente();
    $feAr = explode('_', $rr['carteraEtl_nombreArchivo'])[2];
    $fecarPago = strtotime(date('Y', strtotime(date(substr($feAr, 2, 2) . "-" . substr($feAr, 0, 2) . "-Y 00:00:00") . "- 1 days")) . '-' . substr($feAr, 0, 2) . '-' . date('d', strtotime(date(substr($feAr, 2, 2) . "-" . substr($feAr, 0, 2) . "-Y 00:00:00") . "- 1 days")));

    $fecarga = strtotime(date('Y') . '-' . substr($feAr, 0, 2) . '-' . substr($feAr, 2, 2));
    if ($cargaInicial == 1) {
        $fecarPago = $fecarga;
    }

    echo 'fecha carga para pagos ' . date('d-m-Y', $fecarPago) . "\n";
    // exit;
    // $lper = $mongo2->buscarDistinct('control_carga_periodo', ['activo' => (int) 1, 'cartera' => (int) $idCartera]);
    // $mongo2->actualizar('cbCreditos', ['cre_fechaPeriodo' => ['$nin' => $lper]], ['cre_inactivo' => (int) 1]);
    $mongo2->actualizar('cbCreditos', ['cre_carteraId' => (string) $idCartera, 'cre_inactivo' => (int) 0], ['cre_inactivo' => (int) 1]);

    $db->query($db->mkSQL("update cbcreditos set cre_inactivo=%N where cre_carteraId=%Q", 1, $idCartera));

    $listaPeriodos = [];

    inicio:

    $correct = [];
    $retVal = "";
    $fechainiciounix = 0;
    if (isset($filefechas[0])) {
        $fechainicio = $filefechas[0];
    }
    $definitive_dir = BASEFOLDER . "carga/files/";
    $numReg = 0;
    $datosEnviar = array();
    $i = 0;
    $correct = true;
    //carga tramos de mora y saldo



    $count = 0;

    //******
    $lin = 1;

    $coleccion = '';
    $condicion = [];
    $coleccion = $coleccionDeCarga;
    $condicion = ["carteraEtl_procesado" => (int) 999, "carteraEtl_estado" => (int) 1, "carteraEtl_carteraId" => strval($idCartera)];
    $cur = $mongoInicio->buscar($coleccion, $condicion, [], [], 1000);
    echo "\ncantidad procesada " . $cantidadT . " de " . $curT . "\n";
    $cantidadT += $cur;
    $tInicio = time();
    while ($row = $mongoInicio->siguiente()) {
        // $tInicio = time();
        $existeMongo = array();
        $nombres = "";
        $apellidos = "";
        $usUsuariosid = 0;
        $provincia = "";
        $provinciaId = 0;
        $ciudad = "";
        $canton = "";
        $parroquia = "";
        $region = "";
        $barrio = "";
        $referencia_domicilio = "";
        $direccion = "";
        $direccion1 = "";
        $direccion2 = "";
        $telefono1 = "";
        $telefonos = "";
        //        $idCartera = 34;
        $tiprel = "";
        $idDireccionRefer = 0;
        $idDireccion = 0;
        $idTel1 = 0;
        $idTel2 = 0;
        $idDireccionRefer1 = 0;
        $idDireccionRefer2 = 0;
        $existeCampaña = false;
        $carteraGuardar = array();
        $telefonosaGuardarUsuarios = array();
        $telefonosaRef1Guardar = array();
        $telefonosaRef2Guardar = array();
        $direccionGuardarUsuario = array();
        $contactosGuardar = array();
        $contactosGuardar1 = array();
        $contactosGuardar2 = array();
        $creditoGuardar = array();
        $productoGuardar = array();
        $direccionGuardar2 = array();
        $direccionGuardar1 = array();
        $programacionGestion = array();
        $fechaDesembolso = "";
        $nombreCart = "";
        $numReg++;
        //            $dat = explode(";", $reg);
        //Bandera para  definir si se creaU o se actualiza usuario
        $creoUsuario = false;
        $creoMongo = false;
        $proceso = false;
        $inicia = true;
        // print_h($row);
        if (isset($row['carteraEtl_cedula']) || $row['carteraEtl_cedula'] != '') {
            //     actCreditosMySQL($idCartera);
            //     exit;
            // }
            $cedula = trim($row['carteraEtl_cedula']);
            $fecha_factura = 0;

            if (isset($row['carteraEtl_fechaFactura']) && $row['carteraEtl_fechaFactura'] != '' && $row['carteraEtl_fechaFactura'] != 'NA') {
                if ($row['carteraEtl_fechaFactura'] > 0) {
                    $fecha_factura = $row['carteraEtl_fechaFactura'];
                } else {
                    $fecha_factura = strtotime($row['carteraEtl_fechaFactura']->toDateTime()->format('Y-m-d H:i:s'));
                }
            }
            // $fecha_Vencimiento = 0;
            // if (isset($row['carteraEtl_fechaVencimiento']) && $row['carteraEtl_fechaVencimiento'] != '' && $row['carteraEtl_fechaVencimiento'] != 'NA') {
            //     if ($row['carteraEtl_fechaVencimiento'] > 0) {
            $fecha_Vencimiento = $row['carteraEtl_fechaVencimiento'];
            // } else {
            //     $fecha_Vencimiento = strtotime($row['carteraEtl_fechaVencimiento']->toDateTime()->format('Y-m-d H:i:s'));
            // }
            // }


            $ced = $cedula;
            //busca telefonos en bdds
            $bdds = 1;
            //        if (strtoupper(onlyChars($row['carteraEtl_tipoIdentidad'])) == 'NATURAL' || onlyChars($row['carteraEtl_tipoIdentidad']) == '' || onlyChars($row['carteraEtl_tipoIdentidad']) == 'NA') {
            //            if (strlen($cedula) == 13) {
            //                $ced = substr($cedula, 0, 10);
            //            }
            //        }
            $tipoCarga = 'INTEGRACION';
            if (isset($row['nombreArchivo'])) {
                $tipoCarga = 'COMPRA DE CARTERA';
            }
            // $row['carteraEtl_apellidoPaterno'] = '';
            // $row['carteraEtl_apellidoMaterno'] = '';
            $bd_datos = '';

            $nombres = onlyChars($row['carteraEtl_nombres']);
            // $nom = $ape = '';
            // if (strlen($cedula) == 10) {
            //     $n = explode(' ', $nombres);
            //     if (count($n) == 4) {
            //         $ape = $n[0] . " " . $n[1];
            //         $nom = $n[2] . " " . $n[3];
            //     } else {
            //         if (count($n) == 2) {
            //             $ape = $n[0];
            //             $nom = $n[1];
            //         }
            //         if (count($n) == 3) {
            //             $ape = $n[0] . " " . $n[1];
            //             $nom = $n[2];
            //         }
            //         if (count($n) > 4) {
            //             $si = 1;
            //             $ap1 = 1;
            //             $nom = '';
            //             $ape = '';
            //             $nap = '';
            //             $nop = '';
            //             $conectivo = 1;
            //             foreach ($n as $v) {
            //                 if (strlen($v) <= 3 && $ap1 <= 2) {
            //                     $nap .= " " . $v;
            //                     if (strlen(trim($nap)) <= 5 && strlen($v) <= 3) {
            //                         $ap1--;
            //                     }
            //                     if ($conectivo == 3) {
            //                         $conectivo = 1;
            //                         $ape .= $nap;
            //                         $nap = '';
            //                     } else {
            //                         $conectivo++;
            //                     }
            //                 } else if (strlen($v) >= 4 && $ap1 <= 2) {
            //                     $ape .= $nap . " " . $v;
            //                     $nap = '';
            //                 }
            //                 if (strlen($v) <= 3 && $ap1 >= 3) {
            //                     $nop .= " " . $v;
            //                 } else if (strlen($v) >= 4 && $ap1 >= 3) {
            //                     $nom .= $nop . " " . $v;
            //                     $nop = '';
            //                 }
            //                 $ap1++;
            //             }
            //         }
            //     }
            // } else {
            //     $nom = $nombres;
            // }
            // $nom = trim($nom); //onlyChars($dat[0]);
            // $nombres = $nom; //onlyChars($dat[0]);
            // $ape = trim($ape); //onlyChars($dat[1] . " " . $dat[2]);
            // $apellidos = $ape; //onlyChars($dat[1] . " " . $dat[2]);
            // if ($nom == '' && $ape == '') {


            $primerNombre = isset($row['carteraEtl_primerNombre']) ? trim(onlyChars($row['carteraEtl_primerNombre'])) : '';
            $segundoNombre = isset($row['carteraEtl_segundoNombre']) ? trim(onlyChars($row['carteraEtl_segundoNombre'])) : '';
            $apellidoPaterno = isset($row['carteraEtl_apellidoPaterno']) ? trim(onlyChars($row['carteraEtl_apellidoPaterno'])) : '';
            $apellidoMaterno = isset($row['carteraEtl_apellidoMaterno']) ? trim(onlyChars($row['carteraEtl_apellidoMaterno'])) : '';
            $nombres = $primerNombre . ' ' . $segundoNombre;
            $apellidos = $apellidoPaterno . " " . $apellidoMaterno;
            $usUsuariosid = creaoActualizaUsuario($cedula, $nombres, $apellidos);

            // echo $row['carteraEtl_factura'] . "\n";
            $provinciaId = 0;

            if (isset($row['carteraEtl_provincia']) && $row['carteraEtl_provincia'] != 'NA' && $row['carteraEtl_provincia'] != '') {
                $provincia = strtoupper(onlyChars($row['carteraEtl_provincia']));
                $provinciaId = (isset($provincias[$provincia])) ? $provincias[strtoupper($provincia)] : 0;
                if (!is_numeric($provinciaId)) {
                    $provinciaId = 0;
                }
            }
            $orden = time() . substr(microtime(), 2, 8);
            $cargo = "";
            $cargo = isset($row['carteraEtl_cargo']) ? $row['carteraEtl_cargo'] : '';
            $dir_emp = isset($row['carteraEtl_direccionEmpresa']) ? trim(onlyChars($row['carteraEtl_direccionEmpresa'])) : '';

            //        $telefono1 = expect_phone_EC(trim($row['TELEFONO']));
            //        print_h('antes de Procesar telefonos');
            //        print_h(time());
            $telefonos = [];
            if (isset($row['carteraEtl_telefono1']) && $row['carteraEtl_telefono1'] != '' && $row['carteraEtl_telefono1'] != 'NA') {
                $t2 = '';
                if (isset($row['carteraEtl_telefono2']) && $row['carteraEtl_telefono2'] != 'NA') {
                    $t2 = $row['carteraEtl_telefono2'];
                }
                $t3 = '';
                if (isset($row['carteraEtl_telefono3']) && $row['carteraEtl_telefono3'] != 'NA') {
                    $t3 = $row['carteraEtl_telefono3'];
                }
                $telefonos = array_unique(array($row['carteraEtl_telefono1'], $t2, $t3));
            }

            //        $telefonos = array_unique(array($row['carteraEtl_telefono1'], $row['carteraEtl_telefono2'], $row['carteraEtl_telefonoRef1']));
            if (isset($row['carteraEtl_telefonoLimpiar']) && $row['carteraEtl_telefonoLimpiar'] != '' && $row['carteraEtl_telefonoLimpiar'] != 'NA') {
                $t = obtenerTelf($row['carteraEtl_telefonoLimpiar']);
                $telefonos = array_unique(array_merge($telefonos, $t));
            }
            if (isset($row['carteraEtl_telefonoLimpiar2']) && $row['carteraEtl_telefonoLimpiar2'] != '' && $row['carteraEtl_telefonoLimpiar2'] != 'NA') {
                $t = obtenerTelf($row['carteraEtl_telefonoLimpiar2']);
                $telefonos = array_unique(array_merge($telefonos, $t));
            }
            foreach ($telefonos as $tel) {
                $idTel = 0;
                $teltmp = expect_phone_EC($tel);
                if (count($teltmp) != 0) {
                    $idTel = insertarTelefonos($usUsuariosid, "ususuarios", $teltmp[0], $teltmp[1], "");
                    $tipoTlf = '';
                    if ($teltmp[0] == '09') {
                        $tipoTlf = 'Movil';
                    } else {
                        $tipoTlf = 'Fijo';
                    }
                    $telefonosaGuardarUsuarios = array(
                        "tel_id" => (int) intval($idTel),
                        "tel_cedula" => (string) $cedula,
                        "tel_numero" => $tel,
                        "tel_tipo" => $tipoTlf,
                        "tel_observacion" => "",
                        "tel_equivocado" => (int) 0,
                        "tel_eliminado" => (int) 0,
                        "tel_origen" => "ETL",
                        "tel_titular" => (int) 1,
                        "tel_orden" => (int) intval($orden)
                    );
                    $telMng = insertaTelefonosMongo($cedula, $tel, $telefonosaGuardarUsuarios);
                } else {
                    $retVal .= "<br> El telefono " . $tel . " proporcionado por el deudor cedula: " . $cedula . " tiene problemas";
                }
            }
            //-----------Direcciones
            $ciudad = '';
            if (isset($row['carteraEtl_canton'])) {
                $ciudad = onlyChars($row['carteraEtl_canton']);
            }
            $canton = $ciudad;
            //        $canton = onlyChars($row['carteraEtl_canton']);
            $parroquia = '';
            if (isset($row['carteraEtl_parroquia'])) {
                $parroquia = onlyChars($row['carteraEtl_parroquia']);
            }
            $agencia = '';
            if (isset($row['carteraEtl_agencia'])) {
                $agencia = onlyChars($row['carteraEtl_agencia']);
            }
            $region = '';
            if (isset($row['carteraEtl_region'])) {
                $region = onlyChars($row['carteraEtl_region']);
            }
            $barrio = '';
            if (isset($row['carteraEtl_barrio'])) {
                $barrio = onlyChars($row['carteraEtl_barrio']);
            }
            $referencia_domicilio = '';
            if (isset($row['carteraEtl_referenciaDomicilio'])) {
                $referencia_domicilio = onlyChars($row['carteraEtl_referenciaDomicilio']);
            }
            $orden = time() . substr(microtime(), 2, 8);
            //$direccion1 = trim(onlyChars($row['carteraEtl_direccion']));
            $direccion1 = (isset($row['carteraEtl_direccion']) && $row['carteraEtl_direccion'] != '') ? trim(onlyChars($row['carteraEtl_direccion'])) : '';
            //                print_h($direccion1);      

            $direccion2 = (isset($row['carteraEtl_direccion1']) && $row['carteraEtl_direccion1'] != '') ? trim(onlyChars($row['carteraEtl_direccion1'])) : '';
            //                print_h($direccion2);
            //            if($row['carteraEtl_carteraId']!='39'){
            if ($direccion1 == $direccion2) {
                if (trim($direccion1) != "") {
                    $idDireccion = crearDirecciones($usUsuariosid, "ususuarios", $provinciaId, "Carga plugin class.pgCargaCartera", $barrio, $direccion1, "Direccion");
                    if (is_numeric($idDireccion)) {
                        $direccionGuardarUsuario[] = array(
                            "usUsuarios_id" => (int) $usUsuariosid,
                            "dir_cedula" => (string) $cedula,
                            "dir_nombres" => $nombres,
                            "dir_apellidos" => $apellidos,
                            "dir_car_id" => (string) $idCartera,
                            "dir_ref_interrelacionId" => (int) 0,
                            "dir_ref_usUsuarios_id" => (int) 0,
                            "dir_ref_tipo" => "",
                            "dir_ref_nombre" => "",
                            "dir_ref_apellido" => "",
                            "dir_ref_nombreCompleto" => "",
                            "dir_ref_cedula" => "",
                            "dir_ref_empTiempo" => "",
                            "dir_ref_empActividad" => "",
                            "dir_ref_empCargo" => "",
                            "dir_ref_empProfesion" => "",
                            'dir_id' => (int) intval($idDireccion),
                            'dir_direccion' => $direccion1,
                            "dir_direccionCallePrincipal" => "",
                            "dir_direccionTrasversal" => "",
                            "dir_direccionNumero" => "",
                            "dir_tipo" => "RESIDENCIA",
                            "dir_orden" => (int) intval($orden),
                            "dir_provincia" => $provincia,
                            "dir_ciudad" => $ciudad,
                            "dir_canton" => $canton,
                            "dir_parroquia" => $parroquia,
                            "dir_region" => $region,
                            "dir_barrio" => $barrio,
                            "dir_latitud" => 0,
                            "dir_longitud" => 0,
                            "dir_equivocada" => "",
                            "dir_referencia" => $referencia_domicilio,
                            "dir_tipoCarga" => (string) $tipoCarga
                        );
                    } else {
                        $retVal .= "<br> No se pudo crear la direccion1 en usdireccion del deudor " . $nombres . " " . $apellidos;
                    }
                }
            } else {
                if (trim($direccion1) != "") {
                    $idDireccion = crearDirecciones($usUsuariosid, "ususuarios", $provinciaId, "Carga plugin class.pgCargaCartera", $barrio, $direccion1, "Direccion");
                    if (is_numeric($idDireccion)) {
                        $direccionGuardarUsuario[] = array(
                            "usUsuarios_id" => (int) $usUsuariosid,
                            "dir_cedula" => (string) $cedula,
                            "dir_nombres" => $nombres,
                            "dir_apellidos" => $apellidos,
                            "dir_car_id" => (string) $idCartera,
                            "dir_ref_interrelacionId" => (int) 0,
                            "dir_ref_usUsuarios_id" => (int) 0,
                            "dir_ref_tipo" => "",
                            "dir_ref_nombre" => "",
                            "dir_ref_apellido" => "",
                            "dir_ref_nombreCompleto" => "",
                            "dir_ref_cedula" => "",
                            "dir_ref_empTiempo" => "",
                            "dir_ref_empActividad" => "",
                            "dir_ref_empCargo" => "",
                            "dir_ref_empProfesion" => "",
                            'dir_id' => (int) intval($idDireccion),
                            'dir_direccion' => $direccion1,
                            "dir_direccionCallePrincipal" => "",
                            "dir_direccionTrasversal" => "",
                            "dir_direccionNumero" => "",
                            "dir_tipo" => "RESIDENCIA",
                            "dir_orden" => (int) intval($orden),
                            "dir_provincia" => $provincia,
                            "dir_ciudad" => $ciudad,
                            "dir_canton" => $canton,
                            "dir_parroquia" => $parroquia,
                            "dir_region" => $region,
                            "dir_barrio" => $barrio,
                            "dir_latitud" => 0,
                            "dir_longitud" => 0,
                            "dir_equivocada" => "",
                            "dir_referencia" => $referencia_domicilio,
                            "dir_tipoCarga" => (string) $tipoCarga
                        );
                    } else {
                        $retVal .= "<br> No se pudo crear la direccion1 en usdireccion del deudor " . $nombres . " " . $apellidos;
                    }
                }

                if (trim($direccion2) != "") {
                    $idDireccion = crearDirecciones($usUsuariosid, "ususuarios", $provinciaId, "Carga plugin class.pgCargaCartera", $barrio, $direccion2, "Direccion2");
                    if (is_numeric($idDireccion)) {
                        $direccionGuardarUsuario[] = array(
                            "usUsuarios_id" => (int) $usUsuariosid,
                            "dir_cedula" => (string) $cedula,
                            "dir_nombres" => $nombres,
                            "dir_apellidos" => $apellidos,
                            "dir_car_id" => (string) $idCartera,
                            "dir_ref_interrelacionId" => (int) 0,
                            "dir_ref_usUsuarios_id" => (int) 0,
                            "dir_ref_tipo" => "",
                            "dir_ref_nombre" => "",
                            "dir_ref_apellido" => "",
                            "dir_ref_nombreCompleto" => "",
                            "dir_ref_cedula" => "",
                            "dir_ref_empTiempo" => "",
                            "dir_ref_empActividad" => "",
                            "dir_ref_empCargo" => "",
                            "dir_ref_empProfesion" => "",
                            'dir_id' => (int) intval($idDireccion),
                            'dir_direccion' => $direccion2,
                            "dir_direccionCallePrincipal" => "",
                            "dir_direccionTrasversal" => "",
                            "dir_direccionNumero" => "",
                            "dir_tipo" => "RESIDENCIA",
                            "dir_orden" => (int) intval(time() . substr(microtime(), 2, 8)),
                            "dir_provincia" => $provincia,
                            "dir_ciudad" => $ciudad,
                            "dir_canton" => $canton,
                            "dir_parroquia" => $parroquia,
                            "dir_region" => $region,
                            "dir_barrio" => $barrio,
                            "dir_latitud" => 0,
                            "dir_longitud" => 0,
                            "dir_equivocada" => "",
                            "dir_referencia" => "",
                            "dir_tipoCarga" => (string) $tipoCarga
                        );
                    } else {
                        $retVal .= "<br> No se pudo crear la direccion2 en usdireccion del deudor " . $nombres . " " . $apellidos;
                    }
                }
            }
            //        }
            //-----------Direcciones Fin
            //        print_h('despues de telefonos');
            //        print_h(time());
            //-----------------------
            $factura = "";
            $factura = $row['carteraEtl_factura'];
            if (!isset($factura) || $factura == null || $factura == 'null' || $factura == 'N/A' || trim($factura) == '') {
                $factura = $cedula;
            }

            $campania = "";
            $carte = array();
            if (!isset($row['carteraEtl_nombre'])) {
                $row['carteraEtl_nombre'] = '';
            }
            $idCartera = $row['carteraEtl_carteraId'];
            $nombreCart = $row['carteraEtl_carteraNombre'];

            $idCampania = 0;
            if (isset($row['carteraEtl_cadena'])) {
                if (trim($row['carteraEtl_cadena']) != "") {
                    $idCampania = $row['carteraEtl_cadena'];
                }
            }
            $plazo = 0;
            if (isset($row['carteraEtl_plazo'])) {
                $plazo = $row['carteraEtl_plazo'];
            }
            $saldo_cuota = 0;
            if (isset($row['carteraEtl_saldoCuota'])) {
                if (trim($row['carteraEtl_saldoCuota']) != "") {
                    if (strlen(trim($row['carteraEtl_saldoCuota'])) == 0) {
                        $saldo_cuota = 0;
                    } else {
                        $saldo_cuota = $row['carteraEtl_saldoCuota'];
                    }
                } else {
                    $saldo_cuota = 0;
                    //            $retVal.="<br> El archivo no contiene el valor del saldo de la cuota " . $nombres . " " . $apellidos;
                }
            }
            $tasa_interes_mora = 0;
            if (isset($row['carteraEtl_tasaInteresMora'])) {
                if (trim($row['carteraEtl_tasaInteresMora']) != "") {
                    $tasa_interes_mora = $row['carteraEtl_tasaInteresMora'];
                } else {
                    $tasa_interes_mora = 0;
                    //            $retVal.="<br> El archivo no contiene la tasa de interes de mora ";
                }
            }
            $interes = 0;
            if (isset($row['carteraEtl_interes'])) {
                if (trim($row['carteraEtl_interes']) != "") {
                    if (strlen(trim($row['carteraEtl_interes'])) == 0) {
                        $interes = 0;
                    } else {
                        $interes = $row['carteraEtl_interes'];
                    }
                } else {
                    $interes = 0;
                    //            $retVal.="<br> El archivo no contiene el monto de interes de la deuda ";
                }
            }
            $deuda_interes = 0;
            if (isset($row['carteraEtl_deudaMasInteres'])) {
                if (trim($row['carteraEtl_deudaMasInteres']) != "") {
                    if (strlen(trim($row['carteraEtl_deudaMasInteres'])) == 0) {
                        $deuda_interes = 0;
                    } else {
                        $deuda_interes = $row['carteraEtl_deudaMasInteres'];
                    }
                } else {
                    $deuda_interes = 0;
                    //            $retVal.="<br> El archivo no contiene el monto total de la deuda ";
                }
            }
            $pagos = 0;
            if (isset($row['carteraEtl_pagos'])) {
                if (trim($row['carteraEtl_pagos']) != "") {
                    $pagos = $row['carteraEtl_pagos'];
                } else {
                    $pagos = 0;
                    //            $retVal.="<br> El archivo no contiene los pagos del deudor  " . $nombres . " " . $apellidos;
                }
            }
            $deuda_neta = 0;
            if (trim($row['carteraEtl_deudaNeta']) != "") {
                $deuda_neta = floatval($row['carteraEtl_deudaNeta']);
            } else {
                $deuda_neta = 0;
                //            $retVal.="<br> El archivo no contiene el monto de la deuda neta  ";
            }
            $deuda_original = 0;
            if (isset($row['carteraEtl_deudaOriginal']) && trim($row['carteraEtl_deudaOriginal']) != "") {
                $deuda_original = floatval($row['carteraEtl_deudaOriginal']);
            } else {
                $deuda_original = 0;
                //            $retVal.="<br> El archivo no contiene el monto de la deuda neta  ";
            }
            //                print_h($deuda_neta);
            $asignacion = "";
            if (isset($row['carteraEtl_asignacion'])) {
                $asignacion = onlyChars($row['carteraEtl_asignacion']);
            }
            $tienda = "";
            if (isset($row['carteraEtl_nombre'])) {
                $tienda = onlyChars($row['carteraEtl_nombre']);
            }
            $agente = "";
            // $buscaAgente = [];
            // if (isset($row['carteraEtl_agente'])) {
            //     if (strlen($row['carteraEtl_agente']) == 8) {
            //         $row['carteraEtl_agente'] = '0' . $row['carteraEtl_agente'];
            //     }
            //     $buscaAgente = buscaUsuarioxCedula($row['carteraEtl_agente']);
            //     $agente = $buscaAgente['usUsuarios_id'];
            //     //$agente = onlyChars($row['carteraEtl_agente']);
            // }
            if ($agente == 'NA') {
                $agente = "";
            }
            // print_h($agente);
            $agenteNombre = "";
            //        $agente=0;
            if ($agente != "" || $agente > 0) {
                $agenteNombre = buscaUsuarioxId($agente);
            }
            //*******************    busca el tramo de saldo segun deuda_neta
            $tramo_saldo = 'SIN TRAMO';
            if (count($tramo_bd_saldo) > 0) {
                foreach ($tramo_bd_saldo as $tr) {
                    //        foreach ($tramo_bd as $tr) {
                    if (intval($idCartera) == intval($tr['cbConf_id'])) {
                        if ($tr['datos'][0]['tr_tramoInicio'] == 0 && $deuda_neta >= $tr['datos'][0]['tr_tramoInicio'] && $deuda_neta <= $tr['datos'][0]['tr_tramoFin']) {
                            $tramo_saldo = $tr['datos'][0]['tr_tramo'];
                        }
                        if ($deuda_neta > $tr['datos'][0]['tr_tramoInicio'] && $deuda_neta <= $tr['datos'][0]['tr_tramoFin']) {
                            $tramo_saldo = $tr['datos'][0]['tr_tramo'];
                        }
                        if ($deuda_neta > $tr['datos'][0]['tr_tramoInicio'] && $tr['datos'][0]['tr_tramoFin'] == 999999) {
                            $tramo_saldo = $tr['datos'][0]['tr_tramo'];
                        }
                        if ($deuda_neta < $tr['datos'][0]['tr_tramoFin'] && $tr['datos'][0]['tr_tramoInicio'] == 999999) {
                            $tramo_saldo = $tr['datos'][0]['tr_tramo'];
                        }
                    }
                }
            }
            $diasMoraFactura = 0;
            if (isset($row['carteraEtl_diasMora']) && $row['carteraEtl_diasMora'] > 0) {
                $diasMoraFactura = intval($row['carteraEtl_diasMora']);
            } else {
                $diasMoraFactura = 0;
                trigger_error("El archivo no contiene los días de mora de la factura  del deudor" . $row['carteraEtl_factura']);
            }
            //*******************    busca el tramo de saldo segun diasMoraFactura
            $tramomora = 'SIN TRAMO';
            $etapa = 0;
            if (isset($row['carteraEtl_etapa'])) {
                $etapa = $row['carteraEtl_etapa'];
            }
            // if (!isset($row['carteraEtl_calificacion'])) {
            //     $calificacion = "NA";
            // } else {
            //     $calificacion = $row['carteraEtl_calificacion'];
            // }

            if (!isset($row['carteraEtl_producto'])) {
                $producto = "NA";
            } else {
                $producto = onlyChars($row['carteraEtl_producto']);
            }

            $tr = calificacion($tramo_bd_mora, $diasMoraFactura, $producto, $idCartera);
            $calificacion = $tr['calificacion'];
            $etapa = $tr['etapa'];

            if (is_array($tr)) {
                if ($diasMoraFactura > 0) {
                    $a = $tr['ini'] . '-';
                    if ($tr['ini'] == 9999999) {
                        $a = '<';
                    }
                    $b = $tr['fin'];
                    if ($tr['fin'] == 9999999) {
                        $a = '';
                        $b = '>' . $tr['ini'];
                    }
                } else {
                    $tr = ['calificacion' => ''];
                    $a = '<';
                    $b = '0';
                }
            }

            $tramomora = $calificacion . ' : ' . $a . $b;

            $ultima_cuota = 0;
            if (isset($row['carteraEtl_ultimaCuotaDevengada'])) {
                if (trim($row['carteraEtl_ultimaCuotaDevengada']) != "") {
                    $ultima_cuota = $row['carteraEtl_ultimaCuotaDevengada'];
                } else {
                    $ultima_cuota = 0;
                }
            }
            $linea = "";
            if (isset($row['carteraEtl_linea'])) {
                $linea = onlyChars($row['carteraEtl_linea']);
            }
            $sublinea = "";
            if (isset($row['carteraEtl_sublinea'])) {
                $sublinea = onlyChars($row['carteraEtl_sublinea']);
            }
            $marca = "";
            if (isset($row['carteraEtl_marca'])) {
                $marca = onlyChars($row['carteraEtl_marca']);
            }
            $sexo = "";
            if (isset($row['carteraEtl_sexo'])) {
                $sexo = onlyChars($row['carteraEtl_sexo']);
            }
            $edad = "";
            if (isset($row['carteraEtl_edad'])) {
                $edad = $row['carteraEtl_edad'];
            }
            $rango_edad = "";
            if (isset($row['carteraEtl_rangoEdad'])) {
                $rango_edad = quickEncode($row['carteraEtl_rangoEdad'], true);
            }
            $tiprel = "";
            if (isset($row['carteraEtl_tiprel'])) {
                $tiprel = onlyChars($row['carteraEtl_tiprel']);
            }

            $perfil = "";
            $perfil = isset($row['carteraEtl_perfil']) ? onlyChars($row['carteraEtl_perfil']) : '';
            $ingresos = "";
            if (isset($row['carteraEtl_ingresos'])) {
                $ingresos = $row['carteraEtl_ingresos'];
            }
            $rango_ingresos = "";
            if (isset($row['carteraEtl_rangoIngresos'])) {
                $rango_ingresos = onlyChars($row['carteraEtl_rangoIngresos']);
            }
            $fecha_ultimomov = "";
            if (isset($row['carteraEtl_fecultimovcto'])) {
                if (trim($row['carteraEtl_fecultimovcto']) != "") {
                    $fec = explode("/", trim($row['carteraEtl_fecultimovcto']));
                    if (is_array($fec) && count($fec) > 1) {
                        $aaN = trim($fec[2]);
                        $mmN = trim($fec[1]);
                        $ddN = trim($fec[0]);
                        $fecha_ultimomov = strtotime($ddN . "-" . $mmN . "-" . $aaN);
                    }
                } else {
                    $fecha_ultimomov = "";
                }
            }
            $valor_cuota = 0;
            if (isset($row['carteraEtl_valorCuota'])) {
                if (trim($row['carteraEtl_valorCuota']) != "") {
                    $valor_cuota = $row['carteraEtl_valorCuota'];
                } else {
                    $valor_cuota = 0;
                }
            }
            $porcentaje_pagado = 0;
            if (isset($row['carteraEtl_porcentajePagado'])) {
                if (trim($row['carteraEtl_porcentajePagado']) != "") {
                    $porcentaje_pagado = $row['carteraEtl_porcentajePagado'];
                } else {
                    $porcentaje_pagado = 0;
                }
            }
            $fecha_ultimopago = "";
            if (isset($row['carteraEtl_ultimaFechaPago'])) {
                if (count($row['carteraEtl_ultimaFechaPago']) > 1) {
                    $row['carteraEtl_ultimaFechaPago'] = strtotime(date('Y-m-d H:i:s', $row['carteraEtl_ultimaFechaPago']->sec));
                } else {
                    $row['carteraEtl_ultimaFechaPago'] = 0;
                }
                if (trim($row['carteraEtl_ultimaFechaPago']) != "" && $row['carteraEtl_ultimaFechaPago'] != 'null') {
                    $fec = explode("/", trim($row['carteraEtl_ultimaFechaPago']));
                    if (is_array($fec)) {
                        $aaN = trim($fec[2]);
                        $mmN = trim($fec[1]);
                        $ddN = trim($fec[0]);
                        $fecha_ultimopago = strtotime($ddN . "-" . $mmN . "-" . $aaN);
                    }
                } else {
                    $fecha_ultimopago = "";
                }
            }

            //valido si el usuario esta creado en mongo
            $usUsuariosidHijo1 = 0;
            $usUsuariosidHijo2 = 0;
            $scInterrelacionesidHijo1 = 0;
            $scInterrelacionesidHijo2 = 0;
            $orden = time() . substr(microtime(), 2, 8);

            $fechaDesembolso = "";
            if (isset($row['carteraEtl_fechaDesembolso'])) {
                if (trim($row['carteraEtl_fechaDesembolso']) != "" && $row['carteraEtl_fechaDesembolso'] != 'null') {
                    $fec = explode("/", trim($row['carteraEtl_fechaDesembolso']));
                    if (is_array($fec) && count($fec) > 1) {
                        $aaN = trim($fec[2]);
                        $mmN = trim($fec[1]);
                        $ddN = trim($fec[0]);
                        $fechaDesembolso = strtotime($ddN . "-" . $mmN . "-" . $aaN);
                    }
                } else {
                    $fechaDesembolso = "";
                    $retVal .= "<br> El archivo no contiene la  fecha de  Desembolso  del deudor " . $nombres . " " . $apellidos;
                }
            }
            if ($factura != '') {
                $producto = "";
                $valor_detalle = 0;
                $productoGuardar[] = array(
                    'pro_linea' => $linea,
                    'pro_sublinea' => $sublinea,
                    'pro_marca' => $marca
                );
                $inactivo = 0;

                if (!isset($row['carteraEtl_producto'])) {
                    $producto = "NA";
                } else {
                    $producto = onlyChars($row['carteraEtl_producto']);
                }

                if (strpos($producto, 'REESTRUCTURACION') !== false && $diasMoraFactura > 0) {
                    $fecha_Vencimiento = strtotime(date("d-m-Y") . " - " . $diasMoraFactura . " day");
                }

                if (isset($row['carteraEtl_inactivo'])) {
                    $inactivo = trim($row['carteraEtl_inactivo']);
                }

                if ($inactivo == '' || $inactivo == 'NA' || $inactivo == 'N/A') {
                    $inactivo = 0;
                }
                $tcre = '';
                if (isset($row['carteraEtl_tipoCredito'])) {
                    $tcre = trim($row['carteraEtl_tipoCredito']);
                }
                $ope = '';
                if (isset($row['carteraEtl_operacion'])) {
                    $ope = trim($row['carteraEtl_operacion']);
                }
                $deudaOriginal = 0;
                if (isset($row['carteraEtl_deudaOriginal']) && $row['carteraEtl_deudaOriginal'] > 0) {
                    $deudaOriginal = $row['carteraEtl_deudaOriginal'];
                } else {
                    $deudaOriginal = $deuda_neta;
                }
                $saldo_capital = 0;
                if (!isset($row['carteraEtl_saldoCapital'])) {
                    $saldo_capital = 0;
                } else {
                    $saldo_capital = $row['carteraEtl_saldoCapital'];
                }
                $gastosCobranza = 0;
                if (!isset($row['carteraEtl_gastosCobranza'])) {
                    $gastosCobranza = 0;
                } else {
                    $gastosCobranza = $row['carteraEtl_gastosCobranza'];
                }
                $coutasAtrasadas = 0;
                if (!isset($row['carteraEtl_cuotasAtrasadas'])) {
                    $coutasAtrasadas = 0;
                } else {
                    $coutasAtrasadas = $row['carteraEtl_cuotasAtrasadas'];
                }

                $statusop = 0;
                if (isset($row['carteraEtl_status'])) {
                    $statusop = $row['carteraEtl_status'];
                }
                if (!isset($row['carteraEtl_interesMora'])) {
                    $saldo_interesMora = 0;
                } else {
                    $saldo_interesMora = $row['carteraEtl_interesMora'];
                }
                if (!isset($row['carteraEtl_infoAdicional'])) {
                    $saldo_infoAdicional = "NA";
                } else {
                    $saldo_infoAdicional = $row['carteraEtl_infoAdicional'];
                }

                $periodo = 0;
                if (!isset($row['carteraEtl_periodo'])) {
                    $periodo = 0;
                } else {
                    $periodo = $row['carteraEtl_periodo'];
                }

                $fechaPeriodo = 0;
                if (!isset($row['carteraEtl_fechaPeriodo'])) {
                    $fechaPeriodo = 0;
                } else {
                    $fechaPeriodo = intval($row['carteraEtl_fechaPeriodo']);
                }

                if ($fechaPeriodo > 0) {
                    $listaPeriodos[$periodo] = strtotime(date('Y-m-d 00:00:00', $fechaPeriodo));
                }

                $acreedor = '';
                if (isset($row['carteraEtl_acreedor'])) {
                    $acreedor = $row['carteraEtl_acreedor'];
                }
                $capitalVencido = 0;
                if (isset($row['carteraEtl_capitalVencido'])) {
                    $capitalVencido = $row['carteraEtl_capitalVencido'];
                }
                $interesVencido = 0;
                if (isset($row['carteraEtl_interesVencido'])) {
                    $interesVencido = $row['carteraEtl_interesVencido'];
                }
                $interesDesfazVencido = 0;
                if (isset($row['carteraEtl_interesDesfazVencido'])) {
                    $interesDesfazVencido = $row['carteraEtl_interesDesfazVencido'];
                }
                $moraVencido = 0;
                if (isset($row['carteraEtl_moraVencido'])) {
                    $moraVencido = $row['carteraEtl_moraVencido'];
                }
                $comisionCobranzasVencido = 0;
                if (isset($row['carteraEtl_comisionCobranzasVencido'])) {
                    $comisionCobranzasVencido = $row['carteraEtl_comisionCobranzasVencido'];
                }
                $rubrosVencido = 0;
                if (isset($row['carteraEtl_rubrosVencido'])) {
                    $rubrosVencido = $row['carteraEtl_rubrosVencido'];
                }
                $infoAdicional1 = '';
                if (isset($row['carteraEtl_infoAdicional1'])) {
                    $infoAdicional1 = $row['carteraEtl_infoAdicional1'];
                }
                $liberacionVencida = 0;
                if (isset($row['carteraEtl_liberacionVencido'])) {
                    $liberacionVencida = $row['carteraEtl_liberacionVencido'];
                }
                $capitalPendiente = 0;
                if (isset($row['carteraEtl_capitalPendiente'])) {
                    $capitalPendiente = $row['carteraEtl_capitalPendiente'];
                }
                $interesPendiente = 0;
                if (isset($row['carteraEtl_interesPendiente'])) {
                    $interesPendiente = $row['carteraEtl_interesPendiente'];
                }
                $interesDesfazPendiente = 0;
                if (isset($row['carteraEtl_interesDesfazPendiente'])) {
                    $interesDesfazPendiente = $row['carteraEtl_interesDesfazPendiente'];
                }
                $capitalVencido = 0;
                if (isset($row['carteraEtl_capitalVencido'])) {
                    $capitalVencido = $row['carteraEtl_capitalVencido'];
                }
                $interesVencido = 0;
                if (isset($row['carteraEtl_interesVencido'])) {
                    $interesVencido = $row['carteraEtl_interesVencido'];
                }
                $interesDesfazVencido = 0;
                if (isset($row['carteraEtl_interesDesfazVencido'])) {
                    $interesDesfazVencido = $row['carteraEtl_interesDesfazVencido'];
                }
                $moraVencido = 0;
                if (isset($row['carteraEtl_moraVencido'])) {
                    $moraVencido = $row['carteraEtl_moraVencido'];
                }
                $comisionCobranzasVencido = 0;
                if (isset($row['carteraEtl_comisionCobranzasVencido'])) {
                    $comisionCobranzasVencido = $row['carteraEtl_comisionCobranzasVencido'];
                }
                $rubrosVencido = 0;
                if (isset($row['carteraEtl_rubrosVencido'])) {
                    $rubrosVencido = $row['carteraEtl_rubrosVencido'];
                }
                $capitalPorVencer = 0;
                if (isset($row['carteraEtl_capitalPorVencer'])) {
                    $capitalPorVencer = $row['carteraEtl_capitalPorVencer'];
                }
                $interesPorVencer = 0;
                if (isset($row['carteraEtl_interesPorVencer'])) {
                    $interesPorVencer = $row['carteraEtl_interesPorVencer'];
                }
                $interesDesfazPorVencer = 0;
                if (isset($row['carteraEtl_interesDesfazPorVencer'])) {
                    $interesDesfazPorVencer = $row['carteraEtl_interesDesfazPorVencer'];
                }
                $moraPorVencer = 0;
                if (isset($row['carteraEtl_moraPorVencer'])) {
                    $moraPorVencer = $row['carteraEtl_moraPorVencer'];
                }
                $comisionCobranzasPorVencer = 0;
                if (isset($row['carteraEtl_comisionCobranzasPorVencer'])) {
                    $comisionCobranzasPorVencer = $row['carteraEtl_comisionCobranzasPorVencer'];
                }
                $rubrosPorVencer = 0;
                if (isset($row['carteraEtl_rubrosPorVencer'])) {
                    $rubrosPorVencer = $row['carteraEtl_rubrosPorVencer'];
                }
                $liberacionPorVencer = 0;
                if (isset($row['carteraEtl_liberacionPorVencer'])) {
                    $liberacionPorVencer = $row['carteraEtl_liberacionPorVencer'];
                }
                $totalPorVencer = 0;
                if (isset($row['carteraEtl_totalPorVencer'])) {
                    $totalPorVencer = $row['carteraEtl_totalPorVencer'];
                }
                $cuotasPagadas = 0;
                if (isset($row['carteraEtl_cuotasPagadas'])) {
                    $cuotasPagadas = $row['carteraEtl_cuotasPagadas'];
                }
                $cuotasPorVencer = 0;
                if (isset($row['carteraEtl_cuotasPorVencer'])) {
                    $cuotasPorVencer = $row['carteraEtl_cuotasPorVencer'];
                }

                $diasVencido = '';
                if (isset($row['carteraEtl_diasMora'])) {
                    $diasVencido = $row['carteraEtl_diasMora'];
                }
                $diaCorte = 0;
                if (isset($row['carteraEtl_diaCorte'])) {
                    $diaCorte = $row['carteraEtl_diaCorte'];
                }
                $oficialCreditoId = '';
                if (isset($row['carteraEtl_oficialCredito'])) {
                    $oficialCreditoId = $row['carteraEtl_oficialCredito'];
                }
                $asesorComercial = '';
                if (isset($row['carteraEtl_asesorComercial'])) {
                    $asesorComercial = $row['carteraEtl_asesorComercial'];
                }
                $oficialIngreso = '';
                if (isset($row['carteraEtl_oficialIngreso'])) {
                    $oficialIngreso = $row['carteraEtl_oficialIngreso'];
                }
                $ciudad = '';
                if (isset($row['carteraEtl_nombreCiudad'])) {
                    $ciudad = $row['carteraEtl_nombreCiudad'];
                }
                $concesionario = '';
                if (isset($row['carteraEtl_concesionario'])) {
                    $concesionario = $row['carteraEtl_concesionario'];
                }
                $tipoAuto = '';
                if (isset($row['carteraEtl_tipo'])) {
                    $tipoAuto = $row['carteraEtl_tipo'];
                }
                $claseAuto = '';
                if (isset($row['carteraEtl_clase'])) {
                    $claseAuto = $row['carteraEtl_clase'];
                }
                $sublinea = '';
                if (isset($row['carteraEtl_sublinea'])) {
                    $sublinea = $row['carteraEtl_sublinea'];
                }
                $motor = '';
                if (isset($row['carteraEtl_motor'])) {
                    $motor = $row['carteraEtl_motor'];
                }
                $dispositivoAuto = '';
                if (isset($row['carteraEtl_dispositivo'])) {
                    $dispositivoAuto = $row['carteraEtl_dispositivo'];
                }
                $numeroSolicitud = '';
                if (isset($row['carteraEtl_numeroSolicitud'])) {
                    $numeroSolicitud = $row['carteraEtl_numeroSolicitud'];
                }
                $actividad = '';
                if (isset($row['carteraEtl_actividad'])) {
                    $actividad = $row['carteraEtl_actividad'];
                }
                $tipoGarantia = '';
                if (isset($row['carteraEtl_tipoGarantia'])) {
                    $tipoGarantia = $row['carteraEtl_tipoGarantia'];
                }
                $ifiAsignada = '';
                if (isset($row['carteraEtl_ifiAsignada'])) {
                    $ifiAsignada = trim($row['carteraEtl_ifiAsignada']);
                }
                $estadoOperacion = '';
                if (isset($row['carteraEtl_estadoOpercion'])) {
                    $estadoOperacion = $row['carteraEtl_estadoOpercion'];
                }

                $fecha_ultimopago = 0;
                if (isset($row['carteraEtl_fechaUltimoPago'])) {
                    $fecha_ultimopago = $row['carteraEtl_fechaUltimoPago'];
                }
                $fGen = 0;
                if (isset($row['carteraEtl_fechaGeneracion'])) {
                    $fGen = $row['carteraEtl_fechaGeneracion'];
                }
                $finCrd = 0;
                if (isset($row['carteraEtl_fechaIniciaCredito'])) {
                    $finCrd = $row['carteraEtl_fechaIniciaCredito'];
                }
                $fmasVen = 0;
                if (isset($row['carteraEtl_fechaMasVencida'])) {
                    $fmasVen = $row['carteraEtl_fechaMasVencida'];
                }
                $tipoNegocio = '';
                if (isset($row['carteraEtl_tipoNegocio'])) {
                    $tipoNegocio = $row['carteraEtl_tipoNegocio'];
                }
                $ramo = '';
                if (isset($row['carteraEtl_ramo'])) {
                    $ramo = $row['carteraEtl_ramo'];
                }
                $codigoTipoAgente = '';
                if (isset($row['carteraEtl_codigoTipoAgente'])) {
                    $codigoTipoAgente = $row['carteraEtl_codigoTipoAgente'];
                }
                $codigoAsegurado = '';
                if (isset($row['carteraEtl_codigoAsegurado'])) {
                    $codigoAsegurado = $row['carteraEtl_codigoAsegurado'];
                }
                $poliza = '';
                if (isset($row['carteraEtl_poliza'])) {
                    $poliza = $row['carteraEtl_poliza'];
                }
                $endoso = '';
                if (isset($row['carteraEtl_endoso'])) {
                    $endoso = $row['carteraEtl_endoso'];
                }
                $facultativo = '';
                if (isset($row['carteraEtl_facultativo'])) {
                    $facultativo = $row['carteraEtl_facultativo'];
                }
                $fechaVigenciaDesde = '';
                if (isset($row['carteraEtl_fechaVigenciaDesde'])) {
                    $fechaVigenciaDesde = $row['carteraEtl_fechaVigenciaDesde'];
                }
                $fechaVigenciaHasta = '';
                if (isset($row['carteraEtl_fechaVigenciaHasta'])) {
                    $fechaVigenciaHasta = $row['carteraEtl_fechaVigenciaHasta'];
                }
                $anioDeuda = '';
                if (isset($row['carteraEtl_anioDeuda'])) {
                    $anioDeuda = $row['carteraEtl_anioDeuda'];
                }
                $valorCartera = 0;
                if (isset($row['carteraEtl_valorCartera'])) {
                    $valorCartera = $row['carteraEtl_valorCartera'];
                }
                $conducto = '';
                if (isset($row['carteraEtl_conducto'])) {
                    $conducto = $row['carteraEtl_conducto'];
                }
                $snGrupo = '';
                if (isset($row['carteraEtl_snGrupo'])) {
                    $snGrupo = $row['carteraEtl_snGrupo'];
                }
                $tipoAsegurado = '';
                if (isset($row['carteraEtl_tipoAsegurado'])) {
                    $tipoAsegurado = $row['carteraEtl_tipoAsegurado'];
                }
                $primaNeta = 0;
                if (isset($row['carteraEtl_primaNeta'])) {
                    $primaNeta = $row['carteraEtl_primaNeta'];
                }
                $correo = '';
                if (isset($row['carteraEtl_email'])) {
                    $correo = strtolower($row['carteraEtl_email']);

                    $rem = $mongo2->buscar('cbEmail', ['mail_cedula' => (string) $cedula, 'mail_email' => $correo]);
                    if ($rem == 0) {
                        $data = [
                            "mail_cedula" => (string) $cedula,
                            "mail_email" => $correo,
                            "mail_observacion" => "",
                            "mail_usUsuario_id" => (int) $usUsuariosid,
                            "mail_fecha" => (int) time(),
                            "mail_cedulaReferido" => "",
                            "mail_tipoCarga" => "",
                            "mail_tipoReferenciaPersona" => ""
                        ];
                        $mongo2->guardar('cbEmail', $data);
                    }
                }
                $pais = '';
                if (isset($row['carteraEtl_pais'])) {
                    $pais = $row['carteraEtl_pais'];
                }
                $canton = '';
                if (isset($row['carteraEtl_canton'])) {
                    $canton = $row['carteraEtl_canton'];
                }
                $nroPatente = '';
                if (isset($row['carteraEtl_nroPatente'])) {
                    $nroPatente = $row['carteraEtl_nroPatente'];
                }
                $anioVehiculo = '';
                if (isset($row['carteraEtl_anioVehiculo'])) {
                    $anioVehiculo = $row['carteraEtl_anioVehiculo'];
                }
                $anioVh = 0;
                if (isset($row['carteraEtl_anioVehiculo'])) {
                    $anioVh = $row['carteraEtl_anioVehiculo'];
                }
                $recargoPendiente = 0;
                if (isset($row['carteraEtl_recargoPendiente'])) {
                    $recargoPendiente = $row['carteraEtl_recargoPendiente'];
                }
                $recargoVencido = 0;
                if (isset($row['carteraEtl_recargoVencido'])) {
                    $recargoVencido = $row['carteraEtl_recargoVencido'];
                }
                $recargoPorVencer = 0;
                if (isset($row['carteraEtl_recargoPorVencer'])) {
                    $recargoPorVencer = $row['carteraEtl_recargoPorVencer'];
                }
                $color = '';
                if (isset($row['carteraEtl_color'])) {
                    $color = $row['carteraEtl_color'];
                }
                $placa = '';
                if (isset($row['carteraEtl_placa'])) {
                    $placa = $row['carteraEtl_placa'];
                }
                $valorFinanciar = 0;
                if (isset($row['carteraEtl_valorFinanciar'])) {
                    $valorFinanciar = $row['carteraEtl_valorFinanciar'];
                }
                $valorVehiculo = 0;
                if (isset($row['carteraEtl_valorVehiculo'])) {
                    $valorVehiculo = $row['carteraEtl_valorVehiculo'];
                }
                $tipoGarantiaDesc = '';
                if (isset($row['carteraEtl_tipoGarantiaDesc'])) {
                    $tipoGarantiaDesc = $row['carteraEtl_tipoGarantiaDesc'];
                }
                $valorDevolucion = 0;
                if (isset($row['carteraEtl_valorDevolucion'])) {
                    $valorDevolucion = $row['carteraEtl_valorDevolucion'];
                }
                $metaConsumo = 0;
                if (isset($row['carteraEtl_meta'])) {
                    $metaConsumo = $row['carteraEtl_meta'];
                }
                $porcentaje = 0;
                if (isset($row['carteraEtl_porcentaje'])) {
                    $porcentaje = $row['carteraEtl_porcentaje'];
                }
                $creditoGuardar = array(
                    'usUsuarios_id' => (int) $usUsuariosid,
                    'cre_cedula' => (string) $cedula,
                    'cre_nombres' => (string) trim($nombres),
                    'cre_apellidos' => (string) trim($apellidos),
                    'cre_primerNombre' => (string) trim($primerNombre),
                    'cre_segundoNombre' => (string) trim($segundoNombre),
                    'cre_apellidoPaterno' => (string) trim($apellidoPaterno),
                    'cre_apellidoMaterno' => (string) trim($apellidoMaterno),
                    'cre_carteraId' => (string) $idCartera,
                    'cre_nombreCartera' => (string) $nombreCart,
                    'cre_provincia' => $provincia,
                    'cre_canton' => $canton,
                    'cre_parroquia' => $parroquia,
                    "cre_factura" => (string) $factura,
                    "cre_campania" => $campania,
                    "cre_nombreTienda" => $tienda,
                    "cre_plazo" => $plazo,
                    "cre_fechaFactura" => $fecha_factura,
                    "cre_fechaVencimiento" => $fecha_Vencimiento,
                    "cre_saldoCuota" => (float) $saldo_cuota,
                    "cre_tasaInteresMora" => $tasa_interes_mora,
                    "cre_interes" => (float) $interes,
                    "cre_deudaInteres" => (float) $deuda_interes,
                    "cre_pago" => $pagos,
                    "cre_deudaNeta" => (float) $deuda_neta,
                    "cre_tramoSaldo" => $tramo_saldo,
                    "cre_diasMoraFactura" => (int) $diasMoraFactura,
                    "cre_tramoMora" => $tramomora,
                    "cre_ultimaCuota" => $ultima_cuota,
                    "cre_rangoEdad" => $rango_edad,
                    "cre_ingresos" => $ingresos,
                    "cre_rangoIngresos" => $rango_ingresos,
                    "cre_fechaultimoMov" => $fecha_ultimomov,
                    "cre_valorCuota" => (string) $valor_cuota,
                    "cre_porcentajePagado" => $porcentaje_pagado,
                    "cre_fechaUltimoPago" => (int) 0,
                    "cre_fechaDesembolso" => $fechaDesembolso,
                    "cre_nombreArchivo" => '',
                    "cre_pagado" => (int) 0,
                    "cre_abono" => (int) 0,
                    "cre_inactivo" => (int) 0,
                    "cre_periodo" => (int) $periodo,
                    "cre_fechaPeriodo" => (int) $fechaPeriodo,
                    "cre_productos" => $productoGuardar,
                    "cre_fecha" => (int) 0,
                    "cre_numServicio" => "0",
                    "cre_ride" => "",
                    "cre_fechaFinPeriodo" => (int) 0,
                    "cre_estadoGestion" => "",
                    "cre_asignadoCargaId" => (string) $agente,
                    "cre_asignadoCargaNombre" => (string) $agenteNombre,
                    "cre_asignadoActualId" => (int) 0,
                    "cre_asignadoActualNombre" => "",
                    'cre_tipoCredito' => (string) $tcre,
                    'cre_codigo' => (string) $ope,
                    'cre_etapa' => (int) $etapa,
                    'cre_saldoCapital' => $saldo_capital,
                    'cre_gastosCobranza' => $gastosCobranza,
                    'cre_cuotasAtrasadas' => $coutasAtrasadas,
                    'cre_interesMora' => $saldo_interesMora,
                    'cre_infoAdicional' => $saldo_infoAdicional,
                    'cre_producto' => $producto,
                    'cre_calificacion' => $calificacion,
                    'cre_acreedor' => $acreedor,
                    'cre_asignacionPrioridad' => (int) 0,
                    'cre_fechaActualizacion' => (int) 0,
                    'cre_agencia' => (string) isset($row['carteraEtl_agencia']) ? $row['carteraEtl_agencia'] : '',
                    'cre_capitalVencido' => (float) $capitalVencido,
                    'cre_interesVencido' => (float) $interesVencido,
                    'cre_interesDesfazVencido' => (float) $interesDesfazVencido,
                    'cre_moraVencido' => (float) $moraVencido,
                    'cre_comisionCobranzasVencido' => (float) $comisionCobranzasVencido,
                    'cre_rubrosVencido' => (float) $rubrosVencido,
                    'cre_liberacionVencido' => (float) $liberacionVencida,
                    'cre_infoAdicional1' => (string) $infoAdicional1,
                    'cre_capitalPendiente' => (float) $capitalPendiente,
                    'cre_interesPendiente' => (float) $interesPendiente,
                    'cre_interesDesfazPendiente' => (float) $interesDesfazPendiente,
                    'cre_capitalPorVencer' => (float) $capitalPorVencer,
                    'cre_interesPorVencer' => (float) $interesPorVencer,
                    'cre_interesDesfazPorVencer' => (float) $interesDesfazPorVencer,
                    'cre_moraPorVencer' => (float) $moraPorVencer,
                    'cre_comisionCobranzasPorVencer' => (float) $comisionCobranzasPorVencer,
                    'cre_rubrosPorVencer' => (float) $rubrosPorVencer,
                    'cre_liberacionPorVencer' => (float) $liberacionPorVencer,
                    'cre_totalPorVencer' => (float) $totalPorVencer,
                    'cre_cuotasPagadas' => (int) $cuotasPagadas,
                    'cre_cuotasPorVencer' => (int) $cuotasPorVencer,
                    'cre_diasVencido' => (int) $diasVencido,
                    'cre_diaCorte' => (int) $diaCorte,
                    'cre_oficialCreditoId' => (string) $oficialCreditoId,
                    'cre_asesorComercial' => (string) $asesorComercial,
                    'cre_oficialIngreso' => (string) $oficialIngreso,
                    'cre_ciudad' => (string) $region,
                    // 'cre_ciudad' => (string) isset($row['carteraEtl_ciudad']) ? $row['carteraEtl_ciudad'] : '',
                    'cre_concesionario' => (string) $concesionario,
                    'cre_tipoAuto' => (string) $tipoAuto,
                    'cre_claseAuto' => (string) $claseAuto,
                    'cre_motor' => (string) $motor,
                    'cre_dispositivoAuto' => (string) $dispositivoAuto,
                    'cre_numeroSolicitud' => (string) $numeroSolicitud,
                    'cre_actividad' => (string) $actividad,
                    'cre_tipoGarantia' => (string) $tipoGarantia,
                    'cre_ifiAsignada' => (string) $ifiAsignada,
                    'cre_estadoOpercion' => (string) $estadoOperacion,
                    'cre_fechaGeneracion' => (int) $fGen,
                    'cre_fechaIniciaCredito' => (int) isset($row['carteraEtl_fechaIniciaCredito']) ? $row['carteraEtl_fechaIniciaCredito'] : 0,
                    'cre_fechaMasVencida' => (int) $fmasVen,
                    'cre_tipoNegocio' => (string) $tipoNegocio,
                    'cre_ramo' => (string) $ramo,
                    'cre_codigoTipoAgente' => (string) $codigoTipoAgente,
                    'cre_codigoAsegurado' => (string) $codigoAsegurado,
                    'cre_polizaCarga' => (string) $poliza,
                    'cre_endoso' => (string) $endoso,
                    'cre_facultativo' => (string) $facultativo,
                    'cre_fechaVigenciaDesde' => (int) isset($row['carteraEtl_fechaVigenciaDesde']) ? $row['carteraEtl_fechaVigenciaDesde'] : 0,
                    'cre_fechaVigenciaHasta' => (string) $fechaVigenciaHasta,
                    'cre_anioDeuda' => (string) $anioDeuda,
                    'cre_valorCartera' => (string) $valorCartera,
                    'cre_conducto' => (string) $conducto,
                    'cre_snGrupo' => (string) $snGrupo,
                    'cre_tipoAsegurado' => (string) $tipoAsegurado,
                    'cre_primaNeta' => (float) $primaNeta,
                    // 'cre_email' => (string) $correo,
                    'cre_pais' => (string) $pais,
                    'cre_nroPatente' => (string) $nroPatente,
                    'cre_linea' => (string) $linea,
                    'cre_sublinea' => (string) $sublinea,
                    'cre_marca' => (string) $marca,
                    'cre_anioVh' => (int) $anioVh,
                    'cre_recargoPendiente' => (float) $recargoPendiente,
                    'cre_recargoVencido' => (float) $recargoVencido,
                    'cre_recargoPorVencer' => (float) $recargoPorVencer,
                    'cre_color' => (string) $color,
                    'cre_placa' => (string) $placa,
                    'cre_valorFinanciar' => (float) $valorFinanciar,
                    'cre_valorVehiculo' => (float) $valorVehiculo,
                    'cre_tipoGarantiaDesc' => (string) $tipoGarantiaDesc,
                    'cre_valorDevolucion' => (float) $valorDevolucion,
                    'cre_meta' => (float) $metaConsumo,
                    'cre_porcentaje' => (float) $porcentaje,
                    'cre_status' => (int) $statusop,
                );
                $existeCredito = getCreditoFactura($idCartera, $cedula, $factura, $fechaPeriodo, $mongo2);

                if (count($existeCredito) > 0) {
                    $arr = [];
                    // if (!isset($existeCredito['cre_deudaOriginal'])) {
                    //     $arr['cre_deudaOriginal'] = $existeCredito['cre_deudaNeta'];
                    // } else {
                    //     if ($existeCredito['cre_deudaOriginal'] > 0) {
                    //         $arr['cre_deudaOriginal'] = $existeCredito['cre_deudaOriginal'];
                    //     }
                    // }
                    if (!isset($existeCredito['cre_cantidadGest']) || $existeCredito['cre_cantidadGest'] == 0) {
                        $arr['cre_cantidadGest'] = 0;
                    } else {
                        $arr['cre_cantidadGest'] = $existeCredito['cre_cantidadGest'];
                    }
                    $arr['cre_fechaActualizacion'] = (int) $tInicio;
                    $arr['cre_inactivo'] = (int) 0;
                    //ultima_cuota indica si es liquidación o no (1:liquidacion)
                    $arr = $creditoGuardar + $arr;
                    $conditionFact = array(
                        'cre_carteraId' => (string) $idCartera,
                        'cre_cedula' => (string) $cedula,
                        'cre_factura' => (string) $factura,
                        'cre_inactivo' => (int) 0,
                        // 'cre_fechaPeriodo' => (int) $fechaPeriodo
                    );
                    $nueva = $arr;
                    $r = $mongo2->actualizar('cbCreditos', $conditionFact, $nueva);
                    $actGest = actualizaGestion($idCartera, $cedula, $factura, $arr['cre_pagado'], $arr['cre_deudaNeta'], $arr['cre_inactivo']);
                    pasodetcargadiaria($row, $mongo2, 0);
                } else {

                    $creditoGuardar["cre_deudaNetaInicial"] = (float) $deuda_original;
                    $creditoGuardar["cre_deudaOriginal"] = (float) $deudaOriginal;
                    $creditoGuardar['cre_registroOperacion'] = (int) $fecarga;

                    $r = $mongo2->buscar('cbCreditos', ['cre_factura' => (string) $factura]);
                    if ($r > 0) {
                        while ($rrm = $mongo2->siguiente()) {
                            $id = $rrm['_id'];
                            unset($rrm['id']);
                            unset($rrm['_id']);
                            $mongo1->guardar('cbCreditos_hist_', $rrm);
                            //$mongo1->borrar('cbCreditos', ['_id' => $id]);
                            $conditionFact2 = array(
                                'cre_factura' => (string) $factura
                            );
                            $mongo1->actualizar('cbCreditos', $conditionFact2, $creditoGuardar);
                        }
                    } else {
                        $r = $mongo2->guardar('cbCreditos', $creditoGuardar);
                    }

                    pasodetcargadiaria($row, $mongo2, 1);
                }
            }
            $existe_idCampania = 0;
            $nombreCapania = "";
            foreach ($direccionGuardarUsuario as $val) {
                $criterio = array(
                    'dir_cedula' => (string) $cedula,
                    'dir_id' => (int) $val['dir_id']
                );
                $cu = $mongo1->buscar('cbDirecciones', $criterio);
                if ($cu == 0) {
                    $mongo1->guardar('cbDirecciones', $val);
                }
            }
            $existeMongo = existeUsurioenMongo($cedula);
            //******************************* inserta o actualiza
            if ($existeMongo[0] == 0) {
                $arrayMongo = array();
                $tipoIden = isset($row['carteraEtl_tipoIdentidad']) ? strtoupper(trim(onlyChars($row['carteraEtl_tipoIdentidad']))) : '';
                if ($tipoIden == '') {
                    $tipoIden = 'NATURAL';
                }
                $arrayMongo = array(
                    "usUsuarios_id" => (int) $usUsuariosid,
                    "crm_nombres" => $nombres,
                    "crm_apellidos" => $apellidos,
                    'crm_primerNombre' => (string) trim($primerNombre),
                    'crm_segundoNombre' => (string) trim($segundoNombre),
                    'crm_apellidoPaterno' => (string) trim($apellidoPaterno),
                    'crm_apellidoMaterno' => (string) trim($apellidoMaterno),
                    "crm_cedula" => $cedula,
                    "crm_tipoDocumento" => $tipoIden,
                    "crm_edad" => $edad,
                    "crm_perfil" => $perfil,
                    "crm_tiprel" => $tiprel,
                    "crm_cargo" => $cargo,
                    "crm_nacionalidad" => "",
                    "crm_profesion" => "",
                    "crm_residencia" => array(),
                    "crm_empresa" => array()
                );
                $coleccion = "CRM";
                $seProceso = creaoActualizaUsuarioMongo($coleccion, $cedula, $arrayMongo);
            }
            $i++;
            $lin++;
        }
        $mongo33->actualizar($coleccionDeCarga, ['_id' => $row['_id']], ['carteraEtl_procesado' => (int) 1]);
        $tFin = time();
        $diffTime = $tInicio - $tFin;
    }

    if ($cur == 1000) {
        sleep(2);
        goto inicio;
    }

    // echo "registrando pagos\n";
    // $mongo->buscar('cbCreditos', ['cre_fechaUltimoPago' => (int) $fecarPago]);
    // while ($cre = $mongo->siguiente()) {
    //     $efectividad = 0;
    //     $c = $mongo1->buscar('avProgramadas', ['av_cedula' => (string) $cre['cre_cedula'], "av_factura" => (string) $cre['cre_factura'], 'av_tipificacion.respuesta1' => 'CONTACTO DIRECTO', 'av_fechaGeneraLlamada' => ['$lt' => (int) $fecarPago], 'av_fechaPeriodo' => (int) $cre['cre_fechaPeriodo']]);
    //     if ($c > 0) {
    //         $efectividad = 1;
    //     }
    //     $pago = [
    //         "pagos_cedula" => $cre['cre_cedula'],
    //         "pagos_cuota" => (int) 0,
    //         "pagos_secuencia" => (int) 0,
    //         "pagos_numFactura" => (string) $cre['cre_factura'],
    //         "pagos_producto" => $cre['cre_producto'],
    //         "pagos_fechaVen" => (int) $cre['cre_fechaVencimiento'],
    //         'pagos_diasMoraFactura' => (int) $cre['cre_diasMoraFactura'],
    //         "pagos_formaPago" => '',
    //         "pagos_tipoPago" => '',
    //         "pagos_monto" => (float) $cre['cre_deudaNeta'],
    //         "pagos_capitalPagado" => (float) $cre['cre_deudaNeta'],
    //         "pagos_interesPagado" => (float) 0,
    //         "pagos_interesdesfazPagado" => (float) 0,
    //         "pagos_cobranzaPagado" => (float) 0,
    //         "pagos_rubrosPagado" => (float) 0,
    //         "pagos_liberacionPagado" => (float) 0,
    //         "pagos_moraPagado" => (float) 0,
    //         "pagos_recargoPagado" => (float) 0,
    //         "pagos_nombres" => trim($cre['cre_nombres'] . ' ' . $cre['cre_apellidos']),
    //         "pagos_numComprobante" => (string) $cre['cre_fechaUltimoPago'] . $cre['cre_factura'],
    //         "pagos_carteraId" => (string) $cre['cre_carteraId'],
    //         "pagos_cartera" => $cre['cre_nombreCartera'],
    //         "pagos_usuarioCarga" => (int) 0,
    //         "pagos_usuarioEdit" => (int) 0,
    //         "pagos_bandera" => "regularizado",
    //         "pagos_estado" => "CashManagement",
    //         "pagos_agenteId" => (int) 0,
    //         "pagos_ultimo" => (int) 0,
    //         "pagos_tercero" => (int) 0,
    //         "pagos_campaniaId" => (int) 0,
    //         "pagos_periodo" => (int) $cre['cre_periodo'],
    //         "pagos_fechaPeriodo" => (int) $cre['cre_fechaPeriodo'],
    //         "pagos_efectividad" => (int) $efectividad,
    //         "pagos_inactivo" => (int) 0,
    //         "pagos_servicios" => (int) 0,
    //         "pagos_usuarioCargaFecha" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_usuarioEditFecha" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_saldo" => (int) 0,
    //         "pagos_fechaPago" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_horaPago" => (string) date('H:i:s', $cre['cre_fechaUltimoPago']),
    //         "pagos_fechaPagoReg" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_horaPagoReg" => (string) date('H:i:s', $cre['cre_fechaUltimoPago']),
    //         "pagos_fechaPagoStr" => (string) date('Y-m-d H:i:s', $cre['cre_fechaUltimoPago']),
    //         "pagos_fechaPagoUT" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_fechaValor" => (int) $cre['cre_fechaUltimoPago'],
    //         "pagos_formaPagoDesc" => 'RECAUDACION'
    //     ];
    //     $mongo2->guardar('cbPagos', $pago);
    // }
    // echo "termino registro de pagos\n";

    foreach ($listaPeriodos as $key => $p) {
        $c = $mongo2->buscar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => (int) $p, 'activo' => (int) 1, 'cartera' => (int) $idCartera]);
        if ($c == 0) {
            $mongo2->actualizar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => ['$lt' => $p]], ['activo' => (int) 0]);
            $mongo2->guardar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => (int) $p, 'activo' => (int) 1, 'cartera' => (int) $idCartera, 'fechaFin' => (int) $p, 'fechaCarga' => (int) time()]);
        }
    }
    return $lin;
}

function pasodetcargadiaria($row, $mongo, $inicial)
{
    $fecha = strtotime(date('Y-m-d 00:00:00', time()));
    $fecStr = date('dmY', $fecha);
    $c = $mongo->buscar('cbCargaDetallePacifico', ['carga' => (string) $fecStr, 'factura' => (string) $row['carteraEtl_factura']]);
    if ($c == 0) {
        $a = [
            "cedula" => (string) $row['carteraEtl_cedula'],
            "primerNombre" => $row['carteraEtl_primerNombre'],
            "segundoNombre" => $row['carteraEtl_segundoNombre'],
            "apellidoPaterno" => $row['carteraEtl_apellidoPaterno'],
            "apellidoMaterno" => $row['carteraEtl_apellidoMaterno'],
            "factura" => (string) $row['carteraEtl_factura'],
            "carga" => (string) $fecStr,
            "cargaUT" => (int) $fecha,
            "inicial" => (int) $inicial,
            "deudaNeta" => (float) $row['carteraEtl_deudaNeta'],
            "saldoCapital" => (float) isset($row['carteraEtl_saldoCapital']) ? floatval($row['carteraEtl_saldoCapital']) : floatval(0),
            "diasMora" => (int) $row['carteraEtl_diasMora'],
            "fechaPeriodo" => (int) $row['carteraEtl_fechaPeriodo'],
            "periodo" => (int) $row['carteraEtl_periodo'],
            "producto" => $row['carteraEtl_producto'],
            "carteraId" => (int) $row['carteraEtl_carteraId'],
            "carteraNombre" => $row['carteraEtl_carteraNombre'],
        ];
        $mongo->guardar('cbCargaDetallePacifico', $a);
    }
}

function borrarUsuario($cedula)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("delete 
                            FROM ususuarios
                            WHERE usUsuarios_cedula=%Q", $cedula);

    $db->query($sql);
}

function prepararNumero($texto)
{
    //        $quitoPunto = str_replace(".", "", $texto);
    $remplazocComa = str_replace(",", ".", $texto);
    $remplazocComa = str_replace("$", "", $remplazocComa);
    return trim($remplazocComa);
    //         return number_format($remplazocComa,2,'.','');
}

function obtenerProvinciaId($nombre)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT usProvincias_id
                            FROM usprovincias
                            WHERE usProvincias_nombre LIKE %Q", "%" . ucfirst(strtolower($nombre)) . "%");
    if ($db->query($sql)) {

        $row = $db->fetchRow();
        //            print_h($row);
        return $row['usProvincias_id'];
    } else {
        //            print_h("entre else");
        return false;
    }
}

function crearDirecciones($relId, $reltable, $provinciaId, $tipoDireccion, $barrio, $direccion, $observacion = "")
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL(
        "SELECT * FROM usdireccion
		WHERE usDireccion_provinciaId=%N
		AND usDireccion_relTable=%Q
                AND usDireccion_relId=%N
		AND usDireccion_tipo=%Q
		AND usDireccion_barrio=%Q
                AND usDireccion_observacion=%Q
                AND usDireccion_callePrincipal=%Q",
        $provinciaId,
        $reltable,
        $relId,
        $tipoDireccion,
        $barrio,
        $observacion,
        $direccion
    );
    //        print_h($sql);
    $respuesta = $db->query($sql, 1, 0);
    if (!$respuesta) {
        $idDireccion = $db->query($db->mkSQL(
            "INSERT INTO usdireccion (usDireccion_provinciaId, 
                                                                    usDireccion_relTable,
                                                                    usDireccion_relId,
                                                                    usDireccion_tipo,
                                                                    usDireccion_barrio,
                                                                    usDireccion_observacion,
                                                                    usDireccion_callePrincipal
                                                    ) VALUES (%N, %Q,%N,%Q,%Q,%Q,%Q)",
            $provinciaId,
            $reltable,
            $relId,
            $tipoDireccion,
            $barrio,
            $observacion,
            $direccion
        ));
        return $idDireccion;
    } else {
        $row = $db->fetchRow();
        return $row['usDireccion_id'];
    }
}

function obtenerArraySimple($array1)
{
    $r = $array1;
    foreach ($array1 as $key => $value) {
        foreach ($value as $key2 => $value2) {
            if (is_array($value2)) {
                unset($r[$key][$key2]);
            }
        }
    }
    return $r;
}

function insertarTelefonos($relId, $relTable, $area, $telefono, $comentario)
{
    $db = new MYSQLDB();
    $respuesta = $db->query($db->mkSQL("SELECT * FROM ustelfs
		WHERE usTelfs_area=%Q
		AND usTelfs_telefono=%Q
		AND usTelfs_relId=%N
		AND usTelfs_relTable=%Q", $area, $telefono, $relId, $relTable), 1, 0);

    if (!$respuesta) {
        $sql = $db->mkSQL("INSERT INTO ustelfs (
			usTelfs_relId,usTelfs_relTable,usTelfs_area,usTelfs_telefono,
			usTelfs_comentario,usTelfs_ranking,usTelfs_titular
			) VALUES (
			%N,%Q,%Q,%Q,%Q,%N,%N
			)", $relId, $relTable, $area, $telefono, $comentario, 0, 1);
        return $db->query($sql);
    } else {
        $row = $db->fetchRow();
        $sql = $db->mkSQL("update ustelfs set usTelfs_titular=%N where usTelfs_id=%N", 1, $row['usTelfs_id']);
        $db->query($sql);
        return $row['usTelfs_id'];
    }
}

function insertaTelefonosMongo($cedula, $telefono, $data)
{
    $mongo = new MYMONGODB();
    $mongo1 = new MYMONGODB();
    $cursor = 0;
    $telf = 0;
    $condition = [
        'tel_cedula' => (string) $cedula,
        'tel_numero' => (string) $telefono
    ];
    $cursor = $mongo->buscar("cbTelefonos", $condition);
    if ($cursor == 0) {
        $telf = $mongo1->guardar('cbTelefonos', $data);
    } else {
        $telf = $mongo1->actualizar('cbTelefonos', $condition, ['tel_titular' => (int) 1, 'tel_fechaActualizacion' => time()]);
    }
    return $telf;
}

function guardarInterrelacion($usuarioId, $usuarioIdhijo, $tipo)
{
    //Funcion que guarda inter rrelaciones
    $existe = existeInterrrelacion($usuarioId, $usuarioIdhijo);
    if (!$existe) {
        $db = new MYSQLDB();

        $interrelacionId = $db->query($db->mkSQL("INSERT INTO scinterrrelaciones
            (scInterrrelaciones_padreId,scInterrrelaciones_hijoId,
             scInterrrelaciones_tipo) 
            VALUES 
            (%N,%N,%Q)", $usuarioId, $usuarioIdhijo, $tipo));
    } else {
        $interrelacionId = $existe;
    }
    return $interrelacionId;
}

function existeInterrrelacion($usuarioIdpadre, $usuarioIdhijo)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT scInterrrelaciones_id
                            FROM scinterrrelaciones
                            WHERE scInterrrelaciones_padreId=%N AND scInterrrelaciones_hijoId=%N", $usuarioIdpadre, $usuarioIdhijo);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['scInterrrelaciones_id'];
    } else {
        return false;
    }
}

function creaoActualizaUsuarioMongo($coleccion, $cedula, $arrayMongo)
{
    $mongo = new MYMONGODB();
    $cursor = $mongo->buscar($coleccion, array("crm_cedula" => (string) $cedula));
    if ($cursor == 0) {
        $idMongo = $mongo->guardar($coleccion, $arrayMongo);
        if ($idMongo == '') {
            return false;
        } else {
            return true;
        }
    } else {

        return true;
    }
}

function existeUsurioenMongo($cedula)
{
    $mongo = new MYMONGODB();
    //         $criterioAccion = array("usUsuarios_id" => (int) $usUsuariosid, "crm_cartera.1.car_id" =>  1);
    $criterioAccion = array("crm_cedula" => (string) $cedula);
    $coleccion = "CRM";
    $cursor = $mongo->buscar($coleccion, $criterioAccion);
    //        print_h($cursor);
    //        $documento = $mongo->siguiente();
    //        print_h($documento);
    return array($cursor, $mongo);
}

//IngresaArreglos del usuario principal


function searchArrayValueByKey(array $array, $search)
{
    if (isset($array[$search])) {
        return $array[$search];
    } else {
        return false;
    }
}

function searchArrayValueByValue(array $array, $search)
{
    $arrIt = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
    foreach ($arrIt as $sub) {
        $subArray = $arrIt->getSubIterator();
        if ($subArray['scRamas_id'] === $search) {
            $outputArray = iterator_to_array($subArray);
        }
    }

    return $outputArray;
}

function buscaCedula($cedula)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT usUsuarios_id
                            FROM ususuarios
                            WHERE usUsuarios_cedula=%Q", $cedula);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['usUsuarios_id'];
    } else {
        return 0;
    }
}

function buscaUsuarioxId($id)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT concat(usUsuarios_apellidos,' ',usUsuarios_nombres) as nombre
                            FROM ususuarios
                            WHERE usUsuarios_id =" . $id);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['nombre'];
    } else {
        return "";
    }
}

function buscaUsuarioxCedula($cedula)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT usUsuarios_id,concat(usUsuarios_apellidos,' ',usUsuarios_nombres) as nombre
                            FROM ususuarios
                            WHERE usUsuarios_cedula ='" . $cedula . "'");
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row;
    } else {
        return "";
    }
}

function validausuariosinCedula($nombes, $apellidos)
{
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT usUsuarios_id
                            FROM ususuarios
                            WHERE usUsuarios_nombres = %Q AND usUsuarios_apellidos=%Q AND usUsuarios_visibleBandera=%N", $nombes, $apellidos, 0);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['usUsuarios_id'];
    } else {
        return false;
    }
}

function creaoActualizaUsuario($cedula, $nombres, $apellidos)
{
    //function creaoActualizaUsuario($cedula, $nombres, $apellidos, $bandera) {
    $db = new MYSQLDB();
    // Inserta usuario relacion sin cedula JC
    //    if ($cedula == "") {
    //        $existe = validausuariosinCedula($nombres, $apellidos);
    //        if (!$existe) {
    //            $idUsuario = $db->query($db->mkSQL("
    //                                    INSERT INTO ususuarios
    //                                    (usUsuarios_activo,
    //                                    usUsuarios_cedula,
    //                                    usUsuarios_apellidos,
    //                                    usUsuarios_nombres,
    //                                    usUsuarios_pais,
    //                                    usUsuarios_idioma,
    //                                    usUsuarios_createdOn,
    //                                    usUsuarios_visibleBandera
    //                                    ) VALUES (%N,%Q,%Q,%Q,%Q,%Q,%N,%N)", 0, "", $apellidos, $nombres, "EC", "ES", time(), 0));
    //        } else {
    //            $idUsuario = $existe;
    //        }
    //        if (!is_numeric($idUsuario)) {
    //            $retVal.="<br>" . $lang["Usuario"] . " " . $nombres . " " . $apellidos . " " . ", con C.I.:" . $cedula . " " . $lang["contiene errores"];
    //        } else {
    //            return $idUsuario;
    //        }
    //    } else {
    //        if (!$bandera) {
    //Actualizo información de usuario
    $id = buscaCedula($cedula);
    if ($id > 0) {
        $db->query($db->mkSQL(
            "UPDATE ususuarios SET
                            usUsuarios_apellidos=%Q,
                            usUsuarios_nombres=%Q
                            WHERE usUsuarios_id=%N",
            trim(substr($apellidos, 0, 99)),
            trim(substr($nombres, 0, 99)),
            $id
        ));
        //        if (!is_numeric($updateUsuario)) {
        //            $retVal.="<br>" . $lang["Usuario"] . " " . $nombres . " " . $apellidos . " " . ", con C.I.:" . $cedula . " " . $lang["contiene errores"];
        //        } else {
        return $id;
        //        }
    } else {
        //creo nuevo usuario
        //se aumenta el campo relacion boolean JC
        $idUsuario = $db->query($db->mkSQL("
                                    INSERT INTO ususuarios
                                    (usUsuarios_activo,
                                    usUsuarios_cedula,
                                    usUsuarios_apellidos,
                                    usUsuarios_nombres,
                                    usUsuarios_pais,
                                    usUsuarios_idioma,
                                    usUsuarios_createdOn
                                    ) VALUES (%N,%Q,%Q,%Q,%Q,%Q,%N)", 1, $cedula, trim(substr($apellidos, 0, 99)), trim(substr($nombres, 0, 99)), "EC", "ES", time()));
        //trigger_error($idUsuario.' '.$cedula);
        //            if (!is_numeric($idUsuario)) {
        //                $retVal.="<br>" . $lang["Usuario"] . " " . $nombres . " " . $apellidos . " " . ", con C.I.:" . $cedula . " " . $lang["contiene errores"];
        //            } else {
        return $idUsuario;
        //            }
    }
    //    }
}

function busquedaCampaniaByNombre($nombre_campania)
{
    $db = new MYSQLDB();
    $ramas = array();
    $sql = $db->mkSQL("SELECT * FROM scramas WHere upper(scRamas_nombre)=%Q", strtoupper($nombre_campania));
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        $ramas = $row;
        return $ramas;
    } else {
        return NULL;
    }
}

function busquedaCampaniaById($id)
{
    $db = new MYSQLDB();
    $cartera = array();
    $sql = $db->mkSQL("SELECT  DISTINCT c.*, r.scRamas_id, r.scRamas_nombre FROM scramas r
            INNER JOIN cobcarteraramas cr on cr.cobCarteraRamas_ramaIdfk=r.scRamas_id
            INNER JOIN cobcartera c on cr.cobCarteraRamas_carteraIdfk=c.cobCartera_id
            WHere scRamas_id=%N", $id);
    //        print_h($sql);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        $cartera = $row;
        return $cartera;
    } else {
        return NULL;
    }
}

function limpiarCaracteresEspeciales($string)
{
    $string = htmlentities($string);
    $string = preg_replace('/\&(.)[^;]*;/', '\\1', $string);
    return $string;
}

function objToArray($obj, &$arr = array())
{
    if (!is_object($obj) && !is_array($obj)) {
        $arr = $obj;
        return $arr;
    }
    foreach ($obj as $key => $value) {
        if (!empty($value)) {
            $arr[$key] = array();
            objToArray($value, $arr[$key]);
        } else {
            $arr[$key] = $value;
        }
    }
    return $arr;
}

function findKey($array, $keySearch)
{
    global $resultado;
    foreach ($array as $key => $item) {

        if ($key == $keySearch) {
            if (isset($resultado)) {
                $resultado = array();
            }
            $resultado = $item;
        } else {
            if (!is_object($array[$key])) {
                if (isset($array[$key]))
                    findKey($array[$key], $keySearch);
            }
        }
    }
    return $resultado;
}

function encodeTemporal($text)
{
    $text = quickEncode($text, true);
    $isUTF8 = preg_match('//u', $text);
    if (!$isUTF8) {
        $textoLimpio = "";
        foreach (str_split($text) as $ch) {
            //                print_h(strtolower_utf8($ch)."=="."ñ");
            if (strtolower_utf8($ch) == "ñ") {
                //                    print_h("entre ñ");
                $textoLimpio .= $ch;
            } else {
                //                    print_h("no entre ñ");
                //                    $textoLimpio.=quickEncode($ch);
                if (preg_match('//u', $ch)) {
                    $textoLimpio .= $ch;
                }
            }
        }
        $text = $textoLimpio;
    }
    return $text;
}

function strtolower_utf8($cadena)
{
    $convertir_a = array(
        "a",
        "b",
        "c",
        "d",
        "e",
        "f",
        "g",
        "h",
        "i",
        "j",
        "k",
        "l",
        "m",
        "n",
        "o",
        "p",
        "q",
        "r",
        "s",
        "t",
        "u",
        "v",
        "w",
        "x",
        "y",
        "z",
        "à",
        "á",
        "â",
        "ã",
        "ä",
        "å",
        "æ",
        "ç",
        "è",
        "é",
        "ê",
        "ë",
        "?",
        "ì",
        "í",
        "î",
        "ï",
        "ð",
        "ñ",
        "ò",
        "ó",
        "ô",
        "õ",
        "ö",
        "ø",
        "ù",
        "ú",
        "û",
        "ü",
        "ý",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?"
    );
    $convertir_de = array(
        "A",
        "B",
        "C",
        "D",
        "E",
        "F",
        "G",
        "H",
        "I",
        "J",
        "K",
        "L",
        "M",
        "N",
        "O",
        "P",
        "Q",
        "R",
        "S",
        "T",
        "U",
        "V",
        "W",
        "X",
        "Y",
        "Z",
        "À",
        "Á",
        "Â",
        "Ã",
        "Ä",
        "Å",
        "Æ",
        "Ç",
        "È",
        "É",
        "Ê",
        "Ë",
        "?",
        "Ì",
        "Í",
        "Î",
        "Ï",
        "Ð",
        "Ñ",
        "Ò",
        "Ó",
        "Ô",
        "Õ",
        "Ö",
        "Ø",
        "Ù",
        "Ú",
        "Û",
        "Ü",
        "Ý",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?"
    );
    return str_replace($convertir_de, $convertir_a, $cadena);
}

function strtoupper_utf8($cadena)
{
    $convertir_de = array(
        "a",
        "b",
        "c",
        "d",
        "e",
        "f",
        "g",
        "h",
        "i",
        "j",
        "k",
        "l",
        "m",
        "n",
        "o",
        "p",
        "q",
        "r",
        "s",
        "t",
        "u",
        "v",
        "w",
        "x",
        "y",
        "z",
        "à",
        "á",
        "â",
        "ã",
        "ä",
        "å",
        "æ",
        "ç",
        "è",
        "é",
        "ê",
        "ë",
        "?",
        "ì",
        "í",
        "î",
        "ï",
        "ð",
        "ñ",
        "ò",
        "ó",
        "ô",
        "õ",
        "ö",
        "ø",
        "ù",
        "ú",
        "û",
        "ü",
        "ý",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?"
    );
    $convertir_a = array(
        "A",
        "B",
        "C",
        "D",
        "E",
        "F",
        "G",
        "H",
        "I",
        "J",
        "K",
        "L",
        "M",
        "N",
        "O",
        "P",
        "Q",
        "R",
        "S",
        "T",
        "U",
        "V",
        "W",
        "X",
        "Y",
        "Z",
        "À",
        "Á",
        "Â",
        "Ã",
        "Ä",
        "Å",
        "Æ",
        "Ç",
        "È",
        "É",
        "Ê",
        "Ë",
        "?",
        "Ì",
        "Í",
        "Î",
        "Ï",
        "Ð",
        "Ñ",
        "Ò",
        "Ó",
        "Ô",
        "Õ",
        "Ö",
        "Ø",
        "Ù",
        "Ú",
        "Û",
        "Ü",
        "Ý",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?",
        "?"
    );
    return str_replace($convertir_de, $convertir_a, $cadena);
}

function limpiarCaracteresEspeciales1($String)
{
    $String = str_replace('Ã³', "o", $String);
    $String = str_replace(array('á', 'à', 'â', 'ã', 'ª', 'ä'), "a", $String);
    $String = str_replace(array('Á', 'À', 'Â', 'Ã', 'Ä'), "A", $String);
    $String = str_replace(array('Í', 'Ì', 'Î', 'Ï'), "I", $String);
    $String = str_replace(array('í', 'ì', 'î', 'ï'), "i", $String);
    $String = str_replace(array('é', 'è', 'ê', 'ë'), "e", $String);
    $String = str_replace(array('É', 'È', 'Ê', 'Ë'), "E", $String);
    $String = str_replace(array('ó', 'ò', 'ô', 'õ', 'ö', 'º'), "o", $String);
    $String = str_replace(array('Ó', 'Ò', 'Ô', 'Õ', 'Ö'), "O", $String);
    $String = str_replace(array('ú', 'ù', 'û', 'ü'), "u", $String);
    $String = str_replace(array('Ú', 'Ù', 'Û', 'Ü'), "U", $String);
    $String = str_replace(array('[', '^', '´', '`', '¨', '~', ']', '³'), "", $String);
    $String = str_replace(array('&', ';', '"', '/n'), " ", $String);
    $String = str_replace("ç", "c", $String);
    $String = str_replace("Ç", "C", $String);
    $String = str_replace("ñ", "n", $String);
    $String = str_replace("Ñ", "N", $String);
    $String = str_replace("Ý", "Y", $String);
    $String = str_replace("ý", "y", $String);
    $String = str_replace("null", "", $String);
    $String = str_replace("Null", "", $String);
    $String = str_replace("NULL", "", $String);

    $String = str_replace("&aacute;", "a", $String);
    $String = str_replace("&Aacute;", "A", $String);
    $String = str_replace("&eacute;", "e", $String);
    $String = str_replace("&Eacute;", "E", $String);
    $String = str_replace("&iacute;", "i", $String);
    $String = str_replace("&Iacute;", "I", $String);
    $String = str_replace("&oacute;", "o", $String);
    $String = str_replace("&Oacute;", "O", $String);
    $String = str_replace("&uacute;", "u", $String);
    $String = str_replace('"', "", $String);
    //        $String = str_replace("&#13;", "", $String);
    //        $String = str_replace("&#xd;", "", $String);
    return $String;
}

function onlyChars($string)
{
    $string = limpiarCaracteresEspeciales1($string);
    $strlength = strlen($string);
    $retString = "";
    for ($i = 0; $i < $strlength; $i++) {
        if (
            (ord($string[$i]) >= 48 && ord($string[$i]) <= 57) ||
            (ord($string[$i]) >= 58 && ord($string[$i]) <= 90) ||
            (ord($string[$i]) >= 97 && ord($string[$i]) <= 122) ||
            ord($string[$i]) == 13 || ord($string[$i]) == 10 || ord($string[$i]) == 164 || ord($string[$i]) == 165 || ord($string[$i]) == 47 || ord($string[$i]) == 32 ||
            ord($string[$i]) == 46 || ord($string[$i]) == 44 || ord($string[$i]) == 45 || ord($string[$i]) == 95 || ord($string[$i]) == 40 ||
            ord($string[$i]) == 41 || ord($string[$i]) == 35 || ord($string[$i]) == 34 || ord($string[$i]) == 39 || ord($string[$i]) == 33 ||
            ord($string[$i]) == 42 || ord($string[$i]) == 37 || ord($string[$i]) == 43 || ord($string[$i]) == 38
        ) {
            $retString .= $string[$i];
        }
    }

    return $retString;
}

function getCreditoFactura($carteraId, $identificacion, $operacion, $fechaPeriodo, $mongo)
{
    // $mongo = new MYMONGODB();
    $coleccion = "cbCreditos";
    $credito = array();
    $condition = array(
        'cre_carteraId' => (string) $carteraId,
        // "cre_cedula" => (string) $identificacion,
        "cre_factura" => (string) $operacion,
        "cre_inactivo" => (int) 0,
        "cre_fechaPeriodo" => (int) $fechaPeriodo
    );
    $r = $mongo->buscar($coleccion, $condition);
    if ($r > 0) {
        $row = $mongo->siguiente();
        $credito = $row;
    }
    return $credito;
}

function obtenerTelf($telfs)
{
    if (strlen($telfs) < 8) {
        return [];
    }
    $datos = [];
    $a = '';
    for ($i = 0; $i < strlen($telfs); $i++) {
        if (is_numeric(substr($telfs, $i, 1))) {
            $a .= substr($telfs, $i, 1);
        } else {
            if ($a != '') {
                // echo $a . "\n";
                if ((substr($telfs, $i, 1) == '-' || substr($telfs, $i, 1) == ' ' || substr($telfs, $i, 1) == ')') && strlen($a) > 0 && strlen($a) < 3) {
                    // caracter especial como separador de codigo de area
                    if (strlen($a) > 0 && strlen($a) < 8 && substr($telfs, $i, 1) == ' ') {
                        $a = '';
                    }
                } else {
                    // echo 'fin.. ' . $a . "\n";
                    if (substr($a, 0, 2) == '00') {
                        $a = substr($a, 1);
                    }
                    if (substr($a, 0, 2) == '00') {
                        $a = substr($a, 1);
                    }
                    if (strlen($a) == 7) {
                        $a = '02' . $a;
                    }
                    if (substr($a, 0, 3) == '593') {
                        $a = '0' . substr($a, 3, strlen($a) - 1);
                    }
                    if (strlen($a) > 7 && strlen($a) < 13) {
                        if (substr($a, 0, 2) == '00') {
                            $a = substr($a, 1);
                        }
                        if (expect_phone_EC($a)) {
                            if (
                                strpos($a, '000000') !== false ||
                                strpos($a, '111111') !== false ||
                                strpos($a, '222222') !== false ||
                                strpos($a, '333333') !== false ||
                                strpos($a, '444444') !== false ||
                                strpos($a, '555555') !== false ||
                                strpos($a, '666666') !== false ||
                                strpos($a, '777777') !== false ||
                                strpos($a, '888888') !== false ||
                                strpos($a, '999999') !== false
                            ) {
                            } else {
                                if (array_search($a, $datos) === false) {
                                    $datos[] = $a;
                                    $a = '';
                                }
                            }
                            $a = '';
                        } else {
                            $a = '';
                        }
                    } else {
                        $a = '';
                    }
                }
            }
        }
    }
    // echo 'final..1 ' . $a . "\n";
    if (substr($a, 0, 2) == '00') {
        $a = substr($a, 1);
    }
    if (substr($a, 0, 2) == '00') {
        $a = substr($a, 1);
    }
    if (strlen($a) == 7) {
        $a = '02' . $a;
    }
    if (substr($a, 0, 3) == '593') {
        $a = '0' . substr($a, 3, strlen($a) - 1);
    }
    // echo 'final..2 ' . $a . "\n";
    if ($a != '' && strlen($a) > 7 && strlen($a) < 13) {
        if (substr($a, 0, 2) == '00') {
            $a = substr($a, 1);
        }
        if (expect_phone_EC($a)) {
            if (
                strpos($a, '000000') !== false ||
                strpos($a, '111111') !== false ||
                strpos($a, '222222') !== false ||
                strpos($a, '333333') !== false ||
                strpos($a, '444444') !== false ||
                strpos($a, '555555') !== false ||
                strpos($a, '666666') !== false ||
                strpos($a, '777777') !== false ||
                strpos($a, '888888') !== false ||
                strpos($a, '999999') !== false
            ) {
            } else {
                if (array_search($a, $datos) === false) {
                    $datos[] = $a;
                    $a = '';
                }
            }
        } else {
            $a = '';
        }
    }
    return $datos;
}

function obtenerTelfOLD($telfs)
{
    if (strlen($telfs) < 8) {
        return [];
    }
    $datos = [];
    $a = '';
    for ($i = 0; $i < strlen($telfs); $i++) {
        if (is_numeric(substr($telfs, $i, 1))) {
            $a .= substr($telfs, $i, 1);
        } else {
            if ($a != '') {
                if ((substr($telfs, $i, 1) == '-' || substr($telfs, $i, 1) == ' ' || substr($telfs, $i, 1) == ')') && strlen($a) > 0 && strlen($a) < 3) {
                    // caracter especial como separador de codigo de area
                } else {
                    if (substr($a, 0, 2) == '00') {
                        $a = substr($a, 1);
                    }
                    if (substr($a, 0, 2) == '00') {
                        $a = substr($a, 1);
                    }
                    if (strlen($a) > 7 && strlen($a) < 13) {
                        if (substr($a, 0, 2) == '00') {
                            $a = substr($a, 1);
                        }
                        if (substr($a, 0, 3) == '593') {
                            $a = '0' . substr($a, 3, strlen($a) - 1);
                        }
                        if (expect_phone_EC($a)) {
                            $datos[] = $a;
                            $a = '';
                        }
                    } else {
                        $a = '';
                    }
                }
            }
        }
    }
    if (substr($a, 0, 2) == '00') {
        $a = substr($a, 1);
    }
    if (substr($a, 0, 2) == '00') {
        $a = substr($a, 1);
    }
    if ($a != '' && strlen($a) > 7 && strlen($a) < 13) {
        if (substr($a, 0, 2) == '00') {
            $a = substr($a, 1);
        }
        if (substr($a, 0, 3) == '593') {
            $a = '0' . substr($a, 3, strlen($a) - 1);
        }
        if (expect_phone_EC($a)) {
            $datos[] = $a;
        }
    }
    return $datos;
}

function actualizaGestion($carteraId, $identificacion, $numOperacion, $pagado, $deuda, $inactivo)
{
    $mongo = new MYMONGODB();
    $coleccionGest = 'cbEnvScLlamadas';
    $conditionGest = array(
        'env_cobCarteraId' => (int) $carteraId,
        "env_identificacion" => (string) $identificacion,
        "env_factura" => (string) $numOperacion,
        "env_inactivo" => 0,
        "env_pagado" => 0
    );
    $nuevaGest = array("env_pagado" => (int) $pagado, "env_totalDeuda" => (float) $deuda, "env_inactivo" => (int) $inactivo);
    //    $nuevaGest = array('$set' => array("env_pagado" => (int) $pagado, "env_totalDeuda" => (double) $deuda, "env_inactivo" => (int) $inactivo));
    //        print_h($conditionGest);
    //        print_h($nuevaGest);
    $r = $mongo->actualizar($coleccionGest, $conditionGest, $nuevaGest, false);
    return $r;
}

function actCreditosMySQL($carteraId)
{
    $db = new MYSQLDB();
    $db1 = new MYSQLDB();
    $mongo = new MYMONGODB();
    $coleccion = 'cbCreditos';
    $cont = 0;
    $contUpd = 0;
    // $carteraId = [(string) '39', (string) '2078'];
    // $periodos = $mongo->buscarDistinct('fecha', 'control_carga_periodo', ['activo' => (int) 1, 'cartera' => (string) $carteraId]);

    $db1->query($db1->mkSQL("update cbcreditos set cre_inactivo=%N where cre_carteraId=%Q", 1, $carteraId));

    $t = $mongo->buscar($coleccion, ['cre_carteraId' => (string) $carteraId, 'cre_inactivo' => (int) 0]);
    echo "\nInicio de act cbCreditos MySql " . $t . "\n\n";
    $lin = 0;
    while ($row = $mongo->siguiente()) {
        $lin++;
        $row['cre_cantidadGest'] = isset($row['cre_cantidadGest']) ? $row['cre_cantidadGest'] : 0;
        $existe = 0;
        $sql1 = $db1->mkSQL("SELECT count(*) as existe FROM cbcreditos where cre_factura=%Q and cre_carteraId=%Q", $row['cre_factura'], $row['cre_carteraId']);
        // $sql1 = $db1->mkSQL("SELECT count(*) as existe FROM cbcreditos where cre_factura=%Q and cre_carteraId=%Q AND cre_fechaPeriodo IN (" . implode(',', $periodos) . ")", $row['cre_factura'], $row['cre_carteraId']);
        if ($db1->query($sql1)) {
            $row1 = $db1->fetchRow();
            $existe = $row1['existe'];
        }
        if ($existe == 0) {
            $db->query(
                $db->mkSQL(
                    "INSERT INTO cbcreditos 
                            (cre_pagado,cre_periodo,cre_provincia,cre_usUsuarios_id,cre_etapa,cre_diasMoraFactura,
                            cre_tramoSaldo,cre_deudaNeta,cre_tramoMora,cre_asignacionPrioridad,
                            cre_cantidadGest,cre_nombres,cre_asignadoCargaId,cre_cedula,cre_nombreCartera,cre_asignadoCargaNombre,
                            cre_calificacion,cre_factura,cre_fecha,cre_producto,cre_tipoCredito,
                            cre_fechaPeriodo,cre_agencia,cre_apellidos,cre_inactivo,cre_carteraId,cre_estadoBien,cre_status,cre_compraConRecurso)
                            VALUES (%N,%N,%Q,%N,%N,%N,%Q,%N,%Q,%N,%N,%Q,%N,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%N,%Q,%Q,%Q,%N)",
                    $row['cre_pagado'],
                    $row['cre_periodo'],
                    $row['cre_provincia'],
                    $row['usUsuarios_id'],
                    $row['cre_etapa'],
                    $row['cre_diasMoraFactura'],
                    $row['cre_tramoSaldo'],
                    $row['cre_deudaNeta'],
                    $row['cre_tramoMora'],
                    $row['cre_asignacionPrioridad'],
                    $row['cre_cantidadGest'],
                    $row['cre_nombres'],
                    $row['cre_asignadoCargaId'],
                    $row['cre_cedula'],
                    $row['cre_nombreCartera'],
                    $row['cre_asignadoCargaNombre'],
                    $row['cre_calificacion'],
                    $row['cre_factura'],
                    $row['cre_fecha'],
                    $row['cre_producto'],
                    $row['cre_tipoCredito'],
                    $row['cre_fechaPeriodo'],
                    $row['cre_agencia'],
                    $row['cre_apellidos'],
                    $row['cre_inactivo'],
                    $row['cre_carteraId'],
                    isset($row['cre_estadoBien']) ? $row['cre_estadoBien'] : '',
                    isset($row['cre_status']) ? $row['cre_status'] : 0,
                    isset($row['cre_compraConRecurso']) ? $row['cre_compraConRecurso'] : 0
                )
            );
            $cont++;
        } else {
            $sql = $db->mkSQL(
                "update cbcreditos set 
                        cre_pagado=%N,
                        cre_provincia=%Q, 
                        cre_etapa=%N , 
                        cre_diasMoraFactura=%N, 
                        cre_tramoSaldo=%Q,
                        cre_deudaNeta=%N,
                        cre_tramoMora=%Q,
                        cre_nombres=%Q,
                        cre_nombreCartera=%Q,
                        cre_calificacion=%Q,
                        cre_fecha=%N,
                        cre_producto=%Q,
                        cre_agencia=%Q,
                        cre_apellidos=%Q,
                        cre_inactivo=%N,
                        cre_estadoBien=%Q, 
                        cre_periodo=%N, 
                        cre_fechaPeriodo=%N,
                        cre_compraConRecurso=%N,
                        cre_status=%N  
                        where cre_factura=%Q and cre_carteraId=%Q ",
                $row['cre_pagado'],
                $row['cre_provincia'],
                $row['cre_etapa'],
                $row['cre_diasMoraFactura'],
                $row['cre_tramoSaldo'],
                $row['cre_deudaNeta'],
                $row['cre_tramoMora'],
                $row['cre_nombres'],
                $row['cre_nombreCartera'],
                $row['cre_calificacion'],
                $row['cre_fecha'],
                $row['cre_producto'],
                $row['cre_agencia'],
                $row['cre_apellidos'],
                $row['cre_inactivo'],
                isset($row['cre_estadoBien']) ? $row['cre_estadoBien'] : '',
                $row['cre_periodo'],
                $row['cre_fechaPeriodo'],
                isset($row['cre_compraConRecurso']) ? $row['cre_compraConRecurso'] : 0,
                $row['cre_status'],
                $row['cre_factura'],
                $row['cre_carteraId']
            );
            $db->query($sql);
            $contUpd++;
        }
    }
    $mongo->actualizar($coleccion, ['cre_carteraId' => (string) $carteraId, 'cre_fechaActMysql' => (int) 999], ['cre_fechaActMysql' => (int) strtotime(date('Ymd 00:00:00', time()))]);
    // $db1->query($db1->mkSQL("delete FROM cbcreditos where cre_inactivo=%N", 1));


    $mongo->guardar('cbCargaEtlRegistroDiario', ['fecha' => (int) time(), 'fechaStr' => (string) date('dmY', time()), 'cartera' => (int) $carteraId, 'insertados' => (int) $cont, 'actualizados' => (int) $contUpd]);

    echo "\nRegistros insertados: " . $cont . " Registros actualizados: " . $contUpd . ' | ' . date('d-m-Y H:i:s', time()) . "\n\n";

    // $db = new MYSQLDB();
    // $db1 = new MYSQLDB();
    // $mongo = new MYMONGODB();
    // $coleccion = 'cbCreditos';
    // $cont = 0;
    // $contUpd = 0;
    // // $carteraId = [(string) '39', (string) '2078'];
    // $periodos = $mongo->buscarDistinct('fecha', 'control_carga_periodo', ['activo' => (int) 1, 'cartera' => (string) $carteraId]);
    // $t = $mongo->buscar($coleccion, ['cre_carteraId' => (string) $carteraId, 'cre_inactivo' => (int) 0]);
    // echo "\nInicio de act cbCreditos MySql " . $t . "\n\n";
    // $lin = 0;
    // while ($row = $mongo->siguiente()) {
    //     $lin++;
    //     $row['cre_cantidadGest'] = isset($row['cre_cantidadGest']) ? $row['cre_cantidadGest'] : 0;
    //     $existe = 0;
    //     $sql1 = $db1->mkSQL("SELECT count(*) as existe FROM cbcreditos where cre_factura=%Q and cre_carteraId=%Q  AND cre_fechaPeriodo IN (" . implode(',', $periodos) . ")", $row['cre_factura'], $row['cre_carteraId']);
    //     if ($db1->query($sql1)) {
    //         $row1 = $db1->fetchRow();
    //         $existe = $row1['existe'];
    //     }
    //     if ($existe == 0) {
    //         $db->query(
    //             $db->mkSQL("INSERT INTO cbcreditos 
    //                         (cre_pagado,cre_periodo,cre_provincia,cre_usUsuarios_id,cre_etapa,cre_diasMoraFactura,
    //                         cre_tramoSaldo,cre_deudaNeta,cre_tramoMora,cre_asignacionPrioridad,
    //                         cre_cantidadGest,cre_nombres,cre_asignadoCargaId,cre_cedula,cre_nombreCartera,cre_asignadoCargaNombre,
    //                         cre_calificacion,cre_factura,cre_fecha,cre_producto,cre_tipoCredito,
    //                         cre_fechaPeriodo,cre_agencia,cre_apellidos,cre_inactivo,cre_carteraId,cre_estadoBien)
    //                         VALUES (%N,%N,%Q,%N,%N,%N,%Q,%N,%Q,%N,%N,%Q,%N,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%N,%Q,%Q)",
    //                 $row['cre_pagado'],
    //                 $row['cre_periodo'],
    //                 $row['cre_provincia'],
    //                 $row['usUsuarios_id'],
    //                 $row['cre_etapa'],
    //                 $row['cre_diasMoraFactura'],
    //                 $row['cre_tramoSaldo'],
    //                 $row['cre_deudaNeta'],
    //                 $row['cre_tramoMora'],
    //                 $row['cre_asignacionPrioridad'],
    //                 $row['cre_cantidadGest'],
    //                 $row['cre_nombres'],
    //                 $row['cre_asignadoCargaId'],
    //                 $row['cre_cedula'],
    //                 $row['cre_nombreCartera'],
    //                 $row['cre_asignadoCargaNombre'],
    //                 $row['cre_calificacion'],
    //                 $row['cre_factura'],
    //                 $row['cre_fecha'],
    //                 $row['cre_producto'],
    //                 $row['cre_tipoCredito'],
    //                 $row['cre_fechaPeriodo'],
    //                 $row['cre_agencia'],
    //                 $row['cre_apellidos'],
    //                 $row['cre_inactivo'],
    //                 $row['cre_carteraId'],
    //                 isset($row['cre_estadoBien']) ? $row['cre_estadoBien'] : ''
    //             )
    //         );
    //         $cont++;
    //     } else {
    //         $sql = $db->mkSQL("update cbcreditos set 
    //                     cre_pagado=%N,
    //                     cre_provincia=%Q, 
    //                     cre_etapa=%N , 
    //                     cre_diasMoraFactura=%N, 
    //                     cre_tramoSaldo=%Q,
    //                     cre_deudaNeta=%N,
    //                     cre_tramoMora=%Q,
    //                     cre_nombres=%Q,
    //                     cre_nombreCartera=%Q,
    //                     cre_calificacion=%Q,
    //                     cre_fecha=%N,
    //                     cre_producto=%Q,
    //                     cre_agencia=%Q,
    //                     cre_apellidos=%Q,
    //                     cre_inactivo=%N,
    //                     cre_estadoBien=%Q, 
    //                     cre_periodo=%N, 
    //                     cre_fechaPeriodo=%N 
    //                     where cre_factura=%Q and cre_carteraId=%Q ",
    //             $row['cre_pagado'],
    //             $row['cre_provincia'],
    //             $row['cre_etapa'],
    //             $row['cre_diasMoraFactura'],
    //             $row['cre_tramoSaldo'],
    //             $row['cre_deudaNeta'],
    //             $row['cre_tramoMora'],
    //             $row['cre_nombres'],
    //             $row['cre_nombreCartera'],
    //             $row['cre_calificacion'],
    //             $row['cre_fecha'],
    //             $row['cre_producto'],
    //             $row['cre_agencia'],
    //             $row['cre_apellidos'],
    //             $row['cre_inactivo'],
    //             isset($row['cre_estadoBien']) ? $row['cre_estadoBien'] : '',
    //             $row['cre_periodo'],
    //             $row['cre_fechaPeriodo'],
    //             $row['cre_factura'],
    //             $row['cre_carteraId']
    //         );
    //         $db->query($sql);
    //         $contUpd++;
    //     }
    // }
    // echo "\nRegistros insertados: " . $cont . " Registros actualizados: " . $contUpd . ' | ' . date('d-m-Y H:i:s', time()) . "\n\n";
}

function calificacion($tramos, $mora, $producto, $carteraId = '39')
{
    $t = '';
    foreach ($tramos as $tr) {
        if ($t == '' && $mora >= $tr['tr_tramoInicio'] && $mora <= $tr['tr_tramoFin'] && $tr['tr_tramoFin'] != 9999999 && $tr['tr_tramoInicio'] != 9999999) {
            $t = $tr['tr_tramo'];
        }
        if ($t == '' && $mora >= $tr['tr_tramoInicio'] && $tr['tr_tramoFin'] == 9999999) {
            $t = $tr['tr_tramo'];
        }
        if ($t == '' && $tr['tr_tramoInicio'] == 9999999 && $mora <= $tr['tr_tramoFin']) {
            $t = $tr['tr_tramo'];
        }
        if ($t != '' && isset($tr['data1'])) {
            $val = $tr['data1'];
            // foreach ($tr['data1'] as $val) {
            if (isset($val['cbConf_carteraNombre']) && isset($val['cbConf_tipo'])) {
                if ($producto != '') {
                    if ($val['cbConf_tipo'] == 'tr_MORA_PRODUCTO' && $val['cbConf_carteraNombre'] == $producto) {
                        return ['ini' => $tr['tr_tramoInicio'], 'fin' => $tr['tr_tramoFin'], 'calificacion' => $t, 'etapa' => isset($tr['tr_etapaValor']) ? $tr['tr_etapaValor'] : 0];
                    }
                } else {
                    if (trim($val["cbConf_id"]) == trim($carteraId) && $val['cbConf_tipo'] == 'tr_MORA_CARTERA') {
                        return ['ini' => $tr['tr_tramoInicio'], 'fin' => $tr['tr_tramoFin'], 'calificacion' => $t, 'etapa' => isset($tr['tr_etapaValor']) ? $tr['tr_etapaValor'] : 0];
                    }
                }
            }
        }
    }
}

echo "EJECUCION_COMPLETA";
//exit;
?><?

    //_FIN_DE_ARCHIVO                                            
    ?>