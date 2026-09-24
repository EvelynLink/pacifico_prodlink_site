<?php
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
if (!isset($_REQUEST["act"])) {
    exit;
}
if (!$Central->hasDomain("Link")) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];

require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
$link = new linkLocalLlmAPI();

switch ($act) {
    case "cargaTabula":
        $d = jsonStart();

        $link = new linkLocalLlmAPI();
        $respuesta = $link->api_obtenerChats(100, 0, 0, 0);
        if ($respuesta["estado"] == "OK") {
            $mongo = new MYMONGODB();
            $x = $mongo->borrar("TemporalTabulaChats", ["fecha_creacion" => ['$gte' => 0]]);

            foreach ($respuesta["datos"] as $value) {
                $value["idChat"] = $value["_id"];
                $value["abierto"] = false;
                unset($value["_id"]);
                $mongo->guardar("TemporalTabulaChats", $value);
            }
        }

        $condition = [];
        $campos = [];
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos("TemporalTabulaChats", $condition, $campos, ["ultima_actividad" => -1]);
        $ngTabula->setPreparaDatos(function ($campos) {
            return $campos;
        });
        $json = $ngTabula->responde();
        break;
    case "detalleChat":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);

        $link = new linkLocalLlmAPI();
        $respuesta = $link->api_obtenerChat($id);

        if ($respuesta["estado"] == "OK") {
            $final = $respuesta["datos"];
            $final["fecha_creacion"] = date("d/m/Y H:i.s", $final["fecha_creacion"]);
            $final["ultima_actividad"] = $final["ultima_actividad"] > 0 ? date("d/m/Y H:i.s", $final["ultima_actividad"]) : "";
            foreach ($final["valores_dinamicos"] as $key => $value) {
                $final["valores_dinamicos"][utf8_2_decode($key)] = utf8_2_decode($value);
            }
            foreach ($final["mensajes"] as $key => $value) {
                $final["mensajes"][$key]["mensaje"] = utf8_2_decode(base64_decode($value["mensaje"]));
                $final["mensajes"][$key]["fecha"] = date("d/m/Y H:i.s", $value["fecha"]);
            }
            $json["respuesta"] = $final;
        } else {
            $json["error"] = $respuesta["mensaje"];
        }

        break;
    case "obtenerRespuesta":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $mensaje = expect_safe_html($d["mensaje"]);

        $respuesta = $link->api_obtenerRespuesta($id, utf8_2_encode($mensaje));

        if ($respuesta["estado"] == "OK") {
            // foreach ($respuesta["datos"]["mensajes"] as $key => $value) {
            //     $respuesta["datos"]["mensajes"][$key]["mensaje"] = utf8_2_decode(base64_decode($value["mensaje"]));
            // }
            // $json["respuesta"] = $respuesta["datos"]["mensajes"];
            $json["respuesta"] = "OK";
        } else {
            $json["error"] = $respuesta["mensaje"];
        }

        break;
    case "finalizarChat":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);

        $respuesta = $link->api_finalizarChat($id);

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "Chat finalizado correctamente";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }

        break;
    case "eliminarChat":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);

        $respuesta = $link->api_eliminarChat($id);

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "Chat eliminado correctamente";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }

        break;
    case "analizarChat":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);

        $respuesta = $link->api_analizarChat($id);

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "Se realizó el análisis correctamente, vea el detalle del chat";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }

        break;
    case "inicializaNuevoChat":
        $d = jsonStart();
        $respuesta = $link->api_obtenerAgentes(100, 0, 0, 0);

        if ($respuesta["estado"] == "OK") {
            $agentes = [];
            foreach ($respuesta["datos"] as $key => $value) {
                $agentes[] = [
                    "nombre" => $value["agente_nombre"],
                    "id" => $value["_id"],
                    "valores_reemplazo" => $value["valores_reemplazo"]
                ];
            }
            $json["respuesta"] = $agentes;
        }
        break;
    case "crearNuevoChat":
        $d = jsonStart();
        $agente = expect_safe_html($d["agente"]);
        $variables = expect_safe_html($d["variablesDinamicas"]);

        $variablesFinal = [];
        foreach ($variables as $key => $value) {
            $variablesFinal[$value["nombre"]] = $value["valor"];
        }
        $respuesta = $link->api_iniciarChat($agente, $variablesFinal);
        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = $respuesta["datos"]["id"];
        } else {
            $json["error"] = $respuesta["mensaje"];
        }
        break;
}
jsonEnd($json, $limpiar);


?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
