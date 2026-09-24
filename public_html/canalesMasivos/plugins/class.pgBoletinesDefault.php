<?
//__Descripción:__ Plugin que permite procesar boletines electrónicos
require_once("../comunes/classes/class.clase.php");

class pgBoletinesDefault extends Clase {

    //__Descripción:__ Función que permite definir los campos de reeemplazo del boletína a enviar
    //__Inputs:__ 
    //__Outputs:__ 
    public function getCamposReemplazo(){
        $camposCorreo = array(
            "@@correo" => "correo",
            "@@nombre" => "nombre",
            "@@identificacion" => "identificacion"
        );
        return $camposCorreo;
    }
    
    //__Descripción:__ Función que permite definir el orígen por el cuál saldrán los correos enviados desde el módulo de boletines
    //                 Si el from está vacío, el módulo lo enviará por las variables de configuración definidas en Email Alerts
    //__Inputs:__ 
    //__Outputs:__ 
    public function getFromEnvio(){
        $conf = getConf("Email alerts");
        //El email de orígen y el títlo del from puede variar según la necesidad
        $origen = isset($conf["Email de origen"][0]) ? $conf["Email de origen"][0] : "";
        return "Boletines Electrónicos <".$origen.">";
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
    //__Outputs:__ $result:boolean/string
    public function envioManual($objConten,$correos){
        require_once("../boletines/classes/class.boBitacora.php");
        require_once("../boletines/classes/class.boCorreo.php");
        $objBitacora = new boBitacora();
        $objCorreo = new boCorreo();
        $db = new MYSQLDB();
        $contenido = $objConten->get("contenido");
        //$contenido = $objCorreo->agregarSeccionDesuscripcion($contenido);
        $titulo = $objConten->get("nombre");
        $camposCorreo = $this->getCamposReemplazo();
        $mfrom = $this->getFromEnvio();
        $pluginEnvio = $this->getPluginEnvio();
        //Envia uno por uno los correos para manejo de reemplazo de campos
        foreach ($correos as $cor) {
            $correo = trim($cor);
            //busca datos alamacenados de correo
            $sql = $db->mkSQL("SELECT * FROM bocorreo WHERE boCorreo_email = %Q", $correo);
            if ($db->query($sql)) {
                $row = $db->fetchRow();
                $cor = array("id"=>$row["boCorreo_id"],"correo"=>$row["boCorreo_email"],"identificacion"=>$row["boCorreo_identificacion"],"nombre"=>$row["boCorreo_nombre"]);
                $contenidoxCorreo = $objBitacora->preparaContenidoBoletinPorEmail($cor, 0, $contenido, $camposCorreo);
                $objBitacora->enviaBoletinPorEmail($correo, $titulo, $mfrom, $contenidoxCorreo, -1, $pluginEnvio);
            }
        }
        return true;
    }
    
    //__Descripción:__ Función que permite ejecutar un envío por bitácora de boletines
    //__Inputs:__ $objConten:object objecto de tabla bocontenido
    //            $correos:array lista de correos
    //__Outputs:__ $result:boolean/string
    public function envioBoletinBitacora($objConten,$lista,$fechaProgramada,$horaProgramada,$minutoProgramado){
        require_once("../boletines/classes/class.boBitacora.php");
        require_once("../boletines/classes/class.boCorreo.php");
        $objBitacora = new boBitacora();
        $objCorreo = new boCorreo();
        $boletinId = $objConten->get("id");
        $contenido = $objConten->get("contenido");
        //$contenido = $objCorreo->agregarSeccionDesuscripcion($contenido);
        //1. Obtener jerarquias marcadas para almacenamiento en bitácora
        $lista = explode(";", $lista);
        $marcados = "0,";
        foreach ($lista as $item) {
            if ($item <> "") {
                $valores = explode("|", $item);
                $id = $valores[0];
                $marcados .= $id . ",";
            }
        }
        $marcados = rtrim($marcados, ',');
        //2. Sección de evaluación de programación
        $nombreArchivoProgramado = "";
        $fechaPrgUnx = 0;
        $horaPgr = "";
        if ($fechaProgramada != -1 && $horaProgramada != -1 && $minutoProgramado != -1) {
            $fechaPgr = date("Y-m-d", $fechaProgramada);
            $horaPgr = $horaProgramada;
            $minutoPgr = $minutoProgramado;
            $fechaPrgUnx = strtotime($fechaPgr);
            $horaPgr = $horaPgr . ":" . $minutoPgr;
            $nombreArchivoProgramado = "Pgr" . $fechaPgr . "_" . $horaPgr . "-" . $minutoPgr;
        }
        //3. Obtengo listado de correos en función de selección
        require_once("../boletines/classes/class.boCorreo.php");
        $correos = $objCorreo->obtenerCorreosPorMarca($lista);
        $filasTotales = count($correos);
        if ($filasTotales > 0) {
            //5. Agrega registro de envío en bitácora
            $idBitacora = $objBitacora->insertaBitacora($boletinId, $fechaPrgUnx, $horaPgr, 0, $filasTotales, 0, $marcados, $contenido);
            if ($idBitacora > 0) {
                //genera archivo para envío
                if ($nombreArchivoProgramado == "") {
                    $nombreArchivo = "Bitacora_" . $idBitacora . "_Boletin_" . $boletinId . "_" . date("Y-m-d h-i", time());
                } else {
                    $nombreArchivo = "Bitacora_" . $idBitacora . "_Boletin_" . $boletinId . "_" . date("Y-m-d h-i", time()) . "_" . $nombreArchivoProgramado;
                }
                $archivo = BASEFOLDER . "/boletines/logs/" . $nombreArchivo;
                $fpnn = fopen($archivo, "w");
                foreach ($correos as $cor) {
                    $linea = $cor["id"] . "|" . $cor["email"] . "|" . $cor["identificacion"] . "|" . $cor["nombre"] . "\n";
                    fwrite($fpnn, $linea);
                }
                fclose($fpnn);
                //actualiza nombre de archivo generado para bitácora de envío
                $objBitacora->actualizaNombreBitacora($nombreArchivo, $idBitacora);
                return true;
            } else {
                return "No se ha podido crear boletín en cola de envío";
            }
        } else {
            return"No existen correos para enviar (" . $objConten->get("nombre") . " " . $boletinId . ")";
        }
    }
    
    //__Descripción:__ Función que permite ejecutar publicación desde una lista en facebook
    //__Inputs:__ $objConten:object objecto de tabla bocontenido
    //            $correos:array lista de correos
    //__Outputs:__ $result:int/string
    public function envioManualFacebook($objConten,$correos){
        global $lang;
        $boletinId = $objConten->get("id");
        //Obtiene contenido a enviar
        $db = new MYSQLDB();
        //obtengo el título y contenido del boletín
        $sql = $db->mkSQL("SELECT * FROM bocontenido WHERE boContenido_id=%N", $boletinId);
        //$tituloEnvio = getConf("Boletines Electronicos");
        //$enviosAlertas = getConf("Email alerts");
        //$subject = $tituloEnvio["Titulo Envio"][0];
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $imagen = $row["boContenido_url"];
            $conecId = $row["boContenido_conexId"];
            $ad_datos = [
                'titulo' => $row["boContenido_nombre"],
                'descripcion' => ($row["boContenido_descripcion"]) ? $row["boContenido_descripcion"] : "",
                'enlace' => ($row["boContenido_enlace"]) ? $row["boContenido_enlace"] : ""
            ];
            //Obtiene toda la información de los correos seleccionados
            $infoCorreo = array();
            foreach ($correos as $cor) {
                $corId = $cor["bco_id"];
                $sql = $db->mkSQL("SELECT * FROM bocorreo WHERE boCorreo_id=%N", $corId);
                if ($db->query($sql)) {
                    $row = $db->fetchRow();
                    $camposLimpios = array();
                    foreach ($row as $campo => $valor) {
                        $campo = str_replace("boCorreo_", "", $campo);
                        $camposLimpios[$campo] = $valor;
                    }
                    $infoCorreo[] = $camposLimpios;
                }
            }
            //Enviar a facebook por correo selecionado ojo
            //$face->publicarEnFacebook($infoCorreo,$imagen,$conecId); here
            if (count($infoCorreo) > 0) {
                //5. Enviar a facebook por correo selecionado ojo
                $estado = "PENDIENTE POR REVISION";
                require_once("../boletines/classes/class.boRedesConf.php");
                $face = new boRedesConf();
                $ans = $face->publicarEnFacebook($imagen, $infoCorreo, $conecId, $ad_datos);
                if ($ans['op']) {
                    //6. Agrega registro de envío en bitácora
                    $conecId = $conecId . '#' . $ans['message'];
                    //trigger_error('Track: after Ad created: ' . $conecId);
                    require_once("../boletines/classes/class.boBitacoraRedes.php");
                    $bit = new boBitacoraRedes();
                    $idBitacora = $bit->insertar($boletinId, 0, "", count($infoCorreo), 0, $estado, $imagen, $conecId, "facebook");
                    if ($idBitacora > 0) {
                        return $idBitacora;
                    } else {
                        return $lang["No se ha podido crear boletín en cola de envío"];
                    }
                } else {
                    return $lang["Disculpe, no hemos podido procesar su solicitud en Facebook"];
                }
            } else {
                return $lang["No existen correos para enviar"] . " (" . $lang["sección"] . " " . $boletinId . ")";
            }
            return true;
        } else {
            return $lang["No se ha podido obtener información del boletín"] . " (" . $lang["sección"] . " " . $boletinId . ")";
        }
    }
    
