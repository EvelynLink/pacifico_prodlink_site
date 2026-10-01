<?php

/**
 * Dashboard de seguimiento de gestiones (llamadas, WhatsApp, correo y pendientes).
 *
 * Los totales salen de la misma logica que cmDashboardGestionIndividualCtrl.php
 * (avProgramadas, avProgramadasWhatsApp, cbEnvioMails), el cuadro "Intentos" es el
 * cuadro "Marcadas" de cmReporteAgenteVirtualCtrl.php (avLlamadasMarcadas) y el
 * grafico de tipificaciones es el de "Resultado de gestion" del mismo reporte.
 * El detalle de audio, chat y correo replica el de cmDashboardGestionesCobranzaCtrl.php,
 * pero valida el permiso contra la cartera del propio documento.
 */

require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";

if (!isset($_REQUEST["act"])) {
    exit;
}

// Canales que acepta el filtro de canal (mismos valores que dashboardGestionIndividual)
const CANAL_LLAMADA = "llamada";
const CANAL_WHATSAPP = "wp";
const CANAL_CORREO = "mail";
const CANALES_DASHBOARD = [CANAL_LLAMADA, CANAL_WHATSAPP, CANAL_CORREO];

// Estados de avProgramadas que cuentan como pendientes (cuadros Pendientes + Marcar luego)
const ESTADOS_LLAMADA_PENDIENTE = ["PENDIENTE", "LLAMADA_GENERADA"];
const ESTADO_LLAMADA_MARCAR_LUEGO = "MARCAR_LUEGO";

// Estados que no entran al grafico de tipificaciones (igual que reporteAgenteVirtual)
const ESTADOS_SIN_TIPIFICACION = ["DESPROGRAMADO_ASIGNACION", "DESPROGRAMADO_VERIFICACION", "PENDIENTE", "MARCAR_LUEGO", "ERROR_GENERAR_LLAMADA"];
const LIMITE_TIPIFICACIONES = 25;

// Motivos de finalizacion que se cuentan como "Sin conexion" (igual que dashboardGestionIndividual)
const FINALIZACION_SIN_CONEXION = [
    "telephony_provider_permission_denied", "dial_failed", "error_user_not_joined", "concurrency_limit_reached",
    "no_valid_payment", "scam_detected", "error_retell", "error_unknown", "telephony_provider_unavailable",
    "user_declined", "invalid_destination", "sip_routing_error", "marked_as_spam",
];
// Motivos que son "Sin conexion" solo si la llamada no quedo tipificada
const FINALIZACION_SIN_CONEXION_SIN_TIPIFICAR = ["dial_busy", "dial_no_answer"];

// Campos que traen las tablas (sin el html del correo ni las transcripciones)
const CAMPOS_TABLA_LLAMADAS = ["av_factura", "av_nombre", "av_campaniaNombre", "av_telefono", "av_fecha", "av_fechaGeneraLlamada", "av_estadoEnvio", "av_tipificacion", "av_idConversacion"];
const CAMPOS_TABLA_WHATSAPP = ["ws_factura", "ws_nombre", "ws_campaniaNombre", "ws_telefono", "ws_fecha", "ws_estadoEnvio"];
const CAMPOS_TABLA_CORREO = ["cem_susFactura", "cem_susNombre", "cem_susCampaniaNombre", "cem_susEmail", "cem_susFechaAsignacion", "cem_susFechaEnvio", "cem_susLlamadaId", "cem_susErrorEnvio"];

// Alias de la columna Estado de las tablas: se calcula en PHP, asi que su filtro se traduce a una condicion a mano
const CAMPO_FILTRO_ESTADO = "estado";
// Condicion que no devuelve filas (filtro de estado sin ninguna coincidencia)
const CONDICION_SIN_RESULTADOS = ["_id" => ['$in' => []]];
// Icono de detalle de la pestania Llamadas segun el estado traducido (igual que el detalle de llamadas de reporteAgenteVirtual):
// activo, visible pero deshabilitado, y oculto para cualquier otro estado
const ESTADOS_DETALLE_ACTIVO = ["ATENDIDA", "NO ATENDIDA", "MARCAR LUEGO", "ERROR GENERAR LLAMADA"];
const ESTADOS_DETALLE_DESHABILITADO = ["EN PROGRESO", "PENDIENTE", "INICIADA"];

global $Central;
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];

$cnf = getConf("Canales Masivos");
$paletaColor = $cnf["Dashboard paleta colores"];
$traduceEstados = [];
foreach ($cnf["Dashboard equivalencias estados"] as $estado) {
    $partes = explode(":::", $estado);
    $traduceEstados[$partes[0]] = $partes[1];
}

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

$idsCarterasGeneral = obtenerCarterasPermitidas($permisos); // vacio = sin restriccion (Desarrollo)

