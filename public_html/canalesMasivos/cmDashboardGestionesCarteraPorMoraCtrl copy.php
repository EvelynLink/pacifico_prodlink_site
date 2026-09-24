<?php

use function PHPSTORM_META\map;

require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
ini_set("max_execution_time", "1800000");
ini_set("memory_limit", "8096M");

if (!isset($_REQUEST["act"])) {
    exit;
}
global $Central;
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

$cnf = getConf("Canales Masivos");
$estadosConfig = $cnf["Dashboard equivalencias estados"];
$sentimientosConfig = $cnf["Dashboard equivalencias sentimientos"];
$desconexionConfig = $cnf["Dashboard equivalencias desconexion"];
$paletaColor = $cnf["Dashboard paleta colores"];
$traduceEstados = [];
foreach ($estadosConfig as $estado) {
    $partes = explode(":::", $estado);
    $traduceEstados[$partes[0]] = $partes[1];
}
$traducSentimiento = [];
foreach ($sentimientosConfig as $sentimiento) {
    $partes = explode(":::", $sentimiento);
    $traducSentimiento[$partes[0]] = $partes[1];
}
$traduceDesconexion = [];
foreach ($desconexionConfig as $estado) {
    $partes = explode(":::", $estado);
    $traduceDesconexion[$partes[0]] = $partes[1];
}

$separador = ";";
$saltoLinea = PHP_EOL;

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor") || $Central->conPermiso("Canales Masivos,Supervisor Cobranzas"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

$idsCarterasGeneral = obtenerCarterasPermitidas($permisos); //siempre se filtra por estas carteras, solo para los usuarios que no son desarrollo

