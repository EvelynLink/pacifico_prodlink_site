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
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroPeriodo = intval(expect_safe_html($d["filtroPeriodo"]));
        //$filtroPeriodo = expect_safe_html($d["filtroPeriodo"]);
        $condicion = establecerCondicionMaster();
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $campo = "av_fecha";
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
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }

        $condicionPeriodo = [];
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
            if ($filtroPeriodo >= 0) {

                $condicion["av_periodo"] = (int)$filtroPeriodo;
                $condicionWhatsapp["ws_periodo"] = (int)$filtroPeriodo;
            }

            $condicionCorreo["cem_susCarteraId"] = intval($filtroCartera);
            $condicionWhatsapp["ws_carteraId"] = intval($filtroCartera);

            // $condicionPeriodo = ['activo' => (int) 1, "cartera" => intval($filtroCartera)];
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
            $condicionCorreo["cem_susCampaniaId"] = intval($filtroCampania);
            $condicionWhatsapp["ws_campaniaId"] = intval($filtroCampania);
        }
        $mongo = new MYMONGODB();
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
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroCanales = expect_safe_html($d["filtroCanales"]);
        $filtroCanal = expect_safe_html($d["filtroCanal"]);
        $filtroPeriodo = expect_safe_html($d["filtroPeriodo"]);
        $condicion = establecerCondicionMaster();
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");
        $totalesWp = [];
        $totalesCorreos = [];
        $condicionPagos = establecerCondicionMaster("pagos_carteraId");
        $efectividadCapital = [];
        $json['efectividad'] = [];
        $campo = "av_fecha";
        $campoCuboGestion = "cubGC_fechaGestion";
        $fechaIniPeriodo =  0;
        $fechaFinPeriodo = 0;

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
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
                        $fechaIniPeriodo =  $ro['fecha'];
                        $fechaFinPeriodo = $ro['fechaFin'];
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
                        $fechaIniPeriodo =  $ro['fecha'];
                        $fechaFinPeriodo = $ro['fechaFin'];
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

            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubAsignacion["cubAG_fechaInicio"] = intval($fechaIniPeriodo);

            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];



            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionWhatsapp["ws_fecha"] = 1756843285;
            $condicionPagos["pagos_proceso"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);

            $condicion["av_carteraId"] = intval($filtroCartera);
            if ($filtroPeriodo >= 0) {
                $condicionCubAsignacion["cubAG_ciclo"] = intval($filtroPeriodo);
                $condicionCubGestion["cubGC_ciclo"] = intval($filtroPeriodo);

                $condicion["av_periodo"] = intval($filtroPeriodo);
                $condicionWhatsapp["ws_periodo"] = intval($filtroPeriodo);
                $condicionPagos["pagos_periodo"] = intval($filtroPeriodo);
            }
            $condicionCorreo["cem_susCarteraId"] = intval($filtroCartera);
            $condicionWhatsapp["ws_carteraId"] = intval($filtroCartera);
            $condicionPagos["pagos_carteraId"] = strval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
            $condicionCorreo["cem_susCampaniaId"] = intval($filtroCampania);
            $condicionWhatsapp["ws_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroCanal == 'llamada' || $filtroCanal == 'todo') {
            $totalesLlamadas = obtenerTotalesLlamadasAV($condicion);
        } else {
            $totalesLlamadas = obtenerTotalesLlamadasAV([]);
        }
        $json["llamadasAV"] = [
            "totales" => $totalesLlamadas["cuadros"],
            "porcentajeAvance" => $totalesLlamadas["porcentajeAvance"],
            "campanias" => $totalesLlamadas["campanias"]
        ];
        if ($filtroCanal == 'mail' || $filtroCanal == 'todo') {
            $totalesCorreos = obtenerTotalesCorreo($condicionCorreo);
            $json["correo"] = [
                "totales" => $totalesCorreos["cuadros"],
                "porcentajeAvance" => $totalesCorreos["porcentajeAvance"],
                "campanias" => $totalesCorreos["campanias"]
            ];
        }
        if ($filtroCanal == 'wp' || $filtroCanal == 'todo') {
            $totalesWp = obtenerTotalesWhatsapp($condicionWhatsapp);
            $json["whatsapp"] = [
                "totales" => $totalesWp["cuadros"],
                "porcentajeAvance" => $totalesWp["porcentajeAvance"],
                "campanias" => $totalesWp["campanias"]
            ];
        }
        $pagos = obtenerPagos($condicionPagos);

        $parametrosTotales = [];
        $parametrosTotalesIntensidades = [];
        $contado = 0;
        $t = 0;
        $totalTotalIntensidad = 0;
        foreach ($filtroCanales as $value) {
            if ($value["id"] == "llamadas_av" && $value["checked"] && $totalesLlamadas["totalIntensidad"] > 0) {
                $parametrosTotales["llamadas_av"] = $totalesLlamadas["gestionadas"];
                $parametrosTotalesIntensidades["llamadas_av"] = $totalesLlamadas["totalIntensidad"];
                $totalTotalIntensidad += $totalesLlamadas["totalIntensidad"];
                $contado++;
                $t += $totalesLlamadas["porcentajeAvance"];
            }
            if ($value["id"] == "correo" && $value["checked"] && $totalesCorreos["totalIntensidad"] > 0) {
                $parametrosTotales["correo"] = $totalesCorreos["gestionadas"];
                $parametrosTotalesIntensidades["correo"] = $totalesCorreos["totalIntensidad"];
                $totalTotalIntensidad += $totalesCorreos["totalIntensidad"];
                $contado++;
                $t += $totalesCorreos["porcentajeAvance"];
            }
            if ($value["id"] == "whatsapp" && $value["checked"] && $totalesWp["totalIntensidad"] > 0) {
                $parametrosTotales["whatsapp"] = $totalesWp["gestionadas"];
                $parametrosTotalesIntensidades["whatsapp"] = $totalesWp["totalIntensidad"];
                $totalTotalIntensidad += $totalesWp["totalIntensidad"];
                $contado++;
                $t += $totalesWp["porcentajeAvance"];
            }
            $parametrosTotalesIntensidades["todo"] = $totalTotalIntensidad;
        }

        $totales = obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $parametrosTotalesIntensidades);
        $json["totales"] = $totales;
        $pt = $contado > 0 ? $t / $contado : 0;
        $json["porcentajeTotal"] = round($pt, 1);
        $totalAv = 0;
        $avPorCampania = [];
        foreach ($totalesLlamadas["gestionadasPorCampania"] as $campania => $total) {
            foreach ($total as $t) {
                $totalAv += $t;
            }
        }
        $totalGestionado = $totalAv + $totalesCorreos["cuadros"]["numeroEnviados"]["total"] + $totalesWp["cuadros"]["numeroRecibidos"]["total"];
        if ($totalGestionado > 0) {
            $graficoPie = [];
            $graficos = [
                "key" => "Totales",
                "values" => []
            ];
            foreach ($totalesLlamadas["gestionadasPorCampania"] as $campania => $total) {
                $tt = 0;
                foreach ($total as $t) {
                    $tt += $t;
                }
                $graficos["values"][] = [
                    "label" => $campania,
                    "value" => $tt,
                    "color" => $paletaColor[3],
                    "porcentaje" => formatea_numero(($tt * 100 / $totalGestionado), 2, ",", "."),
                ];
                $graficoPie[] = [
                    "key" => "Agente virtual " . $campania,
                    "y" => $tt,
                    "color" => $paletaColor[3],
                ];
            }
            foreach ($totalesCorreos["gestionadasPorCampania"] as $campania => $total) {
                $graficos["values"][] = [
                    "label" => $campania,
                    "value" => $total,
                    "color" => $paletaColor[4],
                    "porcentaje" => formatea_numero(($total * 100 / $totalGestionado), 2, ",", "."),
                ];
                $graficoPie[] = [
                    "key" => "Correo electrónico " . $campania,
                    "y" => $total,
                    "color" => $paletaColor[4],
                ];
            }
            foreach ($totalesWp["gestionadasPorCampania"] as $campania => $total) {
                $graficos["values"][] = [
                    "label" => $campania,
                    "value" => $total,
                    "color" => $paletaColor[5],
                    "porcentaje" => formatea_numero(($total * 100 / $totalGestionado), 2, ",", "."),
                ];
                $graficoPie[] = [
                    "key" => "WhatsApp " . $campania,
                    "y" => $total,
                    "color" => $paletaColor[5],
                ];
            }
            $json["graficos"]["barrasTotales"] = [
                $graficos
            ];
            $json["graficos"]["pie"] = $graficoPie;
        }
        #endregion
        break;
    case "graficoLineaLlamadas":
        #region graficoLineaLlamadas 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $horaDesde = explode(":", $horaDesde)[0];

        $condicion = establecerCondicionMaster("cartera");
        $desde = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
        $horaHoy = date("H");
        $hasta = strtotime("+5 minutes");

        $factor = 0;
        $factor1 = 3600;
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $factor = 1;
                    // $desde = strtotime("-1 hour");
                    // $hasta =  strtotime("+5 minutes");
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
                    break;
                case 'dia':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d") . " 00:00:00");
                    $hasta = strtotime("+5 minutes");
                    break;
                case 'ayer':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                    break;
                case 'semana':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                    break;
                case 'semanaanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                    break;
                case 'mes':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'mesanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'asigActual':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 1];
                    } else {
                        $query = ['activo' => (int) 1];
                    }
                    $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                    $ro = $mongo->siguiente();
                    // $desde = strtotime(date("Y-m-d", $ro['fecha']) . " 00:00:00");
                    $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                    } else {
                        $query = ['activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                    }
                    $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                    $ro = $mongo->siguiente();
                    $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);

                    if ($filtroDesde - $filtroHasta < 86400) {
                        $factor = 60;
                    } else {
                        $factor = 86400;
                        $factor1 = 86400;
                    }

                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
        }

        $condicion["fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["cartera"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["campania"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avLlamadasMarcadas", $condicion, [], ["fecha" => 1]);
        $respuesta = [];
        $primera = "";
        $ultima = "";
        $totalGestiones = 0;
        $ticks = 0;
        if ($cursor > 0) {
            $dataLlamadas = [];
            $dataLlamadasContestadas = [];
            $dataLlamadasNoContestadas = [];
            while ($row = $mongo->siguientex()) {
                $primera = $primera == "" ? $row["fecha"] : $primera;
                $ultima = $row["fecha"];
                $h = date("Y-m-d H:i", $row["fecha"]);
                $hb = date("Y-m-d H:" . "00", $row["fecha"]);
                switch ($filtroTiempo) {
                    case 'hora':
                        $h = date("Y-m-d H:i", $row["fecha"]);
                        break;
                    case 'dia':
                        $h = date("Y-m-d H:i", $row["fecha"]);
                        break;
                    case 'ayer':
                        $h = date("Y-m-d H:i", $row["fecha"]);
                        break;
                    case 'semana':
                        $h = date("Y-m-d", $row["fecha"]);
                        $hb = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'semanaanterior':
                        $h = date("Y-m-d", $row["fecha"]);
                        $hb = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'mes':
                        $h = date("Y-m-d", $row["fecha"]);
                        $hb = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'mesanterior':
                        $h = date("Y-m-d", $row["fecha"]);
                        $hb = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'rango':
                        if ($hasta - $desde < 86400) {
                            $h = date("Y-m-d H:i", $row["fecha"]);
                        } else {
                            $h = date("Y-m-d", $row["fecha"]);
                            $hb = date("Y-m-d", $row["fecha"]);
                        }
                        break;
                }
                $sinHora = strtotime($h);
                $sinHoraB = strtotime($hb);
                $dataLlamadas[$sinHora] = isset($dataLlamadas[$sinHora]) ? $dataLlamadas[$sinHora] + 1 : 1;
                if ($row["exito"]) {
                    $dataLlamadasContestadas[$sinHora] = isset($dataLlamadasContestadas[$sinHora]) ? $dataLlamadasContestadas[$sinHora] + 1 : 1;
                } else {
                    $dataLlamadasNoContestadas[$sinHora] = isset($dataLlamadasNoContestadas[$sinHora]) ? $dataLlamadasNoContestadas[$sinHora] + 1 : 1;
                }
            }

            $dataLlamadasFinal = [];
            $totalPorHora = [];
            $totalPorHoraNo = [];

            if ($factor == 60) {
                $primera = strtotime("-1 hour", $primera);
                $desde = strtotime(date("Y-m-d", $desde) . " " . date("H", $primera) . ":00:00"); //strtotime("-1 hora", $primera);
                $hasta = strtotime(date("Y-m-d", $hasta) . " " . date("H", $ultima) . ":59:59");
            } else {
                $desde = strtotime(date("Y-m-d", ($desde - $factor)) . " " . "00:00:00"); //strtotime("-1 hora", $primera);                
            }

            for ($i = $desde; $i <= $hasta; $i += $factor) {
                $dataLlamadasFinal[0][] = ["x" => $i, "y" => $dataLlamadas[$i] ?? 0, "series" => 0, "seriesIndex" => 0];
                $dataLlamadasFinal[1][] = ["x" => $i, "y" => $dataLlamadasContestadas[$i] ?? 0, "series" => 1, "seriesIndex" => 1];
                $totalGestiones += isset($dataLlamadas[$i]) ? $dataLlamadas[$i] : 0;
                $dataLlamadasFinal[2][] = ["x" => $i, "y" => $dataLlamadasNoContestadas[$i] ?? 0, "series" => 2, "seriesIndex" => 2];
                $ticks++;
            }

            if (count($dataLlamadasFinal) > 0) {
                $respuesta[] = [
                    "values" => $dataLlamadasFinal[0],
                    "key" => "Llamadas marcadas",
                    "color" => $paletaColor[1]
                ];
                $respuesta[] = [
                    "values" => $dataLlamadasFinal[1],
                    "key" => "Llamadas atendidas",
                    "color" => $paletaColor[2]
                ];
                $respuesta[] = [
                    "values" => $dataLlamadasFinal[2],
                    "key" => "Llamadas no atendidas",
                    "color" => $paletaColor[0]
                ];
            }
        }

        $json["respuesta"] = $respuesta;
        $promedio = $ticks > 0 ? formatea_numero(($totalGestiones / $ticks), 0, ",", "") : 0;
        $json["promedio"] = "Promedio de " . $promedio . " llamadas por " . ($factor == 60 ? "minuto" : "día");
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

            // SWITCH
            switch ($tipoCartera) {

                // SIN GESTIONES
                case 'CARTERA ASIGNADA':
                    $usaGestiones = false;
                    break;

                case 'CARTERA NO GESTIONADA':
                    $usaGestiones = false;
                    $condicion['cubAG_gestionada'] = 0;
                    break;

                case 'CARTERA NO GESTIONADA PAGADA':
                    $usaGestiones = false;
                    $condicion['cubAG_gestionada'] = 0;
                    $condicion['cubAG_montoTotalPago'] = ['$gt' => 0];
                    break;

                // CON GESTIONES
                case 'CARTERA GESTIONADA':
                    $condicion['cubAG_gestionada'] = 1;
                    break;

                case 'CARTERA GESTIONADA NO CONTACTADA':
                    $condicion['cubAG_gestionada'] = 1;
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'SIN CONTACTO';
                    break;

                case 'CARTERA GESTIONADA CONTACTADA':
                    $condicion['cubAG_gestionada'] = 1;
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = [
                        '$in' => ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']
                    ];
                    break;

                case 'CONTACTO DIRECTO':
                    $condicion['cubAG_gestionada'] = 1;
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO DIRECTO';
                    break;

                case 'CONTACTO INDIRECTO':
                    $condicion['cubAG_gestionada'] = 1;
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO INDIRECTO';
                    break;

                case 'CARTERA GESTIONADA PAGADA':
                    $condicion['cubAG_gestionada'] = 1;
                    $condicion['cubAG_montoTotalPago'] = ['$gt' => 0];
                    break;

                case 'COMPROMISO PAGO':
                    $condicion['cubAG_gestionada'] = 1;
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta1'] = 'CONTACTO DIRECTO';
                    $matchGestion['gestiones.cubGC_tipificacion_respuesta2'] = [
                        '$in' => [
                            'PAGA EN FECHA',
                            'ABONO CUOTA',
                            'CONFIRMACION DE PAGO',
                            'NEGOCIACION EN CURSO ALIVIO'
                        ]
                    ];
                    break;
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

                                //FILTRO DE FECHA 
                                [
                                    '$match' => [
                                        'cubGC_fechaGestion' => [
                                            '$gte' => $desde,
                                            '$lte' => $hasta
                                        ]
                                    ]
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

        $ruta = generarExcel($dataAsig, $usaGestiones, $nombreArchivo);

        $json["ruta"] = $ruta;

        trigger_error("archivo ruta: " . $ruta);

        break;
    case "graficoLineaCorreos":
        #region graficoLineaCorreos 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $horaDesde = explode(":", $horaDesde)[0];

        $condicion = establecerCondicionMaster("cem_susCarteraId");
        $desde = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
        $horaHoy = date("H");
        $hasta = strtotime("+5 minutes");

        $factor = 0;
        $factor1 = 3600;
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $factor = 1;
                    // $desde = strtotime("-1 hour");
                    // $hasta =  strtotime("+5 minutes");
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
                    break;
                case 'dia':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d") . " 00:00:00");
                    $hasta = strtotime("+5 minutes");
                    break;
                case 'ayer':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                    break;
                case 'semana':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                    break;
                case 'semanaanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                    break;
                case 'mes':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'mesanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'asigActual':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 1];
                    } else {
                        $query = ['activo' => (int) 1];
                    }
                    $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                    $ro = $mongo->siguiente();
                    // $desde = strtotime(date("Y-m-d", $ro['fecha']) . " 00:00:00");
                    $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    $query = establecerCondicionMaster("cartera");
                    if ($filtroCartera != "" && $filtroCartera != "todo") {
                        $query = ['cartera' => (int) $filtroCartera, 'activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                    } else {
                        $query = ['activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                    }
                    $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                    $ro = $mongo->siguiente();
                    $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);

                    if ($filtroDesde - $filtroHasta < 86400) {
                        $factor = 60;
                    } else {
                        $factor = 86400;
                        $factor1 = 86400;
                    }

                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
        }

        $condicion["cem_susFechaEnvio"] = ['$gte' => $desde, '$lte' => $hasta];
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["cem_susCarteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["cem_susCampaniaId"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("cbEnvioMails", $condicion, [], ["cem_susFechaEnvio" => 1]);
        $respuesta = [];
        $primera = "";
        $ultima = "";
        $totalGestiones = 0;
        $ticks = 0;
        if ($cursor > 0) {
            $dataCorreos = [];
            while ($row = $mongo->siguientex()) {
                $primera = $primera == "" ? $row["cem_susFechaEnvio"] : $primera;
                $ultima = $row["cem_susFechaEnvio"];
                $h = date("Y-m-d H:i", $row["cem_susFechaEnvio"]);
                switch ($filtroTiempo) {
                    case 'hora':
                        $h = date("Y-m-d H:i", $row["cem_susFechaEnvio"]);
                        break;
                    case 'dia':
                        $h = date("Y-m-d H:i", $row["cem_susFechaEnvio"]);
                        break;
                    case 'ayer':
                        $h = date("Y-m-d H:i", $row["cem_susFechaEnvio"]);
                        break;
                    case 'semana':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'semanaanterior':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'mes':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'mesanterior':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'asigActual':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'asigAnterior':
                        $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        break;
                    case 'rango':
                        if ($hasta - $desde < 86400) {
                            $h = date("Y-m-d H:i", $row["cem_susFechaEnvio"]);
                        } else {
                            $h = date("Y-m-d", $row["cem_susFechaEnvio"]);
                        }
                        break;
                }
                $sinHora = strtotime($h);
                $dataCorreos[$sinHora] = isset($dataCorreos[$sinHora]) ? $dataCorreos[$sinHora] + 1 : 1;
            }

            $dataCorreoFinal = [];

            if ($factor == 60) {
                $desde = strtotime(date("Y-m-d", $desde) . " " . date("H", $primera) . ":00:00"); //strtotime("-1 hora", $primera);
                $hasta = strtotime(date("Y-m-d", $hasta) . " " . date("H", $ultima) . ":59:59");
            } else {
                $desde = strtotime(date("Y-m-d", ($desde - $factor)) . " " . "00:00:00"); //strtotime("-1 hora", $primera);                
            }

            //por hora
            for ($i = $desde; $i <= $hasta; $i += $factor) {
                $dataCorreoFinal[0][] = ["x" => $i, "y" => $dataCorreos[$i] ?? 0, "series" => 0, "seriesIndex" => 0];
                $totalGestiones += isset($dataCorreos[$i]) ? $dataCorreos[$i] : 0;
                $ticks++;
            }

            if (count($dataCorreoFinal) > 0) {
                $respuesta[] = [
                    "values" => $dataCorreoFinal[0],
                    "key" => "Correos enviados",
                    "color" => $paletaColor[2]
                ];
            }
        }

        $json["respuesta"] = $respuesta;
        $promedio = $ticks > 0 ? formatea_numero(($totalGestiones / $ticks), 0, ",", "") : 0;
        $json["promedio"] = "Promedio de " . $promedio . " correos enviados por " . ($factor == 60 ? "minuto" : "día");
        #endregion 
        break;
    case "graficoLineaWhatsapp":
        #region graficoLineaWhatsapp 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $horaDesde = explode(":", $horaDesde)[0];

        $condicion = establecerCondicionMaster("ws_carteraId");
        $desde = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
        $horaHoy = date("H");
        $hasta = strtotime("+5 minutes");

        $factor = 0;
        $factor1 = 3600;
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $factor = 1;
                    // $desde = strtotime("-1 hour");
                    // $hasta =  strtotime("+5 minutes");
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
                    break;
                case 'dia':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d") . " 00:00:00");
                    $hasta = strtotime("+5 minutes");
                    break;
                case 'ayer':
                    $factor = 60;
                    $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                    break;
                case 'semana':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                    break;
                case 'semanaanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                    break;
                case 'mes':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'mesanterior':
                    $factor = 86400;
                    $factor1 = 86400;
                    $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);

                    if ($filtroDesde - $filtroHasta < 86400) {
                        $factor = 60;
                    } else {
                        $factor = 86400;
                        $factor1 = 86400;
                    }

                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
        }

        $condicion["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["ws_campaniaId"] = intval($filtroCampania);
        }
        $condicion["ws_estadoEnvio"] = ['$in' => ["ENVIADO", "CONTESTADO"]];

        $mongo = new MYMONGODB();
        $mongo1 = new MYMONGODB();

        $cursor = $mongo->buscar("avProgramadasWhatsApp", $condicion);
        $respuesta = [];
        $primera = "";
        $ultima = "";
        $dataWp = [];
        $totalGestiones = 0;
        $ticks = 0;
        if ($cursor > 0) {
            while ($row = $mongo->siguientex()) {
                $resp = $mongo1->buscar("whatsapp_logs", ["wp_destinatario" => $row["ws_telefono"] . "@c.us", "wp_ack" => "enviado"], [], ["_id" => -1], 1);
                if ($resp > 0) {
                    $r = $mongo1->siguientex();
                    $fechaEnvio = $r["wp_date"];

                    $primera = $primera == "" ? $fechaEnvio : ($primera < $fechaEnvio ? $primera : $fechaEnvio);
                    $ultima = $fechaEnvio;
                    $h = date("Y-m-d H:i", $fechaEnvio);
                    switch ($filtroTiempo) {
                        case 'hora':
                            $h = date("Y-m-d H:i", $fechaEnvio);
                            break;
                        case 'dia':
                            $h = date("Y-m-d H:i", $fechaEnvio);
                            break;
                        case 'ayer':
                            $h = date("Y-m-d H:i", $fechaEnvio);
                            break;
                        case 'semana':
                            $h = date("Y-m-d", $fechaEnvio);
                            break;
                        case 'semanaanterior':
                            $h = date("Y-m-d", $fechaEnvio);
                            break;
                        case 'mes':
                            $h = date("Y-m-d", $fechaEnvio);
                            break;
                        case 'mesanterior':
                            $h = date("Y-m-d", $fechaEnvio);
                            break;
                        case 'rango':
                            if ($hasta - $desde < 86400) {
                                $h = date("Y-m-d H:i", $fechaEnvio);
                            } else {
                                $h = date("Y-m-d", $fechaEnvio);
                            }
                            break;
                    }
                    $sinHora = strtotime($h);
                    $dataWp[$sinHora] = isset($dataWp[$sinHora]) ? $dataWp[$sinHora] + 1 : 1;
                }
            }

            $dataWpFinal = [];

            if ($factor == 60) {

                $desde = strtotime(date("Y-m-d", $desde) . " " . date("H", ($primera == "" ? $desde : $primera)) . ":00:00");
                $hasta = strtotime(date("Y-m-d", $hasta) . " " . date("H", ($ultima == "" ? $hasta : $ultima)) . ":59:59");
            } else {
                $desde = strtotime(date("Y-m-d", ($desde - $factor)) . " " . "00:00:00"); //strtotime("-1 hora", $primera);                
            }

            //por hora
            for ($i = $desde; $i <= $hasta; $i += $factor) {
                $dataWpFinal[0][] = ["x" => $i, "y" => $dataWp[$i] ?? 0, "series" => 0, "seriesIndex" => 0];
                $totalGestiones += isset($dataWp[$i]) ? $dataWp[$i] : 0;
                $ticks++;
            }

            if (count($dataWpFinal) > 0) {
                $respuesta[] = [
                    "values" => $dataWpFinal[0],
                    "key" => "WhatsApp enviados",
                    "color" => $paletaColor[2]
                ];
            }
        }

        $json["respuesta"] = $respuesta;
        $promedio = $ticks > 0 ? formatea_numero(($totalGestiones / $ticks), 0, ",", "") : 0;
        $json["promedio"] = "Promedio de " . $promedio . " mensajes enviados por " . ($factor == 60 ? "minuto" : "día");
        #endregion 
        break;
    case "graficoCompromisos":
        #region graficoCompromisos 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicion = establecerCondicionMaster();
        $condicionPagos = establecerCondicionMaster("pagos_carteraId");
        $campo = "av_fecha";
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            //$desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionPagos["pagos_proceso"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
            $condicionPagos["pagos_carteraId"] = strval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $pagos = $mongo->buscar("cbPagos", $condicionPagos);
        $losPagos = [];
        while ($pago = $mongo->siguientex()) {
            if ($pago["pagos_numFactura"] != "") {
                //$lf = date("Y-m-d", $pago["pagos_proceso"]);
                if (isset($pago["pagos_proceso"]) && $pago["pagos_proceso"] > 0) {
                    if ($pago["pagos_proceso"] < $hasta) {
                        $lf = strtotime(date("Y-m-d", $pago["pagos_proceso"]) . " 00:00:00");
                        $losPagos[$pago["pagos_numFactura"]][$lf] = isset($losPagos[$pago["pagos_numFactura"]][$lf]) ? $losPagos[$pago["pagos_numFactura"]][$lf] += $pago["pagos_monto"] : $pago["pagos_monto"];
                    }
                }
            }
        }

        $compromisosRealizadosEl = [];
        $compromisosParaEl = [];
        $compromisosRealizadosPagados = [];
        $compromisosRealizadosPagadosMismoDia = [];


        $f = 0;
        $c = $mongo->buscar("avProgramadas", $condicion, [], ['av_fechaGeneraLlamada' => 1]);
        if ($c > 0) {
            while ($campos = $mongo->siguientex()) {
                if (
                    isset($campos["av_tipificacion"]["respuesta2"]) && $campos["av_tipificacion"]["respuesta2"] == "PAGA EN FECHA"
                    && $campos["av_tipificacion"]["respuesta3"] != ""
                ) {
                    //if (isset($campos["av_tipificacion"]) && $campos["av_tipificacion"]["respuesta3"] != "") {
                    $f++;
                    $partes = explode("|", $campos["av_tipificacion"]["respuesta3"]);
                    if (isset($partes[1]) && $partes[1] != "") {
                        if (
                            $partes[1] != "TOMORROW" &
                            $partes[1] != "NEXT MONTH" &
                            $partes[1] != "VIERNES" &
                            $partes[1] != "MONDAY" &
                            $partes[1] != "NEXT FRIDAY" &
                            $partes[1] != "FRIDAY" &
                            $partes[1] != "SATURDAY" &
                            $partes[1] != "TUESDAY" &
                            $partes[1] != "WEDNESDAY" &
                            $partes[1] != "NEXT MONDAY" &
                            $partes[1] != "THURSDAY" &
                            $partes[1] != "NEXT WEEK"

                        ) {
                            $x = strtotime($partes[1]);
                            if ($partes[1] == "TODAY") {
                                $x = time();
                            }
                            if ($x > 0) {
                                $d = strtotime(date("Y-m-d", $x) . " 00:00:00");
                                //$d = date("Y-m-d", $x);
                                if ($d !== false) {
                                    $a = date("Ym", $x);
                                    $h = date("Ym");
                                    if ($a >= $h) {
                                        $compromisosParaEl[$d] = isset($compromisosParaEl[$d]) ? $compromisosParaEl[$d] + 1 : 1;
                                        $fll = strtotime(date("Y-m-d", $campos["av_fechaGeneraLlamada"]) . " 00:00:00");
                                        //$fll = date("Y-m-d", $campos["av_fechaGeneraLlamada"]);
                                        $compromisosRealizadosEl[$fll] = isset($compromisosRealizadosEl[$fll]) ? $compromisosRealizadosEl[$fll] + 1 : 1;
                                        if (isset($losPagos[$campos["av_factura"]][$d])) {
                                            $compromisosRealizadosPagadosMismoDia[$d] = isset($compromisosRealizadosPagadosMismoDia[$d]) ? $compromisosRealizadosPagadosMismoDia[$d] + 1 : 1;
                                        } else {
                                            $compromisosRealizadosPagadosMismoDia[$d] = isset($compromisosRealizadosPagadosMismoDia[$d]) ? $compromisosRealizadosPagadosMismoDia[$d] : 0;
                                        }

                                        //solo hasta hoy para que no salga pagos a futuro
                                        if ($d <= time()) {
                                            if (isset($losPagos[$campos["av_factura"]])) {
                                                $compromisosRealizadosPagados[$d] = isset($compromisosRealizadosPagados[$d]) ? $compromisosRealizadosPagados[$d] + 1 : 1;
                                            } else {
                                                $compromisosRealizadosPagados[$d] = isset($compromisosRealizadosPagados[$d]) ? $compromisosRealizadosPagados[$d] : 0;
                                            }
                                        } else {
                                            $compromisosRealizadosPagados[$d] = 0;
                                        }

                                        if (!isset($compromisosRealizadosEl[$d])) {
                                            $compromisosRealizadosEl[$d] = 0;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        ksort($compromisosParaEl);
        ksort($compromisosRealizadosEl);
        ksort($compromisosRealizadosPagadosMismoDia);
        ksort($compromisosRealizadosPagados);

        $valores1 = [];
        $valores2 = [];
        $valores3 = [];
        $valores4 = [];

        $resumenPorDia = [];
        $ticks = [];

        foreach ($compromisosParaEl as $key => $value) {
            $ticks[] = $key * 1000;
            $valores1[$key] = [
                "x" => $key * 1000,
                "y" => $value
            ];
            $resumenPorDia["_" . ($key * 1000)]["Compromisos proyectados"] = [$value, $paletaColor[1]];
        }
        // foreach ($compromisosRealizadosEl as $key => $value) {
        //     $valores2[$key] = [
        //         "x" => $key * 1000,
        //         "y" => $value
        //     ];            
        // }
        foreach ($compromisosRealizadosPagadosMismoDia as $key => $value) {
            $valores3[$key] = [
                "x" => $key * 1000,
                "y" => $value
            ];
            $resumenPorDia["_" . ($key * 1000)]["Cumplimiento compromisos del día"] = [$value, $paletaColor[2]];
        }
        foreach ($compromisosRealizadosPagados as $key => $value) {
            $valores4[$key] = [
                "x" => $key * 1000,
                "y" => $value
            ];
            $c = $resumenPorDia["_" . ($key * 1000)]["Cumplimiento compromisos del día"][0];
            $r = $value - $c;
            $resumen = "(" . $c . " Cumplimiento compromisos del día + " . $r . " Seguimiento promesa caída)";
            $resumenPorDia["_" . ($key * 1000)]["Seguimiento promesa caída"] = [$value, $paletaColor[3], $resumen];
        }

        $json["ticks"] = $ticks;
        $json["resumen"] = $resumenPorDia;
        $json["respuesta"] = [
            [
                "key" => "Compromisos proyectados",
                "values" => array_values($valores1),
                "color" => $paletaColor[1]
            ],
            // [
            //     "key" => "Realizado el",
            //     "values" => array_values($valores2)
            // ],
            [
                "key" => "Cumplimiento compromisos del día",
                "values" => array_values($valores3),
                "color" => $paletaColor[2]
            ],
            [
                "key" => "Seguimiento promesa caída",
                "values" => array_values($valores4),
                "color" => $paletaColor[3]
            ]
        ];

        break;
    #endregion
    case "descargarGestionGeneral":
        #region descargarGestionGeneral
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicion = establecerCondicionMaster();
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");
        $condicionPagos = establecerCondicionMaster("pagos_carteraId");

        $campo = "av_fecha";
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionWhatsapp["ws_fecha"] = 1756843285;
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
            $condicionCorreo["cem_susCarteraId"] = intval($filtroCartera);
            $condicionWhatsapp["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
            $condicionCorreo["cem_susCampaniaId"] = intval($filtroCampania);
            $condicionWhatsapp["ws_campaniaId"] = intval($filtroCampania);
        }

        $exportar = [];
        //obtener total cartera
        $cedulasEnCarga = obtenerCedulasEnCarga($desde, $hasta, $filtroCartera);
        $mongo = new MYMONGODB();
        //llamadas
        $cursor = $mongo->buscar("avProgramadas", $condicion);
        $llamadas = [];
        if ($cursor > 0) {
            while ($row = $mongo->siguientex()) {
                if (isset($llamadas[$row["av_factura"]])) {
                    $llamadas[$row["av_factura"]]["realizadas"]++;
                } else {
                    $llamadas[$row["av_factura"]] = ["realizadas" => 1, "atendidas" => 0, "no_atendidas" => 0];
                }
                if (isset($row["av_tipificacion"]["respuesta1"])) {
                    if ($row["av_tipificacion"]["respuesta1"] == "CONTACTO DIRECTO") {
                        $llamadas[$row["av_factura"]]["atendidas"]++;
                    } else if ($row["av_tipificacion"]["respuesta1"] == "CONTACTO INDIRECTO") {
                        $llamadas[$row["av_factura"]]["atendidas"]++;
                    } else {
                        $llamadas[$row["av_factura"]]["no_atendidas"]++;
                    }
                } else {
                    $llamadas[$row["av_factura"]]["no_atendidas"]++;
                }
            }
        }
        //correos
        $cursor2 = $mongo->buscar("cbEnvioMails", $condicionCorreo);
        $correos = [];
        if ($cursor2 > 0) {
            while ($row = $mongo->siguientex()) {
                if (!isset($correos[$row["cem_susFactura"]])) {
                    $correos[$row["cem_susFactura"]] = ["enviado" => 1, "recibido" => 0];
                }
                if (isset($row["cem_susFechaEnvio"]) && $row["cem_susFechaEnvio"] > 0) {
                    $correos[$row["cem_susFactura"]]["enviado"]++;
                    $correos[$row["cem_susFactura"]]["recibido"]++;
                }
            }
        }
        //whatsapp
        $cursor3 = $mongo->buscar("avProgramadasWhatsApp", $condicionWhatsapp);
        $whatsapps = [];
        if ($cursor2 > 0) {
            while ($row = $mongo->siguientex()) {
                if (!isset($whatsapps[$row["ws_factura"]])) {
                    $whatsapps[$row["ws_factura"]] = ["enviado" => 0, "recibido" => 0];
                }
                if ($row['ws_estadoEnvio'] == 'CONTESTADO') {
                    $whatsapps[$row["ws_factura"]]["enviado"]++;
                    $whatsapps[$row["ws_factura"]]["recibido"]++;
                } else {
                    if (isset($row["ws_tipificacion"])) {
                        if ($row["ws_tipificacion"]["respuesta2"] == "WHATSAPP RESPONDIDO" || $row["ws_tipificacion"]["respuesta2"] == "WHATSAPP LEIDO") {
                            $whatsapps[$row["ws_factura"]]["enviado"]++;
                            $whatsapps[$row["ws_factura"]]["recibido"]++;
                        }
                        if ($row["ws_tipificacion"]["respuesta2"] == "WHATSAPP NO LEIDO" || $row["ws_tipificacion"]["respuesta2"] == "WHATSAPP NO ENVIADO") {
                            $whatsapps[$row["ws_factura"]]["enviado"]++;
                        }
                    }
                }
            }
        }

        foreach ($cedulasEnCarga as $op) {
            $llamadasRealizadas = 0;
            $llamadasAtendidas = 0;
            $llamadasnoAtendidas = 0;
            $correosEnviados = 0;
            $correosRecibidos = 0;
            $wpEnviados = 0;
            $wpRecibidos = 0;
            $estado = "NO GESTIONADO";

            if (isset($llamadas[$op[0]])) {
                $llamadasRealizadas = $llamadas[$op[0]]["realizadas"];
                $llamadasAtendidas = $llamadas[$op[0]]["atendidas"];
                $llamadasnoAtendidas = $llamadas[$op[0]]["no_atendidas"];
                $estado = "GESTIONADO";
            }
            if (isset($correos[$op[0]])) {
                $correosEnviados = $correos[$op[0]]["enviado"];
                $correosRecibidos = $correos[$op[0]]["recibido"];
                $estado = "GESTIONADO";
            }
            if (isset($whatsapps[$op[0]])) {
                $wpEnviados = $whatsapps[$op[0]]["enviado"];
                $wpRecibidos = $whatsapps[$op[0]]["recibido"];
                $estado = "GESTIONADO";
            }

            $fila = [
                //$op[2], //cedula
                //$op[1], //nombre
                $op[0], //operacion
                $estado, //estado
                $llamadasRealizadas, //total_llamadas
                $llamadasAtendidas, //llamadas_atendidas
                $llamadasnoAtendidas, //llamadas_no_atendidas
                $wpEnviados, //total mensajes whatsapp
                $wpRecibidos, //mensajes_whatsapp_recibidos
                $correosEnviados, //mails enviados
                $correosRecibidos, //mails recibidos
            ];
            $exportar[] = $fila;
        }

        if (count($exportar) > 0) {
            $encabezado = "OPERACIÓN;ESTADO;TOTAL LLAMADAS;LLAMADAS ANTENDIDAS;LLAMADAS NO ATENDIDAS;TOTAL MENSAJES WHATSAPP;MENSAJES WHATSAPP RECIBIDOS;TOTAL CORREOS;CORREOS LEÍDOS";
            $encabezado = str_replace(";", $separador, $encabezado);
            $textoFinal = "";
            foreach ($exportar as $linea) {
                $textoFinal .= implode($separador, $linea) . $saltoLinea;
            }
            $archivo = BASEFOLDER . "/web/cacheFolder/Reporte_gestion_general_" . date("dmYHis") . ".csv";
            $myFile = fopen($archivo, "w");
            if ($myFile !== false) {
                fwrite($myFile, $encabezado . $saltoLinea);
                fwrite($myFile, $textoFinal);
                fclose($myFile);
                if (file_exists($archivo)) {
                    $json["respuesta"]["archivo"] = str_replace(BASEFOLDER, BASEURL, $archivo);
                } else {
                    $json["respuesta"]["error"] = "No se pudo crear el archivo [002]";
                }
            } else {
                $json["respuesta"]["error"] = "No se pudo crear el archivo [001]";
            }
        } else {
            $json["respuesta"]["error"] = "No hay datos para generar reporte";
        }
        #endregion
        break;
    case "descargarGestionLlamadas":
        #region descargarGestionLlamadas
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicion = establecerCondicionMaster();
        $campo = "av_fecha";
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $mongo2 = new MYMONGODB();
        $c = $mongo->buscar("avProgramadas", $condicion, [], ['av_fechaGeneraLlamada' => 1]);
        $exportar = [];
        if ($c > 0) {
            while ($campos = $mongo->siguientex()) {
                $camposResultado = [];
                $c2 = $mongo2->buscar("avDetalleConversacionesRetell", ["idConversacion" => $campos["av_idConversacion"]]);
                $avDetalle = [];
                if ($c2 > 0) {
                    $avDetalle = $mongo2->siguientex();
                }

                $camposResultado["Estado"] = isset($traduceEstados[$campos["av_estadoEnvio"]]) ? $traduceEstados[$campos["av_estadoEnvio"]] : $campos["av_estadoEnvio"];
                $camposResultado["Fecha asignación"] = date("d/m/Y H:i:s", $campos["av_fecha"]);
                $camposResultado["Fecha inicio"] = isset($campos["av_fechaGeneraLlamada"]) && $campos["av_fechaGeneraLlamada"] > 0 ? date("d/m/Y H:i:s", $campos["av_fechaGeneraLlamada"]) : "";
                $camposResultado["Fecha fin"] = isset($campos["av_fechaFinLlamada"]) && $campos["av_fechaFinLlamada"] > 0 ? date("d/m/Y H:i:s", $campos["av_fechaFinLlamada"]) : "";
                $camposResultado["Duracion llamada"] = isset($campos["av_duracionSegundos"]) && $campos["av_duracionSegundos"] > 0 ? ($campos["av_duracionSegundos"] > 3600 ? gmdate("H:i.s", $campos["av_duracionSegundos"]) : gmdate("i:s", $campos["av_duracionSegundos"])) : "";
                $camposResultado["Número"] = $campos["av_telefono"];
                //$camposResultado["Cédula"] = $campos["av_cedula"];
                //$camposResultado["Nombre"] = $campos["av_nombre"];
                $camposResultado["Operación"] = $campos["av_factura"];
                $camposResultado["Periodo"] = isset($campos["av_periodo"]) ? $campos["av_periodo"] : "";
                $camposResultado["Producto"] = $campos["av_producto"];
                $camposResultado["Cartera"] = $campos["av_carteraNombre"];
                $camposResultado["Campaña"] = $campos["av_campaniaNombre"];
                $camposResultado["Fecha programación"] = isset($campos["av_lote"]) && $campos["av_lote"] > 0 ? date("d/m/Y H:i:s", $campos["av_lote"]) : "";
                if ($permisos["soyDesarrollo"]) {
                    $camposResultado["Proveedor"] = isset($campos["av_proveedor"]) ? $campos["av_proveedor"] : "";
                    //obtengo los tokens
                    $tokens = 0;
                    $costo = 0;
                    if (isset($campos["av_duracionSegundos"]) && $campos["av_duracionSegundos"] > 0) {
                        if (isset($avDetalle["costoCreditos"])) {
                            $costo = formatea_numero($avDetalle["costoCreditos"], 2, ".", "");
                        }
                        if (isset($avDetalle["usoTokens"])) {
                            $tokens = $avDetalle["usoTokens"]["total"];
                        }
                    }
                    $camposResultado["Costo"] = $costo;
                    $camposResultado["Tokens"] = $tokens;
                }
                $camposResultado["Sentimiento"] = isset($campos["av_sentimiento"]) ? (isset($traducSentimiento[$campos["av_sentimiento"]]) ? $traducSentimiento[$campos["av_sentimiento"]] : $campos["av_sentimiento"]) : "";
                $camposResultado["Motivo finalización"] = isset($campos["av_finalizacion"]) ? (isset($traduceDesconexion[$campos["av_finalizacion"]]) ? $traduceDesconexion[$campos["av_finalizacion"]] : $campos["av_finalizacion"]) : "";
                $camposResultado["Tipificación 1"] = isset($campos["av_tipificacion"]["respuesta1"]) ? $campos["av_tipificacion"]["respuesta1"] : "";
                $camposResultado["Tipificación 2"] = isset($campos["av_tipificacion"]["respuesta2"]) ? $campos["av_tipificacion"]["respuesta2"] : "";
                if (isset($campos["av_tipificacion"]) && $campos["av_tipificacion"]["respuesta3"] != "") {
                    $partes = explode("|", $campos["av_tipificacion"]["respuesta3"]);
                    if (isset($partes[1]) && $partes[1] != "") {
                        if (
                            $partes[1] != "TOMORROW" &
                            $partes[1] != "NEXT MONTH" &
                            $partes[1] != "VIERNES" &
                            $partes[1] != "MONDAY" &
                            $partes[1] != "NEXT FRIDAY" &
                            $partes[1] != "FRIDAY" &
                            $partes[1] != "SATURDAY" &
                            $partes[1] != "TUESDAY" &
                            $partes[1] != "WEDNESDAY" &
                            $partes[1] != "NEXT MONDAY" &
                            $partes[1] != "THURSDAY" &
                            $partes[1] != "NEXT WEEK"

                        ) {
                            $camposResultado["Compromiso pago"] = "";
                            $x = strtotime($partes[1]);
                            if ($partes[1] == "TODAY") {
                                $x = time();
                            }
                            if ($x > 0) {
                                $d = date("Y-m-d", $x);
                                if ($d !== false) {
                                    $a = date("Ym", $x);
                                    $h = date("Ym");
                                    if ($a >= $h) {
                                        $camposResultado["Compromiso pago"] = $d;
                                    }
                                }
                            }
                        } else {
                            $camposResultado["Compromiso pago"] = "";
                        }
                    } else {
                        $camposResultado["Compromiso pago"] = "";
                    }
                } else {
                    $camposResultado["Compromiso pago"] = "";
                }
                if (isset($avDetalle["archivoAudio"]) && $avDetalle["archivoAudio"] != "") {
                    $url = str_replace(BASEFOLDER, BASEURL, $avDetalle["archivoAudio"]);
                    if ($url != BASEURL && $url != BASEURL . "sc/cacheAudios/") {
                        $camposResultado["Audio"] = $url;
                    } else {
                        $camposResultado["Audio"] = "";
                    }
                } else {
                    $camposResultado["Audio"] = "";
                }
                $exportar[] = $camposResultado;
            }
        }
        if (count($exportar) > 0) {
            if ($permisos["soyDesarrollo"]) {
                $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA INICIO;FECHA FIN;DURACION LLAMADA;NÚMERO;OPERACIÓN;PERIODO;PRODUCTO;CARTERA;CAMPAÑA;FECHA PROGRAMACIÓN;PROVEEDOR;COSTO;TOKENS;SENTIMIENTO;MOTIVO FINALIZACIÓN;TIPIFICACIÓN 1;TIPIFICACIÓN 2;COMPROMISO PAGO;AUDIO";
            } else {
                $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA INICIO;FECHA FIN;DURACION LLAMADA;NÚMERO;OPERACIÓN;PERIODO;PRODUCTO;CARTERA;CAMPAÑA;FECHA PROGRAMACIÓN;SENTIMIENTO;MOTIVO FINALIZACIÓN;TIPIFICACIÓN 1;TIPIFICACIÓN 2;COMPROMISO PAGO;AUDIO";
            }
            $encabezado = str_replace(";", $separador, $encabezado);
            $textoFinal = "";
            foreach ($exportar as $linea) {
                $textoFinal .= implode($separador, $linea) . $saltoLinea;
            }
            $archivo = BASEFOLDER . "/web/cacheFolder/Reporte_llamadas_av_" . date("dmYHis") . ".csv";
            $myFile = fopen($archivo, "w");
            if ($myFile !== false) {
                fwrite($myFile, $encabezado . $saltoLinea);
                fwrite($myFile, $textoFinal);
                fclose($myFile);
                if (file_exists($archivo)) {
                    $json["respuesta"]["archivo"] = str_replace(BASEFOLDER, BASEURL, $archivo);
                } else {
                    $json["respuesta"]["error"] = "No se pudo crear el archivo [002]";
                }
            } else {
                $json["respuesta"]["error"] = "No se pudo crear el archivo [001]";
            }
        } else {
            $json["respuesta"]["error"] = "No hay datos para generar reporte";
        }
        #endregion
        break;
    case "descargarGestionCorreo":
        #region descargarGestionCorreo
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        //$filtroCanales = expect_safe_html($d["filtroCanales"]);
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");

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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCorreo["cem_susCarteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicionCorreo["cem_susCampaniaId"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $c = $mongo->buscar("cbEnvioMails", $condicionCorreo, [], ['cem_susFechaEnvio' => 1]);
        $exportar = [];
        if ($c > 0) {
            $hoy = strtotime(date("Y-m-d") . " 00:00:00");
            while ($campos = $mongo->siguientex()) {
                $camposResultado = [];
                $estado = isset($campos["cem_susFechaEnvio"]) && $campos["cem_susFechaEnvio"] > 0 ? "ENVIADO" : (isset($row["cem_susFechaAsignacion"]) && $row["cem_susFechaAsignacion"] < $hoy ? "NO ENVIADO" : "PENDIENTE");
                $camposResultado["Estado"] = $estado;
                $camposResultado["Fecha asignación"] = isset($row["cem_susFechaAsignacion"]) ? date("d/m/Y H:i:s", $row["cem_susFechaAsignacion"]) : "";
                $camposResultado["Fecha envio"] = isset($campos["cem_susFechaEnvio"]) && $campos["cem_susFechaEnvio"] > 0 ? date("d/m/Y H:i:s", $campos["cem_susFechaEnvio"]) : "";
                $camposResultado["Abierto"] = isset($campos["abierto"]) && $campos["abierto"] > 0 ? date("d/m/Y H:i", $campos["abierto"]) : "";
                $camposResultado["Correo"] = $campos["cem_susEmail"];
                //$camposResultado["Cédula"] = $campos["cem_susCedula"];
                //$camposResultado["Nombre"] = $campos["cem_susNombre"];
                $camposResultado["Operación"] = $campos["cem_susFactura"];
                $camposResultado["Cartera"] = $campos["cem_susCarteraNombre"] == "" ? "BANCO DEL PACIFICO" : $campos["cem_susCarteraNombre"];
                $camposResultado["Campaña"] = $campos["cem_susCampaniaNombre"];
                $exportar[] = $camposResultado;
            }
        }
        if (count($exportar) > 0) {
            $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA ENVIO;ABIERTO;CORREO;OPERACIÓN;CARTERA;CAMPAÑA";
            $encabezado = str_replace(";", $separador, $encabezado);
            $textoFinal = "";
            foreach ($exportar as $linea) {
                $textoFinal .= implode($separador, $linea) . $saltoLinea;
            }
            $archivo = BASEFOLDER . "/web/cacheFolder/Reporte_correos_av_" . date("dmYHis") . ".csv";
            $myFile = fopen($archivo, "w");
            if ($myFile !== false) {
                fwrite($myFile, $encabezado . $saltoLinea);
                fwrite($myFile, $textoFinal);
                fclose($myFile);
                if (file_exists($archivo)) {
                    $json["respuesta"]["archivo"] = str_replace(BASEFOLDER, BASEURL, $archivo);
                } else {
                    $json["respuesta"]["error"] = "No se pudo crear el archivo [002]";
                }
            } else {
                $json["respuesta"]["error"] = "No se pudo crear el archivo [001]";
            }
        } else {
            $json["respuesta"]["error"] = "No hay datos para generar reporte";
        }
        #endregion
        break;
    case "descargarGestionWhatsapp":
        #region descargarGestionWhatsapp
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        // $filtroCanales = expect_safe_html($d["filtroCanales"]);
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionWhatsapp["ws_fecha"] = 1756843285;
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionWhatsapp["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicionWhatsapp["ws_campaniaId"] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();
        $c = $mongo->buscar("avProgramadasWhatsApp", $condicionWhatsapp, [], ['ws_fecha' => 1]);
        $exportar = [];
        if ($c > 0) {
            while ($campos = $mongo->siguientex()) {
                $camposResultado = [];
                $camposResultado["Estado"] = $campos["ws_estadoEnvio"];
                $camposResultado["Fecha asignación"] = date("d/m/Y H:i:s", $campos["ws_fecha"]);
                $camposResultado["Fecha envio"] = date("d/m/Y H:i:s", $campos["ws_fecha"]);
                $camposResultado["Número"] = $campos["ws_telefono"];
                //$camposResultado["Cédula"] = $campos["ws_cedula"];
                //$camposResultado["Nombre"] = $campos["ws_nombre"];
                $camposResultado["Operación"] = $campos["ws_factura"];
                $camposResultado["Cartera"] = $campos["ws_carteraNombre"] == "" ? "BANCO DEL PACIFICO" : $campos["ws_carteraNombre"];
                $camposResultado["Campaña"] = $campos["ws_campaniaNombre"];
                $camposResultado["Tipificación 1"] = isset($campos["ws_tipificacion"]["respuesta1"]) ? $campos["ws_tipificacion"]["respuesta1"] : "";
                $camposResultado["Tipificación 2"] = isset($campos["ws_tipificacion"]["respuesta2"]) ? $campos["ws_tipificacion"]["respuesta2"] : "";
                $exportar[] = $camposResultado;
            }
        }
        if (count($exportar) > 0) {

            $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA ENVIO;NÚMERO;OPERACIÓN;CARTERA;CAMPAÑA;TIPIFICACIÓN 1;TIPIFICACIÓN 2";
            $encabezado = str_replace(";", $separador, $encabezado);
            $textoFinal = "";
            foreach ($exportar as $linea) {
                $textoFinal .= implode($separador, $linea) . $saltoLinea;
            }
            $archivo = BASEFOLDER . "/web/cacheFolder/Reporte_whatsapp_av_" . date("dmYHis") . ".csv";
            $myFile = fopen($archivo, "w");
            if ($myFile !== false) {
                fwrite($myFile, $encabezado . $saltoLinea);
                fwrite($myFile, $textoFinal);
                fclose($myFile);
                if (file_exists($archivo)) {
                    $json["respuesta"]["archivo"] = str_replace(BASEFOLDER, BASEURL, $archivo);
                } else {
                    $json["respuesta"]["error"] = "No se pudo crear el archivo [002]";
                }
            } else {
                $json["respuesta"]["error"] = "No se pudo crear el archivo [001]";
            }
        } else {
            $json["respuesta"]["error"] = "No hay datos para generar reporte";
        }
        #endregion
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


function obtenerTotalesLlamadasAV($condicion)
{
    $respuesta = [
        "cuadros" => [
            "numeroProgramadas" => [
                "nombre" => "Programadas",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas programadas para gestionar",
            ],
            "numeroLlamadasPendientes" => [
                "nombre" => "Pendientes",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas pendientes"
            ],
            "numeroLlamadasLuego" => [
                "nombre" => "Marcar luego",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas que se marcaron para llamar luego"
            ],

            "numeroLlamadasFinalizadas" => [
                "nombre" => "Atendidas",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas atendidas"
            ],
            "numeroLlamadasError" => [
                "nombre" => "No atendidas",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas no atendidas por el cliente"
            ],
            "numeroLlamadasEnProgreso" => [
                "nombre" => "En progreso",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas recién iniciadas o en progreso"
            ],
            "numeroLlamadasDesprogramadas" => [
                "nombre" => "Desprogramadas",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas desprogramadas"
            ],

            "numeroLlamadasSinConexion" => [
                "nombre" => "Sin conexión",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Llamadas que no se pudieron realizar o aún no se tipifican"
            ],
            "duracionPromedioSegundos" => [
                "nombre" => "Dur. promedio",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Duración promedio"
            ]
        ],
        "totalIntensidad" => 0,
        "porcentajeAvance" => 0,
        "campanias" => [],
        "gestionadas" => [],
        "compromisosPago" => []
    ];

    $mongo = new MYMONGODB();
    $cursor = $mongo->buscar("avProgramadas", $condicion);
    $llamadasPendientes = 0;
    $llamadasFinalizadas = 0;
    $llamadasLanzadas = 0;
    $llamadasSinConexion = 0;
    $llamadasError = 0;
    $llamadasProgreso = 0;
    $llamadasDesprogramadas = 0;
    $llamadasReintento = 0;
    $llamadasHechas = 0;
    $totalDuracion = 0;
    $campanias = [];
    $gestionadoPorCampania = [];
    $cedulasGestionadas = [];
    $cg = [];
    $cedulasCompromiso = [];
    if (count($condicion) > 0) {
        if ($cursor > 0) {
            $respuesta["cuadros"]["numeroProgramadas"]["total"] = $cursor;
            $respuesta["cuadros"]["numeroProgramadas"]["porcentaje"] = formatea_numero(100, 2, ",", ".");
            while ($row = $mongo->siguientex()) {
                $llamadasHechas++;
                $campanias[$row["av_campaniaNombre"]] = $row["av_campaniaNombre"];

                $totalDuracion += isset($row["av_duracionSegundos"]) ? $row["av_duracionSegundos"] : 0;
                switch ($row["av_estadoEnvio"]) {
                    case 'PENDIENTE':
                        $llamadasPendientes++;
                        break;
                    case 'FINALIZADA':
                        $llamadasFinalizadas++;
                        //$llamadasHechas++;
                        break;
                    case "LLAMADA_GENERADA":
                        $llamadasLanzadas++;
                        //$llamadasHechas++;
                        break;
                    case "ERROR_GENERAR_LLAMADA":
                        //$llamadasLanzadas++;
                        if (isset($row["av_finalizacion"])) {
                            switch ($row["av_finalizacion"]) {
                                case 'telephony_provider_permission_denied':
                                    $llamadasSinConexion++;
                                    break;
                                case 'dial_failed':
                                    $llamadasSinConexion++;
                                    break;
                                case 'dial_busy':
                                    if (!isset($row["av_tipificacion"]) || $row["av_tipificacion"] == "") {
                                        $llamadasSinConexion++;
                                    } else {
                                        $llamadasError++;
                                    }
                                    break;
                                case 'error_user_not_joined':
                                    $llamadasSinConexion++;
                                    break;
                                case 'concurrency_limit_reached':
                                    $llamadasSinConexion++;
                                    break;
                                case 'no_valid_payment':
                                    $llamadasSinConexion++;
                                    break;
                                case 'scam_detected':
                                    $llamadasSinConexion++;
                                    break;
                                case 'error_retell':
                                    $llamadasSinConexion++;
                                    break;
                                case 'error_unknown':
                                    $llamadasSinConexion++;
                                    break;
                                case 'telephony_provider_unavailable':
                                    $llamadasSinConexion++;
                                    break;
                                case 'user_declined':
                                    $llamadasSinConexion++;
                                    break;
                                case 'invalid_destination':
                                    $llamadasSinConexion++;
                                    break;
                                case 'sip_routing_error':
                                    $llamadasSinConexion++;
                                    break;
                                case 'marked_as_spam':
                                    $llamadasSinConexion++;
                                    break;
                                case "dial_no_answer":
                                    if (!isset($row["av_tipificacion"]) || $row["av_tipificacion"] == "") {
                                        $llamadasSinConexion++;
                                    } else {
                                        $llamadasError++;
                                    }
                                    //$llamadasError++;
                                    break;
                                default:
                                    $llamadasError++;
                                    break;
                            }
                        } else {
                            $llamadasSinConexion++;
                        }
                        //$llamadasSinConexion++;
                        break;
                    case "ERROR":
                        if (isset($row["av_finalizacion"])) {
                            switch ($row["av_finalizacion"]) {
                                case 'telephony_provider_permission_denied':
                                    $llamadasSinConexion++;
                                    break;
                                case 'dial_failed':
                                    $llamadasSinConexion++;
                                    break;
                                case 'dial_busy':
                                    if (!isset($row["av_tipificacion"]) || $row["av_tipificacion"] == "") {
                                        $llamadasSinConexion++;
                                    } else {
                                        $llamadasError++;
                                    }
                                    break;
                                case 'error_user_not_joined':
                                    $llamadasSinConexion++;
                                    break;
                                case 'concurrency_limit_reached':
                                    $llamadasSinConexion++;
                                    break;
                                case 'no_valid_payment':
                                    $llamadasSinConexion++;
                                    break;
                                case 'scam_detected':
                                    $llamadasSinConexion++;
                                    break;
                                case 'error_retell':
                                    $llamadasSinConexion++;
                                    break;
                                case 'error_unknown':
                                    $llamadasSinConexion++;
                                    break;
                                case 'telephony_provider_unavailable':
                                    $llamadasSinConexion++;
                                    break;
                                case 'user_declined':
                                    $llamadasSinConexion++;
                                    break;
                                case 'invalid_destination':
                                    $llamadasSinConexion++;
                                    break;
                                case 'sip_routing_error':
                                    $llamadasSinConexion++;
                                    break;
                                case 'marked_as_spam':
                                    $llamadasSinConexion++;
                                    break;
                                case "dial_no_answer":
                                    if (!isset($row["av_tipificacion"]) || $row["av_tipificacion"] == "") {
                                        $llamadasSinConexion++;
                                    } else {
                                        $llamadasError++;
                                    }
                                    //$llamadasError++;
                                    break;
                                case "voicemail_reached":
                                    $llamadasError++;
                                    break;
                                default:
                                    $llamadasError++;
                                    break;
                            }
                        } else {
                            $llamadasSinConexion++;
                        }
                        break;
                    case "EN_PROGRESO":
                        $llamadasProgreso++;
                        //$llamadasHechas++;
                        break;
                    // case "DESPROGRAMADO_ASIGNACION":
                    //     $llamadasDesprogramadas++;
                    //     break;
                    case "DESPROGRAMADO_VERIFICACION":
                        $llamadasDesprogramadas++;
                        break;
                    case "MARCAR_LUEGO":
                        //$llamadasPendientes++;
                        $llamadasReintento++;
                        break;
                }

                if (isset($row["av_desprogramada"]) && $row["av_desprogramada"] > 0 && $row["av_estadoEnvio"] != "DESPROGRAMADO_VERIFICACION") {
                    //desprogramadas en asignacion
                    $llamadasDesprogramadas++;
                }

                if (
                    $row["av_estadoEnvio"] != "DESPROGRAMADO_ASIGNACION"
                    && $row["av_estadoEnvio"] != "DESPROGRAMADO_VERIFICACION"
                    && $row["av_estadoEnvio"] != "PENDIENTE"
                    && $row["av_estadoEnvio"] != "MARCAR_LUEGO"
                    && $row["av_estadoEnvio"] != "ERROR_GENERAR_LLAMADA"
                    && $row["av_estadoEnvio"] != "EN_PROGRESO"
                ) {
                    if (isset($cedulasGestionadas[$row["av_factura"]])) {
                        if (isset($row["av_tipificacion"]["respuesta1"])) {
                            if ($row["av_tipificacion"]["respuesta1"] == "CONTACTO DIRECTO") {

                                $cedulasGestionadas[$row["av_factura"]] = "CONTACTO DIRECTO";
                            }
                            if ($row["av_tipificacion"]["respuesta1"] == "CONTACTO INDIRECTO" && $cedulasGestionadas[$row["av_factura"]] == "SIN CONTACTO") {
                                $cedulasGestionadas[$row["av_factura"]] = "CONTACTO INDIRECTO";
                            }
                        } else {
                            $cedulasGestionadas[$row["av_factura"]] = "SIN CONTACTO";
                        }
                    } else {
                        if (isset($row["av_tipificacion"]["respuesta1"])) {
                            $cedulasGestionadas[$row["av_factura"]] = $row["av_tipificacion"]["respuesta1"];
                        } else {
                            $cedulasGestionadas[$row["av_factura"]] = "SIN CONTACTO";
                        }
                    }

                    if (isset($row["av_tipificacion"]) && ($row["av_tipificacion"]["respuesta1"] == "CONTACTO DIRECTO" || $row["av_tipificacion"]["respuesta1"] == "CONTACTO INDIRECTO")) {
                        if (!isset($gestionadoPorCampania[$row["av_campaniaNombre"]])) {
                            $gestionadoPorCampania[$row["av_campaniaNombre"]] = [];
                        }
                        $gestionadoPorCampania[$row["av_campaniaNombre"]][$row["av_factura"]] = 1;
                    }

                    //compromisos de pago
                    if (
                        isset($row["av_tipificacion"]["respuesta2"]) && $row["av_tipificacion"]["respuesta2"] == "PAGA EN FECHA"
                        && $row["av_tipificacion"]["respuesta3"] != ""
                    ) {
                        // $partes = explode("|", $row["av_tipificacion"]["respuesta3"]);
                        // if ($partes[1] != "") {
                        //     if (
                        //         $partes[1] != "TOMORROW" &
                        //         $partes[1] != "NEXT MONTH" &
                        //         $partes[1] != "VIERNES" &
                        //         $partes[1] != "MONDAY" &
                        //         $partes[1] != "NEXT FRIDAY" &
                        //         $partes[1] != "FRIDAY" &
                        //         $partes[1] != "SATURDAY" &
                        //         $partes[1] != "TUESDAY" &
                        //         $partes[1] != "WEDNESDAY" &
                        //         $partes[1] != "NEXT MONDAY" &
                        //         $partes[1] != "THURSDAY" &
                        //         $partes[1] != "NEXT WEEK"

                        //     ) {
                        //         $x = strtotime($partes[1]);
                        //         if ($partes[1] == "TODAY") {
                        //             $x = time();
                        //         }
                        //         if ($x > 0) {
                        //             $d = date("Y-m-d", $x);
                        //             if ($d !== false) {
                        //                 $a = date("Ym", $x);
                        //                 $h = date("Ym");
                        //                 if ($a >= $h) {
                        //                     $cedulasCompromiso[$row["av_factura"]] = $x;
                        //                     //$cedulasCompromiso[] = $x;
                        //                 }
                        //             }
                        //         }
                        //     }
                        // }
                        //$cedulasCompromiso[$row["av_factura"]] = $row["av_tipificacion"]["respuesta3"];
                        $cedulasCompromiso[] = $row["av_tipificacion"]["respuesta3"];
                    }
                }
            }
            $duracionPromedio = $llamadasFinalizadas > 0 ? $totalDuracion / $llamadasFinalizadas : 0;

            $respuesta["cuadros"]["numeroLlamadasDesprogramadas"]["total"] = $llamadasDesprogramadas;
            $respuesta["cuadros"]["numeroLlamadasDesprogramadas"]["porcentaje"] = formatea_numero(($llamadasDesprogramadas * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasPendientes"]["total"] = $llamadasPendientes;
            $respuesta["cuadros"]["numeroLlamadasPendientes"]["porcentaje"] = formatea_numero(($llamadasPendientes * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasLuego"]["total"] = $llamadasReintento;
            $respuesta["cuadros"]["numeroLlamadasLuego"]["porcentaje"] = formatea_numero(($llamadasReintento * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasEnProgreso"]["total"] = $llamadasProgreso;
            $respuesta["cuadros"]["numeroLlamadasEnProgreso"]["porcentaje"] = formatea_numero(($llamadasProgreso * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasFinalizadas"]["total"] = $llamadasFinalizadas;
            $respuesta["cuadros"]["numeroLlamadasFinalizadas"]["porcentaje"] = formatea_numero(($llamadasFinalizadas * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasError"]["total"] = $llamadasError;
            $respuesta["cuadros"]["numeroLlamadasError"]["porcentaje"] = formatea_numero(($llamadasError * 100 / $cursor), 2, ",", ".");
            $respuesta["cuadros"]["numeroLlamadasSinConexion"]["total"] = $llamadasSinConexion;
            $respuesta["cuadros"]["numeroLlamadasSinConexion"]["porcentaje"] = formatea_numero(($llamadasSinConexion * 100 / $cursor), 2, ",", ".");
            if ($duracionPromedio > 3600) {
                $respuesta["cuadros"]["duracionPromedioSegundos"]["total"] = gmdate("H:i.s", $duracionPromedio);
                $respuesta["cuadros"]["duracionPromedioSegundos"]["tooltip"] = "Duración promedio en horas, minutos y segundos";
            } else {
                $respuesta["cuadros"]["duracionPromedioSegundos"]["total"] = gmdate("i:s", $duracionPromedio);
                $respuesta["cuadros"]["duracionPromedioSegundos"]["tooltip"] = "Duración promedio en minutos y segundos";
            }
            $porcentajeAvance = round(((($llamadasFinalizadas + $llamadasError + $llamadasSinConexion + $llamadasDesprogramadas) * 100) / $cursor), 1);
            $respuesta["porcentajeAvance"] = $porcentajeAvance;
        }
    }
    $respuesta["campanias"] = array_values($campanias);
    $respuesta["gestionadas"] = $cedulasGestionadas;
    $respuesta["gestionadasPorCampania"] = $gestionadoPorCampania;
    $respuesta["totalIntensidad"] = $llamadasHechas; // $llamadasFinalizadas + $llamadasReintento + $llamadasError;

    $respuesta["compromisosPago"] = $cedulasCompromiso;
    return $respuesta;
}

function obtenerTotalesCorreo($condicion)
{
    $respuesta = [
        "cuadros" => [
            "numeroProgramadas" => [
                "nombre" => "Programados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Envios programados para gestionar",
            ],
            "numeroPendientes" => [
                "nombre" => "Pendientes",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Correos pendientes de envio"
            ],
            "numeroEnviados" => [
                "nombre" => "Enviados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Correos enviados"
            ],
            "numeroError" => [
                "nombre" => "No enviados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Correos no enviados"
            ]
        ],
        "porcentajeAvance" => 0,
        "campanias" => []
    ];
    $campanias = [];
    $gestionadoPorCampania = [];
    $cedulasGestionadas = [];
    $mongo = new MYMONGODB();
    $mongo2 = new MYMONGODB();
    $totalPendientes = 0;
    $totalEnviados = 0;
    $totalError = 0;
    $cursor = $mongo->buscar("cbEnvioMails", $condicion);
    if ($cursor > 0) {
        $respuesta["cuadros"]["numeroProgramadas"]["total"] = $cursor;
        $respuesta["cuadros"]["numeroProgramadas"]["porcentaje"] = formatea_numero(100, 2, ",", ".");
        while ($row = $mongo->siguientex()) {
            $cedula = $row["cem_susFactura"] ?? $row["cem_susCedula"];

            //obtener factura
            // $t = $mongo2->buscar("cbCargaEtlDetalleCargas", ["carteraEtl_cedula" => strval($row["cem_susCedula"])]);
            // if ($t > 0) {
            //     $u = $mongo2->siguientex();
            //     $cedula = $u["carteraEtl_factura"];
            // }

            $campanias[$row["cem_susCampaniaNombre"]] = $row["cem_susCampaniaNombre"];

            // if (!in_array($row["cem_susCedula"], $cedulasGestionadas)) {
            //     $cedulasGestionadas[$row["cem_susCedula"]] == "";
            // }

            if (isset($row["cem_susFechaEnvio"]) && $row["cem_susFechaEnvio"] > 0) {
                if (isset($row["cem_susLlamadaId"]) && $row["cem_susLlamadaId"] > 0) {
                    $totalEnviados++;
                    $cedulasGestionadas[$cedula] = "CONTACTO DIRECTO";
                    $gestionadoPorCampania[$row["cem_susCampaniaNombre"]] = isset($gestionadoPorCampania[$row["cem_susCampaniaNombre"]]) ? $gestionadoPorCampania[$row["cem_susCampaniaNombre"]] + 1 : 1;
                } else {
                    $totalError++;
                    $cedulasGestionadas[$cedula] = "SIN CONTACTO";
                }
            } else if (isset($row["cem_susErrorEnvio"]) && $row["cem_susErrorEnvio"] > 0) {
                $totalError++;
                $cedulasGestionadas[$cedula] = "SIN CONTACTO";
            } else {
                $hoy = strtotime(date("Y-m-d") . " 00:00:00");
                if ($row["cem_susFechaAsignacion"] < $hoy) {
                    $totalError++;
                } else {
                    $totalPendientes++;
                }
                $cedulasGestionadas[$cedula] = "SIN CONTACTO";
            }
        }

        $respuesta["cuadros"]["numeroPendientes"]["porcentaje"] = formatea_numero(($totalPendientes * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroPendientes"]["total"] = $totalPendientes;
        $respuesta["cuadros"]["numeroEnviados"]["porcentaje"] = formatea_numero(($totalEnviados * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroEnviados"]["total"] = $totalEnviados;
        $respuesta["cuadros"]["numeroError"]["porcentaje"] = formatea_numero(($totalError * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroError"]["total"] = $totalError;
        $porcentajeAvance = round((($totalEnviados * 100) / $cursor), 1);
        $respuesta["porcentajeAvance"] = $porcentajeAvance;
    }
    $respuesta["campanias"] = array_values($campanias);
    $respuesta["gestionadas"] = $cedulasGestionadas;
    $respuesta["gestionadasPorCampania"] = $gestionadoPorCampania;
    $respuesta["totalIntensidad"] = $totalEnviados;
    return $respuesta;
}

function obtenerTotalesWhatsapp($condicion)
{
    //$condicion["ws_estadoEnvio"] = ['$in' => ["PENDIENTE", "CONTESTADO", "ENVIADO"]];
    $respuesta = [
        "cuadros" => [
            "numeroProgramadas" => [
                "nombre" => "Programados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Programadas para gestionar",
            ],
            "numeroPendientesEnvio" => [
                "nombre" => "Pendientes",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Mensajes pendiente de envio"
            ],
            "numeroEnviados" => [
                "nombre" => "Entregados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Entregados al usuario",
            ],
            "numeroError" => [
                "nombre" => "No enviados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "No enviados",
            ],
            "numeroRecibidos" => [
                "nombre" => "Recibidos",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Mensajes recibidos",
                "extra" => ""
            ],
            "numeroLeido" => [
                "nombre" => "Leidos",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Mensajes leidos"
            ],
            "numeroRepondidos" => [
                "nombre" => "Contestados",
                "total" => 0,
                "porcentaje" => "0,00",
                "tooltip" => "Mensajes que se contestaron"
            ],
            // "numeroNoLeido" => [
            //     "nombre" => "No leidos",
            //     "total" => 0,
            //     "porcentaje" => "0,00",
            //     "tooltip" => "Mensajes no leidos"
            // ],
            // "numeroNoRecibidos" => [
            //     "nombre" => "No recibidos",
            //     "total" => 0,
            //     "porcentaje" => "0,00",
            //     "tooltip" => "Mensajes no recibidos"
            // ],

        ],
        "porcentajeAvance" => 0,
        "campanias" => [],
        "totalIntensidad" => 0
    ];
    $campanias = [];
    $cedulasGestionadas = [];
    $gestionadoPorCampania = [];
    $mongo = new MYMONGODB();
    $mongo1 = new MYMONGODB();
    $mongo2 = new MYMONGODB();
    $cursor = $mongo->buscar("avProgramadasWhatsApp", $condicion);
    $totalRecibido = 0;
    $totalLeido = 0;
    $totalRespondido = 0;
    $totalNoLeido = 0;
    $totalEnviado = 0;
    $totalNoEnviado = 0;
    $totalError = 0;
    if ($cursor > 0) {
        $respuesta["cuadros"]["numeroProgramadas"]["total"] = $cursor;
        $respuesta["cuadros"]["numeroProgramadas"]["porcentaje"] = formatea_numero(100, 2, ",", ".");
        while ($row = $mongo->siguientex()) {
            if ($row["ws_estadoEnvio"] == "CONTESTADO") {
                $totalEnviado++;
                $totalRecibido++;
                $totalLeido++;
                $totalRespondido++;
                $cedulasGestionadas[$row["ws_factura"]] = "CONTACTO DIRECTO";
                $gestionadoPorCampania[$row["ws_campaniaNombre"]] = isset($gestionadoPorCampania[$row["ws_campaniaNombre"]]) ? $gestionadoPorCampania[$row["ws_campaniaNombre"]] + 1 : 1;
            } else if ($row['ws_estadoEnvio'] == 'ENVIADO') {
                $fd = strtotime(date("Y-m-d H:i:s", $row["ws_fecha"])) - 300;
                $fh = strtotime(date("Y-m-d H:i:s", $row["ws_fecha"])) + 300;
                $resp = $mongo1->buscar("whatsapp_logs", ["wp_numeroWP" => $row["ws_telefono"] . "@c.us", "wp_date" => ['$gte' => $fd, '$lte' => $fh], "wp_ack" => "enviado"]);
                $entregado = false;
                $leido = false;
                $totalEnviado++;
                if ($resp > 0) {
                    while ($r = $mongo1->siguiente()) {
                        $mongo2->buscar("whatsapp_logs", ["wp_msgID" => $r["wp_msgID"], "wp_ack" => ['$ne' => "enviado"]]);
                        while ($r2 = $mongo2->siguiente()) {
                            if (isset($r2["wp_ack"])) {
                                if ($r2["wp_ack"] == "entregado") {
                                    $entregado = true;
                                }
                                if ($r2["wp_ack"] == "leido") {
                                    $leido = true;
                                }
                            }
                        }
                    }
                    if ($entregado) {
                        $totalRecibido++;
                        $cedulasGestionadas[$row["ws_factura"]] = "CONTACTO DIRECTO";
                        $gestionadoPorCampania[$row["ws_campaniaNombre"]] = isset($gestionadoPorCampania[$row["ws_campaniaNombre"]]) ? $gestionadoPorCampania[$row["ws_campaniaNombre"]] + 1 : 1;
                    }
                    if ($leido) {
                        $totalLeido++;
                        $cedulasGestionadas[$row["ws_factura"]] = "CONTACTO DIRECTO";
                        //$gestionadoPorCampania[$row["ws_campaniaNombre"]] = isset($gestionadoPorCampania[$row["ws_campaniaNombre"]]) ? $gestionadoPorCampania[$row["ws_campaniaNombre"]] + 1 : 1;
                    }
                    if (!$entregado && !$leido) {
                        $totalNoLeido++;
                        $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                    }
                } else {
                    $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                    $totalNoLeido++;
                }
            } else if (strpos($row['ws_estadoEnvio'], 'ERROR') !== FALSE) {
                $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                $totalError++;
            } else {
                //$cedulasGestionadas[$row["ws_cedula"]] = "SIN CONTACTO";
                $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                //$totalNoRecibidos++;
                $totalNoEnviado++;
            }
            $campanias[$row["ws_campaniaNombre"]] = $row["ws_campaniaNombre"];
        }
        $respuesta["cuadros"]["numeroRecibidos"]["porcentaje"] = formatea_numero(($totalRecibido + $totalNoLeido * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroRecibidos"]["total"] = $totalRecibido + $totalNoLeido;
        $respuesta["cuadros"]["numeroRecibidos"]["extra"] = $totalRecibido . " con confirmación (doble marca azul)\r\n" . $totalNoLeido . " sin confirmación";

        $respuesta["cuadros"]["numeroEnviados"]["porcentaje"] = formatea_numero(($totalEnviado * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroEnviados"]["total"] = $totalEnviado;
        $respuesta["cuadros"]["numeroPendientesEnvio"]["porcentaje"] = formatea_numero(($totalNoEnviado * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroPendientesEnvio"]["total"] = $totalNoEnviado;

        $respuesta["cuadros"]["numeroLeido"]["porcentaje"] = formatea_numero(($totalLeido * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroLeido"]["total"] = $totalLeido;
        $respuesta["cuadros"]["numeroRepondidos"]["porcentaje"] = formatea_numero(($totalRespondido * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroRepondidos"]["total"] = $totalRespondido;

        $respuesta["cuadros"]["numeroError"]["porcentaje"] = formatea_numero(($totalError * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroError"]["total"] = $totalError;

        // $respuesta["cuadros"]["numeroNoLeido"]["porcentaje"] = formatea_numero(($totalNoLeido * 100 / $cursor), 2, ",", ".");
        // $respuesta["cuadros"]["numeroNoLeido"]["total"] = $totalNoLeido;
        // $respuesta["cuadros"]["numeroNoRecibidos"]["porcentaje"] = formatea_numero(($totalNoRecibidos * 100 / $cursor), 2, ",", ".");
        // $respuesta["cuadros"]["numeroNoRecibidos"]["total"] = $totalNoRecibidos;
        $porcentajeAvance = round((($totalEnviado * 100) / $cursor), 1);
        $respuesta["porcentajeAvance"] = $porcentajeAvance;
    }
    $respuesta["campanias"] = array_values($campanias);
    $respuesta["gestionadas"] = $cedulasGestionadas;
    $respuesta["gestionadasPorCampania"] = $gestionadoPorCampania;
    $respuesta["totalIntensidad"] = $totalRecibido + $totalNoLeido; //no leido porque a la final si se envio
    return $respuesta;
}

function obtenerPagos($condicion)
{
    // print_h($condicion);
    $mongo = new MYMONGODB();
    $pagos = $mongo->buscar("cbPagos", $condicion);
    $losPagos = [];
    while ($pago = $mongo->siguientex()) {
        if (!isset($losPagos[$pago["pagos_numFactura"]])) {
            $losPagos[$pago["pagos_numFactura"]] = floatval($pago["pagos_monto"]);
        } else {
            $losPagos[$pago["pagos_numFactura"]] += floatval($pago["pagos_monto"]);
        }
    }
    return $losPagos;
}

function obtenerTotalesGestion($desde, $hasta, $parametrosTotales, $intensidades, $pagos, $compromisos, $filtroCartera)
{
    $resultado = [
        "gestion" => [
            [
                "nombre" => "Cartera gestionada",
                "total" => 0,
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
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Cartera no gestionada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Compromisos de pago registrados",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Cartera gestionada pagada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "Sin datos"
            ],
            [
                "nombre" => "Cartera no gestionada pagada",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "Sin datos"
            ],
            // [
            //     "nombre" => "Efectividad",
            //     "porcentaje" => "0,0",
            //     "recaudo" => "0,0",
            //     "recaudoTooltip" => "$0,0 de $0,0",
            //     "capital" => "0,0",
            //     "capitalTooltip" => "$0,00 de $0,00"
            // ]
        ],
        "tipificacion" => [
            [
                "nombre" => "Contacto directo",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Contacto indirecto",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Sin contacto",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ]
        ],
        "intensidad" => [
            [
                "nombre" => "Intensidad",
                "total" => 0,
                "tooltip" => ""
            ],
            // [
            //     "nombre" => "Intensidad llamada telefónica (agentes virtuales)",
            //     "total" => 0,
            //     "tooltip" => ""
            // ],
            // [
            //     "nombre" => "Intensidad correo electrónico",
            //     "total" => 0,
            //     "tooltip" => ""
            // ],
            // [
            //     "nombre" => "Intensidad WhatsApp",
            //     "total" => 0,
            //     "tooltip" => ""
            // ],
        ],
        /* "cumplimiento" => [
            [
                "nombre" => "Normalizados premora",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Recupero premora",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Mantuvieron en preventiva",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Recupero preventiva",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Cumplen reestructuración",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],

            [
                "nombre" => "Recupero reestructuración",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
        ]*/
    ];

    // if (count($pagos) == 0) {
    //     unset($resultado["gestion"][4]);
    //     unset($resultado["gestion"][5]);
    // }

    //gestiones de av, correo, ws
    $cedulasGestionadas = [];
    foreach ($parametrosTotales as $tipo => $cedulas) {
        foreach ($cedulas as $cedula => $tipificacion) {
            if (!isset($cedulasGestionadas[$cedula])) {
                $cedulasGestionadas[$cedula] = $tipificacion;
            } else {
                if ($cedulasGestionadas[$cedula] != "CONTACTO DIRECTO") {
                    if ($tipificacion == "CONTACTO INDIRECTO" && $cedulasGestionadas[$cedula] == "SIN CONTACTO") {
                        $cedulasGestionadas[$cedula] = $tipificacion;
                    } else {
                        $cedulasGestionadas[$cedula] = $tipificacion;
                    }
                }
            }
        }
    }

    //obtener total cargado
    $cedulasEnCarga = obtenerCedulasEnCarga2($desde, $hasta, $filtroCartera);
    // $cedulasEnCarga = obtenerCedulasEnCarga($desde, $hasta, $filtroCartera);

    // $i = 0;
    // foreach ($cedulasEnCarga as $key => $value) {
    //     print_h($key);
    //     print_h($value);
    //     $i++;
    //     if ($i == 10) {
    //         break;
    //     }
    // }

    //cedulas que ya pagaron
    $cedulasGestionadasPagadas = 0;
    $cedulasNoGestionadasPagadas = 0;
    $totalMonto = 0;
    $totalMontoPagado = 0;
    $totalMontoGestion = 0;
    //por calificicion
    $registrosPremora = [
        "numeroPagado" => 0,
        "monto" => 0,
        "total" => 0,
        "mantienen" => 0
    ];
    $registrosPreventiva = [
        "numeroPagado" => 0,
        "monto" => 0,
        "total" => 0,
        "mantienen" => 0
    ];
    $registrosReestructura = [
        "numeroPagado" => 0,
        "monto" => 0,
        "total" => 0,
        "mantienen" => 0
    ];
    foreach ($cedulasEnCarga as $cedula) {
        $totalMonto += $cedula[4];
        if (isset($pagos[$cedula[0]])) {
            if (isset($cedulasGestionadas[$cedula[0]])) {
                $totalMontoPagado += $cedula[4];
                $cedulasGestionadasPagadas++;
            } else {
                $cedulasNoGestionadasPagadas++;
            }

            if ($cedula[3] == "PREMORA") {
                $registrosPremora["numeroPagado"]++;
                $registrosPremora["monto"] += $cedula[4];
                if (count($cedula[5]) == 1) {
                    $registrosPremora["mantienen"]++;
                }
            }
            if ($cedula[3] == "PREVENTIVA") {
                $registrosPreventiva["numeroPagado"]++;
                $registrosPreventiva["monto"] += $cedula[4];
                if (count($cedula[5]) == 1) {
                    $registrosPreventiva["mantienen"]++;
                }
            }
            if ($cedula[3] == "REESTRUCTURA") {
                $registrosReestructura["numeroPagado"]++;
                $registrosReestructura["monto"] += $cedula[4];
                if (count($cedula[5]) == 1) {
                    $registrosReestructura["mantienen"]++;
                }
            }
        }
        if ($cedula[3] == "PREMORA") {
            $registrosPremora["total"]++;
        }
        if ($cedula[3] == "PREVENTIVA") {
            $registrosPreventiva["total"]++;
        }
        if ($cedula[3] == "REESTRUCTURA") {
            $registrosReestructura["total"]++;
        }
    }

    //hago calculos
    $cedulasSinGestion = 0;
    $cedulasContactoDirecto = 0;
    $cedulasContactoIndirecto = 0;
    $cedulasSinContacto = 0;

    if (count($cedulasEnCarga) > 0) {
        foreach ($cedulasEnCarga as $cedula) {
            if (array_key_exists($cedula[0], $cedulasGestionadas)) {
                if ($cedulasGestionadas[$cedula[0]] == "CONTACTO DIRECTO") {
                    $cedulasContactoDirecto++;
                }
                if ($cedulasGestionadas[$cedula[0]] == "CONTACTO INDIRECTO") {
                    $cedulasContactoIndirecto++;
                }
                if ($cedulasGestionadas[$cedula[0]] == "SIN CONTACTO") {
                    $cedulasSinContacto++;
                }
                $totalMontoGestion += $cedula[4];
            } else {
                $cedulasSinGestion++;
            }
        }
    } else {
        foreach ($cedulasGestionadas as $key => $value) {
            if ($value == "CONTACTO DIRECTO") {
                $cedulasContactoDirecto++;
            }
            if ($value == "CONTACTO INDIRECTO") {
                $cedulasContactoIndirecto++;
            }
            if ($value == "SIN CONTACTO") {
                $cedulasSinContacto++;
            }
        }
    }

    $total = count($cedulasEnCarga);
    $totalGestionado = $cedulasContactoDirecto + $cedulasContactoIndirecto + $cedulasSinContacto;
    $totalNoGestionado = abs($total - $totalGestionado);
    $totalContactado = $cedulasContactoDirecto + $cedulasContactoIndirecto;
    $sinGestion = $total > 0 ? ($total - $totalGestionado) : 0;

    if ($total > 0) {
        //intensidad
        // $resultado["intensidad"][0]["total"] = formatea_numero(round(isset($intensidades["llamadas_av"]) ? ($intensidades["llamadas_av"] / $total) : 0, 2), 2, ",", ".");
        // $resultado["intensidad"][1]["total"] = formatea_numero(round(isset($intensidades["correo"]) ? ($intensidades["correo"] / $total) : 0, 2), 2, ",", ".");
        // $resultado["intensidad"][2]["total"] = formatea_numero(round(isset($intensidades["whatsapp"]) ? ($intensidades["whatsapp"] / $total) : 0, 2), 2, ",", ".");
        $resultado["intensidad"][0]["total"] = formatea_numero(round(isset($intensidades["todo"]) ? ($intensidades["todo"] / $totalGestionado) : 0, 2), 2, ",", ".");
        $intLLamadas = formatea_numero(round(isset($intensidades["llamadas_av"]) ? ($intensidades["llamadas_av"] / $totalGestionado) : 0, 2), 2, ",", ".");
        $intCorreo = formatea_numero(round(isset($intensidades["correo"]) ? ($intensidades["correo"] / $totalGestionado) : 0, 2), 2, ",", ".");
        $intWs = formatea_numero(round(isset($intensidades["whatsapp"]) ? ($intensidades["whatsapp"] / $totalGestionado) : 0, 2), 2, ",", ".");
        $resultado["intensidad"][0]["tooltip"] = [
            $intLLamadas . " Intensidad telefonía agente virtual",
            $intCorreo . " Intensidad correo electrónico",
            $intWs . " Intensidad WhatsApp"
        ];
        // $resultado["intensidad"][1]["total"] = formatea_numero(round(isset($intensidades["llamadas_av"]) ? ($intensidades["llamadas_av"] / $totalGestionado) : 0, 2), 2, ",", ".");
        // $resultado["intensidad"][2]["total"] = formatea_numero(round(isset($intensidades["correo"]) ? ($intensidades["correo"] / $totalGestionado) : 0, 2), 2, ",", ".");
        // $resultado["intensidad"][3]["total"] = formatea_numero(round(isset($intensidades["whatsapp"]) ? ($intensidades["whatsapp"] / $totalGestionado) : 0, 2), 2, ",", ".");
        //cartera gestionada
        $resultado["gestion"][0]["total"] = $totalGestionado;
        $resultado["gestion"][0]["porcentaje"] = formatea_numero(($totalGestionado * 100) / $total, 2, ",", ".");
        $resultado["gestion"][0]["tooltip"] = $totalGestionado . " de " . $total;
        $resultado["gestion"][0]["monto"] = "$" . abreviaNumero($totalMontoGestion, 1) . " de $" . abreviaNumero($totalMonto, 1);
        $resultado["gestion"][0]["montoTooltip"] = "$" . formatea_numero($totalMontoGestion, 2, ",", ".") . " de $" . formatea_numero($totalMonto, 2, ",", ".");
        //cartera gestionada con contacto
        $resultado["gestion"][1]["total"] = $totalContactado;
        $resultado["gestion"][1]["porcentaje"] = formatea_numero(($totalContactado * 100) / $total, 2, ",", ".");
        $resultado["gestion"][1]["tooltip"] = $totalContactado . " de " . $total;
        //cartera no gestionada
        $resultado["gestion"][2]["total"] = $sinGestion;
        $resultado["gestion"][2]["porcentaje"] = formatea_numero(($sinGestion * 100) / $total, 2, ",", ".");
        $resultado["gestion"][2]["tooltip"] = $sinGestion . " de " . $total;
        //cumplimiento
        $resultado["cumplimiento"][0]["total"] = $registrosPremora["mantienen"];
        $resultado["cumplimiento"][0]["porcentaje"] = $registrosPremora["total"] > 0 ? formatea_numero(($registrosPremora["mantienen"] * 100 / $registrosPremora["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][0]["tooltip"] = $registrosPremora["mantienen"] . " de " . $registrosPremora["total"];
        $resultado["cumplimiento"][1]["total"] = $registrosPremora["numeroPagado"];
        $resultado["cumplimiento"][1]["porcentaje"] = $registrosPremora["total"] > 0 ? formatea_numero(($registrosPremora["numeroPagado"] * 100 / $registrosPremora["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][1]["tooltip"] = "$" . abreviaNumero($registrosPremora["monto"], 1);

        $resultado["cumplimiento"][2]["total"] = $registrosPreventiva["mantienen"];
        $resultado["cumplimiento"][2]["porcentaje"] = $registrosPreventiva["total"] > 0 ? formatea_numero(($registrosPreventiva["mantienen"] * 100 / $registrosPreventiva["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][2]["tooltip"] = $registrosPreventiva["mantienen"] . " de " . $registrosPreventiva["total"];
        $resultado["cumplimiento"][3]["total"] = $registrosPreventiva["numeroPagado"];
        $resultado["cumplimiento"][3]["porcentaje"] = $registrosPreventiva["total"] > 0 ? formatea_numero(($registrosPreventiva["numeroPagado"] * 100 / $registrosPreventiva["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][3]["tooltip"] = "$" . abreviaNumero($registrosPreventiva["monto"], 1);

        $resultado["cumplimiento"][4]["total"] = $registrosReestructura["mantienen"];
        $resultado["cumplimiento"][4]["porcentaje"] = $registrosReestructura["total"] > 0 ? formatea_numero(($registrosReestructura["mantienen"] * 100 / $registrosReestructura["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][4]["tooltip"] = $registrosReestructura["mantienen"] . " de " . $registrosReestructura["total"];
        $resultado["cumplimiento"][5]["total"] = $registrosReestructura["numeroPagado"];
        $resultado["cumplimiento"][5]["porcentaje"] = $registrosReestructura["total"] > 0 ? formatea_numero(($registrosReestructura["numeroPagado"] * 100 / $registrosReestructura["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][5]["tooltip"] = "$" . abreviaNumero($registrosReestructura["monto"], 1);
    }
    if ($totalGestionado > 0) {
        //contacto directo
        $resultado["tipificacion"][0]["total"] = $cedulasContactoDirecto;
        $resultado["tipificacion"][0]["porcentaje"] = formatea_numero(($cedulasContactoDirecto * 100) / $totalGestionado, 2, ",", ".");
        $resultado["tipificacion"][0]["tooltip"] = $cedulasContactoDirecto . " de " . $totalGestionado;
        //contacto indirecto
        $resultado["tipificacion"][1]["total"] = $cedulasContactoIndirecto;
        $resultado["tipificacion"][1]["porcentaje"] = formatea_numero(($cedulasContactoIndirecto * 100) / $totalGestionado, 2, ",", ".");
        $resultado["tipificacion"][1]["tooltip"] = $cedulasContactoIndirecto . " de " . $totalGestionado;
        //sin contacto
        $resultado["tipificacion"][2]["total"] = $cedulasSinContacto;
        $resultado["tipificacion"][2]["porcentaje"] = formatea_numero(($cedulasSinContacto * 100) / $totalGestionado, 2, ",", ".");
        $resultado["tipificacion"][2]["tooltip"] = $cedulasSinContacto . " de " . $totalGestionado;
        //compromisos pago
        $resultado["gestion"][3]["total"] = count($compromisos);
        if (count($compromisos) > 0 && $totalContactado > 0) {
            $resultado["gestion"][3]["porcentaje"] = formatea_numero((count($compromisos) * 100) / $totalContactado, 2, ",", ".");
        }
        $resultado["gestion"][3]["tooltip"] = count($compromisos) . " de " . $totalContactado;
        if (count($pagos) > 0) {
            //pagos
            $resultado["gestion"][4]["total"] = $cedulasGestionadasPagadas;
            $resultado["gestion"][4]["porcentaje"] = formatea_numero(($cedulasGestionadasPagadas * 100) / $totalGestionado, 2, ",", ".");
            $resultado["gestion"][4]["tooltip"] = $cedulasGestionadasPagadas . " de " . $totalGestionado;
            $resultado["gestion"][4]["monto"] = "$" . abreviaNumero($totalMontoPagado, 1) . " de $" . abreviaNumero($totalMontoGestion, 1);
            $resultado["gestion"][4]["montoTooltip"] = "$" . formatea_numero($totalMontoPagado, 2, ",", ".") . " de $" . formatea_numero($totalMontoGestion, 2, ",", ".");

            $resultado["gestion"][5]["total"] = $cedulasNoGestionadasPagadas;
            $resultado["gestion"][5]["porcentaje"] = formatea_numero(($cedulasNoGestionadasPagadas * 100) / $totalNoGestionado, 2, ",", ".");
            $resultado["gestion"][5]["tooltip"] = $cedulasNoGestionadasPagadas . " de " . $totalNoGestionado;

            // $resultado["gestion"][6]["porcentaje"] = formatea_numero(($totalMontoPagado * 100) / $totalMonto, 2, ",", ".");
            // $resultado["gestion"][6]["recaudo"] = "Recaudo : $" . abreviaNumero($totalMontoPagado, 1);
            // $resultado["gestion"][6]["recaudoTooltip"] = "Recaudo: $" . formatea_numero($totalMontoPagado, 2, ",", ".");
            // $resultado["gestion"][6]["capital"] = "Capital inicial: $" . abreviaNumero($totalMonto, 1);
            // $resultado["gestion"][6]["capitalTooltip"] = "Capital inicial: $" . formatea_numero($totalMonto, 2, ",", ".");
        }
    }

    return $resultado;
}


//TOTALES GESTION NUEVOS
function obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $intensidades)
{
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
        /*"cumplimiento" => [
            [
                "nombre" => "Normalizados premora",f
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Recupero premora",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Mantuvieron en preventiva",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Recupero preventiva",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
            [
                "nombre" => "Cumplen reestructuración",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],

            [
                "nombre" => "Recupero reestructuración",
                "total" => 0,
                "porcentaje" => "0,0",
                "tooltip" => "0 de 0"
            ],
        ]*/
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
                    // Total gestionados
                    'totalGestionados' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 1]],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital gestionado
                    'capitalGestionado' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 1]],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Total sin gestión
                    'totalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 0]],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital sin gestión
                    'capitalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 0]],
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                        [
                                            '$in' => [
                                                '$cubAG_tipificacion2',
                                                [
                                                    'PAGA EN FECHA',
                                                    'ABONO CUOTA',
                                                    'CONFIRMACION DE PAGO',
                                                    'NEGOCIACION EN CURSO ALIVIO'
                                                ]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                        [
                                            '$in' => [
                                                '$cubAG_tipificacion2',
                                                [
                                                    'PAGA EN FECHA',
                                                    'ABONO CUOTA',
                                                    'CONFIRMACION DE PAGO',
                                                    'NEGOCIACION EN CURSO ALIVIO'
                                                ]
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
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
                                        ['$eq' => ['$cubAG_gestionada', 1]],
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
                                        ['$eq' => ['$cubAG_gestionada', 0]],
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
                                        ['$eq' => ['$cubAG_gestionada', 0]],
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

        $condicionCubGestion = [
            '$and' => [
                $condicionCubGestion,
                [
                    'cubGC_tipificacion_respuesta2' => [
                        '$nin' => ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado']
                    ]
                ]
            ]
        ];


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

        $pagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$gte');

        $totalPagadas = $pagadas["totalPagos"];
        $totalGestionesPagadas = $pagadas["totalGestiones"];
        $totalTelefonicaPagadas = $pagadas["telefonica"];
        $totalWhatsappPagadas = $pagadas["whatsapp"];
        $totalEmailPagadas = $pagadas["email"];

        $noPagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$lt');

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


        /*   $resultado["intensidad"][0]["total"] = formatea_numero(round(isset($intensidades["todo"]) && $total > 0 ? ($intensidades["todo"] / $total) : 0, 2), 2, ",", ".");
        $intLLamadas = formatea_numero(round(isset($intensidades["llamadas_av"]) && $total > 0 ? ($intensidades["llamadas_av"] / $total) : 0, 2), 2, ",", ".");
        $intCorreo = formatea_numero(round(isset($intensidades["correo"]) && $total > 0 ? ($intensidades["correo"] / $total) : 0, 2), 2, ",", ".");
        $intWs = formatea_numero(round(isset($intensidades["whatsapp"]) && $total > 0 ? ($intensidades["whatsapp"] / $total) : 0, 2), 2, ",", ".");
        $resultado["intensidad"][0]["tooltip"] = [
            $intLLamadas, //. " Intensidad telefonía agente virtual",
            $intCorreo, //. " Intensidad correo electrónico",
            $intWs //. " Intensidad WhatsApp"
        ];*/


        //cartera gestionada
        $resultado["gestion"][0]["total"] = formatea_numero($total, 0, ",", ".");
        $resultado["gestion"][0]["montoTotal"] = " $" . formatea_numero($totalMonto, 2, ",", ".");
        $resultado["gestion"][0]["porcentaje"] = $total > 0 ? formatea_numero(($totalGestionado * 100) / $total, 2, ",", ".") : 0;
        $resultado["gestion"][0]["tooltip"] = formatea_numero($totalGestionado, 0, ",", ".") . " de " . formatea_numero($total, 0, ",", ".");
        $resultado["gestion"][0]["monto"] = "$" . abreviaNumero($totalMontoGestion, 1) . " de $" . abreviaNumero($totalMonto, 1);
        $resultado["gestion"][0]["montoTooltip"] = "$" . formatea_numero($totalMontoGestion, 2, ",", ".") . " de $" . formatea_numero($totalMonto, 2, ",", ".");
        //cartera gestionada con contacto
        $resultado["gestion"][1]["total"] = $totalContactado;
        $resultado["gestion"][1]["porcentaje"] = $totalGestionado > 0 ? formatea_numero(($totalContactado * 100) / $totalGestionado, 2, ",", ".") : 0;
        $resultado["gestion"][1]["tooltip"] = formatea_numero($totalContactado, 0, ",", ".") . " de " . formatea_numero($totalGestionado, 0, ",", ".");
        $resultado["gestion"][1]["monto"] = "$" . abreviaNumero($totalMontoContactadaContacto, 1) . " de $" . abreviaNumero($totalMontoGestion, 1);
        $resultado["gestion"][1]["montoTooltip"] = "$" . formatea_numero($totalMontoContactadaContacto, 2, ",", ".") . " de $" . formatea_numero($totalMontoGestion, 2, ",", ".");
        //cartera no gestionada
        $resultado["gestion"][2]["total"] = $sinGestion;
        $resultado["gestion"][2]["porcentaje"] = $total > 0 ? formatea_numero(($sinGestion * 100) / $total, 2, ",", ".") : 0;
        $resultado["gestion"][2]["tooltip"] = formatea_numero($sinGestion, 0, ",", ".") . " de " . formatea_numero($total, 0, ",", ".");
        $resultado["gestion"][2]["monto"] = "$" . abreviaNumero($totalMontoSinGestion, 1) . " de $" . abreviaNumero($totalMonto, 1);
        $resultado["gestion"][2]["montoTooltip"] = "$" . formatea_numero($totalMontoSinGestion, 2, ",", ".") . " de $" . formatea_numero($totalMonto, 2, ",", ".");
        //cumplimiento
        $resultado["cumplimiento"][0]["total"] = 0; //$registrosPremora["mantienen"];
        $resultado["cumplimiento"][0]["porcentaje"] = 0; //$registrosPremora["total"] > 0 ? formatea_numero(($registrosPremora["mantienen"] * 100 / $registrosPremora["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][0]["tooltip"] = 0; //$registrosPremora["mantienen"] . " de " . $registrosPremora["total"];
        $resultado["cumplimiento"][1]["total"] = 0; //$registrosPremora["numeroPagado"];
        $resultado["cumplimiento"][1]["porcentaje"] = 0; //$registrosPremoraZ["total"] > 0 ? formatea_numero(($registrosPremora["numeroPagado"] * 100 / $registrosPremora["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][1]["tooltip"] = 0; // "$" . abreviaNumero($registrosPremora["monto"], 1);

        $resultado["cumplimiento"][2]["total"] = 0; //$registrosPreventiva["mantienen"];
        $resultado["cumplimiento"][2]["porcentaje"] = 0; // $registrosPreventiva["total"] > 0 ? formatea_numero(($registrosPreventiva["mantienen"] * 100 / $registrosPreventiva["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][2]["tooltip"] = 0; // $registrosPreventiva["mantienen"] . " de " . $registrosPreventiva["total"];
        $resultado["cumplimiento"][3]["total"] = 0; //$registrosPreventiva["numeroPagado"];
        $resultado["cumplimiento"][3]["porcentaje"] = 0; // $registrosPreventiva["total"] > 0 ? formatea_numero(($registrosPreventiva["numeroPagado"] * 100 / $registrosPreventiva["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][3]["tooltip"] = 0; //"$" . abreviaNumero($registrosPreventiva["monto"], 1);

        $resultado["cumplimiento"][4]["total"] = 0; // $registrosReestructura["mantienen"];
        $resultado["cumplimiento"][4]["porcentaje"] = 0; //$registrosReestructura["total"] > 0 ? formatea_numero(($registrosReestructura["mantienen"] * 100 / $registrosReestructura["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][4]["tooltip"] = 0; //$registrosReestructura["mantienen"] . " de " . $registrosReestructura["total"];
        $resultado["cumplimiento"][5]["total"] = 0; // $registrosReestructura["numeroPagado"];
        $resultado["cumplimiento"][5]["porcentaje"] = 0; //$registrosReestructura["total"] > 0 ? formatea_numero(($registrosReestructura["numeroPagado"] * 100 / $registrosReestructura["total"]), 2, ",", ".") : "0,0";
        $resultado["cumplimiento"][5]["tooltip"] = 0; //"$" . abreviaNumero($registrosReestructura["monto"], 1);
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

function generarExcel($data, $usaGestiones, $archivoExcelNombre)
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
        $cabeceras = array(
            "Factura",
            "Ciclo",
            "Capital"
        );
    } else {
        $cabeceras = array(
            "Factura",
            "Ciclo",
            "Capital",
            "Canal",
            "Campaña",
            "Tipificación 1",
            "Tipificación 2",
            "Fecha Gestión"
        );
    }

    $filasExcel = array();
    $i = 0;

    $filasExcel["titulo_" . $i] = $cabeceras;
    $i++;

    // FILAS
    foreach ($data as $fila) {

        if (!$usaGestiones) {

            // ASIGNACIONES
            $nuevaFila = [
                $fila['cubAG_numFactura'] ?? '',
                $fila['cubAG_ciclo'] ?? '',
                $fila['cubAG_capitalActual'] ?? ''

            ];
        } else {

            // GESTIONES
            $nuevaFila = [
                $fila['cubGC_numFactura'] ?? '',
                $fila['cubGC_ciclo'] ?? '',
                $fila['cubGC_capitalActual'] ?? '',
                $fila['cubGC_canal'] ?? '',
                $fila['cubGC_campaniaNombre'] ?? '',
                $fila['cubGC_tipificacion_respuesta1'] ?? '',
                $fila['cubGC_tipificacion_respuesta2'] ?? '',
                isset($fila['cubGC_fechaGestion'])
                    ? date('Y-m-d H:i:s', $fila['cubGC_fechaGestion'])
                    : ''
            ];
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
    $rutaRelativa = "/canalesMasivos/reportesExcel/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();

    return $rutaRelativa;
}


function obtenerGestionesPorPago($condicionCubAsignacion, $operadorExpr)
{
    $mdb = new MYMONGODB();

    $condicionCubAsignacion["cubAG_gestionada"] = (int)1;
    $condicionCubAsignacion['$expr'] = [
        $operadorExpr => ['$cubAG_montoTotalPago', '$cubAG_deudaNetaActual']
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
        $resultado["totalGestiones"] = (int)$row['totalGestionesPagadas'];
        $resultado["telefonica"] = (int)$row['totalTelefonicaPagada'];
        $resultado["whatsapp"] = (int)$row['totalWhatsappPagada'];
        $resultado["email"] = (int)$row['totalEmailPagada'];
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



function obtenerCedulasEnCarga($desde, $hasta, $filtroCartera)
{
    //obtener total cargado
    $mongo = new MYMONGODB();
    $cedulasEnCarga = [];
    $historial = [];

    for ($i = $desde; $i <= $desde; $i += 86400) {
        $dinamico = date("dmY", $i);
        $dia = date("w", $i);
        if ($dia == 1) {
            $dinamico = date("dmY", ($i - 172800)); //hace dos dias            
        }
        if ($filtroCartera != null && $filtroCartera != "todo") {
            $condicioncarga = ["carga_" . $dinamico => ['$exists' => true], 'carteraEtl_carteraId' => (int) $filtroCartera];
        } else {
            $condicioncarga = ["carga_" . $dinamico => ['$exists' => true]];
        }
        // print_h($condicioncarga);
        $totalCarga = $mongo->buscar("cbCargaEtlDetalleCargas", $condicioncarga);
        // print_h($totalCarga);
        $nuevos = 0;
        $existentes = 0;
        while ($carga = $mongo->siguientex()) {

            $nombre = $carga["carteraEtl_primerNombre"] . " " . $carga["carteraEtl_segundoNombre"] . " " . $carga["carteraEtl_apellidoPaterno"] . " " . $carga["carteraEtl_apellidoMaterno"];
            $calificacion = "N/D";
            $deuda = 0;
            $producto = 0;            //calculo en que calificacion se encuentra
            if (isset($carga["detalle_" . $dinamico])) {
                $deuda = $carga["detalle_" . $dinamico]["deudaNeta"] ?? $deuda;
                $dias = isset($carga["detalle_" . $dinamico]["diasMora"]) ? intval($carga["detalle_" . $dinamico]["diasMora"]) : "";
                if ($dias != "") {
                    $producto = $carga["detalle_" . $dinamico]["producto"] ?? "";
                    if ($dias > 0) {
                        if (strpos($producto, "REESTRUCTURACI") !== false) {
                            $calificacion = "REESTRUCTURA";
                            // } else if (strpos($producto, "DFP") !== false) {
                            //     $calificacion = "PREMORA";
                            // } else if (strpos($producto, "NORMAL") !== false) {
                            //     $calificacion = "PREMORA";
                        } else {
                            $calificacion = "PREMORA";
                        }
                    } else {
                        $calificacion = "PREVENTIVA";
                    }
                }

                //lleno el historial de las calificaciones que ha tenido            
                if ($calificacion != "") {
                    $historial = isset($cedulasEnCarga[$carga["carteraEtl_factura"]][5]) ? $cedulasEnCarga[$carga["carteraEtl_factura"]][5] : [];
                    if (!in_array($calificacion, $historial)) {
                        $historial[$dinamico] = $calificacion;
                    }
                }

                if (isset($carga["monto_" . $dinamico]) && $deuda == 0) {
                    $deuda = $carga["monto_" . $dinamico];
                }
            }
            $fact = str_replace(["'", '"', ' '], "", trim($carga["carteraEtl_factura"]));
            //cargo en el array
            $cedulasEnCarga[$fact] = [
                $carga["carteraEtl_factura"],
                $nombre,
                $carga["carteraEtl_cedula"],
                $calificacion,
                $deuda,
                $historial
            ];
        }
        // print_h($nuevos . "n");
        // print_h($existentes . "e");
        // print_h(count($cedulasEnCarga));
    }

    return $cedulasEnCarga;
}
function obtenerCedulasEnCarga2($desde, $hasta, $filtroCartera)
{
    //obtener total cargado
    $mongo = new MYMONGODB();
    $cedulasEnCarga = [];
    $historial = [];

    $dinamico = date("dmY", $desde);
    if ($filtroCartera != null && $filtroCartera != "todo") {
        $condicioncarga = ['inicial' => (int) 1, "cargaUT" => ['$gte' => (int) $desde, '$lte' => $hasta], 'carteraId' => (int) $filtroCartera];
    } else {
        $condicioncarga = ['inicial' => (int) 1, "cargaUT" => ['$gte' => (int) $desde, '$lte' => $hasta]];
    }
    $totalCarga = $mongo->buscar("cbCargaDetallePacifico", $condicioncarga);
    $nuevos = 0;
    $existentes = 0;
    while ($carga = $mongo->siguiente()) {

        $nombre = $carga["primerNombre"] . " " . $carga["segundoNombre"] . " " . $carga["apellidoPaterno"] . " " . $carga["apellidoMaterno"];
        $calificacion = "N/D";
        $deuda = 0;
        $producto = 0;            //calculo en que calificacion se encuentra
        $deuda = $carga["deudaNeta"] ?? $deuda;
        $dias = isset($carga["diasMora"]) ? intval($carga["diasMora"]) : "";
        if ($dias != "") {
            $producto = $carga["producto"] ?? "";
            if ($dias > 0) {
                if (strpos($producto, "REESTRUCTURACI") !== false) {
                    $calificacion = "REESTRUCTURA";
                    // } else if (strpos($producto, "DFP") !== false) {
                    //     $calificacion = "PREMORA";
                    // } else if (strpos($producto, "NORMAL") !== false) {
                    //     $calificacion = "PREMORA";
                } else {
                    $calificacion = "PREMORA";
                }
            } else {
                $calificacion = "PREVENTIVA";
            }
        }

        //lleno el historial de las calificaciones que ha tenido            
        if ($calificacion != "") {
            $historial = isset($cedulasEnCarga[$carga["factura"]][5]) ? $cedulasEnCarga[$carga["factura"]][5] : [];
            if (!in_array($calificacion, $historial)) {
                $historial[$dinamico] = $calificacion;
            }
        }

        if (isset($carga["monto_" . $dinamico]) && $deuda == 0) {
            $deuda = $carga["monto_" . $dinamico];
        }
        // }
        $fact = str_replace(["'", '"', ' '], "", trim($carga["factura"]));
        //cargo en el array
        $cedulasEnCarga[$fact] = [
            $carga["factura"],
            $nombre,
            $carga["cedula"],
            $calificacion,
            $deuda,
            $historial
        ];
    }
    // print_h($nuevos . "n");
    // print_h($existentes . "e");
    // print_h(count($cedulasEnCarga));
    // }

    return $cedulasEnCarga;
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