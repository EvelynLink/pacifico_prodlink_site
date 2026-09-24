<?php

/** API para la interaccion con servicio de WhatsApp
 */
require_once("../comunes/classes/class.API.php");

class whatsappAPI extends API
{
    private $URL = "";
    private $ORIGEN = "";

    function __construct()
    {
        $cnf = getConf("Canales Masivos");
        $url = isset($cnf["WhatsApp URL"][0]) ? $cnf["WhatsApp URL"][0] : "";
        $org = isset($cnf["WhatsApp Origen"][0]) ? $cnf["WhatsApp Origen"][0] : "";
        if ($url == "" || $org == '') {
            throw new Exception("No se encuentran las variables de configuración necesarias para el servicio de whatsapp", 1);
        }
        $this->URL = $url;
        $this->ORIGEN = $org;
    }

    /**
     * 
     * @param string $numero numero de telefono a registrar la cual se usa para enviar y recibir mensajes, formato codigo internacional ej. +593
     * @param mixed $datos cualquier tipo de datos que requiere que devuelva la respuesta
     * 
     * @return array imagen QR en base64
     */
    function getQR($numero)
    {
        $t = file_get_contents($this->URL . "/start/$numero?origen=$this->ORIGEN");
        sleep(5);
        return $this->responder("OK", "Solicitud de registro", base64_encode($this->URL . "/qr/$numero"));
    }

    /**
     * 
     * @return array lista de números activos
     */
    function getActivos()
    {
        $mongo = new MYMONGODB();
        $c = $mongo->buscar('cbConfig', ['cbConf_tipo' => 'whatsappConf', 'cbConf_nombre' => 'whatsappConf', 'cbConf_status' => 'Activo', "cbConf_estado" => (int) 1]);
        $data = [];
        while ($row = $mongo->siguiente()) {
            $data = $row;
        }
        return $data;
    }

    /**
     * 
     * @param string $numero numero de telefono destino, formato codigo internacional ej. +593
     * @param string $mensaje mensaje
     * @param string $numFrom numero de telefono desde el cual se envian los mensajes
     * 
     * @return array responde si esta OK o ERROR el envio del mensaje
     */
    function enviar($numero, $mensaje, $numFrom)
    {
        if (empty($numero) || empty($mensaje)) {
            return $this->responder("ERROR", "Faltan parámetros", '');
        }
        $fecha = time();
        $data = [
            'numero' => $numero,
            'mensaje' => $mensaje,
            'id' => $numFrom
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json",
                'method' => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($this->URL . '/send', false, $context);

        if ($result === FALSE) {
            return $this->responder("ERROR", "Error al enviar el mensaje.", "");
        } else {
            $mongo = new MYMONGODB();
            $mongo->guardar('cbServicioWhatsApp', ['fecha' => (int) $fecha, 'fechaStr' => (string) date('d/m/Y H:i:s', $fecha), 'desde' => (string) $numFrom, 'destino' => (string) $numero, 'origen' => $this->ORIGEN, 'estado' => 'salida', 'data' => $data]);

            return $this->responder("OK", "Mensaje Enviado", '');
        }

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
        return [
            "estado" => $estado,
            "mensaje" => $mensaje,
            "datos" => $datos,
            "fecha" => date("d/m/Y H:i:s")
        ];
    }
}
?>
<? //_FIN_DE_ARCHIVO 
?>