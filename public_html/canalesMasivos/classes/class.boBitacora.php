<?

require_once("../comunes/classes/class.clase.php");

class boBitacora extends Clase {

    protected $objContenido;


    //funcion que crea el objeto
    function __construct() {
        parent::init("bobitacora", "boBitacora_");
        $this->checkStructure();
    }
    
    //__Descripción:__ Función que permite instanciar el objContenido relacionado a la bitácora
    //__Inputs:__ 
    //__Outputs:__ 
    function getContenido(){
        require_once("../boletines/classes/class.boContenido.php");
        $objConten = new boContenido();
        if(!empty($this->get("contenidoId")) && !isset($this->objContenido)){
            $objConten->initFromDB($this->get("contenidoId"));
            $this->objContenido = $objConten;
        }
        return $this->objContenido;
    }

    //__Descripción:__ Función que permite obtener el plugin de la bitácora instanciada
    //__Inputs:__ 
    //__Outputs:__ 
    function getPlugin(){
        $boletinId = $this->get("contenidoId");
        require_once("../boletines/classes/class.boContenido.php");
        $objConten = new boContenido();
        $objConten->initFromDB($boletinId);
        if($boletinId == $objConten->get("id")){
            $result = $objConten->getPlugin();
            return $result;
        }else{
            return "No se pudo encontrar el boletín relacionado: ".$boletinId;
        }
    }

    //__Descripción:__ Función que permite preparar el contenido del boletìn en base a los datos del correo enviado
    //__Inputs:__ $cor:array de datos del cliente en formato ["id","correo","identificacion","nombre"]
    //__Outputs:__ $result:string contenido preparado con datos enviados
    function preparaContenidoBoletinPorEmail($cor,$idBitacora,$contenido,$camposCorreo){
        $contenidoxCorreo = $contenido;
        //Reemplazo para des-suscripcion
        $correoUsrClave = hash("md5", $cor["id"]."mhfg$%/(2345&!xs");
        $urlDesSus = "boletines/SuscripcionesWeb.php#/SuscripcionWeb/".$correoUsrClave."/".$cor["id"]."/".$idBitacora;
        $contenidoxCorreo = str_replace("__stopNewsletter.php__",$urlDesSus,$contenidoxCorreo);
        //encuentra y reemplaza
        foreach ($camposCorreo as $key=>$campo){
            $pos = strpos($contenido,$key);
            if($pos !== false){
                $valorDeReemplazo = ""; $encontre = false;
                if(isset($cor[$campo])){
                    $encontre = true;
                    $valorDeReemplazo = $cor[$campo];
                }
                if($key == "@@nombre" && trim($valorDeReemplazo) == ""){
                    $valorDeReemplazo = $cor["correo"];
                }else{
                    if(!$encontre){
                        trigger_error("Boletines :: preparaContenidoBoletinPorEmail, no se ha podido encontrar el campo de reemplazo: ".$campo);
                    }
                }
                $contenidoxCorreo = str_replace($key,$valorDeReemplazo,$contenidoxCorreo);
            }
        }
        //agrega sección de reportería
        $hashValida = hash("md5", $idBitacora."mhfg$%/(2345&!xs")."/(x".hash("md5", $cor["correo"]."mhfg$%/(2345&!xs");
        $contenidoxCorreo.='<div><img src="'.BASEURL.'boletines/registroLecturas.php?act=registraLectura&bit='.$idBitacora.'&cr='.$cor["correo"].'&hash='.$hashValida.'"/></div>';
        return $contenidoxCorreo;
    }
    
    //__Descripción:__ Función que permite ejecutar envío del boletín con el contenido personalizado
    //__Inputs:__ 
    //__Outputs:__ 
    function enviaBoletinPorEmail($correo,$titulo,$mfrom,$contenidoxCorreo,$idBitacora,$pluginEnvio=""){
        if(expect_email($correo) != ""){
            require_once("../alerts/classes/class.alEvent.php");
            $alEv = new alEvent();
            $cuantos = $alEv->send_alert(array(
                            "family" => "Boletines Electrónicos",
                            "name" => "Envío manual de boletines electrónicos",
                            "explanation" => "Envío manual de boletines electrónicos",
                            "subject" => $titulo,
                            "text" => "",
                            "bitacoraId" => $idBitacora,
                            "to" => $correo,
                            "from"=> $mfrom,
                            "html" => $contenidoxCorreo
            ),$pluginEnvio,false);
            $estado="No Enviado";
            $nota="Email alerts no pudo enviar el correo electrónico";
            if($cuantos > 0){
                $nota="";
                $estado="Enviado";
            }
            $this->guardarEnvioCorreo($idBitacora,$correo,$titulo,$estado, $pluginEnvio, $mfrom,$nota);
       }else{
           $estado="No Enviado";
            $nota="El correo electrónico es incorrecto";
            $this->guardarEnvioCorreo($idBitacora,$correo,$titulo,$estado, $pluginEnvio, $mfrom,$nota);
       }
    }
    
