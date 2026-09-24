<?php

/** API para la interaccion con los apis de elevenlabs
 */
require_once("../comunes/classes/class.API.php");

class retellAPI extends API
{
    private $RETELL_API_KEY = "";
    private $URL = "";
    private $URL2 = "";
    private $DIRECTORIO = "web/cacheFolder/"; //TODO: directorio definitivo?

    function __construct()
    {
        $cnf = getConf("Canales Masivos");
        $api = isset($cnf["Retell api key"][0]) ? $cnf["Retell api key"][0] : "";
        $url = isset($cnf["Retell url"][0]) ? $cnf["Retell url"][0] : "";
        $url2 = isset($cnf["Retell url2"][0]) ? $cnf["Retell url2"][0] : "";
        if ($api == "" || $url == "") {
            throw new Exception("No se encuentran las variables de configuración necesarias para el api de retell AI", 1);
        }
        $this->RETELL_API_KEY = $api;
        $this->URL = $url;
        $this->URL2 = $url2;
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

    /**
     * Valida los campos requeridos para el agente de cobranza, limpia los datos
     * 
     * @param array $data los datos que se recibio
     * 
     * @return array si hay error retorna la respuesta de error caso contrario los campos formateados
     */
    private function validarLimpiarDatosCobranza($data, $requeridos)
    {
        // $requeridos = [
        //     "nro_cuotas_vencidas",
        //     "dias_mora",
        //     "nro_operacion",
        //     "monto_total_vencido",
        //     "cuota",
        //     "monto_total_por_vencer",
        //     "cuotas_por_vencer",
        //     "dia_corte",
        //     "marca",
        //     "producto",
        //     "fecha_vencimiento",
        //     "nombreCorto",
        //     "saludos"
        // ];

        $faltantes = $this->validarRequeridos($requeridos, $data);

        if (count($faltantes) > 0) {
            return $this->responder("ERROR", "Campos requeridos faltantes", $faltantes);
        } else {
            if ($data["telefono"] != "34623051132" && $data["telefono"] != "+34623051132") {
                if (substr($data["telefono"], 0, 5) != "+5939") {
                    return $this->responder("ERROR", "Teléfono incorrrecto", ["El número de teléfono al cuál llamar debe tener el código del país +593"]);
                }
                if (strlen($data["telefono"]) != 13) {
                    return $this->responder("ERROR", "Teléfono incorrrecto", ["El número de teléfono al cuál llamar debe ser un número válido p.e: +593986259845"]);
                }
                $numeros = array_unique(str_split(substr($data["telefono"], 4)));
                if (count($numeros) < 2) {
                    return $this->responder("ERROR", "Teléfono incorrrecto", ["El número de teléfono al cuál llamar debe ser un número válido p.e: +593986259845"]);
                }
            }

            foreach ($requeridos as $value) {
                $dinamicos[$value] = strval($this->miDestilda($data[$value]));
            }
            // $dinamicos = [
            //     "nro_cuotas_vencidas" => strval($data["nro_cuotas_vencidas"]),
            //     "dias_mora" => strval($data["dias_mora"]),
            //     "nro_operacion" => strval($data["nro_operacion"]),
            //     "monto_total_vencido" => strval($data["monto_total_vencido"]),
            //     "cuota" => strval($data["cuota"]),
            //     "monto_total_por_vencer" => strval($data["monto_total_por_vencer"]),
            //     "cuotas_por_vencer" => strval($data["cuotas_por_vencer"]),
            //     "dia_corte" => strval($data["dia_corte"]),
            //     "marca" =>  $this->miDestilda($data["marca"]),
            //     "producto" =>  $this->miDestilda($data["producto"]),
            //     "fecha_vencimiento" => strval($data["fecha_vencimiento"]),
            //     "nombreCorto" =>  $this->miDestilda($data["nombreCorto"]),
            //     "saludos" =>  $this->miDestilda($data["saludos"]),
            // ];
            return $dinamicos;
        }
    }

    /**
     * Verifica si un proceso ya esta corriendo, si hay que eliminar un proceso bloqueado o levantar uno nuevo
     */
    public function verificaDesdeArchivoPid($nombreProceso)
    {
        require_once(BASEFOLDER . "comunes/classes/class.myredisdb.php");
        $miPid = getmypid();
        $identificadorProceso = "pid:" . $nombreProceso;
        $redis = new MYREDISDB();
        $valores = $redis->query("hGetAll", $identificadorProceso);
        if (isset($valores["ultimoPid"]) && isset($valores["ultimaEscritura"])) {
            $ultimoPid = $valores["ultimoPid"];
            $ultimaEscritura = $valores["ultimaEscritura"];
            if ($ultimaEscritura < time() - 30) {
                trigger_error("I'M THE KILLER OF " . $ultimoPid . " (" . $nombreProceso . ") " . ((time() - 30) - $ultimaEscritura) . " seg pasados");
                exec("kill " . $ultimoPid); //hace mas de 30 segundos?? matele porque algo le paso.
                if ($redis->query("hMSet", $identificadorProceso, [
                    "ultimoPid" => $miPid,
                    "ultimaEscritura" => time()
                ])) {
                    //y quedo yo como proceso actual
                } else {
                    trigger_error("No se pudo escribir las variables de control a Redis " . $identificadorProceso, E_USER_ERROR);
                    exit;
                }
            } else {
                //hace menos de 30 segundos? entonces salga y dejele seguir al otro proceso
                exit;
            }
        } else {
            //es la primera vez en la vida que se levante el proceso
            if ($redis->query("hMSet", $identificadorProceso, [
                "ultimoPid" => $miPid,
                "ultimaEscritura" => time()
            ])) {
                //y quedo yo como proceso actual
            } else {
                trigger_error("No se pudo escribir las variables de control a Redis " . $identificadorProceso, E_USER_ERROR);
                exit;
            }
        }
    }

    /**
     * Reporta que un proceso esta corriendo
     */
    public function reporteArchivoPid($nombreProceso)
    {
        //__Descripcion__: Reporta que un proceso esta corriendo
        $miPid = getmypid();
        $identificadorProceso = "pid:" . $nombreProceso;
        $redis = new MYREDISDB();
        if ($redis->query("hMSet", $identificadorProceso, [
            "ultimoPid" => $miPid,
            "ultimaEscritura" => time()
        ])) {
            //nothing
        } else {
            trigger_error("No se pudo escribir las variables de control a Redis " . $identificadorProceso, E_USER_ERROR);
            exit;
        }
    }

    #endregion

    #region: "Funciones API"

    /**
     * Devuelve los valores de concurrencia del plan contratado
     * 
     * @return array {estado: "OK", mensaje: "Concurrencia", datos: array con las concurrencias, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la concurrencia", datos: [], fecha: string}
     */
    function api_devolverConcurrencia()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'get-concurrency',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if ($resp) {
            return $this->responder("OK", "Concurrencia", $resp);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la concurrencia", []);
        }
    }