    //__Descripción:__ Funcion para crear una lista de publicación a facebook en base a categorías marcadas
    //                 Éste envío genera un registro de envío por bitácora, por lo que el envío lo realiza el plugin actualizaBitacoraBoletinesRedes.php
    //__Inputs:__ $lista:array lista de jerarquías marcadas en el módulo
    //            $boletinId:int id de tabla bocontenido
    //            $fechaProgramada:int fecha de envío programada
    //            $horaProgramada:string hora de envío programado
    //            $minutoProgramado:string minuto de envío programado
    //__Outputs:__ string/true string:error,true:ok
    public function envioFacebookBitacora($objConten, $lista, $fechaProgramada = -1, $horaProgramada = -1, $minutoProgramado = -1){
        global $lang;
        $boletinId = $objConten->get("id");
        $db = new MYSQLDB();
        $objCorreo = new boCorreo();
        $lista = explode(";", $lista);
        //1. Obtener jerarquias marcadas para almacenamiento en bitácora
        $marcados = "0,";
        foreach ($lista as $item) {
            if ($item <> "") {
                $valores = explode("|", $item);
                $id = $valores[0];
                $marcados .= $id . ",";
            }
        }
        $marcados = rtrim($marcados, ',');
        //2. Sección de evaluación de programación --
        $nombreArchivoProgramado = "";
        $fechaPrgUnx = 0;
        $horaPgr = "";
        if ($fechaProgramada != -1 && $horaProgramada != -1 && $minutoProgramado != -1) {
            $fechaPgr = date("Y-m-d", $fechaProgramada);
            $horaPgr = $horaProgramada;
            $minutoPgr = $minutoProgramado;
            $fechaPrgUnx = strtotime($fechaPgr);
            $horaPgr = $horaPgr . ":" . $minutoPgr;
            $nombreArchivoProgramado = "Pgr" . $fechaPgr . "_" . $horaPgr . "-" . $minutoPgr;
        }
        //3. Obtengo el título y contenido del boletín
        $sql = $db->mkSQL("SELECT * FROM bocontenido WHERE boContenido_id=%N", $boletinId);
        if ($db->query($sql)) {
            $contenido = $db->fetchRow();
//                $imagen = rtrim(BASEURL,'/').$contenido["boContenido_url"];
            $imagen = $contenido["boContenido_url"];
            $conecId = $contenido["boContenido_conexId"];
            $ad_datos = [
                'titulo' => $contenido["boContenido_nombre"],
                'descripcion' => ($contenido["boContenido_descripcion"]) ? $contenido["boContenido_descripcion"] : "",
                'enlace' => ($contenido["boContenido_enlace"]) ? $contenido["boContenido_enlace"] : ""
            ];
            if ($contenido != "") {
                //4. Obtengo listado de correos en función de selección
                $correos = $objCorreo->obtenerCorreosPorMarca($lista);
                $filasTotales = count($correos);
                if ($filasTotales > 0) {
                    //5. Enviar a facebook por correo selecionado ojo
                    $estado = "PENDIENTE POR REVISION";
                    require_once("../boletines/classes/class.boRedesConf.php");
                    $face = new boRedesConf();
                    $ans = $face->publicarEnFacebook($imagen, $correos, $conecId, $ad_datos);
                    if ($ans['op']) {
                        //6. Agrega registro de envío en bitácora
                        $conecId = $conecId . '#' . $ans['message'];
                        trigger_error('Track: after Ad created: ' . $conecId);
                        require_once("../boletines/classes/class.boBitacoraRedes.php");
                        $bit = new boBitacoraRedes();
                        $idBitacora = $bit->insertar($boletinId, $fechaPrgUnx, $horaPgr, $filasTotales, $marcados, $estado, $imagen, $conecId, "facebook");
                        if ($idBitacora > 0) {
                            return $idBitacora;
                        } else {
                            return $lang["No se ha podido crear boletín en cola de envío"];
                        }
                    } else {
                        return $lang["Disculpe, no hemos podido procesar su solicitud en Facebook"];
                    }
                } else {
                    return $lang["No existen correos para enviar"] . " (" . $lang["sección"] . " " . $boletinId . ")";
                }
            } else {
                return $lang["La bitacora seleccionada no posee datos de contenido"] . " (" . $lang["sección"] . " " . $boletinId . ")";
            }
        } else {
            return $lang["No se ha podido obtener información del boletín"] . " (" . $lang["sección"] . " " . $boletinId . ")";
        }
    }
    
