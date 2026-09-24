<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$origen = isset($_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : '';
if ($origen == "") { // si es ejecutado por servicio
    chdir(__DIR__);
    require_once("/home/pacifico/_configBasico.inc.php");
    $basePath = '..';
    define('SALTO_DE_LINEA', "\n");
    $categoriaCarga = $argv[1] ?? '';
    $idCartera = (int) ($argv[2] ?? 0);
    //$cargaInicial = isset($argv[3]) ? intval($argv[3]) : '';
    //Se aumenta parámetro, desea inactivar los registros que no vinieron en la carga (Inactivar(Si inactiva),Mantener(no hace nada))
    $inactivaCartera = $argv[3] ?? '';
} else { // si es ejecutado por browser
    require_once("../../_configBasico.inc.php");
    $basePath = dirname(__DIR__);
    define('SALTO_DE_LINEA', "<br>");
    $categoriaCarga = $_GET['categoriaCarga'] ?? $_POST['categoriaCarga'] ?? '';
    $idCartera = (int) ($_GET['idCartera'] ?? $_POST['idCartera'] ?? 0);
    //$cargaInicial = isset($_GET['cargaInicial']) ? intval($_GET['cargaInicial']) : (isset($_POST['cargaInicial']) ? intval($_POST['cargaInicial']) : '');
    //Se aumenta parámetro, desea inactivar los registros que no vinieron en la carga (Inactivar(Si inactiva),Mantener(no hace nada))
    $inactivaCartera = $_GET['inactivaCartera'] ?? $_POST['inactivaCartera'] ?? '';
}
require_once $basePath . "/comunes/classes/class.mymongodb.php";
require_once $basePath . "/comunes/classes/class.clase.php";
require_once $basePath . "/usuarios/classes/class.usuario.php";
require_once $basePath . "/usuarios/classes/class.usTelf.php";
require_once $basePath . "/usuarios/classes/class.usDireccion.php";
require_once $basePath . "/cobranza/classes/class.cobCartera.php";

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '4096M');

if ($idCartera === 0)
    exit;

switch ($categoriaCarga) {
    case "Activacion":
        $coleccionDeCarga = "cbCargaActivacionEtl";
        $coleccionDeGestiones = "cuGestionVentas";
        $prefijo = "cubGV_";
        break;
    case "Seguros":
        $coleccionDeCarga = "cbCargaSeguroEtl";
        $coleccionDeGestiones = "cuGestionVentas";
        $prefijo = "cubGV_";
        break;
    default:
        $coleccionDeCarga = "cbCargaEtl";
        $coleccionDeGestiones = "cuGestionCobranza";
        $prefijo = "cubGC_";
        break;
}
$inactiva = 'N';

echo SALTO_DE_LINEA . "coleccion de carga " . $coleccionDeCarga . SALTO_DE_LINEA;
echo SALTO_DE_LINEA . "cartera " . $idCartera . SALTO_DE_LINEA;
echo SALTO_DE_LINEA . "inactivar cartera " . $inactivaCartera . SALTO_DE_LINEA;

//procesaArchivo($idCartera, $inactiva, $cargaInicial, $coleccionDeCarga, $coleccionDeGestiones, $prefijo, $inactivaCartera);
procesaArchivo($idCartera, $inactiva, $coleccionDeCarga, $coleccionDeGestiones, $prefijo, $inactivaCartera);

echo SALTO_DE_LINEA . "termino" . SALTO_DE_LINEA;

actCreditosMySQL($idCartera);
exit;