    #region: "Funciones llamadas"

    /**
     * Inicia una llamada a un agente de elevenlabs
     * 
     * @param array $data todas las variables dinamicas y requeridas para la llamada
     * @param string $tipo el tipo de agente que debe llamar, si esta vacio no se validan datos ni se envian datos dinamicos
     * 
     * @return array {estado: string, mensaje: string, datos: mixed, fecha: string}
     */
    function api_generarLlamada($data, $variablesDinamicas, $tipo = "")
    {

        //estos campos deben venir siempre en cualquier agente
        $requeridos = [
            "tipo_agente",
            "telefono",
            "numero_telefono_agente"
        ];
        $faltantes = $this->validarRequeridos($requeridos, $data);
        if (count($faltantes) > 0) {
            return $this->responder("ERROR", "Campos requeridos faltantes", $faltantes);
        }

        $datos_dinamicos = [];
        //cada tipo de agente puede tener distintos campos dinamicos, aqui se validan y limpian esos campos
        switch ($tipo) {
            case 'cobranza':
                $datos_dinamicos = $this->validarLimpiarDatosCobranza($data, $variablesDinamicas);
                break;
        }

        if (isset($datos_dinamicos["estado"]) && $datos_dinamicos["estado"] == "ERROR") {
            return $datos_dinamicos;
        }

        //armo el request
        $agentId = $data["tipo_agente"];
        $telefonoLlamar = $data["telefono"];
        $telefonoDesdeLlamar = $data["numero_telefono_agente"];

        $body = [
            "from_number" => $telefonoDesdeLlamar,
            "to_number" => $telefonoLlamar,
            "override_agent_id" => $agentId,
            "retell_llm_dynamic_variables" => $datos_dinamicos,
        ];

        if (count($datos_dinamicos) == 0) {
            unset($body["retell_llm_dynamic_variables"]);
        }
        if (isset($data["metadata"])) {
            $body["metadata"] = $data["metadata"];
        }

        $encode = json_encode($body);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . 'create-phone-call',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
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

        if (isset($resp["call_status"])) {
            if ($resp["call_status"] == "registered") {
                return $this->responder("OK", $resp["call_status"], ["conversation_id" => $resp["call_id"], "callSid" => $resp["call_id"]]);
            } elseif ($resp["call_status"] == "error") {
                return $this->responder("ERROR", isset($resp["disconnection_reason"]) ? $resp["disconnection_reason"] : "Call with status: " . $resp["call_status"], ["conversation_id" => $resp["call_id"], "callSid" => $resp["call_id"]]);
            } else {
                return $this->responder("ERROR", "Call with status: " . $resp["call_status"], ["conversation_id" => $resp["call_id"], "callSid" => $resp["call_id"]]);
            }
        } else {
            if (isset($resp["error_message"])) {
                return $this->responder("ERROR", $resp["error_message"], []);
            } else {
                if (isset($resp["status"]) && $resp["status"] == "error") {
                    return $this->responder("ERROR", $resp["message"], []);
                } else {
                    return $this->responder("ERROR", "Error desconocido", []);
                }
            }
        }
    }

