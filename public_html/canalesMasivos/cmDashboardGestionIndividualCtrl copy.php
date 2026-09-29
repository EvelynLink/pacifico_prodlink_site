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
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor"),
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

        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroPeriodo = expect_safe_html($d["filtroPeriodo"]);
        $condicion = establecerCondicionMaster();
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");
        $campo = "av_fecha";
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
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionWhatsapp["ws_fecha"] = 1756843285;
        }

        $condicionPeriodo = [];

        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }

        // NOTA: filtroPeriodo ahora llega como un arreglo de selecciones "carteraId_periodo"
        // (un ciclo distinto por cada cartera, porque el número de ciclo es propio de cada cartera).
        $periodoPorCartera = [];
        if (is_array($filtroPeriodo)) {
            foreach ($filtroPeriodo as $value) {
                if (is_string($value) && strpos($value, '_') !== false) {
                    list($carteraIdSel, $periodoSel) = explode('_', $value, 2);
                    $periodoPorCartera[intval($carteraIdSel)] = intval($periodoSel);
                }
            }
        } else if ($filtroPeriodo !== "" && $filtroPeriodo !== null && intval($filtroPeriodo) >= 0 && count($fcSel) === 1) {
            $periodoPorCartera[$fcSel[0]] = intval($filtroPeriodo);
        }

        if (count($fcSel) > 0) {
            $condicion = array_merge($condicion, construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, "av_carteraId", "av_periodo"));
            $condicionWhatsapp = array_merge($condicionWhatsapp, construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, "ws_carteraId", "ws_periodo"));

            $condicionCorreo["cem_susCarteraId"] = ['$in' => $fcSel];

            $condicionPeriodo = ['activo' => (int) 1, "cartera" => ['$in' => $fcSel]];
        }
        if (count($fcaSel) > 0) {
            $condicion["av_campaniaId"] = ['$in' => $fcaSel];
            $condicionCorreo["cem_susCampaniaId"] = ['$in' => $fcaSel];
            $condicionWhatsapp["ws_campaniaId"] = ['$in' => $fcaSel];
        }

        $mongo = new MYMONGODB();

        // NOTA: ya no se agrega la opción "Todas las carteras" como entrada seleccionable: con
        // selección múltiple, no elegir ninguna cartera ya significa "todas" (igual que en
        // reporteAgenteVirtual), así que ese ítem quedaba confuso dentro del combo de checkboxes.
        $carteras = [];
        //AV
        $options = [
            ['$match' => $condicion],
            [
                '$project' => [
                    'av_carteraId' => 1,
                    "av_carteraNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'av_carteraId' => '$av_carteraId',
                        'av_carteraNombre' => '$av_carteraNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("avProgramadas", $options);
        $carterasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_carteraId"] != "null") {
                if (!isset($carterasu[intval($row["_id"]["av_carteraId"])])) {
                    $carterasu[intval($row["_id"]["av_carteraId"])] = [
                        "id" => intval($row["_id"]["av_carteraId"]),
                        "nombre" => ($row["_id"]["av_carteraNombre"] != null ? $row["_id"]["av_carteraNombre"] : "Cartera ID: " . $row["_id"]["av_carteraId"])
                    ];
                }
            }
        }
        //correo
        $options = [
            ['$match' => $condicionCorreo],
            [
                '$project' => [
                    'cem_susCarteraId' => 1,
                    "cem_susCarteraNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'cem_susCarteraId' => '$cem_susCarteraId',
                        'cem_susCarteraNombre' => '$cem_susCarteraNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("cbEnvioMails", $options);
        $carterasc = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["cem_susCarteraId"] != "null") {
                if (!isset($carterasu[intval($row["_id"]["cem_susCarteraId"])])) {
                    $carterasu[intval($row["_id"]["cem_susCarteraId"])] = [
                        "id" => intval($row["_id"]["cem_susCarteraId"]),
                        "nombre" => ($row["_id"]["cem_susCarteraNombre"] != null ? $row["_id"]["cem_susCarteraNombre"] : "Cartera ID: " . $row["_id"]["cem_susCarteraId"])
                    ];
                }
            }
        }
        //wp
        $options = [
            ['$match' => $condicionWhatsapp],
            [
                '$project' => [
                    'ws_carteraId' => 1,
                    "ws_carteraNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'ws_carteraId' => '$ws_carteraId',
                        'ws_carteraNombre' => '$ws_carteraNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_carteraId"] != "null") {
                if (!isset($carterasu[intval($row["_id"]["ws_carteraId"])])) {
                    $carterasu[intval($row["_id"]["ws_carteraId"])] = [
                        "id" => intval($row["_id"]["ws_carteraId"]),
                        "nombre" => ($row["_id"]["ws_carteraNombre"] != null ? $row["_id"]["ws_carteraNombre"] : "Cartera ID: " . $row["_id"]["ws_carteraId"])
                    ];
                }
            }
        }
        sort($carterasu);
        $carteras = array_merge($carteras, $carterasu);

        // NOTA: igual que con carteras, se quita la opción "Todas las campañas" del combo:
        // no elegir ninguna campaña ya significa "todas".
        $campanias = [];
        //AV
        $options = [
            ['$match' => $condicion],
            [
                '$project' => [
                    'av_campaniaId' => 1,
                    "av_campaniaNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'av_campaniaId' => '$av_campaniaId',
                        'av_campaniaNombre' => '$av_campaniaNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("avProgramadas", $options);
        $campaniasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_campaniaId"] != "null") {
                if (!isset($campaniasu[intval($row["_id"]["av_campaniaId"])])) {
                    $campaniasu[intval($row["_id"]["av_campaniaId"])] = [
                        "id" => intval($row["_id"]["av_campaniaId"]),
                        "nombre" => ($row["_id"]["av_campaniaNombre"] != null ? $row["_id"]["av_campaniaNombre"] : "Campaña ID: " . $row["_id"]["av_campaniaId"])
                    ];
                }
            }
        }
        //correo
        $options = [
            ['$match' => $condicionCorreo],
            [
                '$project' => [
                    'cem_susCampaniaId' => 1,
                    "cem_susCampaniaNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'cem_susCampaniaId' => '$cem_susCampaniaId',
                        'cem_susCampaniaNombre' => '$cem_susCampaniaNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("cbEnvioMails", $options);
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["cem_susCampaniaId"] != "null") {
                if (!isset($campaniasu[intval($row["_id"]["cem_susCampaniaId"])])) {
                    $campaniasu[intval($row["_id"]["cem_susCampaniaId"])] = [
                        "id" => intval($row["_id"]["cem_susCampaniaId"]),
                        "nombre" => ($row["_id"]["cem_susCampaniaNombre"] != null ? $row["_id"]["cem_susCampaniaNombre"] : "Campaña ID: " . $row["_id"]["cem_susCampaniaId"])
                    ];
                }
            }
        }
        //wp
        $options = [
            ['$match' => $condicionWhatsapp],
            [
                '$project' => [
                    'ws_campaniaId' => 1,
                    "ws_campaniaNombre" => 1,
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'ws_campaniaId' => '$ws_campaniaId',
                        'ws_campaniaNombre' => '$ws_campaniaNombre'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_campaniaId"] != "null") {
                if (!isset($campaniasu[$row["_id"]["ws_campaniaId"]])) {
                    $campaniasu[$row["_id"]["ws_campaniaId"]] = [
                        "id" => $row["_id"]["ws_campaniaId"],
                        "nombre" => ($row["_id"]["ws_campaniaNombre"] != null ? $row["_id"]["ws_campaniaNombre"] : "Campaña ID: " . $row["_id"]["ws_campaniaId"])
                    ];
                }
            }
        }
        sort($campaniasu);
        $campanias = array_merge($campanias, $campaniasu);

        // NOTA: el "id" de cada ciclo ahora es compuesto "carteraId_periodo" (ej: "5_0"), porque el
        // número de periodo es propio de cada cartera y puede repetirse (y hasta coincidir en fecha)
        // entre carteras distintas. El nombre incluye la cartera entre paréntesis para diferenciarlos
        // en el combo, ej: "0 - 05/08/2026 (Premora)", "0 - 05/08/2026 (Vencida)".
        $periodo = [];
        $nombresCarteraCache = [];
        if (count($condicionPeriodo) > 0) {
            $mongo->buscar("control_carga_periodo", $condicionPeriodo, [], ['periodo' => 1]);
            while ($row = $mongo->siguiente()) {
                $carteraIdPeriodo = intval($row["cartera"]);
                if (isset($carterasu[$carteraIdPeriodo])) {
                    $nombreCarteraPeriodo = $carterasu[$carteraIdPeriodo]["nombre"];
                } else {
                    // La cartera tiene ciclo configurado pero no aparece en $carterasu (no tuvo actividad
                    // bajo los filtros de tiempo/campaña actuales): se busca su nombre real aparte,
                    // en vez de mostrar "Cartera ID: X".
                    if (!isset($nombresCarteraCache[$carteraIdPeriodo])) {
                        $nombresCarteraCache[$carteraIdPeriodo] = obtenerNombreCartera($carteraIdPeriodo);
                    }
                    $nombreCarteraPeriodo = $nombresCarteraCache[$carteraIdPeriodo];
                }
                $periodo[] = [
                    "id" => $carteraIdPeriodo . "_" . $row["periodo"],
                    "nombre" => (string) $row["periodo"] . ' - ' . date('d/m/Y', $row["fecha"]) . ' (' . $nombreCarteraPeriodo . ')'
                ];
            }
        }

        $json["carteras"] = $carteras;
        $json["campanias"] = $campanias;

        $json["permisos"] = $permisos;
        $json["periodos"] = $periodo;

        #endregion 
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
        // NOTA: filtroCanal ahora es un arreglo (selección múltiple). Vacío = todos los canales.
        $fcanSel = [];
        if (is_array($filtroCanal)) {
            foreach ($filtroCanal as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcanSel[] = $value;
                }
            }
        } else if ($filtroCanal != "" && $filtroCanal != "todo") {
            $fcanSel[] = $filtroCanal;
        }
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }
        // NOTA: filtroPeriodo ahora llega como un arreglo de selecciones "carteraId_periodo"
        // (un ciclo distinto por cada cartera, porque el número de ciclo es propio de cada cartera).
        // Se admite también el valor simple antiguo por compatibilidad, aplicándolo a la única cartera seleccionada.
        $periodoPorCartera = [];
        if (is_array($filtroPeriodo)) {
            foreach ($filtroPeriodo as $value) {
                if (is_string($value) && strpos($value, '_') !== false) {
                    list($carteraIdSel, $periodoSel) = explode('_', $value, 2);
                    $periodoPorCartera[intval($carteraIdSel)] = intval($periodoSel);
                }
            }
        } else if ($filtroPeriodo !== "" && $filtroPeriodo !== null && intval($filtroPeriodo) >= 0 && count($fcSel) === 1) {
            $periodoPorCartera[$fcSel[0]] = intval($filtroPeriodo);
        }
        $condicion = establecerCondicionMaster();
        $condicionCorreo = establecerCondicionMaster("cem_susCarteraId");
        $condicionWhatsapp = establecerCondicionMaster("ws_carteraId");
        $totalesWp = [];
        $totalesCorreos = [];
        $condicionPagos = establecerCondicionMaster("pagos_carteraId");
        $efectividadCapital = [];
        $json['efectividad'] = [];
        $campo = "av_fecha";
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
                    // NOTA: con selección múltiple de carteras se combina (suma) el Saldo Capital
                    // de cada cartera elegida, porque cada una puede tener su propia fecha de corte/periodo.
                    $mongo = new MYMONGODB();
                    $mongo1 = new MYMONGODB();
                    $efectividadCapital = [
                        "nombre" => "Saldo Capital",
                        "porcentaje" => "0,0",
                        "recaudo" => "0,0",
                        "recaudoTooltip" => "$0,0 de $0,0",
                        "capital" => "0,0",
                        "capitalTooltip" => "$0,00 de $0,00"
                    ];
                    $totalInicial = 0;
                    $totalRecaudado = 0;
                    $desdeMin = null;
                    if (count($fcSel) > 0) {
                        foreach ($fcSel as $carteraSel) {
                            $periodoSel = isset($periodoPorCartera[$carteraSel]) ? $periodoPorCartera[$carteraSel] : -1;
                            if ($periodoSel >= 0) {
                                $query = ['periodo' => (int) $periodoSel, 'cartera' => (int) $carteraSel, 'activo' => (int) 1, 'fecha' => (int) strtotime('first day of this month')];
                            } else {
                                $query = ['cartera' => (int) $carteraSel, 'activo' => (int) 1, 'fechaCarga' => ['$gte' => (int) strtotime('first day of this month')]];
                            }
                            $mongo->buscar('control_carga_periodo', $query, [], ['fechaCarga' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $desdeCartera = strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00");
                            $desdeMin = $desdeMin === null ? $desdeCartera : min($desdeMin, $desdeCartera);
                            $query2 = ["carteraEtl_diaCorte" => (string) date("d", $ro['fecha']), "carteraEtl_fechaCarga" => ['$gte' => strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00")], 'carteraEtl_carteraId' => (string) $carteraSel];
                            $mongo->buscar('avCargaInicial', $query2);
                            while ($row = $mongo->siguiente()) {
                                $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                                $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00"), '$lte' => (int) strtotime('last day of this month')]]);
                                if ($c > 0) {
                                    $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                                }
                            }
                        }
                        $desde = $desdeMin !== null ? $desdeMin : strtotime(date("Y-m-d") . " 00:00:00");
                    } else {
                        // Sin carteras seleccionadas no hay ciclo aplicable (el selector de ciclo requiere cartera).
                        $query = ['activo' => (int) 1, 'fechaCarga' => ['$gte' => (int) strtotime('first day of this month')]];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fechaCarga' => 1]);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00");
                        $query2 = ["carteraEtl_diaCorte" => (string) date("d", $ro['fecha']), "carteraEtl_fechaCarga" => ['$gte' => strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00")]];
                        $mongo->buscar('avCargaInicial', $query2);
                        while ($row = $mongo->siguiente()) {
                            $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                            $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00"), '$lte' => (int) strtotime('last day of this month')]]);
                            if ($c > 0) {
                                $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                            }
                        }
                    }
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    $efectividadCapital["porcentaje"] = $totalInicial > 0 ? number_format(($totalRecaudado / $totalInicial) * 100, 2, ',', '') : "0,0";
                    $efectividadCapital["recaudo"] = abreviaNumero($totalRecaudado, 1);
                    $efectividadCapital["recaudoTooltip"] = formatea_numero($totalRecaudado, 2, ',', '.');
                    $efectividadCapital["capital"] = abreviaNumero($totalInicial, 1);
                    $efectividadCapital["capitalTooltip"] = formatea_numero($totalInicial, 2, ',', '.');
                    $json['efectividad'] = $efectividadCapital;
                    break;
                case 'asigAnterior':
                    // NOTA: con selección múltiple de carteras se combina (suma) el Saldo Capital de cada una.
                    $mongo = new MYMONGODB();
                    $mongo1 = new MYMONGODB();
                    $desde = strtotime(date('Y-m-d', strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");

                    $efectividadCapital = [
                        "nombre" => "Saldo Capital",
                        "porcentaje" => "0,0",
                        "recaudo" => "0,0",
                        "recaudoTooltip" => "$0,0 de $0,0",
                        "capital" => "0,0",
                        "capitalTooltip" => "$0,00 de $0,00"
                    ];
                    $totalInicial = 0;
                    $totalRecaudado = 0;

                    if (count($fcSel) > 0) {
                        foreach ($fcSel as $carteraSel) {
                            $periodoSel = isset($periodoPorCartera[$carteraSel]) ? $periodoPorCartera[$carteraSel] : -1;
                            if ($periodoSel >= 0) {
                                $query = ['periodo' => (int) $periodoSel, 'cartera' => (int) $carteraSel, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                            } else {
                                $query = ['cartera' => (int) $carteraSel, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                            }
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $query2 = ["carteraEtl_fechaCarga" => ['$gte' => $desde, '$lte' => strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . " + 3 days")) . " 00:00:00")], 'carteraEtl_carteraId' => (string) $carteraSel];
                            $mongo->buscar('avCargaInicial', $query2);
                            while ($row = $mongo->siguiente()) {
                                $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                                $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => $desde, '$lte' => $hasta]]);
                                if ($c > 0) {
                                    $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                                }
                            }
                        }
                    } else {
                        // Sin carteras seleccionadas no hay ciclo aplicable (el selector de ciclo requiere cartera).
                        $query = ['fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $query2 = ["carteraEtl_fechaCarga" => ['$gte' => $desde, '$lte' => strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . " + 3 days")) . " 00:00:00")]];
                        $mongo->buscar('avCargaInicial', $query2);
                        while ($row = $mongo->siguiente()) {
                            $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                            $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => $desde, '$lte' => $hasta]]);
                            if ($c > 0) {
                                $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                            }
                        }
                    }
                    $efectividadCapital["porcentaje"] = $totalInicial > 0 ? number_format(($totalRecaudado / $totalInicial) * 100, 2, ',', '') : 0;
                    $efectividadCapital["recaudo"] = abreviaNumero($totalRecaudado, 1);
                    $efectividadCapital["recaudoTooltip"] = formatea_numero($totalRecaudado, 2, ',', '.');
                    $efectividadCapital["capital"] = abreviaNumero($totalInicial, 1);
                    $efectividadCapital["capitalTooltip"] = formatea_numero($totalInicial, 2, ',', '.');
                    $json['efectividad'] = $efectividadCapital;
                    break;
                case 'rango':
                    // NOTA: con selección múltiple de carteras se combina (suma) el Saldo Capital de cada una.
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    $mongo = new MYMONGODB();
                    $mongo1 = new MYMONGODB();
                    $efectividadCapital = [
                        "nombre" => "Saldo Capital",
                        "porcentaje" => "0,0",
                        "recaudo" => "0,0",
                        "recaudoTooltip" => "$0,0 de $0,0",
                        "capital" => "0,0",
                        "capitalTooltip" => "$0,00 de $0,00"
                    ];
                    $totalInicial = 0;
                    $totalRecaudado = 0;
                    $totalRecaudadoCuota = 0;

                    if (count($fcSel) > 0) {
                        foreach ($fcSel as $carteraSel) {
                            $periodoSel = isset($periodoPorCartera[$carteraSel]) ? $periodoPorCartera[$carteraSel] : -1;
                            $query = $periodoSel >= 0
                                ? ['periodo' => (int) $periodoSel, 'cartera' => (int) $carteraSel, 'fecha' => ['$gte' => $desde, '$lte' => $hasta]]
                                : ['cartera' => (int) $carteraSel, 'fecha' => ['$gte' => $desde, '$lte' => $hasta]];
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $desde2 = strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00");
                            $query2 = ["carteraEtl_fechaCarga" => ['$gte' => $desde2, '$lte' => strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . " + 5 days")) . " 00:00:00")], 'carteraEtl_carteraId' => (string) $carteraSel];
                            $mongo->buscar('avCargaInicial', $query2);
                            while ($row = $mongo->siguiente()) {
                                $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                                $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => $desde2, '$lte' => $hasta]]);
                                if ($c > 0) {
                                    $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                                    $totalRecaudadoCuota += floatval($row["carteraEtl_deudaNeta"]);
                                }
                            }
                        }
                    } else {
                        // Sin carteras seleccionadas no hay ciclo aplicable (el selector de ciclo requiere cartera).
                        $query = ['fecha' => ['$gte' => $desde, '$lte' => $hasta]];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $desde2 = strtotime(date('Y-m-d', $ro['fechaCarga']) . " 00:00:00");
                        $query2 = ["carteraEtl_fechaCarga" => ['$gte' => $desde2, '$lte' => strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . " + 5 days")) . " 00:00:00")]];
                        $mongo->buscar('avCargaInicial', $query2);
                        while ($row = $mongo->siguiente()) {
                            $totalInicial += floatval($row["carteraEtl_saldoCapital"]);
                            $c = $mongo1->buscar("cbPagos", ["pagos_numFactura" => (string) $row["carteraEtl_factura"], "pagos_proceso" => ['$gte' => $desde2, '$lte' => $hasta]]);
                            if ($c > 0) {
                                $totalRecaudado += floatval($row["carteraEtl_saldoCapital"]);
                                $totalRecaudadoCuota += floatval($row["carteraEtl_deudaNeta"]);
                            }
                        }
                    }
                    $efectividadCapital["porcentaje"] = number_format(($totalInicial > 0 ? ($totalRecaudado / $totalInicial) : 0) * 100, 2, ',', '');
                    $efectividadCapital["recaudo"] = abreviaNumero($totalRecaudado, 1);
                    $efectividadCapital["recaudoTooltip"] = formatea_numero($totalRecaudado, 2, ',', '.');
                    $efectividadCapital["capital"] = abreviaNumero($totalInicial, 1);
                    $efectividadCapital["capitalTooltip"] = formatea_numero($totalInicial, 2, ',', '.');
                    $efectividadCapital["monto"] = abreviaNumero($totalRecaudadoCuota, 1);
                    $efectividadCapital["montoTooltip"] = formatea_numero($totalRecaudadoCuota, 2, ',', '.');
                    $json['efectividad'] = $efectividadCapital;
                    break;
            }
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionWhatsapp["ws_fecha"] = 1756843285;
            $condicionPagos["pagos_proceso"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if (count($fcSel) > 0) {
            $condicion = array_merge($condicion, construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, "av_carteraId", "av_periodo"));
            $condicionWhatsapp = array_merge($condicionWhatsapp, construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, "ws_carteraId", "ws_periodo"));
            $condicionPagos = array_merge($condicionPagos, construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, "pagos_carteraId", "pagos_periodo", true));

            $condicionCorreo["cem_susCarteraId"] = ['$in' => $fcSel];
        }
        if (count($fcaSel) > 0) {
            $condicion["av_campaniaId"] = ['$in' => $fcaSel];
            $condicionCorreo["cem_susCampaniaId"] = ['$in' => $fcaSel];
            $condicionWhatsapp["ws_campaniaId"] = ['$in' => $fcaSel];
        }
        if (count($fcanSel) === 0 || in_array('llamada', $fcanSel)) {
            $totalesLlamadas = obtenerTotalesLlamadasAV($condicion);
        } else {
            $totalesLlamadas = obtenerTotalesLlamadasAV([]);
        }
        $json["llamadasAV"] = [
            "totales" => $totalesLlamadas["cuadros"],
            "porcentajeAvance" => $totalesLlamadas["porcentajeAvance"],
            "campanias" => $totalesLlamadas["campanias"]
        ];

        if (count($fcanSel) === 0 || in_array('mail', $fcanSel)) {
            $totalesCorreos = obtenerTotalesCorreo($condicionCorreo);
            $json["correo"] = [
                "totales" => $totalesCorreos["cuadros"],
                "porcentajeAvance" => $totalesCorreos["porcentajeAvance"],
                "campanias" => $totalesCorreos["campanias"]
            ];
        }
        if (count($fcanSel) === 0 || in_array('wp', $fcanSel)) {
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

        $totales = 0; //obtenerTotalesGestion($desde, $hasta, $parametrosTotales, $parametrosTotalesIntensidades, $pagos, $totalesLlamadas["compromisosPago"], $filtroCartera);
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
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }

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
                    if (count($fcSel) > 0) {
                        // Varias carteras: se toma la fecha de referencia más temprana entre todas
                        $desdeMin = null;
                        foreach ($fcSel as $carteraSel) {
                            $query = ['cartera' => (int) $carteraSel, 'activo' => (int) 1];
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $d2 = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                            $desdeMin = $desdeMin === null ? $d2 : min($desdeMin, $d2);
                        }
                        $desde = $desdeMin !== null ? $desdeMin : strtotime(date("Y-m-d") . " 00:00:00");
                    } else {
                        $query = ['activo' => (int) 1];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    }
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    if (count($fcSel) > 0) {
                        $desdeMin = null;
                        foreach ($fcSel as $carteraSel) {
                            $query = ['cartera' => (int) $carteraSel, 'activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $d2 = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                            $desdeMin = $desdeMin === null ? $d2 : min($desdeMin, $d2);
                        }
                        $desde = $desdeMin !== null ? $desdeMin : strtotime(date('Y-m-d', strtotime('first day of previous month')) . " 00:00:00");
                    } else {
                        $query = ['activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    }
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
        if (count($fcSel) > 0) {
            $condicion["cartera"] = ['$in' => $fcSel];
        }
        if (count($fcaSel) > 0) {
            $condicion["campania"] = ['$in' => $fcaSel];
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
    case "graficoLineaCorreos":
        #region graficoLineaCorreos 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }

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
                    if (count($fcSel) > 0) {
                        $desdeMin = null;
                        foreach ($fcSel as $carteraSel) {
                            $query = ['cartera' => (int) $carteraSel, 'activo' => (int) 1];
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $d2 = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                            $desdeMin = $desdeMin === null ? $d2 : min($desdeMin, $d2);
                        }
                        $desde = $desdeMin !== null ? $desdeMin : strtotime(date("Y-m-d") . " 00:00:00");
                    } else {
                        $query = ['activo' => (int) 1];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    }
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'asigAnterior':
                    $mongo = new MYMONGODB();
                    if (count($fcSel) > 0) {
                        $desdeMin = null;
                        foreach ($fcSel as $carteraSel) {
                            $query = ['cartera' => (int) $carteraSel, 'activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                            $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                            $ro = $mongo->siguiente();
                            if (!$ro) {
                                continue;
                            }
                            $d2 = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                            $desdeMin = $desdeMin === null ? $d2 : min($desdeMin, $d2);
                        }
                        $desde = $desdeMin !== null ? $desdeMin : strtotime(date('Y-m-d', strtotime('first day of previous month')) . " 00:00:00");
                    } else {
                        $query = ['activo' => (int) 0, 'fecha' => ['$gte' => strtotime('first day of previous month'), '$lte' => strtotime('last day of previous month')]];
                        $mongo->buscar('control_carga_periodo', $query, [], ['fecha' => 1]);
                        $ro = $mongo->siguiente();
                        $desde = strtotime(date("Y-m-d", strtotime(date('Y-m-d', $ro['fecha']) . "- 2 days")) . " 00:00:00");
                    }
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
        if (count($fcSel) > 0) {
            $condicion["cem_susCarteraId"] = ['$in' => $fcSel];
        }
        if (count($fcaSel) > 0) {
            $condicion["cem_susCampaniaId"] = ['$in' => $fcaSel];
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
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }

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
        if (count($fcSel) > 0) {
            $condicion["ws_carteraId"] = ['$in' => $fcSel];
        }
        if (count($fcaSel) > 0) {
            $condicion["ws_campaniaId"] = ['$in' => $fcaSel];
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
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }
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
        if (count($fcSel) > 0) {
            $condicion["av_carteraId"] = ['$in' => $fcSel];
            $condicionPagos["pagos_carteraId"] = ['$in' => array_map('strval', $fcSel)];
        }
        if (count($fcaSel) > 0) {
            $condicion["av_campaniaId"] = ['$in' => $fcaSel];
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
        // NOTA: filtroCartera ahora es un arreglo (selección múltiple, igual que en reporteAgenteVirtual)
        $fcSel = [];
        if (is_array($filtroCartera)) {
            foreach ($filtroCartera as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcSel[] = intval($value);
                }
            }
        } else if ($filtroCartera != "" && $filtroCartera != "todo") {
            $fcSel[] = intval($filtroCartera);
        }

        // NOTA: filtroCampania ahora es un arreglo (selección múltiple)
        $fcaSel = [];
        if (is_array($filtroCampania)) {
            foreach ($filtroCampania as $value) {
                if ($value !== "" && $value !== "todo") {
                    $fcaSel[] = intval($value);
                }
            }
        } else if ($filtroCampania != "" && $filtroCampania != "todo") {
            $fcaSel[] = intval($filtroCampania);
        }

        $mongo = new MYMONGODB();

        $filtroDesde = null;
        $filtroHasta = null;
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            switch ($filtroTiempo) {
                case 'hora':
                    $filtroDesde = strtotime(date("Y-m-d H") . ":00:00");
                    $filtroHasta = strtotime(date("Y-m-d H") . ":59:00");
                    break;
                case 'dia':
                    $filtroDesde = strtotime(date("Y-m-d") . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d") . " 23:59:59");
                    break;
                case 'ayer':
                    $filtroDesde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                    break;
                case 'semana':
                    $filtroDesde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                    break;
                case 'semanaanterior':
                    $filtroDesde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                    break;
                case 'mes':
                    $filtroDesde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'mesanterior':
                    $filtroDesde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    break;
                case 'rango':
                    $rangoDesde = expect_integer($d["filtroDesde"]);
                    $rangoHasta = expect_integer($d["filtroHasta"]);
                    $filtroDesde = strtotime(date("Y-m-d", $rangoDesde) . " 00:00:00");
                    $filtroHasta = strtotime(date("Y-m-d", $rangoHasta) . " 23:59:59");
                    break;
            }
        }

        //Periodos activos (uno o varios según filtro de cartera)
        $condicionPeriodo = ["activo" => 1];
        if (count($fcSel) > 0) {
            $condicionPeriodo["cartera"] = ['$in' => $fcSel];
        }

        $periodosActivos = [];
        $cursorPeriodo = $mongo->buscar("control_carga_periodo", $condicionPeriodo);
        if ($cursorPeriodo > 0) {
            while ($row = $mongo->siguientex()) {
                $periodosActivos[] = $row;
            }
        }

        $exportar = [];

        foreach ($periodosActivos as $periodo) {
            $cartera = intval($periodo["cartera"]);
            $ciclo = intval($periodo["periodo"]);
            $fechaPeriodo = intval($periodo["fecha"]);

            //Todas las facturas asignadas en ese periodo/cartera
            $condicionAsignacion = [
                "cubAG_carteraId" => (string) $cartera,
                "cubAG_fechaPeriodo" => $fechaPeriodo,
                "cubAG_ciclo" => $ciclo
            ];

            $universo = [];
            $cursorAsig = $mongo->buscar("cuAsignacionesGestionAP", $condicionAsignacion);
            if ($cursorAsig > 0) {
                while ($row = $mongo->siguientex()) {
                    $fact = str_replace(["'", '"', ' '], "", trim($row["cubAG_numFactura"]));
                    $universo[$fact] = [
                        "factura" => $row["cubAG_numFactura"],
                        "nombres" => $row["cubAG_nombres"],
                        "apellidos" => $row["cubAG_apellidos"],
                        "cedula" => $row["cubAG_cedula"],
                        "gestionada" => intval($row["cubAG_gestionada"] ?? 0)
                    ];
                }
            }

            if (count($universo) == 0) {
                continue; // no hay facturas asignadas en este periodo/cartera
            }

            //Toma gestiones solo del ciclo actual, cuando son de ciclos anteriores no los toma
            $limiteInferior = $fechaPeriodo;
            if ($filtroDesde !== null && $filtroDesde >= $limiteInferior) {
                $limiteInferior = $filtroDesde;
            }
            $condicionFecha = ['$gte' => $limiteInferior];
            if ($filtroHasta !== null) {
                $condicionFecha['$lte'] = $filtroHasta;
            }

            // Gestiones realizadas en ese periodo/cartera, dentro del rango pedido
            $condicionGestion = [
                "cubGC_carteraId" => (string) $cartera,
                "cubGC_ciclo" => $ciclo,
                "cubGC_fechaGestion" => $condicionFecha
            ];
            if (count($fcaSel) > 0) {
                $condicionGestion["cubGC_campaniaId"] = ['$in' => $fcaSel];
            }

            $llamadas = [];
            $whatsapps = [];
            $correos = [];

            $cursorGestion = $mongo->buscar("cuGestionCobranzaMysql", $condicionGestion);
            if ($cursorGestion > 0) {
                while ($row = $mongo->siguientex()) {
                    $factGestion = str_replace(["'", '"', ' '], "", trim($row["cubGC_numFactura"]));
                    $canal = $row["cubGC_canal"] ?? "";
                    $respuesta1 = $row["cubGC_tipificacion_respuesta1"] ?? "";
                    $respuesta2 = $row["cubGC_tipificacion_respuesta2"] ?? "";

                    if ($canal == "TELEFONICA") {
                        if (!isset($llamadas[$factGestion])) {
                            $llamadas[$factGestion] = ["realizadas" => 0, "atendidas" => 0, "no_atendidas" => 0];
                        }
                        $llamadas[$factGestion]["realizadas"]++;
                        if ($respuesta1 == "CONTACTO DIRECTO" || $respuesta1 == "CONTACTO INDIRECTO") {
                            $llamadas[$factGestion]["atendidas"]++;
                        } else {
                            $llamadas[$factGestion]["no_atendidas"]++;
                        }
                    } else if ($canal == "WHATSAPP") {
                        if (!isset($whatsapps[$factGestion])) {
                            $whatsapps[$factGestion] = ["realizados" => 0, "atendidos" => 0, "no_atendidos" => 0];
                        }
                        $whatsapps[$factGestion]["realizados"]++;
                        if ($respuesta1 == "CONTACTO DIRECTO" || $respuesta1 == "CONTACTO INDIRECTO") {
                            $whatsapps[$factGestion]["atendidos"]++;
                        } else {
                            $whatsapps[$factGestion]["no_atendidos"]++;
                        }
                    } else if ($canal == "EMAIL") {
                        //Se muestran correos enviados / no enviados según cubGC_tipificacion_respuesta2
                        if (!isset($correos[$factGestion])) {
                            $correos[$factGestion] = ["total" => 0, "enviados" => 0, "no_enviados" => 0];
                        }
                        $correos[$factGestion]["total"]++;
                        if ($respuesta2 == "Mail Enviado") {
                            $correos[$factGestion]["enviados"]++;
                        } else {
                            $correos[$factGestion]["no_enviados"]++;
                        }
                    }
                }
            }

            foreach ($universo as $fact => $datos) {
                $llamadasRealizadas = 0;
                $llamadasAtendidas = 0;
                $llamadasNoAtendidas = 0;
                $wpRealizados = 0;
                $wpAtendidos = 0;
                $wpNoAtendidos = 0;
                $correosTotal = 0;
                $correosEnviados = 0;
                $correosNoEnviados = 0;

                $estado = $datos["gestionada"] == 1 ? "GESTIONADO" : "NO GESTIONADO";

                if (isset($llamadas[$fact])) {
                    $llamadasRealizadas = $llamadas[$fact]["realizadas"];
                    $llamadasAtendidas = $llamadas[$fact]["atendidas"];
                    $llamadasNoAtendidas = $llamadas[$fact]["no_atendidas"];
                }
                if (isset($whatsapps[$fact])) {
                    $wpRealizados = $whatsapps[$fact]["realizados"];
                    $wpAtendidos = $whatsapps[$fact]["atendidos"];
                    $wpNoAtendidos = $whatsapps[$fact]["no_atendidos"];
                }
                if (isset($correos[$fact])) {
                    $correosTotal = $correos[$fact]["total"];
                    $correosEnviados = $correos[$fact]["enviados"];
                    $correosNoEnviados = $correos[$fact]["no_enviados"];
                }

                $exportar[] = [
                    $datos["factura"],
                    $estado,
                    $llamadasRealizadas,
                    $llamadasAtendidas,
                    $llamadasNoAtendidas,
                    $wpRealizados,
                    $wpAtendidos,
                    $wpNoAtendidos,
                    $correosTotal,
                    $correosEnviados,
                    $correosNoEnviados
                ];
            }
        }

        if (count($exportar) > 0) {
            //Encabezado actualizado con las 3 columnas de correo
            $encabezado = "OPERACIÓN;ESTADO;TOTAL LLAMADAS;LLAMADAS ATENDIDAS;LLAMADAS NO ATENDIDAS;TOTAL WHATSAPP;WHATSAPP ATENDIDOS;WHATSAPP NO ATENDIDOS;TOTAL CORREOS;CORREOS ENVIADOS;CORREOS NO ENVIADOS";
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
        // Las filas salen del cubo cuGestionCobranzaMysql (mismo universo que descargarGestionGeneral);
        // lo que el cubo no guarda (estado, lote, sentimiento, finalización, costo, audio) se completa desde avProgramadas.
        $d = jsonStart();
        $fcSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCartera"]));
        $fcaSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCampania"]));
        [$filtroDesde, $filtroHasta] = obtenerRangoFiltroTiempo((string) expect_safe_html($d["filtroTiempo"]), $d);
        $condiciones = obtenerCondicionesCubo($fcSel, $fcaSel, $filtroDesde, $filtroHasta, "TELEFONICA");

        $mongo = new MYMONGODB();
        $mongoOrigen = new MYMONGODB();
        $origenes = []; // por cubGC_avId: cada reintento es otra fila del cubo con el mismo avProgramadas
        $exportar = [];
        foreach ($condiciones as $condicion) {
            if ($mongo->buscar("cuGestionCobranzaMysql", $condicion, [], ['cubGC_fechaGestion' => 1]) <= 0) {
                continue;
            }
            while ($gestion = $mongo->siguientex()) {
                $avId = (string) ($gestion["cubGC_avId"] ?? "");
                if (!isset($origenes[$avId])) {
                    $origenes[$avId] = obtenerOrigenLlamada($mongoOrigen, $gestion["cubGC_avId"] ?? "");
                }
                $origen = $origenes[$avId];
                $duracion = intval($gestion["cubGC_duracionGestionSeg"] ?? 0);

                $camposResultado = [];
                // un reintento tiene su propio estado en av_detalleReintentos; el resto de datos es el de la llamada original
                $llamadaId = intval($gestion["cubGC_llamadaId"] ?? 0);
                $estado = $llamadaId != $origen["evento"] && isset($origen["estadosReintento"][$llamadaId]) ? $origen["estadosReintento"][$llamadaId] : $origen["estado"];
                $camposResultado["Estado"] = $traduceEstados[$estado] ?? $estado;
                $camposResultado["Fecha asignación"] = formatearFechaReporte($gestion["cubGC_fechaProgramacion"] ?? 0);
                $camposResultado["Fecha inicio"] = formatearFechaReporte($gestion["cubGC_horaInicio"] ?? 0);
                $camposResultado["Fecha fin"] = formatearFechaReporte($gestion["cubGC_horaFin"] ?? 0);
                $camposResultado["Duracion llamada"] = $duracion > 0 ? ($duracion > 3600 ? gmdate("H:i.s", $duracion) : gmdate("i:s", $duracion)) : "";
                $camposResultado["Número"] = $gestion["cubGC_telefono"] ?? "";
                $camposResultado["Operación"] = $gestion["cubGC_numFactura"] ?? "";
                $camposResultado["Periodo"] = $gestion["cubGC_ciclo"] ?? "";
                $camposResultado["Producto"] = $gestion["cubGC_producto"] ?? "";
                $camposResultado["Cartera"] = $gestion["cubGC_carteraNombre"] ?? "";
                $camposResultado["Campaña"] = $gestion["cubGC_campaniaNombre"] ?? "";
                $camposResultado["Fecha programación"] = formatearFechaReporte($origen["lote"]);
                if ($permisos["soyDesarrollo"]) {
                    $camposResultado["Proveedor"] = $gestion["cubGC_proveedor"] ?? "";
                    $camposResultado["Costo"] = $duracion > 0 ? $origen["costo"] : 0;
                    $camposResultado["Tokens"] = $duracion > 0 ? $origen["tokens"] : 0;
                }
                $camposResultado["Sentimiento"] = $origen["sentimiento"] !== "" ? ($traducSentimiento[$origen["sentimiento"]] ?? $origen["sentimiento"]) : "";
                $camposResultado["Motivo finalización"] = $origen["finalizacion"] !== "" ? ($traduceDesconexion[$origen["finalizacion"]] ?? $origen["finalizacion"]) : "";
                $camposResultado["Tipificación 1"] = $gestion["cubGC_tipificacion_respuesta1"] ?? "";
                $camposResultado["Tipificación 2"] = $gestion["cubGC_tipificacion_respuesta2"] ?? "";
                $camposResultado["Compromiso pago"] = formatearCompromisoPago((string) ($gestion["cubGC_tipificacion_compromiso"] ?? ""));
                $camposResultado["Audio"] = $origen["audio"];
                $exportar[] = $camposResultado;
            }
        }
        if ($permisos["soyDesarrollo"]) {
            $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA INICIO;FECHA FIN;DURACION LLAMADA;NÚMERO;OPERACIÓN;PERIODO;PRODUCTO;CARTERA;CAMPAÑA;FECHA PROGRAMACIÓN;PROVEEDOR;COSTO;TOKENS;SENTIMIENTO;MOTIVO FINALIZACIÓN;TIPIFICACIÓN 1;TIPIFICACIÓN 2;COMPROMISO PAGO;AUDIO";
        } else {
            $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA INICIO;FECHA FIN;DURACION LLAMADA;NÚMERO;OPERACIÓN;PERIODO;PRODUCTO;CARTERA;CAMPAÑA;FECHA PROGRAMACIÓN;SENTIMIENTO;MOTIVO FINALIZACIÓN;TIPIFICACIÓN 1;TIPIFICACIÓN 2;COMPROMISO PAGO;AUDIO";
        }
        $json["respuesta"] = escribirReporteCsv("Reporte_llamadas_av_", $encabezado, $exportar, $separador, $saltoLinea);
        #endregion
        break;
    case "descargarGestionCorreo":
        #region descargarGestionCorreo
        // Las filas salen del cubo cuGestionCobranzaMysql (mismo universo que descargarGestionGeneral).
        $d = jsonStart();
        $fcSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCartera"]));
        $fcaSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCampania"]));
        [$filtroDesde, $filtroHasta] = obtenerRangoFiltroTiempo((string) expect_safe_html($d["filtroTiempo"]), $d);
        $condiciones = obtenerCondicionesCubo($fcSel, $fcaSel, $filtroDesde, $filtroHasta, "EMAIL");

        $mongo = new MYMONGODB();
        $exportar = [];
        foreach ($condiciones as $condicion) {
            if ($mongo->buscar("cuGestionCobranzaMysql", $condicion, [], ['cubGC_fechaGestion' => 1]) <= 0) {
                continue;
            }
            while ($gestion = $mongo->siguientex()) {
                $abierto = intval($gestion["cubGC_abierto"] ?? 0);
                $carteraNombre = $gestion["cubGC_carteraNombre"] ?? "";
                $camposResultado = [];
                // mismo criterio que descargarGestionGeneral para enviados / no enviados
                $camposResultado["Estado"] = ($gestion["cubGC_tipificacion_respuesta2"] ?? "") == "Mail Enviado" ? "ENVIADO" : "NO ENVIADO";
                $camposResultado["Fecha asignación"] = formatearFechaReporte($gestion["cubGC_fechaProgramacion"] ?? 0);
                $camposResultado["Fecha envio"] = formatearFechaReporte($gestion["cubGC_fechaGestion"] ?? 0);
                $camposResultado["Abierto"] = $abierto > 0 ? date("d/m/Y H:i", $abierto) : "";
                $camposResultado["Correo"] = $gestion["cubGC_email"] ?? "";
                $camposResultado["Operación"] = $gestion["cubGC_numFactura"] ?? "";
                $camposResultado["Cartera"] = $carteraNombre == "" ? "BANCO DEL PACIFICO" : $carteraNombre;
                $camposResultado["Campaña"] = $gestion["cubGC_campaniaNombre"] ?? "";
                $exportar[] = $camposResultado;
            }
        }
        $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA ENVIO;ABIERTO;CORREO;OPERACIÓN;CARTERA;CAMPAÑA";
        $json["respuesta"] = escribirReporteCsv("Reporte_correos_av_", $encabezado, $exportar, $separador, $saltoLinea);
        #endregion
        break;
    case "descargarGestionWhatsapp":
        #region descargarGestionWhatsapp
        // Las filas salen del cubo cuGestionCobranzaMysql (mismo universo que descargarGestionGeneral);
        // el estado de envío no está en el cubo y se completa desde avProgramadasWhatsApp.
        $d = jsonStart();
        $fcSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCartera"]));
        $fcaSel = obtenerSeleccionFiltro(expect_safe_html($d["filtroCampania"]));
        [$filtroDesde, $filtroHasta] = obtenerRangoFiltroTiempo((string) expect_safe_html($d["filtroTiempo"]), $d);
        $condiciones = obtenerCondicionesCubo($fcSel, $fcaSel, $filtroDesde, $filtroHasta, "WHATSAPP");

        $mongo = new MYMONGODB();
        $mongoOrigen = new MYMONGODB();
        $estadosEnvio = []; // por cubGC_avId
        $exportar = [];
        foreach ($condiciones as $condicion) {
            if ($mongo->buscar("cuGestionCobranzaMysql", $condicion, [], ['cubGC_fechaGestion' => 1]) <= 0) {
                continue;
            }
            while ($gestion = $mongo->siguientex()) {
                $avId = (string) ($gestion["cubGC_avId"] ?? "");
                if (!isset($estadosEnvio[$avId])) {
                    $ws = obtenerDocumentoPorId($mongoOrigen, "avProgramadasWhatsApp", $gestion["cubGC_avId"] ?? "");
                    $estadosEnvio[$avId] = $ws !== null ? (string) ($ws["ws_estadoEnvio"] ?? "") : "";
                }
                $carteraNombre = $gestion["cubGC_carteraNombre"] ?? "";
                $camposResultado = [];
                $camposResultado["Estado"] = $estadosEnvio[$avId];
                $camposResultado["Fecha asignación"] = formatearFechaReporte($gestion["cubGC_fechaProgramacion"] ?? 0);
                $camposResultado["Fecha envio"] = formatearFechaReporte($gestion["cubGC_fechaGestion"] ?? 0);
                $camposResultado["Número"] = $gestion["cubGC_telefono"] ?? "";
                $camposResultado["Operación"] = $gestion["cubGC_numFactura"] ?? "";
                $camposResultado["Cartera"] = $carteraNombre == "" ? "BANCO DEL PACIFICO" : $carteraNombre;
                $camposResultado["Campaña"] = $gestion["cubGC_campaniaNombre"] ?? "";
                $camposResultado["Tipificación 1"] = $gestion["cubGC_tipificacion_respuesta1"] ?? "";
                $camposResultado["Tipificación 2"] = $gestion["cubGC_tipificacion_respuesta2"] ?? "";
                $exportar[] = $camposResultado;
            }
        }
        $encabezado = "ESTADO;FECHA ASIGNACIÓN;FECHA ENVIO;NÚMERO;OPERACIÓN;CARTERA;CAMPAÑA;TIPIFICACIÓN 1;TIPIFICACIÓN 2";
        $json["respuesta"] = escribirReporteCsv("Reporte_whatsapp_av_", $encabezado, $exportar, $separador, $saltoLinea);
        #endregion
        break;
}

#region funciones
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
                // Cuenta cuántas asignaciones (filas de avProgramadas, en cualquier estado) hay por campaña,
                // igual que en cmReporteAgenteVirtualCtrl.php, para mostrar "NOMBRE (cantidad)"
                $campanias[$row["av_campaniaNombre"]] = isset($campanias[$row["av_campaniaNombre"]]) ? $campanias[$row["av_campaniaNombre"]] + 1 : 1;

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
                        //$llamadasLanzadas++;
                        $llamadasPendientes++; //porque con livekit realidad no se generan aun solo se programan para que el servidor lance la llamada
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
    $campaniasConTotal = [];
    foreach ($campanias as $nombreCampania => $totalCampania) {
        $campaniasConTotal[] = $nombreCampania . " (" . $totalCampania . ")";
    }
    $respuesta["campanias"] = array_values($campaniasConTotal);
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
    $nuevoTotalEnviados = 0;
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

            // Cuenta cuántos correos (filas de cbEnvioMails) hay por campaña, para mostrar "NOMBRE (cantidad)"
            $campanias[$row["cem_susCampaniaNombre"]] = isset($campanias[$row["cem_susCampaniaNombre"]]) ? $campanias[$row["cem_susCampaniaNombre"]] + 1 : 1;

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

        //$respuesta["cuadros"]["numeroEnviados"]["porcentaje"] = formatea_numero(($totalEnviados * 100 / $cursor), 2, ",", ".");
        //$respuesta["cuadros"]["numeroEnviados"]["total"] = $totalEnviados;
        $nuevoTotalEnviados = $respuesta["cuadros"]["numeroProgramadas"]["total"] - $totalError;
        $respuesta["cuadros"]["numeroEnviados"]["porcentaje"] = formatea_numero(($nuevoTotalEnviados * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroEnviados"]["total"] = $nuevoTotalEnviados;
        $nuevoTotalPendientes = $respuesta["cuadros"]["numeroProgramadas"]["total"] - $nuevoTotalEnviados;
        $respuesta["cuadros"]["numeroPendientes"]["porcentaje"] = formatea_numero(($nuevoTotalPendientes * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroPendientes"]["total"] = $nuevoTotalPendientes;
        $respuesta["cuadros"]["numeroError"]["porcentaje"] = formatea_numero(($totalError * 100 / $cursor), 2, ",", ".");
        $respuesta["cuadros"]["numeroError"]["total"] = $totalError;
        $porcentajeAvance = round((($nuevoTotalEnviados * 100) / $cursor), 1);
        $respuesta["porcentajeAvance"] = $porcentajeAvance;
    }
    $campaniasConTotal = [];
    foreach ($campanias as $nombreCampania => $totalCampania) {
        $campaniasConTotal[] = $nombreCampania . " (" . $totalCampania . ")";
    }
    $respuesta["campanias"] = array_values($campaniasConTotal);
    $respuesta["gestionadas"] = $cedulasGestionadas;
    $respuesta["gestionadasPorCampania"] = $gestionadoPorCampania;
    $respuesta["totalIntensidad"] = $nuevoTotalEnviados;
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
            // Cuenta cuántos mensajes (filas de avProgramadasWhatsApp) hay por campaña, para mostrar "NOMBRE (cantidad)"
            $campanias[$row["ws_campaniaNombre"]] = isset($campanias[$row["ws_campaniaNombre"]]) ? $campanias[$row["ws_campaniaNombre"]] + 1 : 1;
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
    $campaniasConTotal = [];
    foreach ($campanias as $nombreCampania => $totalCampania) {
        $campaniasConTotal[] = $nombreCampania . " (" . $totalCampania . ")";
    }
    $respuesta["campanias"] = array_values($campaniasConTotal);
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
        "cumplimiento" => [
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
        ]
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

function obtenerCedulasEnCarga($desde, $hasta, $filtroCartera)
{
    //obtener total cargado
    $mongo = new MYMONGODB();
    $cedulasEnCarga = [];
    $historial = [];

    for ($i = $desde; $i <= $hasta; $i += 86400) {
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

// Busca el nombre de una cartera por su id, sin aplicar los filtros de fecha/campaña activos
// (a diferencia de $carterasu, que solo trae las carteras con actividad bajo los filtros actuales).
// Se usa como respaldo para no mostrar "Cartera ID: X" cuando una cartera tiene ciclos configurados
// pero, por los filtros de tiempo/campaña elegidos, no aparece en avProgramadas/cbEnvioMails/
// avProgramadasWhatsApp filtrados.
function obtenerNombreCartera($carteraId)
{
    $mongo = new MYMONGODB();
    $mongo->buscar("avProgramadas", ["av_carteraId" => $carteraId], [], [], 1);
    $row = $mongo->siguiente();
    if ($row && !empty($row["av_carteraNombre"])) {
        return $row["av_carteraNombre"];
    }
    $mongo->buscar("cbEnvioMails", ["cem_susCarteraId" => $carteraId], [], [], 1);
    $row = $mongo->siguiente();
    if ($row && !empty($row["cem_susCarteraNombre"])) {
        return $row["cem_susCarteraNombre"];
    }
    $mongo->buscar("avProgramadasWhatsApp", ["ws_carteraId" => $carteraId], [], [], 1);
    $row = $mongo->siguiente();
    if ($row && !empty($row["ws_carteraNombre"])) {
        return $row["ws_carteraNombre"];
    }
    return "Cartera ID: " . $carteraId;
}

//establece una condicion principal de carteras
// Construye la condición Mongo para cartera (+ periodo opcional por cartera).
// Cuando hay ciclos elegidos para alguna cartera ($periodoPorCartera no vacío), arma un $or
// con una condición por cartera (agregando su periodo si fue seleccionado para esa cartera
// puntual); si no hay ningún ciclo elegido, se comporta igual que antes: $campoCartera => ['$in' => $fcSel].
function construirCondicionCarteraPeriodo($fcSel, $periodoPorCartera, $campoCartera, $campoPeriodo = null, $carteraComoString = false)
{
    if (count($periodoPorCartera) > 0 && $campoPeriodo !== null) {
        $orConds = [];
        foreach ($fcSel as $carteraSel) {
            $valorCartera = $carteraComoString ? (string) $carteraSel : (int) $carteraSel;
            $cond = [$campoCartera => $valorCartera];
            if (isset($periodoPorCartera[$carteraSel])) {
                $cond[$campoPeriodo] = (int) $periodoPorCartera[$carteraSel];
            }
            $orConds[] = $cond;
        }
        return ['$or' => $orConds];
    }
    $valores = $carteraComoString ? array_map('strval', $fcSel) : $fcSel;
    return [$campoCartera => ['$in' => $valores]];
}

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

#region funciones reportes desde cuGestionCobranzaMysql

/**
 * Tipificaciones que el cubo guarda pero que no llegaron al cliente; se excluyen de los reportes.
 * Misma lista que tipificacionesExcluidasGestion() de cmDashboardGestionesCtrl.php.
 *
 * @return array
 */
function tipificacionesExcluidasGestion(): array
{
    return ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado'];
}

/**
 * Normaliza un filtro de selección múltiple (arreglo o valor simple) a un arreglo de enteros, sin "todo".
 *
 * @param mixed $valor Valor recibido del front.
 * @return array
 */
function obtenerSeleccionFiltro(mixed $valor): array
{
    $valores = is_array($valor) ? $valor : [$valor];
    $seleccion = [];
    foreach ($valores as $value) {
        if ($value === null || $value === "" || $value === "todo") {
            continue;
        }
        $seleccion[] = intval($value);
    }
    return $seleccion;
}

/**
 * Traduce el filtro de tiempo de la pantalla a un rango de timestamps.
 *
 * @param string $filtroTiempo Valor del filtro (hora, dia, ayer, semana, ..., rango).
 * @param array  $d            Datos recibidos; en "rango" se leen filtroDesde / filtroHasta.
 * @return array [desde, hasta]; ambos null si no hay filtro o el valor no se reconoce.
 */
function obtenerRangoFiltroTiempo(string $filtroTiempo, array $d): array
{
    switch ($filtroTiempo) {
        case 'hora':
            return [strtotime(date("Y-m-d H") . ":00:00"), strtotime(date("Y-m-d H") . ":59:00")];
        case 'dia':
            return [strtotime(date("Y-m-d") . " 00:00:00"), strtotime(date("Y-m-d") . " 23:59:59")];
        case 'ayer':
            return [strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00"), strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59")];
        case 'semana':
            return [strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00"), strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59")];
        case 'semanaanterior':
            return [strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00"), strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59")];
        case 'mes':
            return [strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00"), strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59")];
        case 'mesanterior':
            return [strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00"), strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59")];
        case 'rango':
            $rangoDesde = intval(expect_integer($d["filtroDesde"] ?? 0));
            $rangoHasta = intval(expect_integer($d["filtroHasta"] ?? 0));
            return [strtotime(date("Y-m-d", $rangoDesde) . " 00:00:00"), strtotime(date("Y-m-d", $rangoHasta) . " 23:59:59")];
    }
    return [null, null];
}

/**
 * Arma una condición sobre cuGestionCobranzaMysql por cada periodo activo de control_carga_periodo,
 * con el mismo universo que descargarGestionGeneral: cartera + ciclo, y gestiones desde el inicio del periodo.
 * Las carteras elegidas se limitan a las permitidas para el usuario ($idsCarterasGeneral; vacío = todas).
 *
 * @param array    $fcSel  Carteras seleccionadas.
 * @param array    $fcaSel Campañas seleccionadas.
 * @param int|null $desde  Inicio del rango pedido.
 * @param int|null $hasta  Fin del rango pedido.
 * @param string   $canal  Valor de cubGC_canal (TELEFONICA, EMAIL, WHATSAPP).
 * @return array Lista de condiciones Mongo; vacía si no hay periodos aplicables.
 */
function obtenerCondicionesCubo(array $fcSel, array $fcaSel, ?int $desde, ?int $hasta, string $canal): array
{
    global $idsCarterasGeneral;
    $carteras = $fcSel;
    if (is_array($idsCarterasGeneral) && count($idsCarterasGeneral) > 0) {
        $carteras = count($fcSel) > 0 ? array_values(array_intersect($fcSel, $idsCarterasGeneral)) : $idsCarterasGeneral;
        if (count($carteras) == 0) {
            return [];
        }
    }

    $condicionPeriodo = ["activo" => 1];
    if (count($carteras) > 0) {
        $condicionPeriodo["cartera"] = ['$in' => $carteras];
    }

    $mongo = new MYMONGODB();
    if ($mongo->buscar("control_carga_periodo", $condicionPeriodo) <= 0) {
        return [];
    }

    $condiciones = [];
    while ($periodo = $mongo->siguientex()) {
        $fechaPeriodo = intval($periodo["fecha"]);
        // solo gestiones del ciclo actual: nunca antes del inicio del periodo
        $condicionFecha = ['$gte' => ($desde !== null && $desde >= $fechaPeriodo) ? $desde : $fechaPeriodo];
        if ($hasta !== null) {
            $condicionFecha['$lte'] = $hasta;
        }
        $condicion = [
            "cubGC_carteraId" => (string) intval($periodo["cartera"]),
            "cubGC_ciclo" => intval($periodo["periodo"]),
            "cubGC_fechaGestion" => $condicionFecha,
            "cubGC_canal" => $canal,
            "cubGC_tipificacion_respuesta2" => ['$nin' => tipificacionesExcluidasGestion()]
        ];
        if (count($fcaSel) > 0) {
            $condicion["cubGC_campaniaId"] = ['$in' => $fcaSel];
        }
        $condiciones[] = $condicion;
    }
    return $condiciones;
}

/**
 * Busca un documento por _id (ObjectId u string de 24 hex).
 *
 * @param MYMONGODB $mongo     Instancia con la que se consulta (su cursor se reutiliza).
 * @param string    $coleccion Colección.
 * @param mixed     $id        ObjectId o su representación en string.
 * @return mixed Documento encontrado o null.
 */
function obtenerDocumentoPorId(MYMONGODB $mongo, string $coleccion, mixed $id): mixed
{
    if (is_string($id)) {
        if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return null;
        }
        $id = new MongoDB\BSON\ObjectId($id);
    }
    if (!is_object($id)) {
        return null;
    }
    if ($mongo->buscar($coleccion, ["_id" => $id], [], [], 1) <= 0) {
        return null;
    }
    $doc = $mongo->siguientex();
    return $doc ?: null;
}

/**
 * Datos de avProgramadas y de avDetalleConversacionesRetell que el cubo no guarda.
 *
 * @param MYMONGODB $mongo Instancia para las consultas de origen.
 * @param mixed     $avId  cubGC_avId de la gestión.
 * @return array {estado: string, evento: int, estadosReintento: array (evento => estado), lote: int, sentimiento: string, finalizacion: string, costo: string|int, tokens: int, audio: string}
 */
function obtenerOrigenLlamada(MYMONGODB $mongo, mixed $avId): array
{
    $origen = ["estado" => "", "evento" => 0, "estadosReintento" => [], "lote" => 0, "sentimiento" => "", "finalizacion" => "", "costo" => 0, "tokens" => 0, "audio" => ""];
    $av = obtenerDocumentoPorId($mongo, "avProgramadas", $avId);
    if ($av === null) {
        return $origen;
    }
    $origen["estado"] = (string) ($av["av_estadoEnvio"] ?? "");
    $origen["evento"] = intval($av["av_evento"] ?? 0);
    foreach ($av["av_detalleReintentos"] ?? [] as $reintento) {
        if (!empty($reintento["evento"])) {
            $origen["estadosReintento"][intval($reintento["evento"])] = (string) ($reintento["estado"] ?? "");
        }
    }
    $origen["lote"] = intval($av["av_lote"] ?? 0);
    $origen["sentimiento"] = (string) ($av["av_sentimiento"] ?? "");
    $origen["finalizacion"] = (string) ($av["av_finalizacion"] ?? "");

    $idConversacion = $av["av_idConversacion"] ?? "";
    if ($idConversacion == "" || $mongo->buscar("avDetalleConversacionesRetell", ["idConversacion" => $idConversacion]) <= 0) {
        return $origen;
    }
    $avDetalle = $mongo->siguientex();
    if (!$avDetalle) {
        return $origen;
    }
    if (isset($avDetalle["costoCreditos"])) {
        $origen["costo"] = formatea_numero($avDetalle["costoCreditos"], 2, ".", "");
    }
    if (isset($avDetalle["usoTokens"]["total"])) {
        $origen["tokens"] = $avDetalle["usoTokens"]["total"];
    }
    if (isset($avDetalle["archivoAudio"]) && $avDetalle["archivoAudio"] != "") {
        $url = str_replace(BASEFOLDER, BASEURL, $avDetalle["archivoAudio"]);
        if ($url != BASEURL && $url != BASEURL . "sc/cacheAudios/") {
            $origen["audio"] = $url;
        }
    }
    return $origen;
}

/**
 * Formatea un timestamp para el CSV; vacío si no hay fecha.
 *
 * @param mixed $timestamp Timestamp Unix.
 * @return string
 */
function formatearFechaReporte(mixed $timestamp): string
{
    $ts = intval($timestamp);
    return $ts > 0 ? date("d/m/Y H:i:s", $ts) : "";
}

/**
 * Compromiso de pago (Y-m-d) a mostrar: solo si cae en el mes actual o posterior.
 *
 * @param string $compromiso Fecha del compromiso tal como la guarda el cubo.
 * @return string
 */
function formatearCompromisoPago(string $compromiso): string
{
    if ($compromiso == "") {
        return "";
    }
    $ts = strtotime($compromiso);
    if ($ts === false || $ts <= 0 || date("Ym", $ts) < date("Ym")) {
        return "";
    }
    return date("Y-m-d", $ts);
}

/**
 * Escribe el CSV en web/cacheFolder y devuelve la respuesta que espera el front.
 *
 * @param string $prefijo     Prefijo del nombre de archivo.
 * @param string $encabezado  Encabezado separado por ";".
 * @param array  $filas       Filas a exportar.
 * @param string $separador   Separador de columnas.
 * @param string $saltoLinea  Fin de línea.
 * @return array ['archivo' => url] o ['error' => mensaje]
 */
function escribirReporteCsv(string $prefijo, string $encabezado, array $filas, string $separador, string $saltoLinea): array
{ 
    if (count($filas) == 0) {
        return ["error" => "No hay datos para generar reporte"];
    }
    $textoFinal = str_replace(";", $separador, $encabezado) . $saltoLinea;
    foreach ($filas as $linea) {
        $textoFinal .= implode($separador, $linea) . $saltoLinea;
    }
    $archivo = BASEFOLDER . "/web/cacheFolder/" . $prefijo . date("dmYHis") . ".csv";
    $myFile = fopen($archivo, "w");
    if ($myFile === false) {
        return ["error" => "No se pudo crear el archivo [001]"];
    }
    fwrite($myFile, $textoFinal);
    fclose($myFile);
    if (!file_exists($archivo)) {
        return ["error" => "No se pudo crear el archivo [002]"];
    }
    return ["archivo" => str_replace(BASEFOLDER, BASEURL, $archivo)];
}

#endregion

?>
<? //_FIN_DE_ARCHIVO                                                                                                                                  
?>