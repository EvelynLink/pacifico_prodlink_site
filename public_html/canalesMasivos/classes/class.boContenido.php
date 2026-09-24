<?

require_once("../comunes/classes/class.clase.php");

class boContenido extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bocontenido", "boContenido_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite insertar un registro en tabla bocontenido
    //__Inputs:__ $nombre:string título del contenido
    //            $fechaCreacion:int fecha de creación de registro
    //            $url:string url de página de donde se extraerá el contenido
    //            $contenido:string HTML extraido de la url definida en el campo url
    //            $categoriaId:int id de referencia a la tabla categoria del editor antiguo
    //__Outputs:__ $id:int id de tabla bocontenido
    function insert($nombre, $fechaCreacion, $url = "", $contenido = "", $categoriaId = 0) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("INSERT INTO bocontenido (boContenido_nombre,boContenido_url,boContenido_fechaCreacion,boContenido_contenido,boContenido_categoriaId) VALUES (%Q,%Q,%N,%Q,%N)", $nombre, $url, $fechaCreacion, $contenido, $categoriaId);
        $id = $db->query($sql);
        return $id;
    }

    //__Descripción:__ Función que permite actualizar registro de tabla bocontenido
    //__Inputs:__ $contenidoId:int id de tabla bocontenido
    //            $nombre:string título de contenido
    //            $url:string url de sitio
    //            $tipo: tipo de contenido (correo/facebook/sms)
    //            $contenido:string contenido HTML
    //            $usuarioId:int usuario que realiza la actualización
    //            $fecha:int fecha de actualización
    //            $conexId:int id de tabla redesconf (solo es usada para conexiones disponibles para publicación en redes sociales)
    //            $enlace:string cadena que representa una dirección url que será parte de una publicación a redes sociales
    //            $descripcion:string cadena que representa un texto que será parte de una publicación a redes sociales
    //            $origen:string orígen de envío modulo/archivo
    //            $mensaje:string mensaje para tipo sms
    //            $plugin:string nombre del plugin a usar
    //__Outputs:__ 
    function actualizar($categoriaId,$contenidoId, $nombre, $url, $tipo, $contenido, $usuarioId, $fecha, $conexId = "", $enlace = "", $descripcion = "", $origen="", $mensaje="", $plugin="") {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE bocontenido 
                                SET boContenido_categoriaId=%N,
                                    boContenido_nombre=%Q,
                                    boContenido_url=%Q,
                                    boContenido_tipo=%Q,
                                    boContenido_contenido=%Q,
                                    boContenido_usuarioActualiza=%N,
                                    boContenido_fechaActualiza=%N,
                                    boContenido_conexId=%Q,
                                    boContenido_enlace=%Q,
                                    boContenido_descripcion=%Q,
                                    boContenido_origen=%Q,
                                    boContenido_mensaje=%Q,
                                    boContenido_plugin=%Q
                                WHERE boContenido_id=%N",
        $categoriaId, $nombre, $url, $tipo, $contenido, $usuarioId, $fecha, $conexId, $enlace, $descripcion, $origen, $mensaje, $plugin, $contenidoId);
        $db->query($sql);
    }

    //__Descripción:__ Función que permite eliminar un registro de contenido con sus respecitvas relaciones en bitacora
    //__Inputs:__ $id:int id de tabla bocontenido
    //__Outputs:__ 
    function eliminar($id) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bobitacora WHERE boBitacora_contenidoId=%N", $id);
        if ($db->query($sql)) {
            while ($row = $db->fetchRow()) {
                $bitacoraId = $row["boBitacora_id"];
                $sql = $db->mkSQL("SELECT * FROM boregistro WHERE boRegistro_bitacoraId=%N", $bitacoraId);
                if ($db->query($sql)) {
                    $sql = $db->mkSQL("DELETE FROM boregistro WHERE boRegistro_bitacoraId=%N", $bitacoraId);
                    $db->query($sql);
                }
                $sql = $db->mkSQL("DELETE FROM bobitacora WHERE boBitacora_id=%N", $bitacoraId);
                $db->query($sql);
            }
        }
        $sql = $db->mkSQL("DELETE FROM bocontenido WHERE boContenido_id=%N", $id);
        $db->query($sql);
    }
    
    //__Descripción:__ Función que permite obtener el plugin del boletín instanciado
    //__Inputs:__ 
    //__Outputs:__ $result:object plugin del boletin, string error
    function getPlugin(){
        global $lang;
        $plugin = $this->get("plugin");
        $clase = !empty($plugin) ? $plugin : "pgBoletinesDefault";
        if(!empty($plugin)){
            $requiered = "boletines/plugins/class.".$plugin.".php";
            if(!empty($requiered)){
                if(file_exists("../".$requiered)){
                    require_once ("../".$requiered);
                    $pg = new $clase();
                    return $pg;
                }else{
                    return $lang["No se pudo encontrar archivo"].": ".$rutaPlugin;
                }
            }else{
                return $lang["No se ha podido definir la ruta del plugín del boletín"].": ".$this->get("nombre");
            }
        }else{
            return $lang["No se ha definido el plugin para el boletín"].": ".$this->get("nombre");
        }
    }
    
    //__Descripción:__ Función que permite guardar la configuración de un boletín
    //__Inputs:__ 
    //__Outputs:__ $result:string 
    function guardarConfiguracionBoletin($categoriaId,$contenidoId,$nombre,$url,$tipo,$usuarioId,$fechaCreacion,$conexId,$enlace,$descripcion,$origen,$mensaje,$plugin){
        require_once ("../boletines/classes/class.boCategoria.php");
        require_once("../boletines/classes/class.boCorreo.php");
        $bco = new boCorreo();
        $bct = new boCategoria();
        $html = "";
        if($tipo == "correo"){
            if(empty($categoriaId)){
                //No uso editor de contenidos
                if($url == ""){
                    //Si no existe url seleccionada obtengo lo que se encuentre almacenado previamente en contenido
                    $html = $this->get("contenido"); 
                }else{
                    //extraigo html de url definida
                    $html = $this->generarContenidoHtml($url);
                }
            }else{
                //Uso editor de contenidos, obtengo el html creado en el editor de contenidos
                $result = $bct->obtenerHtmlEditor($categoriaId);
                $html = isset($result["html"]) ? $result["html"] : "";
                //Agrego la sección de desuscripción que requiere el html
                $html = $bco->agregarSeccionDesuscripcion($html);
                //Obtengo url modificada
                $url = isset($result["url"]) ? $result["url"] : "";
                //$conten->setDatosEditor($html,$url);
            }
        }else if($tipo == 'facebook'){
            //no requiere modificaciones en HTML, usa imágen seleccionada en url
        }else if($tipo == 'sms'){
            //no requiere modificaciones en HTML, usa campo mensaje
        }
        $this->actualizar($categoriaId,$contenidoId,$nombre,$url,$tipo,$html,$usuarioId,$fechaCreacion,$conexId,$enlace,$descripcion,$origen,$mensaje,$plugin);
        return $html;
    }

    //__Descripción:__ Función que permite cambiar estado de una boletín instanciado
    //__Inputs:__ $estado:string estado de registro bocontenido (activo/inactivo)
    //__Outputs:__ 
    function cambiarEstado($estado){
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE bocontenido SET boContenido_estado=%Q WHERE boContenido_id=%N", $estado, $this->get("id"));
        $db->query($sql);
    }

    //__Descripción:__ Función que permite obtener el id de boletín relacionado a un nombre de contenido
    //__Inputs:__ $nombre:string título del contenido a buscar
    //__Outputs:__ $boletinId:int id de tabla bocontenido, la cual contiene toda la información del boletín
    function obtenerBoletinPorNombre($nombre) {
        $boletinId = 0;
        $nombre = "%" . $nombre . "%";
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bocontenido WHERE boContenido_nombre LIKE %Q", $nombre);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $boletinId = $row['boContenido_id'];
        }
        return $boletinId;
    }

    //__Descripción:__ Función que permite generar contenido HTML con direcciones de absolutas encontradas en una URL
    //__Inputs:__ $url:string dirección del sitio del cual se extraerá el contenido HTML
    //__Outputs:__ $htmlFimal:string HTML con rutas absolutas de imagen
    function generarContenidoHtml($url) {
        $htmlFinal = "";
        if ($url != "") {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $html = curl_exec($ch);
            curl_close($ch);
            //print_h($html);
            //agrega url en imagenes
            if (is_string($html) && $html != "") {
                $partes = explode("/", $url);
                $ultPos = count($partes) - 1;
                if ($partes[$ultPos] == "") {
                    $ultPos = $ultPos - 1;
                }
                $urlBase = "";
                //la ultima posición tiene un punto? es decir va direccionado a un archivo..
                //https://elefante.espaciolink.com/web/boletin.php?c=948
                //http://www.buenanueva.net/biblia/1-biblia1er_Grado/8_biblia1-David.html
                if ((strpos($partes[$ultPos], ".") !== false)) {
                    //La url base es todo menos la sección final
                    for ($i = 0; $i < $ultPos; $i++) {
                        $urlBase .= $partes[$i] . "/";
                    }
                    //Comprueba raiz de url
                    $raiz1 = "http://";
                    $raiz2 = "https://";
                    if ($urlBase == $raiz1 || $urlBase == $raiz2) {
                        $urlBase = rtrim($url, "/") . "/";
                    }
                }
                //https://es.wikipedia.org/wiki/Historia
                else {
                    $urlBase = rtrim($url, "/") . "/";
                }
                $filas = explode("\n", $html);
                foreach ($filas as $key => $fila) {
                    $partes = explode('"', $fila);
                    //print_h($partes);
                    $partesLimpias = array();
                    foreach ($partes as $prt) {
                        if ((strpos($prt, "JPG") !== false) || (strpos($prt, "GIF") !== false) || (strpos($prt, "JPEG") !== false) || (strpos($prt, "PNG") !== false) || (strpos($prt, "ICO") !== false) || (strpos($prt, "jpg") !== false) || (strpos($prt, "gif") !== false) || (strpos($prt, "jpeg") !== false) || (strpos($prt, "png") !== false) || (strpos($prt, "ico") !== false)) {
                            if (strpos($prt, "http") !== false) {
                                //no requiere agregar ruta
                                $partesLimpias[] = $prt;
                            } else {
                                //agrego ruta limpia
                                $partesLimpias[] = $urlBase . $prt;
                            }
                        } else {
                            $partesLimpias[] = $prt;
                        }
                    }
                    foreach ($partesLimpias as $key => $p) {
                        $htmlFinal .= $p;
                        $pos = strpos($p, "=");
                        if ($pos !== false && $pos == (strlen($p) - 1)) {
                            $htmlFinal .= "\"";
                        } else {
                            $ant = $key - 1;
                            if (isset($partesLimpias[$ant])) {
                                $antData = $partesLimpias[$ant];
                                $pos = strpos($antData, "=");
                                if ($pos !== false && $pos == (strlen($antData) - 1)) {
                                    $htmlFinal .= "\"";
                                }
                            }
                        }
                    }
                    $htmlFinal .= "\n";
                }
            }
            //print_h($htmlFinal);
            //$htmlFinal = $html;
        }
        return $htmlFinal;
    }

    //__Descripción:__ Función que permite actualizar el campo contenido de la tabla bocontenido
    //__Inputs:__ $html:string cadena que contiene HTML
    //__Outputs:__ 
    function setContenido($html) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE bocontenido SET boContenido_contenido=%Q WHERE boContenido_id=%N", $html, $this->id);
        $db->query($sql);
    }

    //__Descripción:__ Función que permite actualizar los campos contenido y url de la tabla bocontenido
    //__Inputs:__ $html:string cadena que contien HTML
    //            $url:string dirección de sitio de donde se extrae el contenido HTML
    //__Outputs:__ 
    function setDatosEditor($html, $url) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE bocontenido SET boContenido_contenido=%Q,boContenido_url=%Q WHERE boContenido_id=%N", $html, $url, $this->id);
        $db->query($sql);
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bocontenido",
                        "prefix" => "boContenido_",
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
                                "name" => "categoriaId",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "usuarioActualiza",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaActualiza",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "contenido",
                                "type" => "text",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "",
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
                                "name" => "url",
                                "type" => "varchar",
                                "size" => "500",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaCreacion",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "tipo",
                                "type" => "varchar",
                                "size" => "20",
                                "default" => "correo",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "conexId",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "enlace",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "descripcion",
                                "type" => "varchar",
                                "size" => "300",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "estado",
                                "type" => "varchar",
                                "size" => "20",
                                "default" => "activo",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "origen",
                                "type" => "varchar",
                                "size" => "20",
                                "default" => "modulo",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "mensaje",
                                "type" => "varchar",
                                "size" => "500",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "plugin",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "pgBoletinesDefault",
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