    #endregion

    #region: "Funciones conversacion"

    /**
     * Devuelve el detalle de una conversacion, la primera vez que se lee se guarda en una coleccion mongo y
     * las proximas consultas se devuelve lo que esta en mongo
     * 
     * @param string $idConversacion el id de la conversacion que se quiere buscar
     * @param string (opcional) $telefono el numero del telefono al cual se llamo, ya que el api no devuelve esa informacion
     * 
     * @return array {estado: "OK", mensaje: "Conversacion 132123", datos: array con el detalle de la conversacion, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar el detalle de la conversacion", datos: [], fecha: string}
     */
    function api_obtenerDetalleConversacion($idConversacion, $telefono = "", $fuerza = false)
    {
        if ($idConversacion == "") {
            return $this->responder("ERROR", "No se ha indicado el id de conversacion a buscar", []);
        }

        $mongo = new MYMONGODB();
        if (!$fuerza) {
            $cursor = $mongo->buscar("avDetalleConversacionesRetell", ["idConversacion" => $idConversacion], [], ["_id" => -1], 1);
        } else {
            $cursor = 0;
        }
        if ($cursor > 0) {
            $row = $mongo->siguiente();
            unset($row["_id"]);
            $row["origen"] = "caché";
            return $this->responder("OK", "Conversacion " . $idConversacion,  $row);
        } else {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->URL . 'get-call/' . $idConversacion,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->RETELL_API_KEY
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

            //trigger_error(json_encode($resp["llm_token_usage"]));
            // print_h($resp["call_status"]);
            // print_h($resp["call_id"]);
            if (isset($resp["call_id"]) && isset($resp["call_status"])) {
                $resp["origen"] = "servidor";
                $resp["fecha_consulta"] = time();

                //archivo public log
                $publicLog = "";
                if (isset($resp["public_log_url"]) && $resp["public_log_url"] != "") {
                    //$publicLog = file_get_contents($resp["public_log_url"]);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $resp["public_log_url"]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                    $publicLog = curl_exec($ch);
                    curl_close($ch);
                }

                if ($resp["call_status"] == "ended" || $resp["call_status"] == "error" || $resp["call_status"] == "not_connected") {
                    //archivo json detalle
                    $nombreArchivo = $idConversacion . ".json";
                    $path = BASEFOLDER . $this->DIRECTORIO . $nombreArchivo;
                    if (file_exists($path)) {
                        unlink($path);
                        sleep(2);
                    }
                    $escribe = file_put_contents($path, json_encode($resp));

                    $transcripcion = [];
                    if (isset($resp["transcript_object"])) {
                        foreach ($resp["transcript_object"] as $value) {
                            $transcripcion[] = [
                                "direccion" => $value["role"] == "agent" ? "agente" : "cliente",
                                "mensaje" => $value["content"],
                                "tiempoTranscurridoSegundos" => isset($value["words"][0]) ? floatval($value["words"][0]["start"]) : "",
                            ];
                        }
                    }
                    if ($resp["call_status"] != "error" && $resp["call_status"] != "not_connected") {
                        $nombreAudio = $idConversacion . ".wav";
                        $patha = BASEFOLDER . $this->DIRECTORIO . $nombreAudio;
                        if (file_exists($patha)) {
                            unlink($patha);
                            sleep(2);
                        }
                        if (isset($resp["recording_url"])) {
                            $escribea = file_put_contents($patha, file_get_contents($resp["recording_url"]));
                        } else {
                            $escribea = false;
                        }
                    } else {
                        $escribea = false;
                    }

                    $usoTokens = ["total" => 0, "promedio" => 0, "numero_pedidos" => 0];
                    if (isset($resp["llm_token_usage"])) {
                        $total = 0;
                        foreach ($resp["llm_token_usage"]["values"] as $v) {
                            $total += $v;
                        }
                        $usoTokens["total"] = $total;
                        $usoTokens["promedio"] = $resp["llm_token_usage"]["average"];
                        $usoTokens["numero_pedidos"] = $resp["llm_token_usage"]["num_requests"];
                    }
                    $detalle = [
                        "status" => $resp["call_status"],
                        "idAgente" => $resp["agent_id"],
                        "idConversacion" => $resp["call_id"],
                        "archivoDetalle" => $escribe != false && $escribe > 0 ? $path : "",
                        "archivoAudio" => $escribea != false && $escribea > 0 ? $patha : "",
                        "fechaInicio" => intval($resp["start_timestamp"]),
                        "fechaContesta" => intval($resp["start_timestamp"]), //repetido, para mantener continuidad en el numero de campos con elevenlabs
                        "fechaFin" => intval($resp["end_timestamp"]),
                        "resumen" => isset($resp["call_analysis"]["call_summary"]) ? $resp["call_analysis"]["call_summary"] : (isset($resp["transcript"]) ? $resp["transcript"] : ""),
                        "analisisExito" => isset($resp["call_analysis"]["call_successful"]) ? ($resp["call_analysis"]["call_successful"] ? "SI" : "NO") :  "",
                        "transcripcion" => $transcripcion,
                        "duracionSegundos" => intval($resp["duration_ms"] / 1000),
                        "costoCreditos" => floatval($resp["call_cost"]["combined_cost"] / 100),
                        "costoLLM" => 0, //para mantener continuidad en el numero de campos con elevenlabs
                        "telefono" => $telefono, //para mantener continuidad en el numero de campos con elevenlabs
                        "finalizacion" => $resp["disconnection_reason"],
                        "sentimiento" => isset($resp["call_analysis"]["user_sentiment"]) ? $resp["call_analysis"]["user_sentiment"] : "",
                        "error" => $resp["call_status"] == "error" ? $resp["disconnection_reason"] : "", //para mantener continuidad en el numero de campos con elevenlabs
                        "callLog" => $publicLog == false ? "" : $publicLog,
                        "variables_dinamicas_cliente" => isset($resp["collected_dynamic_variables"]) ? $resp["collected_dynamic_variables"] : [],
                        "variables_dinamicas_analisis" => isset($resp["call_analysis"]["custom_analysis_data"]) ? $resp["call_analysis"]["custom_analysis_data"] : [],
                        "usoTokens" => $usoTokens
                    ];

                    $x = $mongo->buscar("avDetalleConversacionesRetell", ["idConversacion" => $idConversacion]);

                    if ($x > 0) {
                        $mongo->actualizar("avDetalleConversacionesRetell", ["idConversacion" => $idConversacion], $detalle);
                    } else {
                        $mongo->guardar("avDetalleConversacionesRetell", $detalle);
                    }
                    $detalle["origen"] = "servidor";
                    return $this->responder("OK", "Conversacion " . $idConversacion,  $detalle);
                } else {
                    $detalle = [
                        "status" => $resp["call_status"],
                        "idAgente" => $resp["agent_id"],
                        "idConversacion" => $resp["call_id"],
                        "archivoDetalle" => "",
                        "archivoAudio" => "",
                        "fechaInicio" => isset($resp["start_timestamp"]) ? intval($resp["start_timestamp"]) : 0,
                        "fechaContesta" => isset($resp["start_timestamp"]) ? intval($resp["start_timestamp"]) : 0, //repetido, para mantener continuidad en el numero de campos con elevenlabs
                        "fechaFin" => isset($resp["end_timestamp"]) ? intval($resp["end_timestamp"]) : 0,
                        "resumen" => isset($resp["call_analysis"]["call_summary"]) ? $resp["call_analysis"]["call_summary"] : (isset($resp["transcript"]) ? $resp["transcript"] : ""),
                        "analisisExito" => isset($resp["call_analysis"]["call_successful"]) ? ($resp["call_analysis"]["call_successful"] ? "SI" : "NO") :  "",
                        "transcripcion" => "",
                        "duracionSegundos" => isset($resp["duration_ms"]) ? intval($resp["duration_ms"] / 1000) : 0,
                        "costoCreditos" => floatval($resp["call_cost"]["combined_cost"] / 100),
                        "costoLLM" => 0, //para mantener continuidad en el numero de campos con elevenlabs
                        "telefono" => $telefono, //para mantener continuidad en el numero de campos con elevenlabs
                        "finalizacion" => isset($resp["disconnection_reason"]) ? $resp["disconnection_reason"] : "",
                        "sentimiento" => isset($resp["call_analysis"]["user_sentiment"]) ? $resp["call_analysis"]["user_sentiment"] : "",
                        "error" => $resp["call_status"] == "error" ? $resp["disconnection_reason"] : "", //para mantener continuidad en el numero de campos con elevenlabs
                        "callLog" => $publicLog == false ? "" : $publicLog,
                        "variables_dinamicas_cliente" => isset($resp["collected_dynamic_variables"]) ? $resp["collected_dynamic_variables"] : [],
                        "variables_dinamicas_analisis" => isset($resp["call_analysis"]["custom_analysis_data"]) ? $resp["call_analysis"]["custom_analysis_data"] : []
                    ];
                    return $this->responder("OK", "Conversacion " . $idConversacion,  $detalle);
                    //return $this->responder("OK", "Conversacion " . $idConversacion,  $resp);
                }
            } else {
                return $this->responder("ERROR", "No se pudo recuperar el detalle de la conversacion", []);
            }
        }
    }