switch ($act) {
    case "obtenerParametrizacion":
        #region obtenerParametrizacion
        $d = jsonStart();
        $filtros = leerFiltros($d);
        $condiciones = construirCondicionesCanal($filtros);
        $mongo = new MYMONGODB();

        // Opciones de cartera y campaña de los tres canales, con los filtros vigentes
        $carteras = [];
        agruparOpciones($mongo, $carteras, "avProgramadas", $condiciones["llamadas"], "av_carteraId", "av_carteraNombre", "Cartera ID: ");
        agruparOpciones($mongo, $carteras, "cbEnvioMails", $condiciones["correo"], "cem_susCarteraId", "cem_susCarteraNombre", "Cartera ID: ");
        agruparOpciones($mongo, $carteras, "avProgramadasWhatsApp", $condiciones["whatsapp"], "ws_carteraId", "ws_carteraNombre", "Cartera ID: ");
        $campanias = [];
        agruparOpciones($mongo, $campanias, "avProgramadas", $condiciones["llamadas"], "av_campaniaId", "av_campaniaNombre", "Campaña ID: ");
        agruparOpciones($mongo, $campanias, "cbEnvioMails", $condiciones["correo"], "cem_susCampaniaId", "cem_susCampaniaNombre", "Campaña ID: ");
        agruparOpciones($mongo, $campanias, "avProgramadasWhatsApp", $condiciones["whatsapp"], "ws_campaniaId", "ws_campaniaNombre", "Campaña ID: ");

        $json["carteras"] = ordenarOpciones($carteras);
        $json["campanias"] = ordenarOpciones($campanias);
        $json["periodos"] = obtenerCiclosCarteras($mongo, $filtros["carteras"], $carteras);
        $json["permisos"] = $permisos;
        #endregion
        break;

    case "obtenerTotales":
        #region obtenerTotales
        $d = jsonStart();
        $filtros = leerFiltros($d);
        $condiciones = construirCondicionesCanal($filtros);

        $llamadas = canalActivo($filtros, CANAL_LLAMADA) ? obtenerTotalesLlamadas($condiciones["llamadas"], $paletaColor) : totalesLlamadasVacios();
        $intentos = canalActivo($filtros, CANAL_LLAMADA)
            ? obtenerIntentosLlamadas($condiciones["marcadas"], $llamadas["atendidas"])
            : ["total" => 0, "tooltip" => "Total de llamadas marcadas"];
        $whatsapp = canalActivo($filtros, CANAL_WHATSAPP) ? obtenerTotalesWhatsapp($condiciones["whatsapp"]) : totalesWhatsappVacios();
        $correo = canalActivo($filtros, CANAL_CORREO) ? obtenerTotalesCorreo($condiciones["correo"]) : totalesCorreoVacios();

        $pendientesLlamadas = $llamadas["pendientes"] + $llamadas["marcarLuego"];
        $pendientesWhatsapp = $whatsapp["pendientes"];
        $pendientesCorreo = $correo["pendientes"];

        $json["canales"] = count($filtros["canales"]) > 0 ? $filtros["canales"] : CANALES_DASHBOARD;
        $json["cuadrosSuperiores"] = [
            "intentos" => $intentos,
            "whatsapp" => ["total" => $whatsapp["programadas"], "tooltip" => "Total de WhatsApp"],
            "correo" => ["total" => $correo["programadas"], "tooltip" => "Total de correos"],
            "pendientes" => [
                "total" => $pendientesLlamadas + $pendientesWhatsapp + $pendientesCorreo,
                "tooltip" => "Clientes por gestionar",
            ],
        ];
        $json["llamadas"] = ["cuadros" => $llamadas["cuadros"], "tipificaciones" => $llamadas["tipificaciones"]];
        $json["whatsapp"] = ["cuadros" => $whatsapp["cuadros"]];
        $json["correo"] = ["cuadros" => $correo["cuadros"]];
        $json["pendientes"] = [
            "cuadros" => [
                cuadro(CANAL_LLAMADA, "Llamadas", $pendientesLlamadas, "Llamadas pendientes y por marcar luego", "fa-phone"),
                cuadro(CANAL_WHATSAPP, "WhatsApp", $pendientesWhatsapp, "Mensajes pendientes de envío", "fa-whatsapp"),
                cuadro(CANAL_CORREO, "Email", $pendientesCorreo, "Correos pendientes de envío", "fa-envelope-o"),
            ],
        ];
        #endregion
        break;

    case "listaLlamadas":
        #region listaLlamadas
        $d = jsonStart();
        $filtroEstado = extraerFiltroEstado($d);
        $filtros = leerFiltros($_GET);
        $condicion = unirCondiciones(
            construirCondicionesCanal($filtros)["llamadas"],
            condicionBusqueda($filtros["buscar"], ["av_factura", "av_nombre", "av_cedula"]),
            condicionEstadoLlamada($filtroEstado, $traduceEstados)
        );
        $json = responderTabla($d, canalActivo($filtros, CANAL_LLAMADA), "avProgramadas", $condicion, CAMPOS_TABLA_LLAMADAS, ["av_fechaGeneraLlamada" => -1], limpiadorLlamadas(), function (array $fila) use ($traduceEstados): array {
            return filaLlamadaEnvio($fila, $traduceEstados);
        });
        #endregion
        break;

    case "listaWhatsapp":
        #region listaWhatsapp
        $d = jsonStart();
        $filtroEstado = extraerFiltroEstado($d);
        $filtros = leerFiltros($_GET);
        $condicion = unirCondiciones(
            construirCondicionesCanal($filtros)["whatsapp"],
            condicionBusqueda($filtros["buscar"], ["ws_factura", "ws_nombre", "ws_cedula"]),
            condicionEstadoCalculado($filtroEstado, condicionesEstadoWhatsapp())
        );
        $json = responderTabla($d, canalActivo($filtros, CANAL_WHATSAPP), "avProgramadasWhatsApp", $condicion, CAMPOS_TABLA_WHATSAPP, ["ws_fecha" => -1], limpiadorWhatsapp(), "filaWhatsapp");
        #endregion
        break;

    case "listaCorreo":
        #region listaCorreo
        $d = jsonStart();
        $filtroEstado = extraerFiltroEstado($d);
        $filtros = leerFiltros($_GET);
        $condicion = unirCondiciones(
            construirCondicionesCanal($filtros)["correo"],
            condicionBusqueda($filtros["buscar"], ["cem_susFactura", "cem_susNombre", "cem_susCedula"]),
            condicionEstadoCalculado($filtroEstado, condicionesEstadoCorreo(inicioDeHoy()))
        );
        $json = responderTabla($d, canalActivo($filtros, CANAL_CORREO), "cbEnvioMails", $condicion, CAMPOS_TABLA_CORREO, ["cem_susFechaAsignacion" => -1], limpiadorCorreo(), "filaCorreo");
        #endregion
        break;

    case "listaPendientes":
        #region listaPendientes
        // Una coleccion por canal: el canal lo elige el cuadro seleccionado en la pestaña Pendientes
        $d = jsonStart();
        $filtros = leerFiltros($_GET);
        $condiciones = construirCondicionesCanal($filtros);
        $canalPendiente = expect_safe_html($_GET["canalPendiente"] ?? CANAL_LLAMADA);
        switch ($canalPendiente) {
            case CANAL_WHATSAPP:
                $condicion = unirCondiciones($condiciones["whatsapp"], condicionWhatsappPendiente(), condicionBusqueda($filtros["buscar"], ["ws_factura", "ws_nombre", "ws_cedula"]));
                $json = responderTabla($d, canalActivo($filtros, CANAL_WHATSAPP), "avProgramadasWhatsApp", $condicion, CAMPOS_TABLA_WHATSAPP, ["ws_fecha" => 1], limpiadorWhatsapp(), "filaWhatsapp");
                break;
            case CANAL_CORREO:
                $condicion = unirCondiciones($condiciones["correo"], condicionCorreoPendiente(), condicionBusqueda($filtros["buscar"], ["cem_susFactura", "cem_susNombre", "cem_susCedula"]));
                $json = responderTabla($d, canalActivo($filtros, CANAL_CORREO), "cbEnvioMails", $condicion, CAMPOS_TABLA_CORREO, ["cem_susFechaAsignacion" => 1], limpiadorCorreo(), "filaCorreo");
                break;
            default:
                $condicion = unirCondiciones(
                    $condiciones["llamadas"],
                    ["av_estadoEnvio" => ['$in' => array_merge(ESTADOS_LLAMADA_PENDIENTE, [ESTADO_LLAMADA_MARCAR_LUEGO])]],
                    condicionBusqueda($filtros["buscar"], ["av_factura", "av_nombre", "av_cedula"])
                );
                $json = responderTabla($d, canalActivo($filtros, CANAL_LLAMADA), "avProgramadas", $condicion, CAMPOS_TABLA_LLAMADAS, ["av_fecha" => 1], limpiadorLlamadas(), function (array $fila) use ($traduceEstados): array {
                    return filaLlamada($fila, $traduceEstados);
                });
                break;
        }
        #endregion
        break;

    case "obtenerAudioTranscripcionLlamada":
        #region obtenerAudioTranscripcionLlamada
        $d = jsonStart();
        $json["respuesta"] = obtenerAudioTranscripcion((string) expect_safe_html($d["avId"] ?? ""));
        #endregion
        break;

    case "obtenerTranscripcionWhatsapp":
        #region obtenerTranscripcionWhatsapp
        $d = jsonStart();
        $json["respuesta"] = obtenerTranscripcionWhatsapp((string) expect_safe_html($d["avId"] ?? ""));
        #endregion
        break;

    case "obtenerDetalleCorreo":
        #region obtenerDetalleCorreo
        $d = jsonStart();
        $json["respuesta"] = obtenerDetalleCorreo((string) expect_safe_html($d["avId"] ?? ""));
        #endregion
        break;
}

jsonEnd($json, $limpiar);

#region FUNCIONES PRINCIPALES

/**
 * Totales de llamadas de avProgramadas (cuadros de dashboardGestionIndividual) y, en la
 * misma pasada, las tipificaciones del grafico de reporteAgenteVirtual.
 *
 * @param array $condicion   Condicion Mongo sobre avProgramadas.
 * @param array $paletaColor Paleta de "Dashboard paleta colores".
 * @return array{cuadros: array, tipificaciones: array, atendidas: int, pendientes: int, marcarLuego: int}
 */
