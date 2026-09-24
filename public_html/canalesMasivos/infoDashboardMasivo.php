<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

chdir(__DIR__);

require_once("/home/pacifico/_configBasico.inc.php");

global $Central;
$cnf = getConf("Canales Masivos");
$estadosConfig = $cnf["Dashboard equivalencias estados"];
$paletaColor = $cnf["Dashboard paleta colores"];

print_h(obtenerTotales(time(), '2120', 14669, 'llamada', 7));
function obtenerTotales($filtroDia, $filtroCartera, $filtroCampania, $filtroCanal, $filtroPeriodo)
{
    $condicion = [];
    $totalesWp = [];
    $totalesCorreos = [];
    $condicionCorreo = [];
    $condicionWhatsapp = [];
    $condicionPagos = [];
    $campo = "av_fecha";
    $desde = strtotime(date("Y-m-d", $filtroDia) . " 00:00:00");
    $hasta = strtotime(date("Y-m-d", $filtroDia) . " 23:59:59");
    $condicion[$campo] = ['$gte' => $desde, '$lte' => $hasta];
    $condicionCorreo["cem_susFechaAsignacion"] = ['$gte' => $desde, '$lte' => $hasta];
    $condicionWhatsapp["ws_fecha"] = ['$gte' => $desde, '$lte' => $hasta];
    $condicionPagos["pagos_proceso"] = ['$gte' => $desde, '$lte' => $hasta];
    if ($filtroCartera != "" && $filtroCartera != "todo") {
        $condicion["av_carteraId"] = intval($filtroCartera);
        if ($filtroPeriodo >= 0) {
            $condicion["av_periodo"] = $filtroPeriodo;
            $condicionWhatsapp["ws_periodo"] = $filtroPeriodo;
            $condicionPagos["pagos_periodo"] = $filtroPeriodo;
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
    if ($filtroCanal == "llamada" && $totalesLlamadas["totalIntensidad"] > 0) {
        $parametrosTotales["llamadas_av"] = $totalesLlamadas["gestionadas"];
        $parametrosTotalesIntensidades["llamadas_av"] = $totalesLlamadas["totalIntensidad"];
        $totalTotalIntensidad += $totalesLlamadas["totalIntensidad"];
        $contado++;
        $t += $totalesLlamadas["porcentajeAvance"];
    }
    if ($filtroCanal == "mail" && $totalesCorreos["totalIntensidad"] > 0) {
        $parametrosTotales["correo"] = $totalesCorreos["gestionadas"];
        $parametrosTotalesIntensidades["correo"] = $totalesCorreos["totalIntensidad"];
        $totalTotalIntensidad += $totalesCorreos["totalIntensidad"];
        $contado++;
        $t += $totalesCorreos["porcentajeAvance"];
    }
    if ($filtroCanal == "whatsapp" && $totalesWp["totalIntensidad"] > 0) {
        $parametrosTotales["whatsapp"] = $totalesWp["gestionadas"];
        $parametrosTotalesIntensidades["whatsapp"] = $totalesWp["totalIntensidad"];
        $totalTotalIntensidad += $totalesWp["totalIntensidad"];
        $contado++;
        $t += $totalesWp["porcentajeAvance"];
    }
    $parametrosTotalesIntensidades["todo"] = $totalTotalIntensidad;

    $totales = obtenerTotalesGestion($desde, $hasta, $parametrosTotales, $parametrosTotalesIntensidades, $pagos, $totalesLlamadas["compromisosPago"], $filtroCartera);
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

    return $json;
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
    $condicion["ws_estadoEnvio"] = ['$in' => ["PENDIENTE", "CONTESTADO", "ENVIADO"]];
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
    $totalNoRecibidos = 0;
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
                "montoTooltip" => "$0,00 de $0,00"
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
    $cedulasEnCarga = obtenerCedulasEnCarga($desde, $hasta, $filtroCartera);

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
            $fact = str_replace(["'", '"'], "", trim($carga["carteraEtl_factura"]));
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

?>
<? //_FIN_DE_ARCHIVO                                                                                                                                  
?>