    /**
     * Elimina una conversacion del servidor de elevenlabs
     * 
     * @param string $idConversacion el id de la conversacion que se quiere buscar
     * 
     * @return array {estado: "OK", mensaje: "Conversacion eliminada", datos: [], fecha: string}
     */
    function api_eliminarConversacion($idConversacion)
    {
        if ($idConversacion == "") {
            return $this->responder("ERROR", "No se ha indicado el id de conversacion a buscar", []);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . 'delete-call/' . $idConversacion,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        //TODO: como validar que si se elimino, la respuesta es vacia
        return $this->responder("OK", "Conversacion eliminada",  []);
    }

    /**
     * Obtiene un listado de las llamadas que se encuentren en 1 o varios estados
     * 
     * @param array $estado un array de strings que contiene los estados a buscar, posibles valores: registered, ongoing, ended, error 
     * 
     * @return array {estado: "OK", mensaje: "Lista de llamadas", datos: lista de llamadas, fecha: string}
     */
    function api_obtenerLlamadasPorEstado($estado)
    {
        if ($estado == null || $estado == "" || !is_array($estado) || count($estado) == 1) {
            return $this->responder("ERROR", "No se ha indicado el/los estados a buscar", []);
        }
        $criterio = "";
        if ($estado != "") {
            $criterio = [
                "filter_criteria" => [
                    "call_status" => $estado
                ]
            ];
        }
        $encode = json_encode($criterio);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . 'list-calls',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
                "Content-Type: application/json"
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

        if (is_array($resp)) {
            $llamadas = [];
            foreach ($resp as $value) {
                $llamadas[] = $value;
            }
            return $this->responder("OK", "Lista de llamadas", $llamadas);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de llamadas", []);
        }
    }

    #endregion

    #region: "Funciones agentes"

    /**
     * Devuelve un listado con los agentes creados
     * 
     * @return array {estado: "OK", mensaje: "Lista de agentes", datos: array con el listado de agentes, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de agentes", datos: [], fecha: string}
     */
    function api_obtenerAgentes()
    {
        //agentes voz
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'list-agents',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        $agentes = [];

        if (is_array($resp)) {
            foreach ($resp as $value) {
                $agentes[$value["agent_id"]] = $value;
            }
        }

        //agentes de chat
        $curla = curl_init();
        curl_setopt_array($curla, [
            CURLOPT_URL => $this->URL2 . 'list-chat-agents',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
            ],
        ]);
        $responsea = curl_exec($curla);

        if ($responsea === false) {
            if (curl_errno($curla)) {
                curl_close($curla);
                return $this->responder("ERROR", "Curl error: " . curl_error($curla), []);
            }
        }
        curl_close($curla);
        $respa = json_decode($responsea, true);

        if (is_array($respa)) {
            foreach ($respa as $value) {
                $agentes[$value["agent_id"]] = $value;
            }
        }

        if (count($agentes) > 0) {
            return $this->responder("OK", "Lista de agentes", array_values($agentes));
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de agentes", []);
        }
    }