function obtenerTotalesLlamadas(array $condicion, array $paletaColor): array
{
    $c = [
        "programadas" => 0, "pendientes" => 0, "marcarLuego" => 0, "atendidas" => 0, "noAtendidas" => 0,
        "enProgreso" => 0, "desprogramadas" => 0, "sinConexion" => 0,
    ];
    $totalDuracion = 0;
    $tipificaciones = [];
    $totalTipificado = 0;

    $mongo = new MYMONGODB();
    $c["programadas"] = intval($mongo->buscar("avProgramadas", $condicion));
    while ($c["programadas"] > 0 && ($row = $mongo->siguientex())) {
        $estado = $row["av_estadoEnvio"] ?? "";
        $totalDuracion += intval($row["av_duracionSegundos"] ?? 0);

        if (in_array($estado, ESTADOS_LLAMADA_PENDIENTE)) {
            $c["pendientes"]++;
        } elseif ($estado === ESTADO_LLAMADA_MARCAR_LUEGO) {
            $c["marcarLuego"]++;
        } elseif ($estado === "FINALIZADA") {
            $c["atendidas"]++;
        } elseif ($estado === "EN_PROGRESO") {
            $c["enProgreso"]++;
        } elseif ($estado === "DESPROGRAMADO_VERIFICACION") {
            $c["desprogramadas"]++;
        } elseif ($estado === "ERROR" || $estado === "ERROR_GENERAR_LLAMADA") {
            $c[esSinConexion($row) ? "sinConexion" : "noAtendidas"]++;
        }
        // Desprogramadas en asignacion: quedan con otro estado pero marcadas
        if (($row["av_desprogramada"] ?? 0) > 0 && $estado !== "DESPROGRAMADO_VERIFICACION") {
            $c["desprogramadas"]++;
        }

        $respuesta2 = $row["av_tipificacion"]["respuesta2"] ?? "";
        if (!in_array($estado, ESTADOS_SIN_TIPIFICACION) && $respuesta2 !== "") {
            if (!isset($tipificaciones[$respuesta2])) {
                $tipificaciones[$respuesta2] = ["padre" => $row["av_tipificacion"]["respuesta1"] ?? "", "total" => 0];
            }
            $tipificaciones[$respuesta2]["total"]++;
            $totalTipificado++;
        }
    }

    $duracionPromedio = $c["atendidas"] > 0 ? (int) ($totalDuracion / $c["atendidas"]) : 0;
    $duracion = $duracionPromedio > 3600 ? gmdate("H:i.s", $duracionPromedio) : gmdate("i:s", $duracionPromedio);

    return [
        "cuadros" => [
            cuadro("programadas", "Programadas", $c["programadas"], "Llamadas programadas para gestionar", "fa-calendar"),
            cuadro("pendientes", "Pendientes", $c["pendientes"], "Llamadas pendientes", "fa-clock-o"),
            cuadro("marcarLuego", "Marcar luego", $c["marcarLuego"], "Llamadas que se marcaron para llamar luego", "fa-clock-o"),
            cuadro("atendidas", "Atendidas", $c["atendidas"], "Llamadas atendidas", "fa-check-circle-o"),
            cuadro("noAtendidas", "No atendidas", $c["noAtendidas"], "Llamadas no atendidas por el cliente", "fa-times-circle-o"),
            cuadro("enProgreso", "En progreso", $c["enProgreso"], "Llamadas recién iniciadas o en progreso", "fa-spinner"),
            cuadro("desprogramadas", "Desprogramadas", $c["desprogramadas"], "Llamadas desprogramadas", "fa-times-circle-o"),
            cuadro("sinConexion", "Sin conexión", $c["sinConexion"], "Llamadas que no se pudieron realizar o aún no se tipifican", "fa-times-circle-o"),
            cuadro("duracionPromedio", "Dur. promedio", $duracion, "Duración promedio de las llamadas atendidas", "fa-clock-o"),
        ],
        "tipificaciones" => armarGraficoTipificaciones($tipificaciones, $totalTipificado, $paletaColor),
        "atendidas" => $c["atendidas"],
        "pendientes" => $c["pendientes"],
        "marcarLuego" => $c["marcarLuego"],
    ];
}

/**
 * Cuadro "Intentos": mismo calculo que "Marcadas" de reporteAgenteVirtual sobre avLlamadasMarcadas.
 *
 * @param array $condicion Condicion Mongo sobre avLlamadasMarcadas.
 * @param int   $atendidas Llamadas FINALIZADA de avProgramadas con los mismos filtros.
 * @return array{total: int, tooltip: string}
 */
function obtenerIntentosLlamadas(array $condicion, int $atendidas): array
{
    $mongo = new MYMONGODB();
    $contestadas = intval($mongo->buscar("avLlamadasMarcadas", $condicion + ["exito" => 1]));
    $noContestadas = intval($mongo->buscar("avLlamadasMarcadas", $condicion + ["exito" => 0]));
    $diferencia = $contestadas - $atendidas;

    return [
        "total" => $atendidas + $noContestadas + $diferencia,
        "tooltip" => "Total de llamadas marcadas: " . $atendidas . " contestadas, " . ($noContestadas + $diferencia) . " no contestadas",
    ];
}

/**
 * Totales de WhatsApp de avProgramadasWhatsApp (misma logica que dashboardGestionIndividual:
 * Recibidos y Leidos se confirman en whatsapp_logs).
 *
 * @param array $condicion Condicion Mongo sobre avProgramadasWhatsApp.
 * @return array{cuadros: array, programadas: int, pendientes: int}
 */
function obtenerTotalesWhatsapp(array $condicion): array
{
    $programadas = 0;
    $pendientes = 0;
    $entregados = 0;
    $noEnviados = 0;
    $recibidosConfirmados = 0;
    $recibidosSinConfirmar = 0;
    $leidos = 0;
    $contestados = 0;

    $mongo = new MYMONGODB();
    $mongoLogs = new MYMONGODB();
    $mongoAck = new MYMONGODB();
    $programadas = intval($mongo->buscar("avProgramadasWhatsApp", $condicion));
    while ($programadas > 0 && ($row = $mongo->siguientex())) {
        $estado = $row["ws_estadoEnvio"] ?? "";
        if ($estado === "CONTESTADO") {
            $entregados++;
            $recibidosConfirmados++;
            $leidos++;
            $contestados++;
        } elseif ($estado === "ENVIADO") {
            $entregados++;
            [$entregado, $leido] = confirmarEntregaWhatsapp($mongoLogs, $mongoAck, $row);
            if ($entregado) {
                $recibidosConfirmados++;
            }
            if ($leido) {
                $leidos++;
            }
            if (!$entregado && !$leido) {
                $recibidosSinConfirmar++;
            }
        } elseif (strpos($estado, "ERROR") !== false) {
            $noEnviados++;
        } else {
            $pendientes++;
        }
    }

    $recibidos = cuadro("recibidos", "Recibidos", $recibidosConfirmados + $recibidosSinConfirmar, "Mensajes recibidos", "fa-check-circle-o");
    $recibidos["extra"] = $recibidosConfirmados . " con confirmación (doble marca azul), " . $recibidosSinConfirmar . " sin confirmación";

    return [
        "cuadros" => [
            cuadro("programadas", "Programadas", $programadas, "Programadas para gestionar", "fa-calendar"),
            cuadro("pendientes", "Pendientes", $pendientes, "Mensajes pendientes de envío", "fa-clock-o"),
            cuadro("entregados", "Entregados", $entregados, "Entregados al usuario", "fa-check-circle-o"),
            cuadro("noEnviados", "No enviados", $noEnviados, "No enviados", "fa-times-circle-o"),
            $recibidos,
            cuadro("leidos", "Leídos", $leidos, "Mensajes leídos", "fa-check-circle-o"),
            cuadro("contestados", "Contestados", $contestados, "Mensajes que se contestaron", "fa-check-circle-o"),
        ],
        "programadas" => $programadas,
        "pendientes" => $pendientes,
    ];
}

/**
 * Totales de correo de cbEnvioMails.
 * Enviado: con fecha de envio y LlamadaId. No enviado: con error, enviado sin LlamadaId,
 * o sin enviar con asignacion anterior a hoy. Pendiente: sin enviar ni error, asignado desde hoy.
 *
 * @param array $condicion Condicion Mongo sobre cbEnvioMails.
 * @return array{cuadros: array, programadas: int, pendientes: int}
 */
function obtenerTotalesCorreo(array $condicion): array
{
    $totales = ["Enviado" => 0, "No enviado" => 0, "Programado" => 0];
    $hoy = inicioDeHoy();

    $mongo = new MYMONGODB();
    $programadas = intval($mongo->buscar("cbEnvioMails", $condicion, ["cem_susFechaEnvio", "cem_susLlamadaId", "cem_susErrorEnvio", "cem_susFechaAsignacion"]));
    while ($programadas > 0 && ($row = $mongo->siguientex())) {
        $totales[estadoCorreo($row, $hoy)]++;
    }

    return [
        "cuadros" => [
            cuadro("programadas", "Programadas", $programadas, "Envíos programados para gestionar", "fa-calendar"),
            cuadro("pendientes", "Pendientes", $totales["Programado"], "Correos pendientes de envío", "fa-spinner"),
            cuadro("enviados", "Enviados", $totales["Enviado"], "Correos enviados", "fa-check-circle-o"),
            cuadro("noEnviados", "No enviados", $totales["No enviado"], "Correos no enviados", "fa-times-circle-o"),
        ],
        "programadas" => $programadas,
        "pendientes" => $totales["Programado"],
    ];
}

