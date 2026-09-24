<?php
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
require_once("../comunes/classes/class.coCompleteAngular.php");

if (!isset($_REQUEST["act"])) {
    exit;
}
if (!$Central->hasDomain("Link")) {
    exit;
}

global $Central;
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];

require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
$linkLocalLlmAPI = new linkLocalLlmAPI("gpu");

switch ($act) {
    case "obtenerModelos":
        $d = jsonStart();
        $apiModelos = $linkLocalLlmAPI->api_obtenerModelos();
        $modelos = [];
        if ($apiModelos["estado"] == "OK") {
            foreach ($apiModelos["datos"] as $value) {
                $modelos[] = [
                    "nombre" => $value["nombre"],
                    "id" => $value["nombre"]
                ];
            }
        }
        $json["modelos"] = $modelos;
        break;
    case "enviarPregunta":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $modelo = expect_safe_html($d["modelo"]);
        $temperatura = expect_float($d["temperatura"]);
        $pregunta = expect_safe_html($d["pregunta"]);

        if ($id == "") {
            //debo generar un nuevo chat
            $inicio = $linkLocalLlmAPI->api_iniciarChatDirecto($modelo, $_SESSION[MID . "userId"]);
            if ($inicio["estado"] == "OK") {
                $sesionId = $inicio["datos"]["id"];
                $respuesta = $linkLocalLlmAPI->api_obtenerRespuestaDirecto($sesionId, utf8_2_encode($pregunta), $temperatura);
                if ($respuesta["estado"] == "OK") {
                    $mensaje = $respuesta["datos"]["mensajes"][0];
                    $mensaje["mensaje"] = $mensaje["mensaje"] != "" ? utf8_2_decode(base64_decode($mensaje["mensaje"])) : "";
                    $json["respuesta"] = $mensaje;
                    $json["id"] = $sesionId;
                } else {
                    $json["error"] = $respuesta["mensaje"];
                }
            } else {
                $json["error"] = $inicio["mensaje"];
            }
        } else {
            //ya es una conversacion iniciada
            $respuesta = $linkLocalLlmAPI->api_obtenerRespuestaDirecto($id, utf8_2_encode($pregunta), $temperatura);
            if ($respuesta["estado"] == "OK") {
                $mensaje = $respuesta["datos"]["mensajes"][0];
                $mensaje["mensaje"] = $mensaje["mensaje"] != "" ? utf8_2_decode(base64_decode($mensaje["mensaje"])) : "";
                $json["respuesta"] = $mensaje;
            } else {
                $json["error"] = $respuesta["mensaje"];
            }
        }
        break;
    case "traerChats":
        $d = jsonStart();
        $respuesta = $linkLocalLlmAPI->api_obtenerChatsUsuario(100, 0, 0, 0);
        $chats = [];
        if ($respuesta["estado"] == "OK") {
            $respuesta["datos"] = array_reverse($respuesta["datos"]);
            foreach ($respuesta["datos"] as $value) {
                $primerMensaje = "";
                $fecha = 0;
                if (isset($value["mensajes"]) && isset($value["mensajes"][0])) {
                    $primerMensaje = utf8_2_decode(base64_decode($value["mensajes"][0]["mensaje"]));
                    $fecha = $value["mensajes"][0]["fecha"];
                }
                $chats[] = [
                    "id" => $value["_id"],
                    "modelo" => $value["id_agente"],
                    "primer_mensaje" => $primerMensaje,
                    "fecha" => $fecha,
                    "seleccionado" => false
                ];
            }
        }
        $json["chats"] = $chats;
        break;
    case "traerChat":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $respuesta = $linkLocalLlmAPI->api_obtenerChat($id);
        if ($respuesta["estado"] == "OK") {
            $mensajes = [];

            foreach ($respuesta["datos"]["mensajes"] as $key => $value) {
                $mensaje = utf8_2_decode(base64_decode($value["mensaje"]));
                $mensajes[] = [
                    "mensaje" => $mensaje,
                    "rol" => $value["rol"],
                    "fecha" => intval($value["fecha"])
                ];
            }

            $chat = [
                "temperatura" => isset($respuesta["datos"]["temperatura"]) ? floatval($respuesta["datos"]["temperatura"]) : 0.8,
                "id_agente" => $respuesta["datos"]["id_agente"],
                "mensajes" => $mensajes,
                "_id" => $respuesta["datos"]["_id"],
            ];

            $json["chat"] = $chat;
        } else {
            $json["error"] = $respuesta["mensaje"];
        }
        break;
}

jsonEnd($json, $limpiar);

?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
