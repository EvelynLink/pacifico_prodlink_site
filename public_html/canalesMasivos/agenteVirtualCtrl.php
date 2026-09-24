<?php
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");

if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

$URL = "http://184.107.167.71:5008/agente-virtual/api/v1";
$CLAVE_PRIVADA = "MIICXQIBAAKBgQDH7hSypoeux4+4SGpD54hZzLPm3pUcoxShVpeEGwrxpSOBwm5AB05ZxCAFQv+ptIRb4+bQffdjp3pttC4AfS9DpaFa3ms5cOTAe6ygImQ6wArG/TQKLUxbLEssqs/Xy0zrH+fnWzBk+RhLj3QSJV8NBj37gZWIYXVp6r0CJKVoXQIDAQABAoGAT/K9ph7/vP2iVB/pFpRcqwQ3oIe7ewMfudClsDccLjtKMpZsfgAt7amG4HPFRrigARrmbtMgfWI4i+v0RU/J+P3pP+xKMRK+ngGY1cD8Q6PKxljcRrMmkN34kKNDGSXuqwXiRsKbydh86VIlNZCfd5WNB71gK0mraLHxMjSdqiUCQQD+v/THsQc+JtUHP0+KGK6Divv0HErQgzHhvrJ9vb3eHLuhwwmVYqArFLbQWAkQs/dNGY0BbhNOUbfQjnYA+3OnAkEAyOlBEjQVGUxa3vFow32fysWJukj+hUhscY83WiyFT189gUbYHi7Irhph30qJKlgSNNfVvRmW6fz65JBx2DvUWwJBAJUSIt0P3JskAhihlZvL4aMcG1+3hpgJjZD6FFy8QXTN/4YjKWJ/Oha7ola8jWF2zkoRn4+sqCN2ckfadXcRrZUCQGPbMwFWK4poXd3jBJvtS0dgCQUylHYwOd3zPaKu8A80GgCv8miF/i4yZKSzihsmrN3gzJXxKwXfO9/wPvUnP3MCQQDG0kXz0RHjMQYfHaNFltO81lhyCzfSx+zPPcmAvcskJmW7vAg58Z+gPcpO9nfNyU+OUG7PlCiXj/yAJJ9CfDub";

$API = new elevenlabsAPI();