/**
 * Audio y transcripcion de una llamada de avProgramadas (igual que dashboardGestionesCobranza).
 *
 * @param string $avId _id de avProgramadas.
 * @return array Audio, transcripcion en base64 y telefono, o "error".
 */
function obtenerAudioTranscripcion(string $avId): array
{
    $mongo = new MYMONGODB();
    $r = obtenerDocumentoPermitido($mongo, "avProgramadas", "av_carteraId", $avId);
    if ($r === null) {
        return ["error" => "No existe el detalle de la llamada (001)"];
    }
    $proveedor = $r["av_proveedor"] ?? "";
    $coleccion = $proveedor == "RETELL" ? "avDetalleConversacionesRetell" : ($proveedor == "ELEVENLABS" ? "avDetalleConversaciones" : "avDetalleConversacionesLink");

    if (empty($r["av_idConversacion"]) || $mongo->buscar($coleccion, ["idConversacion" => $r["av_idConversacion"]]) <= 0) {
        return ["error" => "No existe el detalle de la llamada (002)"];
    }

    while ($row = $mongo->siguiente()) {
        if (!isset($row["archivoAudio"]) || ($row["archivoAudio"] == "" && $proveedor != "RETELL")) {
            continue;
        }
        $transcripcion = $row["transcripcion"] ?? [];
        foreach ($transcripcion as $key => $value) {
            if (isset($value["tiempoTranscurridoSegundos"]) && $value["tiempoTranscurridoSegundos"] != "") {
                $inicio = strlen((string) $row["fechaInicio"]) > 10 ? $row["fechaInicio"] / 1000 : $row["fechaInicio"];
                $transcripcion[$key]["fechaHora"] = date("H:i:s", (int) ($inicio + $value["tiempoTranscurridoSegundos"]));
            }
            $transcripcion[$key]["mensaje"] = utf8_2_encode($value["mensaje"]);
        }
        return [
            "audio" => str_replace(BASEFOLDER, BASEURL, str_replace("\\", "", $row["archivoAudio"])),
            "transcripcion" => base64_encode(json_encode(utf8_converter($transcripcion))),
            "telefono" => $row["telefono"] ?? "",
        ];
    }
    return ["error" => "No existe el detalle de la llamada (002)"];
}

/**
 * Transcripcion de un chat de WhatsApp de avProgramadasWhatsApp (igual que dashboardGestionesCobranza).
 * Sin ws_idConversacion el cliente no respondio: se muestra solo el mensaje de plantilla.
 *
 * @param string $avId _id de avProgramadasWhatsApp.
 * @return array Transcripcion en base64 y telefono, o "error".
 */
function obtenerTranscripcionWhatsapp(string $avId): array
{
    $mongo = new MYMONGODB();
    $r = obtenerDocumentoPermitido($mongo, "avProgramadasWhatsApp", "ws_carteraId", $avId);
    if ($r === null) {
        return ["error" => "No existe el detalle del chat (001)"];
    }
    if (empty($r["ws_idConversacion"] ?? "")) {
        return [
            "transcripcion" => base64_encode(json_encode(utf8_converter(mensajePlantillaWhatsapp($r)))),
            "telefono" => $r["ws_telefono"] ?? "",
        ];
    }

    $idChat = $r["ws_idConversacion"];
    $mongo->buscar("avParametros", ["_id" => $r["ws_agenteParametroId"] ?? ""]);
    $agente = $mongo->siguientex();
    $proveedor = $agente ? ($agente["proveedor"] ?? "") : "";
    $transcripcion = [];

    if ($proveedor == "LINK") {
        require_once "../canalesMasivos/apis/class.linkLocalLlmAPI.php";
        $resp = (new linkLocalLlmAPI())->api_obtenerChat($idChat);
        if ($resp["estado"] != "OK") {
            return ["error" => "No existe el detalle del chat (002)"];
        }
        foreach ($resp["datos"]["mensajes"] ?? [] as $value) {
            if ($value["rol"] != "agente" && $value["rol"] != "usuario") {
                continue;
            }
            $transcripcion[] = [
                "direccion" => $value["rol"] == "agente" ? "agente" : "cliente",
                "mensaje" => utf8_2_decode(base64_decode($value["mensaje"])),
                "fechaHora" => isset($value["fecha"]) ? date("d/m/Y H:i.s", $value["fecha"]) : "",
            ];
        }
    } elseif ($proveedor == "RETELL") {
        require_once "../canalesMasivos/apis/class.retellAPI.php";
        $resp = (new retellAPI())->api_obtenerDetalleChat($idChat, true);
        if ($resp["estado"] != "OK") {
            return ["error" => "No existe el detalle del chat (002)"];
        }
        $transcripcion = mensajePlantillaWhatsapp($r);
        foreach ($resp["datos"]["message_with_tool_calls"] ?? [] as $value) {
            if ($value["role"] != "agent" && $value["role"] != "user") {
                continue;
            }
            $transcripcion[] = [
                "direccion" => $value["role"] == "agent" ? "agente" : "cliente",
                "mensaje" => utf8_2_decode($value["content"]),
                "fechaHora" => isset($value["created_timestamp"]) ? date("d/m/Y H:i.s", (int) (floatval($value["created_timestamp"]) / 1000)) : "",
            ];
        }
    } else {
        return ["error" => "No existe el detalle del chat (002)"];
    }

    return [
        "transcripcion" => base64_encode(json_encode(utf8_converter($transcripcion))),
        "telefono" => $r["ws_telefono"] ?? "",
    ];
}

/**
 * Html y destinatario de un correo de cbEnvioMails. cem_susUrl trae el html completo en base64.
 *
 * @param string $avId _id de cbEnvioMails.
 * @return array Html en base64 y destinatario, o "error".
 */
function obtenerDetalleCorreo(string $avId): array
{
    $mongo = new MYMONGODB();
    $r = obtenerDocumentoPermitido($mongo, "cbEnvioMails", "cem_susCarteraId", $avId);
    if ($r === null) {
        return ["error" => "No existe el detalle del correo (001)"];
    }
    return [
        "html" => $r["cem_susUrl"] ?? "",
        "destinatario" => $r["cem_susEmail"] ?? "",
    ];
}

#endregion

#region FUNCIONES AUXILIARES

/**
 * Lee los filtros de la pantalla. Llegan en el cuerpo JSON (arreglos) o en el
 * querystring de las tablas (valores separados por coma).
 *
 * @param array $origen Datos recibidos ($d de jsonStart o $_GET).
 * @return array{desde: ?int, hasta: ?int, campoFechaLlamada: string, carteras: array, campanias: array, periodoPorCartera: array, canales: array, buscar: string}
 */
function leerFiltros(array $origen): array
{
    $filtroTiempo = (string) expect_safe_html($origen["filtroTiempo"] ?? "");
    [$desde, $hasta] = obtenerRangoFiltroTiempo($filtroTiempo, $origen);
    $carteras = array_map('intval', obtenerSeleccionTextos($origen["filtroCartera"] ?? []));

    return [
        "desde" => $desde,
        "hasta" => $hasta,
        // En "Ultima hora" la llamada se ubica por la hora en que se genero, no por la asignacion
        "campoFechaLlamada" => $filtroTiempo === "hora" ? "av_fechaGeneraLlamada" : "av_fecha",
        "carteras" => $carteras,
        "campanias" => array_map('intval', obtenerSeleccionTextos($origen["filtroCampania"] ?? [])),
        "periodoPorCartera" => obtenerPeriodoPorCartera(obtenerSeleccionTextos($origen["filtroPeriodo"] ?? []), $carteras),
        "canales" => array_values(array_intersect(obtenerSeleccionTextos($origen["filtroCanal"] ?? []), CANALES_DASHBOARD)),
        "buscar" => trim((string) expect_safe_html($origen["buscarCliente"] ?? "")),
    ];
}

/**
 * Normaliza un filtro de seleccion multiple a un arreglo de textos sin vacios ni "todo".
 *
 * @param mixed $valor Arreglo del cuerpo JSON o texto separado por comas del querystring.
 * @return array
 */
