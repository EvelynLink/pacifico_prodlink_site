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
$link = new linkLiveKitAPI();

switch ($act) {
    case "obtenerParametrizacion":
        #region obtenerParametrizacion 
        $d = jsonStart();
        //TODO:

        $json = [
            "agentes" => [
                ["nombre" => "Agente Whatsapp Preventiva Link LM2 GPU", "id" => "Agente_Whatsapp_Preventiva_Link_LM2_GPU"],
                ["nombre" => "Agente Premora Normal", "id" => "Agente_Premora_Normal"],
                //["nombre" => "Agente Premora Whatsapp Link LM2 GPU", "id" => "Agente_Premora_Whatsapp_Link_LM2_GPU"],
            ],
            "estados" => [
                ["nombre" => "Nueva", "id" => "new"],
                ["nombre" => "Iniciada", "id" => "registered"],
                ["nombre" => "En Progreso", "id" => "ongoing"],
                ["nombre" => "Finalizada", "id" => "ended"],
                ["nombre" => "Error", "id" => "error"],
                ["nombre" => "Desprogramada", "id" => "cancelled"]
            ]
        ];
        #endregion 
        break;
    case "cargaTabula":
        $d = jsonStart();
        $agentes = expect_safe_html($_GET["agentes"]);
        $estados = expect_safe_html($_GET["estados"]);
        $tiempo = expect_safe_html($_GET["tiempo"]);

        if ($tiempo != "" && $tiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($tiempo) {
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
                    $filtroDesde = expect_integer($d["fecha_desde"]);
                    $filtroHasta = expect_integer($d["fecha_hasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }
        }

        $agentes = json_decode($agentes);
        $estados = json_decode($estados);

        $respuesta = $link->api_obtenerLlamadas($agentes, $estados, 1, 0, $desde, $hasta);
        if ($respuesta["estado"] == "OK") {
            $mongo = new MYMONGODB();
            $x = $mongo->borrar("TemporalTabulaLlamadasLk", ["fecha_creacion" => ['$gte' => 0]]);

            foreach ($respuesta["datos"] as $value) {
                $value["idLlamada"] = $value["_id"];
                unset($value["_id"]);
                $mongo->guardar("TemporalTabulaLlamadasLk", $value);
            }
        }

        $condition = [];
        $campos = [];
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos("TemporalTabulaLlamadasLk", $condition, $campos, ["fecha_creacion" => -1]);
        $ngTabula->setPreparaDatos(function ($campos) {
            return $campos;
        });
        $json = $ngTabula->responde();
        break;
    case "traerCamposAgente":
        $d = jsonStart();
        $id = expect_safe_html($d["agente"]);

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avParametros", ["agenteId" => $id, "activo" => 1]);
        $campos = [];
        if ($cursor > 0) {
            $row = $mongo->siguiente();
            foreach ($row["valores_reemplazo"] as $key => $value) {
                $campos[] = ["nombre" => $value, "valor" => ""];
            }
        }
        $json["campos"] = $campos;
        break;
    case "iniciarLlamada":
        $d = jsonStart();
        $agente = expect_safe_html($d["agente"]);
        $telefono = expect_safe_html($d["telefono"]);
        $valores_reemplazo = expect_safe_html($d["valores_reemplazo"]);

        $variables = [];

        foreach ($valores_reemplazo as $key => $value) {
            $variables[$value["nombre"]] = $value["valor"];
        }

        $respuesta = $link->api_crearLlamada($agente, $telefono, $variables);

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "LLamada creada con éxito";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }

        break;
    case "eliminarLlamada":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $respuesta = $link->api_eliminarLlamada($id);

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "LLamada eliminada con éxito";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }
        break;
    case "desprogramarLlamada":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $respuesta = $link->api_desprogramarLlamada($id, "cancelled");

        if ($respuesta["estado"] == "OK") {
            $json["respuesta"] = "LLamada desprogramada con éxito";
        } else {
            $json["respuesta"] = $respuesta["mensaje"];
        }
        break;
    case "detalleLlamada":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $respuesta = $link->api_traerDetalleLlamada($id);
        if ($respuesta["estado"] == "OK") {
            if (isset($respuesta["datos"]["analisis"])) {
                $respuesta["datos"]["analisis"]["resumen_llamada"] = utf8_2_decode($respuesta["datos"]["analisis"]["resumen_llamada"]);
            }
            if (isset($respuesta["datos"]["transcripcion"])) {
                foreach ($respuesta["datos"]["transcripcion"]["items"] as $key => $value) {
                    if ($value["type"] == "message") {
                        foreach ($value["content"] as $key1 => $value1) {
                            $respuesta["datos"]["transcripcion"]["items"][$key]["content"][$key1] = utf8_2_decode($value1);
                        }
                        if (!isset($value["metrics"]["started_speaking_at"])) {
                            $respuesta["datos"]["transcripcion"]["items"][$key]["metrics"]["started_speaking_at"] = 0;
                        }
                    }
                }
            }
            $json["llamada"] = $respuesta["datos"];
        } else {
            $json["error"] = $respuesta["mensaje"];
        }
        break;
    case "audioLlamada":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $respuesta = $link->api_traerAudioLlamada($id);
        if ($respuesta["estado"] == "OK") {
            $json["audio"] = count($respuesta["datos"]) > 0 ? $respuesta["datos"][0] : "";
        } else {
            $json["error"] = $respuesta["mensaje"];
        }
        break;
}
jsonEnd($json, $limpiar);


?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