//function procesaArchivo(int $idCartera, string $inactiva, ?string $cargaInicial, string $coleccionDeCarga, string $coleccionDeGestiones, string $prefijo, string $inactivaCartera): void
function procesaArchivo(int $idCartera, string $inactiva, string $coleccionDeCarga, string $coleccionDeGestiones, string $prefijo, string $inactivaCartera): void {
    $cnf = getConf('Cobranza');
    $bandasHorarias = $cnf["Bandas horarias"];
    $mongo = new MYMONGODB();
    $mongo1 = new MYMONGODB();
    $mongo2 = new MYMONGODB();
    $mongo3 = new MYMONGODB();
    $mongo33 = new MYMONGODB();
    $mongoInicio = new MYMONGODB();
    $db = new MYSQLDB();
    echo SALTO_DE_LINEA . "inicio" . SALTO_DE_LINEA;
    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
    $tInicio = time();
    $hoy = date("d-m-Y", $tInicio);
    $tramosPorProducto = [];
    $tramosPorCartera = [];
    $condition = [
        ['$match' => [
                'tr_tipoTramo' => 'MORA',
            ],
        ],
        ['$lookup' => [
                'from' => 'cbConfig',
                'localField' => '_id',
                'foreignField' => 'cbConf_cbTramosId',
                'as' => 'data',
            ],
        ],
        ['$unwind' => '$data',],
        ['$match' => [
                'data.cbConf_tipo' => 'tr_MORA_CARTERA',
            ],
        ],
        ['$lookup' => [
                'from' => 'cbConfig',
                'localField' => 'data.cbConf_cbTramosId',
                'foreignField' => 'cbConf_cbTramosId',
                'as' => 'data1',
            ],
        ],
        ['$unwind' => '$data1',],
        ['$match' => [
                'data1.cbConf_tipo' => 'tr_MORA_PRODUCTO',
            ],
        ],
    ];
    $mongo->agregar('cbTramos', $condition);
    while ($tr = $mongo->siguiente()) {
        // Tramos por cartera (data)
        if (isset($tr['data']['cbConf_id'])) {
            $carteraKey = $tr['data']['cbConf_id'];
            $tramosPorCartera[$carteraKey][] = $tr;
        }
        // Tramos por producto (data1)
        if (isset($tr['data1']['cbConf_carteraNombre'])) {
            $productoKey = $tr['data1']['cbConf_carteraNombre'];
            $tramosPorProducto[$productoKey][] = $tr;
        }
    }

    $condition = [
        ['$match' => [
                'cbConf_id' => (string) $idCartera,
                'cbConf_tipo' => 'tr_SALDO_CARTERA',
            ],
        ],
        ['$lookup' => [
                'from' => 'cbTramos',
                'localField' => 'cbConf_cbTramosId',
                'foreignField' => '_id',
                'as' => 'datos',
            ],
        ],
    ];
    $mongo->agregar('cbConfig', $condition);
    $tramo_bd_saldo = [];
    while ($t = $mongo->siguiente()) {
        $tramo_bd_saldo[] = $t;
    }

    $provincias = [];
    $sql = $db->mkSQL("SELECT usProvincias_id,trim(usProvincias_nombre) usProvincias_nombre FROM usprovincias;");
    if ($db->query($sql)) {
        while ($row = $db->fetchRow()) {
            $provincias[strtoupper($row['usProvincias_nombre'])] = $row['usProvincias_id'];
        }
    }

    $cantidadT = 0;

    $curT = $mongoInicio->buscar($coleccionDeCarga, ["carteraEtl_procesado" => 999, "carteraEtl_estado" => 1, "carteraEtl_carteraId" => (string) $idCartera]);

    //almacene en un array todos los registros de cbCreditos que esten activos para esta cartera
    $mongo2->buscar('cbCreditos', ['cre_carteraId' => (string) $idCartera, 'cre_inactivo' => 0], ['cre_carteraId', 'cre_factura']);
    $cbCreditosActivos = [];
    while ($doc = $mongo2->siguiente()) {
        $cbCreditosActivos[$doc['_id']->__toString()] = $doc;
    }
    if ($coleccionDeCarga === "cbCargaEtl") {   //solo para carteras de cobranza
    }
    //obtenga en un array el día probable de pago para las carteras de cobranza
    $haceUnAnio = strtotime('-1 year');
    $diasProbables = [];
    $regularidad = [];
    $mejoresBandasHorarias = [];

    if ($coleccionDeCarga === "cbCargaEtl") {  //solo para carteras de cobranza
        $pipeline = [
            ['$match' => ['pagos_carteraId' => (string) $idCartera, 'pagos_fechaPago' => ['$gte' => $haceUnAnio]]],
            ['$project' => [
                    'pagos_cedula' => 1,
                    'dia' => [
                        '$dayOfMonth' => [
                            '$toDate' => [
                                '$multiply' => [
                                    [
                                        '$subtract' => [
                                            '$pagos_fechaPago',
                                            18000  //zona horaria
                                        ]
                                    ],
                                    1000 //javascript timestamp
                                ]
                            ]
                        ]
                    ]
                ]],
            ['$group' => [
                    '_id' => '$pagos_cedula',
                    'avgDayOfMonth' => [
                        '$avg' => '$dia'
                    ],
                    'regularityScore' => [
                        '$stdDevPop' => '$dia'
                    ]
                ]],
            ['$project' => [
                    'avgDayOfMonth' => [
                        '$round' => ['$avgDayOfMonth', 0]
                    ],
                    'regularidad' => [
                        '$divide' => ['$regularityScore', 15]
                    ],
                ]]
        ];
        $mongo2->aggregate("cbPagos", $pipeline);
        while ($doc = $mongo2->siguiente()) {
            $diasProbables['ced_' . $doc['_id']] = $doc['avgDayOfMonth'];
            if ($doc['regularidad'] <= 0.01) {
                $regularidad['ced_' . $doc['_id']] = 'N/A';
            } elseif ($doc['regularidad'] < 0.2) {
                $regularidad['ced_' . $doc['_id']] = 'Muy Regular';
            } elseif ($doc['regularidad'] < 0.5) {
                $regularidad['ced_' . $doc['_id']] = 'Bastante Regular';
            } elseif ($doc['regularidad'] < 1.0) {
                $regularidad['ced_' . $doc['_id']] = 'Irregular';
            } else {
                $regularidad['ced_' . $doc['_id']] = 'Impredecible';
            }
        }
    }
    //obtenga un array de banda horaria de mejor gestion
    $branches = [];
    foreach ($bandasHorarias as $bh) {
        //obtenga la hora maxima de la banda como un entero
        $hh = explode(" ", $bh);
        if (count($hh) === 2) {
            $mm = explode(":", $hh[1]);
            if (count($mm) === 2) {
                $entero = (int) $mm[0];
                if ($entero > 0) {
                    //añada este opcion en el pipeline de mongo para segmentar las horas de gestion
                    $branches[] = [
                        'case' => [
                            '$lt' => ['$hora', $entero]
                        ],
                        'then' => $bh
                    ];
                }
            }
        }
    }
    //prepare el agreggate
    $pipeline = [
        ['$match' => [$prefijo . 'carteraId' => (string) $idCartera, $prefijo . 'fechaGestion' => ['$gte' => $haceUnAnio]]],
        [
            '$sort' => [
                $prefijo . 'cedula' => 1,
                $prefijo . 'ponderacion' => -1
            ]
        ],
        [
            '$group' => [
                '_id' => '$' . $prefijo . 'cedula',
                'maxDoc' => ['$first' => '$$ROOT']
            ]
        ],
        [
            '$project' =>
            [
                'maxDoc.' . $prefijo . 'cedula' => 1,
                'maxDoc.' . $prefijo . 'ponderacion' => 1,
                'maxDoc.' . $prefijo . 'fechaGestion' => 1,
                'hora' => [
                    '$hour' => [
                        '$toDate' => [
                            '$multiply' => [
                                [
                                    '$subtract' => [
                                        '$maxDoc.' . $prefijo . 'fechaGestion',
                                        18000
                                    ]
                                ],
                                1000
                            ]
                        ]
                    ]
                ]
            ]
        ],
        [
            '$addFields' =>
            [
                'mejorBandaHoraria' => [
                    '$switch' => [
                        'branches' => $branches,
                        'default' => ""
                    ]
                ]
            ]
        ]
    ];
    $mongo2->aggregate($coleccionDeGestiones, $pipeline);
    while ($doc = $mongo2->siguiente()) {
        $mejoresBandasHorarias['ced_' . $doc['_id']] = $doc['mejorBandaHoraria'];
    }

    $listaPeriodos = [];
    $limit = 1000;

    inicio:

    $retVal = "";
    $numReg = 0;
    $lin = 1;
    $condicion = ["carteraEtl_procesado" => 999, "carteraEtl_estado" => 1, "carteraEtl_carteraId" => (string) $idCartera];
    $cur = $mongoInicio->buscar($coleccionDeCarga, $condicion, [], [], $limit);
    $cantidadT += $cur;
    echo SALTO_DE_LINEA . "cantidad procesada " . $cantidadT . " de " . $curT . SALTO_DE_LINEA;
    while ($row = $mongoInicio->siguiente()) {
        $direccionGuardarUsuario = [];
        $numReg++;
        $cedula = trim($row['carteraEtl_cedula'] ?? '');
        if ($cedula !== '') {

            $fecha_factura = 0;
            if (isset($row['carteraEtl_fechaFactura']) && $row['carteraEtl_fechaFactura'] != '' && $row['carteraEtl_fechaFactura'] != 'NA') {
                if ($row['carteraEtl_fechaFactura'] > 0) {
                    $fecha_factura = (int) $row['carteraEtl_fechaFactura'];
                } else {
                    $fecha_factura = $row['carteraEtl_fechaFactura']->toDateTime()->getTimestamp();
                }
            }

            $primerNombre = isset($row['carteraEtl_primerNombre']) ? trim(onlyChars($row['carteraEtl_primerNombre'])) : '';
            $segundoNombre = isset($row['carteraEtl_segundoNombre']) ? trim(onlyChars($row['carteraEtl_segundoNombre'])) : '';
            $apellidoPaterno = isset($row['carteraEtl_apellidoPaterno']) ? trim(onlyChars($row['carteraEtl_apellidoPaterno'])) : '';
            $apellidoMaterno = isset($row['carteraEtl_apellidoMaterno']) ? trim(onlyChars($row['carteraEtl_apellidoMaterno'])) : '';
            $nombres = $primerNombre . ' ' . $segundoNombre;
            $apellidos = $apellidoPaterno . " " . $apellidoMaterno;
            $usUsuariosid = creaoActualizaUsuario($cedula, $nombres, $apellidos);

            $provincia = "";
            $provinciaId = 0;
            if (isset($row['carteraEtl_provincia']) && $row['carteraEtl_provincia'] != 'NA' && $row['carteraEtl_provincia'] != '') {
                $provincia = strtoupper(onlyChars($row['carteraEtl_provincia']));
                $provinciaId = (isset($provincias[$provincia])) ? $provincias[strtoupper($provincia)] : 0;
                if (!is_numeric($provinciaId)) {
                    $provinciaId = 0;
                }
            }
            $orden = $tInicio . substr(microtime(), 2, 8);

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
                    $tipoTlf = $teltmp[0] == '09' ? 'Movil' : 'Fijo';
                    $telefonosaGuardarUsuarios = array(
                        "tel_id" => (int) $idTel,
                        "tel_cedula" => $cedula,
                        "tel_numero" => $tel,
                        "tel_tipo" => $tipoTlf,
                        "tel_observacion" => "",
                        "tel_equivocado" => 0,
                        "tel_eliminado" => 0,
                        "tel_origen" => "ETL",
                        "tel_titular" => 1,
                        "tel_orden" => $orden
                    );
                    $telMng = insertaTelefonosMongo($cedula, $tel, $telefonosaGuardarUsuarios);
                } else {
                    $retVal .= "<br> El telefono " . $tel . " proporcionado por el deudor cedula: " . $cedula . " tiene problemas";
                }
            }

            $ciudad = isset($row['carteraEtl_canton']) ? onlyChars($row['carteraEtl_canton']) : '';
            $canton = $ciudad;
            $parroquia = onlyChars($row['carteraEtl_parroquia'] ?? '');
            $region = onlyChars($row['carteraEtl_region'] ?? '');
            $barrio = onlyChars($row['carteraEtl_barrio'] ?? '');
            $referencia_domicilio = onlyChars($row['carteraEtl_referenciaDomicilio'] ?? '');

            $tipoCarga = isset($row['nombreArchivo']) ? 'COMPRA DE CARTERA' : 'INTEGRACION';
            $direccion1 = (isset($row['carteraEtl_direccion']) && $row['carteraEtl_direccion'] != '') ? trim(onlyChars($row['carteraEtl_direccion'])) : '';
            $direccion2 = (isset($row['carteraEtl_direccion1']) && $row['carteraEtl_direccion1'] != '') ? trim(onlyChars($row['carteraEtl_direccion1'])) : '';

            if ($direccion1 === $direccion2) {
                procesarDireccionUsuario(
                        $usUsuariosid, $cedula, $nombres, $apellidos, $idCartera,
                        $provinciaId, $provincia, $ciudad, $canton, $parroquia, $region, $barrio,
                        $direccion1, $tipoCarga, $direccionGuardarUsuario, $retVal, $orden, $referencia_domicilio
                );
            } else {
                // Procesar primera dirección
                procesarDireccionUsuario(
                        $usUsuariosid, $cedula, $nombres, $apellidos, $idCartera,
                        $provinciaId, $provincia, $ciudad, $canton, $parroquia, $region, $barrio,
                        $direccion1, $tipoCarga, $direccionGuardarUsuario, $retVal, $orden, $referencia_domicilio
                );
                // Procesar segunda dirección
                procesarDireccionUsuario(
                        $usUsuariosid, $cedula, $nombres, $apellidos, $idCartera,
                        $provinciaId, $provincia, $ciudad, $canton, $parroquia, $region, $barrio,
                        $direccion2, $tipoCarga, $direccionGuardarUsuario, $retVal, 0 // orden 0 para generar tipoDireccion2
                );
            }

            if (!isset($row['carteraEtl_nombre'])) {
                $row['carteraEtl_nombre'] = '';
            }
            $deuda_neta = trim($row['carteraEtl_deudaNeta'] ?? '') !== '' ? floatval($row['carteraEtl_deudaNeta']) : 0;
            $deuda_original = trim($row['carteraEtl_deudaOriginal'] ?? '') !== '' ? floatval($row['carteraEtl_deudaOriginal']) : 0;
            $agente = ($agente ?? '') === 'NA' ? '' : ($agente ?? '');
            $agenteNombre = ($agente !== '' && $agente > 0) ? buscaUsuarioxId($agente) : '';

            $tramo_saldo = 'SIN TRAMO';
            foreach ($tramo_bd_saldo as $tr) {
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
            $diasMoraFactura = 0;
            if (isset($row['carteraEtl_diasMora']) && $row['carteraEtl_diasMora'] > 0) {
                $diasMoraFactura = intval($row['carteraEtl_diasMora']);
            } else {
                $diasMoraFactura = 0;
                // trigger_error("El archivo no contiene los días de mora de la factura  del deudor - factura: " . $row['carteraEtl_factura']);
            }
            //*******************    busca el tramo de saldo segun diasMoraFactura
            $tramomora = 'SIN TRAMO';
            $etapa = $row['carteraEtl_etapa'] ?? 0;
            $producto = isset($row['carteraEtl_producto']) ? onlyChars($row['carteraEtl_producto']) : 'NA';
            $tr = calificacion($diasMoraFactura, $producto, $idCartera, $tramosPorProducto, $tramosPorCartera);
            $calificacion = $tr['calificacion'] ?? '';
            $etapa = $tr['etapa'] ?? '';
            $a = $b = '';
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

            $linea = onlyChars($row['carteraEtl_linea'] ?? '');
            $sublinea = onlyChars($row['carteraEtl_sublinea'] ?? '');
            $marca = onlyChars($row['carteraEtl_marca'] ?? '');

            $fecha_ultimomov = '';
            if (!empty($row['carteraEtl_fecultimovcto'])) {
                $fecha = trim($row['carteraEtl_fecultimovcto']);
                $date = DateTime::createFromFormat('d/m/Y', $fecha);
                if ($date !== false) {
                    $fecha_ultimomov = $date->getTimestamp();
                }
            }

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

            $factura = $row['carteraEtl_factura'] ?? '';
            if (empty($factura) || strtolower($factura) == 'null' || $factura == 'N/A') {
                $factura = $cedula;
            }
            if ($factura !== '') {
                $productoGuardar = [
                    'pro_linea' => $linea,
                    'pro_sublinea' => $sublinea,
                    'pro_marca' => $marca
                ];
                $producto = onlyChars($row['carteraEtl_producto'] ?? 'NA');

                $fecha_Vencimiento = (int) ($row['carteraEtl_fechaVencimiento'] ?? 0);
                if (strpos($producto, 'REESTRUCTURACION') !== false && $diasMoraFactura > 0) {
                    $fecha_Vencimiento = strtotime($hoy . " - " . $diasMoraFactura . " day");
                }

                $inactivo = trim($row['carteraEtl_inactivo'] ?? '');
                if ($inactivo === '' || $inactivo === 'NA' || $inactivo === 'N/A') {
                    $inactivo = 0;
                }

                // Si deudaOriginal > 0, la usamos; si no, usamos $deuda_neta
                $deudaOriginal = (isset($row['carteraEtl_deudaOriginal']) && $row['carteraEtl_deudaOriginal'] > 0) ? $row['carteraEtl_deudaOriginal'] : $deuda_neta;

                $periodo = $row['carteraEtl_periodo'] ?? 0;
                $fechaPeriodo = isset($row['carteraEtl_fechaPeriodo']) ? intval($row['carteraEtl_fechaPeriodo']) : 0;
                $ctrlFec = $mongo3->buscar('control_carga_periodo', ['cartera' => (int) $idCartera, 'periodo' => (int) $periodo, 'activo' => 1], ['fecha']);
                if ($ctrlFec) {
                    $rw = $mongo3->siguiente();
                    $fechaPeriodo = $rw['fecha'];
                } else {
                    $ctrlFec = $mongo3->buscar('control_carga_periodo', ['cartera' => (int) $idCartera, 'periodo' => (int) $periodo, 'activo' => 0], ['fecha'], ['fecha' => -1], 1);
                    if ($ctrlFec) {
                        $rw = $mongo3->siguiente();
                        $fechaPeriodo = $rw['fecha'];
                    }
                }

                if ($fechaPeriodo > 0) {
                    $listaPeriodos[$periodo] = strtotime(date('Y-m-d 00:00:00', $fechaPeriodo));
                }
                $correo = '';
                if (isset($row['carteraEtl_email'])) {
                    $correo = strtolower($row['carteraEtl_email']);
                    $rem = $mongo2->buscar('cbEmail', ['mail_cedula' => $cedula, 'mail_email' => $correo]);
                    if ($rem === 0) {
                        $data = [
                            "mail_cedula" => $cedula,
                            "mail_email" => $correo,
                            "mail_observacion" => "",
                            "mail_usUsuario_id" => $usUsuariosid,
                            "mail_fecha" => $tInicio,
                            "mail_cedulaReferido" => "",
                            "mail_tipoCarga" => "",
                            "mail_tipoReferenciaPersona" => ""
                        ];
                        $mongo2->guardar('cbEmail', $data);
                    }
                }
                $diaProbable = 0;
                if (isset($diasProbables['ced_' . $cedula])) {
                    $diaProbable = $diasProbables['ced_' . $cedula];
                }
                $mejorBandaHoraria = '';
                if (isset($mejoresBandasHorarias['ced_' . $cedula])) {
                    $mejorBandaHoraria = $mejoresBandasHorarias['ced_' . $cedula];
                }
                $regularidadCedula = '';
                if (isset($regularidadCedula['ced_' . $cedula])) {
                    $regularidadCedula = $regularidad['ced_' . $cedula];
                }
                $creditoGuardar = [
                    'usUsuarios_id' => $usUsuariosid,
                    'cre_cedula' => $cedula,
                    'cre_nombres' => $nombres,
                    'cre_apellidos' => $apellidos,
                    'cre_primerNombre' => $primerNombre,
                    'cre_segundoNombre' => $segundoNombre,
                    'cre_apellidoPaterno' => $apellidoPaterno,
                    'cre_apellidoMaterno' => $apellidoMaterno,
                    'cre_carteraId' => (string) $idCartera,
                    'cre_nombreCartera' => $row['carteraEtl_carteraNombre'] ?? '',
                    'cre_provincia' => $provincia,
                    'cre_canton' => $row['carteraEtl_canton'] ?? '',
                    'cre_parroquia' => $parroquia,
                    "cre_factura" => (string) $factura,
                    "cre_campania" => '',
                    "cre_nombreTienda" => onlyChars($row['carteraEtl_nombre'] ?? ''),
                    "cre_plazo" => $row['carteraEtl_plazo'] ?? 0,
                    "cre_fechaFactura" => $fecha_factura,
                    "cre_fechaVencimiento" => $fecha_Vencimiento,
                    "cre_saldoCuota" => (double) (!empty(trim($row['carteraEtl_saldoCuota'] ?? '')) ? $row['carteraEtl_saldoCuota'] : 0),
                    "cre_tasaInteresMora" => (double) (trim($row['carteraEtl_tasaInteresMora'] ?? '') !== '' ? $row['carteraEtl_tasaInteresMora'] : 0),
                    "cre_interes" => (double) (trim($row['carteraEtl_interes'] ?? '') !== '' ? $row['carteraEtl_interes'] : 0),
                    "cre_deudaInteres" => (double) (trim($row['carteraEtl_deudaMasInteres'] ?? '') !== '' ? $row['carteraEtl_deudaMasInteres'] : 0),
                    "cre_pago" => (double) (trim($row['carteraEtl_pagos'] ?? '') !== '' ? $row['carteraEtl_pagos'] : 0),
                    "cre_deudaNeta" => (double) $deuda_neta,
                    "cre_tramoSaldo" => $tramo_saldo,
                    "cre_diasMoraFactura" => (int) $diasMoraFactura,
                    "cre_tramoMora" => $tramomora,
                    "cre_ultimaCuota" => trim($row['carteraEtl_ultimaCuotaDevengada'] ?? '') !== '' ? $row['carteraEtl_ultimaCuotaDevengada'] : 0,
                    "cre_rangoEdad" => isset($row['carteraEtl_rangoEdad']) ? quickEncode($row['carteraEtl_rangoEdad'], true) : '',
                    "cre_ingresos" => $row['carteraEtl_ingresos'] ?? '',
                    "cre_rangoIngresos" => onlyChars($row['carteraEtl_rangoIngresos'] ?? ''),
                    "cre_fechaultimoMov" => $fecha_ultimomov,
                    "cre_valorCuota" => (string) (trim($row['carteraEtl_valorCuota'] ?? '') !== '' ? $row['carteraEtl_valorCuota'] : 0),
                    "cre_porcentajePagado" => (double) (trim($row['carteraEtl_porcentajePagado'] ?? '') !== '' ? $row['carteraEtl_porcentajePagado'] : 0),
                    "cre_fechaUltimoPago" => 0,
                    "cre_fechaDesembolso" => $fechaDesembolso,
                    "cre_nombreArchivo" => '',
                    "cre_pagado" => 0,
                    "cre_abono" => 0,
                    "cre_inactivo" => 0,
                    "cre_periodo" => (int) $periodo,
                    "cre_fechaPeriodo" => (int) $fechaPeriodo,
                    "cre_productos" => $productoGuardar,
                    "cre_fecha" => 0,
                    "cre_numServicio" => "0",
                    "cre_ride" => "",
                    "cre_fechaFinPeriodo" => 0,
                    "cre_estadoGestion" => "",
                    "cre_asignadoCargaId" => $agente,
                    "cre_asignadoCargaNombre" => $agenteNombre,
                    "cre_asignadoActualId" => 0,
                    "cre_asignadoActualNombre" => "",
                    'cre_tipoCredito' => trim($row['carteraEtl_tipoCredito'] ?? ''),
                    'cre_codigo' => trim($row['carteraEtl_operacion'] ?? ''),
                    'cre_etapa' => (int) $etapa,
                    'cre_saldoCapital' => (double) ($row['carteraEtl_saldoCapital'] ?? 0),
                    'cre_gastosCobranza' => (double) ($row['carteraEtl_gastosCobranza'] ?? 0),
                    'cre_cuotasAtrasadas' => (int) ($row['carteraEtl_cuotasAtrasadas'] ?? 0),
                    'cre_interesMora' => (double) ($row['carteraEtl_interesMora'] ?? 0),
                    'cre_infoAdicional' => $row['carteraEtl_infoAdicional'] ?? 'NA',
                    'cre_producto' => $producto,
                    'cre_calificacion' => $calificacion,
                    'cre_acreedor' => $row['carteraEtl_acreedor'] ?? '',
                    'cre_asignacionPrioridad' => 0,
                    'cre_fechaActualizacion' => 0,
                    'cre_agencia' => (string) isset($row['carteraEtl_agencia']) ? $row['carteraEtl_agencia'] : '',
                    'cre_capitalVencido' => (float) ($row['carteraEtl_capitalVencido'] ?? 0),
                    'cre_interesVencido' => (float) ($row['carteraEtl_interesVencido'] ?? 0),
                    'cre_interesDesfazVencido' => (float) ($row['carteraEtl_interesDesfazVencido'] ?? 0),
                    'cre_moraVencido' => (float) ($row['carteraEtl_moraVencido'] ?? 0),
                    'cre_comisionCobranzasVencido' => (float) ($row['carteraEtl_comisionCobranzasVencido'] ?? 0),
                    'cre_rubrosVencido' => (float) ($row['carteraEtl_rubrosVencido'] ?? 0),
                    'cre_liberacionVencido' => (float) ($row['carteraEtl_liberacionVencido'] ?? 0),
                    'cre_infoAdicional1' => (string) ($row['carteraEtl_infoAdicional1'] ?? ''),
                    'cre_capitalPendiente' => (double) ($row['carteraEtl_capitalPendiente'] ?? 0),
                    'cre_interesPendiente' => (double) ($row['carteraEtl_interesPendiente'] ?? 0),
                    'cre_interesDesfazPendiente' => (double) ($row['carteraEtl_interesDesfazPendiente'] ?? 0),
                    'cre_capitalPorVencer' => (double) ($row['carteraEtl_capitalPorVencer'] ?? 0),
                    'cre_interesPorVencer' => (double) ($row['carteraEtl_interesPorVencer'] ?? 0),
                    'cre_interesDesfazPorVencer' => (double) ($row['carteraEtl_interesDesfazPorVencer'] ?? 0),
                    'cre_moraPorVencer' => (double) ($row['carteraEtl_moraPorVencer'] ?? 0),
                    'cre_comisionCobranzasPorVencer' => (double) ($row['carteraEtl_comisionCobranzasPorVencer'] ?? 0),
                    'cre_rubrosPorVencer' => (double) ($row['carteraEtl_rubrosPorVencer'] ?? 0),
                    'cre_liberacionPorVencer' => (double) ($row['carteraEtl_liberacionPorVencer'] ?? 0),
                    'cre_totalPorVencer' => (double) ($row['carteraEtl_totalPorVencer'] ?? 0),
                    'cre_cuotasPagadas' => (int) ($row['carteraEtl_cuotasPagadas'] ?? 0),
                    'cre_cuotasPorVencer' => (int) ($row['carteraEtl_cuotasPorVencer'] ?? 0),
                    'cre_diasVencido' => (int) ($row['carteraEtl_diasMora'] ?? ''),
                    'cre_diaCorte' => (int) ($row['carteraEtl_diaCorte'] ?? 0),
                    'cre_oficialCreditoId' => (string) ($row['carteraEtl_oficialCredito'] ?? ''),
                    'cre_asesorComercial' => (string) ($row['carteraEtl_asesorComercial'] ?? ''),
                    'cre_oficialIngreso' => (string) ($row['carteraEtl_oficialIngreso'] ?? ''),
                    'cre_ciudad' => (string) $region,
                    'cre_concesionario' => (string) ($row['carteraEtl_concesionario'] ?? ''),
                    'cre_tipoAuto' => (string) ($row['carteraEtl_tipo'] ?? ''),
                    'cre_claseAuto' => (string) ($row['carteraEtl_clase'] ?? ''),
                    'cre_motor' => (string) ($row['carteraEtl_sublinea'] ?? ''),
                    'cre_dispositivoAuto' => (string) ($row['carteraEtl_motor'] ?? ''),
                    'cre_numeroSolicitud' => (string) ($row['carteraEtl_numeroSolicitud'] ?? ''),
                    'cre_actividad' => (string) ($row['carteraEtl_actividad'] ?? ''),
                    'cre_tipoGarantia' => (string) ($row['carteraEtl_tipoGarantia'] ?? ''),
                    'cre_ifiAsignada' => (string) (trim($row['carteraEtl_ifiAsignada'] ?? '')),
                    'cre_estadoOpercion' => (string) ($row['carteraEtl_estadoOpercion'] ?? ''),
                    'cre_fechaGeneracion' => (int) ($row['carteraEtl_fechaGeneracion'] ?? 0),
                    'cre_fechaIniciaCredito' => (int) isset($row['carteraEtl_fechaIniciaCredito']) ? $row['carteraEtl_fechaIniciaCredito'] : 0,
                    'cre_fechaMasVencida' => (int) ($row['carteraEtl_fechaMasVencida'] ?? 0),
                    'cre_tipoNegocio' => (string) ($row['carteraEtl_tipoNegocio'] ?? ''),
                    'cre_ramo' => (string) ($row['carteraEtl_ramo'] ?? ''),
                    'cre_codigoTipoAgente' => (string) ($row['carteraEtl_codigoTipoAgente'] ?? ''),
                    'cre_codigoAsegurado' => (string) ($row['carteraEtl_codigoAsegurado'] ?? ''),
                    'cre_polizaCarga' => (string) ($row['carteraEtl_poliza'] ?? ''),
                    'cre_endoso' => (string) ($row['carteraEtl_endoso'] ?? ''),
                    'cre_facultativo' => (string) ($row['carteraEtl_facultativo'] ?? ''),
                    'cre_fechaVigenciaDesde' => (int) isset($row['carteraEtl_fechaVigenciaDesde']) ? $row['carteraEtl_fechaVigenciaDesde'] : 0,
                    'cre_fechaVigenciaHasta' => (string) ($row['carteraEtl_fechaVigenciaHasta'] ?? ''),
                    'cre_anioDeuda' => (string) ($row['carteraEtl_anioDeuda'] ?? ''),
                    'cre_valorCartera' => (string) ($row['carteraEtl_valorCartera'] ?? 0),
                    'cre_conducto' => (string) ($row['carteraEtl_conducto'] ?? ''),
                    'cre_snGrupo' => (string) ($row['carteraEtl_snGrupo'] ?? ''),
                    'cre_tipoAsegurado' => (string) ($row['carteraEtl_tipoAsegurado'] ?? ''),
                    'cre_primaNeta' => (double) ($row['carteraEtl_primaNeta'] ?? 0),
                    'cre_pais' => (string) ($row['carteraEtl_pais'] ?? ''),
                    'cre_nroPatente' => (string) ($row['carteraEtl_nroPatente'] ?? ''),
                    'cre_linea' => (string) $linea,
                    'cre_sublinea' => (string) $sublinea,
                    'cre_marca' => (string) $marca,
                    'cre_anioVh' => (int) ($row['carteraEtl_anioVehiculo'] ?? 0),
                    'cre_recargoPendiente' => (double) ($row['carteraEtl_recargoPendiente'] ?? 0),
                    'cre_recargoVencido' => (double) ($row['carteraEtl_recargoVencido'] ?? 0),
                    'cre_recargoPorVencer' => (double) ($row['carteraEtl_recargoPorVencer'] ?? 0),
                    'cre_color' => (string) ($row['carteraEtl_color'] ?? ''),
                    'cre_placa' => (string) ($row['carteraEtl_placa'] ?? ''),
                    'cre_valorFinanciar' => (double) ($row['carteraEtl_valorFinanciar'] ?? 0),
                    'cre_valorVehiculo' => (double) ($row['carteraEtl_valorVehiculo'] ?? 0),
                    'cre_tipoGarantiaDesc' => (string) ($row['carteraEtl_tipoGarantiaDesc'] ?? ''),
                    'cre_valorDevolucion' => (float) ($row['carteraEtl_valorDevolucion'] ?? 0),
                    'cre_meta' => (float) ($row['carteraEtl_meta'] ?? 0),
                    'cre_porcentaje' => (float) ($row['carteraEtl_porcentaje'] ?? 0),
                    'cre_status' => (int) ($row['carteraEtl_status'] ?? 0),
                    'cre_diaProbable' => (int) ($diaProbable),
                    'cre_mejorBandaHoraria' => (int) ($mejorBandaHoraria),
                    'cre_regularidad' => (string) ($regularidadCedula)
                ];

                $conditionFact = array(
                    'cre_carteraId' => (string) $creditoGuardar['cre_carteraId'],
                    'cre_cedula' => (string) $creditoGuardar['cre_cedula'],
                    'cre_factura' => (string) $creditoGuardar['cre_factura'],
                );

                if ($mongo2->buscar('cbCreditos', $conditionFact)) {
                    $existeCredito = $mongo2->siguiente();
                    unset($cbCreditosActivos[$existeCredito['_id']->__toString()]);
                    if (!isset($existeCredito['cre_cantidadGest']) || $existeCredito['cre_cantidadGest'] == 0) {
                        $creditoGuardar['cre_cantidadGest'] = 0;
                    } else {
                        $creditoGuardar['cre_cantidadGest'] = $existeCredito['cre_cantidadGest'];
                    }
                    $creditoGuardar['cre_fechaActualizacion'] = $tInicio;
                    $creditoGuardar['cre_inactivo'] = 0;
                    $creditoGuardar['cre_fechaActMysql'] = strtotime(date('Ymd 00:00:00', time()));  //CAMBIADO
                    $creditoGuardar['cre_fechaCarga'] = $tInicio; //Se agrega para que se actualice siempre

                    $r = $mongo2->actualizar('cbCreditos', ['_id' => $existeCredito['_id']], $creditoGuardar);
                    pasodetcargadiaria($row, $mongo2, 0);
                } else {
                    $creditoGuardar["cre_deudaNetaInicial"] = (double) $deuda_original;
                    $creditoGuardar["cre_deudaOriginal"] = (double) $deudaOriginal;
                    $creditoGuardar['cre_fechaCarga'] = $tInicio;
                    $creditoGuardar['cre_fechaActMysql'] = strtotime(date('Ymd 00:00:00', time()));  //CAMBIADO
                    // Modificaciones
                    $r2 = $mongo2->buscar('cbCreditos', ['cre_carteraId' => (string) $idCartera, 'cre_fechaCargaInicial' => ['$gte' => $desde]], [], ['cre_fechaCargaInicial' => 1], 1);
                    if ($r2 > 0) {
                        $feccre = $mongo2->siguiente();
                        $creditoGuardar['cre_fechaCargaInicial'] = (int) $feccre['cre_fechaCargaInicial'];
                    } else {
                        $creditoGuardar['cre_fechaCargaInicial'] = $tInicio;
                    }
                    //if ($r == 0) {
                    $r = $mongo2->guardar('cbCreditos', $creditoGuardar);
                    pasodetcargadiaria($row, $mongo2, 1);
                }
            } else {
                trigger_error("{$factura} ::: Factura vacia");
            }
            foreach ($direccionGuardarUsuario as $val) {
                $criterio = [
                    'dir_cedula' => $cedula,
                    'dir_id' => (int) $val['dir_id']
                ];
                $cu = $mongo1->buscar('cbDirecciones', $criterio);
                if ($cu == 0) {
                    $mongo1->guardar('cbDirecciones', $val);
                }
            }
            $existeMongo = existeUsurioenMongo($cedula);
            if ($existeMongo === 0) {
                $arrayMongo = [
                    "usUsuarios_id" => (int) $usUsuariosid,
                    "crm_nombres" => $nombres,
                    "crm_apellidos" => $apellidos,
                    'crm_primerNombre' => (string) trim($primerNombre),
                    'crm_segundoNombre' => (string) trim($segundoNombre),
                    'crm_apellidoPaterno' => (string) trim($apellidoPaterno),
                    'crm_apellidoMaterno' => (string) trim($apellidoMaterno),
                    "crm_cedula" => $cedula,
                    "crm_tipoDocumento" => strtoupper(trim(onlyChars($row['carteraEtl_tipoIdentidad'] ?? ''))) ?: 'NATURAL',
                    "crm_edad" => $row['carteraEtl_edad'] ?? '',
                    "crm_perfil" => isset($row['carteraEtl_perfil']) ? onlyChars($row['carteraEtl_perfil']) : '',
                    "crm_tiprel" => onlyChars($row['carteraEtl_tiprel'] ?? ''),
                    "crm_cargo" => isset($row['carteraEtl_cargo']) ? $row['carteraEtl_cargo'] : '',
                    "crm_nacionalidad" => "",
                    "crm_profesion" => "",
                    "crm_residencia" => [],
                    "crm_empresa" => []
                ];
                $seProceso = creaoActualizaUsuarioMongo("CRM", $cedula, $arrayMongo);
            }
            $lin++;
        } else {
            trigger_error("{$cedula} ::: Cedula vacia");
        }
        $mongo33->actualizar($coleccionDeCarga, ['_id' => $row['_id']], ['carteraEtl_procesado' => 1]);
    }

    if ($cur == $limit) {
        sleep(1);
        goto inicio;
    }

    //ya termino toda la carga, entonces procedo a inactivar los cbCreditos que no llegaron en la carga de hoy
    //Se aumenta parámetro inactivaCartera, si es 1, se inactivan los registros que no vinieron en la carga, si es 0, no hace nada
    if (strtoupper(trim($inactivaCartera)) === 'INACTIVAR') {
    foreach ($cbCreditosActivos as $cred) {
        $mongo->actualizar('cbCreditos', ['_id' => $cred['_id']], ['cre_inactivo' => 1]);
    }
    }

    // Desactivar funcionalidad a pedido de Any debido a que se va manejar manualmente.
    // foreach ($listaPeriodos as $key => $p) {
    //     $c = $mongo2->buscar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => (int) $p, 'activo' => 1, 'cartera' => (int) $idCartera]);
    //     if ($c == 0) {
    //         $mongo2->actualizar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => ['$lt' => $p]], ['activo' => 0]);
    //         $mongo2->guardar('control_carga_periodo', ['periodo' => (int) $key, 'fecha' => (int) $p, 'activo' => 1, 'cartera' => (int) $idCartera, 'fechaFin' => (int) $p, 'fechaCarga' => (int) $tInicio]);
    //     }
    // }
}

