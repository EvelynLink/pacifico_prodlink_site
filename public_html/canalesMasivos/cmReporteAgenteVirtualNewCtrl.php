<?php
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");

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
// [
//     "#000f30",
//     "#001c4d",
//     "#003685",
//     "#0050bd",
//     "#0068f1",
//     "#417cff",
//     "#6e92ff",
//     "#91a9ff",
//     "#b3c2ff",
//     "#d7deff",
//     "#edf0ff",
//];
//OJO estos valores solo cambian en la interface el estado de las llamadas, el key es el estado en la bdd
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

// $paletaBn = [
//     "#dddddd",
//     "#b5b7b9",
//     "#9c9ea0",
//     "#65686d",
//     "#0e151e",
//     "#b8b8b8",
//     "#8c8c8c",
//     "#5e5e5e",
//     "#383838",
// ];

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

switch ($act) {
    case "cargarTabula":
        #region cargarTabula 
        $d = jsonStart();

        if (isset($d["filtro"])) {
            foreach ($d["filtro"] as $f => $filtro) {
                if ($filtro["campo"] == "av_estadoEnvio" && $filtro["filtro"] != "") {
                    foreach ($traduceEstados as $k => $v) {
                        if (str_contains(strtolower($v), strtolower($filtro["filtro"]))) {
                            $d["filtro"][$f]["filtro"] = $k;
                            break;
                        }
                    }
                }
            }
        }

        $filtroLote = expect_safe_html($_GET["filtroLote"]);
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroCampania = expect_safe_html($_GET["filtroCampania"]);
        $filtroProveedor = expect_safe_html($_GET["filtroProveedor"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador([]);

        $ngTabula->permiteExportar(true, "Programadas.csv");

        $coleccion = "avProgramadas";
        $condition = [];
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
                case 'rango':
                    $filtroDesde = expect_integer($_GET["filtroDesde"]);
                    $filtroHasta = expect_integer($_GET["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condition[$campo] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condition["av_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condition["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condition["av_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condition["av_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condition["av_producto"] = $filtroProducto;
        }
        if ($_SESSION[MID . "userId"] != 910753601 && $_SESSION[MID . "userId"] != 910848422) {
            $condition["av_carteraId"] = 39; //TODO: quitar PS
        }

        $campos = [];

        $ngTabula->setQueryDatos(
            $coleccion,
            $condition,
            $campos,
            ['av_fechaGeneraLlamada' => -1]
        );

        $ngTabula->setPreparaDatos(function ($campos) {
            global $traduceEstados;
            $campos["av_estadoEnvio"] = isset($traduceEstados[$campos["av_estadoEnvio"]]) ? $traduceEstados[$campos["av_estadoEnvio"]] : $campos["av_estadoEnvio"];
            $campos["av_duracionSegundos"] = isset($campos["av_duracionSegundos"]) && $campos["av_duracionSegundos"] > 0 ? ($campos["av_duracionSegundos"] > 3600 ? gmdate("(H:i.s)", $campos["av_duracionSegundos"]) : gmdate("(i:s)", $campos["av_duracionSegundos"])) : "";
            return $campos;
        });

        $ngTabula->setPreparaDatosCabeceraExportar(function ($cabecera) {
            return $cabecera;
        });

        $mongo = new MYMONGODB();
        $mongo2 = new MYMONGODB();
        $ngTabula->setPreparaDatosExportar(function ($campos) {
            global $traduceEstados, $traduceDesconexion, $traducSentimiento, $mongo, $permisos, $mongo2;

            $c = $mongo->buscar("avDetalleConversacionesRetell", ["idConversacion" => $campos["av_idConversacion"]]);
            $avDetalle = [];
            if ($c > 0) {
                $avDetalle = $mongo->siguientex();
            }

            unset($campos["_id"]);
            unset($campos["id"]);
            unset($campos["av_carteraId"]);
            unset($campos["av_campaniaId"]);
            unset($campos["av_clienteId"]);
            unset($campos["av_procesado"]);
            unset($campos["av_evento"]);
            unset($campos["av_procesadoRespuesta"]);
            unset($campos["av_agenteParametroId"]);
            unset($campos["av_agenteId"]);
            unset($campos["av_numeroId"]);
            unset($campos["av_vozId"]);
            unset($campos["av_valoresReemplazo"]);
            unset($campos["av_idLlamada"]);
            unset($campos["av_fechaActualizaLlamada"]);
            unset($campos["av_tieneAudio"]);
            unset($campos["av_obtenerAudio"]);
            unset($campos["av_fechaObtieneAudio"]);
            unset($campos["av_tieneTranscripcion"]);
            unset($campos["av_fechaPeriodo"]);
            unset($campos["av_eventoFecha"]);
            unset($campos["av_error"]);
            unset($campos["av_reintentos"]);
            unset($campos["av_audioActualizado"]);
            unset($campos["av_detalleReintentos"]);
            unset($campos["av_supera"]);
            unset($campos["procesoDesprogramado"]);
            unset($campos["procesoDesprogramadoDetalle"]);
            unset($campos["av_modoClasificacion"]);
            unset($campos["av_desprogramada"]);
            unset($campos["av_llamadasEncontradasFinalizadas"]);

            $campos["Estado"] = isset($traduceEstados[$campos["av_estadoEnvio"]]) ? $traduceEstados[$campos["av_estadoEnvio"]] : $campos["av_estadoEnvio"];
            unset($campos["av_estadoEnvio"]);

            $campos["Fecha asignación"] = date("d/m/Y H:i:s", $campos["av_fecha"]);
            unset($campos["av_fecha"]);

            $campos["Fecha inicio"] = isset($campos["av_fechaGeneraLlamada"]) && $campos["av_fechaGeneraLlamada"] > 0 ? date("d/m/Y H:i:s", $campos["av_fechaGeneraLlamada"]) : "";
            unset($campos["av_fechaGeneraLlamada"]);

            $campos["Fecha fin"] = isset($campos["av_fechaFinLlamada"]) && $campos["av_fechaFinLlamada"] > 0 ? date("d/m/Y H:i:s", $campos["av_fechaFinLlamada"]) : "";
            unset($campos["av_fechaFinLlamada"]);

            $campos["Duracion llamada"] = isset($campos["av_duracionSegundos"]) && $campos["av_duracionSegundos"] > 0 ? ($campos["av_duracionSegundos"] > 3600 ? gmdate("H:i.s", $campos["av_duracionSegundos"]) : gmdate("i:s", $campos["av_duracionSegundos"])) : "";
            unset($campos["av_duracionSegundos"]);

            // $campos["Número origen"] = $campos["av_numeroId"];
            // unset($campos["av_numeroId"]);

            $campos["Número"] = $campos["av_telefono"];
            unset($campos["av_telefono"]);

            //$campos["Cédula"] = $campos["av_cedula"];
            unset($campos["av_cedula"]);

            //$campos["Nombre"] = $campos["av_nombre"];
            unset($campos["av_nombre"]);

            $campos["Operación"] = $campos["av_factura"];
            unset($campos["av_factura"]);

            $campos["Periodo"] = isset($campos["av_periodo"]) ? $campos["av_periodo"] : "";
            unset($campos["av_periodo"]);

            $campos["Producto"] = $campos["av_producto"];
            unset($campos["av_producto"]);

            $campos["Cartera"] = $campos["av_carteraNombre"];
            unset($campos["av_carteraNombre"]);

            $campos["Campaña"] = $campos["av_campaniaNombre"];
            unset($campos["av_campaniaNombre"]);

            $campos["Fecha programación"] = isset($campos["av_lote"]) && $campos["av_lote"] > 0 ? date("d/m/Y H:i:s", $campos["av_lote"]) : "";
            unset($campos["av_lote"]);

            if ($permisos["soyDesarrollo"]) {
                $campos["Proveedor"] = $campos["av_proveedor"];
                //obtengo los tokens
                $tokens = 0;
                $costo = 0;
                if ($campos["Duracion llamada"] != "") {
                    $mongo2->buscar("avDetalleConversacionesRetell", ["idConversacion" => $campos["av_idConversacion"]]);
                    while ($r = $mongo2->siguientex()) {
                        if (isset($r["costoCreditos"])) {
                            $costo = formatea_numero($r["costoCreditos"], 2, ".", "");
                        }
                        if (isset($r["usoTokens"])) {
                            $tokens = $r["usoTokens"]["total"];
                        }
                    }
                }
                $campos["Costo"] = $costo;
                $campos["Tokens"] = $tokens;
            }
            unset($campos["av_idConversacion"]);
            unset($campos["av_proveedor"]);

            $campos["Sentimiento"] = isset($campos["av_sentimiento"]) ? (isset($traducSentimiento[$campos["av_sentimiento"]]) ? $traducSentimiento[$campos["av_sentimiento"]] : $campos["av_sentimiento"]) : "";
            unset($campos["av_sentimiento"]);

            $campos["Motivo finalización"] = isset($campos["av_finalizacion"]) ? (isset($traduceDesconexion[$campos["av_finalizacion"]]) ? $traduceDesconexion[$campos["av_finalizacion"]] : $campos["av_finalizacion"]) : "";
            unset($campos["av_finalizacion"]);

            $campos["Tipificación 1"] = isset($campos["av_tipificacion"]["respuesta1"]) ? $campos["av_tipificacion"]["respuesta1"] : "";
            $campos["Tipificación 2"] = isset($campos["av_tipificacion"]["respuesta2"]) ? $campos["av_tipificacion"]["respuesta2"] : "";
            $campos["Compromiso pago"] = isset($campos["av_tipificacion"]["respuesta3"]) ? $campos["av_tipificacion"]["respuesta3"] : "";
            unset($campos["av_tipificacion"]);

            $campos["Audio"] = (isset($avDetalle["archivoAudio"]) && $avDetalle["archivoAudio"] != "") ? (str_replace(BASEFOLDER, BASEURL, $avDetalle["archivoAudio"])) : "";

            return $campos;
        });

        $json = $ngTabula->responde();
        #endregion 
        break;
    case "obtenerParametrizacion":
        #region obtenerParametrizacion 
        $d = jsonStart();
        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);

        $mongo = new MYMONGODB();

        $condicion = "";
        $condiciones = [];
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condiciones[$campo] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condiciones["av_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condiciones["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condiciones["av_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condiciones["av_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condiciones["av_producto"] = $filtroProducto;
        }
        if ($_SESSION[MID . "userId"] != 910753601 && $_SESSION[MID . "userId"] != 910848422) {
            $condiciones["av_carteraId"] = 39; //TODO: quitar PS
        }

        if (count($condiciones) > 0) {
            $condicion = [
                '$match' => $condiciones
            ];
        }

        $lotes = [
            [
                "id" => "todo",
                "nombre" => "Cualquier fecha programación"
            ]
        ];
        $options = [
            [
                '$project' => [
                    'av_lote' => 1
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'av_lote' => '$av_lote'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadas", $options);
        $lotesu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_lote"] != "null") {
                $lotesu[] = [
                    "id" => $row["_id"]["av_lote"],
                    "nombre" => date("d/m/Y H:i", $row["_id"]["av_lote"])
                ];
            }
        }
        sort($lotesu);
        $lotes = array_merge($lotes, $lotesu);

        $proveedores = [
            [
                "id" => "todo",
                "nombre" => "Cualquier proveedor"
            ]
        ];
        $options = [
            [
                '$project' => [
                    'av_proveedor' => 1
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'av_proveedor' => '$av_proveedor'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadas", $options);
        $proveedoresu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_proveedor"] != "null") {
                $proveedoresu[] = [
                    "id" => $row["_id"]["av_proveedor"],
                    "nombre" => $row["_id"]["av_proveedor"]
                ];
            }
        }
        sort($proveedoresu);
        $proveedores = array_merge($proveedores, $proveedoresu);

        $carteras = [
            [
                "id" => "todo",
                "nombre" => "Todas las carteras"
            ]
        ];
        $options = [
            ['$project' => [
                'av_carteraId' => 1,
                "av_carteraNombre" => 1,
            ]],
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
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadas", $options);
        $carterasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_carteraId"] != "null") {
                $carterasu[] = [
                    "id" => $row["_id"]["av_carteraId"],
                    "nombre" => ($row["_id"]["av_carteraNombre"] != null ? $row["_id"]["av_carteraNombre"] : "Cartera ID: " . $row["_id"]["av_carteraId"])
                ];
            }
        }
        sort($carterasu);
        $carteras = array_merge($carteras, $carterasu);

        $campanias = [
            [
                "id" => "todo",
                "nombre" => "Todas las campañas"
            ]
        ];
        $options = [
            ['$project' => [
                'av_campaniaId' => 1,
                "av_campaniaNombre" => 1,
            ]],
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
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadas", $options);
        $campaniasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_campaniaId"] != "null") {
                $campaniasu[] = [
                    "id" => $row["_id"]["av_campaniaId"],
                    "nombre" => ($row["_id"]["av_campaniaNombre"] != null ? $row["_id"]["av_campaniaNombre"] : "Campaña ID: " . $row["_id"]["av_campaniaId"])
                ];
            }
        }
        sort($campaniasu);
        $campanias = array_merge($campanias, $campaniasu);

        $productos = [
            [
                "id" => "todo",
                "nombre" => "Todos los productos"
            ]
        ];
        $options = [
            ['$project' => [
                'av_producto' => 1
            ]],
            [
                '$group' => [
                    "_id" => [
                        'av_producto' => '$av_producto'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadas", $options);
        $productosu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["av_producto"] != "null") {
                $productosu[] = [
                    "id" => $row["_id"]["av_producto"],
                    "nombre" => $row["_id"]["av_producto"]
                ];
            }
        }
        sort($productosu);
        $productos = array_merge($productos, $productosu);

        $json["lotes"] = $lotes;
        $json["carteras"] = $carteras;
        $json["campanias"] = $campanias;
        $json["proveedores"] = $permisos["soyDesarrollo"] ? $proveedores : [];
        $json["productos"] = $productos;
        $json["permisos"] = $permisos;
        $json["colores"] = $paletaColor;

        #endregion 
        break;
    case "obtenerDetalleConversacion":
        #region obtenerDetalleConversacion 
        $d = jsonStart();
        $conv  = expect_safe_html($d["conv"]);
        $mongo = new MYMONGODB();

        //obtengo el proveedor
        $cursor2 = $mongo->buscar("avProgramadas", ["av_idConversacion" => $conv], [], ["av_fechaGeneraLlamada" => -1], 1);
        if ($cursor2 > 0) {
            $r = $mongo->siguiente();
            $coleccion = $r["av_proveedor"] == "RETELL" ? "avDetalleConversacionesRetell" : "avDetalleConversaciones";

            $cursor = $mongo->buscar($coleccion, ["idConversacion" => $conv]);
            if ($cursor > 0) {
                while ($row = $mongo->siguientex()) {
                    if (isset($row["archivoAudio"]) && ($row["archivoAudio"] != "" ||  $r["av_proveedor"] == "RETELL")) {

                        $detallesReintentos = [];
                        if (isset($r["av_detalleReintentos"])) {
                            foreach ($r["av_detalleReintentos"] as $value) {
                                $detallesReintentos[] = [
                                    "error" => $value["error"],
                                    "estado" => isset($traduceEstados[$value["estado"]]) ? $traduceEstados[$value["estado"]] : $value["estado"],
                                    "fecha" => $value["fecha"],
                                ];
                            }
                        }

                        $json["respuesta"]["id"] = $permisos["soyDesarrollo"] ? $conv : "";
                        $json["respuesta"]["audio"] = str_replace(BASEFOLDER, BASEURL, str_replace("\\", "", $row["archivoAudio"]));
                        $json["respuesta"]["archivoJson"] = $permisos["soyDesarrollo"] ? str_replace(BASEFOLDER, BASEURL, str_replace("\\", "", $row["archivoDetalle"])) : "";
                        $json["respuesta"]["resumen"] = utf8_2_decode($row["resumen"]);
                        //$json["respuesta"]["transcripcion"] = base64_encode(json_encode($row["transcripcion"]));
                        $json["respuesta"]["duracionSegundos"] = $row["duracionSegundos"] > 3600 ? gmdate("H:i.s", $row["duracionSegundos"]) : gmdate("i:s", $row["duracionSegundos"]);
                        $json["respuesta"]["finalizacion"] = $permisos["soyDesarrollo"] ? (isset($traduceDesconexion[$row["finalizacion"]]) ? $traduceDesconexion[$row["finalizacion"]] : $row["finalizacion"]) : "";
                        $json["respuesta"]["error"] = $r["av_error"] != "" ?  $r["av_error"] : ($row["error"] != "" ? $row["error"] : "");
                        $json["respuesta"]["telefono"] = $row["telefono"];
                        $json["respuesta"]["fechaInicio"] = $row["fechaInicio"];
                        $json["respuesta"]["fechaContesta"] = $row["fechaContesta"];
                        $json["respuesta"]["fechaFin"] = isset($row["fechaFin"]) ? $row["fechaFin"] : "";
                        $json["respuesta"]["datosDinamicos"] = $r["av_valoresReemplazo"];
                        $json["respuesta"]["costo"] = $permisos["soyDesarrollo"] ? round($row["costoCreditos"], 3) : 0;
                        $json["respuesta"]["tokens"] = $permisos["soyDesarrollo"] ? (isset($row["usoTokens"]) ? $row["usoTokens"]["total"] : 0) : 0;
                        $json["respuesta"]["sentimiento"] = isset($row["sentimiento"]) ? (isset($traducSentimiento[$row["sentimiento"]]) ? $traducSentimiento[$row["sentimiento"]] : $row["sentimiento"]) : "";
                        $json["respuesta"]["proveedor"] = $permisos["soyDesarrollo"] ? $r["av_proveedor"] : "";
                        $json["respuesta"]["reintentos"] = $permisos["soyDesarrollo"] ? (isset($r["av_reintentos"]) && $r["av_reintentos"] > 0 ? ($r["av_reintentos"] == 9999 ? 0 : $r["av_reintentos"] - 1) : 0) : 0;
                        $json["respuesta"]["reintentosDetalle"] = $permisos["soyDesarrollo"] ? $detallesReintentos : "";
                        $json["respuesta"]["mostrarReintentos"] = false;
                        $json["respuesta"]["analisisExito"] = $permisos["soyDesarrollo"] ? (isset($row["analisisExito"]) ? $row["analisisExito"] : "") : "";
                        $json["respuesta"]["eliminadoProveedor"] = $permisos["soyDesarrollo"] ? (isset($row["eliminadoProveedor"]) && $row["eliminadoProveedor"] > 0 ? "SI" : "NO") : "";
                        $json["respuesta"]["callLog"] = $permisos["soyDesarrollo"] ? (isset($row["callLog"]) ? $row["callLog"] : "") : "";
                        $json["respuesta"]["telefonoOrigen"] = $permisos["soyDesarrollo"] ? $r["av_numeroId"] : "";

                        $tipificacion = "";
                        if (isset($r["av_tipificacion"])) {
                            if (isset($r["av_tipificacion"]["respuesta1"]) && $r["av_tipificacion"]["respuesta1"] != "") {
                                $tipificacion .= $r["av_tipificacion"]["respuesta1"];
                            }
                            if (isset($r["av_tipificacion"]["respuesta2"]) && $r["av_tipificacion"]["respuesta2"] != "") {
                                $tipificacion .= " -> " . $r["av_tipificacion"]["respuesta2"];
                            }
                            if (isset($r["av_tipificacion"]["respuesta3"]) && $r["av_tipificacion"]["respuesta3"] != "") {
                                $tipificacion .= " -> " . $r["av_tipificacion"]["respuesta3"];
                            }
                        }

                        $json["respuesta"]["tipificacion"] = $tipificacion;
                        $json["respuesta"]["analisis"] = (isset($r["av_tipificacion"]["analisis"]) ? (is_array($r["av_tipificacion"]["analisis"]) ? "" : $r["av_tipificacion"]["analisis"]) : "") . (isset($r["av_tipificacion"]["resumen"]) ? $r["av_tipificacion"]["resumen"] : "");
                        foreach ($row["transcripcion"] as $key => $value) {
                            if (isset($value["tiempoTranscurridoSegundos"]) && $value["tiempoTranscurridoSegundos"] != "") {
                                $row["transcripcion"][$key]["fechaHora"] = date("H:i:s", (($row["fechaInicio"] / 1000) + $value["tiempoTranscurridoSegundos"]));
                            }
                        }
                        $json["respuesta"]["transcripcion"] = base64_encode(json_encode($row["transcripcion"]));
                    } else {
                        $resp = $API->api_obtenerAudioConversacion($conv);

                        if (isset($resp["datos"]["path"])) {
                            //actualizarLlamada($id, $row["detalle"], $resp["datos"]["path"]);TODO:
                            $json["respuesta"] = $resp["datos"]["path"];
                        } else {
                            $json["respuesta"] = $resp["mensaje"];
                        }
                    }
                }
            } else {
                //hay error registrado?
                if ($r["av_estadoEnvio"] == "ERROR_GENERAR_LLAMADA" && $r["av_error"] != "") {
                    $json["error"]  = "Error al programar la llamada: " . $r["av_error"];
                } else {
                    $json["error"]  = "No existe el detalle de la llamada (002)";
                }
            }
        } else {
            $json["error"]  = "No existe el detalle de la llamada (001)";
        }
        #endregion 
        break;
    case "detalleGraficos":
        #region detalleGraficos 
        $d = jsonStart();
        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);

        $condicion = [];
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
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
            // $condicion['$or'] = [
            //     ["av_fecha" => ['$gte' => $desde, '$lte' => $hasta]],
            //     ["av_fechaGeneraLlamada" => ['$gte' => $desde, '$lte' => $hasta]]
            // ];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condicion["av_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condicion["av_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicion["av_producto"] = $filtroProducto;
        }
        if ($_SESSION[MID . "userId"] != 910753601 && $_SESSION[MID . "userId"] != 910848422) {
            $condicion["av_carteraId"] = 39; //TODO: quitar PS
        }

        $arbol = [
            "VOLVER A LLAMAR" => "CONTACTO DIRECTO",
            "NO LLEGO ESTADO CUENTA" => "CONTACTO DIRECTO",
            "RECLAMO PENDIENTE" => "CONTACTO DIRECTO",
            "PAGA EN FECHA" => "CONTACTO DIRECTO",
            "DESEMPLEADO" => "CONTACTO DIRECTO",
            "NO QUIERE PAGAR" => "CONTACTO DIRECTO",
            "FALLECIDO" => "CONTACTO INDIRECTO",
            "SOCIO FUERA PAIS" => "CONTACTO INDIRECTO",
            "MENSAJE A TERCERO" => "CONTACTO INDIRECTO",
            "MENSAJE VOZ GRABADORA" => "SIN CONTACTO",
            "ILOCALIZABLE" => "SIN CONTACTO",
            "TELEFONO EQUIVOCADO NO CORRESPONDE" => "SIN CONTACTO",
            "SIN RESPUESTA" => "SIN CONTACTO",
            "USUARIO RECHAZO LA LLAMADA" => "SIN CONTACTO",
        ];

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avProgramadas", $condicion);
        $respuesta = [
            "cuadrosTotales" => [
                "numeroProgramadas" => ["Programadas", 0, "fa fa-hashtag", "Llamadas programadas para gestionar", "black", ""],
                "numeroLlamadasDesprogramadas" => ["Desprogramadas", 0, "fa fa-hashtag", "Llamadas desprogramadas", "black"],
                "numeroLlamadasPendientes" => ["Pendientes", 0, "fa fa-hashtag", "Llamadas pendientes de marcar", "black"],
                "numeroLlamadasLuego" => ["Marcar luego", 0, "fa fa-hashtag", "Llamadas que no contestaron y se va a reintentar luego", "black"],
                //"numeroLlamadasIniciadas" => ["Iniciadas", 0, "fa fa-hashtag", "Llamadas iniciadas", "black"],
                "numeroLlamadasEnProgreso" => ["En progreso", 0, "fa fa-hashtag", "Llamadas recién iniciadas o en progreso", "black"],
                "numeroLlamadasFinalizadas" => ["Atendidas", 0, "fa fa-hashtag", "Llamadas atendidas", $paletaColor[2], ""],
                "numeroLlamadasError" => ["No atendidas", 0, "fa fa-hashtag", "Llamadas no atendidas por el cliente", $paletaColor[0], ""],
                "numeroLlamadasSinConexion" => ["Sin conexión", 0, "fa fa-hashtag", "Llamadas que no se pudieron realizar o aún no se tipifican", "black"],
                "duracionPromedioSegundos" => ["Dur. promedio", 0, "fa fa-clock-o", "Duración promedio", "black"],
            ],
            "cuadrosIndicadores" => [
                "contactoDirecto" => ["Contacto directo", "Contacto directo con el cliente", 0, "$0,00", $paletaColor[8], ""],
                "compromisosPago" => ["Paga en fecha", "Ya pagó o se obtuvo un compromiso de pago por parte del cliente", 0,   "$0,00", $paletaColor[8], ""],
                "contactoIndirecto" => ["Contacto indirecto", "No se pudo contactar directamente con el cliente", 0,   "$0,00", $paletaColor[4], ""],
                "sinContacto" => ["Sin contacto", "No hubo ningún tipo de contacto", 0, "$0,00", $paletaColor[3], ""],
            ],
            "dataGraficoTipificaciones" => [],
            "dataGraficoLlamadas" => [],
            "dataGraficoProgramadasEstado" => [],
            "dataGraficoSentimientos" => [],
            "dataGraficoFinalizacion" => [],
            "porcentajeAvance" => 0, //iniciadas, en progreso, Atendidas y error / total de llamadas - desprogramadas
            "porcentajeExito" => 0,
            "totalMeta" => ["$0", "0 deudas", "100%"],
            "totalContactado" => ["$0", "0 deudas", "0%"],
            "campanias" => []
        ];

        if ($cursor > 0) {
            $respuesta["cuadrosTotales"]["numeroProgramadas"][1] = $cursor;
            $llamadasLanzadas = 0;
            $llamadasDesprogramadas = 0;
            $llamadasReintento = 0;
            $llamadasPendientes = 0;
            $llamadasError = 0;
            $llamadasFinalizadas = 0;
            $duracionPromedio = 0;
            $totalDuracion = 0;
            $llamadasProgreso = 0;
            $llamadasSinConexion = 0;
            $estados = [];
            //$duraciones = [];
            $dataLlamadas = [];
            $dataProgreso = [];
            $dataError = [];
            $dataIniciada = [];
            $sentimientos = [
                "Positive" => 0,
                "Negative" => 0,
                "Neutral" => 0,
                "Desconocido" => 0
            ];
            $finalizacion = [];
            $totalFinalizacion = 0;
            $menor = 0;
            $mayor = 0;
            $contactoDirecto = [0, 0]; //cantidad, monto
            $contactoIndirecto = [0, 0];
            $sinContacto = [0, 0];
            $sinClasificacion = [0, 0];
            $compromisosPago = [0, 0];
            $detalleTipificaciones = [];
            $montoTipificaciones = [];
            $totalTipificacion = 0;
            $montoTotal = 0;
            $montoAtendidas = 0;
            $montoNoAtendidas = 0;
            $campanias = [];

            while ($row = $mongo->siguientex()) {
                $campanias[$row["av_campaniaNombre"]] = isset($campanias[$row["av_campaniaNombre"]]) ? $campanias[$row["av_campaniaNombre"]] + 1 : 1;
                $montoTotal = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoTotal + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoTotal;
                $menor = $menor == 0 ? $row["av_procesado"] : ($row["av_procesado"] < $menor ? $row["av_procesado"] : $menor);
                $mayor = $row["av_procesado"] > $mayor ? $row["av_procesado"] : $mayor;
                $totalDuracion += isset($row["av_duracionSegundos"]) ? $row["av_duracionSegundos"] : 0;

                $estados[$row["av_estadoEnvio"]] = isset($estados[$row["av_estadoEnvio"]]) ? $estados[$row["av_estadoEnvio"]] + 1 : 1;
                switch ($row["av_estadoEnvio"]) {
                    case 'PENDIENTE':
                        $llamadasPendientes++;
                        break;
                    case 'FINALIZADA':
                        $llamadasFinalizadas++;
                        $montoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoAtendidas;
                        break;
                    case "LLAMADA_GENERADA":
                        $llamadasLanzadas++;
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
                                        $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
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
                                        trigger_error($row["av_tipificacion"]);
                                        $llamadasSinConexion++;
                                    } else {
                                        $llamadasError++;
                                        $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
                                    }
                                    //$llamadasError++;
                                    break;
                                default:
                                    $llamadasError++;
                                    $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
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
                                        $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
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
                                        $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
                                    }
                                    //$llamadasError++;
                                    break;
                                case "voicemail_reached":
                                    $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
                                    break;
                                default:
                                    $llamadasError++;
                                    $montoNoAtendidas = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? $montoNoAtendidas + floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : $montoNoAtendidas;
                                    break;
                            }
                        } else {
                            $llamadasSinConexion++;
                        }
                        break;
                    case "EN_PROGRESO":
                        $llamadasProgreso++;
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
                    default:
                        //nothing
                        break;
                }

                if (isset($row["av_desprogramada"]) && $row["av_desprogramada"] > 0 && $row["av_estadoEnvio"] != "DESPROGRAMADO_VERIFICACION") {
                    //desprogramadas en asignacion
                    $llamadasDesprogramadas++;
                }

                // if ($row["av_estadoEnvio"] == "FINALIZADA" && isset($sentimientos[$row["av_sentimiento"]])) {
                //     $sentimientos[$row["av_sentimiento"]]++;
                // }
                if (isset($row["av_finalizacion"]) && $row["av_finalizacion"] != "" && $row["av_finalizacion"] != null && $row["av_finalizacion"] != "null") {
                    $totalFinalizacion++;
                    $finalizacion[$row["av_finalizacion"]] = isset($finalizacion[$row["av_finalizacion"]]) ? $finalizacion[$row["av_finalizacion"]] + 1 : 1;
                }

                //tipificaciones
                if (
                    $row["av_estadoEnvio"] != "DESPROGRAMADO_ASIGNACION"
                    && $row["av_estadoEnvio"] != "DESPROGRAMADO_VERIFICACION"
                    && $row["av_estadoEnvio"] != "PENDIENTE"
                    && $row["av_estadoEnvio"] != "MARCAR_LUEGO"
                    && $row["av_estadoEnvio"] != "ERROR_GENERAR_LLAMADA"
                ) {
                    if (isset($row["av_tipificacion"])) {
                        // if (isset($detalleTipificaciones[$row["av_tipificacion"]["respuesta1"]][$row["av_tipificacion"]["respuesta2"]])) {
                        //     $detalleTipificaciones[$row["av_tipificacion"]["respuesta1"]][$row["av_tipificacion"]["respuesta2"]]++;
                        // } else {
                        //     $detalleTipificaciones[$row["av_tipificacion"]["respuesta1"]][$row["av_tipificacion"]["respuesta2"]] = 1;
                        // }
                        $monto = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : 0;

                        if ($row["av_tipificacion"]["respuesta2"] != "") {
                            if (isset($detalleTipificaciones[$row["av_tipificacion"]["respuesta2"]])) {
                                $detalleTipificaciones[$row["av_tipificacion"]["respuesta2"]][1]++;
                                $montoTipificaciones[$row["av_tipificacion"]["respuesta2"]] += $monto;
                            } else {
                                $detalleTipificaciones[$row["av_tipificacion"]["respuesta2"]] = [
                                    $row["av_tipificacion"]["respuesta1"],
                                    1
                                ];
                                $montoTipificaciones[$row["av_tipificacion"]["respuesta2"]] = $monto;
                            }
                            $totalTipificacion++;

                            if (strpos(strtoupper($row["av_tipificacion"]["respuesta2"]), "PROVEEDOR DE TELEFON") !== false) {
                                trigger_error($row["_id"]);
                            }

                            //if (strtoupper($row["av_tipificacion"]["respuesta1"]) == "CONTACTO DIRECTO") {
                            if (isset($arbol[strtoupper($row["av_tipificacion"]["respuesta2"])]) && $arbol[strtoupper($row["av_tipificacion"]["respuesta2"])] == "CONTACTO DIRECTO") {
                                $contactoDirecto[0]++;
                                $contactoDirecto[1] += $monto;
                                if (isset($sentimientos[$row["av_sentimiento"]])) {
                                    $sentimientos[$row["av_sentimiento"]]++;
                                } else {
                                    $sentimientos["Desconocido"]++;
                                }
                            }
                            //if (strtoupper($row["av_tipificacion"]["respuesta1"]) == "CONTACTO INDIRECTO") {
                            if (isset($arbol[strtoupper($row["av_tipificacion"]["respuesta2"])]) && $arbol[strtoupper($row["av_tipificacion"]["respuesta2"])] == "CONTACTO INDIRECTO") {
                                $contactoIndirecto[0]++;
                                $contactoIndirecto[1] += $monto;
                                if (isset($sentimientos[$row["av_sentimiento"]])) {
                                    $sentimientos[$row["av_sentimiento"]]++;
                                } else {
                                    $sentimientos["Desconocido"]++;
                                }
                            }
                            //if (strtoupper($row["av_tipificacion"]["respuesta1"]) == "SIN CONTACTO") {
                            if (isset($arbol[strtoupper($row["av_tipificacion"]["respuesta2"])]) && $arbol[strtoupper($row["av_tipificacion"]["respuesta2"])] == "SIN CONTACTO") {
                                $sinContacto[0]++;
                                $sinContacto[1] += $monto;
                            }
                            if ((
                                    (isset($arbol[strtoupper($row["av_tipificacion"]["respuesta2"])]) && $arbol[strtoupper($row["av_tipificacion"]["respuesta2"])] == "CONTACTO DIRECTO")
                                    || (isset($arbol[strtoupper($row["av_tipificacion"]["respuesta2"])]) && $arbol[strtoupper($row["av_tipificacion"]["respuesta2"])] == "CONTACTO INDIRECTO"))
                                && strtoupper($row["av_tipificacion"]["respuesta3"]) != ""
                            ) {
                                $compromisosPago[0]++;
                                $compromisosPago[1] += $monto;
                            }
                        } else {
                            $monto = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : 0;
                            $sinClasificacion[0]++;
                            $sinClasificacion[1] += $monto;
                        }
                    } else {
                        $monto = isset($row["av_valoresReemplazo"]["monto_total_vencido"]) ? floatval($row["av_valoresReemplazo"]["monto_total_vencido"]) : 0;
                        $sinClasificacion[0]++;
                        $sinClasificacion[1] += $monto;
                    }
                }
            }

            $duracionPromedio = $llamadasFinalizadas > 0 ? $totalDuracion / $llamadasFinalizadas : 0;

            //iniciadas, en progreso, finalizadas y error / total de llamadas - desprogramadas
            //$realizadas = ($llamadasLanzadas + $llamadasProgreso + $llamadasFinalizadas + $llamadasError);
            //$realizadas = ($llamadasLanzadas + $llamadasProgreso + $llamadasFinalizadas);
            $realizadas = $llamadasFinalizadas;
            $total = $cursor - $llamadasDesprogramadas;
            if ($total > 0) {
                $respuesta["porcentajeAvance"] = round(((($llamadasFinalizadas +  $llamadasError + $llamadasSinConexion) * 100)  / $total), 1);
                $respuesta["porcentajeExito"] = round((($realizadas / $total) * 100), 1);
            }

            if ($permisos["soyGerente"]) {
                $respuesta["cuadrosIndicadores"]["contactoDirecto"][2] = $contactoDirecto[0];
                $respuesta["cuadrosIndicadores"]["contactoDirecto"][3] = "$" . abreviaNumero($contactoDirecto[1], 1);
                $total = $respuesta["cuadrosTotales"]["numeroProgramadas"][1]; //$contactoDirecto[0] + $contactoIndirecto[0] + $sinContacto[0];
                $por = $total > 0 ? ($contactoDirecto[0] * 100) / $total : 0;
                $respuesta["cuadrosIndicadores"]["contactoDirecto"][5] = formatea_numero($por, 1, ",", ".") . "%";
                $respuesta["cuadrosIndicadores"]["compromisosPago"][2] = $compromisosPago[0];
                $respuesta["cuadrosIndicadores"]["compromisosPago"][3] = "$" . abreviaNumero($compromisosPago[1], 1);
                $total = $contactoDirecto[0];
                $por = $total > 0 ?  ($compromisosPago[0] * 100) / $total : 0;
                $respuesta["cuadrosIndicadores"]["compromisosPago"][5] = formatea_numero($por, 1, ",", ".") . "%";
                $respuesta["cuadrosIndicadores"]["contactoIndirecto"][2] = $contactoIndirecto[0];
                $respuesta["cuadrosIndicadores"]["contactoIndirecto"][3] = "$" . abreviaNumero($contactoIndirecto[1], 1);
                $total = $respuesta["cuadrosTotales"]["numeroProgramadas"][1]; //$contactoDirecto[0] + $contactoIndirecto[0] + $sinContacto[0];
                $por = $total > 0 ? ($contactoIndirecto[0] * 100) / $total : 0;
                $respuesta["cuadrosIndicadores"]["contactoIndirecto"][5] = formatea_numero($por, 1, ",", ".") . "%";
                //$respuesta["cuadrosIndicadores"]["sinContacto"][1] = "No hubo ningún tipo de contacto (" . $sinContacto[0] . ") o no se tipifica aún (" . $sinClasificacion[0] . ")";
                //$respuesta["cuadrosIndicadores"]["sinContacto"][2] = ($sinContacto[0] + $sinClasificacion[0]);
                //$respuesta["cuadrosIndicadores"]["sinContacto"][3] = "$" . abreviaNumero(($sinContacto[1] + $sinClasificacion[1]), 1);
                $respuesta["cuadrosIndicadores"]["sinContacto"][1] = "No hubo ningún tipo de contacto";
                $respuesta["cuadrosIndicadores"]["sinContacto"][2] = $sinContacto[0];
                $respuesta["cuadrosIndicadores"]["sinContacto"][3] = "$" . abreviaNumero($sinContacto[1], 1);
                $total = $respuesta["cuadrosTotales"]["numeroProgramadas"][1]; //$contactoDirecto[0] + $contactoIndirecto[0] + $sinContacto[0];
                $por = $total > 0 ? ($sinContacto[0] * 100) / $total : 0;
                $respuesta["cuadrosIndicadores"]["sinContacto"][5] = formatea_numero($por, 1, ",", ".") . "%";

                $graficoTipificaciones = [];

                // foreach ($detalleTipificaciones as $primerNivel => $valuePrimer) {
                uasort($detalleTipificaciones, function ($a, $b) {
                    return ($a[0] < $b[0]) ? -1 : 1;
                });
                foreach ($detalleTipificaciones as $segundoNivel => $valueSegundo) {
                    $porc = $totalTipificacion > 0 ? ($valueSegundo[1] * 100) / $totalTipificacion : 0;
                    $color = "";
                    switch (strtolower($valueSegundo[0])) {
                        case 'contacto directo':
                            $color = $paletaColor[8];
                            break;
                        case 'contacto indirecto':
                            $color = $paletaColor[4];
                            break;
                        case 'sin contacto':
                            $color = $paletaColor[3];
                            break;
                        default:
                            $color = "#000";
                            break;
                    }
                    // $graficoTipificaciones[] = [
                    //     "label" => ucfirst(strtolower($segundoNivel)),
                    //     "value" => $valueSegundo[1],
                    //     "color" => $color,
                    //     "porcentaje" => formatea_numero($porc, 2, ",", ".") . "%",
                    //     "monto" => "$" . abreviaNumero($montoTipificaciones[$segundoNivel], 1),
                    //     "padre" => ucfirst(strtolower($valueSegundo[0]))
                    // ];
                    $graficoTipificaciones["titulos"][] = ucfirst(strtolower($segundoNivel));
                    $graficoTipificaciones["valores"][] = $valueSegundo[1];
                }
                // }

                $respuesta["totalMeta"][0] = "$" . abreviaNumero($montoTotal, 1);
                $respuesta["totalMeta"][1] = $respuesta["cuadrosTotales"]["numeroProgramadas"][1] . " deudas";
                //$respuesta["totalContactado"][0] = "$" . abreviaNumero(($contactoDirecto[1] + $contactoIndirecto[1]), 1);
                //$respuesta["totalContactado"][1] = ($contactoDirecto[0] + $contactoIndirecto[0]) . " deudas";
                $respuesta["totalContactado"][0] = "$" . abreviaNumero(($compromisosPago[1]), 1);
                $respuesta["totalContactado"][1] = ($compromisosPago[0]) . " deudas";
                //$respuesta["totalContactado"][2] = formatea_numero((($contactoDirecto[0] + $contactoIndirecto[0]) * 100) / $total, 1, ",", ".") . "%"; 
                //$respuesta["totalContactado"][2] = formatea_numero((($contactoDirecto[0] + $contactoIndirecto[0]) * 100) / $respuesta["cuadrosTotales"]["numeroProgramadas"][1], 1, ",", ".") . "%";
                $respuesta["totalContactado"][2] = formatea_numero((($compromisosPago[0]) * 100) / $respuesta["cuadrosTotales"]["numeroProgramadas"][1], 1, ",", ".") . "%";
                $campaniasFin = [];
                foreach ($campanias as $key => $value) {
                    $campaniasFin[] = $key . " (" . $value . ")";
                }
                $respuesta["campanias"] = array_values($campaniasFin);
            }

            // $respuesta["dataGraficoTipificaciones"][] = [
            //     "key" => "Tipificaciones",
            //     "values" => $graficoTipificaciones
            // ];

            $respuesta["dataGraficoTipificaciones"] = [
                "titulos" => $graficoTipificaciones["titulos"],
                "valores" => $graficoTipificaciones["valores"]
            ];

            $respuesta["cuadrosTotales"]["numeroProgramadas"][5] = "$" . abreviaNumero($montoTotal, 1);
            $respuesta["cuadrosTotales"]["numeroLlamadasError"][5] = "$" . abreviaNumero($montoNoAtendidas, 1);
            $respuesta["cuadrosTotales"]["numeroLlamadasFinalizadas"][5] = "$" . abreviaNumero($montoAtendidas, 1);

            $respuesta["cuadrosTotales"]["numeroLlamadasPendientes"][1] = $llamadasPendientes; //($llamadasPendientes + $llamadasReintento);
            //$respuesta["cuadrosTotales"]["numeroLlamadasPendientes"][3] = "Llamadas pendientes (" . $llamadasPendientes . ") o que se marcaron para llamar luego (" . $llamadasReintento . ")";

            $respuesta["cuadrosTotales"]["numeroLlamadasLuego"][1] = $llamadasReintento;

            $respuesta["cuadrosTotales"]["numeroLlamadasEnProgreso"][1] = $llamadasProgreso + $llamadasLanzadas;
            $respuesta["cuadrosTotales"]["numeroLlamadasError"][1] = $llamadasError;
            //$respuesta["cuadrosTotales"]["numeroLlamadasIniciadas"][1] = $llamadasLanzadas;
            $respuesta["cuadrosTotales"]["numeroLlamadasFinalizadas"][1] = $llamadasFinalizadas;
            $respuesta["cuadrosTotales"]["numeroLlamadasSinConexion"][1] = $llamadasSinConexion;
            if ($duracionPromedio > 3600) {
                $respuesta["cuadrosTotales"]["duracionPromedioSegundos"][1] = gmdate("H:i.s", $duracionPromedio);
                $respuesta["cuadrosTotales"]["duracionPromedioSegundos"][3]  = "Duración promedio de las llamadas en horas, minutos y segundos";
            } else {
                $respuesta["cuadrosTotales"]["duracionPromedioSegundos"][1] = gmdate("i:s", $duracionPromedio);
                $respuesta["cuadrosTotales"]["duracionPromedioSegundos"][3]  = "Duración promedio de las llamadas en minutos y segundos";
            }
            $graficoEstados = [];
            $graficoSentimientos = [];
            $graficoFinalizadas = [];

            $i = 3;
            foreach ($estados as $key => $value) {
                $es = isset($traduceEstados[$key]) ? $traduceEstados[$key] : $key;
                $es = ucfirst(strtolower($es));
                $porc = $value * 100 / $respuesta["cuadrosTotales"]["numeroProgramadas"][1];
                $porc = formatea_numero($porc, 2, ",", ".");
                $graficoEstados[] = ["porc" => $porc, "key" => $es, "y" => $value, "color" => ($es == "Atendida" ? $paletaColor[2] : ($es == "No Atendida" ? $paletaColor[0] : $paletaColor[$i])), "extra" => $key];
                $i = $i == count($paletaColor) - 1 ? 3 : $i + 1;
            }
            $respuesta["dataGraficoProgramadasEstado"] = $graficoEstados;

            $i = 3;
            foreach ($sentimientos as $key => $value) {
                if ($value > 0) {
                    $es = isset($traducSentimiento[$key]) ? $traducSentimiento[$key] : $key;
                    $porc = $value * 100 / $respuesta["cuadrosTotales"]["numeroLlamadasFinalizadas"][1];
                    $porc = formatea_numero($porc, 2, ",", ".");
                    $graficoSentimientos[] = ["porc" => $porc, "key" => $es, "y" => $value, "color" => ($es == "Positivo" ? $paletaColor[2] : ($es == "Negativo" ? $paletaColor[0] : ($es == "Neutral" ? $paletaColor[1] : $paletaColor[$i])))];
                    $i = $i == count($paletaColor) - 1 ? 0 : $i + 1;
                }
            }
            $respuesta["dataGraficoSentimientos"] = $graficoSentimientos;

            $i = 0;
            foreach ($finalizacion as $key => $value) {
                if ($value > 0) {
                    $porc = $value * 100 / $totalFinalizacion;
                    $porc = formatea_numero($porc, 2, ",", ".");
                    $laKey = isset($traduceDesconexion[$key]) ? $traduceDesconexion[$key] : $key;
                    $tooltip = $laKey;
                    if (strlen($laKey) > 15) {
                        $laKey = substr($laKey, 0, 12) . "...";
                    }
                    $graficoFinalizadas[] = ["porc" => $porc, "key" => $laKey, "y" => $value, "color" => $paletaColor[$i], "tooltip" => $tooltip];
                    $i = $i == count($paletaColor) - 1 ? 0 : $i + 1;
                }
            }

            $respuesta["dataGraficoFinalizacion"] = $graficoFinalizadas;
            $respuesta["cuadrosTotales"]["numeroLlamadasDesprogramadas"][1] = $llamadasDesprogramadas;
            $respuesta["totalProgramadas"] = $respuesta["cuadrosTotales"]["numeroProgramadas"][1];
            $respuesta["totalFinalizadas"] = $respuesta["cuadrosTotales"]["numeroLlamadasFinalizadas"][1];
            $respuesta["totalFinalizacion"] = $totalFinalizacion;
            $respuesta["totalNuevas"] = $llamadasPendientes;
            $respuesta["totalReintento"] = $llamadasReintento;
        }

        $json["respuesta"] = $respuesta;
        #endregion 
        break;
    case "graficoDia":
        #region graficoDia 
        $d = jsonStart();
        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $configConcurrencias = isset($cnf["Máximo de concurrencia"]) ? $cnf["Máximo de concurrencia"] : [];
        $horaDesde = explode(":", $horaDesde)[0];

        $concurrencias = [];
        foreach ($configConcurrencias as $value) {
            $partes = explode(":::", $value);
            $concurrencias[] = ["nombre" => strtoupper($partes[0]), "valor" => $partes[1]];
        }

        $condicion = [];
        $desde = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
        $horaHoy = date("H");
        $hasta =  strtotime("+5 minutes");

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
                    $hasta =  strtotime("+5 minutes");
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
            //$condicion["fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }

        $condicion["fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condicion["lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["cartera"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["campania"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condicion["proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicion["producto"] = $filtroProducto;
        }

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avLlamadasMarcadas", $condicion);
        $respuesta = [];
        $graficoBarras = [];
        $graficoBarrasNo = [];
        if ($cursor > 0) {
            $dataLlamadas = [];
            $dataLlamadasContestadas = [];
            $dataLlamadasContestadas1 = [];
            $dataLlamadasNoContestadas = [];
            $dataLlamadasNoContestadas1 = [];
            $dataConstestadasPorHora = [];
            $dataNoConstestadasPorHora = [];
            while ($row = $mongo->siguientex()) {
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
                    $dataLlamadasContestadas1[$sinHoraB] = isset($dataLlamadasContestadas1[$sinHoraB]) ? $dataLlamadasContestadas1[$sinHoraB] + 1 : 1;
                    $dataConstestadasPorHora[$sinHoraB] = isset($dataConstestadasPorHora[$sinHoraB]) ? $dataConstestadasPorHora[$sinHoraB] + 1 : 1;
                } else {
                    $dataLlamadasNoContestadas[$sinHora] = isset($dataLlamadasNoContestadas[$sinHora]) ? $dataLlamadasNoContestadas[$sinHora] + 1 : 1;
                    $dataLlamadasNoContestadas1[$sinHoraB] = isset($dataLlamadasNoContestadas1[$sinHoraB]) ? $dataLlamadasNoContestadas1[$sinHoraB] + 1 : 1;
                    $dataNoConstestadasPorHora[$sinHoraB] = isset($dataNoConstestadasPorHora[$sinHoraB]) ? $dataNoConstestadasPorHora[$sinHoraB] + 1 : 1;
                }
            }

            $dataLlamadasFinal = [];
            $totalPorHora = [];
            $totalPorHoraNo = [];

            $lineaTitulos = [];
            $lineaValores = [
                "llamadas_marcadas" => [],
                "llamadas_atendidas" => [],
                "llamadas_no_atendidas" => [],
            ];
            $topValores = [];

            //por hora
            //for ($i = $desde; $i <= $hasta; $i += 60) {
            for ($i = $desde; $i <= $hasta; $i += $factor) {
                //if (count($dataLlamadas) > 0) {
                $dataLlamadasFinal[0][] = ["x" => $i, "y" => (isset($dataLlamadas[$i]) ? $dataLlamadas[$i] : 0), "series" => 0, "seriesIndex" => 0];
                $dataLlamadasFinal[1][] = ["x" => $i, "y" => (isset($dataLlamadasContestadas[$i]) ? $dataLlamadasContestadas[$i] : 0), "series" => 1, "seriesIndex" => 1];
                $dataLlamadasFinal[2][] = ["x" => $i, "y" => (isset($dataLlamadasNoContestadas[$i]) ? $dataLlamadasNoContestadas[$i] : 0), "series" => 2, "seriesIndex" => 2];
                //}
                // if (isset($dataLlamadasContestadas[$i])) {
                //     $totalPorHora[$i] = $dataLlamadasContestadas[$i];
                // }
                //if ($dataLlamadas[$i] > 0 || $dataLlamadasContestadas[$i] > 0 || $dataLlamadasNoContestadas[$i] > 0) {
                $lineaTitulos[] = date("H:i", $i);
                $lineaValores["llamadas_marcadas"][] = (isset($dataLlamadas[$i]) ? $dataLlamadas[$i] : 0);
                $lineaValores["llamadas_atendidas"][] = (isset($dataLlamadasContestadas[$i]) ? $dataLlamadasContestadas[$i] : 0);
                $lineaValores["llamadas_no_atendidas"][] = (isset($dataLlamadasNoContestadas[$i]) ? $dataLlamadasNoContestadas[$i] : 0);
                //}
            }

            for ($i = $desde; $i <= $hasta; $i += $factor1) {
                if (isset($dataLlamadasContestadas1[$i])) {
                    $totalPorHora[$i] = $dataConstestadasPorHora[$i];
                }
                if (isset($dataLlamadasNoContestadas1[$i])) {
                    $totalPorHoraNo[$i] = $dataNoConstestadasPorHora[$i];
                }
            }

            //arsort($totalPorHora);
            $cuantos = count($totalPorHora) > 5 ? 5 : count($totalPorHora);
            $dias = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
            $i = 0;
            foreach ($totalPorHora as $key => $value) {
                $desde = date("H:i", ($key)); //8:00
                $hasta = "-" . date("H:i", ($key + 3540)); //8:59
                switch ($filtroTiempo) {
                    case 'semana':
                        $desde = $dias[intval(date("w", ($key)))];
                        $hasta = "";
                        break;
                    case 'semanaanterior':
                        $desde = $dias[intval(date("w", ($key)))];
                        $hasta = "";
                        break;
                    case 'mes':
                        $desde = date("d/m/Y", ($key));
                        $hasta = "";
                        break;
                    case 'mesanterior':
                        $desde = date("d/m/Y", ($key));
                        $hasta = "";
                        break;
                    case 'rango':
                        if ($hasta - $desde > 86400) {
                            $desde = date("d/m/Y", ($key));
                            $hasta = "";
                        }
                        break;
                }
                $graficoBarras[$key] = [
                    "label" => $desde . $hasta,
                    "value" => $value
                ];

                $topValores[date("H:i", $key)]["atendidas"] = $value;
                $i++;
                //si quiero limitar lo mostrado
                // if ($i >= $cuantos) {
                //     break;
                // }
            }

            foreach ($totalPorHoraNo as $key => $value) {
                $desde = date("H:i", ($key)); //8:00
                $hasta = "-" . date("H:i", ($key + 3540)); //8:59
                switch ($filtroTiempo) {
                    case 'semana':
                        $desde = $dias[intval(date("w", ($key)))];
                        $hasta = "";
                        break;
                    case 'semanaanterior':
                        $desde = $dias[intval(date("w", ($key)))];
                        $hasta = "";
                        break;
                    case 'mes':
                        $desde = date("d/m/Y", ($key));
                        $hasta = "";
                        break;
                    case 'mesanterior':
                        $desde = date("d/m/Y", ($key));
                        $hasta = "";
                        break;
                    case 'rango':
                        if ($hasta - $desde > 86400) {
                            $desde = date("d/m/Y", ($key));
                            $hasta = "";
                        }
                        break;
                }
                $graficoBarrasNo[$key] = [
                    "label" => $desde . $hasta,
                    "value" => $value
                ];
                $topValores[date("H:i", $key)]["realizadas"] = $value;
                $i++;
                //si quiero limitar lo mostrado
                // if ($i >= $cuantos) {
                //     break;
                // }
            }
            //sort($graficoBarras);

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
            // [
            //     "values" => $dataLlamadasError,
            //     "key" => "Error"
            // ]            
        }

        // $dataGraficoB = [
        //     "key" => "Llamadas atendidas",
        //     "values" => array_values($graficoBarras),
        //     "color" => $paletaColor[2]
        // ];
        // $dataGraficoB1 = [
        //     "key" => "Llamadas marcadas",
        //     "values" => array_values($graficoBarrasNo),
        //     "color" => $paletaColor[1]
        // ];

        $json["respuesta"] = $respuesta;
        // if ($permisos["soyDesarrollo"]) {
        //     $json["top"] = [$dataGraficoB, $dataGraficoB1];
        // } else {
        //     $json["top"] = [$dataGraficoB];
        // }

        // $topTitulos = array_reverse($topTitulos);
        // $topValores["atendidas"] = array_reverse($topValores["atendidas"]);
        // $topValores["realizadas"] = array_reverse($topValores["realizadas"]);

        foreach ($topValores as $key => $value) {
            if (!isset($value["realizadas"])) {
                $topValores[$key]["realizadas"] = 0;
            }
            if (!isset($value["atendidas"])) {
                $topValores[$key]["atendidas"] = 0;
            }
        }
        krsort($topValores);
        $topTitulos = [];
        $topValues = [];
        foreach ($topValores as $key => $value) {
            $topTitulos[] = $key;
            $topValues["realizadas"][] = $value["realizadas"];
            $topValues["atendidas"][] = $value["atendidas"];
        }

        $json["top"] = [$topTitulos, $topValues];
        $json["concurrencias"] = $concurrencias;
        $json["graficoLinea"] = [
            "titulos" => $lineaTitulos,
            "valores" => $lineaValores
        ];
        #endregion 
        break;
    case "graficoDiaOld":
        #region graficoDiaOld 
        $d = jsonStart();
        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $horaHasta = isset($cnf["Hora final llamadas"][0]) ? $cnf["Hora final llamadas"][0] : 17;
        $configConcurrencias = isset($cnf["Máximo de concurrencia"]) ? $cnf["Máximo de concurrencia"] : [];

        $horaDesde = explode(":", $horaDesde)[0];
        $horaHasta = explode(":", $horaHasta)[0];

        $concurrencias = [];
        foreach ($configConcurrencias as $value) {
            $partes = explode(":::", $value);
            $concurrencias[] = ["nombre" => strtoupper($partes[0]), "valor" => $partes[1]];
        }

        $condicion = [];
        $desde = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
        //$hasta = strtotime(date("Y-m-d") . " " . $horaHasta . ":59:59");
        $horaHoy = date("H");
        $hasta =  $horaHoy < $horaHasta ? strtotime("+5 minutes") : strtotime(date("Y-m-d") . " " . $horaHasta . ":59:59");
        // $desde = strtotime("2025-06-12 00:00:00");
        // $hasta = strtotime("2025-06-12 23:59:59");
        $condicion["av_fechaGeneraLlamada"] = ['$gte' => $desde, '$lte' => $hasta];
        $condicion["av_estadoEnvio"] = ['$in' => ["FINALIZADA", "ERROR", "ERROR_GENERAR_LLAMADA", "EN_PROGRESO", "MARCAR_LUEGO"]];
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condicion["av_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["av_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condicion["av_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicion["av_producto"] = $filtroProducto;
        }
        if ($_SESSION[MID . "userId"] != 910753601 && $_SESSION[MID . "userId"] != 910848422) {
            $condicion["av_carteraId"] = 39; //TODO: quitar PS
        }

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avProgramadas", $condicion);
        $respuesta = [];
        if ($cursor > 0) {
            $dataLlamadas = [];
            while ($row = $mongo->siguientex()) {
                $h = date("Y-m-d H:i", $row["av_procesado"]);
                //$h = date("Y-m-d", $row["av_procesado"]) . " 00:00:00";
                $sinHora = strtotime($h);
                $dataLlamadas[$sinHora] = isset($dataLlamadas[$sinHora]) ? $dataLlamadas[$sinHora] + 1 : 1;
            }

            // $desde = strtotime(date("Y-m-d " . $horaDesde . ":00:00"));
            // $hasta = strtotime(date("Y-m-d " . $horaHasta . ":59:00"));
            $dataLlamadasFinal = [];
            // $dataLlamadasProgreso = [];
            // $dataLlamadasError = [];
            // $dataLlamadaIniciadas = [];

            //por hora
            for ($i = $desde; $i <= $hasta; $i += 60) {
                //if (count($dataLlamadas) > 0) {
                $dataLlamadasFinal[] = ["x" => $i, "y" => (isset($dataLlamadas[$i]) ? $dataLlamadas[$i] : 0), "series" => 0, "seriesIndex" => 0];
                //}
            }
            if (count($dataLlamadasFinal) > 0) {
                $respuesta[] = [
                    "values" => $dataLlamadasFinal,
                    "key" => "Llamadas marcadas"
                ];
            }
            // [
            //     "values" => $dataLlamadasError,
            //     "key" => "Error"
            // ]            
        }

        $json["respuesta"] = $respuesta;
        $json["concurrencias"] = $concurrencias;
        #endregion 
        break;
    case "generarLlamada":
        #region generarLlamada 
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $nuevo = [
            "av_estadoEnvio" => "PENDIENTE",
            "av_procesado" => 0,
            "av_fechaGeneraLlamada" => 0
        ];
        $cursor = $mongo->actualizar("avProgramadas", ["_id" => $mongo->String2MongoId($id)], $nuevo);
        if ($cursor > 0) {
            $json["respuesta"] = "Llamada reprogramada";
        } else {
            $json["respuesta"] = "No se pudo reprogramar la llamada";
        }
        #endregion 
        break;
    case "totalesGestion":
        $d = jsonStart();

        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $condiciones = [];
        $campo = "av_fecha";
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
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

            $condiciones = [$campo => ['$gte' => $desde, '$lte' => $hasta]];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condiciones["av_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condiciones["av_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condiciones["av_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condiciones["av_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condiciones["av_producto"] = $filtroProducto;
        }

        if ($_SESSION[MID . "userId"] != 910753601 && $_SESSION[MID . "userId"] != 910848422) {
            $condiciones["av_carteraId"] = 39; //TODO: quitar PS
        }

        $cuadros = [
            [
                "Cartera gestionada",
                "0%",
                0,
                "",
                [
                    [$paletaColor[8], "Contacto directo"],
                    [$paletaColor[4], "Contacto indirecto"],
                    [$paletaColor[3], "Sin contacto"]
                ]
            ],
            [
                "Cartera gestionada con contacto",
                "0%",
                0,
                "",
                [
                    [$paletaColor[8], "Contacto directo"],
                    [$paletaColor[4], "Contacto indirecto"],
                ]
            ],
            [
                "Cartera no gestionada",
                "0%",
                0,
                "",
                []
            ]
        ];
        $row = null;
        switch ($filtroTiempo) {
            case 'hora':
                $hoy = strtotime(date("Y-m-d") . " 00:00:00");
                $row = obtenerTotalesDia($hoy, $condiciones);
                break;
            case 'dia':
                $hoy = strtotime(date("Y-m-d") . " 00:00:00");
                $row = obtenerTotalesDia($hoy, $condiciones);
                break;
            case 'ayer':
                $hoy = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                $row = obtenerTotalesDia($hoy, $condiciones);
                break;
            case 'semana':
                $row = obtenerTotalesRango($desde, $hasta, $condiciones);
                break;
            case 'semanaanterior':
                $row = obtenerTotalesRango($desde, $hasta, $condiciones);
                break;
            case 'mes':
                $row = obtenerTotalesRango($desde, $hasta, $condiciones);
                break;
            case 'mesanterior':
                $row = obtenerTotalesRango($desde, $hasta, $condiciones);
                break;
            case 'rango':
                $row = obtenerTotalesRango($desde, $hasta, $condiciones);
                break;
        }

        if ($row != null) {
            if ($row["total"] > 0) {
                $cuadros[0][1] = formatea_numero(((($row["contacto_directo"] + $row["contacto_indirecto"] + $row["sin_contacto"]) * 100) / $row["total"]), 2, ",", ".") . "%";
                $cuadros[1][1] = formatea_numero(((($row["contacto_directo"] + $row["contacto_indirecto"]) * 100) / $row["total"]), 2, ",", ".") . "%";
                $cuadros[2][1] = formatea_numero((($row["sin_gestion"] * 100) / $row["total"]), 2, ",", ".") . "%";
            }
            $cuadros[0][2] = $row["contacto_directo"] + $row["contacto_indirecto"] + $row["sin_contacto"];
            $cuadros[1][2] = $row["contacto_directo"] + $row["contacto_indirecto"];
            $cuadros[2][2] = $row["sin_gestion"];

            $cuadros[0][3] = $cuadros[0][2] . " de " . $row["total"];
            $cuadros[1][3] = $cuadros[1][2] . " de " . $row["total"];
            $cuadros[2][3] = $cuadros[2][2] . " de " . $row["total"];
        }

        $json["respuesta"]["cuadros"] = $cuadros;
        break;
    case "detalleGraficosLlamadas":
        #region detalleGraficosLlamadas 
        $d = jsonStart();
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $filtroLote = expect_safe_html($d["filtroLote"]);
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroCampania = expect_safe_html($d["filtroCampania"]);
        $filtroProveedor = expect_safe_html($d["filtroProveedor"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);

        $horaDesde = isset($cnf["Hora inicio llamadas"][0]) ? $cnf["Hora inicio llamadas"][0] : 8;
        $horaDesde = explode(":", $horaDesde)[0];

        $condicion = [];
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    // $desde = strtotime("-1 hour");
                    // $hasta = time();
                    $desde = strtotime(date("Y-m-d H") . ":00:00");
                    $hasta = strtotime(date("Y-m-d H") . ":59:00");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'dia':
                    $desde = strtotime(date("Y-m-d") . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d") . " 23:59:59");
                    $desdeLinea = strtotime(date("Y-m-d") . " " . $horaDesde . ":00:00");
                    $hastaLinea =  strtotime("+5 minutes");
                    break;
                case 'ayer':
                    $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'semana':
                    $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'semanaanterior':
                    $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'mes':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'mesanterior':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    $desdeLinea = $desde;
                    $hastaLinea =  $hasta;
                    break;
            }
            $condicion["fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }

        if ($filtroLote != "" && $filtroLote != "todo") {
            $condicion["lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["cartera"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["campania"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condicion["proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicion["producto"] = $filtroProducto;
        }


        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avLlamadasMarcadas", $condicion, [], ["fecha" => 1]);
        $totalExito = 0;
        $totalError = [];
        $totalErrorNumero = 0;
        $totalReintentos = 0;
        $totalEnLinea = 0;
        $dataLlamadas = [];
        $dataLlamadasEstado = [];
        $llamadasTelefono = [];
        $inicio = "";
        $fin = "";

        if ($cursor > 0) {
            while ($row = $mongo->siguientex()) {
                $inicio = $inicio == "" ? $row["fecha"] : $inicio;
                $fin = $row["fecha"];
                if ($row["exito"] == 1) {
                    $totalExito++;
                }
                if ($row["exito"] == 0) {
                    $err = $row["error"] != "" ? $row["error"] : "Error desconocido";
                    if (isset($totalError[$err])) {
                        $totalError[$err]++;
                    } else {
                        $totalError[$err] = 1;
                    }
                    $totalErrorNumero++;
                }
                if (isset($row["reintento"]) && $row["reintento"] == 1) {
                    $totalReintentos++;
                }
                if (isset($row["duracion"]) && $row["duracion"] > 0) {
                    $totalEnLinea += $row["duracion"];
                }

                $h = date("Y-m-d H:i", $row["fecha"]);
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
                        break;
                    case 'semanaanterior':
                        $h = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'mes':
                        $h = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'mesanterior':
                        $h = date("Y-m-d", $row["fecha"]);
                        break;
                    case 'rango':
                        if ($hasta - $desde < 86400) {
                            $h = date("Y-m-d H:i", $row["fecha"]);
                        } else {
                            $h = date("Y-m-d", $row["fecha"]);
                        }
                        break;
                }
                $sinHora = strtotime($h);
                $dataLlamadas[$sinHora] = isset($dataLlamadas[$sinHora]) ? $dataLlamadas[$sinHora] + 1 : 1;

                if ($row["exito"] == 1) {
                    $dataLlamadasEstado["Atendida"][$sinHora] = isset($dataLlamadasEstado["Atendida"][$sinHora]) ? $dataLlamadasEstado["Atendida"][$sinHora] + 1 : 1;
                } else {
                    $err = isset($row["error"]) && $row["error"] != "" ? $row["error"] : "Error desconocido";
                    $dataLlamadasEstado[$err][$sinHora] = isset($dataLlamadasEstado[$err][$sinHora]) ? $dataLlamadasEstado[$err][$sinHora] + 1 : 1;
                }

                if (isset($llamadasTelefono[$row["numero"]])) {
                    $llamadasTelefono[$row["numero"]]++;
                } else {
                    $llamadasTelefono[$row["numero"]] = 1;
                }
            }
        }


        $dataLlamadasFinal = [];
        $factor = 1;
        switch ($filtroTiempo) {
            case 'hora':
                $factor = 1;
                break;
            case 'dia':
                $factor = 60;
                break;
            case 'ayer':
                $factor = 60;
                break;
            case 'semana':
                $factor = 86400;
                break;
            case 'semanaanterior':
                $factor = 86400;
                break;
            case 'mes':
                $factor = 86400;
                break;
            case 'mesanterior':
                $factor = 86400;
                break;
            case 'rango':
                //es un dia o mas?
                if ($hastaLinea - $desdeLinea < 86400) {
                    $factor = 60;
                } else {
                    $factor = 86400;
                }
                break;
        }

        foreach ($dataLlamadasEstado as $estado => $value) {
            $e = isset($traduceDesconexion[$estado]) ? $traduceDesconexion[$estado] : $estado;

            for ($i = $desdeLinea; $i <= $hastaLinea; $i += $factor) {
                $dataLlamadasFinal[$e][] = ["x" => $i, "y" => (isset($value[$i]) ? $value[$i] : 0), "series" => 0, "seriesIndex" => 0, "tooltip" => "el tool"];
            }
        }

        $datosGraficoLinea = [];
        $i = 3;
        foreach ($dataLlamadasFinal as $estado => $value) {
            $datosGraficoLinea[] = [
                "values" => $value,
                "key" => $estado,
                "color" => ($estado == "Atendida" ? $paletaColor[2] : ($estado == "Sin respuesta" ? $paletaColor[1] : ($estado == "Falla en marcador" ? $paletaColor[0] : $paletaColor[$i])))
            ];
            $i = $i == count($paletaColor) - 1 ? 3 : $i + 1;
        }

        $contador = 0;
        foreach ($llamadasTelefono as $key => $value) {
            $contador += $value;
        }
        $promedioPorNumero = count($llamadasTelefono) > 0 ? round($contador / (count($llamadasTelefono)), 0) : 0;

        $contador = 0;
        foreach ($dataLlamadas as $key => $value) {
            $contador += $value;
        }
        $promedioPorMinuto = count($dataLlamadas) > 0 ? round($contador / (count($dataLlamadas)), 0) : 0;

        $cuadrosErrores = [];
        $elTotal = $totalExito + $totalErrorNumero;
        $j = 4;
        foreach ($totalError as $key => $value) {
            $e = isset($traduceDesconexion[$key]) ? $traduceDesconexion[$key] : $key;
            $promedio = round($value * 100 / $elTotal, 2);
            $cuadrosErrores[] = [$e, $value, "", $e . " (" . $promedio . "%)", $e == "Sin respuesta" ? $paletaColor[1] : ($e == "Falla en marcador" ? $paletaColor[0] : $paletaColor[$j]), $j];
            $j = $j == count($paletaColor) - 1 ? 4 : $j + 1;
        }

        $promedioExito = $elTotal > 0 ? (round($totalExito * 100 / $elTotal, 2)) : 0;
        $promedioReintento = $elTotal > 0 ? (round($totalReintentos * 100 / $elTotal, 2)) : 0;

        $tiempo = "";
        $tooltip = "";
        if ($totalEnLinea > 86400) {
            //$tiempo = (gmdate("d H:i", $totalEnLinea));
            $days = floor($totalEnLinea / 86400);
            $remainingSeconds = $totalEnLinea % 86400;
            $time = gmdate("H:i", $remainingSeconds);

            if ($days > 0) {
                $tiempo = $days . " " . $time;
            } else {
                $tiempo = (gmdate("H:i.s", $totalEnLinea));
            }
            $tooltip = "Total del tiempo en línea mostrado en días, horas y minutos. " . ($totalEnLinea > 3600 ? (round($totalEnLinea / 60, 2)) . " minutos." : "");
        } else {
            $tiempo = (gmdate("H:i.s", $totalEnLinea));
            $tooltip = "Total del tiempo en línea mostrado en horas, minutos y segundos. " . ($totalEnLinea > 3600 ? (round($totalEnLinea / 60, 2)) . " minutos." : "");
        }

        $cuadros =  [
            ["Total", $elTotal, "fa fa-hashtag", "Total de llamadas realizadas", "black"],
            ["Atendidas", $totalExito, "fa fa-hashtag", "Total de llamadas atendidas (" . $promedioExito . "%)", $paletaColor[2]],
            ["Reintentos", $totalReintentos, "fa fa-hashtag", "Llamadas de reintento (" . $promedioReintento . "%)", "black"],
            ["Llams. x minuto", $promedioPorMinuto, "fa fa-hashtag", "Promedio de llamadas por minuto", "black"],
            ["Tiempo en línea", $tiempo, "fa fa-hashtag", $tooltip, "black"]
        ];
        array_splice($cuadros, 2, 0, $cuadrosErrores);

        if ($fin != "" && $inicio != "") {
            if ($fin - $inicio < 86400) {
                $inicio = date("H:i:s", $inicio);
                $fin = date("H:i:s", $fin);
            } else {
                $inicio = date("d/m/Y H:i:s", $inicio);
                $fin = date("d/m/Y H:i:s", $fin);
            }
        }

        $json["respuesta"] = [
            "inicioGestion" => $inicio,
            "finGestion" => $fin,
            "cuadros" => $permisos["soyDesarrollo"] ? $cuadros : [],
            "promedioPorMinuto" => $permisos["soyDesarrollo"] ? $promedioPorMinuto : 0,
            "totalReintentos" => $permisos["soyDesarrollo"] ? $totalReintentos : 0,
            "totalExito" => $permisos["soyDesarrollo"] ? $totalExito : 0,
            "totalErrorGeneral" => $permisos["soyDesarrollo"] ? $totalErrorNumero : 0,
            "totalError" => $permisos["soyDesarrollo"] ? $totalError : 0,
            "total" => ($totalExito + $totalErrorNumero),
            "graficoGeneral" => [
                [
                    "key" => "Total llamadas realizadas",
                    "values" => [
                        ["label" => "Atendidas", "value" => $totalExito, "color" => $paletaColor[0]],
                        //["label" => "Error", "value" => $totalErrorNumero, "color" => $paleta[1]],
                    ]
                ]
            ],
            "dataGraficoLineaLlamada" => $permisos["soyDesarrollo"] ? $datosGraficoLinea : [],
        ];

        if ($permisos["soyDesarrollo"]) {
            $h = 3;
            foreach ($totalError as $key => $value) {
                $json["respuesta"]["graficoGeneral"][0]["values"][] = [
                    "label" => isset($traduceDesconexion[$key]) ? $traduceDesconexion[$key] : $key,
                    "value" => $value,
                    "color" => $paletaColor[$h]
                ];
                $h = $h == count($paletaColor) - 1 ? 3 : $h + 1;
            }
        }
        #endregion 
        break;
    case "ultimasAlertasJambonz":
        #region ultimasAlertasJambonz 
        $d = jsonStart();
        require_once("../canalesMasivos/apis/class.jambonzAPI.php");
        $jambonzAPI = new jambonzAPI();
        $data = [
            "cuantos" => 5,
            "pagina" => 1,
            "desde" => date("Y-m-d") . "T00:00:00.000Z",
            "hasta" => date("Y-m-d") . "T23:59:59.000Z",
        ];
        $alertas = $jambonzAPI->api_leerAlertas($data);

        if (isset($alertas["datos"])) {
            foreach ($alertas["datos"][0] as $key => $value) {
                $f = date("d/m/Y H:i:s", strtotime($value["time"]));
                $alertas["datos"][0][$key]["mostrar"] = false;
                $alertas["datos"][0][$key]["fechaLocal"] = $f;
            }
        }
        $json["respuesta"] = $alertas["datos"][0];
        $json["total"] = $alertas["datos"][1];

        //servicios link
        $redis = new MYREDISDB();
        $actualiza = unserialize($redis->query("get", "actualizarEstadoLlamadasAgenteVirtual"));
        $genera = unserialize($redis->query("get", "generaLlamadasAgenteVirtual"));
        $tipifica = unserialize($redis->query("get", "trazaGeneraEventosAgenteVirtual"));
        $json["logActualiza"] = $permisos["soyDesarrollo"] ? $actualiza : "";
        $json["logGenera"] = $permisos["soyDesarrollo"] ? $genera : "";
        $json["logTipifica"] = $permisos["soyDesarrollo"] ? $tipifica : "";

        #endregion 
        break;
}

jsonEnd($json, $limpiar);

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

//obtiene el total de lo cargado y gestionado en un rango de fecha
function obtenerTotalesRango($desde, $hasta, $condicion)
{
    $mongo = new MYMONGODB();
    $cedulasEnCarga = [];
    $cedulasGestionadas = [];
    for ($i = $desde; $i <= $hasta; $i += 86400) {
        $dinamico = date("dmY", $i);
        //$coleccion = "cbCargaEtl_" . $dinamico;
        $coleccion = "cbCargaEtlDetalleCargas";
        $condicioncarga = ["carga_" . $dinamico => ['$exists' => true]];
        $totalCarga = $mongo->buscar($coleccion, $condicioncarga);

        if ($totalCarga == 0) {
            $dia = date("w", $i);
            if ($dia == 1) {
                $dinamico = date("dmY", ($i - 172800)); //hace dos dias
                //$coleccion = "cbCargaEtl_" . $dinamico;
                $condicioncarga = ["carga_" . $dinamico => ['$exists' => true]];
                $totalCarga = $mongo->buscar($coleccion, $condicioncarga);
            }
        }

        while ($carga = $mongo->siguientex()) {
            $cedulasEnCarga[$carga["carteraEtl_cedula"]] = $carga["carteraEtl_cedula"];
        }
        $condicion["av_fecha"] = ['$gte' => $i, '$lte' => $i + 86399];
        $programadas = $mongo->buscar("avProgramadas", $condicion);
        $repetido = 0;
        while ($programada = $mongo->siguientex()) {
            if (
                $programada["av_estadoEnvio"] != "DESPROGRAMADO_ASIGNACION"
                && $programada["av_estadoEnvio"] != "DESPROGRAMADO_VERIFICACION"
                && $programada["av_estadoEnvio"] != "PENDIENTE"
                && $programada["av_estadoEnvio"] != "MARCAR_LUEGO"
                && $programada["av_estadoEnvio"] != "ERROR_GENERAR_LLAMADA"
                && $programada["av_estadoEnvio"] != "EN_PROGRESO"
                //&& $programada["av_estadoEnvio"] != "ERROR"
            ) {
                if (isset($cedulasGestionadas[$programada["av_cedula"]])) {
                    $repetido++;
                    if (isset($programada["av_tipificacion"]["respuesta1"])) {
                        if ($programada["av_tipificacion"]["respuesta1"] = "CONTACTO DIRECTO") {
                            $cedulasGestionadas[$programada["av_cedula"]] == "CONTACTO DIRECTO";
                        } else if ($programada["av_tipificacion"]["respuesta1"] == "CONTACTO INDIRECTO" && $cedulasGestionadas[$programada["av_cedula"]] == "SIN CONTACTO") {
                            $cedulasGestionadas[$programada["av_cedula"]] = "CONTACTO INDIRECTO";
                        } else {
                            $cedulasGestionadas[$programada["av_cedula"]] = "SIN CONTACTO";
                        }
                    } else {
                        $cedulasGestionadas[$programada["av_cedula"]] = "SIN CONTACTO";
                    }
                } else {
                    if (isset($programada["av_tipificacion"]["respuesta1"])) {
                        $cedulasGestionadas[$programada["av_cedula"]] = $programada["av_tipificacion"]["respuesta1"];
                    } else {
                        //$cedulasGestionadas[$programada["av_cedula"]] = "SIN GESTION";
                        $cedulasGestionadas[$programada["av_cedula"]] = "SIN CONTACTO";
                    }
                }
            }
        }
    }
    //print_h($repetido);

    $cedulasSinGestion = 0;
    $cedulasContactoDirecto = 0;
    $cedulasContactoIndirecto = 0;
    $cedulasSinContacto = 0;

    if (count($cedulasEnCarga) > 0) {
        foreach ($cedulasEnCarga as $cedula) {
            if (array_key_exists($cedula, $cedulasGestionadas)) {
                if ($cedulasGestionadas[$cedula] == "CONTACTO DIRECTO") {
                    $cedulasContactoDirecto++;
                }
                if ($cedulasGestionadas[$cedula] == "CONTACTO INDIRECTO") {
                    $cedulasContactoIndirecto++;
                }
                if ($cedulasGestionadas[$cedula] == "SIN CONTACTO") {
                    $cedulasSinContacto++;
                }
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
    return devolverTotales($cedulasContactoDirecto, $cedulasContactoIndirecto, $cedulasSinContacto, count($cedulasEnCarga));
}

//obtiene el total de lo cargado y gestionado en un dia
function obtenerTotalesDia($fecha, $condicion)
{
    $dinamico = date("dmY", $fecha);
    $mongo = new MYMONGODB();
    //$coleccion = "cbCargaEtl_" . $dinamico;
    $coleccion = "cbCargaEtlDetalleCargas";
    $condicioncarga = ["carga_" . $dinamico => ['$exists' => true]];
    $cuantos = $mongo->buscar($coleccion, $condicioncarga);
    $items = [
        "fecha" => $fecha,
        "fecha_formato" => date("Y-m-d", $fecha),
        "total" => $cuantos,
        "programadas" => 0,
        "contacto_directo" => 0,
        "contacto_indirecto" => 0,
        "sin_contacto" => 0,
        "sin_gestion" => 0,
        "actualizado" => time()
    ];
    //$diaFin = $fecha + 86399;
    //$condicion = ["av_fecha" => ['$gte' => $fecha, '$lte' => $diaFin], "av_carteraId" => 39];
    $programadas = $mongo->buscar("avProgramadas", $condicion);
    $items["programadas"] = $programadas;

    $contacto_directo = 0;
    $contacto_indirecto = 0;
    $sin_contacto = 0;
    if ($programadas > 0) {
        while ($row = $mongo->siguientex()) {
            if (isset($row["av_tipificacion"])) {
                if ($row["av_tipificacion"]["respuesta1"] != "") {
                    if ($row["av_tipificacion"]["respuesta1"] === "CONTACTO DIRECTO") {
                        $contacto_directo++;
                    } else if ($row["av_tipificacion"]["respuesta1"] === "CONTACTO INDIRECTO") {
                        $contacto_indirecto++;
                    } else {
                        $sin_contacto++;
                    }
                } else {
                    //$sin_contacto++;
                }
            } else {
                // if (
                //     $row["av_estadoEnvio"] == "DESPROGRAMADO_VERIFICACION"
                // ) {
                //     //$contacto_directo++;
                // } else {
                //     $sin_contacto++;
                // }
            }
        }
        //si total es cero, valido si es lunes y busco la base del sabado que es la que se procesa el lunes
        if ($cuantos == 0) {
            $dia = date("w", $fecha);
            if ($dia == 1) {
                $dinamico = date("dmY", ($fecha - 172800)); //hace dos dias
                //$coleccion = "cbCargaEtl_" . $dinamico;
                $condicioncarga = ["carga_" . $dinamico => ['$exists' => true]];
                $cuantos = $mongo->buscar($coleccion, $condicioncarga);
                //$items["total"] = $cuantos;
            }
        }
    }
    return devolverTotales($contacto_directo, $contacto_indirecto, $sin_contacto, $cuantos);
}

function devolverTotales($contacto_directo, $contacto_indirecto, $sin_contacto, $total)
{
    $items["contacto_directo"] = $contacto_directo;
    $items["contacto_indirecto"] = $contacto_indirecto;
    $items["sin_contacto"] = $sin_contacto;
    $items["sin_gestion"] = $total > 0 ? ($total - ($contacto_directo + $contacto_indirecto + $sin_contacto)) : 0;
    $items["total"] = $total;
    return $items;
}

?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
