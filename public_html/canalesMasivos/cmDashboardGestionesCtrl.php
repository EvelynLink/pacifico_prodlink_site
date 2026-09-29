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
$paletaColor = $cnf["Dashboard paleta colores"];

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor") || $Central->conPermiso("Canales Masivos,Supervisor Cobranzas"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

$idsCarterasGeneral = obtenerCarterasPermitidas($permisos); //siempre se filtra por estas carteras, solo para los usuarios que no son desarrollo

switch ($act) {
    case "obtenerParametrizacion":
        #region obtenerParametrizacion 
        $d = jsonStart();
        //filtros
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));
        $error = 0;
        $mensaje = "";
        $periodo = [];
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    // $desde = strtotime("-1 hour");
                    // $hasta = time();
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
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

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $error = 1;
                        $mensaje = "Por favor seleccione una opción válida";
                    } else {
                        $error = 0;
                        $mensaje = "";
                    }
                    break;
                case 'semanaanterior':
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $error = 1;
                        $mensaje = "Por favor seleccione una opción válida";
                    } else {
                        $error = 0;
                        $mensaje = "";
                    }
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
                                "fechaFin" => date('d/m/Y', $row["fechaFin"])
                            ];
                        }
                    }
                    break;
                case 'asigAnterior':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    $mongo = new MYMONGODB();
                    $condicionPeriodo = [
                        'activo' => (int) 0,
                        "cartera" => intval($filtroCartera),
                        'fecha' => [
                            '$gte' => strtotime('first day of previous month'),
                            '$lte' => strtotime('last day of previous month')
                        ],
                        'fechaCreacion' => ['$gte' => 0]
                    ];

                    $periodo[] = ["id" => -1, "nombre" => 'Todos los ciclos'];
                    if (count($condicionPeriodo) > 0) {
                        $mongo->buscar("control_carga_periodo", $condicionPeriodo, [], ['periodo' => 1]);
                        while ($row = $mongo->siguiente()) {
                            $periodo[] = [
                                "id" => $row["periodo"],
                                "nombre" => (string) $row["periodo"] . ' - ' . date('d/m/Y', $row["fecha"]),
                                "fechaInicio" =>  date('d/m/Y', $row["fecha"]),
                                "fechaFin" => date('d/m/Y', $row["fechaFin"])
                            ];
                        }
                    }
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $error = 1;
                        $mensaje = "Por favor seleccione una opción válida";
                    } else {
                        $error = 0;
                        $mensaje = "";
                    }
                    break;
            }
        }
        $carteras = [
            [
                "id" => "todo",
                "nombre" => "Todas las carteras"
            ]
        ];
        $mysql = new MYSQLDB();
        $tabla = "cobcartera";
        $sql = $mysql->mkSQL("SELECT cobCartera_id,cobCartera_nombre FROM " . $tabla . " WHERE cobCartera_estado=%N AND cobCartera_tipo='COBRANZA'", 1);
        $mysql->query($sql);
        while ($row = $mysql->fetchRow()) {
            $carteras[] = [
                "id" => $row["cobCartera_id"],
                "nombre" => $row["cobCartera_nombre"]
            ];
        }
        $campanias = [];
        $json["carteras"] = $carteras;
        $json["campanias"] = $campanias;
        $json["permisos"] = $permisos;
        $json["periodos"] = $periodo;
        $json["error"] = $error;
        $json["mensaje"] = $mensaje;
        #endregion 
        break;

    case "resincronizarCubo":
        resincronizarCubo();
        $json["terminar"] = "ok";
        break;

    case "obtenerTotales":
        #region obtenerTotales
        // Todos los indicadores salen de los cubos (cuAsignacionesGestionAP / cuGestionCobranzaMysql).
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroCanal = expect_safe_html($d["filtroCanal"]);
        // Sólo se aceptan estos valores; cualquier otra cosa se trata como "todos los canales"
        $filtroCanal = in_array($filtroCanal, ['AV', 'EMAIL', 'WHATSAPP'], true) ? $filtroCanal : 'todo';
        $filtroPeriodo = expect_safe_html($d["filtroPeriodo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $fechaIniPeriodo =  0;

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
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

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $desde = 0;
                        $hasta = 0;
                    }

                    break;
                case 'semanaanterior':
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $desde = 0;
                        $hasta = 0;
                    }
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
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $mongo = new MYMONGODB();
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 1];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }
                        $mongo->buscar('control_carga_periodo', $query, [],  ['_id' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodo =  $ro['fecha'];
                    }
                    break;
                case 'asigAnterior':
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $mongo = new MYMONGODB();
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }
                        $mongo->buscar('control_carga_periodo', $query, [],  ['_id' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                        $fechaIniPeriodo =  $ro['fecha'];
                    }
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    if (existeFechaRango($fechasCarga, $desde, $hasta)) {
                        $desde = 0;
                        $hasta = 0;
                    }
                    break;
            }

            $condicionCubAsignacion["cubAG_fechaInicio"] = intval($fechaIniPeriodo);
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
            if ($filtroPeriodo >= 0) {
                $condicionCubAsignacion["cubAG_ciclo"] = intval($filtroPeriodo);
                $condicionCubGestion["cubGC_ciclo"] = intval($filtroPeriodo);
            }
        }

        $json["totales"] = obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $filtroCanal);
        #endregion
        break;
    case "graficoHistorialGestiones":

        $d = jsonStart();

        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroTiempo  = expect_safe_html($d["filtroTiempo"]);
        $tipoGrafico   = expect_safe_html($d["tipo"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));


        $condicion = establecerCondicionMaster("avHist_cartera");

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            $desde = 0;
            $hasta = 0;

            switch ($filtroTiempo) {

                case 'asigActual':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 1];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }

                        $mongo->buscar('control_carga_periodo', $query, [],  ['_id' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                    }
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }

                        $mongo->buscar('control_carga_periodo', $query, [],  ['_id' => -1], 1);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                        $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                    }
                    break;
            }

            $condicion["avHist_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["avHist_cartera"] = intval($filtroCartera);
            //if ($filtroPeriodo >= 0) {
            $condicion['avHist_ciclo'] = (int)$filtroPeriodo;
            // } else {

            // }
        }




        // Grupos para intensidad
        $grupos = [];

        switch ($tipoGrafico) {

            case "INTENSIDAD":
                $grupos = ["INTENSIDAD", "INTENSIDAD AV", "INTENSIDAD WHATSAPP", "INTENSIDAD EMAIL"];
                break;

            case "INTENSIDAD PAGADA":
                $grupos = ["INTENSIDAD PAGADA", "INTENSIDAD PAGADA AV", "INTENSIDAD PAGADA WHATSAPP", "INTENSIDAD PAGADA EMAIL"];
                break;

            case "INTENSIDAD NO PAGADA":
                $grupos = ["INTENSIDAD NO PAGADA", "INTENSIDAD NO PAGADA AV", "INTENSIDAD NO PAGADA WHATSAPP", "INTENSIDAD NO PAGADA EMAIL"];
                break;

            default:
                $grupos = [strval($tipoGrafico)];
                break;
        }

        $condicion["avHist_grupo"] = ['$in' => $grupos];

        //CONSULTA
        $mongo = new MYMONGODB();
        $mongo->buscar("avHistorialGestiones", $condicion, [], ["avHist_fecha" => 1]);

        $data = [];

        foreach ($grupos as $g) {
            $data[$g] = [
                "TOTAL" => [],
                "MONTO" => [],
                "PORCENTAJE" => []
            ];
        }

        while ($row = $mongo->siguientex()) {

            $fecha = strtotime(date("Y-m-d", $row["avHist_fecha"]));
            $valor = $row["avHist_valor"] ?? 0;

            $grupo = strtoupper($row["avHist_grupo"]);
            $indicador = strtoupper($row["avHist_indicador"]);

            // SI ES INTENSIDAD, SOLO TOTAL
            if (strpos($tipoGrafico, "INTENSIDAD") !== false) {

                if ($indicador == "TOTAL") {
                    $data[$grupo]["TOTAL"][] = [
                        "x" => $fecha,
                        "y" => (float)$valor
                    ];
                }
            } else {
                //SI SON CARTERAS  
                $data[$grupo][$indicador][] = [
                    "x" => $fecha,
                    "y" => (float)$valor
                ];
            }
        }

        // -------------------- RESPUESTA --------------------
        $respuesta = [];
        $indexColor = 0;

        foreach ($data as $grupo => $valores) {

            if (strpos($tipoGrafico, "INTENSIDAD") !== false) {

                // SOLO TOTAL
                if (!empty($valores["TOTAL"])) {
                    $respuesta[] = [
                        "values" => $valores["TOTAL"],
                        "key" => $grupo,
                        "color" => $paletaColor[$indexColor % count($paletaColor)]
                    ];
                    $indexColor++;
                }
            } else {

                if (!empty($valores["TOTAL"])) {
                    $respuesta[] = [
                        "values" => $valores["TOTAL"],
                        "key" => "Total",
                        "color" => $paletaColor[0]
                    ];
                }

                if (!empty($valores["MONTO"])) {
                    $respuesta[] = [
                        "values" => $valores["MONTO"],
                        "key" => "Monto",
                        "color" => $paletaColor[1]
                    ];
                }

                if (!empty($valores["PORCENTAJE"])) {
                    $respuesta[] = [
                        "values" => $valores["PORCENTAJE"],
                        "key" => "Porcentaje",
                        "color" => $paletaColor[2]
                    ];
                }
            }
        }

        $json["respuesta"] = $respuesta;

        break;

    case "descargarGestiones":

        $d = jsonStart();

        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroTiempo  = expect_safe_html($d["filtroTiempo"]);
        $tipoCartera   = expect_safe_html($d["tipo"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));
        $filtroCanal   = expect_safe_html($d["filtroCanal"]);

        // En cuGestionCobranzaMysql el canal AV se guarda como 'TELEFONICA'
        $mapaCanalDescarga = ['AV' => 'TELEFONICA', 'EMAIL' => 'EMAIL', 'WHATSAPP' => 'WHATSAPP'];
        $usaCanalEspecificoDescarga = isset($mapaCanalDescarga[$filtroCanal]);

        $condicion = establecerCondicionMaster("cubAG_carteraId");
        $fechaIniPeriodo = 0;
        $desde = 0;
        $hasta = 0;
        $nombreCartera = "";

        // ?? FILTRO TIEMPO
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            $mongo = new MYMONGODB();

            if ($filtroCartera != "" && $filtroCartera != "todo") {

                switch ($filtroTiempo) {

                    case 'asigActual':
                        $query = ['cartera' => (int)$filtroCartera, 'activo' => 1];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }
                        break;

                    case 'asigAnterior':
                        $query = ['cartera' => (int)$filtroCartera, 'activo' => 0];
                        if ($filtroPeriodo >= 0) {
                            $query['periodo'] = (int)$filtroPeriodo;
                        }
                        break;
                }

                $mongo->buscar('control_carga_periodo', $query, [], ['_id' => -1], 1);
                $ro = $mongo->siguiente();

                if ($ro) {
                    $fechaIniPeriodo = $ro['fecha'];
                    $desde = strtotime(date('Y-m-d', $ro['fecha']) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $ro['fechaFin']) . " 23:59:59");
                }
            }

            $condicion["cubAG_fechaInicio"] = intval($fechaIniPeriodo);
        }




        //FILTRO CARTERA

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["cubAG_carteraId"] = strval($filtroCartera);

            //obtener nombre Cartera

            $mysql = new MYSQLDB();
            $tabla = "cobcartera";

            $sql = $mysql->mkSQL(
                "SELECT cobCartera_nombre FROM " . $tabla . " WHERE cobCartera_id=%N",
                $filtroCartera
            );

            $mysql->query($sql);

            if ($row = $mysql->fetchRow()) {
                $nombreCartera = $row["cobCartera_nombre"];
            }
            if ($filtroPeriodo >= 0) {
                $condicion['cubAG_ciclo'] = (int)$filtroPeriodo;
            }
        }

        if ($fechaIniPeriodo > 0) {

            $mdbAsig = new MYMONGODB();

            $usaGestiones = true;
            $matchGestion = [];

            // Mismas expresiones que las tarjetas, para que el Excel cuadre con el dashboard.
            $exprCanalDesc = exprCanalMejorGestion($filtroCanal);
            $gest = $exprCanalDesc['gestionada'];
            $noGest = $exprCanalDesc['noGestionada'];
            $tip1 = $exprCanalDesc['tip1'];
            $tip2 = $exprCanalDesc['tip2'];
            $tipsCompromiso = tipificacionesCompromisoPago();
            $exprTarjeta = [];

            switch ($tipoCartera) {

                // SIN GESTIONES (Cartera Asignada no se filtra por canal)
                case 'CARTERA ASIGNADA':
                    $usaGestiones = false;
                    break;

                case 'CARTERA NO GESTIONADA':
                    $usaGestiones = false;
                    $exprTarjeta[] = $noGest;
                    break;

                case 'CARTERA NO GESTIONADA PAGADA':
                    $usaGestiones = false;
                    $exprTarjeta[] = $noGest;
                    $condicion['cubAG_montoTotalPago'] = ['$gt' => 0];
                    break;

                // CON GESTIONES
                case 'CARTERA GESTIONADA':
                    $exprTarjeta[] = $gest;
                    break;

                case 'CARTERA GESTIONADA NO CONTACTADA':
                    $exprTarjeta[] = $gest;
                    $exprTarjeta[] = ['$eq' => [$tip1, 'SIN CONTACTO']];
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'SIN CONTACTO';
                    break;

                case 'CARTERA GESTIONADA CONTACTADA':
                    $exprTarjeta[] = $gest;
                    $exprTarjeta[] = ['$in' => [$tip1, ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']]];
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = [
                        '$in' => ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']
                    ];
                    break;

                case 'CONTACTO DIRECTO':
                    $exprTarjeta[] = $gest;
                    $exprTarjeta[] = ['$eq' => [$tip1, 'CONTACTO DIRECTO']];
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO DIRECTO';
                    break;

                case 'CONTACTO INDIRECTO':
                    $exprTarjeta[] = $gest;
                    $exprTarjeta[] = ['$eq' => [$tip1, 'CONTACTO INDIRECTO']];
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO INDIRECTO';
                    break;

                case 'CARTERA GESTIONADA PAGADA':
                    $exprTarjeta[] = $gest;
                    $condicion['cubAG_montoTotalPago'] = ['$gt' => 0];
                    break;

                case 'COMPROMISO PAGO':
                    $exprTarjeta[] = $gest;
                    $exprTarjeta[] = ['$eq' => [$tip1, 'CONTACTO DIRECTO']];
                    $exprTarjeta[] = ['$in' => [$tip2, $tipsCompromiso]];
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO DIRECTO';
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta2'] = ['$in' => $tipsCompromiso];
                    break;
            }

            if (count($exprTarjeta) > 0) {
                $condicion['$expr'] = ['$and' => $exprTarjeta];
            }

            $dataAsig = [];

            // SIN GESTIONES
            if (!$usaGestiones) {

                $mdbAsig->buscar("cuAsignacionesGestionAP", $condicion);

                while ($row = $mdbAsig->siguiente()) {
                    $dataAsig[] = $row;
                }
            }

            // CON GESTIONES
            else {

                $pipeline = [

                    ['$match' => $condicion],

                    [
                        '$lookup' => [
                            'from' => 'cuGestionCobranzaMysql',
                            'let' => [
                                'factura' => '$cubAG_numFactura',
                                'cartera' => '$cubAG_carteraId',
                                'periodo' => '$cubAG_ciclo',
                                'fechaPeriodo' => '$cubAG_fechaPeriodo'
                            ],
                            'pipeline' => [

                                //MATCH RELACIONAL
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$and' => [
                                                ['$eq' => ['$cubGC_numFactura', '$$factura']],
                                                ['$eq' => ['$cubGC_carteraId', '$$cartera']],
                                                ['$eq' => ['$cubGC_ciclo', '$$periodo']],
                                                ['$eq' => ['$cubGC_fechaPeriodo', '$$fechaPeriodo']]
                                            ]
                                        ]
                                    ]
                                ],

                                //FILTRO DE FECHA Y CANAL (AV/EMAIL/WHATSAPP, si se seleccionó uno)
                                [
                                    '$match' => array_merge(
                                        [
                                            'cubGC_fechaGestion' => [
                                                '$gte' => $desde,
                                                '$lte' => $hasta
                                            ],
                                            'cubGC_tipificacion_respuesta2' => [
                                                '$nin' => tipificacionesExcluidasGestion()
                                            ]
                                        ],
                                        $usaCanalEspecificoDescarga
                                            ? ['cubGC_canal' => $mapaCanalDescarga[$filtroCanal]]
                                            : []
                                    )
                                ]

                            ],
                            'as' => 'gestiones'
                        ]
                    ],

                    ['$unwind' => '$gestiones']
                ];

                if (!empty($matchGestion)) {
                    $pipeline[] = ['$match' => $matchGestion];
                }

                // SOLO GESTIONES
                $pipeline[] = [
                    '$replaceRoot' => ['newRoot' => '$gestiones']
                ];

                $mdbAsig->aggregate("cuAsignacionesGestionAP", $pipeline);

                while ($row = $mdbAsig->siguiente()) {
                    $dataAsig[] = $row;
                }
            }
        }

        trigger_error("tipo cartera. " . $tipoCartera . " - " . count($dataAsig));

        //Nombre del archivo
        $fechaHoy = date("Y_m_d");

        $nombreArchivo = $nombreCartera . "_" . $tipoCartera . "_" . $fechaHoy;

        $ruta = generarExcel($dataAsig, $usaGestiones, $nombreArchivo, $tipoCartera);

        $json["ruta"] = $ruta;

        trigger_error("archivo ruta: " . $ruta);

        break;
}

