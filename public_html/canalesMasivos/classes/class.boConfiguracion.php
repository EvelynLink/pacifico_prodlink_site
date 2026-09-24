<?

require_once("../comunes/classes/class.clase.php");

class boConfiguracion extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("boconfiguracion", "boConfiguracion_");
        $this->checkStructure();
    }
    
    //__Descripción:__ Función que permite obtener todas las configuraciones registradas en el sitio
    //__Inputs:__ 
    //__Outputs:__ $result:array
    function getConfiguraciones(){
        $db = new MYSQLDB();
        $tipos = array();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion ORDER BY boConfiguracion_nombre DESC");
        if($db->query($sql)){
            while($row = $db->fetchRow()){
                $camposLimpios = array();
                foreach ($row as $campo=>$valor){
                    $campo = str_replace("boConfiguracion_", "", $campo);
                    $camposLimpios[$campo] = $valor;
                }
                $tipos[] = $camposLimpios;
            }
        }
        return $tipos;
    }
    
    //__Descripción:__ Función que permite obtener la configuración del sitio por tipo
    //__Inputs:__ $tipo:string tipo de configuración (Contacto/Boletin)
    //__Outputs:__ $result:array
    function getConfiguracionPorTipo($tipo){
        $db = new MYSQLDB();
        $tipos = array();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_tipo=%Q",$tipo);
        if($db->query($sql)){
            while($row = $db->fetchRow()){
                $camposLimpios = array();
                foreach ($row as $campo=>$valor){
                    $campo = str_replace("boConfiguracion_", "", $campo);
                    $camposLimpios[$campo] = $valor;
                }
                $tipos[] = $camposLimpios;
            }
        }
        return $tipos;
    }
    
    //__Descripción:__ Función que permite obtener la configuración del sitio por nombre
    //__Inputs:__ $nombre:string nombre de configuración
    //            $tipo:string tipo de configuración
    //__Outputs:__ $result:array
    function getConfiguracionPorNombre($nombre,$tipo="Contacto"){
        $db = new MYSQLDB();
        $camposLimpios = array();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q AND boConfiguracion_tipo=%Q",$nombre,$tipo);
        if($db->query($sql)){
            $row = $db->fetchRow();
            foreach ($row as $campo=>$valor){
                $campo = str_replace("boConfiguracion_", "", $campo);
                $camposLimpios[$campo] = $valor;
            }
        }
        return $camposLimpios;
    }
    
    //__Descripción:__ Función que permite obtener los archivos registrados en boletines/log por tipo
    //                 Bitacora_ =>archivos generados por módulo para envío de correos
    //                 extBitacora_ =>archivos generados por externamente para envío de correos
    //                 Sms_ =>archivos para envío de sms
    //                 Facebook_ =>archivos para envío de facebook
    //__Inputs:__ $tipo:string tipo de archivo solicitado
    //__Outputs:__ 
    function getArchivosProcesamiento($tipo){
        $carpeta = "boletines/logs";
        $archivos = array();
        $thisDir = BASEFOLDER . $carpeta;
        if (!is_dir($thisDir)) {

        } else {
            if($tipo == "correo"){
                $queBusco = "extBitacora_";
            }else if($tipo == "sms"){
                $queBusco = "Sms_";
            }else if($tipo=="facebook"){
                $queBusco = "Facebook_";
            }
            $ficheros = scandir($thisDir);
            foreach ($ficheros as $file) {
                if ($file != "." && $file != "..") {
                    $nombre = $file;
                    $prefijo = substr($file,0,20);
                    if(strpos($prefijo, $queBusco) !== false ){
                        $nombre = str_replace(".txt", "", $file);
                        $archivos[] = array("id"=>$file,"nombre"=>$nombre);
                    }
                }
            }
        }
        return $archivos;
    }
    
    //__Descripción:__ Función que permite presentar el contenido limitado de un archivo a procesar
    //__Inputs:__ 
    //__Outputs:__ 
    function getContenidoArchivo($archivo){
        $nombreArchivo = isset($archivo["id"]) ? $archivo["id"] : "";
        if(!empty($nombreArchivo)){
            $archivo = BASEFOLDER."boletines/logs/".$nombreArchivo;
            if(file_exists($archivo)){
                $archivoCorreo = fopen($archivo, "r");
                if($archivoCorreo){
                    $filas = file($archivo);
                    $cuantasFilas = count($filas);
                    if($cuantasFilas > 0){
                        $html = "<table width=100%>";
                        for($i=0;$i<100; $i++){
                            if(isset($filas[$i])){
                                $html.="<tr><td>";
                                $html.= $filas[$i];
                                $html.="</td></tr>";
                            }
                        }
                        $html .= "</table>";
                    }
                }
                fclose($archivoCorreo);
                return $html;
            }else{
                return "No se pudo encontrar archivo especificado";
            }
        }else{
            return "Nombre de archivo inválido";
        }
    }
    
    //__Descripción:__ Función que permite obtener el email de orígen de salida para envío de información de contacto
    //__Inputs:__ $nombre:string nombre de configuración
    //__Outputs:__ $result:array email de orígen, descripcion
    function getEmailOrigenPorNombre($nombre){
        $mfrom = ""; $descripcion = "";
        if(!empty($nombre)){
            $confAlertas = getConf("Email alerts");
            $emailsOrigen = $confAlertas["Email de origen"];
            //Primera, revisión de configuración en caso de tener separadores de directiva, puede usarse tanto para boletines como para contactos
            foreach ($emailsOrigen as $valor){
                //De valor se espera un registro con nombredirectiva|email@origen.com|alias (alias no se usa en éste contexto)
                $partes = explode('|', $valor);
                $directiva = isset($partes[0]) ? $partes[0] : "";
                $emailOrigen = isset($partes[1]) ? $partes[1] : "";
                $descripcion = isset($partes[2]) ? $partes[2] : "";
                if(!empty($directiva)){
                    if($directiva == $nombre){
                        $mfrom = $emailOrigen;
                        break;
                    }
                }
            }
            //Segunda, revisión de configuración en caso de NO tener separadores de directiva usa el primer dato por defecto de la configuración
            if(empty($mfrom)){
                if(isset($emailsOrigen[0])){
                    $partes = explode('|', $emailsOrigen[0]);
                    if(count($partes)== 1){
                        $emailOrigen = isset($partes[0]) ? $partes[0] : "";
                    }else if(count($partes) >= 2){
                        $emailOrigen = isset($partes[0]) ? $partes[0] : "";
                        $descripcion = isset($partes[1]) ? $partes[1] : "";
                    }
                   $mfrom = $emailOrigen;
                }
            }
        }
        $from = array("mfrom"=>$mfrom,"description"=>$descripcion);
        return $from;
    }
    
    //__Descripción:__ Función que permite obtener el título de la configuración enviada por nombre
    //__Inputs:__ $nombre:string nombre de configuración
    //__Outputs:__ $result:string
    function getTituloPorNombre($nombre){
        $db = new MYSQLDB();
        $titulo = "";
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q",$nombre);
        if($db->query($sql)){
            $row = $db->fetchRow();
            $titulo = $row["boConfiguracion_titulo"];
        }
        return $titulo;
    }
    
    //__Descripción:__ Función que permite obtener los correos agregados sobre la configuración del sitio
    //__Inputs:__ $nombre:string nombre de configuración
    //__Outputs:__ $result:array
    function getCorreosPorNombre($nombre){
        $db = new MYSQLDB();
        $correos = array();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q",$nombre);
        if($db->query($sql)){
            $row = $db->fetchRow();
            if(!empty($row["boConfiguracion_correos"])){
                $correos = explode("\n", $row["boConfiguracion_correos"]);
            }
        }
        return $correos;
    }
    
    //__Descripción:__ Función que permite obtener los correos agregados sobre la configuración del sitio en formato string
    //__Inputs:__ $nombre:string nombre de configuración
    //__Outputs:__ $result:array
    function getCorreosPorNombreString($nombre){
        $db = new MYSQLDB();
        $correos = "";
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q",$nombre);
        if($db->query($sql)){
            $row = $db->fetchRow();
            if(!empty($row["boConfiguracion_correos"])){
                $correos = $row["boConfiguracion_correos"];
            }
        }
        return $correos;
    }
    
    //__Descripción:__ Función que permite guardar los correos en la configuracion del sitio
    //__Inputs:__ $nombre:string nombre de configuración
    //            $correos:string de correos
    //__Outputs:__ $result:boolean
    function guardarCorreosPorNombre($nombre,$correos){
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE boconfiguracion 
                           SET boConfiguracion_correos=%Q
                           WHERE boConfiguracion_nombre=%Q",$correos,$nombre);
        return $db->query($sql);
    }
    
    //__Descripción:__ Función que permite obtener el nombre del grupo de interés a usarse para suscripción a boletines
    //__Inputs:__ $nombre:string nombre de configuración
    //__Outputs:__ $result:string nombre de grupo de interés
    function getGrupoInteresPorNombre($nombre){
        $db = new MYSQLDB();
        $grupoInteres = "";
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q",$nombre);
        if($db->query($sql)){
            $row = $db->fetchRow();
            if(!empty($row["boConfiguracion_grupoSuscripcion"])){
                $grupoInteres = $row["boConfiguracion_grupoSuscripcion"];
            }
        }
        return $grupoInteres;
    }

    //__Descripción:__ Función que permite insertar registro de configuración de boletines
    //__Inputs:__ 
    //__Outputs:__ 
    function insertar($nombre,$correos="",$titulo=""){
        $db = new MYSQLDB();
        $sql = $db->mkSQL("INSERT INTO boconfiguracion 
                (boConfiguracion_nombre,boConfiguracion_correos,boConfiguracion_titulo,boConfiguracion_fechaCreacion)
                VALUES (%Q,%Q,%Q,%N)",$nombre,$correos,$titulo,time());
        return $db->query($sql);
    }
    
    //__Descripción:__ Función que permite actualizar registro de configuración de boletines
    //__Inputs:__ 
    //__Outputs:__ $result:boolean/string
    function actualizar($nombre,$correos="",$titulo="",$grupoSuscripcion="",$pluginEnvio="",$tipo=""){
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion WHERE boConfiguracion_nombre=%Q AND boConfiguracion_id!=%N",$nombre,$this->get("id"));
        if(!$db->query($sql)){
            $sql = $db->mkSQL("UPDATE boconfiguracion 
                               SET boConfiguracion_nombre=%Q,
                               boConfiguracion_correos=%Q,
                               boConfiguracion_titulo=%Q,
                               boConfiguracion_grupoSuscripcion=%Q,
                               boConfiguracion_pluginEnvio=%Q,
                               boConfiguracion_tipo=%Q
                               WHERE boConfiguracion_id=%N",$nombre,$correos,$titulo,$grupoSuscripcion,$pluginEnvio,$tipo,$this->get("id"));
            return $db->query($sql);
        }else{
            return "Nombre registrado previamente";
        }
    }
    
    //__Descripción:__ Función que permite eliminar un registro de configuración de boletines
    //__Inputs:__ 
    //__Outputs:__ $result:boolean
    function eliminar(){
        $db = new MYSQLDB();
        $sql = $db->mkSQL("DELETE FROM boconfiguracion WHERE boConfiguracion_id=%N",$this->get("id"));
        $db->query($sql);
        return true;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "boconfiguracion",
                        "prefix" => "boConfiguracion_",
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
                                "name" => "nombre",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "titulo",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "correos",
                                "type" => "varchar",
                                "size" => "400",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "grupoSuscripcion",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "pluginEnvio",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaCreacion",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "tipo",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "Contacto",
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
