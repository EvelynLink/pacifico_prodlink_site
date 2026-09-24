<?php

/** API para la interaccion con los apis de livekit
 */

use function PHPSTORM_META\type;

require_once("../comunes/classes/class.API.php");

class linkLiveKitAPI extends API
{
    private $PUBLIC_KEY = "";
    private $PRIVATE_KEY = "";
    private $URL = "";
    private $VERSION = "";

    function __construct()
    {
        $cnf = getConf("Canales Masivos");
        $private = isset($cnf["Link livekit private key"][0]) ? $cnf["Link livekit private key"][0] : "";
        $public = isset($cnf["Link livekit public key"][0]) ? $cnf["Link livekit public key"][0] : "";
        $url = isset($cnf["Link livekit url"][0]) ? $cnf["Link livekit url"][0] : "";
        $version = isset($cnf["Link livekit url version"][0]) ? $cnf["Link livekit url version"][0] : "";
        if ($private == "" || $url == "" || $private == null || $url == null) {
            throw new Exception("No se encuentran las variables de configuración necesarias para el api de Link LiveKit", 1);
        }
        $this->PRIVATE_KEY = $private;
        $this->URL = $url;
        $this->PUBLIC_KEY = $public;
        $this->VERSION = $version;
    }

    #region: "Funciones generales"

    /**
     * Devuelve un array de respuesta estandar
     * 
     * @param string $estado OK o ERROR
     * @param string $mensaje cualquier mensaje descriptivo del proque el estado
     * @param mixed $datos cualquier tipo de datos que requiere que devuelva la respuesta
     * 
     * @return array {estado: string, mensaje: string, datos: mixed, fecha: string}
     */
    private function responder($estado, $mensaje, $datos)
    {
        return  [
            "estado" => $estado,
            "mensaje" => $mensaje,
            "datos" => $datos,
            "fecha" => date("d/m/Y H:i:s")
        ];
    }

    /**
     * Valida los campos requeridos en un array
     * 
     * @param array $requeridos los campos que son requeridos
     * @param array $parametros el array en el que se valida que contenga los campos requeridos
     * 
     * @return array de los campos requeridos que no existen
     */
    private function validarRequeridos($requeridos, $parametros)
    {
        $faltantes = [];
        foreach ($requeridos as $key => $value) {
            if (!array_key_exists($value, $parametros)) {
                $faltantes[] = $value;
            }
        }
        return $faltantes;
    }

    /**
     * Quita tildes de un texto
     * 
     * @param string $txt el texto al que se le van a quitar las tildes
     * 
     * @return string el texto con las tildes reemplazadas
     */
    private function miDestilda($txt)
    {
        return utf8_2_encode(strtr($txt, "áéíóúüÁÉÍÓÚÜ", "aeiouuAEIOUU"));
    }

    #endregion

    #region: "Funciones API"