#region funciones
function resincronizarCubo()
{
    ini_set("max_execution_time", 100000000);
    ini_set('memory_limit', '3000M');
    $mdb = new MYMONGODB();
    $mdb2 = new MYMONGODB();
    $mdb3 = new MYMONGODB();

    /* Copiar registros desde cbEnvioMails, avProgramadas y avProgramadasWhatsApp al cubo de gestiones si no existen */
    require_once '../cubos/plugins/cu.gestionCobranza.class.php';
    $cub = new cuPGgestionCobranza();
    //lista de carteras a procesar
    $carteras = [
        2120,
        2121,
        2122,
    ];
    foreach ($carteras as $carteraId) {
        //print_h("CARTERA ".$carteraId);
        //busque periodos activos para esta cartera
        $mdb->buscar('control_carga_periodo', ['activo' => 1, 'cartera' => $carteraId]);
        if ($doc = $mdb->siguiente()) {
            $fechaInicio = $doc['fecha'];
            $fechaFin = $doc['fechaFin'];
        }
        //busque todas las gestiones en el cubo de gestiones para esta cartera y fechas
        $cuantas = $mdb->buscar('cuGestionCobranzaMysql', [
            'cubGC_carteraId' => strval($carteraId),
            'cubGC_fechaGestion' => ['$gte' => $fechaInicio],
        ]);
        $encubados = [];
        while ($doc = $mdb->siguiente()) {
            $encubados[($doc['cubGC_avId'])->__toString()] = $doc;
        }
        //print_h('encubados '.count($encubados));
        //parametrice las colecciones y campos para cada canal
        $canales = [
            'EMAILS' => [
                'tabla' => 'cbEnvioMails',
                'campoCartera' => 'cem_susCarteraId',
                'campoFecha' => 'cem_susFechaEnvio',
            ],
            'WHATSAPP' => [
                'tabla' => 'avProgramadasWhatsApp',
                'campoCartera' => 'ws_carteraId',
                'campoFecha' => 'ws_fecha',
            ],
            'AGENTES VIRTUALES' => [
                'tabla' => 'avProgramadas',
                'campoCartera' => 'av_carteraId',
                'campoFecha' => 'av_fechaActualizaLlamada',
            ],
        ];
        foreach ($canales as $key => $val) {
            //print_h('IGUALO '.$key);
            //busque todas las gestiones de este canal para esta cartera y fechas
            $cuantas = $mdb->buscar($val['tabla'], [
                $val['campoCartera'] => $carteraId,
                $val['campoFecha'] => ['$gte' => $fechaInicio]
            ]);
            $gestiones = [];
            while ($doc = $mdb->siguiente()) {
                $gestiones[($doc['_id'])->__toString()] = $doc;
            }
            //print_h(count($gestiones));
            $encontrados = 0;
            $noencontrados = 0;
            foreach ($gestiones as $idG => $ges) {
                if (isset($encubados[$idG])) {
                    $encontrados++;
                } else {
                    $noencontrados++;
                    //trigger_error(".");
                    //print_h($ges);
                    $cub->onAdd([$val['tabla'] . ':' . $idG]);
                    //trigger_error(".");
                }
            }
            //print_h('encontrados '.$encontrados);
            //print_h('noencontrados '.$noencontrados);
        }
    }
}