function actCreditosMySQL(int $carteraId): void {

    $db = new MYSQLDB();
    $db1 = new MYSQLDB();
    $mongo = new MYMONGODB();
    $coleccion = 'cbCreditos';
    $cont = 0;
    $contUpd = 0;

    $db1->query($db1->mkSQL("UPDATE cbcreditos SET cre_inactivo=%N WHERE cre_carteraId = %Q", 1, $carteraId));

    $t = $mongo->buscar($coleccion, ['cre_carteraId' => (string) $carteraId, 'cre_inactivo' => 0]);
    echo SALTO_DE_LINEA . "Inicio de actualización cbCreditos MySql " . $t . SALTO_DE_LINEA . "" . SALTO_DE_LINEA;
    $lin = 0;
    while ($row = $mongo->siguiente()) {
        $lin++;
        $row['cre_cantidadGest'] = isset($row['cre_cantidadGest']) ? $row['cre_cantidadGest'] : 0;
        $sql1 = $db1->mkSQL("SELECT cre_id FROM cbcreditos where cre_factura=%Q and cre_carteraId=%N;", $row['cre_factura'], $row['cre_carteraId']);
        $existe = $db1->query($sql1);
        if ($existe == 0) {
            $db->query(
                    $db->mkSQL("INSERT INTO cbcreditos 
                            (cre_pagado,cre_periodo,cre_provincia,cre_usUsuarios_id,cre_etapa,cre_diasMoraFactura,
                            cre_tramoSaldo,cre_deudaNeta,cre_tramoMora,cre_asignacionPrioridad,
                            cre_cantidadGest,cre_nombres,cre_asignadoCargaId,cre_cedula,cre_nombreCartera,cre_asignadoCargaNombre,
                            cre_calificacion,cre_factura,cre_fecha,cre_producto,cre_tipoCredito,
                            cre_fechaPeriodo,cre_agencia,cre_apellidos,cre_inactivo,cre_carteraId,cre_estadoBien,cre_status,cre_compraConRecurso,
                            cre_diaProbable,cre_mejorBandaHoraria,cre_regularidad, cre_marca, cre_fechaCarga)
                            VALUES (%N,%N,%Q,%N,%N,%N,%Q,%N,%Q,%N,%N,%Q,%N,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%Q,%N,%Q,%Q,%Q,%N,%N,%Q,%Q,%Q,%N)",
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
                            $row['cre_estadoBien'] ?? '',
                            $row['cre_status'] ?? 0,
                            $row['cre_compraConRecurso'] ?? 0,
                            $row['cre_diaProbable'],
                            $row['cre_mejorBandaHoraria'],
                            $row['cre_regularidad'],
                            $row['cre_marca'],
                            $row['cre_fechaCarga']
                    )
            );
            $cont++;
        } else {
            $sql = $db->mkSQL("UPDATE cbcreditos SET 
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
                cre_status=%N,
                cre_diaProbable=%N,
                cre_mejorBandaHoraria=%Q,
                cre_regularidad=%Q,
                cre_marca=%Q,
                cre_fechaCarga=%N
                WHERE cre_factura = %Q AND cre_carteraId = %Q ",
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
                    $row['cre_estadoBien'] ?? '',
                    $row['cre_periodo'],
                    $row['cre_fechaPeriodo'],
                    $row['cre_compraConRecurso'] ?? 0,
                    $row['cre_status'],
                    $row['cre_diaProbable'],
                    $row['cre_mejorBandaHoraria'],
                    $row['cre_regularidad'],
                    $row['cre_marca'],
                    $row['cre_fechaCarga'],
                    $row['cre_factura'],
                    $row['cre_carteraId']
            );
            $db->query($sql);
            $contUpd++;
        }
    }
    //CAMBIADO $mongo->actualizar($coleccion, ['cre_carteraId' => (string) $carteraId, 'cre_fechaActMysql' => 999], ['cre_fechaActMysql' => strtotime(date('Ymd 00:00:00', time()))]);
    $mongo->guardar('cbCargaEtlRegistroDiario', ['fecha' => time(), 'fechaStr' => (string) date('dmY', time()), 'cartera' => (int) $carteraId, 'insertados' => (int) $cont, 'actualizados' => (int) $contUpd]);
    echo SALTO_DE_LINEA . "Registros insertados: " . $cont . " Registros actualizados: " . $contUpd . ' | ' . date('d-m-Y H:i:s', time()) . SALTO_DE_LINEA . "" . SALTO_DE_LINEA;
}

