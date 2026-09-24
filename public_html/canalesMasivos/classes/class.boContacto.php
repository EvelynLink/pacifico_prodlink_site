<?

require_once("../comunes/classes/class.clase.php");

class boContacto extends Clase {

    function __construct() {}
    
    //__Descripción:__ Función que permite obtener los parámetros requeridos para el envío de correos para contactos
    //                 usa configuración de módulo de boletines en conjunto con variable de configuración Email de origen
    //__Inputs:__ $name:string nombre de configuración de directiva
    //__Outputs:__ $result:array
    function obtenerParametrosEnvio($name){
        global $lang;
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $configuracion = $objConf->getConfiguracionPorNombre($name);
        $subject = isset($configuracion["titulo"]) ? $configuracion["titulo"] : $lang["Registro de Contacto"];
        $pluginEnvio = isset($configuracion["pluginEnvio"]) ? $configuracion["pluginEnvio"] : "";
        return array("subject"=>$subject,"pluginEnvio"=>$pluginEnvio);
    }
    
    //__Descripción:__ Función que permite obtener los correos configurados en el panel de control de Email Alerts para Boletines electrónicos en formato string
    //__Inputs:__ $name:string nombre de configuración de directiva
    //__Outputs:__ $result:string
    function obtenerConfiguracionTitulo($name){
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $titulo = $objConf->getTituloPorNombre($name);
        return $titulo;
    }
    
    //__Descripción:__ Función que permite obtener los correos configurados en el panel de control de Email Alerts para Boletines electrónicos en formato string
    //__Inputs:__ $name:string nombre de configuración de directiva
    //__Outputs:__ $result:string
    function obtenerConfiguracionCorreosString($name){
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $correos = $objConf->getCorreosPorNombreString($name);
        return $correos;
    }
    
    //__Descripción:__ Función que permite obtener los correos configurados en el panel de control de Email Alerts para Boletines electrónicos
    //__Inputs:__ $name:string nombre de configuración de directiva
    //__Outputs:__ $result:array
    function obtenerConfiguracionCorreos($name){
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $correos = $objConf->getCorreosPorNombre($name);
        return $correos;
    }
    
    //__Descripción:__ Función que permite modificar los correos configurados en el panel de control de Email Alerts para Boletines electrónicos
    //__Inputs:__ $name:string nombre de configuración de directiva
    //__Outputs:__ 
    function guardarConfiguracionCorreos($name,$correos){
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $objConf->guardarCorreosPorNombre($name,$correos);
        return true;
    }
    
