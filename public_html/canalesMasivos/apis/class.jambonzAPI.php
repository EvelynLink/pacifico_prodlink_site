<?php

/** API para la interaccion con los apis de jambonz
 */
require_once("../comunes/classes/class.API.php");

class jambonzAPI extends API
{
    private $API_KEY = "";
    private $URL = "";
    private $ACCOUNT_ID = "";

    function __construct()
    {
        $cnf = getConf("Canales Masivos");
        $api = isset($cnf["Jambonz api key"][0]) ? $cnf["Jambonz api key"][0] : "";
        $url = isset($cnf["Jambonz api url"][0]) ? $cnf["Jambonz api url"][0] : "";
        $account = isset($cnf["Jambonz account id"][0]) ? $cnf["Jambonz account id"][0] : "";
        if ($api == "" || $url == "" || $account == "") {
            throw new Exception("No se encuentran las variables de configuración necesarias para el api de Jambonz", 1);
        }
        $this->API_KEY = $api;
        $this->URL = $url;
        $this->ACCOUNT_ID = $account;
    }

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

    function api_leerAlertas($data)
    {

        $pagina = $data["pagina"];
        $cuantos = $data["cuantos"];
        //start=2025-06-20T05:00:00.000Z
        $desde = isset($data["desde"]) ? $data["desde"] : "";
        $hasta = isset($data["hasta"]) ? $data["hasta"] : "";
        $condicionTiempo = "";
        if ($desde != "" && $hasta != "") {
            $condicionTiempo = "&start=" . $desde . "&end=" . $hasta;
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . $this->ACCOUNT_ID . '/Alerts?page=' . $pagina . "&count=" . $cuantos . $condicionTiempo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->API_KEY
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

        if (isset($resp["data"]) && is_array($resp["data"])) {
            $alertas = [];
            foreach ($resp["data"] as $value) {
                $alertas[] = $value;
            }
            return $this->responder("OK", "Listado de alertas", [$alertas, $resp["total"]]);
        } else {
            return $this->responder("ERROR", "Error al obtener las alertas", []);
        }
    }

    function api_devolverLlamadas($estados)
    {

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->URL . $this->ACCOUNT_ID . '/Calls',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->API_KEY
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
                if (in_array($value["sip_reason"], $estados)) {
                    $llamadas[] = $value;
                }
            }
            return $this->responder("OK", "Listado de llamadas", $llamadas);
        } else {
            return $this->responder("ERROR", "Error al obtener las llamadas", []);
        }
    }
}
?>
<? //_FIN_DE_ARCHIVO 
?>