switch ($act) {
    case "cargarTablaDias":
        #region obtenerdias carga 
        $d = jsonStart();
        $filtroTiempo = $d["filtroTiempo"];

        switch ($filtroTiempo) {
            case 'hora':
                // $desde = strtotime("-1 hour");
                // $hasta = time();
                $desde = strtotime(date("Y-m-d H") . ":00:00");
                $hasta = strtotime(date("Y-m-d H") . ":59:00");
                $campo = "av_fechaGeneraLlamada";
                break;
            case 'dia':
                $desde = strtotime(date("Y-m-d") . " 00:00:00");
                $hasta = strtotime(date("Y-m-d") . " 23:59:59");
                break;
            case 'ayer':
                $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                break;
            case 'semana':
                $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                break;
            case 'semanaanterior':
                $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                break;
            case 'mes':
                $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                break;
            case 'mesanterior':
                $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                break;
            case 'asigActual':
                $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                break;
            case 'asigAnterior':
                $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                break;
            case 'rango':
                $filtroDesde = expect_integer($d["filtroDesde"]);
                $filtroHasta = expect_integer($d["filtroHasta"]);
                $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                break;
        }
        $mongo = new MYMONGODB();
        $mongo->buscar('cbCargaDetallePacifico', ['cargaUT' => ['$gte' => $desde, '$lte' => $hasta]]);
        $filas = [];
        while ($row = $mongo->siguiente()) {
            $dia = (int) date('d', $row['cargaUT']);
            if (isset($filas[$dia])) {
                $filas[$dia]['total'] = $filas[$dia]['total'] + 1;
            } else {
                $filas[$dia] = ['dia' => (int) $dia, 'premora' => 0, 'nuevoPremora' => 0, 'vencida' => 0, 'nuevoVencida' => 0, 'total' => (int) 1];
            }
            if (strpos($row['carteraNombre'], 'PREMORA') !== false) {
                $filas[$dia]['premora'] = $filas[$dia]['premora'] + 1;
                if ($row['inicial'] == 1) {
                    $filas[$dia]['nuevoPremora'] = $filas[$dia]['nuevoPremora'] + 1;
                }
            } else {
                $filas[$dia]['vencida'] = $filas[$dia]['vencida'] + 1;
                if ($row['inicial'] == 1) {
                    $filas[$dia]['nuevoVencida'] = $filas[$dia]['nuevoVencida'] + 1;
                }
            }
        }
        $f = [];
        foreach ($filas as $val) {
            $f[] = $val;
        }
        $json = $f;
        break;
    case "obtenerParametrizacion":
        #region obtenerParametrizacion 
        $d = jsonStart();
        //filtros
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroAsignacion = expect_safe_html($d["filtroAsignacion"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));
        $error = 0;
        $mensaje = "";

        $periodo = [];

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    break;
            }
        }


        if ($filtroAsignacion != "" && $filtroAsignacion != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroAsignacion) {

                case 'asigActual':
                    $mongo = new MYMONGODB();
                    $condicionPeriodo = ['activo' => (int) 1, "cartera" => intval($filtroCartera)];

                    $periodo[] = ["id" => -1, "nombre" => 'Todos los ciclos'];
                    if (count($condicionPeriodo) > 0) {
                        $mongo->buscar("control_carga_periodo", $condicionPeriodo, [], ['periodo' => 1]);
                        while ($row = $mongo->siguiente()) {
                            $periodo[] = [
                                "id" => $row["periodo"],
                                "nombre" => (string) $row["periodo"] . ' - ' . date('d/m/Y', $row["fecha"]),
                                "fechaInicio" =>  date('d/m/Y', $row["fecha"]),
                                "fechaFin" => date('d/m/Y', $row["fechaFin"]),
                                "fechaInicioConv" =>  (int)$row["fecha"],
                                "fechaFinConv" => (int)$row["fechaFin"]
                            ];
                        }
                    }
                    break;

                case 'asigAnterior':
                    $mongo = new MYMONGODB();

                    $periodo[] = ["id" => -1, "nombre" => 'Todos los ciclos'];

                    if (intval($filtroCartera) > 0) {

                        // se toma el registro inactivo más reciente (el último período desactivado = el período inmediatamente anterior al actual)
                        $condicionPeriodo = [
                            'activo' => (int) 0,
                            "cartera" => intval($filtroCartera),
                            'fechaCreacion' => ['$gte' => 0]

                        ];

                        if ($filtroPeriodo >= 0) {
                            $condicionPeriodo['periodo'] = (int) $filtroPeriodo;
                        }

                        $mongo->buscar("control_carga_periodo", $condicionPeriodo, [], ['fecha' => -1], 1);

                        if ($row = $mongo->siguiente()) {
                            $periodo[] = [
                                "id" => $row["periodo"],
                                "nombre" => (string) $row["periodo"] . ' - ' . date('d/m/Y', $row["fecha"]),
                                "fechaInicio" =>  date('d/m/Y', $row["fecha"]),
                                "fechaFin" => date('d/m/Y', $row["fechaFin"]),
                                "fechaInicioConv" =>  (int)$row["fecha"],
                                "fechaFinConv" => (int)$row["fechaFin"]
                            ];
                        }
                    }
                    break;
            }
        }


        $mongo = new MYMONGODB();

        //Carteras
        $carteras = [];
        $carteras = [
            [
                "id" => "todo",
                "nombre" => "Todas las carteras"
            ]
        ];
        $mysql = new MYSQLDB();
        $tabla = "cobcartera";
        $carterasConfiguradas = [];
        $sql = $mysql->mkSQL("SELECT cobCartera_id,cobCartera_nombre FROM " . $tabla . " WHERE cobCartera_estado=%N AND cobCartera_tipo='COBRANZA'", 1);
        $mysql->query($sql);
        while ($row = $mysql->fetchRow()) {
            $carteras[] = [
                "id" => $row["cobCartera_id"],
                "nombre" => $row["cobCartera_nombre"]
            ];
        }



        $condicionFiltroDistinct = [];

        if ($filtroAsignacion != "" && $filtroAsignacion != "todo") {

            $condicionFiltroDistinct["cubAG_carteraId"] = strval($filtroCartera);


            if ($filtroPeriodo > 0) {
                $condicionFiltroDistinct["cubAG_ciclo"] = intval($filtroPeriodo);
            }



            if (!empty($periodo[1])) {

                $condicionFiltroDistinct["cubAG_fechaPeriodo"] = [
                    '$gte' => $periodo[1]["fechaInicioConv"],
                    '$lte' => $periodo[1]["fechaFinConv"]
                ];
            }
        }


        //Productos
        $prod = $mongo->buscarDistinct("cubAG_producto", 'cuAsignacionesGestionAP', $condicionFiltroDistinct);

        $productos = [];
        $productos = [
            [
                "id" => "todo",
                "nombre" => "Todos los productos"
            ]
        ];

        $productosUnicos = [];
        foreach ($prod as $p) {
            $nombre = strtoupper(trim($p));

            if (!isset($productosUnicos[$nombre])) {
                $productosUnicos[$nombre] = [
                    "id" => $nombre,
                    "nombre" => $nombre
                ];
            }
        }

        $productos = array_merge($productos, array_values($productosUnicos));


        //Marcas

        $marca = $mongo->buscarDistinct("cubAG_marca", 'cuAsignacionesGestionAP', $condicionFiltroDistinct);

        $marcas = [];
        $marcas = [
            [
                "id" => "todo",
                "nombre" => "Todas las marcas"
            ]
        ];

        $marcasUnicas = [];
        foreach ($marca as $p) {
            $nombre = strtoupper(trim($p));

            if (!isset($marcasUnicas[$nombre])) {
                $marcasUnicas[$nombre] = [
                    "id" => $nombre,
                    "nombre" => $nombre
                ];
            }
        }

        $marcas = array_merge($marcas, array_values($marcasUnicas));

        //Región

        $region = $mongo->buscarDistinct("cubAG_region", 'cuAsignacionesGestionAP', $condicionFiltroDistinct);

        $regiones = [];
        $regiones = [
            [
                "id" => "todo",
                "nombre" => "Todas las regiones"
            ]
        ];

        $regionesUnicas = [];
        foreach ($region as $p) {
            $nombre = strtoupper(trim($p));

            if (!isset($regionesUnicas[$nombre])) {
                $regionesUnicas[$nombre] = [
                    "id" => $nombre,
                    "nombre" => $nombre
                ];
            }
        }

        $regiones = array_merge($regiones, array_values($regionesUnicas));


        //Cantón

        $canton = $mongo->buscarDistinct("cubAG_canton", 'cuAsignacionesGestionAP', $condicionFiltroDistinct);

        $cantones = [];
        $cantones = [
            [
                "id" => "todo",
                "nombre" => "Todos los cantones"
            ]
        ];

        $cantonesUnicos = [];
        foreach ($canton as $p) {
            $nombre = strtoupper(trim($p));

            if (!isset($cantonesUnicos[$nombre])) {
                $cantonesUnicos[$nombre] = [
                    "id" => $nombre,
                    "nombre" => $nombre
                ];
            }
        }

        $cantones = array_merge($cantones, array_values($cantonesUnicos));

        //Fechas carga

        $fechaCarga = $mongo->buscarDistinct("cubAG_fechaCarga", 'cuAsignacionesGestionAP', $condicionFiltroDistinct);

        $fechasCarga = [];
        $fechasCarga = [
            [
                "id" => "todo",
                "nombre" => "Fechas de Carga"
            ]
        ];

        $fechasCargaUnicos = [];
        foreach ($fechaCarga as $p) {

            // VALIDAR MAYOR A 0
            if (intval($p) > 0) {

                $timestamp = intval($p);

                // FORMATEAR FECHA
                $nombre = date('d/m/Y', $timestamp);

                // EVITAR DUPLICADOS
                if (!isset($fechasCargaUnicos[$nombre])) {

                    $fechasCargaUnicos[$nombre] = [
                        "id" =>  $p,
                        "nombre" => $nombre
                    ];
                }
            }
        }

        $fechasCarga = array_merge($fechasCarga, array_values($fechasCargaUnicos));





        $json["carteras"] = $carteras;
        $json["periodos"] = $periodo;
        $json["productos"] = $productos;
        $json["marcas"] = $marcas;
        $json["regiones"] = $regiones;
        $json["cantones"] = $cantones;
        $json["fechasCarga"] = $fechasCarga;
        $json["permisos"] = $permisos;
        $json["error"] = $error;
        $json["mensaje"] = $mensaje;
        #endregion 
        break;


    case "obtenerTotales":


        #region obtenerTotales 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);

        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);

        $filtroAsignacion = expect_safe_html($d["filtroAsignacion"]);
        $filtroMarca = expect_safe_html($d["filtroMarca"]);
        $filtroRegion = expect_safe_html($d["filtroRegion"]);
        $filtroCanton = expect_safe_html($d["filtroCanton"]);
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));
        $filtroDesde = expect_integer($d["filtroDesde"]);
        $filtroHasta = expect_integer($d["filtroHasta"]);

        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionHistAsignacion = establecerCondicionMaster("avHistAsig_carteraId");
        $condicionCubAsignacionAnt = establecerCondicionMaster("cubAG_carteraId");
        $condicionEvolucion = establecerCondicionMaster("cubAG_carteraId");

        $fechaIniPeriodo =  0;
        $fechaFinPeriodo = 0;

        $fechaIniPeriodoAnterior =  0;
        $fechaFinPeriodoAnterior = 0;

        $mongo = new MYMONGODB();




        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        if (!empty($condicionHistAsignacion["avHistAsig_carteraId"]['$in'])) {
            $condicionHistAsignacion["avHistAsig_carteraId"]['$in'] = array_map('strval', $condicionHistAsignacion["avHistAsig_carteraId"]['$in']);
        }

        if (!empty($condicionCubAsignacionAnt["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacionAnt["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacionAnt["cubAG_carteraId"]['$in']);
        }

        if (!empty($condicionEvolucion["cubAG_carteraId"]['$in'])) {
            $condicionEvolucion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionEvolucion["cubAG_carteraId"]['$in']);
        }


        if ($filtroAsignacion != "" && $filtroAsignacion != "ini") {

            switch ($filtroAsignacion) {

                case 'asigActual':
                    $mongo = new MYMONGODB();
                    $mongoAnt = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    $queryAnterior = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 1];

                        //Se aumenta condición para comparativa con periodo anterior
                        $queryAnterior = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0];

                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;

                            $queryAnterior['periodo'] = (int)$filtroPeriodo;
                        }

                        $mongo->buscar('control_carga_periodo', $query, [],  ['_id' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodo =  $ro['fecha'];
                        $fechaFinPeriodo = $ro['fechaFin'];


                        //Buscar período anterior para comparativa
                        $mongoAnt->buscar('control_carga_periodo', $queryAnterior, [],  ['_id' => -1], 1);
                        $roAnt = $mongoAnt->siguiente();
                        $desdeAnt = strtotime(date('Y-m-d', $roAnt['fecha']) . " 00:00:00");
                        $hastaAnt = strtotime(date("Y-m-d", $roAnt['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodoAnterior =  $roAnt['fecha'];
                        $fechaFinPeriodoAnterior = $roAnt['fechaFin'];
                    }
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    $mongoAnt = new MYMONGODB();

                    $query = establecerCondicionMaster("cartera");
                    $queryAnterior = establecerCondicionMaster("cartera");



                    if ($filtroCartera != "" && $filtroCartera != "todo") {

                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0];

                        $queryAnterior = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0];

                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                            $queryAnterior['periodo'] = (int)$filtroPeriodo;
                        }


                        $mongo->buscar('control_carga_periodo', $query, [],  ['fecha' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodo =  $ro['fecha'];
                        $fechaFinPeriodo = $ro['fechaFin'];

                        $queryAnterior['fecha'] = ['$lt' => $fechaIniPeriodo];

                        //Buscar período anterior para comparativa

                        $mongoAnt->buscar('control_carga_periodo', $queryAnterior, [],  ['fecha' => -1], 1);
                        $roAnt = $mongoAnt->siguiente();
                        $desdeAnt = strtotime(date('Y-m-d', $roAnt['fecha']) . " 00:00:00");
                        $hastaAnt = strtotime(date("Y-m-d", $roAnt['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodoAnterior =  $roAnt['fecha'];
                        $fechaFinPeriodoAnterior = $roAnt['fechaFin'];
                    }
                    break;
            }
        }

        if ($filtroTiempo != "" && $filtroTiempo != "ini") {

            switch ($filtroTiempo) {

                case 'rango':
                    $condicionHistAsignacion["avHistAsig_fechaCreacion"] = [
                        '$gte' => $filtroDesde,
                        '$lte' => $filtroHasta
                    ];

                    break;
            }
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubAsignacion["cubAG_fechaInicio"] = intval($fechaIniPeriodo);

            $condicionHistAsignacion["avHistAsig_carteraId"] = strval($filtroCartera);
            $condicionHistAsignacion["avHistAsig_fechaPeriodo"] = intval($fechaIniPeriodo);

            $condicionCubAsignacionAnt["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubAsignacionAnt["cubAG_fechaInicio"] = intval($fechaIniPeriodoAnterior);

            $condicionEvolucion["cubAG_carteraId"] = strval($filtroCartera);


            if ($filtroPeriodo >= 0) {
                $condicionCubAsignacion["cubAG_ciclo"] = intval($filtroPeriodo);
                $condicionHistAsignacion["avHistAsig_ciclo"] = intval($filtroPeriodo);
                $condicionCubAsignacionAnt["cubAG_ciclo"] = intval($filtroPeriodo);
                $condicionEvolucion["cubAG_ciclo"] = intval($filtroPeriodo);
            }
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionHistAsignacion["avHistAsig_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionCubAsignacionAnt["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionEvolucion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        if ($filtroFechaCarga != "" && $filtroFechaCarga != "todo") {
            $desdeCarga = strtotime(date('Y-m-d', $filtroFechaCarga) . " 00:00:00");
            $hastaCarga = strtotime(date("Y-m-d", $filtroFechaCarga) . " 23:59:59");

            $condicionCubAsignacion["cubAG_fechaCarga"] = ['$gte' => $desdeCarga, '$lte' => $hastaCarga];
            $condicionHistAsignacion["avHistAsig_fechaCarga"] = ['$gte' => $desdeCarga, '$lte' => $hastaCarga];
        }

        if ($filtroMarca != "" && $filtroMarca != "todo") {
            $condicionCubAsignacion["cubAG_marca"] = [
                '$regex' => '^' . $filtroMarca . '$',
                '$options' => 'i'
            ];

            $condicionHistAsignacion["avHistAsig_marca"] = [
                '$regex' => '^' . $filtroMarca . '$',
                '$options' => 'i'
            ];

            $condicionCubAsignacionAnt["cubAG_marca"] = [
                '$regex' => '^' . $filtroMarca . '$',
                '$options' => 'i'
            ];

            $condicionEvolucion["cubAG_marca"] = [
                '$regex' => '^' . $filtroMarca . '$',
                '$options' => 'i'
            ];
        }

        if ($filtroRegion != "" && $filtroRegion != "todo") {
            $condicionCubAsignacion["cubAG_region"] = [
                '$regex' => '^' . $filtroRegion . '$',
                '$options' => 'i'
            ];

            $condicionHistAsignacion["avHistAsig_region"] = [
                '$regex' => '^' . $filtroRegion . '$',
                '$options' => 'i'
            ];

            $condicionCubAsignacionAnt["cubAG_region"] = [
                '$regex' => '^' . $filtroRegion . '$',
                '$options' => 'i'
            ];

            $condicionEvolucion["cubAG_region"] = [
                '$regex' => '^' . $filtroRegion . '$',
                '$options' => 'i'
            ];
        }

        if ($filtroCanton != "" && $filtroCanton != "todo") {
            $condicionCubAsignacion["cubAG_canton"] = [
                '$regex' => '^' . $filtroCanton . '$',
                '$options' => 'i'
            ];

            $condicionHistAsignacion["avHistAsig_canton"] = [
                '$regex' => '^' . $filtroCanton . '$',
                '$options' => 'i'
            ];

            $condicionCubAsignacionAnt["cubAG_canton"] = [
                '$regex' => '^' . $filtroCanton . '$',
                '$options' => 'i'
            ];

            $condicionEvolucion["cubAG_canton"] = [
                '$regex' => '^' . $filtroCanton . '$',
                '$options' => 'i'
            ];
        }

        /*trigger_error("condicionCubAsignacion " . print_r($condicionCubAsignacion, true));*/
        trigger_error("condicionHistAsignacion " . print_r($condicionHistAsignacion, true));
        trigger_error("condicionHistAsignacionAnt " . print_r($condicionCubAsignacionAnt, true));
        /* trigger_error("condicionEvolucion " . print_r($condicionEvolucion, true));*/


        $totales = obtenerTotalesGestion2($condicionCubAsignacion, $condicionHistAsignacion, $condicionCubAsignacionAnt, $condicionEvolucion);

        $json["totales"] = $totales;


        #endregion
        break;
    case "descargarGestiones":

        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAV_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGV_carteraId");
        $nombreCartera = "";

        // Convertir valores a string
        if (!empty($condicionCubAsignacion["cubAV_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAV_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAV_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGV_carteraId"]['$in'])) {
            $condicionCubGestion["cubGV_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGV_carteraId"]['$in']);
        }

        $desde = 0;
        $hasta = 0;

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }

            $condicionCubAsignacion["cubAV_fechaPeriodo"] = ['$gt' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGV_fechaGestion"] = ['$gt' => $desde, '$lte' => $hasta];
        }

        if ($filtroCartera != "" && $filtroCartera != "todo") {

            $condicionCubAsignacion["cubAV_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGV_carteraId"] = strval($filtroCartera);

            // obtener nombre cartera
            $mysql = new MYSQLDB();
            $sql = $mysql->mkSQL(
                "SELECT cobCartera_nombre FROM cobcartera WHERE cobCartera_id=%N",
                $filtroCartera
            );

            $mysql->query($sql);

            if ($row = $mysql->fetchRow()) {
                $nombreCartera = $row["cobCartera_nombre"];
            }
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {

            $condicionCubAsignacion["cubAV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionCubGestion["cubGV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        $mdbAsigV = new MYMONGODB();

        $dataCubAasignacion = [
            'cubAV_numFactura',
            'cubAV_carteraNombre',
            'cubAV_gestionada',
            'cubAV_tipificacion1',
            'cubAV_tipificacion2',
            'cubAV_carteraId',
            'cubAV_producto',
            'cubAV_fechaCarga',
            'cubAV_fechaGestion'
        ];

        $mdbAsigV->buscar('cuAsignacionesGestionVentas', $condicionCubAsignacion, $dataCubAasignacion, ["cubAV_fechaCarga" => 1]);


        $mdbCuGV = new MYMONGODB();

        while ($f = $mdbAsigV->siguiente()) {
            /* $condCuGV = [
                "cubGV_numFactura" => $f["cubAV_numFactura"],
                "cubGV_carteraId" => $f["cubAV_carteraId"],
                "cubGV_tipificacion_respuesta1" => $f["cubAV_tipificacion1"],
                "cubGV_tipificacion_respuesta2" => $f["cubAV_tipificacion2"],
                "cubGV_producto" => $f["cubAV_producto"],
                "cubGV_fechaGestion" => $f["cubAV_producto"],
               //"cubGV_fechaGestion" => ['$gte' => $desde, '$lte' => $hasta]
            ];

            // Buscar la más reciente 
            $mdbCuGV->buscar('cuGestionVentas', $condCuGV, [], ['cubGV_fechaGestion' => -1], 1);
            $fechaGestion = null;
            if ($doc = $mdbCuGV->siguiente()) {
                $fechaGestion = $doc['cubGV_fechaGestion'] ?? null;
            }
            $f["fechaGestion"] = $fechaGestion;*/
            $resultado[] = $f;
        }

        // Nombre archivo
        $fechaHoy = date("Y_m_d");

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $nombreArchivo = "GESTIONES_VENTAS_" . $nombreCartera . "_" . $fechaHoy;
        } else {
            $nombreArchivo = "GESTIONES_VENTAS_" . $fechaHoy;
        }

        $ruta = generarExcel($resultado, $nombreArchivo);

        $json["ruta"] = $ruta;

        trigger_error("archivo ruta: " . $ruta);

        break;


    case "listaAsignacionGestiones":

        #region listaAsignacionGestiones 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAV_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGV_carteraId");

        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAV_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAV_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAV_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGV_carteraId"]['$in'])) {
            $condicionCubGestion["cubGV_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGV_carteraId"]['$in']);
        }


        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($_GET["filtroDesde"]);
                    $filtroHasta = expect_integer($_GET["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    break;
            }

            $condicionCubAsignacion["cubAV_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGV_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAV_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGV_carteraId"] = strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
            $condicionCubGestion["cubGV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }


        //<editor-fold defaultstate="collapsed" desc=" Lista las asignaciones para presentarlos en la tabula ">
        $coleccion = "cuAsignacionesGestionVentas";

        //$campos = ["cubAV_numFactura", "cubAV_carteraId", "cubAV_carteraNombre", "cubAV_gestionada", "cubAV_tipificacion1", "cubAV_tipificacion2", "cubAV_producto"];
        $campos = [];
        $orden = ["cubAV_carteraId" => 1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);

        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);

        $ngTabula->setPreparaDatos(function ($campos) {
            $campos["cubAV_gestionada"] = (isset($campos['cubAV_gestionada']) && $campos['cubAV_gestionada'] == 1) ? "SI" : "NO";
            return $campos;
        });
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        //</editor-fold>



        $filas = $json["filas"];

        break;

    case "listaTramosSaldo":
        //trigger_error("entro a tramo");

        $d = jsonStart();

        $filtroCartera  = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo   = expect_safe_html($_GET["filtroTiempo"]);

        $condicionHistAsignacion = establecerCondicionMaster("avHistAsig_carteraId");

        if (!empty($condicionHistAsignacion["avHistAsig_carteraId"]['$in'])) {
            $condicionHistAsignacion["avHistAsig_carteraId"]['$in'] =
                array_map('strval', $condicionHistAsignacion["avHistAsig_carteraId"]['$in']);
        }

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            switch ($filtroTiempo) {

                case "rango":

                    $filtroDesde = expect_integer($_GET["filtroDesde"]);
                    $filtroHasta = expect_integer($_GET["filtroHasta"]);

                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    $condicionHistAsignacion["avHistAsig_fechaPeriodo"] = [
                        '$gte' => $desde,
                        '$lte' => $hasta
                    ];

                    break;
            }
        }

        if ($filtroCartera != "" && $filtroCartera != "todo") {

            $condicionHistAsignacion["avHistAsig_carteraId"] =
                strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {

            $condicionHistAsignacion["avHistAsig_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | OBTENER SALDO TOTAL GENERAL
    |--------------------------------------------------------------------------
    */

        $pipelineTotal = [

            [
                '$match' => array_merge(
                    $condicionHistAsignacion,
                    [
                        'avHistAsig_tramoSaldo' => [
                            '$ne' => ''
                        ]
                    ]
                )
            ],

            [
                '$group' => [
                    '_id' => [
                        'cliente' => '$avHistAsig_numFactura',
                        'tramo'   => '$avHistAsig_tramoSaldo'
                    ],
                    'saldo' => [
                        '$max' => '$avHistAsig_capitalActual'
                    ]
                ]
            ],

            [
                '$group' => [
                    '_id' => null,
                    'saldoTotal' => [
                        '$sum' => '$saldo'
                    ]
                ]
            ]
        ];

        $mdb = new MYMONGODB();

        $mdb->aggregate(
            "avHistorialAsignacionesDiarias",
            $pipelineTotal
        );

        $totalSaldoGeneral = 0;

        if ($row = $mdb->siguiente()) {

            $totalSaldoGeneral = (float)$row["saldoTotal"];
        }

        /*
    |--------------------------------------------------------------------------
    | TABULA
    |--------------------------------------------------------------------------
    */

        $pipeline = [

            [
                '$match' => array_merge(
                    $condicionHistAsignacion,
                    [
                        'avHistAsig_tramoSaldo' => [
                            '$ne' => ''
                        ]
                    ]
                )
            ],

            [
                '$group' => [
                    '_id' => [
                        'cliente' => '$avHistAsig_numFactura',
                        'tramo'   => '$avHistAsig_tramoSaldo'
                    ],
                    'saldo' => [
                        '$max' => '$avHistAsig_capitalActual'
                    ]
                ]
            ],

            [
                '$group' => [
                    '_id' => '$_id.tramo',
                    'total' => [
                        '$sum' => 1
                    ],
                    'saldo' => [
                        '$sum' => '$saldo'
                    ]
                ]
            ]
        ];

        $ngTabula = new coTabulaMongo();

        $ngTabula->setInput($d);

        $ngTabula->setAggregate(
            "avHistorialAsignacionesDiarias",
            $pipeline,
            ['_id' => 1]
        );

        $ngTabula->permiteExportar(false);

        $json = $ngTabula->responde();

        $filas = $json["filas"];

        foreach ($filas as &$fila) {

            $fila["tramo"] = $fila["_id"];

            $fila["saldo"] = round(
                (float)$fila["saldo"],
                2
            );

            $fila["porcentaje"] =
                $totalSaldoGeneral > 0
                ? round(
                    ($fila["saldo"] * 100) / $totalSaldoGeneral,
                    2
                )
                : 0;
        }

        $json["filas"] = $filas;

        trigger_error(json_encode($json));

        break;
}





function obtenerTotalesGestion2($condicionCubAsignacion, $condicionHistAsignacion, $condicionCubAsignacionAnt, $condicionEvolucion)
{

    $resultado = [
        "asignacion" => [
            [
                "nombre" => "Cartera Asignada",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00"
            ],
            [
                "nombre" => "Cartera en Mora",
                "total" => 0,
                "porcentaje" => "0"
            ]
        ],
        "asignacionAnt" => [
            [
                "nombre" => "Cartera Asignada",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00"
            ],
            [
                "nombre" => "Cartera en Mora",
                "total" => 0,
                "porcentaje" => "0"
            ]
        ],

        "asignacionComparativa" => [
            "clientes" => [
                "porcentaje" => 0,
                "tendencia" => "igual"
            ],
            "saldo" => [
                "porcentaje" => 0,
                "tendencia" => "igual"
            ],
            "diasMora" => [
                "dias" => 0,
                "tendencia" => "igual"
            ],
            "porcentajeMora" => [
                "puntos" => 0,
                "tendencia" => "igual"
            ],
        ],

        "tramosMora" => [
            "grafica" => [],
            "totales" => []
        ],
        "graficaSaldosMora" => [],
        "heatmapProductoMora" => [
            "productos" => [],
            "tramos" => [],
            "datos" => []
        ],
        "evolucionVencida" => [
            "meses" => [],
            "porcentajes" => [],
            "totalClientes" => [],
            "clientesMora" => []
        ]


    ];

    $totalRegistros = 0;
    $totalCapital   = 0;
    $sumaDiasMora   = 0;
    $totalEnMora    = 0;

    $totalRegistrosAnt = 0;
    $totalCapitalAnt   = 0;
    $sumaDiasMoraAnt   = 0;
    $totalEnMoraAnt    = 0;

    $diasPromedioMora = 0;
    $diasPromedioMoraAnt = 0;
    $porcentajeMora = 0;
    $porcentajeMoraAnt = 0;


    $mdb = new MYMONGODB();

    /*
    |--------------------------------------------------------------------------
    | TOTALES ASIGNACION
    |--------------------------------------------------------------------------
    */
    if (!empty($condicionCubAsignacion) &&  !empty($condicionHistAsignacion)) {

        $pipeline = [
            [
                '$match' => $condicionCubAsignacion
            ],
            [
                '$group' => [
                    '_id' => null,

                    'totalRegistros' => [
                        '$sum' => 1
                    ],

                    'totalCapital' => [
                        '$sum' => '$cubAG_capitalActual'
                    ],

                    'sumaDiasMora' => [
                        '$sum' => [
                            '$cond' => [
                                ['$gt' => ['$cubAG_diasMora', 0]],
                                '$cubAG_diasMora',
                                0
                            ]
                        ]
                    ],

                    'totalEnMora' => [
                        '$sum' => [
                            '$cond' => [
                                ['$gt' => ['$cubAG_diasMora', 0]],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $mdb->aggregate("cuAsignacionesGestionAP", $pipeline);

        if ($row = $mdb->siguiente()) {

            $totalRegistros = (int)$row["totalRegistros"];
            $totalCapital   = (float)$row["totalCapital"];
            $sumaDiasMora   = (float)$row["sumaDiasMora"];
            $totalEnMora    = (int)$row["totalEnMora"];

            $diasPromedioMora = $totalEnMora > 0 ? (int) round($sumaDiasMora / $totalEnMora) : 0;
            $porcentajeMora = $totalRegistros > 0 ? round(($totalEnMora * 100) / $totalRegistros, 2) : 0;

            $resultado["asignacion"][0]["total"] = formatea_numero($totalRegistros, 0, ",", ".");
            $resultado["asignacion"][0]["monto"] = "$" . formatea_numero($totalCapital, 2, ",", ".");
            $resultado["asignacion"][1]["total"] = formatea_numero($diasPromedioMora, 0, ",", ".");
            $resultado["asignacion"][1]["porcentaje"] = formatea_numero($porcentajeMora, 2, ",", ".");
        }

        /*
        |--------------------------------------------------------------------------
        | TOTALES ASIGNACION ANTERIOR
        |--------------------------------------------------------------------------
        */
        $pipelineAsigAnt = [
            [
                '$match' => $condicionCubAsignacionAnt
            ],
            [
                '$group' => [
                    '_id' => null,

                    'totalRegistros' => [
                        '$sum' => 1
                    ],

                    'totalCapital' => [
                        '$sum' => '$cubAG_capitalActual'
                    ],

                    'sumaDiasMora' => [
                        '$sum' => [
                            '$cond' => [
                                ['$gt' => ['$cubAG_diasMora', 0]],
                                '$cubAG_diasMora',
                                0
                            ]
                        ]
                    ],

                    'totalEnMora' => [
                        '$sum' => [
                            '$cond' => [
                                ['$gt' => ['$cubAG_diasMora', 0]],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $mdb->aggregate("cuAsignacionesGestionAP", $pipelineAsigAnt);

        if ($row = $mdb->siguiente()) {

            $totalRegistrosAnt = (int)$row["totalRegistros"];
            $totalCapitalAnt   = (float)$row["totalCapital"];
            $sumaDiasMoraAnt   = (float)$row["sumaDiasMora"];
            $totalEnMoraAnt    = (int)$row["totalEnMora"];

            $diasPromedioMoraAnt = $totalEnMoraAnt > 0 ? (int) round($sumaDiasMoraAnt / $totalEnMoraAnt) : 0;
            $porcentajeMoraAnt = $totalRegistrosAnt > 0 ? round(($totalEnMoraAnt * 100) / $totalRegistrosAnt, 2) : 0;

            $resultado["asignacionAnt"][0]["total"] = formatea_numero($totalRegistrosAnt, 0, ",", ".");
            $resultado["asignacionAnt"][0]["monto"] = "$" . formatea_numero($totalCapitalAnt, 2, ",", ".");
            $resultado["asignacionAnt"][1]["total"] = formatea_numero($diasPromedioMoraAnt, 0, ",", ".");
            $resultado["asignacionAnt"][1]["porcentaje"] = formatea_numero($porcentajeMoraAnt, 2, ",", ".");
        }

        // Comparativa de asignaciones actual y anterior

        $variacionClientes = 0;

        if ($totalRegistros > 0 && $totalRegistrosAnt > 0) {

            $mayor = max($totalRegistros, $totalRegistrosAnt);
            $menor = min($totalRegistros, $totalRegistrosAnt);

            $variacionClientes =  formatea_numero(round(100 - (($menor * 100) / $mayor), 2), 2, ",", ".");
        }

        $variacionSaldo = 0;

        if ($totalCapital > 0 && $totalCapitalAnt > 0) {

            $mayor = max($totalCapital, $totalCapitalAnt);
            $menor = min($totalCapital, $totalCapitalAnt);

            $variacionSaldo = formatea_numero(round(100 - (($menor * 100) / $mayor), 2), 2, ",", ".");
        }

        $variacionDiasMora =  abs($diasPromedioMora - $diasPromedioMoraAnt);

        $variacionPuntosMora = formatea_numero(abs(round($porcentajeMora - $porcentajeMoraAnt, 2)), 2, ",", ".");


        // Tendencias

        $tendenciaClientes = "igual";

        if ($totalRegistros > $totalRegistrosAnt) {
            $tendenciaClientes = "sube";
        } elseif ($totalRegistros < $totalRegistrosAnt) {
            $tendenciaClientes = "baja";
        }


        $tendenciaSaldo = "igual";

        if ($totalCapital > $totalCapitalAnt) {
            $tendenciaSaldo = "sube";
        } elseif ($totalCapital < $totalCapitalAnt) {
            $tendenciaSaldo = "baja";
        }

        $tendenciaDiasMora = "igual";

        if ($diasPromedioMora > $diasPromedioMoraAnt) {
            $tendenciaDiasMora = "sube";
        } elseif ($diasPromedioMora < $diasPromedioMoraAnt) {
            $tendenciaDiasMora = "baja";
        }

        $tendenciaPuntosMora = "igual";

        if ($porcentajeMora > $porcentajeMoraAnt) {
            $tendenciaPuntosMora = "sube";
        } elseif ($porcentajeMora < $porcentajeMoraAnt) {
            $tendenciaPuntosMora = "baja";
        }


        $resultado["asignacionComparativa"] = [

            "clientes" => [
                "porcentaje" => $variacionClientes,
                "tendencia" => $tendenciaClientes
            ],

            "saldo" => [
                "porcentaje" => $variacionSaldo,
                "tendencia" => $tendenciaSaldo
            ],
            "diasMora" => [
                "dias" => $variacionDiasMora,
                "tendencia" => $tendenciaDiasMora
            ],
            "porcentajeMora" => [
                "puntos" => $variacionPuntosMora,
                "tendencia" => $tendenciaPuntosMora
            ]

        ];

        /*
        |--------------------------------------------------------------------------
        | OBTENER CARTERAS COBRANZA MYSQL
        |--------------------------------------------------------------------------
        */


        $mysql = new MYSQLDB();

        $sql = "SELECT cobCartera_id FROM cobcartera WHERE cobCartera_estado = 1 AND cobCartera_tipo = 'COBRANZA'";

        $mysql->query($sql);

        $carterasCobranza = [];

        while ($row = $mysql->fetchRow()) {
            $carterasCobranza[] = strval($row["cobCartera_id"]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRAMOS DE MORA
        |--------------------------------------------------------------------------
        */

        // OBTENER TODOS LOS TRAMOS DE MORA EXISTENTES


        $mdbTramos = new MYMONGODB();

        $condicionTramos = ['cubAG_carteraId' => ['$in' => $carterasCobranza]];

        $tramosCatalogo = $mdbTramos->buscarDistinct("cubAG_tramoMora", "cuAsignacionesGestionAP", $condicionTramos);

        if (!is_array($tramosCatalogo)) {
            $tramosCatalogo = [];
        }

        sort($tramosCatalogo);


        // CONTAR CLIENTES POR TRAMO

        $mdbHist = new MYMONGODB();

        $pipelineHist = [
            [
                '$match' => $condicionHistAsignacion
            ],
            [
                '$group' => [
                    '_id' => [
                        'cliente' => '$avHistAsig_numFactura',
                        'tramo'   => '$avHistAsig_tramoMora'
                    ],
                    'saldo' => [
                        '$max' => '$avHistAsig_capitalActual'
                    ]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$_id.tramo',
                    'total' => [
                        '$sum' => 1
                    ],
                    'saldo' => [
                        '$sum' => '$saldo'
                    ]
                ]
            ]
        ];

        $mdbHist->aggregate("avHistorialAsignacionesDiarias", $pipelineHist);

        $totalesPorTramo = [];

        while ($row = $mdbHist->siguiente()) {

            $tramo = trim($row["_id"]);

            $totalesPorTramo[$tramo] = [
                "total" => (int)$row["total"],
                "saldo" => (float)$row["saldo"]
            ];
        }

        $totalClientesGeneral = 0;
        $totalSaldoGeneral = 0;

        foreach ($tramosCatalogo as $tramo) {

            $cantidad = isset($totalesPorTramo[$tramo]) ? $totalesPorTramo[$tramo]["total"] : 0;
            $saldo = isset($totalesPorTramo[$tramo]) ? $totalesPorTramo[$tramo]["saldo"] : 0;

            $totalClientesGeneral += $cantidad;
            $totalSaldoGeneral += $saldo;
        }


        // Ahora construyo el detalle
        $totalPorcentajeGeneral = 0;

        foreach ($tramosCatalogo as $tramo) {

            $cantidad = isset($totalesPorTramo[$tramo]) ? $totalesPorTramo[$tramo]["total"] : 0;

            $saldo = isset($totalesPorTramo[$tramo]) ? $totalesPorTramo[$tramo]["saldo"] : 0;

            $porcentaje = $totalClientesGeneral > 0 ? round(($cantidad * 100) / $totalClientesGeneral, 2) : 0;

            $totalPorcentajeGeneral += $porcentaje;

            $resultado["tramosMora"]["grafica"][] = [
                "nombre"     => $tramo,
                "total"      => $cantidad,
                "saldo"      => round($saldo, 2),
                "porcentaje" => $porcentaje
            ];
        }


        // Totales para la fila final de la tabla
        $resultado["tramosMora"]["totales"] = [
            "totalClientes" => $totalClientesGeneral,
            "saldo" => round($totalSaldoGeneral, 2),
            "porcentaje" => round($totalPorcentajeGeneral, 2)
        ];



        /*
        |--------------------------------------------------------------------------
        | HEATMAP PRODUCTO VS TRAMO MORA
        |--------------------------------------------------------------------------
        */

        $mdbHeatmap = new MYMONGODB();

        $pipelineHeatmap = [
            [
                '$match' => $condicionHistAsignacion
            ],
            [
                '$group' => [
                    '_id' => [
                        'cliente'  => '$avHistAsig_numFactura',
                        'producto' => '$avHistAsig_producto',
                        'tramo'    => '$avHistAsig_tramoMora'
                    ],
                    'saldo' => [
                        '$max' => '$avHistAsig_capitalActual'
                    ]
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'producto' => '$_id.producto',
                        'tramo'    => '$_id.tramo'
                    ],
                    'total' => [
                        '$sum' => 1
                    ],
                    'saldo' => [
                        '$sum' => '$saldo'
                    ]
                ]
            ]
        ];

        $mdbHeatmap->aggregate(
            "avHistorialAsignacionesDiarias",
            $pipelineHeatmap
        );

        $productos = [];
        $tramosEncontrados = [];
        $matriz = [];

        while ($row = $mdbHeatmap->siguiente()) {

            $producto = trim($row["_id"]["producto"]);
            $tramo    = trim($row["_id"]["tramo"]);
            $saldo    = (float)$row["saldo"];

            if ($producto == "" || $tramo == "") {
                continue;
            }

            if (!in_array($producto, $productos)) {
                $productos[] = $producto;
            }

            if (!in_array($tramo, $tramosEncontrados)) {
                $tramosEncontrados[] = $tramo;
            }

            $matriz[$producto][$tramo] = round($saldo, 2);
        }

        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS
        |--------------------------------------------------------------------------
        */

        sort($productos);

        /*
        |--------------------------------------------------------------------------
        | TRAMOS
        |--------------------------------------------------------------------------
        |
        | Se utiliza el mismo catálogo de tramos obtenido
        | previamente para graficaTramosMora.
        |
        */

        $tramosEncontradosMap = [];

        foreach ($tramosEncontrados as $tramo) {
            $tramosEncontradosMap[$tramo] = true;
        }

        $tramos = [];

        foreach ($tramosCatalogo as $tramo) {

            $tramo = trim($tramo);

            if (isset($tramosEncontradosMap[$tramo])) {
                $tramos[] = $tramo;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DATOS DEL HEATMAP
        |--------------------------------------------------------------------------
        */

        $datosHeatmap = [];

        foreach ($productos as $producto) {

            $fila = [
                "producto" => $producto,
                "valores"  => []
            ];

            foreach ($tramos as $tramo) {

                $fila["valores"][] =
                    isset($matriz[$producto][$tramo])
                    ? $matriz[$producto][$tramo]
                    : 0;
            }

            $datosHeatmap[] = $fila;
        }

        /*
        |--------------------------------------------------------------------------
        | RESULTADO
        |--------------------------------------------------------------------------
        */

        $resultado["heatmapProductoMora"] = [
            "productos" => $productos,
            "tramos"    => $tramos,
            "datos"     => $datosHeatmap
        ];


        /*
        |--------------------------------------------------------------------------
        | TRAMOS DE SALDO
        |--------------------------------------------------------------------------
        */

        // OBTENER TODOS LOS TRAMOS DE MORA EXISTENTES


        $mdbTramos = new MYMONGODB();

        $tramosSaldoCatalogo = $mdbTramos->buscarDistinct("cubAG_tramoSaldo", "cuAsignacionesGestionAP", $condicionTramos);


        if (!is_array($tramosSaldoCatalogo)) {
            $tramosSaldoCatalogo = [];
        }

        sort($tramosSaldoCatalogo);



        /*
        |--------------------------------------------------------------------------
        | CONTAR CLIENTES POR TRAMO
        |--------------------------------------------------------------------------
        */

        $mdbHistSaldo = new MYMONGODB();

        $pipelineHistSaldo = [
            [
                '$match' => array_merge(
                    $condicionHistAsignacion,
                    [
                        'avHistAsig_tramoSaldo' => [
                            '$ne' => ''
                        ]
                    ]
                )
            ],
            [
                '$group' => [
                    '_id' => [
                        'cliente'    => '$avHistAsig_numFactura',
                        'tramoSaldo' => '$avHistAsig_tramoSaldo'
                    ],
                    'saldo' => [
                        '$max' => '$avHistAsig_capitalActual'
                    ]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$_id.tramoSaldo',
                    'total' => [
                        '$sum' => 1
                    ],
                    'saldo' => [
                        '$sum' => '$saldo'
                    ]
                ]
            ]
        ];

        $mdbHistSaldo->aggregate(
            "avHistorialAsignacionesDiarias",
            $pipelineHistSaldo
        );

        $totalesPorTramoSaldo = [];


        while ($row = $mdbHistSaldo->siguiente()) {

            $tramoSaldo = trim($row["_id"]);

            // Evita que espacios u otros detalles sobrescriban registros
            if (!isset($totalesPorTramoSaldo[$tramoSaldo])) {

                $totalesPorTramoSaldo[$tramoSaldo] = [
                    "total" => 0,
                    "saldo" => 0
                ];
            }

            $totalesPorTramoSaldo[$tramoSaldo]["total"] += (int)$row["total"];
            $totalesPorTramoSaldo[$tramoSaldo]["saldo"] += (float)$row["saldo"];
        }



        $totalSaldoGeneral = 0;

        foreach ($totalesPorTramoSaldo as $dato) {
            $totalSaldoGeneral += $dato["saldo"];
        }



        $saldo = 0;
        foreach ($tramosSaldoCatalogo as $tramo) {

            $cantidad = isset($totalesPorTramoSaldo[$tramo])
                ? $totalesPorTramoSaldo[$tramo]["total"]
                : 0;

            $saldo = isset($totalesPorTramoSaldo[$tramo])
                ? $totalesPorTramoSaldo[$tramo]["saldo"]
                : 0;

            $porcentaje = $totalSaldoGeneral > 0
                ? round(($saldo * 100) / $totalSaldoGeneral, 2)
                : 0;

            $resultado["graficaSaldosMora"][] = [
                "nombre"     => $tramo,
                "total"      => $cantidad,
                "saldo"      => round($saldo, 2),
                "porcentaje" => $porcentaje
            ];
        }




        /*
        |--------------------------------------------------------------------------
        | EVOLUCIÓN MENSUAL % CARTERA VENCIDA
        |--------------------------------------------------------------------------
        */

        $anioActual = date('Y');

        $fechaInicioAnio = strtotime($anioActual . '-01-01 00:00:00');
        $fechaFinAnio    = strtotime($anioActual . '-12-31 23:59:59');

        $condicionEvolucion['cubAG_fechaPeriodo'] = [
            '$gte' => $fechaInicioAnio,
            '$lte' => $fechaFinAnio
        ];

        //trigger_error("condicionEvolucion en totales gest2 " . print_r($condicionEvolucion, true));



        $mdbEvolucion = new MYMONGODB();

        $pipelineEvolucion = [

            [
                '$match' => $condicionEvolucion
            ],

            [
                '$project' => [

                    'mes' => [
                        '$month' => [
                            '$toDate' => [
                                '$multiply' => [
                                    '$cubAG_fechaPeriodo',
                                    1000
                                ]
                            ]
                        ]
                    ],

                    'diasMora' => '$cubAG_diasMora'
                ]
            ],

            [
                '$group' => [

                    '_id' => '$mes',

                    'totalClientes' => [
                        '$sum' => 1
                    ],

                    'clientesMora' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$gt' => [
                                        '$diasMora',
                                        0
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ],

            [
                '$sort' => [
                    '_id' => 1
                ]
            ]
        ];

        $mdbEvolucion->aggregate(
            "cuAsignacionesGestionAP",
            $pipelineEvolucion
        );

        $nombresMeses = [
            1  => 'Ene',
            2  => 'Feb',
            3  => 'Mar',
            4  => 'Abr',
            5  => 'May',
            6  => 'Jun',
            7  => 'Jul',
            8  => 'Ago',
            9  => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic'
        ];

        while ($row = $mdbEvolucion->siguiente()) {

            $mes = (int)$row["_id"];

            $totalClientes = (int)$row["totalClientes"];

            $clientesMora = (int)$row["clientesMora"];

            $porcentaje = $totalClientes > 0
                ? round(($clientesMora * 100) / $totalClientes, 2)
                : 0;

            $resultado["evolucionVencida"]["meses"][] =
                $nombresMeses[$mes];

            $resultado["evolucionVencida"]["porcentajes"][] =
                $porcentaje;

            $resultado["evolucionVencida"]["totalClientes"][] =
                $totalClientes;

            $resultado["evolucionVencida"]["clientesMora"][] =
                $clientesMora;
        }
    }


    return $resultado;
}






function generarExcel($data, $archivoExcelNombre)
{

    require_once("../comunes/classes/class.coGeneraExcel.php");

    $anio = date('Y');
    $mes = date('m');


    // RUTA RELATIVA (para la clase)
    $rutaRelativaExcel = "/canalesMasivos/reportesExcel/ventas/$anio/$mes/";

    //RUTA FÍSICA (DONDE SE GUARDA)
    $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . "/canalesMasivos/reportesExcel/ventas/$anio/$mes";

    if (!is_dir($rutaFisica)) {
        mkdir($rutaFisica, 0777, true);
    }

    $objExcel = new coGeneraExcel();
    $objExcel->setNombreArchivo($archivoExcelNombre);
    $objExcel->setUbicacion($rutaRelativaExcel);

    $nombreHoja = "Resumen";
    $objExcel->addHoja(0, $nombreHoja);
    $objExcel->setAnchoColumna(30);

    // CABECERAS

    $cabeceras = array(
        "Operación",
        "Cartera",
        "Gestionado",
        "Contactabilidad",
        "Gestión Realizada",
        "Fecha y Hora Gestión"
    );


    $filasExcel = array();
    $i = 0;

    $filasExcel["titulo_" . $i] = $cabeceras;
    $i++;

    // FILAS
    foreach ($data as $fila) {


        // GESTIONES
        $nuevaFila = [
            $fila['cubAV_numFactura'] ?? '',
            $fila['cubAV_carteraNombre'] ?? '',
            (isset($fila['cubAV_gestionada']) && $fila['cubAV_gestionada'] == 1) ? "SI" : "NO",
            $fila['cubAV_tipificacion1'] ?? '',
            $fila['cubAV_tipificacion2'] ?? '',
            (isset($fila['cubAV_fechaGestion']) && $fila['cubAV_fechaGestion'] > 0)
                ? date('Y-m-d H:i:s', $fila['cubAV_fechaGestion']) : ''

        ];


        $filasExcel["fila_" . $i] = $nuevaFila;
        $i++;
    }

    //ESTILOS
    foreach ($filasExcel as $key => $fila) {

        $partes = explode("_", $key);
        $style = $partes[0];

        switch ($style) {

            case "titulo":
                $font = $objExcel->setFilaEstiloFuente("Calibri", "14", true, "#ffffff");
                $background = $objExcel->setFilaEstiloFondo("solid", "1e2040");
                $borders = array();
                $alinear = $objExcel->setFilaEstiloAlineacion('center', 'center');
                $merge = "";
                break;

            default:
                $font = $objExcel->setFilaEstiloFuente("Calibri", "11", false, "#000000");
                $background = $borders = $alinear = array();
                $alinear = $objExcel->setFilaEstiloAlineacion('left', 'center');
                $merge = "";
                break;
        }

        $objExcel->addFila($nombreHoja, $fila, $font, $background, $borders, $alinear, $merge);
    }

    // GENERAR ARCHIVO
    $archivo = $objExcel->genera();

    // RUTA PARA USAR EN FRONT / DESCARGA
    $rutaRelativa = "/canalesMasivos/reportesExcel/ventas/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();

    return $rutaRelativa;
}







//abrevia y muestra numeros como: 1K, 10M, ect
function abreviaNumero($n, $precision)
{
    $negativo = 0;
    if ($n < 0) {
        $negativo = 1;
        $n = $n * -1;
    }
    if ($n < 900) {
        // 0 - 900
        $n_format = number_format($n, $precision);
        $suffix = '';
    } else if ($n < 900000) {
        // 0.9k-850k
        $n_format = number_format($n / 1000, $precision);
        $suffix = 'K';
    } else if ($n < 900000000) {
        // 0.9m-850m
        $n_format = number_format($n / 1000000, $precision);
        $suffix = 'M';
    } else if ($n < 900000000000) {
        // 0.9b-850b
        $n_format = number_format($n / 1000000000, $precision);
        $suffix = 'B';
    } else {
        // 0.9t+
        $n_format = number_format($n / 1000000000000, $precision);
        $suffix = 'T';
    }
    // Remover ceros innecesarios despues del decimal. "1.0" -> "1"; "1.00" -> "1"
    // pero intencionalmente no afecta parciales, eg "1.50" -> "1.50"
    if ($precision > 0) {
        $dotzero = '.' . str_repeat('0', $precision);
        $n_format = str_replace($dotzero, '', $n_format);
    }
    if ($negativo == 1) {
        $n_format = '-' . $n_format;
    }
    return $n_format . $suffix;
}

#endregion

jsonEnd($json, $limpiar);

//devuelve un listado de las carteras que puede ver el usuario
function obtenerCarterasPermitidas($permisos)
{
    $idsCarterasGeneral = null;
    if ($permisos["soySupervisor"] || $permisos["soyObservador"] || $permisos["soyGerente"]) {
        //saco el listado de carteras activas
        $mysql = new MYSQLDB();
        $tabla = "cobcartera";
        $carterasConfiguradas = [];
        $sql = $mysql->mkSQL("SELECT * FROM " . $tabla . " WHERE cobCartera_estado=%N", 1);
        if ($mysql->query($sql) > 0) {
            while ($row = $mysql->fetchRow()) {
                $item = [];
                foreach ($row as $key => $value) {
                    $k = str_replace("cobCartera_", "", $key);
                    $item[$k] = $value;
                }
                $carterasConfiguradas[] = $item;
            }
        }
        require_once("../permisos/classes/class.permisoUniversal.php");
        $permisoUniversal = new permisoUniversal();
        $quePuedoVer = $permisoUniversal->validaRegistrosUsuarioSesion($carterasConfiguradas, $tabla);
        //$quePuedoVer = $permisoUniversal->validaRegistrosPorUsuarioId($carterasConfiguradas, $tabla, 910797409);
        foreach ($quePuedoVer as $value) {
            $idsCarterasGeneral[] = intval($value["id"]);
        }
        if ($idsCarterasGeneral == null || count($idsCarterasGeneral) == 0) { //siempre debe tener permisos universales
            trigger_error("No tiene permisos universales configurados para usuario " . $_SESSION[MID . "userId"]);
            echo "No tiene permisos universales configurados";
            die;
        }
    } else if ($permisos["soyDesarrollo"]) {
        $idsCarterasGeneral = [];
    } else {
        trigger_error("No tiene permisos configurados para usuario " . $_SESSION[MID . "userId"]);
        echo "No tiene permisos configurados";
        die;
    }

    // if ($_SESSION[MID . "userId"] == 910753601) { //TODO: solo para pruebas
    //     $idsCarterasGeneral = [2121]; //TODO: solo para pruebas
    // } else {
    //     $idsCarterasGeneral = [];
    // }
    return $idsCarterasGeneral;
}

//establece una condicion principal de carteras
function establecerCondicionMaster($tabla = "av_carteraId")
{
    global $idsCarterasGeneral;
    if ($idsCarterasGeneral != null && count($idsCarterasGeneral) > 0) {
        return [
            $tabla => count($idsCarterasGeneral) == 1 ? $idsCarterasGeneral[0] : ['$in' => $idsCarterasGeneral]
        ];
    }
    return [];
}

?>
<? //_FIN_DE_ARCHIVO                                                                                                                                  
?>