function obtenerSeleccionTextos(mixed $valor): array
{
    $valores = is_array($valor) ? $valor : explode(",", (string) expect_safe_html($valor));
    $seleccion = [];
    foreach ($valores as $value) {
        $value = trim((string) $value);
        if ($value === "" || $value === "todo") {
            continue;
        }
        $seleccion[] = $value;
    }
    return $seleccion;
}

/**
 * Traduce el filtro de tiempo de la pantalla a un rango de timestamps (igual que dashboardGestionIndividual).
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
 * Traduce los ciclos elegidos ("carteraId_periodo") a un mapa cartera => ciclo.
 *
 * @param array $filtroPeriodo Ciclos seleccionados.
 * @param array $carteras      Carteras seleccionadas.
 * @return array [carteraId => ciclo]
 */
function obtenerPeriodoPorCartera(array $filtroPeriodo, array $carteras): array
{
    $periodoPorCartera = [];
    foreach ($filtroPeriodo as $value) {
        if (strpos($value, '_') === false) {
            continue;
        }
        [$carteraId, $periodo] = explode('_', $value, 2);
        if (in_array(intval($carteraId), $carteras)) {
            $periodoPorCartera[intval($carteraId)] = intval($periodo);
        }
    }
    return $periodoPorCartera;
}

/**
 * Indica si el filtro de canal deja ver un canal (sin canales elegidos se ven todos).
 *
 * @param array  $filtros Filtros de leerFiltros().
 * @param string $canal   Uno de CANALES_DASHBOARD.
 * @return bool
 */
function canalActivo(array $filtros, string $canal): bool
{
    return count($filtros["canales"]) === 0 || in_array($canal, $filtros["canales"]);
}

/**
 * Arma las condiciones Mongo de cada coleccion con los filtros de la pantalla.
 *
 * @param array $filtros Filtros de leerFiltros().
 * @return array{llamadas: array, whatsapp: array, correo: array, marcadas: array}
 */
function construirCondicionesCanal(array $filtros): array
{
    $fecha = function (string $campo) use ($filtros): array {
        return $filtros["desde"] === null ? [] : [$campo => ['$gte' => $filtros["desde"], '$lte' => $filtros["hasta"]]];
    };
    $campanias = function (string $campo) use ($filtros): array {
        return count($filtros["campanias"]) > 0 ? [$campo => ['$in' => $filtros["campanias"]]] : [];
    };

    return [
        "llamadas" => unirCondiciones(condicionCartera($filtros, "av_carteraId", "av_periodo"), $fecha($filtros["campoFechaLlamada"]), $campanias("av_campaniaId")),
        "whatsapp" => unirCondiciones(condicionCartera($filtros, "ws_carteraId", "ws_periodo"), $fecha("ws_fecha"), $campanias("ws_campaniaId")),
        "correo" => unirCondiciones(condicionCartera($filtros, "cem_susCarteraId", null), $fecha("cem_susFechaAsignacion"), $campanias("cem_susCampaniaId")),
        // avLlamadasMarcadas no guarda el ciclo: solo se filtra por cartera, fecha y campaña
        "marcadas" => unirCondiciones(condicionCartera($filtros, "cartera", null), $fecha("fecha"), $campanias("campania")),
    ];
}

/**
 * Condicion de cartera (y ciclo por cartera) limitada a las carteras permitidas al usuario.
 *
 * @param array       $filtros      Filtros de leerFiltros().
 * @param string      $campoCartera Campo de cartera de la coleccion.
 * @param string|null $campoPeriodo Campo de ciclo; null si la coleccion no lo guarda.
 * @return array
 */
function condicionCartera(array $filtros, string $campoCartera, ?string $campoPeriodo): array
{
    global $idsCarterasGeneral;
    $permitidas = $idsCarterasGeneral ?? [];
    $seleccion = $filtros["carteras"];

    if (count($seleccion) === 0) {
        return count($permitidas) > 0 ? [$campoCartera => ['$in' => $permitidas]] : [];
    }
    if (count($permitidas) > 0) {
        $seleccion = array_values(array_intersect($seleccion, $permitidas));
    }
    if (count($seleccion) === 0) {
        return [$campoCartera => ['$in' => []]];
    }
    if ($campoPeriodo !== null && count($filtros["periodoPorCartera"]) > 0) {
        $porCartera = [];
        foreach ($seleccion as $carteraId) {
            $cond = [$campoCartera => $carteraId];
            if (isset($filtros["periodoPorCartera"][$carteraId])) {
                $cond[$campoPeriodo] = $filtros["periodoPorCartera"][$carteraId];
            }
            $porCartera[] = $cond;
        }
        return ['$or' => $porCartera];
    }
    return [$campoCartera => ['$in' => $seleccion]];
}

/**
 * Une condiciones con $and (cada una puede traer su propio $or sin pisarse).
 *
 * @param array ...$partes Condiciones Mongo; las vacias se ignoran.
 * @return array
 */
function unirCondiciones(array ...$partes): array
{
    $partes = array_values(array_filter($partes, function (array $parte): bool {
        return count($parte) > 0;
    }));
    if (count($partes) === 0) {
        return [];
    }
    return count($partes) === 1 ? $partes[0] : ['$and' => $partes];
}

/**
 * Condicion del buscador "Buscar cliente" sobre los campos indicados.
 *
 * @param string $texto  Texto buscado.
 * @param array  $campos Campos donde se busca.
 * @return array
 */
function condicionBusqueda(string $texto, array $campos): array
{
    if ($texto === "") {
        return [];
    }
    $or = [];
    foreach ($campos as $campo) {
        $or[] = [$campo => ['$regex' => preg_quote($texto, '/'), '$options' => 'i']];
    }
    return ['$or' => $or];
}

/**
 * WhatsApp pendiente: ni contestado, ni enviado, ni con error.
 *
 * @return array
 */
function condicionWhatsappPendiente(): array
{
    return ['$nor' => [
        ["ws_estadoEnvio" => ['$in' => ["CONTESTADO", "ENVIADO"]]],
        ["ws_estadoEnvio" => ['$regex' => "ERROR"]],
    ]];
}

/**
 * Correo pendiente: sin envio ni error y asignado desde hoy (ver estadoCorreo()).
 *
 * @return array
 */
function condicionCorreoPendiente(): array
{
    return ['$and' => [
        ["cem_susFechaEnvio" => ['$not' => ['$gt' => 0]]],
        ["cem_susErrorEnvio" => ['$not' => ['$gt' => 0]]],
        ["cem_susFechaAsignacion" => ['$gte' => inicioDeHoy()]],
    ]];
}

/**
 * Saca de la entrada de la tabula el filtro de la columna Estado y devuelve el texto buscado.
 * Estado no es un campo de la coleccion (se arma en filaLlamadaEnvio/filaWhatsapp/filaCorreo),
 * asi que coTabulaMongo no puede filtrarlo: se quita y se aplica como condicion propia.
 *
 * @param array $d Entrada de la tabula (por referencia, se le quita el filtro de Estado).
 * @return string Texto buscado; vacio si no hay filtro.
 */
function extraerFiltroEstado(array &$d): string
{
    if (!isset($d["filtro"]) || !is_array($d["filtro"])) {
        return "";
    }
    $texto = "";
    foreach ($d["filtro"] as $i => $filtro) {
        if (($filtro["campo"] ?? "") !== CAMPO_FILTRO_ESTADO) {
            continue;
        }
        $texto = trim((string) expect_safe_html($filtro["filtro"] ?? ""));
        unset($d["filtro"][$i]);
    }
    $d["filtro"] = array_values($d["filtro"]);
    return $texto;
}

/**
 * Condicion del filtro de Estado de llamadas, igual que el detalle de llamadas de
 * reporteAgenteVirtual: el texto se busca en la traduccion de "Dashboard equivalencias
 * estados" y, para los estados sin traduccion, en el codigo tal como se muestra.
 *
 * @param string $texto          Texto buscado.
 * @param array  $traduceEstados Codigo de av_estadoEnvio => texto mostrado.
 * @return array
 */