function pasodetcargadiaria($row, $mongo, int $inicial) {
    $fecha = strtotime(date('Y-m-d 00:00:00', time()));
    $fecStr = date('dmY', $fecha);
    $c = $mongo->buscar('cbCargaDetallePacifico', ['carga' => (string) $fecStr, 'factura' => (string) $row['carteraEtl_factura']]);
    if ($c === 0) {
        $a = [
            "cedula" => (string) $row['carteraEtl_cedula'],
            "primerNombre" => $row['carteraEtl_primerNombre'],
            "segundoNombre" => isset($row['carteraEtl_segundoNombre']) ? $row['carteraEtl_segundoNombre'] : '',
            "apellidoPaterno" => $row['carteraEtl_apellidoPaterno'] ?? '',
            "apellidoMaterno" => isset($row['carteraEtl_apellidoMaterno']) ? $row['carteraEtl_apellidoMaterno'] : '',
            "factura" => (string) $row['carteraEtl_factura'],
            "carga" => (string) $fecStr,
            "cargaUT" => (int) $fecha,
            "inicial" => $inicial,
            "deudaNeta" => (float) ($row['carteraEtl_deudaNeta'] ?? 0),
            "saldoCapital" => (float) (isset($row['carteraEtl_saldoCapital']) ? $row['carteraEtl_saldoCapital'] : 0),
            "diasMora" => (int) ($row['carteraEtl_diasMora'] ?? 0),
            "fechaPeriodo" => (int) ($row['carteraEtl_fechaPeriodo'] ?? 0),
            "periodo" => (int) ($row['carteraEtl_periodo'] ?? 0),
            "producto" => $row['carteraEtl_producto'],
            "carteraId" => (int) $row['carteraEtl_carteraId'],
            "carteraNombre" => $row['carteraEtl_carteraNombre'],
        ];
        $mongo->guardar('cbCargaDetallePacifico', $a);
    }
}

