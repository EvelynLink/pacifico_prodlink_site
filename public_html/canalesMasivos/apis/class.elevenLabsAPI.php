<?php

/** API para la interaccion con los apis de elevenlabs
 */
require_once("../comunes/classes/class.API.php");

class elevenlabsAPI extends API
{
    // private $ELEVENLABS_API_KEY = "sk_55ea4e585da69e4f14a9e6e059760d843a5fdd0cd27739e9";
    // private $URLV1CONVAI = "https://api.elevenlabs.io/v1/convai/";
    // private $URLV2 = "https://api.elevenlabs.io/v2/";
    private $ELEVENLABS_API_KEY = "";
    private $URLV1CONVAI = "";
    private $URLV2 = "";
    private $DIRECTORIO = "web/cacheFolder/"; //TODO: directorio definitivo?

    function __construct()
    {
        $cnf = getConf("Canales Masivos");
        $api = isset($cnf["Elevenlabs api key"][0]) ? $cnf["Elevenlabs api key"][0] : "";
        $urlv1 = isset($cnf["Elevenlabs url v1 convai"][0]) ? $cnf["Elevenlabs url v1 convai"][0] : "";
        $urlv2 = isset($cnf["Elevenlabs url v2"][0]) ? $cnf["Elevenlabs url v2"][0] : "";
        if ($api == "" || $urlv1 == "" || $urlv2 == "") {
            throw new Exception("No se encuentran las variables de configuración necesarias para el api de elevenlabs", 1);
        }
        $this->ELEVENLABS_API_KEY = $api;
        $this->URLV1CONVAI = $urlv1;
        $this->URLV2 = $urlv2;
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
    private function validarLimpiarDatosCobranza($data)
    {
        $requeridos = [
            "dia_corte",
            "direccion",
            "telefono",
            "nombre",
            "nombre_deudor",
            "nombre_cartera",
            "nro_cuotas_vencidas",
            "dias_mora",
            "monto_total_vencido",
            "nro_operacion",
            "ruc_empresa",
            "nombre_empresa"
        ];

        $faltantes = $this->validarRequeridos($requeridos, $data);

        if (count($faltantes) > 0) {
            return $this->responder("ERROR", "Campos requeridos faltantes", $faltantes);
        } else {
            if (substr($data["telefono"], 0, 5) != "+5939") {
                return $this->responder("ERROR", "Teléfono incorrrecto", ["El número de teléfono al cuál llamar debe tener el código del país +593"]);
            }
            if (strlen($data["telefono"]) != 13) {
                return $this->responder("ERROR", "Teléfono incorrrecto", ["El número de teléfono al cuál llamar debe ser un número válido p.e: +593986259845"]);
            }

            $dinamicos = [
                "nombre" => $this->miDestilda($data["nombre"]),
                "nombre_deudor" => $this->miDestilda($data["nombre_deudor"]),
                "nombre_cartera" => $this->miDestilda($data["nombre_cartera"]),
                "nro_cuotas_vencidas" => $data["nro_cuotas_vencidas"],
                "dias_mora" => $data["dias_mora"],
                "monto_total_vencido" => $data["monto_total_vencido"],
                "nro_operacion" => $data["nro_operacion"],
                "ruc_empresa" => $data["ruc_empresa"],
                "nombre_empresa" => $this->miDestilda($data["nombre_empresa"]),
                "dia_corte" => $data["dia_corte"],
                "direccion" => $data["direccion"]
            ];
            //se devuielve los datos originales y los dinamicos que 
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
     * Devuelve las estadisticas de uso de los diferentes productos de elevenlabs
     * 
     * @param int @desde timestamp desde cuando se quiere obtener los datos
     * @param int @hasta timestamp hasta cuando se quiere obtener los datos
     * @param string @metrica indica en que metrica se muestra el uso, puede ser: credits, minutes_used, request_count
     * @param string @porTipo indica como se totaliza, puede ser: product_type, none
     * @param string @mostrarPor indica si se totaliza por: hour, day, week, month, cumulative
     * 
     * @return array indicando el detalle del uso
     */
    function api_devolverUso($desde, $hasta, $metrica = "credits", $porTipo = "product_type", $mostrarPor = "day")
    {
        $desde = intval($desde);
        $hasta = intval($hasta);
        if ($desde <= 0 || $hasta <= 0) {
            return $this->responder("ERROR", "Envie desde y hasta como timestamp", []);
        }
        if ($desde < 1000000000000) {
            $desde = $desde * 1000;
        }
        if ($hasta < 1000000000000) {
            $hasta = $hasta * 1000;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.elevenlabs.io/v1/usage/character-stats?start_unix=' . $desde . '&end_unix=' . $hasta . '&metric=' . $metrica . '&aggregation_interval=' . $mostrarPor . '&breakdown_type=' . $porTipo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY
            ),
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);
        if (isset($resp["time"])) {
            foreach ($resp["time"] as $key => $value) {
                $resp["time"][$key] = date("d/m/Y H:i", $value / 1000);
            }
        }
        return $this->responder("OK", "Detalle de uso", $resp);
    }

    /**
     * Devuelve el detalle del usuario y plan contratado
     * 
     * @return array con el detalle del usuario y subscripcion
     */
    function api_devolverUsuarioPlan()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.elevenlabs.io/v1/user',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY
            ),
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            if (curl_errno($curl)) {
                curl_close($curl);
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        return $this->responder("OK", "Detalle de uso", $resp);
    }

    /**
     * Da un estimado del costo por minuto de un agente
     * 
     * @param string @idAgente id del agente del que se quiere calcular
     * @param int $prompt_length numero de caracteres del prompt del agente, 0 y este valor no se envia
     * @param int $number_of_pages numero de paginas o urls que se tiene la base de conocimiento para el agente
     * @param bool $rag_enabled si el agente tendra o no esta caracteristica activada
     * 
     * @return array que indica el costo por minuto por modelo
     */
    function api_calcularCostoAgente($idAgente, $prompt_length = 0, $number_of_pages = 0, $rag_enabled = false)
    {
        //prompt_length = Length of the prompt in characters.
        //number_of_pages = Pages of content in pdf documents OR urls in agent?s Knowledge Base.
        //rag_enabled = Retrieval-Augmented Generation (RAG) increases the agent's maximum Knowledge Base size. The agent will have access to relevant pieces of attached Knowledge Base during answer generation.

        $opciones = [];

        if ($prompt_length > 0) {
            $opciones["prompt_length"] = $prompt_length;
        }
        if ($number_of_pages > 0) {
            $opciones["number_of_pages"] = $number_of_pages;
        }
        if ($rag_enabled) {
            $opciones["rag_enabled"] = true;
        }

        $encode = count($opciones) > 0 ? json_encode($opciones) : "{}";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV1CONVAI . 'agent/' . $idAgente . '/llm-usage/calculate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if ($resp["llm_prices"]) {
            return $this->responder("OK", "LLM Prices", $resp);
        } else {
            return $this->responder("ERROR", "Error al obtener los precios", []);
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
    function api_generarLlamada($data, $tipo = "")
    {

        //estos campos deben venir siempre en cualquier agente
        $requeridos = [
            "genero_agente",
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
                $datos_dinamicos = $this->validarLimpiarDatosCobranza($data);
                break;
        }

        //hay error
        if (isset($datos_dinamicos["estado"]) && $datos_dinamicos["estado"] == "ERROR") {
            return $datos_dinamicos;
        }

        //armo el request
        $voiceId = $data["genero_agente"];
        $agentId = $data["tipo_agente"];
        $telefonoLlamar = $data["telefono"];
        $telefonoDesdeLlamar = $data["numero_telefono_agente"];
        $saludo = isset($data["saludo"]) ? $this->miDestilda($data["saludo"]) : "";
        $prompt = isset($data["conversacion"]) ? $this->miDestilda($data["conversacion"]) : "";

        $body = [
            "agent_id" => $agentId,
            "agent_phone_number_id" => $telefonoDesdeLlamar,
            "to_number" => $telefonoLlamar,
            "conversation_initiation_client_data" => [
                "conversation_config_override" => [
                    "agent" => [
                        "prompt" => [
                            "prompt" => $prompt
                        ],
                        "first_message" => $saludo
                    ],
                    "tts" => [
                        "voice_id" => $voiceId
                    ]
                ],
                "dynamic_variables" => $datos_dinamicos
            ]
        ];

        if (count($datos_dinamicos) == 0) {
            unset($body["conversation_initiation_client_data"]["dynamic_variables"]);
        }
        if ($saludo == "") {
            unset($body["conversation_initiation_client_data"]["conversation_config_override"]["agent"]["first_message"]);
        }
        if ($prompt == "") {
            unset($body["conversation_initiation_client_data"]["conversation_config_override"]["agent"]["prompt"]);
        }
        if ($saludo == "" && $prompt == "") {
            unset($body["conversation_initiation_client_data"]["conversation_config_override"]["agent"]);
        }

        $encode = json_encode($body);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV1CONVAI . 'twilio/outbound-call',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        //TODO: manejo llamadas concurrentes

        if ($resp["success"]) {
            return $this->responder("OK", "Llamada iniciada", ["conversation_id" => $resp["conversation_id"], "callSid" => $resp["callSid"]]);
        } else {
            $error = "Error no definido";
            if (isset($resp["detail"])) {
                if (is_array($resp["detail"])) {
                    $error = "";
                    foreach ($resp["detail"] as $value) {
                        $error .= $value["msg"];
                    }
                } else {
                    $error = serialize($resp["detail"]);
                }
            }
            if (isset($resp["message"])) {
                $error = $resp["message"];
            }

            return $this->responder("ERROR", $error, []);
        }
    }

    /**
     * Devuelve un listado de llamadas realizadas en un rango de tiempo y por agente en cualquier estado
     * 
     * @param int $desde fecha desde la cual buscar las llamadas
     * @param int $hasta fecha hasta la cual buscar las llamadas
     * @param string (opcional) $agente id del agente del cual buscar las llamadas
     * 
     * @return array {estado: string, mensaje: string, datos: mixed, fecha: string}
     */
    function api_obtenerLlamadas($desde = 0, $hasta = 0, $agente = "")
    {
        $desde = intval($desde);
        $hasta = intval($hasta);

        if ($desde <= 0 || $hasta <= 0) {
            return $this->responder("ERROR", "Envie desde y hasta como timestamp", []);
        }

        $url = $this->URLV1CONVAI . 'conversations?page_size=100';
        if ($desde > 0 && $hasta > 0) {
            $url .= '&call_start_after_unix=' . $desde . '&call_start_before_unix=' . $hasta;
        }
        if ($agente != "") {
            $url .= '&agent_id=' . $agente;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if (isset($resp["conversations"])) {
            $llamadas = [];
            foreach ($resp["conversations"] as $value) {
                $llamadas[] = $value;
            }
            return $this->responder("OK", "Lista de llamadas", $llamadas);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de llamadas", []);
        }
    }

    #endregion

    #region: "Funciones obtener ids"

    /**
     * Devuelve un listado con las voces disponibles en elevenlabs
     * 
     * @return array {estado: "OK", mensaje: "Lista de voces", datos: array con el listado de voces, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de voces", datos: [], fecha: string}
     */
    function api_obtenerVoces()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV2 . 'voices?page_size=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if (isset($resp["voices"])) {
            $agentes = [];
            foreach ($resp["voices"] as $value) {
                $agentes[] = [
                    "voz_id" => $value["voice_id"],
                    "voz_nombre" => $value["name"],
                    "categoria" => $value["category"],
                    "descripcion" => $value["description"]
                ];
            }
            return $this->responder("OK", "Lista de voces", $agentes);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de voces", []);
        }
    }

    /**
     * Devuelve un listado con los telefonos configurados en elevenlabs
     * 
     * @return array {estado: "OK", mensaje: "Lista de telefonos", datos: array con el listado de telefonos, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de telefonos", datos: [], fecha: string}
     */
    function api_obtenerTelefonos()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV1CONVAI . 'phone-numbers/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if (is_array($resp)) {
            $telefonos = [];
            foreach ($resp as $value) {
                $telefonos[] = [
                    "telefono_id" => $value["phone_number_id"],
                    "telefono_numero" => $value["phone_number"],
                    "telefono_nombre" => $value["label"]
                ];
            }
            return $this->responder("OK", "Lista de telefonos", $telefonos);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de telefonos configurados", []);
        }
    }

    /**
     * Devuelve un listado con los modelos de voz de elevenlabs (speech synthesis models)
     * 
     * @return array {estado: "OK", mensaje: "Lista de modelos", datos: array con el listado de modelos, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de modelos", datos: [], fecha: string}
     */
    function api_obtenerModelosVoz()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.elevenlabs.io/v1/models',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if (is_array($resp)) {
            $modelos = [];
            foreach ($resp as $value) {
                $modelos[] = [
                    "modelo_id" => $value["model_id"],
                    "model_name" => $value["name"],
                    "description" => $value["description"],
                    "max_characters_request_free_user" => $value["max_characters_request_free_user"],
                    "max_characters_request_subscribed_user" => $value["max_characters_request_subscribed_user"],
                    "maximum_text_length_per_request" => $value["maximum_text_length_per_request"],
                    "languages" => $value["languages"]
                ];
            }
            return $this->responder("OK", "Lista de modelos", $modelos);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de modelos", []);
        }
    }

    #endregion

    #region: "Funciones conversacion"

    /**
     * Devuelve el detalle de una conversacion, la primera vez que se lee se guarda en una coleccion mongo y
     * las proximas consultas se devuelve lo que esta en mongo
     * 
     * @param string $idConversacion el id de la conversacion que se quiere buscar
     * 
     * @return array {estado: "OK", mensaje: "Conversacion 132123", datos: array con el detalle de la conversacion, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar el detalle de la conversacion", datos: [], fecha: string}
     */
    function api_obtenerDetalleConversacion($idConversacion)
    {
        if ($idConversacion == "") {
            return $this->responder("ERROR", "No se ha indicado el id de conversacion a buscar", []);
        }

        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avDetalleConversaciones", ["idConversacion" => $idConversacion]);
        if ($cursor > 0) {
            $row = $mongo->siguiente();
            unset($row["_id"]);
            $row["origen"] = "caché";
            return $this->responder("OK", "Conversacion " . $idConversacion,  $row);
        } else {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->URLV1CONVAI . 'conversations/' . $idConversacion,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

            if (isset($resp["agent_id"]) && isset($resp["status"])) {
                $resp["origen"] = "servidor";
                $resp["fecha_consulta"] = time();
                if ($resp["status"] == "done") { //TODO: tambien en error
                    $nombreArchivo = $idConversacion . ".json";
                    $path = BASEFOLDER . $this->DIRECTORIO . $nombreArchivo;
                    if (file_exists($path)) {
                        unlink($path);
                        sleep(2);
                    }
                    $escribe = file_put_contents($path, json_encode($resp));
                    $transcripcion = [];
                    foreach ($resp["transcript"] as $value) {
                        $transcripcion[] = [
                            "direccion" => $value["role"] == "agent" ? "agente" : "cliente",
                            "mensaje" => $value["message"],
                            "tiempoTranscurridoSegundos" => floatval($value["time_in_call_secs"]),
                            "interlocutorInterrumpe" => $value["interrupted"],
                            "mensajeOriginal" => $value["original_message"]
                        ];
                    }

                    $detalle = [
                        "status" => $resp["status"],
                        "idAgente" => $resp["agent_id"],
                        "idConversacion" => $resp["conversation_id"],
                        "archivoDetalle" => $escribe != false && $escribe > 0 ? $path : "",
                        "archivoAudio" => "", //se llena luego
                        "fechaInicio" => intval($resp["metadata"]["start_time_unix_secs"]),
                        "fechaContesta" => intval($resp["metadata"]["accepted_time_unix_secs"]),
                        "resumen" => $resp["analysis"]["transcript_summary"],
                        "analisisExito" => "",
                        "transcripcion" => $transcripcion,
                        "duracionSegundos" => intval($resp["metadata"]["call_duration_secs"]),
                        "costoCreditos" => intval($resp["metadata"]["cost"]),
                        "costoLLM" => floatval($resp["metadata"]["charging"]["llm_price"]),
                        "telefono" => $resp["metadata"]["phone_call"]["external_number"],
                        "finalizacion" => $resp["metadata"]["termination_reason"],
                        "sentimiento" => "",
                        "error" => $resp["metadata"]["error"],
                        "callLog" => ""
                    ];
                    $mongo->guardar("avDetalleConversaciones", $detalle);
                    $detalle["origen"] = "servidor";
                    return $this->responder("OK", "Conversacion " . $idConversacion,  $detalle);
                }
                return $this->responder("OK", "Conversacion " . $idConversacion,  $resp);
            } else {
                return $this->responder("ERROR", "No se pudo recuperar el detalle de la conversacion", []);
            }
        }
    }

    /**
     * Obtiene el audio de una conversacion y guarda el audio en el servidor
     * 
     * @param string $idConversacion el id de la conversacion que se quiere buscar
     * 
     * @return array {estado: "OK", mensaje: "Audio conversacion 132123", datos: array que incluye una url de donde se guardo el audio, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar el audio del servidor", datos: [], fecha: string}
     */
    function api_obtenerAudioConversacion($idConversacion)
    {
        if ($idConversacion == "") {
            return $this->responder("ERROR", "No se ha indicado el id de conversacion a buscar", []);
        }
        $mongo = new MYMONGODB();
        $mongo2 = new MYMONGODB();
        $cursor = $mongo->buscar("avDetalleConversaciones", ["idConversacion" => $idConversacion]);
        if ($cursor > 0) {
            $row = $mongo->siguiente();
            unset($row["_id"]);
            unset($row["fechaInicio"]);
            unset($row["fechaContesta"]);
            unset($row["resumen"]);
            unset($row["transcripcion"]);
            unset($row["duracionSegundos"]);
            unset($row["costoCreditos"]);
            unset($row["telefono"]);
            unset($row["finalizacion"]);
            unset($row["error"]);
            unset($row["costoLLM"]);

            if ($row["archivoAudio"] == "") {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $this->URLV1CONVAI . 'conversations/' . $idConversacion . "/audio",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        'xi-api-key: ' . $this->ELEVENLABS_API_KEY
                    ],
                ]);
                $response = curl_exec($curl);
                if ($response === false) {
                    if (curl_errno($curl)) {
                        curl_close($curl);
                        return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
                    }
                }

                $nombreArchivo = $idConversacion . ".mp3";
                $path = BASEFOLDER . $this->DIRECTORIO . $nombreArchivo;
                if (file_exists($path)) {
                    unlink($path);
                    sleep(2);
                }
                $escribe = file_put_contents($path, $response);
                curl_close($curl);
                if ($escribe != false && $escribe > 0) {
                    $item = [
                        "archivoAudio" => $path,
                        "fechaAudio" => time()
                    ];

                    $mongo2->actualizar("avDetalleConversaciones", ["_id" => $mongo2->String2MongoId($row["id"])], $item);
                    $row["archivoAudio"] = $path;
                    $row["origen"] = "servidor";
                    return $this->responder("OK", "Audio conversacion " . $idConversacion,  $row);
                } else {
                    return $this->responder("ERROR", "No se pudo guardar el audio en el servidor",  []);
                }
            } else {
                $row["origen"] = "caché";
                //$row["archivoAudio"] = str_replace(BASEFOLDER, BASEURL, $row["archivoAudio"]);
                return $this->responder("OK", "Audio conversacion " . $idConversacion,  $row);
            }
        } else {
            return $this->responder("ERROR", "No se ha consultado previamente el detalle de la conversacion",  []);
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
            CURLOPT_URL => $this->URLV1CONVAI . 'conversations/' . $idConversacion,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        //TODO: como validar que si se elimino, la respuesta es vacia
        return $this->responder("OK", "Conversacion eliminada",  []);
    }

    #endregion

    #region: "Funciones agentes"

    /**
     * Devuelve un listado con los agentes configurados en elevenlabs
     * 
     * @return array {estado: "OK", mensaje: "Lista de agentes", datos: array con el listado de agentes, fecha: string}
     * 
     * {estado: "ERROR", mensaje: "No se pudo recuperar la lista de agentes configurados", datos: [], fecha: string}
     */
    function api_obtenerAgentes()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV1CONVAI . 'agents?page_size=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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
        if (isset($resp["agents"])) {
            $agentes = [];
            foreach ($resp["agents"] as $value) {
                $agentes[] = [
                    "agente_id" => $value["agent_id"],
                    "agente_nombre" => $value["name"]
                ];
            }
            return $this->responder("OK", "Lista de agentes", $agentes);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de agentes configurados", []);
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
            CURLOPT_URL => $this->URLV1CONVAI . 'agents/' . $idAgente,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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

        if (isset($resp["agent_id"])) {
            unset($resp["conversation_config"]["asr"]);
            unset($resp["conversation_config"]["turn"]);
            unset($resp["conversation_config"]["tts"]);
            unset($resp["conversation_config"]["conversation"]);
            unset($resp["conversation_config"]["language_presets"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["tools"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["tool_ids"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["mcp_server_ids"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["knowledge_base"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["custom_llm"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["ignore_default_personality"]);
            unset($resp["conversation_config"]["agent"]["prompt"]["rag"]);
            unset($resp["platform_settings"]["evaluation"]);
            unset($resp["platform_settings"]["widget"]);
            unset($resp["platform_settings"]["data_collection"]);
            unset($resp["platform_settings"]["ban"]);
            unset($resp["platform_settings"]["workspace_overrides"]);
            unset($resp["platform_settings"]["safety"]);
            unset($resp["metadata"]);
            unset($resp["access_info"]);
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
            CURLOPT_URL => $this->URLV1CONVAI . 'agents/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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
            if (isset($resp["detail"]["message"])) {
                return $this->responder("ERROR", $resp["detail"]["message"], []);
            } else {
                return $this->responder("ERROR", "No se pudo crear el agente", []);
            }
        }
    }

    /**
     * Modificar un agente
     * 
     * @param int $idAgente el id del agente que se va a modificar
     * @param array $data un array con las opciones que se necesitan para modificar un agente
     * 
     * @return array {estado: "OK", mensaje: "Agente actualizado", datos: el array enviado, fecha: string}
     */
    function api_modificarAgente($idAgente, $data)
    {
        //TODO: validar campos requeridos
        if ($data == null || $data == "" || !is_array($data)) {
            return $this->responder("ERROR", "No se enviaron los datos del agente a crear", []);
        }

        $curl = curl_init();
        $data = utf8_converter($data);
        $encode = json_encode($data);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URLV1CONVAI . 'agents/' . $idAgente,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $encode,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->ELEVENLABS_API_KEY,
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
            return $this->responder("OK", "Agente actualizado", $data);
        } else {
            if (isset($resp["detail"]["message"])) {
                return $this->responder("ERROR", $resp["detail"]["message"], []);
            } else {
                return $this->responder("ERROR", "No se pudo actualizar el agente", []);
            }
        }
    }

    #endregion

    #endregion
}
?>
<? //_FIN_DE_ARCHIVO 
?>