    /**
     * Devuelve el detalle de un agente
     * 
     * @param string $idAgente el id del agente que se debe buscar
     * 
     * @return array que contiene el detalle del agente
     */
    function api_traerAgente($idAgente)
    {
        if ($idAgente == "") {
            return $this->responder("ERROR", "No se ha indicado el id del agente a buscar", []);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'get-agent/' . $idAgente,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (isset($resp["agent_id"])) {
            // unset($resp["conversation_config"]["asr"]);
            // unset($resp["conversation_config"]["turn"]);
            // unset($resp["conversation_config"]["tts"]);
            // unset($resp["conversation_config"]["conversation"]);
            // unset($resp["conversation_config"]["language_presets"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["tools"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["tool_ids"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["mcp_server_ids"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["knowledge_base"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["custom_llm"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["ignore_default_personality"]);
            // unset($resp["conversation_config"]["agent"]["prompt"]["rag"]);
            // unset($resp["platform_settings"]["evaluation"]);
            // unset($resp["platform_settings"]["widget"]);
            // unset($resp["platform_settings"]["data_collection"]);
            // unset($resp["platform_settings"]["ban"]);
            // unset($resp["platform_settings"]["workspace_overrides"]);
            // unset($resp["platform_settings"]["safety"]);
            // unset($resp["metadata"]);
            // unset($resp["access_info"]);
            return $this->responder("OK", "Agente " . $idAgente,  $resp);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar el detalle del agente", []);
        }
    }

    /**
     * Crear un agente
     * 
     * @param array $data un array con las opciones que se necesitan para crear un agente
     * 
     * @return array {estado: "OK", mensaje: "Agente creado {{id_agente}}", datos: [{{id_agente}}], fecha: string}
     */
    function api_crearAgente($data)
    {
        //TODO: validar campos requeridos
        if ($data == null || $data == "" || !is_array($data)) {
            return $this->responder("ERROR", "No se enviaron los datos del agente a crear", []);
        }

        $curl = curl_init();
        $encode = json_encode($data);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'create-agent',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
                'Content-Type: application/json'
            ]
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

        if (isset($resp["agent_id"])) {
            return $this->responder("OK", "Agente creado " . $resp["agent_id"], [$resp["agent_id"], $data]);
        } else {
            if (isset($resp["status"]) && $resp["status"] == "error") {
                return $this->responder("ERROR", $resp["message"], []);
            } else {
                return $this->responder("ERROR", "No se pudo crear el agente", []);
            }
        }
    }