    function api_obtenerLlamadas($agentes, $estados, $cuantos, $saltar, $fecha_desde, $fecha_hasta)
    {
        $agentes = expect_safe_html($agentes);
        $estados = expect_safe_html($estados);
        $cuantos = expect_integer($cuantos);
        $saltar = expect_integer($saltar);
        $fecha_desde = expect_integer($fecha_desde);
        $fecha_hasta = expect_integer($fecha_hasta);

        if ($cuantos <= 0) {
            return $this->responder("ERROR", "Debe indicar cuantos registros regresar", []);
        }

        if ($saltar < 0) {
            return $this->responder("ERROR", "Debe indicar cuantos registros saltar", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "list-calls");
        $parametros = [
            "token" => $token,
            "filter_criteria" => [
                "agent_id" => $agentes,
                "call_status" => $estados,
                "start_timestamp" => [
                    "upper_threshold" => $fecha_hasta,
                    "lower_threshold" => $fecha_desde
                ]
            ],
            "sort_order" => "descending",
            "limit" => 0,
            "skip" => $saltar
        ];

        if (count($agentes) == 0) {
            unset($parametros["filter_criteria"]["agent_id"]);
        }
        if (count($estados) == 0) {
            unset($parametros["filter_criteria"]["call_status"]);
        }
        if ($fecha_desde == 0 || $fecha_hasta == 0) {
            unset($parametros["filter_criteria"]["start_timestamp"]);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/list-calls',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            $llamadas = [];
            foreach ($resp["datos"] as $value) {
                $llamadas[] = $value;
            }
            return $this->responder("OK", "Lista de llamadas", $llamadas);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de llamadas. " . $resp["mensaje"], []);
        }
    }

    function api_crearLlamada($agente, $telefono, $datos, $telefonoDesde, $idOriginal = "")
    {
        $agente = expect_safe_html($agente);
        $telefono = expect_safe_html($telefono);
        $datos = expect_safe_html($datos);

        if ($agente == "") {
            return $this->responder("ERROR", "Debe indicar el agente con el cual llamar", []);
        }

        if ($telefono == "") {
            return $this->responder("ERROR", "Debe indicar el telefono al que llamar", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "create-phone-call");
        $parametros = [
            "token" => $token,
            "from_number" => $telefonoDesde,
            "to_number" => $telefono,
            "agent_id" => $agente,
            "metadata" => [
                "sitio" => BASEURL,
                "origen" => "lanzador_llamadas",
                "id_original" => $idOriginal
            ],
            "variables" => $datos,
            "prioridad" => 0 //TODO: recibir como parametro?
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/create-phone-call',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {

            return $this->responder("OK", $resp["mensaje"], ["call_id" => $resp["mensaje"]]);
        } else {
            return $this->responder("ERROR", $resp["mensaje"], []);
        }
    }

    function api_generarLlamada($agente, $telefono, $datos, $telefonoDesde, $idOriginal = "")
    {
        return $this->api_crearLlamada($agente, $telefono, $datos, $telefonoDesde, $idOriginal);
    }

    function api_eliminarLlamada($id_llamada)
    {
        if ($id_llamada == "") {
            return $this->responder("ERROR", "Debe indicar que llamada eliminar", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "delete-call");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/delete-call/" . $id_llamada,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Llamada eliminada", []);
        } else {
            return $this->responder("ERROR", "No se pudo eliminar la llamada. " . $resp["mensaje"], []);
        }
    }

    function api_traerDetalleLlamada($id_llamada)
    {
        if ($id_llamada == "") {
            return $this->responder("ERROR", "Debe indicar que llamada mostrar", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "get-call");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/get-call/' . $id_llamada,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Llamada encontrada", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la llamada. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerDetalleConversacion($id_llamada, $descargaAudio = false, $fuerza = false)
    {
        if ($id_llamada == "") {
            return $this->responder("ERROR", "Debe indicar que llamada mostrar", []);
        }

        $mongo = new MYMONGODB();
        if (!$fuerza) {
            $cursor = $mongo->buscar("avDetalleConversacionesLink", ["idConversacion" => $id_llamada], [], ["_id" => -1], 1);
        } else {
            $cursor = 0;
        }
        if ($cursor > 0) {
            $row = $mongo->siguiente();
            unset($row["_id"]);
            $row["origen"] = "caché";
            return $this->responder("OK", "Llamada encontrada",  $row);
        } else {

            $curl = curl_init();
            $token = $this->generaToken($this->PRIVATE_KEY, "get-call");
            $parametros = [
                "token" => $token
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/get-call/' . $id_llamada,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => json_encode($parametros),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
            ]);
            $response = curl_exec($curl);

            if ($response === false) {
                if (curl_errno($curl)) {
                    curl_close($curl);
                    return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
                }
            }
            curl_close($curl);
            $resp = json_decode($response, true);

            if ($resp["estado"] == "ok") {

                $transcripcion = [];
                if (isset($resp["datos"]["transcripcion"])) {
                    foreach ($resp["datos"]["transcripcion"]["items"] as $key => $value) {
                        if ($value["type"] == "message") {
                            $mensajes = [];
                            foreach ($value["content"] as $key1 => $value1) {
                                $mensajes[]  = utf8_2_decode($value1);
                            }
                            $tiempo = 0;
                            if (isset($resp["datos"]["transcripcion"]["items"][$key]["metrics"]["started_speaking_at"])) {
                                $tiempo = round($resp["datos"]["transcripcion"]["items"][$key]["metrics"]["stopped_speaking_at"] - $resp["datos"]["transcripcion"]["items"][$key]["metrics"]["started_speaking_at"], 2);
                            }
                            $item = [
                                "direccion" => $value["role"] == "assistant" ? "agente" : "cliente",
                                "mensaje" => join(". ", $mensajes),
                                "tiempoTranscurridoSegundos" => $tiempo
                            ];
                            $transcripcion[] = $item;
                        }
                    }
                }

                $audio = "";
                if ($descargaAudio) {
                    $descargarAudio = $this->api_traerAudioLlamada($id_llamada);
                    if ($descargarAudio["estado"] == "OK") {
                        $audio = count($descargarAudio["datos"]) > 0 ? $descargarAudio["datos"][0] : "";
                    }
                }

                $detalle = [
                    "status" => $resp["datos"]["estado"],
                    "idAgente" => $resp["datos"]["agent_id"],
                    "idConversacion" => $resp["datos"]["_id"],
                    "archivoDetalle" => "",
                    "archivoAudio" => $audio,
                    "fechaInicio" => intval($resp["datos"]["fecha_inicio"]),
                    "fechaContesta" => intval($resp["datos"]["fecha_inicio"]), //repetido, para mantener continuidad en el numero de campos con elevenlabs
                    "fechaFin" => intval($resp["datos"]["fecha_fin"]),
                    "resumen" => isset($resp["datos"]["analisis"]["resumen"]) ? utf8_2_decode($resp["datos"]["analisis"]["resumen"]) : $transcripcion,
                    "analisisExito" => "",
                    "transcripcion" => $transcripcion,
                    "duracionSegundos" => intval($resp["datos"]["duracion_segundos"]),
                    "costoCreditos" => 0,
                    "costoLLM" => 0, //para mantener continuidad en el numero de campos con elevenlabs
                    "telefono" => $resp["datos"]["to_number"], //para mantener continuidad en el numero de campos con elevenlabs
                    "finalizacion" => $resp["datos"]["motivo_finaliza"]["stop"] . "/" . $resp["datos"]["motivo_finaliza"]["reason"],
                    "sentimiento" => isset($resp["datos"]["analisis"]["sentimiento_general"]) ? $resp["datos"]["analisis"]["sentimiento_general"] : "",
                    "error" => "", //para mantener continuidad en el numero de campos con elevenlabs
                    "callLog" => "",
                    "variables_dinamicas_cliente" => isset($resp["datos"]["variables_extraidas_function"]) ? $resp["datos"]["variables_extraidas_function"] : [],
                    "variables_dinamicas_analisis" => isset($resp["datos"]["variables_extraidas"]) ? $resp["datos"]["variables_extraidas"] : [],
                    "usoTokens" => ["total" => 0, "promedio" => 0, "numero_pedidos" => 0],
                    "fecha_consulta" => time()
                ];
                $x = $mongo->buscar("avDetalleConversacionesLink", ["idConversacion" => $id_llamada]);

                if ($x > 0) {
                    $mongo->actualizar("avDetalleConversacionesLink", ["idConversacion" => $id_llamada], $detalle);
                } else {
                    $mongo->guardar("avDetalleConversacionesLink", $detalle);
                }
                $detalle["origen"] = "servidor";

                return $this->responder("OK", "Llamada encontrada", $detalle);
            } else {
                return $this->responder("ERROR", "No se pudo recuperar la llamada. " . $resp["mensaje"], []);
            }
        }
    }

    function api_traerAudioLlamada($id_llamada)
    {
        if ($id_llamada == "") {
            return $this->responder("ERROR", "Debe indicar que llamada mostrar", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "get-audio");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/get-audio/' . $id_llamada,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);

        $destino = BASEFOLDER . "web/cacheFolderSC/" . $id_llamada . ".mp3";

        if (file_exists($destino)) {
            unlink($destino);
        }
        $guarda = file_put_contents($destino, $response);

        if ($guarda === FALSE) {
            return $this->responder("ERROR", "Audio no se pudo descargar", []);
        } else {
            $final = str_replace(BASEFOLDER, BASEURL, $destino);
            return $this->responder("OK", "Audio descargado", [$final]);
        }
    }

    function api_desprogramarLlamada($id_llamada, $estado)
    {
        if ($id_llamada == "") {
            return $this->responder("ERROR", "Debe indicar que llamada desprogramar", []);
        }

        if ($estado == "") {
            return $this->responder("ERROR", "Debe indicar el nuevo estado", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "update-call");
        $parametros = [
            "token" => $token,
            "estado" => $estado
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/update-call/" . $id_llamada,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Llamada modificada", []);
        } else {
            return $this->responder("ERROR", "No se pudo modificar la llamada. " . $resp["mensaje"], []);
        }
    }

    function api_traerConfiguracion()
    {
        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "get-config");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/get-config',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Configuracion encontrada", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la configuracion. " . $resp["mensaje"], []);
        }
    }

    function api_actualizarConfiguracion($agente_id, $configuracion)
    {
        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "update-config");

        if (isset($_SESSION[MID . "userNombre"])) {
            $configuracion["usuario_modificacion"] = $_SESSION[MID . "userNombre"];
        }
        if (isset($configuracion["segundos_pausa_entre_procesos"])) {
            $configuracion["segundos_pausa_entre_procesos"] = intval($configuracion["segundos_pausa_entre_procesos"]);
        }
        if (isset($configuracion["registros_procesar"])) {
            $configuracion["registros_procesar"] = intval($configuracion["registros_procesar"]);
        }
        if (isset($configuracion["registros_finalizado_procesar"])) {
            $configuracion["registros_finalizado_procesar"] = intval($configuracion["registros_finalizado_procesar"]);
        }
        if (isset($configuracion["segundos_pausa_entre_procesos_finalizado"])) {
            $configuracion["segundos_pausa_entre_procesos_finalizado"] = intval($configuracion["segundos_pausa_entre_procesos_finalizado"]);
        }
        if (isset($configuracion["temperatura_analisis"])) {
            $configuracion["temperatura_analisis"] = floatval($configuracion["temperatura_analisis"]);
        }
        if (isset($configuracion["pausa_servicio"])) {
            $configuracion["pausa_servicio"] = intval($configuracion["pausa_servicio"]);
        }

        $parametros = [
            "token" => $token,
            "configuracion" => $configuracion
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/update-config/' . $agente_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Configuracion actualizada", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo actualizar la configuracion. " . $resp["mensaje"], []);
        }
    }

    function api_crearConfiguracion($agente_id, $configuracion)
    {
        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "create-config");

        if (isset($_SESSION[MID . "userNombre"])) {
            $configuracion["usuario_modificacion"] = $_SESSION[MID . "userNombre"];
        }

        $parametros = [
            "token" => $token,
            "configuracion" => $configuracion
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/create-config/' . $agente_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($parametros),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            return $this->responder("OK", "Configuracion creada", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo crear la configuracion. " . $resp["mensaje"], []);
        }
    }

    #endregion
}
?>
<? //_FIN_DE_ARCHIVO 
?>