    //__Descripción:__ Función que permite guardar la información de envío de un boletín en la colección de mongo
    //__Inputs:__ 
    //__Outputs:__ 
    function guardarEnvioCorreo($idBitacora,$correo,$titulo,$estado, $pluginEnvio, $mfrom, $nota){
        //Crea colección mongo requerida para registro
        $mdb = new MYMONGODB();
        $cnf = getConf("Email alerts");
        $emailOrigen = isset($cnf["Email de origen"][0]) ? $cnf["Email de origen"][0] : "";
        if(DEVELOPMENT){
            $mdb->crearColeccion("bolRegistroBitacora",array());
            $mdb->crearIndice("bolRegistroBitacora", array("bolRegistroBitacora_idBitacora"));
            $mdb->crearIndice("bolRegistroBitacora", array("bolRegistroBitacora_estado"));
        }
        if(!empty($idBitacora)){
            $datosBitacora=array(
                "bolRegistroBitacora_idBitacora"=>(integer)$idBitacora,
                "bolRegistroBitacora_correoElectronico"=>(string)$correo,
                "bolRegistroBitacora_pluginEnvio"=>$pluginEnvio,
                "bolRegistroBitacora_fecha"=>time(),
                "bolRegistroBitacora_nombreBoletin"=>(string)$titulo,
                "bolRegistroBitacora_estado"=>(string) $estado,
                "bolRegistroBitacora_from"=>(string)($mfrom!="")?$mfrom:$emailOrigen,
                "bolRegistroBitacora_nota"=>(string)$nota,
            );
            $mdb->guardar("bolRegistroBitacora",$datosBitacora);
        }
    }
    
    //__Descripción:__ Función que permite definir los campos de reemplazo establecidos por boletines
    //__Inputs:__ 
    //__Outputs:__ $result:array lista de campos posibles
    function obtieneCamposReemplazo(){
        $camposCorreo = array( 
                                "@@correo"=>"correo",
                                "@@nombre"=>"nombre",
                                "@@identificacion"=>"identificacion"
                            );
        return $camposCorreo;
    }
    