function condicionEstadoLlamada(string $texto, array $traduceEstados): array
{
    if ($texto === "") {
        return [];
    }
    $codigos = [];
    foreach ($traduceEstados as $codigo => $nombre) {
        if (str_contains(strtolower($nombre), strtolower($texto))) {
            $codigos[] = $codigo;
        }
    }
    return ['$or' => [
        ["av_estadoEnvio" => ['$in' => $codigos]],
        ["av_estadoEnvio" => ['$regex' => preg_quote($texto, '/'), '$options' => 'i', '$nin' => array_keys($traduceEstados)]],
    ]];
}

/**
 * Condicion del filtro de Estado para un estado calculado: une las condiciones de
 * los estados cuyo nombre contiene el texto buscado.
 *
 * @param string $texto               Texto buscado.
 * @param array  $condicionesPorEstado Estado mostrado => condicion Mongo que lo produce.
 * @return array
 */
function condicionEstadoCalculado(string $texto, array $condicionesPorEstado): array
{
    if ($texto === "") {
        return [];
    }
    $or = [];
    foreach ($condicionesPorEstado as $estado => $condicion) {
        if (str_contains(strtolower($estado), strtolower($texto))) {
            $or[] = $condicion;
        }
    }
    return count($or) > 0 ? ['$or' => $or] : CONDICION_SIN_RESULTADOS;
}

/**
 * Condicion Mongo de cada estado de WhatsApp (misma regla que filaWhatsapp()).
 *
 * @return array<string, array>
 */
function condicionesEstadoWhatsapp(): array
{
    return [
        "Respondido" => ["ws_estadoEnvio" => "CONTESTADO"],
        "Entregado" => ["ws_estadoEnvio" => "ENVIADO"],
        "No enviado" => ["ws_estadoEnvio" => ['$regex' => "ERROR"]],
        "Programado" => condicionWhatsappPendiente(),
    ];
}

/**
 * Condicion Mongo de cada estado de correo (misma regla que estadoCorreo()).
 *
 * @param int $hoy Inicio del dia de hoy.
 * @return array<string, array>
 */
function condicionesEstadoCorreo(int $hoy): array
{
    $conEnvio = ["cem_susFechaEnvio" => ['$gt' => 0]];
    $sinEnvio = ["cem_susFechaEnvio" => ['$not' => ['$gt' => 0]]];
    $sinError = ["cem_susErrorEnvio" => ['$not' => ['$gt' => 0]]];
    return [
        "Enviado" => ['$and' => [$conEnvio, ["cem_susLlamadaId" => ['$gt' => 0]]]],
        "No enviado" => ['$or' => [
            ['$and' => [$conEnvio, ["cem_susLlamadaId" => ['$not' => ['$gt' => 0]]]]],
            ['$and' => [$sinEnvio, ["cem_susErrorEnvio" => ['$gt' => 0]]]],
            ['$and' => [$sinEnvio, $sinError, ["cem_susFechaAsignacion" => ['$lt' => $hoy]]]],
        ]],
        "Programado" => ['$and' => [$sinEnvio, $sinError, ["cem_susFechaAsignacion" => ['$gte' => $hoy]]]],
    ];
}

/**
 * Responde una tabla tabula sobre una coleccion, o vacia si el canal esta filtrado.
 *
 * @param array           $d          Entrada de la tabula.
 * @param bool            $activo     false si el filtro de canal excluye la tabla.
 * @param string          $coleccion  Coleccion Mongo.
 * @param array           $condicion  Condicion Mongo.
 * @param array           $campos     Campos a traer.
 * @param array           $orden      Orden por defecto.
 * @param array           $limpiador  Campo real => alias usado en tabula-encabezado.
 * @param callable|string $preparador Normaliza cada fila.
 * @return array
 */
function responderTabla(array $d, bool $activo, string $coleccion, array $condicion, array $campos, array $orden, array $limpiador, callable|string $preparador): array
{
    $ngTabula = new coTabulaMongo();
    if (!$activo) {
        return $ngTabula->respondeVacio();
    }
    $ngTabula->setInput($d);
    $ngTabula->setLimpiador($limpiador);
    $ngTabula->setQueryDatos($coleccion, $condicion, $campos, $orden);
    $ngTabula->setPreparaDatos($preparador);
    $ngTabula->permiteExportar(false);
    return $ngTabula->responde();
}

/**
 * Alias de las columnas de llamadas.
 *
 * @return array
 */
function limpiadorLlamadas(): array
{
    return [
        "av_factura" => "operacion",
        "av_nombre" => "nombreCliente",
        "av_campaniaNombre" => "campania",
        "av_telefono" => "contacto",
        "av_fechaGeneraLlamada" => "fechaMostrar",
    ];
}

/**
 * Alias de las columnas de WhatsApp.
 *
 * @return array
 */
function limpiadorWhatsapp(): array
{
    return [
        "ws_factura" => "operacion",
        "ws_nombre" => "nombreCliente",
        "ws_campaniaNombre" => "campania",
        "ws_telefono" => "contacto",
        "ws_fecha" => "fechaMostrar",
    ];
}

/**
 * Alias de las columnas de correo.
 *
 * @return array
 */
function limpiadorCorreo(): array
{
    return [
        "cem_susFactura" => "operacion",
        "cem_susNombre" => "nombreCliente",
        "cem_susCampaniaNombre" => "campania",
        "cem_susEmail" => "contacto",
        "cem_susFechaAsignacion" => "fechaMostrar",
    ];
}

/**
 * Fila de avProgramadas para la tabla. El estado es la tipificacion de primer nivel;
 * sin tipificar se muestra el estado de envio traducido.
 *
 * @param array $fila           Documento de avProgramadas.
 * @param array $traduceEstados Traduccion de "Dashboard equivalencias estados".
 * @return array
 */
function filaLlamada(array $fila, array $traduceEstados): array
{
    $estadoEnvio = $fila["av_estadoEnvio"] ?? "";
    $tipificacion = strtoupper($fila["av_tipificacion"]["respuesta1"] ?? "");
    switch (true) {
        case $tipificacion === "CONTACTO DIRECTO":
            [$estado, $clase] = ["Contactado", "contactado"];
            break;
        case $tipificacion === "CONTACTO INDIRECTO":
            [$estado, $clase] = ["Gestionado", "gestionado"];
            break;
        case $tipificacion === "SIN CONTACTO":
            [$estado, $clase] = ["Sin contacto", "sincontacto"];
            break;
        case in_array($estadoEnvio, ESTADOS_LLAMADA_PENDIENTE):
            [$estado, $clase] = ["Programado", "programado"];
            break;
        case $estadoEnvio === ESTADO_LLAMADA_MARCAR_LUEGO:
            [$estado, $clase] = ["Marcar luego", "programado"];
            break;
        case $estadoEnvio === "EN_PROGRESO":
            [$estado, $clase] = [$traduceEstados[$estadoEnvio] ?? "En progreso", "enproceso"];
            break;
        default:
            [$estado, $clase] = [$traduceEstados[$estadoEnvio] ?? $estadoEnvio, "sincontacto"];
            break;
    }
    return filaTabla($fila, "Llamada", $estado, $clase, !empty($fila["av_idConversacion"]), [
        "av_factura", "av_nombre", "av_campaniaNombre", "av_telefono", "av_fechaGeneraLlamada",
    ]);
}

/**
 * Fila de avProgramadas para la pestania Llamadas: el estado es el estado de envio
 * traducido, igual que el detalle de llamadas de reporteAgenteVirtual.
 * El icono de detalle tambien se activa o deshabilita como en ese reporte.
 *
 * @param array $fila           Documento de avProgramadas.
 * @param array $traduceEstados Traduccion de "Dashboard equivalencias estados".
 * @return array
 */
function filaLlamadaEnvio(array $fila, array $traduceEstados): array
{
    $estadoEnvio = $fila["av_estadoEnvio"] ?? "";
    $estado = $traduceEstados[$estadoEnvio] ?? $estadoEnvio;
    $resultado = filaTabla($fila, "Llamada", $estado, claseEstadoEnvio($estadoEnvio), in_array($estado, ESTADOS_DETALLE_ACTIVO), [
        "av_factura", "av_nombre", "av_campaniaNombre", "av_telefono", "av_fechaGeneraLlamada",
    ]);
    $resultado["detalleDeshabilitado"] = in_array($estado, ESTADOS_DETALLE_DESHABILITADO);
    return $resultado;
}