switch ($act) {
    case "cargarTabula":
        #region cargarTabula 
        $d = jsonStart();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador([]);

        $coleccion = "avLlamadasRealizadas";
        $condition = [];

        $campos = [];

        $ngTabula->setQueryDatos(
            $coleccion,
            $condition,
            $campos,
            ['fecha' => -1]
        );

        $ngTabula->setPreparaDatos(function ($campos) {
            $campos["mostrarDetalle"] = false;
            return $campos;
        });

        $json = $ngTabula->responde();
        #endregion 
        break;
    case "generarLlamada":
        //<editor-fold defaultstate="collapsed" desc="generarLlamada">  
        $d = jsonStart();
        $token = sha1($CLAVE_PRIVADA . date("Y-m-d H:i")  . "llamar");

        $telefono = expect_safe_html($d["telefono"]);
        $nombre = expect_safe_html(destilda($d["nombre"]));
        $nombre_deudor = expect_safe_html(destilda($d["nombre_deudor"]));
        $nombre_cartera = expect_safe_html(destilda($d["nombre_cartera"]));
        $nro_cuotas_vencidas = expect_integer($d["nro_cuotas_vencidas"]);
        $dias_mora = expect_integer($d["dias_mora"]);
        $monto_total_vencido = intval(expect_double($d["monto_total_vencido"]));
        $nro_operacion = expect_safe_html($d["nro_operacion"]);
        $ruc_empresa = expect_safe_html($d["ruc_empresa"]);
        $nombre_empresa = expect_safe_html(destilda($d["nombre_empresa"]));
        $generoAgente = expect_safe_html($d["generoAgente"]);
        $tipoAgente = expect_safe_html($d["tipoAgente"]);
        $diasCorte = expect_integer($d["dia_corte"]);
        $direccion = expect_safe_html(destilda($d["direccion"]));
        $saludo = expect_safe_html(destilda($d["saludo"]));
        $conversacion = expect_safe_html(destilda($d["conversacion"]));
        $telefonoLlamar = expect_safe_html($d["numero_telefono_agente"]);

        $curl = curl_init();

        $params = [
            "genero_agente" => $generoAgente,
            "tipo_agente" => $tipoAgente,
            "telefono" => "+593" . $telefono,
            "nombre" => $nombre,
            "nombre_deudor" => $nombre_deudor,
            "nombre_cartera" => $nombre_cartera,
            "nro_cuotas_vencidas" => $nro_cuotas_vencidas,
            "dias_mora" => $dias_mora,
            "monto_total_vencido" => $monto_total_vencido,
            "nro_operacion" => $nro_operacion,
            "ruc_empresa" => $ruc_empresa,
            "nombre_empresa" => $nombre_empresa,
            "direccion" => $direccion,
            "dia_corte" => $diasCorte,
            "saludo" => $saludo,
            "conversacion" => $conversacion,
            "numero_telefono_agente" => $telefonoLlamar
        ];

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $URL . '/llamar',
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 0,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST => 'POST',
        //     CURLOPT_POSTFIELDS => json_encode($params),
        //     CURLOPT_HTTPHEADER => array(
        //         'Content-Type: application/json'
        //     ),
        // ));

        // $response = curl_exec($curl);

        // curl_close($curl);

        // $resp = json_decode($response, true);

        $resp = $API->api_generarLlamada($params, "cobranza");

        guardarLlamada($params, $resp);
        $json = $resp;

        //</editor-fold>
        break;
    case "obtenerDetalle":
        //<editor-fold defaultstate="collapsed" desc="obtenerDetalle">  
        $d = jsonStart();
        $conv = expect_safe_html($d["conv"]);
        $id  = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avLlamadasRealizadas", ["_id" => $mongo->String2MongoId($id)]);

        if ($cursor > 0) {
            while ($row = $mongo->siguientex()) {
                if (isset($row["detalle"]) && is_array($row["detalle"]) && count($row["detalle"]) > 0) {
                    $json["respuesta"] = $row["detalle"];
                } else {
                    // $curl = curl_init();
                    // $token = sha1($CLAVE_PRIVADA . date("Y-m-d H:i")  . "llamada");
                    // $laUrl = $URL . "//llamada//" . $conv . "?token=" . $token;
                    // curl_setopt_array($curl, array(
                    //     CURLOPT_URL => $laUrl,
                    //     CURLOPT_RETURNTRANSFER => true,
                    //     CURLOPT_ENCODING => '',
                    //     CURLOPT_MAXREDIRS => 10,
                    //     CURLOPT_TIMEOUT => 0,
                    //     CURLOPT_FOLLOWLOCATION => true,
                    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    //     CURLOPT_CUSTOMREQUEST => 'GET',
                    // ));

                    // $response = curl_exec($curl);

                    // curl_close($curl);

                    // $resp = json_decode($response, true);

                    $resp = $API->api_obtenerDetalleConversacion($conv);
                    //actualizarLlamada($id, utf8_converter($resp), "");
                    $json["respuesta"] = $resp;
                }
            }
        } else {
            $json["error"]  = "No existe la llamada";
        }
        //</editor-fold>
        break;
    case "obtenerAudio":
        //<editor-fold defaultstate="collapsed" desc="obtenerAudio">  
        $d = jsonStart();
        $conv = expect_safe_html($d["conv"]);
        $id  = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avLlamadasRealizadas", ["_id" => $mongo->String2MongoId($id)]);

        if ($cursor > 0) {
            while ($row = $mongo->siguientex()) {
                if (isset($row["audio"]) && $row["audio"] != "") {
                    $json["respuesta"] = str_replace(BASEFOLDER, BASEURL, str_replace("\\", "", $row["audio"]));
                } else {
                    // $curl = curl_init();
                    // $token = sha1($CLAVE_PRIVADA . date("Y-m-d H:i")  . "audio");
                    // $laUrl = $URL . "//audio//" . $conv . "?token=" . $token;
                    // curl_setopt_array($curl, array(
                    //     CURLOPT_URL => $laUrl,
                    //     CURLOPT_RETURNTRANSFER => true,
                    //     CURLOPT_ENCODING => '',
                    //     CURLOPT_MAXREDIRS => 10,
                    //     CURLOPT_TIMEOUT => 0,
                    //     CURLOPT_FOLLOWLOCATION => true,
                    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    //     CURLOPT_CUSTOMREQUEST => 'GET',
                    // ));

                    // $response = curl_exec($curl);

                    // $nombreArchivo = $id . ".mp3";
                    // $path = BASEFOLDER . "web/cacheFolder/" . $nombreArchivo;
                    // $archivo = fopen($path, "w");
                    // fwrite($archivo, $response);
                    // fclose($archivo);

                    // curl_close($curl);
                    $resp = $API->api_obtenerAudioConversacion($conv);

                    if (isset($resp["datos"]["path"])) {
                        actualizarLlamada($id, $row["detalle"], $resp["datos"]["path"]);
                        $json["respuesta"] = $resp["datos"]["path"];
                    } else {
                        $json["respuesta"] = $resp["mensaje"];
                    }
                }
            }
        } else {
            $json["error"]  = "No existe la llamada";
        }
        //</editor-fold>
        break;
    case "eliminarLlamada":
        //<editor-fold defaultstate="collapsed" desc="eliminarLlamada">  
        $d = jsonStart();
        $conv = expect_safe_html($d["conv"]);
        $id  = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();

        $curl = curl_init();

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $URL . "//llamada//" . $conv . "?token=" . $token,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 0,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST => 'DELETE',
        // ));

        // $response = curl_exec($curl);
        // curl_close($curl);
        // $resp = json_decode($response, true);
        $conv = "conv_01jvmvmy3jeybb9n20bha9yjnn";
        $resp = $API->api_eliminarConversacion($conv);
        $json["respuesta"] = $resp;

        //</editor-fold>
        break;
}

function guardarLlamada($parametros, $resultado)
{
    $mongo = new MYMONGODB();
    $nuevo = [
        "parametros" => $parametros,
        "resultado" => $resultado,
        "idLlamada" => isset($resultado["datos"]["callSid"]) ? $resultado["datos"]["callSid"] : "",
        "fecha" => time(),
        "estado" => isset($resultado["datos"]["callSid"]) ? "REALIZADA" : "ERROR",
        "mensaje" => isset($resultado["mensaje"]) ? $resultado["mensaje"] : "",
        "detalle" => [],
        "audio" => "",
        "generadaPorId" => intval($_SESSION[MID . "userId"]),
        "generadaPorNombre" => $_SESSION[MID . "userNombre"],
    ];
    $mongo->guardar("avLlamadasRealizadas", $nuevo);
}

function actualizarLlamada($id, $detalle, $audio)
{
    $mongo = new MYMONGODB();
    $item = [
        "detalle" => $detalle,
        "audio" => $audio,
    ];
    $mongo->actualizar("avLlamadasRealizadas", ["_id" => $mongo->String2MongoId($id)], $item);
}

jsonEnd($json, $limpiar);


?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
