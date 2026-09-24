<?php

/** API para la interaccion con los apis de elevenlabs
 */
require_once("../comunes/classes/class.API.php");

class linkLocalLlmAPI extends API
{
    private $PUBLIC_KEY = "";
    private $PRIVATE_KEY = "";
    private $URL = "";
    private $VERSION = "";

    function __construct($servidor = "local")
    {
        $cnf = getConf("Canales Masivos");
        if ($servidor == "local") {
            $private = isset($cnf["Link private key"][0]) ? $cnf["Link private key"][0] : "";
            $public = isset($cnf["Link public key"][0]) ? $cnf["Link public key"][0] : "";
            $url = isset($cnf["Link url"][0]) ? $cnf["Link url"][0] : "";
            $version = isset($cnf["Link url version"][0]) ? $cnf["Link url version"][0] : "";
        }
        if ($servidor == "gpu") {
            $private = isset($cnf["Link gpu private key"][0]) ? $cnf["Link gpu private key"][0] : "";
            $public = isset($cnf["Link gpu public key"][0]) ? $cnf["Link gpu public key"][0] : "";
            $url = isset($cnf["Link gpu url"][0]) ? $cnf["Link gpu url"][0] : "";
            $version = isset($cnf["Link gpu url version"][0]) ? $cnf["Link gpu url version"][0] : "";
        }
        if ($private == "" || $url == "" || $private == null || $url == null) {
            throw new Exception("No se encuentran las variables de configuración necesarias para el api de Link Local LLM", 1);
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

    function api_crearAgente($parametros)
    {
        if (!is_array($parametros) || $parametros == "" || $parametros == null) {
            return $this->responder("ERROR", "No se recibieron los parametros requeridos", []);
        }
        if (!isset($parametros["nombre"]) || $parametros["nombre"] == "") {
            return $this->responder("ERROR", "Parametro nombre es una cadena de texto requerida", []);
        }
        if (!isset($parametros["codigo"]) || $parametros["codigo"] == "") {
            return $this->responder("ERROR", "Parametro codigo es una cadena de texto requerida", []);
        }
        if (!isset($parametros["modelo"]) || $parametros["modelo"] == "") {
            return $this->responder("ERROR", "Parametro modelo es una cadena de texto requerida", []);
        }
        if (!isset($parametros["prompt"]) || $parametros["prompt"] == "") {
            return $this->responder("ERROR", "Parametro prompt es una cadena de texto requerida", []);
        }
        if (base64_encode(base64_decode($parametros["prompt"], true)) !== $parametros["prompt"]) {
            return $this->responder("ERROR", "Parametro prompt debe ser una cadena de texto codificada en base64", []);
        }
        if (!isset($parametros["sitio"]) || $parametros["sitio"] == "") {
            return $this->responder("ERROR", "Parametro sitio es una cadena de texto requerida", []);
        }
        if (!isset($parametros["valores-reemplazo"]) || !is_array($parametros["valores-reemplazo"])) {
            return $this->responder("ERROR", "Parametro valores-reemplazo es un array requerido", []);
        }
        // if (!isset($parametros["saludos"]) || !is_array($parametros["saludos"])) {
        //     return $this->responder("ERROR", "Parametro saludos es un array requerido", []);
        // }

        $token = $this->generaToken($this->PRIVATE_KEY, "crear_modelo");
        $parametrosNuevoModelo = [
            "token" => $token,
            "modelo" => base64_encode(json_encode($parametros))
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/agents',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($parametrosNuevoModelo),
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
            return $this->responder("OK", $resp["mensaje"], []);
        } else {
            return $this->responder("ERROR", $resp["mensaje"], []);
        }
    }

    function api_eliminarAgente($idAgente)
    {
        if (trim($idAgente) == "") {
            return $this->responder("ERROR", "El id del agente a eliminar debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "eliminar_modelo");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/agents/" . $idAgente,
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
            return $this->responder("OK", "Agente eliminado", []);
        } else {
            return $this->responder("ERROR", "No se pudo eliminar el agente. " . $resp["mensaje"], []);
        }
    }

    function api_modificarAgente($idAgente, $parametros, $que)
    {
        if ($idAgente == null || $idAgente == "") {
            return $this->responder("ERROR", "No se recibio el id del agente a modificar", []);
        }
        if (!is_array($parametros) || $parametros == "" || $parametros == null) {
            return $this->responder("ERROR", "No se recibieron los parametros requeridos", []);
        }
        if ($que == "prompt") {
            if (!isset($parametros["prompt"]) || $parametros["prompt"] == "") {
                return $this->responder("ERROR", "Parametro prompt es una cadena de texto requerida", []);
            }
            if (base64_encode(base64_decode($parametros["prompt"], true)) !== $parametros["prompt"]) {
                return $this->responder("ERROR", "Parametro prompt debe ser una cadena de texto codificada en base64", []);
            }
            if (!isset($parametros["saludos"]) || $parametros["saludos"] == "") {
                return $this->responder("ERROR", "Parametro saludos es una cadena de texto requerida", []);
            }
            if (base64_encode(base64_decode($parametros["saludos"], true)) !== $parametros["saludos"]) {
                return $this->responder("ERROR", "Parametro saludos debe ser una cadena de texto codificada en base64", []);
            }
            // if (!isset($parametros["saludos"]) || !is_array($parametros["saludos"])) {
            //     return $this->responder("ERROR", "Parametro saludos es un array requerido", []);
            // }
        }

        if ($que == "valores") {
            if (!isset($parametros["valores-reemplazo"]) || !is_array($parametros["valores-reemplazo"])) {
                return $this->responder("ERROR", "Parametro valores-reemplazo es un array requerido", []);
            }
        }

        if ($que == "extraer") {
            if (!isset($parametros["valores-extraer"]) || !is_array($parametros["valores-extraer"])) {
                return $this->responder("ERROR", "Parametro valores-extraer es un array requerido", []);
            }
        }

        $token = $this->generaToken($this->PRIVATE_KEY, "modificar_modelo");
        $parametrosModificarModelo = [
            "token" => $token,
            "modelo" => base64_encode(json_encode($parametros))
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/agents/" . $idAgente,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($parametrosModificarModelo),
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
            return $this->responder("OK", "Agente modificado con exito", []);
        } else {
            return $this->responder("ERROR", "No se pudo modificar el agente " . $resp["mensaje"], []);
        }
    }

    function api_obtenerAgentes($cuantos, $saltar, $fecha_desde, $fecha_hasta)
    {
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
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_agentes");
        $parametros = [
            "token" => $token,
            "cuantos" => $cuantos,
            "saltar" => $saltar,
            "fecha_desde" => $fecha_desde,
            "fecha_hasta" => $fecha_hasta,
            "sitio" => "https://ia.devlink.site/"
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/agents',
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
            $agentes = [];
            foreach ($resp["datos"][0] as $value) {
                $agentes[] = $value;
            }
            return $this->responder("OK", "Lista de agentes", $agentes);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de agentes. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerAgentesActivos($cuantos, $saltar, $fecha_desde, $fecha_hasta)
    {
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
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_agentes_activos");
        $parametros = [
            "token" => $token,
            "cuantos" => $cuantos,
            "saltar" => $saltar,
            "fecha_desde" => $fecha_desde,
            "fecha_hasta" => $fecha_hasta,
            "sitio" => BASEURL
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/active-agents',
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
                print_h(curl_error($curl));
                return $this->responder("ERROR", "Curl error: " . curl_error($curl), []);
            }
        }
        curl_close($curl);
        $resp = json_decode($response, true);

        if ($resp["estado"] == "ok") {
            $agentes = [];
            foreach ($resp["datos"] as $value) {
                $agentes[] = $value;
            }
            return $this->responder("OK", "Lista de agentes activos", $agentes);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de agentes activos. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerChats($cuantos, $saltar, $fecha_desde, $fecha_hasta)
    {
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
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_chats");
        $parametros = [
            "token" => $token,
            "cuantos" => $cuantos,
            "saltar" => $saltar,
            "fecha_desde" => $fecha_desde,
            "fecha_hasta" => $fecha_hasta,
            "sitio" => BASEURL
        ];

        trigger_error($this->URL . "/" . $this->VERSION . '/chats');

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/chats',
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
            $chats = [];
            foreach ($resp["datos"][0] as $value) {
                $chats[] = $value;
            }
            return $this->responder("OK", "Lista de chats", $chats);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de chats. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerChatsUsuario($cuantos, $saltar, $fecha_desde, $fecha_hasta)
    {
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
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_chats_usuario");
        $parametros = [
            "token" => $token,
            "cuantos" => $cuantos,
            "saltar" => $saltar,
            "fecha_desde" => $fecha_desde,
            "fecha_hasta" => $fecha_hasta,
            "sitio" => BASEURL
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/models/chats/' . $_SESSION[MID . "userId"],
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
            $chats = [];
            foreach ($resp["datos"][0] as $value) {
                $chats[] = $value;
            }
            return $this->responder("OK", "Lista de chats", $chats);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de chats. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerChat($idChat)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_chat");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats/" . $idChat,
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
            return $this->responder("OK", "Lista de chats", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar el chat. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerRespuesta($idChat, $mensaje)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        if (trim($mensaje) == "") {
            return $this->responder("ERROR", "El mensaje debe tener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_respuesta_chat");
        $parametros = [
            "token" => $token,
            "mensaje" => base64_encode($mensaje)
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats/" . $idChat,
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
            return $this->responder("OK", $resp["mensaje"], $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo obtener la respuesta. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerRespuestaDirecto($idChat, $mensaje, $temperatura = 0.8)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        if (trim($mensaje) == "") {
            return $this->responder("ERROR", "El mensaje debe tener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_respuesta_chat_directo");
        $parametros = [
            "token" => $token,
            "mensaje" => base64_encode($mensaje),
            "temperatura" => floatval($temperatura)
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/models/chats/" . $idChat,
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
            return $this->responder("OK", $resp["mensaje"], $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo obtener la respuesta. " . $resp["mensaje"], []);
        }
    }

    function api_finalizarChat($idChat)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "modificar_chat");
        $parametros = [
            "token" => $token,
            "accion" => "finaliza"
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats/" . $idChat,
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
            return $this->responder("OK", "Chat finalizado", []);
        } else {
            return $this->responder("ERROR", "No se pudo finalizar el chat. " . $resp["mensaje"], []);
        }
    }

    function api_eliminarChat($idChat)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "eliminar_chat");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats/" . $idChat,
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
            return $this->responder("OK", "Chat eliminado", []);
        } else {
            return $this->responder("ERROR", "No se pudo eliminar el chat. " . $resp["mensaje"], []);
        }
    }

    function api_analizarChat($idChat)
    {
        if (trim($idChat) == "") {
            return $this->responder("ERROR", "El id del chat a obtener debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "modificar_chat");
        $parametros = [
            "token" => $token,
            "accion" => "analiza"
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats/" . $idChat,
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
            return $this->responder("OK", "Chat analizado", []);
        } else {
            return $this->responder("ERROR", "No se pudo analizar el chat. " . $resp["mensaje"], []);
        }
    }

    function api_iniciarChat($agente, $variablesDinamicas)
    {

        if (trim($agente) == "") {
            return $this->responder("ERROR", "El id del agente debe contener algún valor", []);
        }

        if (!is_array($variablesDinamicas)) {
            return $this->responder("ERROR", "Las variables dinámicas deben ser un array", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "crear_chat");
        $metadata = [
            "inicio" => date("d/m/Y H:i.s"),
            "sesionId" => intval($_SESSION[MID . "userId"]),
            "sesionNombre" => $_SESSION[MID . "userNombre"],
        ];

        $parametros = [
            "token" => $token,
            "agente_id" => $agente,
            "variables_dinamicas" => base64_encode(json_encode_utf8($variablesDinamicas)),
            "metadata" => base64_encode(json_encode_utf8($metadata))
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/chats",
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
            return $this->responder("OK", $resp["mensaje"], $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo crear el chat. " . $resp["mensaje"], []);
        }
    }

    //chat con un modelo llm directo en ollama
    function api_iniciarChatDirecto($agente, $usuarioId)
    {

        if (trim($agente) == "") {
            return $this->responder("ERROR", "El id del agente debe contener algún valor", []);
        }

        if (trim($usuarioId) == "") {
            return $this->responder("ERROR", "El id del usuario debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "crear_chat_modelo_directo");
        $metadata = [
            "inicio" => date("d/m/Y H:i.s"),
            "sesionId" => intval($_SESSION[MID . "userId"]),
            "sesionNombre" => $_SESSION[MID . "userNombre"],
        ];

        $parametros = [
            "token" => $token,
            "agente_id" => $agente,
            "usuario_id" => $usuarioId,
            "metadata" => base64_encode(json_encode_utf8($metadata))
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . "/models/chats",
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
            return $this->responder("OK", $resp["mensaje"], $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo crear el chat. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerModelos()
    {
        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_modelos");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/models',
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
            $agentes = [];
            foreach ($resp["datos"][0] as $value) {
                $agentes[] = $value;
            }
            return $this->responder("OK", "Lista de modelos", $agentes);
        } else {
            return $this->responder("ERROR", "No se pudo recuperar la lista de modelos. " . $resp["mensaje"], []);
        }
    }

    function api_obtenerModelo($idModelo)
    {
        if (trim($idModelo) == "") {
            return $this->responder("ERROR", "El id del modelo a obtener debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "obtener_modelo");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/models/' . $idModelo,
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
            return $this->responder("OK", "Modelo encontrado", $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo encontrar el modelo. " . $resp["mensaje"], []);
        }
    }

    function api_descargarModelo($idModelo)
    {
        if (trim($idModelo) == "") {
            return $this->responder("ERROR", "El id del modelo a descargar debe contener algún valor", []);
        }

        $curl = curl_init();
        $token = $this->generaToken($this->PRIVATE_KEY, "agregar_modelos");
        $parametros = [
            "token" => $token
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . "/" . $this->VERSION . '/models/' . $idModelo,
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
            return $this->responder("OK", $resp["mensaje"], $resp["datos"]);
        } else {
            return $this->responder("ERROR", "No se pudo encontrar el modelo. " . $resp["mensaje"], []);
        }
    }

    #endregion
}
?>
<? //_FIN_DE_ARCHIVO 
?>