/**
 * Color del badge segun el estado de envio de la llamada.
 *
 * @param string $estadoEnvio Codigo de av_estadoEnvio.
 * @return string Sufijo de la clase del badge (sg-badge-<clase>).
 */
function claseEstadoEnvio(string $estadoEnvio): string
{
    return match (true) {
        $estadoEnvio === "FINALIZADA" => "enviado",
        in_array($estadoEnvio, ESTADOS_LLAMADA_PENDIENTE), $estadoEnvio === ESTADO_LLAMADA_MARCAR_LUEGO => "programado",
        $estadoEnvio === "EN_PROGRESO" => "enproceso",
        str_starts_with($estadoEnvio, "ERROR") => "error",
        default => "sincontacto",
    };
}

/**
 * Fila de avProgramadasWhatsApp para la tabla.
 *
 * @param array $fila Documento de avProgramadasWhatsApp.
 * @return array
 */
function filaWhatsapp(array $fila): array
{
    $estadoEnvio = $fila["ws_estadoEnvio"] ?? "";
    switch (true) {
        case $estadoEnvio === "CONTESTADO":
            [$estado, $clase] = ["Respondido", "contactado"];
            break;
        case $estadoEnvio === "ENVIADO":
            [$estado, $clase] = ["Entregado", "enviado"];
            break;
        case strpos($estadoEnvio, "ERROR") !== false:
            [$estado, $clase] = ["No enviado", "error"];
            break;
        default:
            [$estado, $clase] = ["Programado", "programado"];
            break;
    }
    return filaTabla($fila, "WhatsApp", $estado, $clase, true, [
        "ws_factura", "ws_nombre", "ws_campaniaNombre", "ws_telefono", "ws_fecha",
    ]);
}

/**
 * Fila de cbEnvioMails para la tabla.
 *
 * @param array $fila Documento de cbEnvioMails.
 * @return array
 */
function filaCorreo(array $fila): array
{
    $estado = estadoCorreo($fila, inicioDeHoy());
    $clases = ["Enviado" => "enviado", "No enviado" => "error", "Programado" => "programado"];
    return filaTabla($fila, "Email", $estado, $clases[$estado], true, [
        "cem_susFactura", "cem_susNombre", "cem_susCampaniaNombre", "cem_susEmail", "cem_susFechaAsignacion",
    ]);
}

/**
 * Arma la fila comun de las tablas a partir de los campos propios de cada coleccion.
 *
 * @param array  $fila         Documento original (trae "id" como texto, ver MYMONGODB::siguiente).
 * @param string $canal        Nombre del canal para la columna "Canal de comunicacion".
 * @param string $estado       Estado a mostrar.
 * @param string $clase        Sufijo de la clase del badge (sg-badge-<clase>).
 * @param bool   $tieneDetalle Si se muestra el icono de detalle.
 * @param array  $campos       Campos de operacion, nombre, campaña, contacto y fecha, en ese orden.
 * @return array
 */
function filaTabla(array $fila, string $canal, string $estado, string $clase, bool $tieneDetalle, array $campos): array
{
    [$operacion, $nombre, $campania, $contacto, $fecha] = $campos;
    return [
        "avId" => (string) ($fila["id"] ?? ""),
        "operacion" => $fila[$operacion] ?? "",
        "nombreCliente" => strtoupper(trim((string) ($fila[$nombre] ?? ""))),
        "campania" => $fila[$campania] ?? "",
        "contacto" => $fila[$contacto] ?? "",
        "fechaMostrar" => intval($fila[$fecha] ?? 0),
        "canal" => $canal,
        "estado" => $estado,
        "estadoClase" => $clase,
        "tieneDetalle" => $tieneDetalle,
    ];
}

/**
 * Estado de un correo de cbEnvioMails (misma regla que obtenerTotalesCorreo de dashboardGestionIndividual).
 *
 * @param array|ArrayAccess $row Documento de cbEnvioMails.
 * @param int   $hoy Inicio del dia de hoy.
 * @return string Enviado | No enviado | Programado
 */
function estadoCorreo(array|ArrayAccess $row, int $hoy): string
{
    if (($row["cem_susFechaEnvio"] ?? 0) > 0) {
        return ($row["cem_susLlamadaId"] ?? 0) > 0 ? "Enviado" : "No enviado";
    }
    if (($row["cem_susErrorEnvio"] ?? 0) > 0) {
        return "No enviado";
    }
    return ($row["cem_susFechaAsignacion"] ?? 0) < $hoy ? "No enviado" : "Programado";
}

/**
 * Indica si una llamada con error cuenta como "Sin conexion" (si no, es "No atendida").
 *
 * @param array|ArrayAccess $row Documento de avProgramadas con estado ERROR o ERROR_GENERAR_LLAMADA.
 * @return bool
 */
function esSinConexion(array|ArrayAccess $row): bool
{
    $finalizacion = $row["av_finalizacion"] ?? null;
    if ($finalizacion === null) {
        return true;
    }
    if (in_array($finalizacion, FINALIZACION_SIN_CONEXION)) {
        return true;
    }
    if (in_array($finalizacion, FINALIZACION_SIN_CONEXION_SIN_TIPIFICAR)) {
        return !isset($row["av_tipificacion"]) || $row["av_tipificacion"] == "";
    }
    return false;
}

/**
 * Busca en whatsapp_logs si un mensaje ENVIADO se entrego y se leyo (±5 minutos del envio).
 *
 * @param MYMONGODB $mongoLogs Cursor para los envios.
 * @param MYMONGODB $mongoAck  Cursor para las confirmaciones.
 * @param array|ArrayAccess $row Documento de avProgramadasWhatsApp.
 * @return array [entregado, leido]
 */
function confirmarEntregaWhatsapp(MYMONGODB $mongoLogs, MYMONGODB $mongoAck, array|ArrayAccess $row): array
{
    $entregado = false;
    $leido = false;
    $fecha = intval($row["ws_fecha"] ?? 0);
    $criterio = ["wp_numeroWP" => ($row["ws_telefono"] ?? "") . "@c.us", "wp_date" => ['$gte' => $fecha - 300, '$lte' => $fecha + 300], "wp_ack" => "enviado"];
    if ($mongoLogs->buscar("whatsapp_logs", $criterio) <= 0) {
        return [$entregado, $leido];
    }
    while ($envio = $mongoLogs->siguiente()) {
        $mongoAck->buscar("whatsapp_logs", ["wp_msgID" => $envio["wp_msgID"], "wp_ack" => ['$ne' => "enviado"]]);
        while ($ack = $mongoAck->siguiente()) {
            $entregado = $entregado || ($ack["wp_ack"] ?? "") === "entregado";
            $leido = $leido || ($ack["wp_ack"] ?? "") === "leido";
        }
    }
    return [$entregado, $leido];
}

/**
 * Arma las barras del grafico de tipificaciones (las 25 mas frecuentes, color segun el primer nivel).
 *
 * @param array $tipificaciones respuesta2 => [padre, total].
 * @param int   $totalTipificado Total de llamadas tipificadas.
 * @param array $paletaColor     Paleta de "Dashboard paleta colores".
 * @return array<int, array{label: string, value: int, color: string, porcentaje: string, padre: string}>
 */
function armarGraficoTipificaciones(array $tipificaciones, int $totalTipificado, array $paletaColor): array
{
    $colores = [
        "contacto directo" => $paletaColor[8] ?? "#4FAF5A",
        "contacto indirecto" => $paletaColor[4] ?? "#A78BFA",
        "sin contacto" => $paletaColor[3] ?? "#9CA3AF",
    ];
    $barras = [];
    foreach ($tipificaciones as $respuesta2 => $valor) {
        $padre = strtolower($valor["padre"]);
        $barras[] = [
            "label" => ucfirst(strtolower($respuesta2)),
            "value" => $valor["total"],
            "color" => $colores[$padre] ?? "#000",
            "porcentaje" => formatea_numero($totalTipificado > 0 ? ($valor["total"] * 100) / $totalTipificado : 0, 2, ",", ".") . "%",
            "padre" => ucfirst($padre),
        ];
    }
    usort($barras, function (array $a, array $b): int {
        return $b["value"] <=> $a["value"];
    });
    return array_slice($barras, 0, LIMITE_TIPIFICACIONES);
}

