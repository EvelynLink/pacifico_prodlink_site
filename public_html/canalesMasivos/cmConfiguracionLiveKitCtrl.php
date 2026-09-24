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

require_once("../canalesMasivos/apis/class.linkLiveKitAPI.php");
$link_lk = new linkLiveKitAPI();
require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
$link_llm = new linkLocalLlmAPI("gpu");

switch ($act) {
    case "cargaConfiguracion":
        $d = jsonStart();
        $config = $link_lk->api_traerConfiguracion();
        $configuracion = [];
        if ($config["estado"] == "OK") {
            $configuracion = $config["datos"];
        }
        $json["configuracion"] = $configuracion;
        break;
    case "cargaAgentes":
        $d = jsonStart();
        $respuesta = $link_llm->api_obtenerAgentes(100, 0, 0, 0);
        $agentes = [];
        if ($respuesta["estado"] == "OK") {
            $agentes = [];
            foreach ($respuesta["datos"] as $key => $value) {
                $agentes[] = [
                    "nombre" => $value["agente_nombre"],
                    "id" => $value["agente_id"]
                ];
            }
        }
        $json["agentes"] = $agentes;
        $json["stt"] = [
            ["nombre" => "AssemblyAI"],
            ["nombre" => "Cartesia"],
            ["nombre" => "Deepgram"],
            ["nombre" => "ElevenLabs"],
        ];
        $json["tts"] = [
            ["nombre" => "Amazon Polly"],
            ["nombre" => "Azure AI Speech"],
            ["nombre" => "Azure OpenAI"],
            ["nombre" => "Baseten"],
            ["nombre" => "Cartesia"],
            ["nombre" => "Deepgram"],
            ["nombre" => "ElevenLabs"],
            ["nombre" => "Gemini"],
            ["nombre" => "Google Cloud"],
            ["nombre" => "Groq"],
            ["nombre" => "Hume"],
            ["nombre" => "Inworld"],
            ["nombre" => "LMNT"],
            ["nombre" => "MiniMax"],
            ["nombre" => "Neuphonic"],
            ["nombre" => "Nvidia"],
            ["nombre" => "OpenAI"],
            ["nombre" => "Resemble AI"],
            ["nombre" => "Rime"],
            ["nombre" => "Sarvam"],
            ["nombre" => "Smallest AI"],
            ["nombre" => "Speechify"],
            ["nombre" => "Spitch"]
        ];
        break;
    case "actualizaConfiguracion":
        $d = jsonStart();
        $configuracion = expect_safe_html($d["configuracion"]);
        $agente = expect_safe_html($d["agente"]);
        $respuesta = $link_lk->api_actualizarConfiguracion($agente, $configuracion);
        $json["respuesta"] = $respuesta["mensaje"];
        break;
    case "crearConfiguracion":
        $d = jsonStart();
        $configuracion = expect_safe_html($d["configuracion"]);
        $agente = expect_safe_html($d["agente"]);
        $respuesta = $link_lk->api_crearConfiguracion($agente, $configuracion);
        $json["respuesta"] = $respuesta["mensaje"];
        break;
    case "actualizaEstadoServicio":
        $d = jsonStart();
        $config = $link_lk->api_traerConfiguracion();
        if ($config["estado"] == "OK") {
            $configuracion = $config["datos"]["general"];
            $accion = expect_safe_html($d["accion"]);
            $configuracion["pausa_servicio"] = $accion == "detener" ? 1 : 0;
            $respuesta = $link_lk->api_actualizarConfiguracion("general", $configuracion);
            $json["respuesta"] = $respuesta["mensaje"];
        } else {
            $json["respuesta"] = "Error";
        }
        break;
}
jsonEnd($json, $limpiar);


?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
