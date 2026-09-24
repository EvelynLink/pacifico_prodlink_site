<?php
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");

if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

$cnf = getConf("Canales Masivos");
$paletaColor = $cnf["Dashboard paleta colores"];

switch ($act) {
    case "hearthBeatServicio":
        $d = jsonStart();
        $redis = new MYREDISDB();
        $x = $redis->query("get", "conversacionesWhatsAppAgenteVirtual");
        $json["hearthBeat"] = $x > 0 ? date("d/m/Y H:i.s", $x) : $x;
        break;
    case "cargarTabula":
        #region cargarTabula 
        $d = jsonStart();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador([]);

        $coleccion = "tempLogWhatsappAgenteVirtual";
        $condition = [];

        $campos = [];

        $ngTabula->setQueryDatos(
            $coleccion,
            $condition,
            $campos,
            ['fecha' => -1]
        );

        $ngTabula->setPreparaDatos(function ($campos) {
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
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $desde = strtotime("-1 hour");
                    $hasta = time();
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
                case 'mes':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condiciones["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condiciones["ws_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condiciones["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condiciones["ws_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condiciones["ws_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condiciones["ws_producto"] = $filtroProducto;
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
                    'ws_lote' => 1
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'ws_lote' => '$ws_lote'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        $lotesu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_lote"] != "null") {
                $lotesu[] = [
                    "id" => $row["_id"]["ws_lote"],
                    "nombre" => date("d/m/Y H:i", $row["_id"]["ws_lote"])
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
                    'ws_proveedor' => 1
                ]
            ],
            [
                '$group' => [
                    "_id" => [
                        'ws_proveedor' => '$ws_proveedor'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        $proveedoresu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_proveedor"] != "null") {
                $proveedoresu[] = [
                    "id" => $row["_id"]["ws_proveedor"],
                    "nombre" => $row["_id"]["ws_proveedor"]
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
                'ws_carteraId' => 1,
                "ws_carteraNombre" => 1,
            ]],
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
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        $carterasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_carteraId"] != "null") {
                $carterasu[] = [
                    "id" => $row["_id"]["ws_carteraId"],
                    "nombre" => ($row["_id"]["ws_carteraNombre"] != null ? $row["_id"]["ws_carteraNombre"] : "Cartera ID: " . $row["_id"]["ws_carteraId"])
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
                'ws_campaniaId' => 1,
                "ws_campaniaNombre" => 1,
            ]],
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
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        $campaniasu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_campaniaId"] != "null") {
                $campaniasu[] = [
                    "id" => $row["_id"]["ws_campaniaId"],
                    "nombre" => ($row["_id"]["ws_campaniaNombre"] != null ? $row["_id"]["ws_campaniaNombre"] : "Campaña ID: " . $row["_id"]["ws_campaniaId"])
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
                'ws_producto' => 1
            ]],
            [
                '$group' => [
                    "_id" => [
                        'ws_producto' => '$ws_producto'
                    ],
                    "count" => ['$sum' => 1],
                ],
            ]
        ];
        if ($condicion != "") {
            array_unshift($options, $condicion);
        }
        $r = $mongo->aggregate("avProgramadasWhatsApp", $options);
        $productosu = [];
        while ($row = $mongo->siguiente()) {
            if ($row["_id"]["ws_producto"] != "null") {
                $productosu[] = [
                    "id" => $row["_id"]["ws_producto"],
                    "nombre" => $row["_id"]["ws_producto"]
                ];
            }
        }
        sort($productosu);
        $productos = array_merge($productos, $productosu);

        $json["lotes"] = $lotes;
        $json["carteras"] = $carteras;
        $json["campanias"] = $campanias;
        $json["proveedores"] = $proveedores;
        $json["productos"] = $productos;
        $json["permisos"] = $permisos;

        #endregion 
        break;
    case "cargarTabulaProgramadas":
        #region cargarTabulaProgramadas 
        $d = jsonStart();

        $filtroLote = expect_safe_html($_GET["filtroLote"]);
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroCampania = expect_safe_html($_GET["filtroCampania"]);
        $filtroProveedor = expect_safe_html($_GET["filtroProveedor"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador([]);

        $coleccion = "avProgramadasWhatsApp";
        $condition = [];

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $desde = strtotime("-1 hour");
                    $hasta = time();
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
                case 'mes':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($_GET["filtroDesde"]);
                    $filtroHasta = expect_integer($_GET["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condition["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condition["ws_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condition["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condition["ws_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condition["ws_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condition["ws_producto"] = $filtroProducto;
        }

        $campos = [];

        $ngTabula->setQueryDatos(
            $coleccion,
            $condition,
            $campos,
            ['ws_ultimaRespuestaAutomatica' => -1]
        );

        $ngTabula->setPreparaDatos(function ($campos) {
            return $campos;
        });

        $json = $ngTabula->responde();
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
        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {
                case 'hora':
                    $desde = strtotime("-1 hour");
                    $hasta = time();
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
                case 'mes':
                    $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                    break;
                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
            $condicion["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
            // $condicion['$or'] = [
            //     ["av_fecha" => ['$gte' => $desde, '$lte' => $hasta]],
            //     ["av_fechaGeneraLlamada" => ['$gte' => $desde, '$lte' => $hasta]]
            // ];
        }
        if ($filtroLote != "" && $filtroLote != "todo") {
            $condicion["ws_lote"] = intval($filtroLote);
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicion["ws_carteraId"] = intval($filtroCartera);
        }
        if ($filtroCampania != "" && $filtroCampania != "todo") {
            $condicion["ws_campaniaId"] = intval($filtroCampania);
        }
        if ($filtroProveedor != "" && $filtroProveedor != "todo") {
            $condicion["ws_proveedor"] = $filtroProveedor;
        }
        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicion["ws_producto"] = $filtroProducto;
        }

        //$condicion["ws_estadoEnvio"] = ['$in' => ["PENDIENTE", "CONTESTADO", "ENVIADO"]];

        $mongo = new MYMONGODB();
        $mongo1 = new MYMONGODB();
        $mongo2 = new MYMONGODB();
        $cursor = $mongo->buscar("avProgramadasWhatsApp", $condicion);
        $respuesta = [
            "cuadros" => [
                ["Programadas", 0, "Programados para gestionar", "0%"],
                ["Pendientes", 0, "Mensajes pendiente de envio", "0%"],
                ["No enviados", 0, "Mensajes que no se enviaron al cliente", "0%"],
                ["Recibidos", 0, "Mensajes recibidos", "0%"],
                ["Leídos", 0, "Mensajes leídos", "0%"],
                ["Contestados", 0, "Mensajes que se contestaron", "0%"],
                ["Promedio iteracciones", 0, "Número promedio de interacciones de los chats", "0%"]
            ],
            "dataGrafico" => []
        ];
        if ($cursor > 0) {
            $respuesta["cuadros"][0][1] = $cursor;
            $totalRecibido = 0;
            $totalLeido = 0;
            $totalRespondido = 0;
            $totalNoLeido = 0;
            $totalEnviado = 0;
            $totalNoEnviado = 0;
            $totalNoRecibidos = 0;
            $totalError = 0;
            $interacciones = 0;
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
                } else if (str_contains($row['ws_estadoEnvio'], 'ERROR')) {
                    $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                    $totalError++;
                } else {
                    //$cedulasGestionadas[$row["ws_cedula"]] = "SIN CONTACTO";
                    $cedulasGestionadas[$row["ws_factura"]] = "SIN CONTACTO";
                    //$totalNoRecibidos++;
                    $totalNoEnviado++;
                }
                if (isset($row["ws_numeroInteracciones"])) {
                    $interacciones += $row["ws_numeroInteracciones"];
                }
            }

            $respuesta["cuadros"][1][1] = $totalNoEnviado;
            $respuesta["cuadros"][2][1] = $totalError;
            $respuesta["cuadros"][3][1] = $totalRecibido + $totalNoLeido;
            $respuesta["cuadros"][3][2] = "Mensajes recibidos\r\n" . $totalRecibido . " con confirmación (doble marca azul)\r\n" . $totalNoLeido . " sin confirmación";

            $respuesta["cuadros"][4][1] = $totalLeido;
            $respuesta["cuadros"][5][1] = $totalRespondido;
            $respuesta["cuadros"][6][1] = $totalRespondido > 0 ? (formatea_numero(($interacciones / $totalRespondido), 2, ",", ".")) : 0;
            $porcentajeRespondidos = ($totalRespondido * 100) / $cursor;
            $porcentajeNoRespondido = ((($totalRecibido + $totalNoLeido) - $totalRespondido) * 100) / $cursor;

            $graficoFinalizadas = [];
            $graficoFinalizadas[] = ["porc" => formatea_numero($porcentajeRespondidos, 1, ",", "."), "key" => "Contestado", "y" => $totalRespondido, "color" => $paletaColor[0]];
            $graficoFinalizadas[] = ["porc" => formatea_numero($porcentajeNoRespondido, 1, ",", "."), "key" => "Sin interacción", "y" => (($totalRecibido + $totalNoLeido) - $totalRespondido), "color" => $paletaColor[1]];
            $respuesta["dataGrafico"] = $graficoFinalizadas;
        }

        $json["respuesta"] = $respuesta;
        #endregion 
        break;
    case "obtenerDetalleChat":
        #region obtenerDetalleChat 
        $d = jsonStart();
        $idChat  = expect_safe_html($d["idChat"]);
        $id = expect_safe_html($d["id"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("avProgramadasWhatsApp", ["_id" => $mongo->String2MongoId($id)]);
        $r = $mongo->siguiente();

        $mongo2 = new MYMONGODB();
        $mongo2->buscar("avParametros", ["_id" => $r["ws_agenteParametroId"]]);
        $agente = $mongo2->siguientex();
        $proveedor = $agente["proveedor"];

        if ($proveedor == "LINK") {
            require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
            $linkAPI = new linkLocalLlmAPI();
            $resp = $linkAPI->api_obtenerChat($idChat);

            $nuevo = [];
            if ($resp["estado"] == "OK") {
                $nuevo["chat_summary"] = "";
                $nuevo["user_sentiment"] = "";
                $nuevo["chat_successful"] = "";
                $nuevo["custom_analysis_data"] = [];
                $nuevo["retell_llm_dynamic_variables"] = $resp["datos"]["analisis"];
                $nuevo["chat_status"] = $resp["datos"]["estado"];
                $transcripcion = [];

                if (isset($resp["datos"]["mensajes"])) {
                    foreach ($resp["datos"]["mensajes"] as $value) {
                        if ($value["rol"] == "agente" || $value["rol"] == "usuario") {
                            $transcripcion[] = [
                                "direccion" => $value["rol"] == "agente" ? "agente" : "cliente",
                                "mensaje" => utf8_2_decode(base64_decode($value["mensaje"])),
                                "tiempoTranscurridoSegundos" => 0,
                                "fechaHora" => isset($value["fecha"]) ? date("d/m/Y H:i.s", $value["fecha"]) : "",
                            ];
                        }
                    }
                }

                $nuevo["transcript"] = $transcripcion;
                $nuevo["chat_cost"] = 0;
            }
            $json["respuesta"] = $nuevo;
        }

        if ($proveedor == "RETELL") {
            require_once("../canalesMasivos/apis/class.retellAPI.php");
            $retellAPI = new retellAPI();

            $resp = $retellAPI->api_obtenerDetalleChat($idChat, true);
            //print_h($resp);
            $nuevo = [];
            if ($resp["estado"] == "OK") {
                $nuevo["retell_llm_dynamic_variables"] = $resp["datos"]["retell_llm_dynamic_variables"];
                if (isset($resp["datos"]["chat_analysis"])) {
                    $nuevo["chat_summary"] = $resp["datos"]["chat_analysis"]["chat_summary"];
                    $nuevo["user_sentiment"] = $resp["datos"]["chat_analysis"]["user_sentiment"];
                    $nuevo["chat_successful"] = $resp["datos"]["chat_analysis"]["custom_analysis_data"]["chat_successful"] == 1 ? "SI" : "NO";
                    $nuevo["custom_analysis_data"] = $resp["datos"]["chat_analysis"]["custom_analysis_data"];
                } else {
                    $nuevo["chat_summary"] = "";
                    $nuevo["user_sentiment"] = "";
                    $nuevo["chat_successful"] = "";
                    $nuevo["custom_analysis_data"] = [];
                }
                $nuevo["chat_status"] = $resp["datos"]["chat_status"];
                $transcripcion = [];

                // $mongo = new MYMONGODB();
                // $mongo->buscar("avProgramadasWhatsApp", ["_id" => $mongo->String2MongoId($id)]);
                // $r = $mongo->siguiente();
                $transcripcion[] = [
                    "direccion" => "agente",
                    "mensaje" => $r["ws_plantilla"],
                    "tiempoTranscurridoSegundos" => $r["ws_fecha"],
                    "fechaHora" => date("d/m/Y H:i.s", $r["ws_fecha"]),
                ];

                if (isset($resp["datos"]["message_with_tool_calls"])) {
                    foreach ($resp["datos"]["message_with_tool_calls"] as $value) {
                        if ($value["role"] == "agent" || $value["role"] == "user") {
                            $transcripcion[] = [
                                "direccion" => $value["role"] == "agent" ? "agente" : "cliente",
                                "mensaje" => utf8_2_decode($value["content"]),
                                "tiempoTranscurridoSegundos" => isset($value["created_timestamp"]) ? floatval($value["created_timestamp"]) / 1000 : 0,
                                "fechaHora" => isset($value["created_timestamp"]) ? date("d/m/Y H:i.s", floatval($value["created_timestamp"]) / 1000) : "",
                            ];
                        }
                    }
                }
                $nuevo["transcript"] = $transcripcion;
                $nuevo["chat_cost"] = isset($resp["datos"]["chat_cost"]["combined_cost"]) ? ($resp["datos"]["chat_cost"]["combined_cost"] / 100) : 0;
            }
            $json["respuesta"] = $nuevo;
        }
        #endregion 
        break;
}

jsonEnd($json, $limpiar);

?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