    function api_traerPrompt($idLLM)
    {
        if ($idLLM == "") {
            return $this->responder("ERROR", "No se ha indicado el id del modelo LLM a buscar", []);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'get-retell-llm/' . $idLLM,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (isset($resp["llm_id"])) {
            return $this->responder("OK", "Agente LLM " . $idLLM,  $resp);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar el detalle del agente", []);
        }
    }

    /**
     * Actualizar el llm de un agente
     */
    function api_actualizarPrompSaludoAgente($idLlm, $data)
    {
        if ($idLlm == null || $idLlm == "") {
            return $this->responder("ERROR", "No se envio el id del agente a modificar", []);
        }
        //TODO: validar campos requeridos
        if ($data == null || $data == "" || !is_array($data)) {
            return $this->responder("ERROR", "No se enviaron los datos del agente a modificar", []);
        }

        $curl = curl_init();
        $encode = json_encode($data);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'update-retell-llm/' . $idLlm,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
                'Content-Type: application/json'
            ]
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

        if (isset($resp["llm_id"])) {
            return $this->responder("OK", "Agente modificado " . $resp["llm_id"], [$resp["llm_id"], $data]);
        } else {
            if (isset($resp["status"]) && $resp["status"] == "error") {
                return $this->responder("ERROR", $resp["message"], []);
            } else {
                return $this->responder("ERROR", "No se pudo modificar el agente", []);
            }
        }
    }

    #endregion

    #region: "Funciones obtener ids"