//TOTALES GESTION NUEVOS
// Tipificaciones del cubo de gestiones que no son gestión real y no se cuentan ni se descargan.
function tipificacionesExcluidasGestion()
{
    return ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado'];
}

// Tipificaciones 2 que cuentan como compromiso de pago (tarjeta, Excel y agente virtual).
function tipificacionesCompromisoPago(): array
{
    return ['PAGA EN FECHA', 'ABONO CUOTA', 'CONFIRMACION DE PAGO', 'NEGOCIACION EN CURSO ALIVIO', 'COMPROMISO DE PAGO'];
}

// Expresiones de gestión según canal: raíz del cubo para "todo" o cubAG_mejorGestion.<CANAL>.
// Un canal cuenta como gestionado si su cuGestionId no está vacío (mismo criterio que generarExcel).
function exprCanalMejorGestion($filtroCanal)
{
    if (!in_array($filtroCanal, ['AV', 'EMAIL', 'WHATSAPP'], true)) {
        $gestionada = ['$eq' => ['$cubAG_gestionada', 1]];
        return [
            'especifico' => false,
            'gestionada' => $gestionada,
            'noGestionada' => ['$not' => [$gestionada]],
            'tip1' => '$cubAG_tipificacion1',
            'tip2' => '$cubAG_tipificacion2',
        ];
    }
    $base = '$cubAG_mejorGestion.' . $filtroCanal;
    $gestionada = ['$not' => [['$in' => [['$ifNull' => [$base . '.cuGestionId', '']], ['', null]]]]];
    return [
        'especifico' => true,
        'gestionada' => $gestionada,
        'noGestionada' => ['$not' => [$gestionada]],
        'tip1' => ['$ifNull' => [$base . '.tipificacion1', '']],
        'tip2' => ['$ifNull' => [$base . '.tipificacion2', '']],
    ];
}

function obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $filtroCanal = 'todo')
{
    // Asignada no depende del canal; gestionada y tipificaciones salen de cubAG_mejorGestion.<CANAL>.
    $exprCanal = exprCanalMejorGestion($filtroCanal);
    $usaCanalEspecifico = $exprCanal['especifico'];
    $exprGestionadaCanal = $exprCanal['gestionada'];
    $exprNoGestionadaCanal = $exprCanal['noGestionada'];
    $campoTipificacion1Canal = $exprCanal['tip1'];
    $campoTipificacion2Canal = $exprCanal['tip2'];

    $resultado = [
        "gestion" => [
            [
                "nombre" => "Cartera Gestionada",
                "total" => 0,
                "montoTotal" => "$0.00",
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
                "montoCapital" => "$0.00 de $0.00",
                "montoCapitalTooltip" => "$0,00 de $0,00"
            ],
            [
                "nombre" => "Cartera gestionada con contacto",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Cartera no gestionada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Compromisos de pago registrados",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Cartera gestionada pagada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" =>  "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Cartera no gestionada pagada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" =>  "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],

        ],
        "tipificacion" => [
            [
                "nombre" => "Contacto directo",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Contacto indirecto",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ],
            [
                "nombre" => "Sin contacto",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0",
                "monto" => "$0.00 de $0.00",
                "montoTooltip" => "$0,00 de $0,00",
            ]
        ],
        "intensidad" => [
            [
                "nombre" => "Intensidad",
                "total" => 0,
                "tooltip" => ""
            ],
        ],
        "intensidadCarteraPagada" => [
            [
                "nombre" => "Intensidad Cartera Pagada",
                "total" => 0,
                "tooltip" => ""
            ],
        ],
        "intensidadCarteraNoPagada" => [
            [
                "nombre" => "Intensidad Cartera No Pagada",
                "total" => 0,
                "tooltip" => ""
            ],
        ],
    ];

    $total = 0;
    $totalMonto = 0;
    $totalGestionado = 0;
    $totalMontoGestion = 0;

    $totalMontoSinGestion = 0;
    $totalMontoContactadaContacto = 0;
    $totalMontoContactoDirecto = 0;
    $totalMontoContactoIndirecto = 0;
    $totalMontoSinContacto = 0;

    $totalContactado = 0;
    $sinGestion = 0;
    $contactoDirecto        = 0;
    $contactoIndirecto = 0;
    $sinContactoGestionado = 0;
    $pagaEnFecha = 0;
    $totalGestionadoPagada = 0;
    $totalMontoPagado = 0;
    $totalNoGestionadoPagada = 0;
    $totalMontoNoGestionadoPagada = 0;
    $totalMontoContactoDirectoPagaFecha = 0;

    //Total asignado
    $mdbAsig = new MYMONGODB();

    if ($condicionCubAsignacion["cubAG_fechaInicio"] > 0 /*&& $condicionCubAsignacion["cubAG_fechaFin"] > 0*/) {
        $pipeline = [
            [
                '$match' => $condicionCubAsignacion
            ],
            [
                '$group' => [
                    '_id' => null,
                    // Total registros
                    'totalRegistros' => ['$sum' => 1],
                    // Total capital
                    'totalCapital' => ['$sum' => '$cubAG_capitalActual'],
                    // Capital inicial del período de todo el universo asignado
                    'totalCapitalInicialPeriodo' => ['$sum' => '$cubAG_capitalInicialPeriodo'],
                    // Total gestionados (en el canal filtrado, o en general si no hay filtro de canal)
                    'totalGestionados' => [
                        '$sum' => [
                            '$cond' => [
                                $exprGestionadaCanal,
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital gestionado
                    'capitalGestionado' => [
                        '$sum' => [
                            '$cond' => [
                                $exprGestionadaCanal,
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Total sin gestión
                    'totalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                $exprNoGestionadaCanal,
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital sin gestión
                    'capitalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                $exprNoGestionadaCanal,
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Contacto Directo
                    'totalContactoDirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO DIRECTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Contacto Directo
                    'capitalContactoDirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO DIRECTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Contacto Indirecto
                    'totalContactoIndirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO INDIRECTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Contacto Indirecto
                    'capitalContactoIndirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO INDIRECTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Sin Contacto
                    'totalSinContacto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'SIN CONTACTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Sin Contacto
                    'capitalSinContacto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'SIN CONTACTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    //Compromisos de pagos registrados
                    'totalContactoDirectoPagaFecha' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO DIRECTO']],
                                        [
                                            '$in' => [
                                                $campoTipificacion2Canal,
                                                tipificacionesCompromisoPago()
                                            ]
                                        ]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    //Total monto paga en fecha
                    'totalMontoContactoDirectoPagaFecha' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$eq' => [$campoTipificacion1Canal, 'CONTACTO DIRECTO']],
                                        [
                                            '$in' => [
                                                $campoTipificacion2Canal,
                                                tipificacionesCompromisoPago()
                                            ]
                                        ]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Total gestionadas pagadas
                    'totalGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Total monto pagado
                    'totalMontoPagado' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprGestionadaCanal,
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                '$cubAG_montoTotalPago',
                                0
                            ]
                        ]
                    ],
                    // Total no gestionadas pagadas
                    'totalNoGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprNoGestionadaCanal,
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Pagos no gestionadas pagadas
                    'totalMontoNoGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $exprNoGestionadaCanal,
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                '$cubAG_montoTotalPago',
                                0
                            ]
                        ]
                    ],
                ]
            ]
        ];
        //        print_h($pipeline);exit;
        $mdbAsig->aggregate("cuAsignacionesGestionAP", $pipeline);
        if ($row = $mdbAsig->siguiente()) {
            $total              = (int)$row['totalRegistros'];
            $totalMonto         = (float)$row['totalCapital'];
            $totalMontoInicialPeriodo = (float)$row['totalCapitalInicialPeriodo'];
            $totalGestionado    = (int)$row['totalGestionados'];
            $totalMontoGestion  = (float)$row['capitalGestionado'];
            $sinGestion  = (int) $row['totalSinGestion'];
            $totalMontoSinGestion = (float)$row['capitalSinGestion'];

            $contactoDirecto        = (int)$row['totalContactoDirecto'];
            $totalMontoContactoDirecto = (float)$row['capitalContactoDirecto'];
            $contactoIndirecto      = (int)$row['totalContactoIndirecto'];
            $totalMontoContactoIndirecto = (float)$row['capitalContactoIndirecto'];
            $sinContactoGestionado  = (int)$row['totalSinContacto'];
            $totalMontoSinContacto = (float)$row['capitalSinContacto'];
            $pagaEnFecha = (int)$row['totalContactoDirectoPagaFecha'];
            $totalMontoContactoDirectoPagaFecha = (float)$row['totalMontoContactoDirectoPagaFecha'];

            $totalGestionadoPagada = (int)$row['totalGestionadoPagada'];
            $totalMontoPagado = (float)$row['totalMontoPagado'];
            $totalNoGestionadoPagada = (int)$row['totalNoGestionadoPagada'];
            $totalMontoNoGestionadoPagada = (float)$row['totalMontoNoGestionadoPagada'];
        }
        $totalContactado = $contactoDirecto + $contactoIndirecto;
        $totalMontoContactadaContacto = $totalMontoContactoDirecto + $totalMontoContactoIndirecto;
    }

    if ($total > 0) {


        $totalGestionesInt = 0;
        $totalTelefonica = 0;
        $totalWhatsapp = 0;
        $totalEmail = 0;

        $totalPagadas = 0;
        $totalGestionesPagadas = 0;
        $totalTelefonicaPagadas = 0;
        $totalWhatsappPagadas = 0;
        $totalEmailPagadas = 0;

        $totalNoPagadas = 0;
        $totalGestionesNoPagadas = 0;
        $totalTelefonicaNoPagadas = 0;
        $totalWhatsappNoPagadas = 0;
        $totalEmailNoPagadas = 0;


        $mdbGestion = new MYMONGODB();


        //pipeline intensidades

        // En cuGestionCobranzaMysql el canal AV se guarda como 'TELEFONICA'
        $mapaCanalGestionCobranza = [
            'AV' => 'TELEFONICA',
            'EMAIL' => 'EMAIL',
            'WHATSAPP' => 'WHATSAPP'
        ];

        $condicionCubGestion = [
            '$and' => [
                $condicionCubGestion,
                [
                    'cubGC_tipificacion_respuesta2' => [
                        '$nin' => tipificacionesExcluidasGestion()
                    ]
                ]
            ]
        ];

        if ($usaCanalEspecifico && isset($mapaCanalGestionCobranza[$filtroCanal])) {
            $condicionCubGestion['$and'][] = [
                'cubGC_canal' => $mapaCanalGestionCobranza[$filtroCanal]
            ];
        }


        $pipelineGestion = [
            [
                '$match' => $condicionCubGestion
            ],
            [
                '$group' => [
                    '_id' => null,

                    // Total gestiones
                    'totalGestiones' => [
                        '$sum' => 1
                    ],

                    // Telefónica
                    'totalTelefonica' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'TELEFONICA']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // WhatsApp
                    'totalWhatsapp' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'WHATSAPP']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // Email
                    'totalEmail' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'EMAIL']],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $mdbGestion->aggregate("cuGestionCobranzaMysql", $pipelineGestion);

        if ($row = $mdbGestion->siguiente()) {

            $totalGestionesInt  = (int)$row['totalGestiones'];
            $totalTelefonica = (int)$row['totalTelefonica'];
            $totalWhatsapp   = (int)$row['totalWhatsapp'];
            $totalEmail      = (int)$row['totalEmail'];
        }
        trigger_error("total gestiones" . $totalGestionesInt);

        //pipeline intensidades pagadas:

        //se toman las gestionadas de los clientes que tienen el pago completo en conversación con Mariel y Any 12/03/2026
        //El total pagados se toman de las que tienen pago completo en conversación con Mariel y Any 12/03/2026

        $pagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$gte', $filtroCanal);

        $totalPagadas = $pagadas["totalPagos"];
        $totalGestionesPagadas = $pagadas["totalGestiones"];
        $totalTelefonicaPagadas = $pagadas["telefonica"];
        $totalWhatsappPagadas = $pagadas["whatsapp"];
        $totalEmailPagadas = $pagadas["email"];

        $noPagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$lt', $filtroCanal);

        $totalNoPagadas = $noPagadas["totalPagos"];
        $totalGestionesNoPagadas = $noPagadas["totalGestiones"];
        $totalTelefonicaNoPagadas = $noPagadas["telefonica"];
        $totalWhatsappNoPagadas = $noPagadas["whatsapp"];
        $totalEmailNoPagadas = $noPagadas["email"];



        //intensidad


        $resultado["intensidad"][0]["total"] = formatea_numero(round(isset($totalGestionesInt) && $total > 0 ? ($totalGestionesInt / $total) : 0, 2), 2, ",", ".");
        $intLLamadas =  $total > 0 ? formatea_numero(round(isset($totalTelefonica) && $total > 0 ? ($totalTelefonica / $total) : 0, 2), 2, ",", ".") : 0;
        $intCorreo = $total > 0 ? formatea_numero(round(isset($totalEmail) && $total > 0 ? ($totalEmail / $total) : 0, 2), 2, ",", ".") : 0;
        $intWs = $total > 0 ? formatea_numero(round(isset($totalWhatsapp) && $total > 0 ? ($totalWhatsapp / $total) : 0, 2), 2, ",", ".") : 0;
        $resultado["intensidad"][0]["tooltip"] = [
            $intLLamadas, //. " Intensidad telefonía agente virtual",
            $intCorreo, //. " Intensidad correo electrónico",
            $intWs //. " Intensidad WhatsApp"
        ];

        $resultado["intensidadCarteraPagada"][0]["total"] = formatea_numero(round(isset($totalGestionesPagadas) && $totalPagadas > 0 ? ($totalGestionesPagadas / $totalPagadas) : 0, 2), 2, ",", ".");
        $intLLamadas = formatea_numero(round(isset($totalTelefonicaPagadas) && $totalPagadas > 0 ? ($totalTelefonicaPagadas / $totalPagadas) : 0, 2), 2, ",", ".");
        $intCorreo = formatea_numero(round(isset($totalEmailPagadas) && $totalPagadas > 0 ? ($totalEmailPagadas / $totalPagadas) : 0, 2), 2, ",", ".");
        $intWs = formatea_numero(round(isset($totalWhatsappPagadas) && $totalPagadas > 0 ? ($totalWhatsappPagadas / $totalPagadas) : 0, 2), 2, ",", ".");
        $resultado["intensidadCarteraPagada"][0]["tooltip"] = [
            $intLLamadas, //. " Intensidad telefonía agente virtual",
            $intCorreo, //. " Intensidad correo electrónico",
            $intWs //. " Intensidad WhatsApp"
        ];

        $resultado["intensidadCarteraNoPagada"][0]["total"] = formatea_numero(round(isset($totalGestionesNoPagadas) && $totalNoPagadas > 0 ? ($totalGestionesNoPagadas / $totalNoPagadas) : 0, 2), 2, ",", ".");
        $intLLamadas = formatea_numero(round(isset($totalTelefonicaNoPagadas) && $totalNoPagadas > 0 ? ($totalTelefonicaNoPagadas / $totalNoPagadas) : 0, 2), 2, ",", ".");
        $intCorreo = formatea_numero(round(isset($totalEmailNoPagadas) && $totalNoPagadas > 0 ? ($totalEmailNoPagadas / $totalNoPagadas) : 0, 2), 2, ",", ".");
        $intWs = formatea_numero(round(isset($totalWhatsappNoPagadas) && $totalNoPagadas > 0 ? ($totalWhatsappNoPagadas / $totalNoPagadas) : 0, 2), 2, ",", ".");
        $resultado["intensidadCarteraNoPagada"][0]["tooltip"] = [
            $intLLamadas, //. " Intensidad telefonía agente virtual",
            $intCorreo, //. " Intensidad correo electrónico",
            $intWs //. " Intensidad WhatsApp"
        ];


        // Cartera asignada: siempre el universo completo, sin importar el canal filtrado.
        $totalAsignacion = $total;
        $totalMontoInicialPeriodoAsignacion = $totalMontoInicialPeriodo;
        $totalMontoAsignacion = $totalMonto;
        $resultado["gestion"][0]["total"] = formatea_numero($totalAsignacion, 0, ",", ".");
        $resultado["gestion"][0]["montoTotal"] = " $" . formatea_numero($totalMontoInicialPeriodoAsignacion, 2, ",", ".");
        $resultado["gestion"][0]["porcentaje"] = $totalAsignacion > 0 ? formatea_numero(($totalGestionado * 100) / $totalAsignacion, 2, ",", ".") : 0;
        $resultado["gestion"][0]["tooltip"] = formatea_numero($totalGestionado, 0, ",", ".") . " de " . formatea_numero($totalAsignacion, 0, ",", ".");
        $resultado["gestion"][0]["monto"] = "$" . abreviaNumero($totalMontoGestion, 1) . " de $" . abreviaNumero($totalMontoAsignacion, 1);
        $resultado["gestion"][0]["montoTooltip"] = "$" . formatea_numero($totalMontoGestion, 2, ",", ".") . " de $" . formatea_numero($totalMontoAsignacion, 2, ",", ".");
        //cartera gestionada con contacto
        $resultado["gestion"][1]["total"] = $totalContactado;
        $resultado["gestion"][1]["porcentaje"] = $totalGestionado > 0 ? formatea_numero(($totalContactado * 100) / $totalGestionado, 2, ",", ".") : 0;
        $resultado["gestion"][1]["tooltip"] = formatea_numero($totalContactado, 0, ",", ".") . " de " . formatea_numero($totalGestionado, 0, ",", ".");
        $resultado["gestion"][1]["monto"] = "$" . abreviaNumero($totalMontoContactadaContacto, 1) . " de $" . abreviaNumero($totalMontoGestion, 1);
        $resultado["gestion"][1]["montoTooltip"] = "$" . formatea_numero($totalMontoContactadaContacto, 2, ",", ".") . " de $" . formatea_numero($totalMontoGestion, 2, ",", ".");
        // Cartera no gestionada: asignada sin gestión en el canal filtrado (o en ninguno si es "todo").
        $resultado["gestion"][2]["total"] = $sinGestion;
        $resultado["gestion"][2]["porcentaje"] = $totalAsignacion > 0 ? formatea_numero(($sinGestion * 100) / $totalAsignacion, 2, ",", ".") : 0;
        $resultado["gestion"][2]["tooltip"] = formatea_numero($sinGestion, 0, ",", ".") . " de " . formatea_numero($totalAsignacion, 0, ",", ".");
        $resultado["gestion"][2]["monto"] = "$" . abreviaNumero($totalMontoSinGestion, 1) . " de $" . abreviaNumero($totalMontoAsignacion, 1);
        $resultado["gestion"][2]["montoTooltip"] = "$" . formatea_numero($totalMontoSinGestion, 2, ",", ".") . " de $" . formatea_numero($totalMontoAsignacion, 2, ",", ".");
    }
    if ($totalGestionado > 0) {
        //contacto directo
        $resultado["tipificacion"][0]["total"] = $contactoDirecto;
        $resultado["tipificacion"][0]["porcentaje"] = $totalContactado > 0 ? formatea_numero(($contactoDirecto * 100) / $totalContactado, 2, ",", ".") : 0;
        $resultado["tipificacion"][0]["tooltip"] = formatea_numero($contactoDirecto, 0, ",", ".") . " de " . formatea_numero($totalContactado, 0, ",", ".");
        $resultado["tipificacion"][0]["monto"] = "$" . abreviaNumero($totalMontoContactoDirecto, 1) . " de $" . abreviaNumero($totalMontoContactadaContacto, 1);
        $resultado["tipificacion"][0]["montoTooltip"] = "$" . formatea_numero($totalMontoContactoDirecto, 2, ",", ".") . " de $" . formatea_numero($totalMontoContactadaContacto, 2, ",", ".");
        //contacto indirecto
        $resultado["tipificacion"][1]["total"] = $contactoIndirecto;
        $resultado["tipificacion"][1]["porcentaje"] = $totalContactado > 0 ? formatea_numero(($contactoIndirecto * 100) / $totalContactado, 2, ",", ".") : 0;
        $resultado["tipificacion"][1]["tooltip"] =  formatea_numero($contactoIndirecto, 0, ",", ".") . " de " . formatea_numero($totalContactado, 0, ",", ".");
        $resultado["tipificacion"][1]["monto"] = "$" . abreviaNumero($totalMontoContactoIndirecto, 1) . " de $" . abreviaNumero($totalMontoContactadaContacto, 1);
        $resultado["tipificacion"][1]["montoTooltip"] = "$" . formatea_numero($totalMontoContactoIndirecto, 2, ",", ".") . " de $" . formatea_numero($totalMontoContactadaContacto, 2, ",", ".");
        //sin contacto
        $resultado["tipificacion"][2]["total"] = $sinContactoGestionado;
        $resultado["tipificacion"][2]["porcentaje"] =  $totalGestionado > 0 ? formatea_numero(($sinContactoGestionado * 100) / $totalGestionado, 2, ",", ".") : 0;
        $resultado["tipificacion"][2]["tooltip"] = formatea_numero($sinContactoGestionado, 0, ",", ".") . " de " . formatea_numero($totalGestionado, 0, ",", ".");
        $resultado["tipificacion"][2]["monto"] = "$" . abreviaNumero($totalMontoSinContacto, 1) . " de $" . abreviaNumero($totalMontoGestion, 1);
        $resultado["tipificacion"][2]["montoTooltip"] = "$" . formatea_numero($totalMontoSinContacto, 2, ",", ".") . " de $" . formatea_numero($totalMontoGestion, 2, ",", ".");
        //compromisos pago
        $resultado["gestion"][3]["porcentaje"] =  $contactoDirecto > 0 ? formatea_numero(($pagaEnFecha * 100) / $contactoDirecto, 2, ",", ".") : 0;
        $resultado["gestion"][3]["tooltip"] = formatea_numero($pagaEnFecha, 0, ",", ".") . " de " . formatea_numero($contactoDirecto, 0, ",", ".");
        $resultado["gestion"][3]["monto"] = "$" . abreviaNumero($totalMontoContactoDirectoPagaFecha, 1) . " de $" . abreviaNumero($totalMontoContactoDirecto, 1);
        $resultado["gestion"][3]["montoTooltip"] = "$" . formatea_numero($totalMontoContactoDirectoPagaFecha, 2, ",", ".") . " de $" . formatea_numero($totalMontoContactoDirecto, 2, ",", ".");

        //pagos
        $resultado["gestion"][4]["total"] = $totalGestionadoPagada;
        $resultado["gestion"][4]["porcentaje"] = $totalGestionado > 0 ?  formatea_numero(($totalGestionadoPagada * 100) / $totalGestionado, 2, ",", ".") : 0;
        $resultado["gestion"][4]["tooltip"] = formatea_numero($totalGestionadoPagada, 0, ",", ".") . " de " . formatea_numero($totalGestionado, 0, ",", ".");
        $resultado["gestion"][4]["monto"] = "$" . abreviaNumero($totalMontoPagado, 1) . " de $" . abreviaNumero($totalMontoGestion, 1);
        $resultado["gestion"][4]["montoTooltip"] = "$" . formatea_numero($totalMontoPagado, 2, ",", ".") . " de $" . formatea_numero($totalMontoGestion, 2, ",", ".");


        //Cartera no gestionada pagada

        $resultado["gestion"][5]["total"] =  $totalNoGestionadoPagada;
        $resultado["gestion"][5]["porcentaje"] =  formatea_numero(($sinGestion > 0) ? ($totalNoGestionadoPagada * 100) / $sinGestion : 0, 2, ",", ".");
        $resultado["gestion"][5]["tooltip"] = formatea_numero($totalNoGestionadoPagada, 0, ",", ".")  . " de " . formatea_numero($sinGestion, 0, ",", ".");
        $resultado["gestion"][5]["monto"] = "$" . abreviaNumero($totalMontoNoGestionadoPagada, 1) . " de $" . abreviaNumero($totalMontoSinGestion, 1);
        $resultado["gestion"][5]["montoTooltip"] = "$" . formatea_numero($totalMontoNoGestionadoPagada, 2, ",", ".") . " de $" . formatea_numero($totalMontoSinGestion, 2, ",", ".");
    }

    return $resultado;
}

function generarExcel($data, $usaGestiones, $archivoExcelNombre, $tipoCartera = '')
{

    require_once("../comunes/classes/class.coGeneraExcel.php");

    $anio = date('Y');
    $mes = date('m');


    // RUTA RELATIVA (para la clase)
    $rutaRelativaExcel = "/canalesMasivos/reportesExcel/$anio/$mes/";

    //RUTA FÍSICA (DONDE SE GUARDA)
    $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . "/canalesMasivos/reportesExcel/$anio/$mes";

    if (!is_dir($rutaFisica)) {
        mkdir($rutaFisica, 0777, true);
    }

    $objExcel = new coGeneraExcel();
    $objExcel->setNombreArchivo($archivoExcelNombre);
    $objExcel->setUbicacion($rutaRelativaExcel);

    $nombreHoja = "Resumen";
    $objExcel->addHoja(0, $nombreHoja);

    // CABECERAS
    if (!$usaGestiones) {

        if ($tipoCartera === 'CARTERA ASIGNADA') {

            $cabeceras = array(
                "Factura",
                "Ciclo",
                "Capital Inicial",
                "Capital Actual",
                "Deuda Neta Actual",
                "AV - Tipificación 1",
                "AV - Tipificación 2",
                "AV - Fecha Gestión",
                "AV - Ponderación",
                "EMAIL - Tipificación 1",
                "EMAIL - Tipificación 2",
                "EMAIL - Fecha Gestión",
                "EMAIL - Ponderación",
                "WHATSAPP - Tipificación 1",
                "WHATSAPP - Tipificación 2",
                "WHATSAPP - Fecha Gestión",
                "WHATSAPP - Ponderación",
                "Mejor Gestión - Canal",
                "Mejor Gestión - Tipificación 1",
                "Mejor Gestión - Tipificación 2",
                "Mejor Gestión - Fecha Gestión",
                "Mejor Gestión - Ponderación"
            );
        } else if ($tipoCartera === 'CARTERA NO GESTIONADA PAGADA') {

            // Nunca tienen gestión (canal, tipificaciones, etc.), pero sí pudieron pagar
            $cabeceras = array(
                "Factura",
                "Ciclo",
                "Capital",
                "Deuda Neta Actual",
                "Pago Total"
            );
        } else {

            // CARTERA NO GESTIONADA: nunca van a tener campos de gestión (canal, tipificaciones, etc.)
            $cabeceras = array(
                "Factura",
                "Ciclo",
                "Capital",
                "Deuda Neta Actual"
            );
        }
    } else {
        $cabeceras = array(
            "Factura",
            "Ciclo",
            "Capital",
            "Deuda Neta Actual",
            "Canal",
            "Campaña",
            "Tipificación 1",
            "Tipificación 2",
            "Fecha Gestión",
            "Ponderación"
        );

        // Solo el boton "Compromiso de pago" agrega estas dos columnas; el resto
        // de descargas que comparten esta rama (Cartera Gestionada, Contacto
        // Directo/Indirecto, etc.) quedan igual que antes.
        if ($tipoCartera === 'COMPROMISO PAGO') {
            $cabeceras[] = "Fecha Compromiso";
            $cabeceras[] = "Monto Compromiso";
        }
    }

    $filasExcel = array();
    $i = 0;

    $filasExcel["titulo_" . $i] = $cabeceras;
    $i++;

    // FILAS
    foreach ($data as $fila) {

        if (!$usaGestiones) {

            if ($tipoCartera === 'CARTERA ASIGNADA') {

                // ASIGNACIONES - CARTERA ASIGNADA (capital inicial/actual + detalle por canal)
                $nuevaFila = [
                    $fila['cubAG_numFactura'] ?? '',
                    $fila['cubAG_ciclo'] ?? '',
                    $fila['cubAG_capitalInicialPeriodo'] ?? '',
                    $fila['cubAG_capitalActual'] ?? '',
                    $fila['cubAG_deudaNetaActual'] ?? ''
                ];

                // MEJOR GESTIÓN POR CANAL (AV, EMAIL, WHATSAPP)
                $mejorGestion = $fila['cubAG_mejorGestion'] ?? [];

                foreach (['AV', 'EMAIL', 'WHATSAPP'] as $canalMejorGestion) {

                    $mg = $mejorGestion[$canalMejorGestion] ?? [];
                    // Sin gestión real en ese canal (cuGestionId vacío) => ponderación en blanco, no "0"
                    $tieneGestionCanal = !empty($mg['cuGestionId'] ?? '');
                    $fechaMg = $mg['fechaGestion'] ?? 0;

                    $nuevaFila[] = $mg['tipificacion1'] ?? '';
                    $nuevaFila[] = $mg['tipificacion2'] ?? '';
                    $nuevaFila[] = ($fechaMg ? date('Y-m-d H:i:s', $fechaMg) : '');
                    $nuevaFila[] = $tieneGestionCanal ? ($mg['ponderacion'] ?? '') : '';
                }

                // MEJOR GESTIÓN (la de mayor ponderación entre los 3 canales)
                $fechaGeneral = $fila['cubAG_fechaGestion'] ?? 0;
                // Sin gestión general => ponderación en blanco, no "0"
                $tieneGestionGeneral = (($fila['cubAG_gestionada'] ?? 0) == 1);

                $nuevaFila[] = $fila['cubAG_canal'] ?? '';
                $nuevaFila[] = $fila['cubAG_tipificacion1'] ?? '';
                $nuevaFila[] = $fila['cubAG_tipificacion2'] ?? '';
                $nuevaFila[] = ($fechaGeneral ? date('Y-m-d H:i:s', $fechaGeneral) : '');
                $nuevaFila[] = $tieneGestionGeneral ? ($fila['cubAG_ponderacion'] ?? '') : '';
            } else if ($tipoCartera === 'CARTERA NO GESTIONADA PAGADA') {

                // Nunca tienen gestión: sin canal ni tipificaciones, pero sí pago total
                $nuevaFila = [
                    $fila['cubAG_numFactura'] ?? '',
                    $fila['cubAG_ciclo'] ?? '',
                    $fila['cubAG_capitalActual'] ?? '',
                    $fila['cubAG_deudaNetaActual'] ?? '',
                    $fila['cubAG_montoTotalPago'] ?? ''
                ];
            } else {

                // CARTERA NO GESTIONADA: nunca van a tener campos de gestión
                $nuevaFila = [
                    $fila['cubAG_numFactura'] ?? '',
                    $fila['cubAG_ciclo'] ?? '',
                    $fila['cubAG_capitalActual'] ?? '',
                    $fila['cubAG_deudaNetaActual'] ?? ''
                ];
            }
        } else {

            // GESTIONES
            $nuevaFila = [
                $fila['cubGC_numFactura'] ?? '',
                $fila['cubGC_ciclo'] ?? '',
                $fila['cubGC_capitalActual'] ?? '',
                // Nota: se asume que el cubo de gestiones también trae este campo
                // (mismo patrón que cubGC_capitalActual); si el nombre real es otro, avisar.
                $fila['cubGC_deudaNetaActual'] ?? '',
                $fila['cubGC_canal'] ?? '',
                $fila['cubGC_campaniaNombre'] ?? '',
                $fila['cubGC_tipificacion_respuesta1'] ?? '',
                $fila['cubGC_tipificacion_respuesta2'] ?? '',
                isset($fila['cubGC_fechaGestion'])
                    ? date('Y-m-d H:i:s', $fila['cubGC_fechaGestion'])
                    : '',
                // Nota: se asume el mismo patrón de nombre que los demás campos del cubo
                // de gestiones; si el nombre real es distinto, avisar.
                $fila['cubGC_ponderacion'] ?? ''
            ];

            // Solo para "Compromiso de pago": estas filas ya vienen del cubo de
            // gestiones (cubGC_*), no del de asignaciones, asi que se lee de ahi.
            if ($tipoCartera === 'COMPROMISO PAGO') {
                $nuevaFila[] = $fila['cubGC_tipificacion_compromiso'] ?? '';
                $nuevaFila[] = $fila['cubGC_tipificacion_montoCompromiso'] ?? '';
            }
        }

        $filasExcel["fila_" . $i] = $nuevaFila;
        $i++;
    }

    //ESTILOS
    foreach ($filasExcel as $key => $fila) {

        $partes = explode("_", $key);
        $style = $partes[0];

        switch ($style) {

            case "titulo":
                $font = $objExcel->setFilaEstiloFuente("Calibri", "12", true, "#ffffff");
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
    $rutaRelativa = "/canalesMasivos/reportesExcel/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();

    return $rutaRelativa;
}


function obtenerGestionesPorPago($condicionCubAsignacion, $operadorExpr, $filtroCanal = 'todo')
{
    $mdb = new MYMONGODB();

    // Gestionada según el canal filtrado (cubAG_mejorGestion.<CANAL>) o general si es "todo".
    $canalesValidosPago = ['AV' => 'TELEFONICA', 'EMAIL' => 'EMAIL', 'WHATSAPP' => 'WHATSAPP'];
    $usaCanalEspecificoPago = isset($canalesValidosPago[$filtroCanal]);
    $exprCanalPago = exprCanalMejorGestion($filtroCanal);

    $condicionCubAsignacion['$expr'] = [
        '$and' => [
            $exprCanalPago['gestionada'],
            [$operadorExpr => ['$cubAG_montoTotalPago', '$cubAG_deudaNetaActual']]
        ]
    ];

    $pipeline = [
        [
            '$match' => $condicionCubAsignacion
        ],
        [
            '$lookup' => [
                'from' => 'cuGestionCobranzaMysql',
                'let' => [
                    'factura' => '$cubAG_numFactura',
                    'cartera' => '$cubAG_carteraId',
                    'periodo' => '$cubAG_ciclo',
                    'fechaPeriodo' => '$cubAG_fechaPeriodo'
                ],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$eq' => ['$cubGC_numFactura', '$$factura']],
                                    ['$eq' => ['$cubGC_carteraId', '$$cartera']],
                                    ['$eq' => ['$cubGC_ciclo', '$$periodo']],
                                    ['$eq' => ['$cubGC_fechaPeriodo', '$$fechaPeriodo']]
                                ]
                            ]
                        ]
                    ],
                    [
                        '$match' => [
                            'cubGC_tipificacion_respuesta2' => ['$nin' => tipificacionesExcluidasGestion()]
                        ]
                    ]
                ],
                'as' => 'gestiones'
            ]
        ],
        [
            '$group' => [
                '_id' => null,

                'totalPagos' => ['$sum' => 1],

                'totalGestionesPagadas' => [
                    '$sum' => ['$size' => '$gestiones']
                ],

                'totalTelefonicaPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'TELEFONICA']]
                            ]
                        ]
                    ]
                ],

                'totalWhatsappPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'WHATSAPP']]
                            ]
                        ]
                    ]
                ],

                'totalEmailPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'EMAIL']]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    $mdb->aggregate("cuAsignacionesGestionAP", $pipeline);

    $resultado = [
        "totalPagos" => 0,
        "totalGestiones" => 0,
        "telefonica" => 0,
        "whatsapp" => 0,
        "email" => 0
    ];

    if ($row = $mdb->siguiente()) {
        $resultado["totalPagos"] = (int)$row['totalPagos'];
        $resultado["telefonica"] = (int)$row['totalTelefonicaPagada'];
        $resultado["whatsapp"] = (int)$row['totalWhatsappPagada'];
        $resultado["email"] = (int)$row['totalEmailPagada'];

        if ($usaCanalEspecificoPago) {
            // El total de gestiones queda acotado a las del canal filtrado
            $mapaResultadoCanalPago = ['TELEFONICA' => 'telefonica', 'WHATSAPP' => 'whatsapp', 'EMAIL' => 'email'];
            $claveCanalPago = $mapaResultadoCanalPago[$canalesValidosPago[$filtroCanal]];
            $resultado["totalGestiones"] = $resultado[$claveCanalPago];
        } else {
            $resultado["totalGestiones"] = (int)$row['totalGestionesPagadas'];
        }
    }

    return $resultado;
}




function existeFechaRango($fechas, $desde, $hasta)
{
    if (!is_array($fechas) || empty($fechas)) return false;

    foreach ($fechas as $f) {
        if ($f >= $desde && $f <= $hasta) {
            return true;
        }
    }
    return false;
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