function crearDirecciones($relId, $reltable, $provinciaId, $tipoDireccion, $barrio, $direccion, $observacion = "") {
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT * FROM usdireccion
		WHERE usDireccion_provinciaId=%N
		AND usDireccion_relTable=%Q
        AND usDireccion_relId=%N
		AND usDireccion_tipo=%Q
		AND usDireccion_barrio=%Q
        AND usDireccion_observacion=%Q
        AND usDireccion_callePrincipal=%Q"
            ,
            $provinciaId,
            $reltable,
            $relId,
            $tipoDireccion,
            $barrio,
            $observacion,
            $direccion
    );
    $respuesta = $db->query($sql, 1, 0);
    if (!$respuesta) {
        $idDireccion = $db->query($db->mkSQL("INSERT INTO usdireccion (usDireccion_provinciaId, 
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

function insertarTelefonos($relId, $relTable, $area, $telefono, $comentario) {
    $db = new MYSQLDB();
    $respuesta = $db->query($db->mkSQL("SELECT usTelfs_id FROM ustelfs
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

function insertaTelefonosMongo($cedula, $telefono, $data) {
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

function creaoActualizaUsuarioMongo($coleccion, $cedula, $arrayMongo) {
    $mongo = new MYMONGODB();
    $cursor = $mongo->buscar($coleccion, ["crm_cedula" => (string) $cedula]);
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

function existeUsurioenMongo($cedula) {
    $mongo = new MYMONGODB();
    $criterioAccion = array("crm_cedula" => (string) $cedula);
    $coleccion = "CRM";
    $cursor = $mongo->buscar("CRM", $criterioAccion);
    return $cursor;
}

function buscaCedula($cedula) {
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT usUsuarios_id FROM ususuarios
        WHERE usUsuarios_cedula=%Q", $cedula);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['usUsuarios_id'];
    } else {
        return 0;
    }
}

function buscaUsuarioxId($id) {
    $db = new MYSQLDB();
    $sql = $db->mkSQL("SELECT concat(usUsuarios_apellidos,' ',usUsuarios_nombres) as nombre
        FROM ususuarios WHERE usUsuarios_id =" . $id);
    if ($db->query($sql)) {
        $row = $db->fetchRow();
        return $row['nombre'];
    } else {
        return "";
    }
}

function creaoActualizaUsuario($cedula, $nombres, $apellidos) {
    $db = new MYSQLDB();
    $id = buscaCedula($cedula);
    if ($id > 0) {
        $db->query($db->mkSQL("UPDATE ususuarios SET
            usUsuarios_apellidos=%Q,
            usUsuarios_nombres=%Q
            WHERE usUsuarios_id=%N",
                        trim(substr($apellidos, 0, 99)),
                        trim(substr($nombres, 0, 99)),
                        $id
                ));
        return (int) $id;
    } else {
        //creo nuevo usuario
        $idUsuario = $db->query($db->mkSQL("INSERT INTO ususuarios
            (usUsuarios_activo,
            usUsuarios_cedula,
            usUsuarios_apellidos,
            usUsuarios_nombres,
            usUsuarios_pais,
            usUsuarios_idioma,
            usUsuarios_createdOn
            ) VALUES (%N,%Q,%Q,%Q,%Q,%Q,%N)", 1, $cedula, trim(substr($apellidos, 0, 99)), trim(substr($nombres, 0, 99)), "EC", "ES", time()));
        return (int) $idUsuario;
    }
}

function limpiarCaracteresEspeciales($string) {
    $string = htmlentities($string);
    $string = preg_replace('/\&(.)[^;]*;/', '\\1', $string);
    return $string;
}

function limpiarCaracteresEspeciales1(string $string): string {
    // Forzar UTF-8 válido
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    $map = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ñ' => 'n', 'Ñ' => 'N',
        'ç' => 'c', 'Ç' => 'C',
        'Ý' => 'Y', 'ý' => 'y',
        "\n" => '', "\r" => '',
        '[' => '', '^' => '', '´' => '', '`' => '', '¨' => '', '~' => '', ']' => '', '³' => '',
        '&' => ' ',
        ';' => '', '"' => '',
        'null' => '', 'Null' => '', 'NULL' => '',
        '&aacute;' => 'a', '&Aacute;' => 'A',
        '&eacute;' => 'e', '&Eacute;' => 'E',
        '&iacute;' => 'i', '&Iacute;' => 'I',
        '&oacute;' => 'o', '&Oacute;' => 'O',
        '&uacute;' => 'u',
    ];
    return strtr($string, $map);
}

function onlyChars(string $string): string {
    $string = limpiarCaracteresEspeciales1($string);
    // Filtramos solo letras, números, y símbolos permitidos
    // Letras (a-z, A-Z), números (0-9), espacios y los símbolos que tenías: / . , - _ ( ) # " ' ! * % + &
    $result = preg_replace('/[^0-9A-Za-z \/\.\,\-\_\(\)\#\"\'\!\*\%\+\&\x0D\x0A\xA4\xA5]/u', '', $string);
    if ($result === null) {
        trigger_error("{$string} ::: Error en preg_replace: " . preg_last_error());
    }
    return $result;
}

function getCreditoFacturaLista($factura, $cbCreditosTodos, $fechaPeriodo, $cbCreditosActivos) {
    for ($i = 0; $i < count($cbCreditosTodos[$fechaPeriodo]); $i++) {
        if ($cbCreditosTodos[$fechaPeriodo][$i]['cre_factura'] === (string) $factura) {
            //devuelva el registro encontrado
            $encontrado = $cbCreditosTodos[$fechaPeriodo][$i];
            unset($cbCreditosActivos[$encontrado['_id']->__toString()]);
            return $encontrado;
        }
    }
    return false; //no se encontro este credito, debe ser nuevo registro
}

//function getCreditoFactura($carteraId, $identificacion, $operacion, $fechaPeriodo, $mongo)
//{
//    $credito = [];
//    if ($fechaPeriodo > 0) {
//        $condition = [
//            'cre_carteraId' => (string) $carteraId,
//            "cre_factura" => (string) $operacion,
//            "cre_fechaPeriodo" => (int) $fechaPeriodo
//            // "cre_inactivo" => 0,
//        ];
//    } else {
//        $condition = [
//            'cre_carteraId' => (string) $carteraId,
//            "cre_factura" => (string) $operacion,
//            // "cre_inactivo" => 0
//        ];
//    }
//    $r = $mongo->buscar('cbCreditos', $condition);
//    if ($r > 0) {
//        $row = $mongo->siguiente();
//        $credito = $row;
//    }
//    return $credito;
//}

function obtenerTelf(string $telfs): array {
    $telfs = trim($telfs);
    if (strlen($telfs) < 8)
        return [];

    $datos = [];
    // Extraemos todos los bloques de números (ignora caracteres no numéricos)
    preg_match_all('/\d+/', $telfs, $matches);

    foreach ($matches[0] as $a) {
        // Normalizaciones de prefijos
        if (substr($a, 0, 2) === '00')
            $a = substr($a, 1);
        if (substr($a, 0, 2) === '00')
            $a = substr($a, 1);

        if (strlen($a) === 7)
            $a = '02' . $a;       // Teléfono local
        if (substr($a, 0, 3) === '593')
            $a = '0' . substr($a, 3);

        // Solo aceptamos números de 8 a 12 dígitos
        if (strlen($a) < 8 || strlen($a) > 12)
            continue;

        // Validación de Ecuador
        if (!expect_phone_EC($a))
            continue;

        // Filtramos números repetidos (como 000000, 111111, etc.)
        if (preg_match('/(\d)\1{5,}/', $a))
            continue;

        // Agregamos al array si no está repetido
        if (!in_array($a, $datos))
            $datos[] = $a;
    }

    return $datos;
}

function calificacion($mora, $producto, $carteraId, $tramosPorProducto, $tramosPorCartera) {
    $tramos = [];

    // Elegir solo tramos relevantes
    if ($producto !== '' && isset($tramosPorProducto[$producto])) {
        $tramos = $tramosPorProducto[$producto];
    } elseif ($carteraId !== '' && isset($tramosPorCartera[$carteraId])) {
        $tramos = $tramosPorCartera[$carteraId];
    }

    // Recorrer solo los tramos filtrados
    foreach ($tramos as $tr) {
        $t = '';
        $inicio = $tr['tr_tramoInicio'];
        $fin = $tr['tr_tramoFin'];

        if ($inicio != 9999999 && $fin != 9999999 && $mora >= $inicio && $mora <= $fin) {
            $t = $tr['tr_tramo'];
        } elseif ($fin == 9999999 && $mora >= $inicio) {
            $t = $tr['tr_tramo'];
        } elseif ($inicio == 9999999 && $mora <= $fin) {
            $t = $tr['tr_tramo'];
        }

        if ($t !== '') {
            return [
                'ini' => $inicio,
                'fin' => $fin,
                'calificacion' => $t,
                'etapa' => $tr['tr_etapaValor'] ?? 0
            ];
        }
    }

    return null; // No coincidió ningún tramo
}

function procesarDireccionUsuario(
        $usUsuariosid,
        $cedula,
        $nombres,
        $apellidos,
        $idCartera,
        $provinciaId,
        $provincia,
        $ciudad,
        $canton,
        $parroquia,
        $region,
        $barrio,
        $direccion,
        $tipoCarga,
        &$direccionGuardarUsuario,
        &$retVal,
        $orden = 0,
        $referencia_domicilio = ""
) {
    if (trim($direccion) === "")
        return;

    $tipoDir = ($orden === 0) ? "Direccion" : "Direccion2";
    $idDireccion = crearDirecciones(
            $usUsuariosid,
            "ususuarios",
            $provinciaId,
            "Carga plugin class.pgCargaCartera",
            $barrio,
            $direccion,
            $tipoDir
    );

    if (is_numeric($idDireccion)) {
        $direccionGuardarUsuario[] = [
            "usUsuarios_id" => (int) $usUsuariosid,
            "dir_cedula" => (string) $cedula,
            "dir_nombres" => $nombres,
            "dir_apellidos" => $apellidos,
            "dir_car_id" => (string) $idCartera,
            "dir_ref_interrelacionId" => 0,
            "dir_ref_usUsuarios_id" => 0,
            "dir_ref_tipo" => "",
            "dir_ref_nombre" => "",
            "dir_ref_apellido" => "",
            "dir_ref_nombreCompleto" => "",
            "dir_ref_cedula" => "",
            "dir_ref_empTiempo" => "",
            "dir_ref_empActividad" => "",
            "dir_ref_empCargo" => "",
            "dir_ref_empProfesion" => "",
            "dir_id" => (int) $idDireccion,
            "dir_direccion" => $direccion,
            "dir_direccionCallePrincipal" => "",
            "dir_direccionTrasversal" => "",
            "dir_direccionNumero" => "",
            "dir_tipo" => "RESIDENCIA",
            "dir_orden" => ($tipoDir === "Direccion2") ? (int) (time() . substr(microtime(), 2, 8)) : (int) $orden,
            "dir_provincia" => $provincia,
            "dir_ciudad" => $ciudad,
            "dir_canton" => $canton,
            "dir_parroquia" => $parroquia,
            "dir_region" => $region,
            "dir_barrio" => $barrio,
            "dir_latitud" => 0,
            "dir_longitud" => 0,
            "dir_equivocada" => "",
            "dir_referencia" => ($tipoDir === "Direccion2") ? "" : $referencia_domicilio,
            "dir_tipoCarga" => (string) $tipoCarga
        ];
    } else {
        $retVal .= "<br> No se pudo crear la {$tipoDir} en usdireccion del deudor $nombres $apellidos";
    }
}

echo "EJECUCION_COMPLETA";
//_FIN_DE_ARCHIVO ?>