<?

require_once("../comunes/classes/class.clase.php");

class boCorreo extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bocorreo", "boCorreo_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite agregar al contenido HTML una sección de de-suscripción
    //__Inputs:__ $contenido:string contenido HTML al cual se adjuntará la sección de desuscripción
    //__Outputs:__ $seccion:string el mismo contenido con la nueva sección
    function agregarSeccionDesuscripcion($contenido) {
        $seccion = "";
        if ($contenido != "") {
            if (strpos($contenido, "__stopNewsletter.php__") !== false) {
                //Si hay sección de desuscripción
                $seccion = $contenido;
            } else {
                //No hay sección de desuscripción
                $html = "<br><br><br><br>
                         <div class='PARRAFOMAIN' style='font-size:10px;width:300px; padding:5px; background-color:#e4e6e5; color:#4F4F4F'>Nota: Para visualizar correctamente las imágenes de este boletín informativo necesita estar conectado a internet. Si no desea continuar recibiendo este boletín haga click aquí:&nbsp;&nbsp;
                         <a href='" . BASEURL . "__stopNewsletter.php__'>Des-suscribir</a>
                         </div>";
                $partes = explode("</body>", $contenido);
                for ($i = 0; $i < (count($partes) - 1); $i++) {
                    $seccion .= $partes[$i];
                }
                $seccion .= $html;
                $seccion .= $partes[count($partes) - 1];
            }
        }
        return $seccion;
    }
    
    //__Descripción:__ Función que permite enviar un boletín conociendo la lista de correos a enviar y el id de la tabla bocontenido
    //                 El envío directo no genera registro de envío por bitácora, por lo que el envío en inmediato y a todos los correos registrados en la lista
    //__Inputs:__ $correos:array lista de correos a enviar un boletín
    //            $boletínId:int id de tabla bocontenido
    //__Outputs:__ string/true string:error, true:ok
    function envioManual($correos, $boletinId) {
        if(empty($boletinId)){
            return "Boletín inválido: ".$boletinId;
        }else{
            require_once("../boletines/classes/class.boContenido.php");
            $objConten = new boContenido();
            $objConten->initFromDB($boletinId);
            $pg = $objConten->getPlugin();
            if(is_string($pg)){
                return $pg;
            }else{
                $tipo = $objConten->get("tipo");
                if($tipo == "correo"){
                    if(method_exists($pg, "envioManual")){
                        return $pg->envioManual($objConten,$correos);
                    }else{
                        return "No se pudo encontrar la función 'envioManual' en el plugin de envío";
                    }
                }else if($tipo == "facebook"){
                    return "Tipo de envio a Facebook no admitido";
                }else if($tipo == "sms"){
                    return "Tipo de envio SMS no admitido";
                }
            }
        }
    }

    //__Descripción:__ Funcion que permite envíar un boletín a una array de datos de un cliente registrado con el id de tabla bocontenido
    //                 Éste envío es directo no genera registro de envío por bitácora, por lo que el envío en inmediato y a todos los correos registrados en la lista
    //__Inputs:__ $correos:array lista de id y correo de tabla bocorreos con prefijo bco_
    //            $boletinId:int id de tabla bocontenido
    //__Outputs:__ string/true string:error,true:ok
    function envioListaBoletin($correos, $boletinId) {
        if(empty($boletinId)){
            return "Boletín inválido: ".$boletinId;
        }else{
            require_once("../boletines/classes/class.boContenido.php");
            $objConten = new boContenido();
            $objConten->initFromDB($boletinId);
            $pg = $objConten->getPlugin();
            if(is_string($pg)){
                return $pg;
            }else{
                $tipo = $objConten->get("tipo");
                if($tipo == "correo"){
                    if(method_exists($pg, "envioManual")){
                        return $pg->envioManual($objConten,$correos);
                    }else{
                        return "No se pudo encontrar la función 'envioManual' en el plugin de envío";
                    }
                }else if($tipo == "facebook"){
                    if(method_exists($pg, "envioManualFacebook")){
                        return $pg->envioManualFacebook($objConten,$correos);
                    }else{
                        return "No se pudo encontrar la función 'envioManualFacebook' en el plugin de envío";
                    }
                }else if($tipo == "sms"){
                    return "Tipo de envio SMS no admitido";
                }
            }
        }
    }

    //__Descripción:__ Funcion para crear una lista de envío en base a categorías marcadas
    //                 Éste envío genera un registro de envío por bitácora, por lo que el envío lo realiza el plugin actualizaBitacoraBoletinesLote.php
    //__Inputs:__ $boletinId:int id de tabla bocontenido
    //            $lista:array lista de jerarquías marcadas en el módulo
    //            $fechaProgramada:int fecha de envío programada
    //            $horaProgramada:string hora de envío programado
    //            $minutoProgramado:string minuto de envío programado
    //__Outputs:__ string/true string:error,true:ok
    function envioBoletinBitacora($boletinId, $lista, $fechaProgramada = -1, $horaProgramada = -1, $minutoProgramado = -1) {
        if(empty($boletinId)){
            return "Boletín inválido: ".$boletinId;
        }else{
            require_once("../boletines/classes/class.boContenido.php");
            $objConten = new boContenido();
            $objConten->initFromDB($boletinId);
            $pg = $objConten->getPlugin();
            if(is_string($pg)){
                return $pg;
            }else{
                $tipo = $objConten->get("tipo");
                if($tipo == "correo"){
                    if(method_exists($pg, "envioBoletinBitacora")){
                        return $pg->envioBoletinBitacora($objConten,$lista,$fechaProgramada,$horaProgramada,$minutoProgramado);
                    }else{
                        return "No se pudo encontrar la función 'envioBoletinBitacora' en el plugin de envío";
                    }
                }else if($tipo == "facebook"){
                    if(method_exists($pg, "envioFacebookBitacora")){
                        return $pg->envioFacebookBitacora($objConten,$lista,$fechaProgramada,$horaProgramada,$minutoProgramado);
                    }else{
                        return "No se pudo encontrar la función 'envioFacebookBitacora' en el plugin de envío";
                    }
                }else if($tipo == "sms"){
                    return "Tipo de envio SMS no admitido";
                }
            }
        }
    }

    //__Descripción:__ Funcion para generar un envío en base a un archivo
    //__Inputs:__ $boletinId:int id de tabla bocontenido
    //            $archivo:string nombre de archivo a enviar
    //            $fechaProgramada:int fecha de envío programada
    //            $horaProgramada:string hora de envío programado
    //            $minutoProgramado:string minuto de envío programado
    //__Outputs:__ string/true string:error,true:ok
    function envioBoletinArchivo($boletinId, $archivo, $fechaProgramada = -1, $horaProgramada = -1, $minutoProgramado = -1){
        if(empty($boletinId)){
            return "Boletín inválido: ".$boletinId;
        }else{
            if(empty($archivo)){
                return "Archivo inválido: ".$archivo;
            }
            require_once("../boletines/classes/class.boContenido.php");
            $objConten = new boContenido();
            $objConten->initFromDB($boletinId);
            $pg = $objConten->getPlugin();
            if(is_string($pg)){
                return $pg;
            }else{
                $tipo = $objConten->get("tipo");
                if(method_exists($pg, "envioBoletinArchivo")){
                    return $pg->envioBoletinArchivo($tipo,$objConten,$archivo,$fechaProgramada,$horaProgramada,$minutoProgramado);
                }else{
                    return "No se pudo encontrar la función 'envioBoletinArchivo' en el plugin de envío";
                }
            }
        }
    }
    
    //__Descripción:__ Funcion que devuelve listado de datos de correo en base a listado de categorias marcadas
    //__Inputs:__ $marcados:string lista de selección de categorías/jerarquías seleccionadas en interfaz para el envío
    //__Outputs:__ $filtrados:array lista de correos que cumplen con todas las condiciones de selección
    function obtenerCorreosPorMarca($marcados) {
        $db = new MYSQLDB();
        $asignaciones = [];
        $jerarquia = [];
        $ItemsLst = [];
        $filtrados = [];
        //1. Obtener toda la jerarquía de envíos actual
        $sql = $db->mkSQL("SELECT * FROM bojerarquia");
        if ($db->query($sql) > 0) {
            while ($row = $db->fetchRow()) {
                $jerarquia[] = $row;
            }
        }
        //2.Recorre lista de selección obteniendo recursividad de selección
        $catSelec = 0;
        $catEncontradas = array();
        foreach ($marcados as $item) {
            if ($item <> "") {
                $valores = explode("|", $item);
                $id = $valores[0];
                $padreId = str_replace('C', '', $valores[1]);
                $categoriaId = $valores[2];
                $catEncontradas[] = $categoriaId;
                $ItemsLst[$categoriaId][] = $id;
                $ids = array();
                $ids = $this->obtieneIdRecursivos($id, $jerarquia, $ids);
                foreach ($ids as $i) {
                    $ItemsLst[$categoriaId][] = $i;
                }
            }
        }
        $catEncontradas = array_unique($catEncontradas);
        $catSelec = count($catEncontradas);
        //3.Obtiene listado de correos por categoría
        if (count($ItemsLst) > 0) {
            foreach ($ItemsLst as $catId => $Items) {
                //obtiene id seleccionados por categoría
                $itemstr = "";
                $Items = array_unique($Items);
                foreach ($Items as $item) {
                    if (strpos($item, "C") !== false) {
                        //Es una categoría, no se busca
                    } else {
                        $itemstr .= $item . ",";
                    }
                }
                $itemstr = rtrim($itemstr, ",");
                if ($itemstr != "") {
                    $sql = "SELECT
                                        bocorreo.*,
                                        boJerarquia_id
                                FROM bocategoria
                                    INNER JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId
                                    INNER JOIN bocorreoxjerarquia ON boJerarquia_id = boCorreoxJerarquia_jerarquiaId
                                    INNER JOIN bocorreo ON boCorreo_id = boCorreoxJerarquia_correoId
                                WHERE boJerarquia_activo = 1 AND boCorreo_activo = 1 AND boCategoria_id = " . $catId . " AND boJerarquia_id IN (" . $itemstr . ")
                                GROUP BY boCorreo_email";
                    if ($db->query($sql) > 0) {
                        while ($row = $db->fetchRow()) {
                            $camposLimpios = array();
                            foreach ($row as $campo => $valor) {
                                $campo = str_replace("boCorreo_", "", $campo);
                                $camposLimpios[$campo] = $valor;
                            }
                            $asignaciones[$row["boCorreo_email"]][] = $camposLimpios;
                        }
                    }
                }
            }
            foreach ($asignaciones as $datos) {
                if (count($datos) == $catSelec && isset($datos[0])) {
                    $filtrados[] = $datos[0];
                }
            }
        }
        return $filtrados;
    }

    //__Descripción:__ Función que permite obtener lista de correos en base a una lista de marcados con parámetros de paginación y filtrado
    //__Inputs:__ $lista:string lista de marcados
    //            $pagina:int número de página
    //            $filtro:string valor de búsqueda
    //__Outputs:__ $correos:array lista de correos encontrados
    function obtieneListaCorreos($lista, $pagina = 0, $filtro = "") {
        //__Descripcion__: obtiene intersección de correos seleccionados para envío
        $asignaciones = array();
        $jerarquia = array();
        $ItemsLst = array();
        $listado = array();
        $correos = array("registros" => 0, "datos" => array(), "paginas" => 0);
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bojerarquia");
        if ($db->query($sql) > 0) {
            //1. Obtine Jerarquia
            while ($row = $db->fetchRow()) {
                $jerarquia[] = $row;
            }
            //2.Recorre lista de selección obteniendo recursividad de selección
            $catSelec = 0;
            $catEncontradas = array();
            foreach ($lista as $item) {
                if ($item <> "") {
                    $valores = explode("|", $item);
                    $id = $valores[0];
                    $padreId = str_replace('C', '', $valores[1]);
                    $categoriaId = $valores[2];
                    $catEncontradas[] = $categoriaId;
                    $ItemsLst[$categoriaId][] = $id;
                    $ids = array();
                    $ids = $this->obtieneIdRecursivos($id, $jerarquia, $ids);
                    foreach ($ids as $i) {
                        $ItemsLst[$categoriaId][] = $i;
                    }
                }
            }
            $catEncontradas = array_unique($catEncontradas);
            $catSelec = count($catEncontradas);
            if (count($ItemsLst) > 0) {
                //2.Obtiene listado de correos por categoría
                foreach ($ItemsLst as $catId => $Items) {
                    //obtiene id seleccionados por categoría
                    $itemstr = "";
                    $Items = array_unique($Items);
                    foreach ($Items as $item) {
                        if (strpos($item, "C") !== false) {
                            //Es una categoría, no se busca
                        } else {
                            $itemstr .= $item . ",";
                        }
                    }
                    $itemstr = rtrim($itemstr, ",");
                    if ($itemstr != "") {
                        $sql = "SELECT     boCorreo_id
                                                ,boCorreo_nombre
                                                ,boCorreo_identificacion
                                                ,boCorreo_email
                                                ,boJerarquia_id
                                            FROM `bocategoria`
                                                INNER JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId
                                                INNER JOIN bocorreoxjerarquia ON boJerarquia_id = boCorreoxJerarquia_jerarquiaId
                                                INNER JOIN bocorreo ON boCorreo_id = boCorreoxJerarquia_correoId
                                            WHERE boJerarquia_activo = 1 AND boCorreo_activo = 1 AND boCategoria_id = " . $catId . " AND boJerarquia_id IN (" . $itemstr . ")
                                            GROUP BY boCorreo_email
                                     ";
                        if ($db->query($sql) > 0) {
                            while ($row = $db->fetchRow()) {
                                $asignaciones[$row["boCorreo_email"]][] = array("bjr_id" => $row["boJerarquia_id"], "bco_id" => $row["boCorreo_id"], "bco_email" => $row["boCorreo_email"], "bco_nombre" => $row["boCorreo_nombre"], "bco_identificacion" => $row["boCorreo_identificacion"]);
                            }
                        }
                    }
                }
                foreach ($asignaciones as $datos) {
                    if (count($datos) == $catSelec && isset($datos[0])) {
                        $listado[] = $datos[0];
                    }
                }
                $reporte = array();
                $listadoFiltrado = array();
                if ($filtro != "") {
                    foreach ($listado as $value) {
                        if (strpos($value["bco_email"], $filtro) !== false) {
                            $listadoFiltrado[] = $value;
                        }
                    }
                    $listado = $listadoFiltrado;
                }
                //paginación
                $Boletines = getConf("Boletines Electronicos");
                $paginaEnvio = 10;
                if (isset($Boletines["paginacionEnvio"]) && isset($Boletines["paginacionEnvio"][0])) {
                    $paginaEnvio = $Boletines["paginacionEnvio"][0];
                }
                $inicio = 0;
                $fin = 0;
                if ($pagina == 0) {
                    $inicio = $pagina;
                    $fin = $paginaEnvio;
                } else {
                    $inicio = $pagina * $paginaEnvio;
                    $fin = $inicio + $paginaEnvio;
                }
                //Datos
                for ($i = $inicio; $i < $fin; $i++) {
                    if (isset($listado[$i])) {
                        $reporte[] = $listado[$i];
                    }
                }
                //resultados
                $correos["datos"] = $reporte;
                $correos["registros"] = count($listado);
                $paginas = $correos["registros"] / $paginaEnvio;
                $correos["paginas"] = ceil($paginas);
            }
        }
        return $correos;
    }

    //__Descripción:__ Función que permite obtener ids de jerarquia de manera recursiva
    //__Inputs:__ $padreId:int padre de tabla bojerarquia
    //            $jerarquia:array lista de todas las jerarquía
    //            $ids:array id recolectados en vueltas recursivas
    //__Outputs:__ $ids:array id recolectados en vueltas recursivas
    function obtieneIdRecursivos($padreId, $jerarquia, $ids) {
        foreach ($jerarquia as $jer) {
            //Obtengo asociaciones tipo padre
            if ($jer["boJerarquia_id"] == $padreId) {
                //Obtiengo campos de control asociados a ubicacion directametne
                $ids[] = $jer["boJerarquia_id"];
            } else if ($jer["boJerarquia_padreId"] == $padreId) {
                //Recolecta hijos
                $ids = $this->obtieneIdRecursivos($jer["boJerarquia_id"], $jerarquia, $ids);
            }
        }
        return $ids;
    }

    //__Descripción:__ Función que permite insertar o actualizar datos de correo verificando su existencia por correo
    //__Inputs:__ $datos:array lista de datos a insertar: email,apellido,nombre,identificacion.fechanacimientounix,telefono,celular,empresa,direccion
    //__Outputs:__ $idCorreo:int id de bocorreo
    function insertUpdate($datos) {
        $db = new MYSQLDB();
        $correo = expect_email($datos["email"]);
        $idCorreo = 0;
        if ($correo != "") {
            $apellidos = "";
            if (isset($datos["apellido"])) {
                $apellidos = $datos["apellido"];
            }
            $nombres = "";
            if (isset($datos["nombre"])) {
                $nombres = $datos["nombre"] . " " . $apellidos;
            }
            $identificacion = "";
            if (isset($datos["identificacion"])) {
                $identificacion = $datos["identificacion"];
            }
            $fechaNacimiento = 0;
            if (isset($datos["fechanacimientounix"])) {
                $fechaNacimiento = $datos["fechanacimientounix"];
            }
            $telefono = "";
            if (isset($datos["telefono"])) {
                $telefono = $datos["telefono"];
            }
            $celular = "";
            if (isset($datos["celular"])) {
                $celular = $datos["celular"];
            }
            $empresa = "";
            if (isset($datos["empresa"])) {
                $empresa = $datos["empresa"];
            }
            $direccion = "";
            if (isset($datos["direccion"])) {
                $direccion = $datos["direccion"];
            }
            $countCorreo = $db->query($db->mkSQL("SELECT boCorreo_id FROM bocorreo WHERE boCorreo_email = %Q", $correo));

            if ($countCorreo > 0) {
                $row = $db->fetchRow();
                $idCorreo = $row["boCorreo_id"];
                if ($nombres != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_nombre = %Q WHERE boCorreo_id = %N", $nombres, $idCorreo));
                }
                if ($identificacion != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_identificacion=%Q  WHERE boCorreo_id = %N", $identificacion, $idCorreo));
                }
                if ($fechaNacimiento > 0) {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_fechaNacimiento=%N  WHERE boCorreo_id = %N", $fechaNacimiento, $idCorreo));
                }
                if ($telefono != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_telefono=%Q  WHERE boCorreo_id = %N", $telefono, $idCorreo));
                }
                if ($celular != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_celular=%Q  WHERE boCorreo_id = %N", $celular, $idCorreo));
                }
                if ($empresa != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_empresa=%Q  WHERE boCorreo_id = %N", $empresa, $idCorreo));
                }
                if ($direccion != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_direccion=%Q  WHERE boCorreo_id = %N", $direccion, $idCorreo));
                }
            }
            //Si no existe, inserta nuevo registro
            if ($idCorreo == 0) {
                $idCorreo = $db->query($db->mkSQL("INSERT INTO bocorreo (boCorreo_nombre,boCorreo_email,boCorreo_identificacion,boCorreo_fechaCreacion,boCorreo_activo,boCorreo_fechaNacimiento,boCorreo_telefono,boCorreo_celular,boCorreo_empresa,boCorreo_direccion) VALUES (%Q,%Q,%Q,%N,%N,%N,%Q,%Q,%Q,%Q)", $nombres, $correo, $identificacion, time(), 1, $fechaNacimiento, $telefono, $celular, $empresa, $direccion));
            }
        }
        return $idCorreo;
    }

    //__Descripción:__ Función que permite actualizar los datos de un correo verificando por id de correo
    //__Inputs:__ $datos:array lista de datos a insertar: email,apellido,nombre,identificacion.fechanacimientounix,telefono,celular,empresa,direccion
    //            $idCorreo:int id de correo a modificar
    //__Outputs:__ $idCorreo:int id de bocorreo
    function UpdateEmailPorId($datos, $idCorreo) {
        $correo = expect_email($datos["email"]);
        if ($correo != "") {
            $apellidos = "";
            if (isset($datos["apellido"])) {
                $apellidos = $datos["apellido"];
            }
            $nombres = "";
            if (isset($datos["nombre"])) {
                $nombres = $datos["nombre"] . " " . $apellidos;
            }
            $identificacion = "";
            if (isset($datos["identificacion"])) {
                $identificacion = $datos["identificacion"];
            }
            $fechaNacimiento = 0;
            if (isset($datos["fechanacimientounix"])) {
                $fechaNacimiento = $datos["fechanacimientounix"];
            }
            $telefono = "";
            if (isset($datos["telefono"])) {
                $telefono = $datos["telefono"];
            }
            $celular = "";
            if (isset($datos["celular"])) {
                $celular = $datos["celular"];
            }
            $empresa = "";
            if (isset($datos["empresa"])) {
                $empresa = $datos["empresa"];
            }
            $direccion = "";
            if (isset($datos["direccion"])) {
                $direccion = $datos["direccion"];
            }
            $db = new MYSQLDB();
            $countCorreo = $db->query($db->mkSQL("SELECT boCorreo_email FROM bocorreo WHERE boCorreo_id = %N", $idCorreo));

            if ($countCorreo > 0) {
                $row = $db->fetchRow();
                //Actualiza campos de suscripción
                $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_email = %Q WHERE boCorreo_id = %N", $correo, $idCorreo));
                if ($nombres != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_nombre = %Q WHERE boCorreo_id = %N", $nombres, $idCorreo));
                }
                if ($identificacion != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_identificacion=%Q  WHERE boCorreo_id = %N", $identificacion, $idCorreo));
                }
                if ($fechaNacimiento > 0) {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_fechaNacimiento=%N  WHERE boCorreo_id = %N", $fechaNacimiento, $idCorreo));
                }
                if ($telefono != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_telefono=%Q  WHERE boCorreo_id = %N", $telefono, $idCorreo));
                }
                if ($celular != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_celular=%Q  WHERE boCorreo_id = %N", $celular, $idCorreo));
                }
                if ($empresa != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_empresa=%Q  WHERE boCorreo_id = %N", $empresa, $idCorreo));
                }
                if ($direccion != '') {
                    $db->query($db->mkSQL("UPDATE bocorreo SET boCorreo_direccion=%Q  WHERE boCorreo_id = %N", $direccion, $idCorreo));
                }
            }
        }
        return $idCorreo;
    }

    //__Descripción:__ Función que permite obtener el id de tabla bocorreo en base a un correo
    //__Inputs:__ $correo:string correo electrónico a buscar
    //__Outputs:__ $idCorreo:int id de tabla bocorreo
    function getEmailId($correo) {
        $db = new MYSQLDB();
        $idCorreo = 0;
        $sql = $db->mkSQL("SELECT * FROM bocorreo WHERE boCorreo_email = %Q", $correo);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $idCorreo = $row["boCorreo_id"];
        }
        return $idCorreo;
    }

    //__Descripción:__ Función que permite eliminar toda la información relacionada al correo y a sus suscripciones
    //__Inputs:__ $id:int id de tabla bocorreo
    //__Outputs:__ 
    function eliminarCorreoSuscripciones($id) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bocorreo WHERE boCorreo_id=%N", $id);
        if ($db->query($sql)) {
            $sql = $db->mkSQL("SELECT * FROM bocorreoxjerarquia WHERE boCorreoxJerarquia_correoId=%N", $id);
            if ($db->query($sql)) {
                $sql = $db->mkSQL("DELETE FROM bocorreoxjerarquia WHERE boCorreoxJerarquia_correoId=%N", $id);
                $db->query($sql);
            }
            $sql = $db->mkSQL("DELETE FROM bocorreo WHERE boCorreo_id=%N", $id);
            $db->query($sql);
        }
    }

    //__Descripción:__ Permite enviar un boletin preestablecido a un grupo de suscriptores para mailing
    //__Inputs:__ $estructura:string Estructrura de categoria y jerarquias con la forma: GRUPO|CATEGORIA1|CATEGORIA2|BOLETIN (CNT|2017|Julio|Tramo30-60) donde el nombre de ultima jerarquia debe coincidir con nombre de boletin
    //            $estricto:boolean TRUE: Si se desea que la estructura $estructura deba existir previamente (omite su creacion en caso de inexistencia) | FALSE: Si desea que se creen categoria y jerarquias indicadas en $estructura en caso de no existir
    //            $listado:array Lista de receptores de mails con formato: [0] => ["email" => $correo, "nombre" => $nombres, "identificacion" => $identificacion, "fechanacimientounix" => $fechaNacimiento, "telefono" => $telefono, "celular" => $celular, "empresa"=>$empresa, "direccion"=>$direccion]
    //__Outputs:__ $ans:array Array con dos posiciones op y data, OP=>TRUE: indica procedente en el registro efectivo para envio | OP=>FALSE: no se completo el registro, DATA: mismo $listado con key "suscrito" adicional en cada elemento para indica suscripcion exitosa
    function enviarCorreoIntegrado($estructura, $estricto = true, $listado) {
        require_once("../boletines/classes/class.boCategoria.php");
        require_once("../boletines/classes/class.boJerarquia.php");
        require_once("../boletines/classes/class.boCorreoxJerarquia.php");
        require_once("../boletines/classes/class.boCorreo.php");
        require_once("../boletines/classes/class.boContenido.php");
        require_once("../boletines/classes/class.boBitacora.php");

        //Default answer
        $ans = ['op' => false, 'data' => []];

        if (count($listado) > 0) {
            $chain_parts = $original_chain_parts = explode("|", $estructura);
            if (count($chain_parts) > 0) {
                $category = $chain_parts[0];
                //Obtengo ID de Categoria
                $categoria = new boCategoria();
                $categoriaId = $categoria->getIdPorNombre($category);
                if ($categoriaId == 0) {
                    $categoriaUltNiv = 1;
                    if (count($chain_parts) > 1) {
                        $categoriaUltNiv = 0;
                    }
                    //Crea la categoria en caso que no exista previamente
                    $categoriaId = $categoria->insertaBoCategoria($category, $categoriaUltNiv);
                }
                //Gestiona jerarquias
                unset($chain_parts[0]);
                $chain_parts = array_values($chain_parts);
                if (count($chain_parts) > 0) {
                    $jerarquias = new boJerarquia();
                    //1. Creacion de jerarquias faltantes
                    $ultimaJerarquiaId = $jerarquias->gestionaArbol($chain_parts, $categoriaId, $estricto);
                    if ($ultimaJerarquiaId > 0) {
                        //Se verifico y recreo la jerarquia en DB
                        if (count($original_chain_parts) > 1) {
                            //El nombre del boletin debera ser el mismo del ultimo grupo
                            $boletinName = trim($original_chain_parts[count($original_chain_parts) - 1]);
                            $content = new boContenido();
                            $boletinId = $content->obtenerBoletinPorNombre($boletinName);
                            if ($boletinId != 0) {
                                //2. Suscripcion de personas
                                $almost_one = false;
                                foreach ($listado as &$list) {
                                    //Registra los correos que se van a enviar en la tabla boCorreo, si existen los actualiza caso contrario inserta
                                    $idCorreo = $this->insertUpdate($list);
                                    if ($idCorreo != 0) {
                                        $suscribe = new boCorreoxJerarquia();
                                        //Suscribe los correos en la tabla boCorreoxJerarquia
                                        $idCorreoJeraquia = 0;
                                        $idCorreoJeraquia = $suscribe->guardaSuscripcion($idCorreo, $ultimaJerarquiaId);
                                        if ($idCorreoJeraquia != 0) {
                                            //Se agrega indice nuevo, para actualizacion de estados en collecciones locales
                                            $list['suscrito'] = true;
                                            $almost_one = true;
                                        } else {
                                            //Se agrega indice nuevo, para actualizacion de estados en collecciones locales
                                            $list['suscrito'] = false;
                                            trigger_error("Notice: correo " . $list['email'] . " ya fue suscrito en la categoría ID: " . $ultimaJerarquiaId);
                                        }
                                    }
                                }
                                //3. Registro en bitacora para correspondiente envio
                                if ($almost_one) {
                                    $bitacora = new boBitacora();
                                    $sentBit = $bitacora->registrarBitacora($ultimaJerarquiaId, $boletinId);
                                    if ($sentBit) {
                                        $ans = ['op' => true, 'data' => $list]; //ENVIO EFECTIVO
                                    }
                                }
                            } else {
                                trigger_error("Error: boletin inexistente");
                            }
                        }
                    }
                }
            }
        } else {
            trigger_error("Error: Listado vacio, se cancelan todos los procesos con Boletines");
        }
        return $ans;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bocorreo",
                        "prefix" => "boCorreo_",
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
                                "name" => "email",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "identificacion",
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
                                "name" => "activo",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaNacimiento",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "telefono",
                                "type" => "varchar",
                                "size" => "10",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "celular",
                                "type" => "varchar",
                                "size" => "10",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "empresa",
                                "type" => "varchar",
                                "size" => "250",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "direccion",
                                "type" => "varchar",
                                "size" => "300",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                        )
                    )
            );
        }
    }

}
?>
<? //_FIN_DE_ARCHIVO  ?>