    //__Descripción:__ Función que permite guardar los datos del contacto
    //__Inputs:__ 
    //__Outputs:__ 
    function guardarContacto($name, $datos, $contactOnly, $keywords, $ultimaVisitada, $pluginEnvio, $subject){
        $mdb = new MYMONGODB();
        if(DEVELOPMENT){
            $mdb->crearColeccion("boMensajesContacto",array());
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_fecha"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_email"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_tipo"));
        }
        $email = isset($datos["email"]) ? $datos["email"] : "";
        $nombre = isset($datos["nombre"]) ? $datos["nombre"] : "";
        $apellido = isset($datos["apellido"]) ? $datos["apellido"] : "";
        $identificacion = isset($datos["identificacion"]) ? $datos["identificacion"] : "";
        $fechaNacimiento = isset($datos["fechaNacimiento"]) ? $datos["fechaNacimiento"] : "";
        $telefono = isset($datos["telefono"]) ? $datos["telefono"] : "";
        $celular = isset($datos["celular"]) ? $datos["celular"] : "";
        $empresa = isset($datos["empresa"]) ? $datos["empresa"] : "";
        $direccion = isset($datos["direccion"]) ? $datos["direccion"] : "";
        $mensaje = isset($datos["mensaje"]) ? $datos["mensaje"] : "";
        $datamensaje = array(
            "boMensajeContacto_fecha"=>time(),
            "boMensajeContacto_email"=>$email,
            "boMensajeContacto_nombre"=>$nombre,
            "boMensajeContacto_apellido"=>$apellido,
            "boMensajeContacto_identificacion"=>$identificacion,
            "boMensajeContacto_fechaNacimiento"=>$fechaNacimiento,
            "boMensajeContacto_telefono"=>$telefono,
            "boMensajeContacto_celular"=>$celular,
            "boMensajeContacto_empresa"=>$empresa,
            "boMensajeContacto_direccion"=>$direccion,
            "boMensajeContacto_mensaje"=>$mensaje,
            "boMensajeContacto_tipo"=>$name
        );
        //Registro contacto
        $mdb->guardar("boMensajesContacto",$datamensaje);
        //Envíar correo
        $etiquetas = $this->aplicarKeyWords($keywords);
        //Obtener título configurado
        $titulo = $this->obtenerConfiguracionTitulo($name);
        $result = array();
        switch ($contactOnly){
            case "admin":
                //Obtengo correos configurados
                $correos = $this->obtenerConfiguracionCorreos($name);
                //Enviar a admin
                if(count($correos)){
                    $result = $this->enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject);
                }
            break;
            case "client":
                //Enviar a cliente
                if(!empty($email)){
                    $result = $this->enviarCorreoContacto($titulo,$email,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,true);
                }
            break;
            case "both":
                //Enviar ambos
                if(!empty($email)){
                    $result = $this->enviarCorreoContacto($titulo,$email,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,true);
                }
                $correos = $this->obtenerConfiguracionCorreos($name);
                //Enviar a admin
                if(count($correos)){
                    $result = $this->enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject);
                }
            break;
        }
        if(is_string($result)){
            return $result;
        }else{
            return true;
        }
    }
    
    //__Descripción:__ Función que permite definir los alias para los campos de interfaz
    //__Inputs:__ $keywords:string definición de campos desde interfaz
    //__Outputs:__ $result:array lista de valores a reemplazar
    function aplicarKeyWords($keywords){
        $keywordsLimpios = array();
        $campos = explode(",",$keywords);
        foreach ($campos as $c){
            $alias = explode(":", $c);
            $campo = isset($alias[0]) ? $alias[0] : "";
            $valor = isset($alias[1]) ? $alias[1] : "";
            if(!empty($campo) && !empty($valor)){
                $keywordsLimpios[$campo] = $valor;
            }
        }
        return $keywordsLimpios;
    }
    
    //__Descripción:__ Función que permite enviar correo de contacto
    //__Inputs:__ 
    //__Outputs:__ 
    function enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,$cliente=false,$categorias="",$template=""){
        $p = array();
        foreach ($datos as $campo=>$valor){
            if($campo == "fechaNacimiento"){ 
                if(!empty($valor) && $valor != -1){
                    $valor = date("Y-m-d",$valor); 
                }else{
                    $valor = "";
                }
            }
            if(!empty($valor)){
                $e = isset($etiquetas[$campo]) ? $etiquetas[$campo] : $campo;
                $p[$e] = $valor;
            }
        }
        $detalle = array();
        foreach ($p as $campo=>$valor){
            $detalle[] = array("campo"=>$campo,"valor"=>$valor);
        }
        if(empty($template)){
            $template = "../boletines/formatos/boFormatoContacto.txt";
        }
        $result = $this->enviarNotificacionContacto($template, $titulo, $subject, $correos, $detalle, $categorias, $pluginEnvio, $ultimaVisitada, $cliente, true);
        return $result;
    }
    
    //__Descripción:__ Función que permite enviar correo de contacto mediante el uso de plantilla y generadorPdf
    //__Inputs:__ 
    //__Outputs:__ 
    private function enviarNotificacionContacto($template,$titulo,$subject,$correos,$detalle,$categoriasMarcadas,$pluginEnvio,$ultimaVisitada,$cliente,$restrict=false){
        global $lang;
        if($template != ""){
            $urlTemplate = $template;
            if(file_exists($urlTemplate)){
                require_once("../comunes/classes/class.coGeneraPdf.php");
                require_once("../boletines/formatos/class.boFormatoContacto.php");
                $objFormato = new boFormatoContacto();
                $nombreArchivo = $objFormato->getNombre();
                $ubicacionArchivo = $objFormato->getUbicacionArchivo();
                $plantillaCertificado = str_replace("../", "", $template);
                $empresaId = $objFormato->preparaDatos();
                if($empresaId > 0){
                    //Defino parámetros de generación de contenido de correo
                    $gpdf = new coGeneraPdf();
                    $gpdf->setTipoPlantilla("T"); //Tipo archivo
                    $gpdf->setNombreArchivo($nombreArchivo); 
                    $gpdf->setPlantilla($plantillaCertificado); 
                    $gpdf->setUbicacion($ubicacionArchivo); 
                    $gpdf->setClave("@@");
                    //Configuraciones para envío, solo si viene parámetro de envío
                    if($pluginEnvio != ""){
                        $descripcion = $objFormato->getDescripcionEmail();
                        $datos = $objFormato->getDatos($gpdf,$titulo,$empresaId,$correos,$detalle,$categoriasMarcadas,$ultimaVisitada);
                        //hay correos para enviar?
                        if(count($correos) > 0){
                           //solo si puedo enviar realizo seteo de valores requeridos a clase coGeneraPdf
                           $gpdf->setValores($datos);
                           //$gpdf->setConfiguraEnvioPdf($correos,$subject,"",$descripcion,$pluginEnvio);
                           //$gpdf->setContenidoEnvioPdf("",true);
                           $respuesta = $gpdf->genera(true);
                           if(isset($respuesta["html"]) && !empty($respuesta["html"])){
                               $html = $respuesta["html"];
                                require_once("../alerts/classes/class.alEvent.php");
                                $alEv = new alEvent();
                                //no envío mfrom ya que el plugin de envío es el predominante en éste caso y él decide email de origen
                                $mfrom = ""; $cuantos = 0;
                                if($cliente == 0){
                                    $cuantos = $alEv->send_alert(array(
                                        "family" => "Boletines Electrónicos Contacto",
                                        "name" => "Envío nuevos contactos",
                                        "explanation" => "Envío de formulario web de contacto",
                                        "subject" => $subject,
                                        "to" => $correos,
                                        "from" => $mfrom,
                                        "html" => $html
                                    ),$pluginEnvio);
                                }else{
                                    $cuantos = $alEv->send_alert(array(
                                        "family" => "Boletines Electrónicos Contacto Cliente",
                                        "name" => "Envío notificación de contacto a cliente",
                                        "explanation" => "Envío de formulario web de contacto a cliente",
                                        "subject" => $subject,
                                        "to" => $correos,
                                        "from" => $mfrom,
                                        "html" => $html
                                    ),$pluginEnvio);
                                }
                                if($cuantos > 0){
                                    return true;
                                }else{
                                    trigger_error("CONTACTO :: ". $lang["No se pudo enviar el correo"]);
                                    if($restrict){
                                        return $lang["No se pudo enviar el correo"];
                                    }
                                }
                           }else{
                                trigger_error("CONTACTO :: ". $lang["No se pudo generar el template"]);
                                if($restrict){
                                    return $lang["No se pudo generar el template"];
                                }
                           }
                        }else{
                            trigger_error("CONTACTO :: No existen correos para envío de notificación de registro de usuario");
                            if($restrict){
                                return $lang["No existen correos para envío de notificación de registro de usuario"];
                            }
                        }
                    }else{
                        trigger_error("CONTACTO :: No existe configuración de plugin requerido para envíos de módulo Email Alerts: 'Email de origen'");
                        if($restrict){
                            return $lang["No existe configuración de plugin requerido para envíos en módulo Email Alerts: Email de origen"];
                        }
                    }   
                }else{
                    trigger_error("CONTACTO :: No existe empresa relacionada a la asignación cliente");
                    if($restrict){
                        return $lang["No existe empresa relacionada a la asignación cliente"];
                    }
                }
            }else{
                trigger_error("CONTACTO :: No existe el archivo plantilla: ".$urlTemplate);
                if($restrict){
                    return $lang["No existe el archivo plantilla"].": ".$template;
                }
            }
        }else{
            trigger_error("CONTACTO :: enviarNotificacionContacto() No existe Template de notificación boFormatoContacto.txt requerido para envío de correo");
            if($restrict){
                return $lang["No existe Template de notificación boFormatoContacto.txt requerido para envío de correo"];
            }
        }
    }
    
    //__Descripción:__ Función que permite obtener el nombre del grupo de interes relacionado a la directiva
    //__Inputs:__ $name:string nombre de directiva asociada al grupo de interes en boletines/categorias de suscripción
    //__Outputs:__ $result:string nombre de la categoría del grupo
    function obtenerNombreCategoriaGrupoInteres($name){
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        return $objConf->getGrupoInteresPorNombre($name);
    }
    
    //__Descripción:__ Función que permite obtener los grupos de interes especìficos para interfaz manual
    //__Inputs:__ $confBoletines:array variables de configuración de módulo de boletínes
    //            $nombre:string nombre de directiva
    //__Outputs:__ 
    function obtenerGruposInteres($nombreCategoria){
        $db = new MYSQLDB();
        $grupos = array();
        $gruposFiltrados = array();
        //Obtiene ID de grupos de interés
        $idGrInt = 0;
        if($nombreCategoria != ""){
            $sql = $db->mkSQL("SELECT * FROM bocategoria WHERE boCategoria_nombre LIKE %Q", $nombreCategoria);
            if ($db->query($sql)) {
                $row = $db->fetchRow();
                $idGrInt = $row["boCategoria_id"];
            }
        }
        $sql = $db->mkSQL("SELECT 
                                    boCategoria_id 
                                    ,boCategoria_nombre 
                                    ,boJerarquia_id 
                                    ,boJerarquia_descripcion 
                                    ,boJerarquia_nombre 
                                    ,boJerarquia_padreId 
                               FROM 
                                    bocategoria 
                                    LEFT JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId 
                               WHERE boJerarquia_activo = %N AND boCategoria_id <> %N 
                               ORDER BY boJerarquia_id ASC
                               ", 1, $idGrInt);
        if ($db->query($sql) > 0) {
            while ($row = $db->fetchRow()) {
                $key = "c_" . $row["boCategoria_id"];
                $grupos[$key][] = array("id" => $row["boJerarquia_id"], "nombre" => $row["boJerarquia_nombre"]);
            }
            $i = 1;
            foreach ($grupos as $g) {
                $key = "c_" . $i;
                $gruposFiltrados[$key] = $g;
                $i++;
            }
        }
        return $gruposFiltrados;
    }
    
    //__Descripción:__ Función que permite obtener el listado de grupos de suscripción de boletines
    //__Inputs:__ 
    //__Outputs:__ 
    function cargarJerarquia($ubicacionBase,$categoriaId,$abrir,$abrirOriginal,$nombreCategoria){
        $db = new MYSQLDB();
        require_once ("../boletines/classes/class.boJerarquia.php");
        $jerarquia = new boJerarquia();
        //Funcion que obtiene la Genealogia
        $genealogia = $jerarquia->obtenerGenealogia($categoriaId, $abrir, $ubicacionBase);
        //Funcion que obtiene el arbol jerarquico
        $ramas = array();
        //Obtiene ID de grupos de interés
        if (!empty($nombreCategoria)) {
            $idGrInt = 0;
            $sql = $db->mkSQL("SELECT * FROM bocategoria WHERE boCategoria_nombre LIKE %Q", $nombreCategoria);            
            if ($db->query($sql)) {
                $row = $db->fetchRow();
                $idGrInt = $row["boCategoria_id"];
                $hijos = array();
                $anterior = -1;
                //$genealogia[]=$ubicacionBase;
                foreach ($genealogia as $anyId) {
                    if ($anyId == 0) {
                        $db->query($db->mkSQL("SELECT * FROM bocategoria
                                                WHERE boCategoria_id = %N
                                                ORDER BY boCategoria_nombre", $idGrInt));
                        $ramas = array();
                        while ($row = $db->fetchRow()) {
                            if ($row["boCategoria_id"] == "C" . $anterior) {
                                $estosHijos = $hijos;
                                $cerrada = false;
                            } else {
                                $estosHijos = array();
                                $cerrada = true;
                            }
                            $seleccionada = false;
                            $ramas[] = array(
                                "id" => "C" . $row["boCategoria_id"],
                                "nombre" => $row["boCategoria_nombre"],
                                "ultimoNivel" => $row["boCategoria_ultimoNivel"],
                                "cerrada" => $cerrada,
                                "nueva" => $cerrada,
                                "seleccionada" => $seleccionada,
                                "padreId" => $anyId,
                                "hijos" => $estosHijos,
                                "categoriaId" => $row["boCategoria_id"],
                            );
                        }
                        $anterior = $anyId;
                        $hijos = $ramas;
                    }
                    if (substr($anyId, 0, 1) == "C") {
                        $padreId = 0;
                        $categoriaId = substr($anyId, 1);
                        $sql = $db->mkSQL("SELECT boJerarquia_activo,boJerarquia_categoriaId,boJerarquia_descripcion,boJerarquia_id,boJerarquia_nombre,boJerarquia_padreId,boCategoria_ultimoNivel FROM bojerarquia
                                           INNER JOIN bocategoria ON boCategoria_id = boJerarquia_categoriaId
                                           WHERE boJerarquia_categoriaId=%N AND boJerarquia_padreId=%N AND boJerarquia_activo = 1", $categoriaId, $padreId);
                        $db->query($sql);
                        $ramas = array();
                        while ($row = $db->fetchRow()) {
                            if ($row["boJerarquia_id"] == $anterior) {
                                $estosHijos = $hijos;
                                $cerrada = false;
                            } else {
                                $estosHijos = array();
                                $cerrada = true;
                            }
                            $seleccionada = false;
                            $ramas[] = array(
                                "id" => $row["boJerarquia_id"],
                                "nombre" => $row["boJerarquia_nombre"],
                                "ultimoNivel" => $row["boCategoria_ultimoNivel"],
                                "cerrada" => $cerrada,
                                "nueva" => $cerrada,
                                "seleccionada" => $seleccionada,
                                "padreId" => $anyId,
                                "hijos" => $estosHijos,
                                "categoriaId" => $row["boJerarquia_categoriaId"]
                            );
                        }
                        $anterior = $categoriaId;
                        $hijos = $ramas;
                    }
                    if ($anyId != 0) {
                        $sql = $db->mkSQL("SELECT boJerarquia_activo,boJerarquia_categoriaId,boJerarquia_descripcion,boJerarquia_id,boJerarquia_nombre,boJerarquia_padreId,boCategoria_ultimoNivel FROM bojerarquia
                                           INNER JOIN bocategoria ON boCategoria_id = boJerarquia_categoriaId
                                           WHERE boCategoria_id = %N AND boJerarquia_padreId=%N", $idGrInt, $anyId);
                        $db->query($sql);
                        $ramas = array();
                        while ($row = $db->fetchRow()) {
                            if ($row["boJerarquia_id"] == $anterior) {
                                $estosHijos = $hijos;
                                $cerrada = false;
                            } else {
                                $estosHijos = array();
                                $cerrada = true;
                            }
                            $seleccionada = false;
                            $ramas[] = array(
                                "id" => $row["boJerarquia_id"],
                                "nombre" => $row["boJerarquia_nombre"],
                                "ultimoNivel" => $row["boCategoria_ultimoNivel"],
                                "cerrada" => $cerrada,
                                "nueva" => $cerrada,
                                "seleccionada" => $seleccionada,
                                "padreId" => $anyId,
                                "hijos" => $estosHijos,
                                "categoriaId" => $row["boJerarquia_categoriaId"]
                            );
                        }
                        $anterior = $categoriaId;
                        $hijos = $ramas;
                    }
                }
            }
        }
        return $ramas;
    }
    
    //__Descripción:__ Función que permite registrar suscripciones y envíar información de contacto en caso de estar configurado en interfaz
    //__Inputs:__ 
    //__Outputs:__ 
    function guardarSuscripcion($name, $datos, $contactOnly, $keywords, $ultimaVisitada, $pluginEnvio, $subject){
        global $lang;
        $mdb = new MYMONGODB();
        if(DEVELOPMENT){
            $mdb->crearColeccion("boMensajesContacto",array());
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_fecha"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_email"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_tipo"));
        }
        $email = isset($datos["email"]) ? $datos["email"] : "";
        $nombre = isset($datos["nombre"]) ? $datos["nombre"] : "";
        $apellido = isset($datos["apellido"]) ? $datos["apellido"] : "";
        $identificacion = isset($datos["identificacion"]) ? $datos["identificacion"] : "";
        $fechaNacimiento = isset($datos["fechaNacimiento"]) ? $datos["fechaNacimiento"] : "";
        $telefono = isset($datos["telefono"]) ? $datos["telefono"] : "";
        $celular = isset($datos["celular"]) ? $datos["celular"] : "";
        $empresa = isset($datos["empresa"]) ? $datos["empresa"] : "";
        $direccion = isset($datos["direccion"]) ? $datos["direccion"] : "";
        $mensaje = isset($datos["mensaje"]) ? $datos["mensaje"] : "";
        $seleccion = isset($datos["seleccion"]) ? $datos["seleccion"] : "";
        $jerarquiaId = explode(";", $seleccion);
        if(count($jerarquiaId)> 0){
            require_once("../boletines/classes/class.boCorreo.php");
            require_once("../boletines/classes/class.boCorreoxJerarquia.php");
            require_once("../boletines/classes/class.boJerarquia.php");
            $objCorreo = new boCorreo();
            $p = array(
                "email"=>$email,
                "apellido"=>$apellido,
                "nombre"=>$nombre,
                "identificacion"=>$identificacion,
                "fechanacimientounix"=>$fechaNacimiento,
                "telefono"=>$telefono,
                "celular"=>$celular,
                "empresa"=>$empresa,
                "direccion"=>$direccion
            );
            $idCorreo = $objCorreo->insertUpdate($p);
            if(empty($idCorreo)){
                return $lang["No se pudo registrar el correo de suscripción"];
            }else{
                $objJerarquiaCorreo = new boCorreoxJerarquia();
                $result = $objJerarquiaCorreo->insertaSuscripcion($idCorreo, $jerarquiaId);
                if(empty($result)){
                    return $lang["No se pudieron registrar las categorías de suscripción"];
                }else{
                    //Obtener nombres de categorías seleccionadas
                    $listaCategorias = array();
                    foreach ($jerarquiaId as $id){
                        $objJerarquia = new boJerarquia();
                        $objJerarquia->initFromDB($id);
                        if(!empty($objJerarquia->get("nombre"))){
                            $listaCategorias[] = $objJerarquia->get("nombre");
                        }
                    }
                    $categorias = implode(',', $listaCategorias);
                    unset($datos["seleccion"]);
                    $datamensaje = array(
                        "boMensajeContacto_fecha"=>time(),
                        "boMensajeContacto_email"=>$email,
                        "boMensajeContacto_nombre"=>$nombre,
                        "boMensajeContacto_apellido"=>$apellido,
                        "boMensajeContacto_identificacion"=>$identificacion,
                        "boMensajeContacto_fechaNacimiento"=>$fechaNacimiento,
                        "boMensajeContacto_telefono"=>$telefono,
                        "boMensajeContacto_celular"=>$celular,
                        "boMensajeContacto_empresa"=>$empresa,
                        "boMensajeContacto_direccion"=>$direccion,
                        "boMensajeContacto_mensaje"=>$mensaje,
                        "boMensajeContacto_suscripcion"=>$categorias,
                        "boMensajeContacto_tipo"=>$name
                    );
                    //Registro contacto
                    $mdb->guardar("boMensajesContacto",$datamensaje);
                    //Envíar correo
                    $etiquetas = $this->aplicarKeyWords($keywords);
                    //Obtener título configurado
                    $titulo = $this->obtenerConfiguracionTitulo($name);
                    $result = array();
                    switch ($contactOnly){
                        case "admin":
                            //Obtengo correos configurados
                            $correos = $this->obtenerConfiguracionCorreos($name);
                            //Enviar a admin
                            if(count($correos)){
                                $result = $this->enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,false,$categorias);
                            }
                        break;
                        case "client":
                            //Enviar a cliente
                            if(!empty($email)){
                                $result = $this->enviarCorreoContacto($titulo,$email,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,true,$categorias);
                            }
                        break;
                        case "both":
                            //Enviar ambos
                            if(!empty($email)){
                                $result = $this->enviarCorreoContacto($titulo,$email,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,true,$categorias);
                            }
                            $correos = $this->obtenerConfiguracionCorreos($name);
                            //Enviar a admin
                            if(count($correos)){
                                $result = $this->enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,false,$categorias);
                            }
                        break;
                    }
                    if(is_string($result)){
                        return $result;
                    }else{
                        return true;
                    }
                }
            }
        }else{
            return $lang["Requiere seleccionar al menos una jerarquía para la suscripción"];
        }
        return true;
    }
    
    //__Descripción:__ Función que permite obtener datos de código de verificación
    //__Inputs:__ 
    //__Outputs:__ 
    function obtenerChallenge(){
        require_once ("../functions/sha256.inc.php");
        $challenges = SesionPropia::generateChallenge();
        $oculto = strlen($challenges[1]);
        for ($j = 0; $j < rand(4, 20); $j++) {
            $oculto.=rand(0, 9);
        }
        $oculto.=$challenges[1];
        for ($j = 0; $j < rand(4, 20); $j++) {
            $oculto.=rand(0, 9);
        }
        $letras = $challenges[2];
        $imagen = "../comunes/getImV.php?hs=" . SHA256::hash($challenges[1]) . "&tx=" . $oculto;
        return array(
            "verificador"=>$imagen,
            "challenge"=>$challenges[0],
            "letras"=>$letras,
            "turingV"=>""
        );
    }
    
    //__Descripción:__ Función que permite guardar los datos recolectados en un formulario incluido en la dorectiva <contact-records>
    //__Inputs:__ 
    //__Outputs:__ 
    function guardarRegistro($name, $datos, $keywords, $ultimaVisitada, $pluginEnvio, $subject, $template){
        $mdb = new MYMONGODB();
        if(DEVELOPMENT){
            $mdb->crearColeccion("boMensajesContacto",array());
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_fecha"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_email"));
            $mdb->crearIndice("boMensajesContacto", array("boMensajeContacto_tipo"));
        }
        $email = isset($datos["email"]) ? $datos["email"] : "";
        $nombre = isset($datos["nombre"]) ? $datos["nombre"] : "";
        $apellido = isset($datos["apellido"]) ? $datos["apellido"] : "";
        $identificacion = isset($datos["identificacion"]) ? $datos["identificacion"] : "";
        $fechaNacimiento = isset($datos["fechaNacimiento"]) ? $datos["fechaNacimiento"] : "";
        $telefono = isset($datos["telefono"]) ? $datos["telefono"] : "";
        $celular = isset($datos["celular"]) ? $datos["celular"] : "";
        $empresa = isset($datos["empresa"]) ? $datos["empresa"] : "";
        $direccion = isset($datos["direccion"]) ? $datos["direccion"] : "";
        $ciudad = isset($datos["ciudad"]) ? $datos["ciudad"] : "";
        $mensaje = isset($datos["mensaje"]) ? $datos["mensaje"] : "";
        $datamensaje = array(
            "boMensajeContacto_fecha"=>time(),
            "boMensajeContacto_email"=>$email,
            "boMensajeContacto_nombre"=>$nombre,
            "boMensajeContacto_apellido"=>$apellido,
            "boMensajeContacto_identificacion"=>$identificacion,
            "boMensajeContacto_fechaNacimiento"=>$fechaNacimiento,
            "boMensajeContacto_telefono"=>$telefono,
            "boMensajeContacto_celular"=>$celular,
            "boMensajeContacto_empresa"=>$empresa,
            "boMensajeContacto_direccion"=>$direccion,
            "boMensajeContacto_ciudad"=>$ciudad,
            "boMensajeContacto_mensaje"=>$mensaje,
            "boMensajeContacto_tipo"=>$name
        );
        //Registro contacto
        $mdb->guardar("boMensajesContacto",$datamensaje);
        $result = true;
        //Envíar correo?
        if(!empty($subject) && !empty($pluginEnvio)){
            $etiquetas = $this->aplicarKeyWords($keywords);
            //Obtener título configurado
            $titulo = $this->obtenerConfiguracionTitulo($name);
            //Enviar ambos
            if(!empty($email)){
                $result = $this->enviarCorreoContacto($titulo,$email,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,true,"",$template);
            }
            if(!is_string($result)){
                $correos = $this->obtenerConfiguracionCorreos($name);
                //Enviar a admin
                if(count($correos)){
                    $result = $this->enviarCorreoContacto($titulo,$correos,$ultimaVisitada,$etiquetas,$datos,$pluginEnvio,$subject,false,"",$template);
                }
            }
        }
        if(is_string($result)){
            return $result;
        }else{
            return true;
        }
    }
}
?>
<? //_FIN_DE_ARCHIVO  ?>