    //__Descripción:__ Función que permite ejecutar envío de sms de manera masiva
    //__Inputs:__ 
    //__Outputs:__ $result:boolean/string
    public function envioBoletinArchivo($tipo,$objConten,$archivo,$fechaProgramada,$horaProgramada,$minutoProgramado){
        if($tipo == "correo"){
            require_once("../boletines/classes/class.boBitacora.php");
            require_once("../boletines/classes/class.boCorreo.php");
            $objBitacora = new boBitacora();
            $objCorreo = new boCorreo();
            $boletinId = $objConten->get("id");
            $contenido = $objConten->get("contenido");
            $marcados = "";
            //$contenido = $objCorreo->agregarSeccionDesuscripcion($contenido);
            //2. Sección de evaluación de programación
            $nombreArchivoProgramado = "";
            $fechaPrgUnx = 0;
            $horaPgr = "";
            if ($fechaProgramada != -1 && $horaProgramada != -1 && $minutoProgramado != -1) {
                $fechaPgr = date("Y-m-d", $fechaProgramada);
                $horaPgr = $horaProgramada;
                $minutoPgr = $minutoProgramado;
                $fechaPrgUnx = strtotime($fechaPgr);
                $horaPgr = $horaPgr . ":" . $minutoPgr;
                $nombreArchivoProgramado = "Pgr" . $fechaPgr . "_" . $horaPgr . "-" . $minutoPgr;
            }
            //3. Obtengo listado de correos en función de selección
            $rutaArchivo = BASEFOLDER."boletines/logs/".$archivo;
            $filasTotales = 0;
            if(file_exists($rutaArchivo)){
                $archivoCorreo = fopen($rutaArchivo, "r");
                if($archivoCorreo){
                    $filas = file($rutaArchivo);
                    $filasTotales = count($filas);
                }
                fclose($archivoCorreo);
            }
            if ($filasTotales > 0) {
                $idBitacora = $objBitacora->insertaBitacora($boletinId, $fechaPrgUnx, $horaPgr, 0, $filasTotales, 0, $marcados, $contenido, $archivo);
                if ($idBitacora > 0) {
                    return true;
                } else {
                    return "No se ha podido crear boletín en cola de envío";
                }
            } else {
                return"No existen correos para enviar (" . $objConten->get("nombre") . " " . $boletinId . ")";
            }
        }else if($tipo == "facebook"){
            return "Pendiente";
        }else if($tipo == "sms"){
            return "Pendiente";
        }
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
    
    //__Descripción:__ Función que permite ejecutar el envío de un boletín/facebook/sms
    //__Inputs:__ $tipo:string tipo de envío
    //            $objBitacora:object objeto bitácora
    //            $objContenido:object objeto contenido
    //            $datosEnviar:array lista de datos a enviar
    //__Outputs:__ $result:boolean/string
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
            return true;
        }else{
            return "No activa";
        }
    }
}
?>
<? //_FIN_DE_ARCHIVO  ?>