    //__Descripción:__ Función que permite ejecutar el envío de boletines de una bitácora instanciada
    //__Inputs:__ $mailsPorBloque:int cantidad de mails a enviar en cada ejecución
    //__Outputs:__ $result:array lista de campos posibles
    function ejecutarBitacora($mailsPorBloque){
        $errores = array();
        global $lang;
        $db1 = new MYSQLDB();
        //Defino parámetros básicos de envío
        $pg = $this->getPlugin();
        if(is_string($pg)){
            $errores[] = "No se pudo instanciar el plugin de envío: ".$pg;
        }else{
            $objContenido = $this->getContenido();
            $tipo = $objContenido->get("tipo");
            if(empty($tipo)){
                $errores[] = "No se pudo establecer el tipo de boletín";
            }else{
                if(method_exists($pg, "preparaDatosEnvio")){
                    if(method_exists($pg, "ejecutaEnvio")){
                        //lee archivo de lista de pendientes de envío
                        $archivo = BASEFOLDER."boletines/logs/".$this->get("archivoEnvio");
                        $archivoCorreo = fopen($archivo, "r");
                        if($archivoCorreo){
                            $lineaLeidasArchivoActual = $this->get("filasLectura");
                            $idBitacora = $this->get("id");
                            $lineasTotalArchivo = $this->get("filasTotal");
                            $correosEnviar = array();
                            $filas = file($archivo);
                            $cuantasFilas = count($filas);
                            $lineasTotalBloque = $mailsPorBloque + $lineaLeidasArchivoActual;
                            if($lineasTotalBloque > $cuantasFilas){
                                $lineasTotalBloque = $cuantasFilas;
                            }
                            $lineasLeidas = 0;
                            //Armo lista de correos a enviar
                            for($i=$lineaLeidasArchivoActual;$i<$lineasTotalBloque; $i++){
                                $strLinea = $filas[$i];
                                $correosEnviar[] = $pg->preparaDatosEnvio($strLinea);
                                $lineasLeidas++;
                            }
                            //Ejecuta envío
                            $result = $pg->ejecutaEnvio($tipo,$this,$objContenido,$correosEnviar);
                            if(is_string($result)){
                                $errores[] = $result;
                            }else{
                                //Actualiza línea de archivo de correo leído
                                $lineasEvaluar = $lineaLeidasArchivoActual + $lineasLeidas;
                                $sql1 = $db1->mkSQL("UPDATE bobitacora SET boBitacora_filasLectura=%N WHERE boBitacora_id = %N",$lineasEvaluar,$idBitacora);
                                $db1->query($sql1);
                                //actualiza estado de envio
                                if($lineasTotalArchivo == $lineasEvaluar){
                                    //Actualiza estado de envío
                                    $sql1 = $db1->mkSQL("UPDATE bobitacora SET boBitacora_estadoEnvio=%N WHERE boBitacora_id = %N",1,$idBitacora);
                                    $db1->query($sql1);
                                }
                            }
                        }else{
                            $errores[] = "Archivo: ". $this->get("archivoEnvio") . " No fue encontrado";
                        }
                        fclose($archivoCorreo);
                    }else{
                        $errores[] = "No se pudo encontrar la función 'ejecutaEnvio' en el tipo plugín";
                    }
                }else{
                    $errores[] = "No se pudo encontrar la función 'preparaDatosEnvio' en el tipo plugín";
                }
            }
        }
        return $errores;
    }

    //__Descripción:__ Inserta registro de tabla bobitacora
    //__Inputs:__ $contenidoId:int (antiguo) id de tabla contenido del módulo antiguo de edición de boletines
    //            $fechaPgr:int fecha de programación de envio
    //            $horaPgr:string hora de programación de envío
    //            $filasLectura:int número de filas enviadas (se actualiza por proceso de actualización de bitácora actualizaBitacoraBoletinesLote.php)
    //            $filaTotal:int número total de envío a generarse en ésta botácora
    //            $estado:int estado de envío (se actualiza por proceso de actualización de bitácora actualizaBitacioraBoletinesLote.php)
    //            $lista:string lista de selección de envío (grupos seleccionados-marcados)
    //            $mensaje:text contenido de boletín a enviar (en la versión actual es obligatorio éste contenido)
    //__Outputs:__ boolean:true/false
    function insertaBitacora($contenidoId, $fechaPgr, $horaPgr, $filasLectura, $filasTotal, $estado, $lista, $mensaje = "", $archivo = "") {
        $db = new MYSQLDB();
        $idBitacora = 0;
        $userId = isset($_SESSION[MID . "userId"]) ? $_SESSION[MID . "userId"] : 0;
        $sql = $db->mkSQL("INSERT INTO bobitacora "
                . "(boBitacora_contenidoId,boBitacora_envioProgramado,"
                . "boBitacora_horaEnvio,boBitacora_filasLectura,"
                . "boBitacora_filasTotal,boBitacora_estadoEnvio,"
                . "boBitacora_creado,boBitacora_marcados,"
                . "boBitacora_userId,boBitacora_mensaje,"
                . "boBitacora_archivoEnvio) "
                . " VALUES (%N,%N,%Q,%N,%N,%N,%N,%Q,%N,%Q,%Q)",
                $contenidoId, $fechaPgr, $horaPgr, 
                $filasLectura, $filasTotal, $estado, 
                time(), $lista, $userId,
                $mensaje, $archivo);
        $idBitacora = $db->query($sql);
        return $idBitacora;
    }

