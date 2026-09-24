<?php
//__Descripción:__ Plugin que permite procesar boletines electrónicos
require_once("../comunes/classes/class.clase.php");

class pgBoletinesSmsCnt extends Clase {

    //__Descripción:__ Función que permite definir los campos de reeemplazo del boletína a enviar
    //__Inputs:__ 
    //__Outputs:__ 
    public function getCamposReemplazo(){
        $camposCorreo = array(
            "@@numero" => "boCorreo_email",
            "@@nombre" => "boCorreo_nombre",
            "@@fechaCompromiso" => "boCorreo_identificacion"
        );
        return $camposCorreo;
    }
    
    //__Descripción:__ Función que permite definir el orígen por el cuál saldrán los correos enviados desde el módulo de boletines
    //                 Si el from está vacío, el módulo lo enviará por las variables de configuración definidas en Email Alerts
    //__Inputs:__ 
    //__Outputs:__ 
    public function getFromEnvio(){
        return "";
    }
    
    //__Descripción:__ Función que permite obtener el plugin de envío a usar en el envío boletines
    //                 Si la configuración no existe, el módulo enviará por el plugin default de Email Alerts si éste está definido en variables de configuración
    //                 Sino saldrá por la configuración básica local de Email Alerts
    //__Inputs:__ 
    //__Outputs:__ 
    public function getPluginEnvio(){
        return ""; //Aquí puede personalizar el plugín de envío de boletines
    }
    
    //__Descripción:__ Función que permite ejecutar un envío básico manual desde boletines
    //__Inputs:__ $objConten:object objecto de tabla bocontenido
    //            $correos:array lista de correos
    //__Outputs:__ 
    public function envioManual($objConten,$clientes){
        require_once("../boletines/classes/class.boBitacora.php");
        require_once("../boletines/classes/class.boCorreo.php");
        $objBitacora = new boBitacora();
        $objCorreo = new boCorreo();
        $db = new MYSQLDB();
        $url = $objConten["contenido"];
        $usuarioSms = $objConten["idCliente"];
        $passSms = $objConten["password"];
        $mensajeSms = $objConten["mensaje"];
        //$contenido = $objCorreo->agregarSeccionDesuscripcion($contenido);
        $titulo = $objConten->get("nombre");
        $camposCorreo = $this->getCamposReemplazo();
        //Envia uno por uno los correos para manejo de reemplazo de campos
        foreach ($clientes as $cor) {
            $numero = trim($cor);
            //busca datos alamacenados de correo
           
            str_replace('@@nombre', $cor['nombre'], $mensajeSms);
            str_replace('@@fechaCompromiso', $cor['fechaCompromiso'], $mensajeSms);
            $this->enviaSMS($url, $usuarioSms, $passSms, $cor['numero'], $mensajeSms);
        }
        return true;
    }
    //__Descripción:__ Función que permite ejecutar envío de sms de manera manual
    //__Inputs:__ 
    //__Outputs:__ 
    public function envioManualSms($objConten,$numeros){
        return "No activa";
    }
    
    //__Descripción:__ Función que permite ejecutar envío de sms de manera masiva
    //__Inputs:__ 
    //__Outputs:__ 
    public function envioSmsBitacora($objConten,$lista,$fechaProgramada,$horaProgramada,$minutoProgramado){
        return "No activa";
    }
    
    //__Descripción:__ Función que permite definir los datos a enviar en el boletín/facebook/envío
    //__Inputs:__ $linea:string línea de archivo
    //__Outputs:__ $result:array lista de datos en formato array
    public function preparaDatosEnvio($linea){
        $datosLinea = explode('|',$linea);
        $datos = array("id"=>$datosLinea[0],
                        "correo"=>$datosLinea[1],
                        "identificacion"=>$datosLinea[2],
                        "nombre"=>$datosLinea[3]);
        return $datos;
    }
    
    //__Descripción:__ Función que permite ejecutar el envío de un boeltín/facebook/sms
    //__Inputs:__ $tipo:string tipo de envío
    //            $objBitacora:object objeto bitácora
    //            $objContenido:object objeto contenido
    //            $datosEnviar:array lista de datos a enviar
    //__Outputs:__ 
    public function ejecutaEnvio($tipo,$objBitacora,$objContenido,$datosEnviar){
        if($tipo=="correo"){
            $contenido = $objBitacora->get("mensaje");
            $titulo = $objContenido->get("nombre");
            if(empty($titulo)){
                return "No se ha podido obtener el título del boletín (".$objContenido->get("id").")"; 
            }
            if(empty($contenido)){
                return "No se ha podido obtener información del boletín (".$objContenido->get("id").")";
            }
            //Envía listado de correos uno por uno reemplazando valores asignados en contenido si existen en archivo creado para envio
            //define campos a reemplazar si existen campos prefijados en el contenido a enviar
            $mfrom = $this->getFromEnvio();
            $pluginEnvio = $this->getPluginEnvio();
            $camposCorreo = $this->getCamposReemplazo();
            foreach ($datosEnviar as $cor){
                $contenidoxCorreo = $objBitacora->preparaContenidoBoletinPorEmail($cor, $objBitacora->get("id"), $contenido, $camposCorreo);
                $objBitacora->enviaBoletinPorEmail($cor["correo"],$titulo,$mfrom,$contenidoxCorreo,$objBitacora->get("id"),$pluginEnvio);
            }
        }else{
            return "No activa";
        }
    }
    public function enviaSMS($url,$clienteId,$pass,$numero, $msj) {
        $soapUrl = $url; // asmx URL of WSDL
        $xml_post_string = '<?xml version="1.0" encoding="utf-8"?>
                            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">
                                <soapenv:Header/>
                                <soapenv:Body>
                                   <tem:EnviarMensaje>
                                      <tem:idCliente>'.$clienteId.'</tem:idCliente>
                                      <tem:contrasenia>'.$pass.'</tem:contrasenia>
                                      <tem:operadora>C</tem:operadora>
                                      <tem:numeroTelefonico>' . $numero . '</tem:numeroTelefonico>
                                      <tem:mensaje>' . $msj . '</tem:mensaje>
                                   </tem:EnviarMensaje>
//                                </soapenv:Body>
                             </soapenv:Envelope>';   // data from the form, e.g. some ID number

        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: http://tempuri.org/IService/EnviarMensaje",
            "Content-length: " . strlen($xml_post_string),
        );

        $url = $soapUrl;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string); // the SOAP request
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $exc) {
            trigger_error('...sms 1824 ' . $numero . '......' . $msj . '......' . json_encode($exc));
            die;
            return 6; //array("existio un problema en la comunicación con el proveedor" => 6);
        }
        $response3 = explode("<EnviarMensajeResult>", $response);
        $response4 = substr($response3[1], 0, 1);
        return $response4;
    }
}
?>
<? //_FIN_DE_ARCHIVO  ?>