    /**
     * Devuelve un listado con las voces disponibles
     * 
     * @return array {estado: "OK", mensaje: "Lista de voces", datos: array con el listado de voces, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de voces", datos: [], fecha: string}
     */
    function api_obtenerVoces()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'list-voices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (is_array($resp)) {
            $voces = [];
            foreach ($resp as $value) {
                $voces[] = [
                    "voz_id" => $value["voice_id"],
                    "voz_nombre" => $value["voice_name"],
                    "proveedor" => $value["provider"],
                    "genero" => $value["gender"]
                ];
            }
            return $this->responder("OK", "Lista de voces", $voces);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de voces", []);
        }
    }

    /**
     * Devuelve un listado con los modelos LLM
     * 
     * @return array {estado: "OK", mensaje: "Lista de modelos LLM", datos: array con el listado de modelos LLM, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de modelos LLM", datos: [], fecha: string}
     */
    function api_obtenerModeloLLM()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'list-retell-llms',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (is_array($resp)) {
            $modelos = [];
            foreach ($resp as $value) {
                $modelos[] = [
                    "llm_id" => $value["llm_id"],
                    "version" => $value["version"],
                    "model" => $value["model"]
                ];
            }
            return $this->responder("OK", "Lista de modelos LLM", $modelos);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de modelos LLM", []);
        }
    }

    /**
     * Devuelve un listado con los telefonos
     * 
     * @return array {estado: "OK", mensaje: "Lista de agentes", datos: array con el listado de agentes, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de telefonos", datos: [], fecha: string}
     */
    function api_obtenerTelefonos()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'list-phone-numbers',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (is_array($resp)) {
            $telefonos = [];
            foreach ($resp as $value) {
                $telefonos[] = $value;
            }
            return $this->responder("OK", "Lista de telefonos", $telefonos);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de telefonos", []);
        }
    }

    #endregion

    #region: "Funciones agente conversacional"

    /**
     * Inicia un nuevo chat de en retell
     * 
     * @param array $data todas las variables dinamicas y requeridas para la llamada
     * @param array $variablesDinamicas todas las variables dinamicas que requiere
     * @param string $tipo el tipo de agente que debe llamar, si esta vacio no se validan datos ni se envian datos dinamicos
     * 
     * @return array {estado: string, mensaje: string, datos: mixed, fecha: string}
     */
    function api_iniciarChat($data, $variablesDinamicas, $tipo = "")
    {
        //estos campos deben venir siempre en cualquier agente
        $requeridos = [
            "agente",
            "telefono"
        ];
        $faltantes = $this->validarRequeridos($requeridos, $data);
        if (count($faltantes) > 0) {
            return $this->responder("ERROR", "Campos requeridos faltantes", $faltantes);
        }

        $datos_dinamicos = [];
        //cada tipo de agente puede tener distintos campos dinamicos, aqui se validan y limpian esos campos
        switch ($tipo) {
            case 'cobranza':
                $datos_dinamicos = $this->validarLimpiarDatosCobranza($data, $variablesDinamicas);
                break;
        }

        if (isset($datos_dinamicos["estado"]) && $datos_dinamicos["estado"] == "ERROR") {
            return $datos_dinamicos;
        }

        //armo el request
        $agentId = $data["agente"];
        $telefonoEscribe = $data["telefono"];
        $metadata = ["telefono" => $telefonoEscribe];
        $body = [
            "agent_id" => $agentId,
            "retell_llm_dynamic_variables" => $datos_dinamicos,
        ];

        if (count($datos_dinamicos) == 0) {
            unset($body["retell_llm_dynamic_variables"]);
        }
        if (isset($data["metadata"])) {
            $body["metadata"] = $metadata;
        }

        $encode = json_encode($body);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'create-chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
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

        if (isset($resp["chat_id"])) {
            if ($resp["chat_status"] == "ongoing") {
                return $this->responder("OK", $resp["chat_status"], ["chat_id" => $resp["chat_id"], "agent_id" => $resp["agent_id"]]);
            } else {
                return $this->responder("ERROR", "Chat with status: " . $resp["chat_status"], ["conversation_id" => $resp["call_id"], "callSid" => $resp["call_id"]]);
            }
        } else {
            if (isset($resp["message"])) {
                return $this->responder("ERROR", $resp["message"], []);
            } else {
                return $this->responder("ERROR", "Error desconocido", []);
            }
        }
    }

    /**
     * Envia un texto al agente para obtener una respuesta
     * 
     * @param string $idChat id del chat con el cual interactuar
     * @param string $texto el texto que se le envia a retell
     * 
     * @return array devuelve un array con las respuesta obtenidas
     */
    function api_escribirAlChat($idChat, $texto)
    {
        if ($idChat == "") {
            return $this->responder("ERROR", "No se ha indicado el id de chat al que escribir", []);
        }
        if (trim($texto) == "") {
            return $this->responder("ERROR", "No se ha indicado el texto que escribir", []);
        }

        $texto = str_replace(['"', "'", "\n", "\r"], ["", "", "", ""], $texto);
        $texto = utf8_2_encode($texto);
        $elContenido = "{\n  \"chat_id\": \"$idChat\",\n  \"content\": \"$texto\"\n}";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'create-chat-completion',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $elContenido,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY,
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        //trigger_error(serialize($response));
        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);
        //trigger_error(serialize($resp));
        if (isset($resp["messages"])) {
            if (is_array($resp["messages"])) {
                return $this->responder("OK", "Mensajes", $resp["messages"]);
            } else {
                return $this->responder("ERROR", "Mensajes vacios", []);
            }
        } else {
            if (isset($resp["message"])) {
                return $this->responder("ERROR", $resp["message"], []);
            } else if (isset($resp["error_message"])) {
                return $this->responder("ERROR", $resp["error_message"], []);
            } else {
                return $this->responder("ERROR", "Error desconocido", $resp);
            }
        }
    }

    /**
     * Obtiene el detalle de un chat
     * 
     * @param string $idChat id del chat del obtener la informacion
     * 
     * @return array con el detalle de la conversacion
     */
    function api_obtenerDetalleChat($idChat, $fuerza = false)
    {
        if ($idChat == "") {
            return $this->responder("ERROR", "No se ha indicado el id de chat a buscar", []);
        }

        $mongo = new MYMONGODB();
        if (!$fuerza) {
            $cursor = $mongo->buscar("wsDetalleChatRetell", ["idChat" => $idChat]);
        } else {
            $cursor = 0;
        }

        if ($cursor > 0) {
            $row = $mongo->siguiente();
            unset($row["_id"]);
            $row["origen"] = "caché";
            return $this->responder("OK", "Chat " . $idChat,  $row);
        } else {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->URL2 . 'get-chat/' . $idChat,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->RETELL_API_KEY
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
            //print_h($resp);
            if (isset($resp["chat_id"]) && isset($resp["chat_status"])) {
                $resp["origen"] = "servidor";
                $resp["fecha_consulta"] = time();
                if ($resp["chat_status"] == "ended" || $resp["chat_status"] == "error") {
                    $mongo->guardar("wsDetalleChatRetell", $resp);
                    $detalle["origen"] = "servidor";
                    return $this->responder("OK", "Conversacion " . $idChat,  $resp);
                } else {
                    return $this->responder("OK", "Conversacion " . $idChat,  $resp);
                }
            } else {
                return $this->responder("ERROR", "No se pudo recuperar el detalle de la conversacion", []);
            }
        }
    }

    /**
     * Finaliza un chat
     * 
     * @param string $idChat id del chat que se debe finalizar
     * 
     * @return array 
     */
    function api_finalizarChat($idChat)
    {
        if ($idChat == "") {
            return $this->responder("ERROR", "No se ha indicado el id de chat a buscar", []);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'end-chat/' . $idChat,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        //TODO: como validar que si se elimino, la respuesta es vacia
        return $this->responder("OK", "Chat finalizado",  []);
    }

    /**
     * Obtiene un listado de los chats
     * 
     * @param array $estado un array de strings que contiene los estados a buscar, posibles valores: registered, ongoing, ended, error 
     * 
     * @return array {estado: "OK", mensaje: "Lista de llamadas", datos: lista de llamadas, fecha: string}
     */
    function api_obtenerChats($estado = [])
    {

        if ($estado == "" || !is_array($estado)) {
            return $this->responder("ERROR", "No se ha indicado el/los estados a buscar", []);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL2 . 'list-chat?limit=1000',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            //CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->RETELL_API_KEY
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

        if (is_array($resp)) {
            $chats = [];
            foreach ($resp as $value) {
                if (in_array($value["chat_status"], $estado) || count($estado) == 0) {
                    $chats[] = $value;
                }
            }
            return $this->responder("OK", "Lista de chats", $chats);
        } else {
            if (isset($resp["message"])) {
                return $this->responder("ERROR", $resp["message"], []);
            } else {
                return $this->responder("ERROR", "No se pudo recuperar la lista de chats", []);
            }
        }
    }

    #endregion

    #endregion
}
?>
<? //_FIN_DE_ARCHIVO 
?>