    //__Descripción:__ Función que permite actualizar el nombre del archivo a ser enviado por la bitácora
    //__Inputs:__ $nombreArchivo:string nombre del archivo
    //            $idBitacora:int id de tabla bobitacora
    //__Outputs:__ boolean:true/false
    function actualizaNombreBitacora($nombreArchivo, $idBitacora) {
        $db = new MYSQLDB();
        $sqlUpd = $db->mkSQL("UPDATE bobitacora SET boBitacora_archivoEnvio = %Q WHERE boBitacora_id = %N", $nombreArchivo, $idBitacora);
        return $db->query($sqlUpd);
    }

    //__Descripción:__ Función que permite actualizar el estado de envío de la bitácora
    //__Inputs:__ $estado:int 0:pendiente,1:enviado,2:detenido
    //            $bitacoraId:int id de tabla bobitacora
    //__Outputs:__ boolean:true/false
    function actualizaEstadoEnvio($estado, $bitacoraId) {
        $db = new MYSQLDB();
        return $db->query($db->mkSQL("UPDATE bobitacora SET boBitacora_estadoEnvio = %N WHERE boBitacora_id=%N", $estado, $bitacoraId));
    }

    //__Descripción:__ Inserta un registro de bitacora para facebook
    //__Inputs:__ $jerarquiaId:int id de jerarquía usada
    //            $boletinId:int id de boletin a enviar
    //__Outputs:__ boolean: true/false
    function registrarBitacora($jerarquiaId, $boletinId) {
        //default answer
        $ans = false;
        $fechaPrgUnx = 0;
        $horaPgr = "";
        $db = new MYSQLDB();
        $db1 = new MYSQLDB();
        //Arma Consulta sql final             
        $sql = "SELECT distinct boCorreo_id,boCorreo_email,boCorreo_identificacion,boCorreo_nombre 
                                 FROM bocorreoxjerarquia inner join bocorreo on boCorreo_id=boCorreoxJerarquia_correoId
                                 where boCorreoxJerarquia_jerarquiaId=" . $jerarquiaId;
        $cCorreos = $db->query($sql);
        if ($cCorreos > 0) {
            //obtengo el título y contenido del boletín
            $sql1 = $db1->mkSQL("SELECT bocontenido.* FROM bocontenido WHERE boContenido_id=%N", $boletinId);
            if ($db1->query($sql1)) {
                $contenido = $db1->fetchRow();
                $contenido = $contenido["boContenido_contenido"];
                //agrega registro en bitácora
                $idBitacora = $this->insertaBitacora($boletinId, $fechaPrgUnx, $horaPgr, 0, $cCorreos, 0, $jerarquiaId, $contenido);
                if ($idBitacora) {
                    $ans = true;
                    //genera archivo para envío
                    $nombreArchivo = "Bitacora_" . $idBitacora . "_Boletin_" . $boletinId . "_" . date("Y-m-d h-i", time());

                    $archivo = BASEFOLDER . "/boletines/logs/" . $nombreArchivo;
                    $fpnn = fopen($archivo, "w");
                    while ($cor = $db->fetchRow()) {
                        $linea = $cor["boCorreo_id"] . "|" . $cor["boCorreo_email"] . "|" . $cor["boCorreo_identificacion"] . "|" . $cor["boCorreo_nombre"] . "\n";
                        fwrite($fpnn, $linea);
                    }
                    fclose($fpnn);
                    //actualiza nombre de archivo generado para bitácora de envío
                    $this->actualizaNombreBitacora($nombreArchivo, $idBitacora);
                } else {
                    trigger_error("Error: No se registro la bitacora");
                }
            } else {
                trigger_error("Error: No existe contenido para enviar los correos");
            }
        } else {
            trigger_error("Error: No existen Correos suscritos para enviar");
        }
        return $ans;
    }
    
    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bobitacora",
                        "prefix" => "boBitacora_",
                        "fields" => array(
                            array(
                                "name" => "id",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "primary",
                            ),
                            array(
                                "name" => "contenidoId",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "archivoEnvio",
                                "type" => "varchar",
                                "size" => "300",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "envioProgramado",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "horaEnvio",
                                "type" => "varchar",
                                "size" => "5",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "filasLectura",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "filasTotal",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "estadoEnvio",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "creado",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "marcados",
                                "type" => "varchar",
                                "size" => "1000",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "userId",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "mensaje",
                                "type" => "text",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "enviados",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "noenviados",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "leidos",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "desuscritos",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                        )
                    )
            );
        }
    }

}
?>
<? //_FIN_DE_ARCHIVO  ?>