/**
 * Cuadro de KPI para el front.
 *
 * @param string     $clave   Identificador del cuadro.
 * @param string     $nombre  Titulo.
 * @param int|string $total   Valor a mostrar.
 * @param string     $tooltip Descripcion.
 * @param string     $icono   Clase de Font Awesome.
 * @return array
 */
function cuadro(string $clave, string $nombre, int|string $total, string $tooltip, string $icono): array
{
    return ["clave" => $clave, "nombre" => $nombre, "total" => $total, "tooltip" => $tooltip, "icono" => $icono];
}

/**
 * Totales de llamadas cuando el filtro de canal excluye las llamadas.
 *
 * @return array
 */
function totalesLlamadasVacios(): array
{
    return ["cuadros" => [], "tipificaciones" => [], "atendidas" => 0, "pendientes" => 0, "marcarLuego" => 0];
}

/**
 * Totales de WhatsApp cuando el filtro de canal excluye WhatsApp.
 *
 * @return array
 */
function totalesWhatsappVacios(): array
{
    return ["cuadros" => [], "programadas" => 0, "pendientes" => 0];
}

/**
 * Totales de correo cuando el filtro de canal excluye el correo.
 *
 * @return array
 */
function totalesCorreoVacios(): array
{
    return ["cuadros" => [], "programadas" => 0, "pendientes" => 0];
}

/**
 * Inicio del dia de hoy (para separar correos pendientes de no enviados).
 *
 * @return int
 */
function inicioDeHoy(): int
{
    return strtotime(date("Y-m-d") . " 00:00:00");
}

/**
 * Agrega a $opciones los valores distintos (id, nombre) de una coleccion.
 *
 * @param MYMONGODB $mongo       Conexion Mongo.
 * @param array     $opciones    Opciones acumuladas, por id.
 * @param string    $coleccion   Coleccion Mongo.
 * @param array     $condicion   Condicion Mongo.
 * @param string    $campoId     Campo del id.
 * @param string    $campoNombre Campo del nombre.
 * @param string    $prefijo     Texto para cuando no hay nombre.
 * @return void
 */
function agruparOpciones(MYMONGODB $mongo, array &$opciones, string $coleccion, array $condicion, string $campoId, string $campoNombre, string $prefijo): void
{
    $pipeline = [
        ['$match' => $condicion],
        ['$group' => ["_id" => ["id" => '$' . $campoId, "nombre" => '$' . $campoNombre]]],
    ];
    $mongo->aggregate($coleccion, $pipeline);
    while ($row = $mongo->siguiente()) {
        $id = $row["_id"]["id"] ?? null;
        if ($id === null || $id === "null" || isset($opciones[intval($id)])) {
            continue;
        }
        $opciones[intval($id)] = [
            "id" => intval($id),
            "nombre" => !empty($row["_id"]["nombre"]) ? $row["_id"]["nombre"] : $prefijo . $id,
        ];
    }
}

/**
 * Ordena las opciones de un combo por nombre.
 *
 * @param array $opciones Opciones por id.
 * @return array
 */
function ordenarOpciones(array $opciones): array
{
    usort($opciones, function (array $a, array $b): int {
        return strcmp((string) $a["nombre"], (string) $b["nombre"]);
    });
    return $opciones;
}

/**
 * Ciclos (control_carga_periodo activos) de las carteras elegidas, con id "carteraId_periodo".
 *
 * @param MYMONGODB $mongo    Conexion Mongo.
 * @param array     $carteras Carteras seleccionadas.
 * @param array     $nombres  Opciones de cartera ya armadas, por id.
 * @return array
 */
function obtenerCiclosCarteras(MYMONGODB $mongo, array $carteras, array $nombres): array
{
    if (count($carteras) === 0) {
        return [];
    }
    $ciclos = [];
    $mongo->buscar("control_carga_periodo", ["activo" => 1, "cartera" => ['$in' => $carteras]], [], ["periodo" => 1]);
    while ($row = $mongo->siguiente()) {
        $carteraId = intval($row["cartera"]);
        $nombreCartera = $nombres[$carteraId]["nombre"] ?? "Cartera ID: " . $carteraId;
        $ciclos[] = [
            "id" => $carteraId . "_" . $row["periodo"],
            "nombre" => $row["periodo"] . " - " . date("d/m/Y", $row["fecha"]) . " (" . $nombreCartera . ")",
        ];
    }
    return $ciclos;
}

/**
 * Busca un documento por _id solo si pertenece a una cartera permitida al usuario.
 *
 * @param MYMONGODB $mongo        Conexion Mongo.
 * @param string    $coleccion    Coleccion Mongo.
 * @param string    $campoCartera Campo de cartera de la coleccion.
 * @param string    $id           _id como texto.
 * @return array|null
 */
function obtenerDocumentoPermitido(MYMONGODB $mongo, string $coleccion, string $campoCartera, string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
        return null;
    }
    $condicion = establecerCondicionMaster($campoCartera);
    $condicion["_id"] = $mongo->String2MongoId($id);
    if ($mongo->buscar($coleccion, $condicion, [], [], 1) <= 0) {
        return null;
    }
    $documento = $mongo->siguiente();
    return is_array($documento) ? $documento : null;
}

/**
 * Mensaje de plantilla con el que arranca el chat de WhatsApp (ws_plantilla y ws_fecha).
 *
 * @param array $r Documento de avProgramadasWhatsApp.
 * @return array<int, array{direccion: string, mensaje: string, fechaHora: string}> Vacio si no hay plantilla.
 */
function mensajePlantillaWhatsapp(array $r): array
{
    $plantilla = (string) ($r["ws_plantilla"] ?? "");
    if ($plantilla === "") {
        return [];
    }
    return [[
        "direccion" => "agente",
        "mensaje" => $plantilla,
        "fechaHora" => !empty($r["ws_fecha"]) ? date("d/m/Y H:i.s", (int) $r["ws_fecha"]) : "",
    ]];
}

/**
 * Carteras que puede ver el usuario (igual que dashboardGestionIndividual). Vacio = todas (Desarrollo).
 *
 * @param array $permisos Permisos del usuario.
 * @return array
 */
function obtenerCarterasPermitidas(array $permisos): array
{
    if ($permisos["soyDesarrollo"]) {
        return [];
    }
    if (!$permisos["soySupervisor"] && !$permisos["soyObservador"] && !$permisos["soyGerente"]) {
        trigger_error("No tiene permisos configurados para usuario " . $_SESSION[MID . "userId"]);
        echo "No tiene permisos configurados";
        die;
    }

    $tabla = "cobcartera";
    $carterasConfiguradas = [];
    $mysql = new MYSQLDB();
    $sql = $mysql->mkSQL("SELECT * FROM " . $tabla . " WHERE cobCartera_estado=%N", 1);
    if ($mysql->query($sql) > 0) {
        while ($row = $mysql->fetchRow()) {
            $item = [];
            foreach ($row as $key => $value) {
                $item[str_replace("cobCartera_", "", $key)] = $value;
            }
            $carterasConfiguradas[] = $item;
        }
    }
    require_once "../permisos/classes/class.permisoUniversal.php";
    $permisoUniversal = new permisoUniversal();
    $ids = [];
    foreach ($permisoUniversal->validaRegistrosUsuarioSesion($carterasConfiguradas, $tabla) as $value) {
        $ids[] = intval($value["id"]);
    }
    if (count($ids) === 0) {
        trigger_error("No tiene permisos universales configurados para usuario " . $_SESSION[MID . "userId"]);
        echo "No tiene permisos universales configurados";
        die;
    }
    return $ids;
}

/**
 * Condicion de las carteras permitidas al usuario sobre un campo.
 *
 * @param string $campo Campo de cartera de la coleccion.
 * @return array
 */
function establecerCondicionMaster(string $campo): array
{
    global $idsCarterasGeneral;
    if (empty($idsCarterasGeneral)) {
        return [];
    }
    return [$campo => count($idsCarterasGeneral) == 1 ? $idsCarterasGeneral[0] : ['$in' => $idsCarterasGeneral]];
}

#endregion

//_FIN_DE_ARCHIVO
