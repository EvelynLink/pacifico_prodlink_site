<?
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaAngular.php";
require_once("../dct/classes/class.dctUploaderAngular.php");
if(!isset($_REQUEST["act"])){
	exit;
}

ini_set("max_execution_time", "19000");
ini_set("memory_limit", "10000M");

$act=expect_pure_alphanumeric($_REQUEST["act"]);
$json=array();
$limpiar=array();
$conf = getConf("Boletines Electronicos");
$formatoRedondeo = isset($conf["Numero decimales"][0]) ? $conf["Numero decimales"][0] : 2;
$formatoMiles = isset($conf["Separador miles"][0]) ? $conf["Separador miles"][0] : "";
$formatoDecimales = isset($conf["Separador decimales"][0]) ? $conf["Separador decimales"][0] : ".";
$enviosAlertas = getConf("Email alerts");
$enzymaFirma='GBvKJkkNeK7vOu4vERS9wmAt/YUc7oIk.IZPiWDiOKr1zuukOLcfr2N8Cn8Ld2fVtLr1AcsoZFz/n6URX3Iqq0WJVCkAnOHwmHdMcG8NCto/Suscripcion masiva boletines|1|10485760|0|text/plain|text/anytext|application/csv|application/excel|application/vnd.ms-excel|text/anytext|text/csv|text/comma-separated-values|application/vnd.msexcel';
$familiaFirma= "Suscripcion masiva boletines";
$firma = new dctUploaderAngular();
$firma->setEnzymaActiva($enzymaFirma);
$firma->setFamilia($familiaFirma);
$firma->setEncript("No");
$enzymaFirmaRs='iKR1YLq6yxMD0hnJ1AT/8xX2GzJR51IY5TCxq7eBzRhG2aIlm1MGW2MbKb1H0luHdyYuBUvh86ihgf540gnfO0pkzntpncnZBU95vT.tUNI.Imagenes Boletines Redes Sociales|1|10485760|0|image/jpeg|image/pjpeg|image/png';
$familiaFirmaRs= "Imagenes Boletines Redes Sociales";
$firmaRs = new dctUploaderAngular();
$firmaRs->setEnzymaActiva($enzymaFirmaRs);
$firmaRs->setFamilia($familiaFirmaRs);
$firmaRs->setEncript("No");
switch($act){
    //Suscipción
    case "jSuscripcionEnvio":
        $d = jsonStart();
        $subaccion = expect_pure_alpha($d["subaccion"]);
        $db = new MYSQLDB();
        switch ($subaccion) {
            case "cargaDatos":
                require_once ("../boletines/classes/class.boJerarquia.php");
                $jerarquia = new boJerarquia();
		$ubicacionBase=expect_pure_alphanumeric($d["ubicacionBase"]);
                $categoriaId = $ubicacionBase;
                isset($d["abrir"]) ? $abrir = expect_integer($d["abrir"]) : $abrir = 0;
                $abrirOriginal = $abrir;
                $genealogia = array();
                //Funcion que obtiene la Genealogia
                $genealogia = $jerarquia->obtenerGenealogia($categoriaId, $abrir, $ubicacionBase);
                //Funcion que obtiene el arbol jerarquico
                //$ramas = $jerarquia->obtenerArbolBojerarquia($categoriaId, $genealogia, $abrirOriginal);
		$hijos=array();
		$anterior=-1;
		//$genealogia[]=$ubicacionBase;
		foreach($genealogia as $anyId){
                    if($anyId==0){
                        $db->query($db->mkSQL("SELECT * FROM bocategoria
						ORDER BY boCategoria_nombre"));
                        $ramas=array();
                        while($row=$db->fetchRow()){
                                if($row["boCategoria_id"]=="C".$anterior){
                                        $estosHijos=$hijos;
                                        $cerrada=false;
                                }else{
                                        $estosHijos=array();
                                        $cerrada=true;
                                }
                                $seleccionada=false;
                                $ramas[]=array(
                                        "id"=>"C".$row["boCategoria_id"],
                                        "nombre"=>$row["boCategoria_nombre"],
                                        "ultimoNivel"=>$row["boCategoria_ultimoNivel"],
                                        "cerrada"=>$cerrada,
                                        "nueva"=>$cerrada,
                                        "seleccionada"=>$seleccionada,
                                        "padreId"=>$anyId,
                                        "hijos"=>$estosHijos,
                                        "categoriaId"=>$row["boCategoria_id"]
                                );
                        }
                        $anterior=$anyId;
                        $hijos=$ramas;
                    }
                    if(substr($anyId,0,1)=="C"){
                        $padreId = 0;
                        $categoriaId = substr($anyId,1);
                        $sql = $db->mkSQL("SELECT boJerarquia_activo,boJerarquia_categoriaId,boJerarquia_descripcion,boJerarquia_id,boJerarquia_nombre,boJerarquia_padreId,boCategoria_ultimoNivel FROM bojerarquia
                                           INNER JOIN bocategoria ON boCategoria_id = boJerarquia_categoriaId
                                           WHERE boJerarquia_categoriaId=%N AND boJerarquia_padreId=%N AND boJerarquia_activo = 1",$categoriaId, $padreId);
                        $db->query($sql);
                        $ramas=array();
                        while($row=$db->fetchRow()){
                                if($row["boJerarquia_id"]==$anterior){
                                        $estosHijos=$hijos;
                                        $cerrada=false;
                                }else{
                                        $estosHijos=array();
                                        $cerrada=true;
                                }
                                $seleccionada=false;
                                $ramas[]=array(
                                        "id"=>$row["boJerarquia_id"],
                                        "nombre"=>$row["boJerarquia_nombre"],
                                        "ultimoNivel"=>$row["boCategoria_ultimoNivel"],
                                        "cerrada"=>$cerrada,
                                        "nueva"=>$cerrada,
                                        "seleccionada"=>$seleccionada,
                                        "padreId"=>$anyId,
                                        "hijos"=>$estosHijos,
                                        "categoriaId"=>$row["boJerarquia_categoriaId"]
                                );
                        }
                        $anterior=$categoriaId;
                        $hijos=$ramas;
                    }
                    if($anyId != 0){
                        $sql = $db->mkSQL("SELECT boJerarquia_activo,boJerarquia_categoriaId,boJerarquia_descripcion,boJerarquia_id,boJerarquia_nombre,boJerarquia_padreId,boCategoria_ultimoNivel FROM bojerarquia
                                           INNER JOIN bocategoria ON boCategoria_id = boJerarquia_categoriaId
                                           WHERE boJerarquia_padreId=%N", $anyId);
                        $db->query($sql);
                        $ramas=array();
                        while($row=$db->fetchRow()){
                                if($row["boJerarquia_id"]==$anterior){
                                        $estosHijos=$hijos;
                                        $cerrada=false;
                                }else{
                                        $estosHijos=array();
                                        $cerrada=true;
                                }
                                $seleccionada=false;
                                $ramas[]=array(
                                        "id"=>$row["boJerarquia_id"],
                                        "nombre"=>$row["boJerarquia_nombre"],
                                        "ultimoNivel"=>$row["boCategoria_ultimoNivel"],
                                        "cerrada"=>$cerrada,
                                        "nueva"=>$cerrada,
                                        "seleccionada"=>$seleccionada,
                                        "padreId"=>$anyId,
                                        "hijos"=>$estosHijos,
                                        "categoriaId"=>$row["boJerarquia_categoriaId"]
                                );
                        }
                        $anterior=$categoriaId;
                        $hijos=$ramas;
                    }
		}
		$json["ramas"]=$ramas;
                break;
            case "mueve":
                //quién es la ubicacion que se mueve? 
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                //bajo qué padre se mueve?
                $destinoPadre = expect_integer($d["destinoPadre"]);
                //a qué posición de los hijos llega?
                $destinoLugar = expect_integer($d["destinoLugar"]); //en este caso no importa la posición
                //aquí decidimos si el movimiento es permitido o no
                // ------ en este caso el padre debe ser mayor que 0 ------
                if ($destinoPadre <= 0) {
                    $json["errores"] = "La ubicación debe estar por debajo de la matriz";
                } else {
                    require_once ("../boletines/classes/class.boJerarquia.php");
                    $jerarquia = new boJerarquia();
                    $resultado = $jerarquia->moverRama($destinoPadre, $ubicacionBase);
                    if ($resultado == 1) {
                        $json["resultado"] = "Exito";
                    } else {
                        $json["errores"] = "No se pudo mover esta ubicación";
                    }
                }
            break;
        }
    break;
    case "traerInfoJerarquia":
        $d = jsonStart();
        $listaMarcados = $d["lista"];
	require_once ("../boletines/classes/class.boJerarquia.php");
        $jerarquia = new boJerarquia();
        foreach ($listaMarcados as $item){
            $cadenaJerarquia="";
            $jerarquia->obtieneDetalleOrigen($item["padreId"],$item["categoriaId"]);
	    //cambio el orden en que se presenta
	    $cadenaJerarquia=str_replace(array("|>"," << "), array("|","|"), $cadenaJerarquia); 
	    $cadenaJerarquia = "|".$item["nombre"] . "|".$cadenaJerarquia; 
	    $a = explode("|",$cadenaJerarquia); 
	    $n = count($a);
	    $cadenaNueva=""; 
	    for($i=$n-1;$i>=0;$i--){
		$nombre=trim($a[$i]); 
		if($nombre!="")
		$cadenaNueva.=$nombre." > "; 
	    }
	    $cadenaNueva=substr($cadenaNueva,0,-2); 
	    $json[$item["nombre"]] = $cadenaNueva;
        }
    break;
    case "guardaSuscripciones":
        $g=jsonStart();  // lee la información del objeto del html
        $errores=array();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $lstCorreos=$g["lstCorreos"];
            $lstCategoriasMarcadas=$g["lstCategoriasMarcadas"];
            foreach ($lstCorreos as $sus){
                $db = new MYSQLDB();
                $correo = expect_email($sus["susEmail"]);
                if($correo != ""){
                    $nombres = "";
                    $identificacion = "";
                    if(isset($sus["susNombre"])){
                        $nombres = expect_safe_html($sus["susNombre"]);
                    }
                    if(isset($sus["susIdentificacion"])){
                        $identificacion = expect_safe_html($sus["susIdentificacion"]);
                    }
                    $fechaNacimiento=0;
                    if(isset($sus["susFechanacimientounix"])){
                        $fechaNacimiento = expect_integer($sus["susFechanacimientounix"]);
                    }
                    $telefono ="";
                    if(isset($sus["susTelefono"])){
                        $telefono = expect_safe_html($sus["susTelefono"]);
                    }
                    $celular = "";
                    if(isset($sus["susCelular"])){
                        $celular = expect_safe_html($sus["susCelular"]);
                    }

                    $datos = array("email"=>$correo,"nombre"=>$nombres,"identificacion"=>$identificacion,"fechanacimientounix"=>$fechaNacimiento,"telefono"=>$telefono,"celular"=>$celular);
                    require_once("../boletines/classes/class.boCorreo.php");
                    $bco = new boCorreo();
                    $idCorreo = $bco->insertUpdate($datos);
                    if($idCorreo != 0){
                       require_once("../boletines/classes/class.boCorreoxJerarquia.php");
                       $suscribe = new boCorreoxJerarquia();
                       foreach ($lstCategoriasMarcadas as $item){
                           $idCorreoJeraquia=0;
                           $idCorreoJeraquia = $suscribe->guardaSuscripcion($idCorreo, $item["id"]);
                           if($idCorreoJeraquia != 0){
                               $json["resultado"]=$g;
                               unset($json["resultado"]["errores"]);
                           }else{
                               $errores[]="Correo: ".$correo." ya fue suscrito en la categoría: ".$item["nombre"];
                           }
                       }
                    }
                }
                else{
                    $errores[]="Correo: ".$correo." Inválido.";
                }
            }
            if(count($errores)>0){
                $json["resultado"]["errores"]=$errores;
            }
        }
    break;
    case "editaSuscripcion":
        $g=jsonStart();  // lee la información del objeto del html
        $errores=array();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $datosSuscribe = $g["suscripcion"];
            $id = $datosSuscribe["idEdita"];
            $correo = expect_email($datosSuscribe["email"]);
            if($correo != ""){
                $nombres = "";
                $identificacion = "";
                if(isset($datosSuscribe["nombre"])){
                    $nombres = expect_safe_html($datosSuscribe["nombre"]);
                }
                if(isset($datosSuscribe["identificacion"])){
                    $identificacion = expect_safe_html($datosSuscribe["identificacion"]);
                }
                $fechaNacimiento=0;
                if(isset($datosSuscribe["fechaNacimiento"]) && $datosSuscribe["fechaNacimiento"] > 0){
                    $fechaNacimiento = expect_integer($datosSuscribe["fechaNacimiento"]);
                }
                $telefono ="";
                if(isset($datosSuscribe["telefono"])){
                    $telefono = expect_safe_html($datosSuscribe["telefono"]);
                }
                $celular = "";
                if(isset($datosSuscribe["celular"])){
                    $celular = expect_safe_html($datosSuscribe["celular"]);
                }
                $empresa = "";
                if(isset($datosSuscribe["empresa"])){
                    $empresa = expect_safe_html($datosSuscribe["empresa"]);
                }
                $direccion = "";
                if(isset($datosSuscribe["direccion"])){
                    $direccion = expect_safe_html($datosSuscribe["direccion"]);
                }

                $datos = array("email"=>$correo,"nombre"=>$nombres,"identificacion"=>$identificacion,"fechanacimientounix"=>$fechaNacimiento,"telefono"=>$telefono,"celular"=>$celular,"empresa"=>$empresa,"direccion"=>$direccion);
                require_once("../boletines/classes/class.boCorreo.php");
                $bco = new boCorreo();
                $idCorreo = $bco->UpdateEmailPorId($datos,$id);
                $json["resultado"]=$g;
            }else{
                $errores[]="Correo: ".$correo." Inválido.".$datosSuscribe["email"];
            }
            if(count($errores)>0){
                $json["resultado"]["errores"]=$errores;
            }
        }
    break;
    case "eliminaSuscripcion":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
                    $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $g = jsonStart();
            $g["bca_id"]=expect_integer($g["bca_id"]);
            $g["bjr_id"]=expect_integer($g["bjr_id"]);
            $g["bco_id"]=expect_integer($g["bco_id"]);
            $db = new MYSQLDB();
            //Elimina suscripción
            require_once("../boletines/classes/class.boCorreoxJerarquia.php");
            $suscribe = new boCorreoxJerarquia();
            $resulta=$suscribe->eliminaSuscripcion($g["bco_id"], $g["bjr_id"]);
            if($resulta){
                $json["resultado"]=$resulta;
            }
        }
    break;
    case "reporteSuscripciones":
         $d = jsonStart();
         $lista = expect_safe_html($_REQUEST["lista"]);
         $aLista = explode(";",$lista);
         $where="";
         //Crea listado de IDs por categoría para asociar categorías(Y) e hijos (OR)
         $aWhere = array();
         foreach ($aLista as $selec){
             if($selec <> ""){
                $valores = explode("|",$selec);
                $id = $valores[0];
                $padreId = $valores[1];
                $categoriaId = $valores[2];
                if(!(strpos($id, "C") !== false)){
                    $aWhere[$categoriaId][] = array("id"=>$id,"padreId"=>$padreId);
                }
             }
         }
         //Arma clausula para consulta SQL
         if(count($aWhere) > 0){
             foreach ($aWhere as $clausula){
                 $where.=" OR ( ";
                 foreach ($clausula as $c){
                        $where.=" boJerarquia_id = ". $c["id"]." OR";
                 }
                 $where=rtrim($where, "OR");
                 $where.=" )";
             }
             $where=ltrim($where, " OR");
             $where= " AND ".$where;
         }
         //Arma Consulta sql final
         $db = new MYSQLDB();
         $sql = $db->mkSQL("SELECT 
                                        boCategoria_id 
                                        ,boJerarquia_id
                                        ,boJerarquia_padreId
                                        ,boCorreo_id
                                        ,boCorreoxJerarquia_id
                                        ,boCategoria_nombre 
                                        ,boJerarquia_descripcion 
                                        ,boJerarquia_nombre 
                                        ,boCorreo_email
                                        ,boCorreo_nombre 
                                        ,boCorreo_identificacion 
                                        ,boCorreo_fechaNacimiento
                                        ,0 as boCorreo_fechaNacimientoFrmt
                                        ,boCorreo_telefono
                                        ,boCorreo_celular
                                        ,boCorreo_empresa
                                        ,boCorreo_direccion
                                    FROM bocategoria 
                                        INNER JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId 
                                        INNER JOIN bocorreoxjerarquia ON boJerarquia_id = boCorreoxJerarquia_jerarquiaId 
                                        INNER JOIN bocorreo ON boCorreo_id = boCorreoxJerarquia_correoId 
                                    WHERE boJerarquia_activo=%N AND boCorreo_activo=%N ".$where,1,1);
         //Ejecución de tabula con clausula
         $limpiar=array( 
                        "boCategoria_"=>"bca_",
                        "boJerarquia_"=>"bjr_",
                        "boCorreo_"=>"bco_",
                        "boCorreoxJerarquia_"=>"bcj_"
             );

         $ngTabula = new coTabulaAngular();
         $ngTabula->setInput($d);
         $ngTabula->setLimpiador($limpiar);
         $ngTabula->setOrdenDefault("boCategoria_nombre");
         $ngTabula->setQueryDatos($sql);
         $ngTabula->setCamposConTabla(false);
         $ngTabula->permiteExportar(true,"SuscripcionesActuales");
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "boCategoria_nombre": $cabecera="CATEGORIA";break;
                case "boJerarquia_nombre": $cabecera="JERARQUIA";break;
                case "boCorreo_nombre": $cabecera="NOMBRE";break;
                case "boCorreo_identificacion": $cabecera="IDENTIFICACIÓN";break;
                case "boCorreo_email": $cabecera="EMAIL";break;
                case "boCorreo_fechaNacimiento": $cabecera="FECHA DE NACIMIENTO";break;
                case "boCorreo_fechaNacimientoFrmt": $cabecera="FECHA DE NACIMIENTO";break;
                case "boCorreo_telefono": $cabecera="TELÉFONO";break;
                case "boCorreo_celular": $cabecera="CELULAR";break;
                case "boCorreo_empresa": $cabecera="EMPRESA";break;
                case "boCorreo_direccion": $cabecera="DIRECCIÓN";break;
            }
            return $cabecera;
         });
         $ngTabula->setPreparaDatos(function($fila){
            $fila["boCorreo_fechaNacimientoFrmt"] = ($fila["boCorreo_fechaNacimiento"] != -1 && $fila["boCorreo_fechaNacimiento"] != 0) ? date('Y-m-d',$fila["boCorreo_fechaNacimiento"]) : "";
            return $fila;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
             unset($fila["boCategoria_id"]);
             unset($fila["boJerarquia_id"]);
             unset($fila["boJerarquia_padreId"]);
             unset($fila["boCorreo_id"]);
             unset($fila["boCorreoxJerarquia_id"]);
             unset($fila["boJerarquia_descripcion"]);
             $fila["boCorreo_fechaNacimientoFrmt"] = ($fila["boCorreo_fechaNacimiento"] != -1 && $fila["boCorreo_fechaNacimiento"] != 0) ? date('Y-m-d',$fila["boCorreo_fechaNacimiento"]) : "";
             unset($fila["boCorreo_fechaNacimiento"]);
             $fila["boCorreo_identificacion"] = "'".$fila["boCorreo_identificacion"];
             return $fila;
         });
         $json=$ngTabula->responde();
         $nuevoQuery = $ngTabula->getSQLFiltrado();
         $lista=array();
         if($db->query($nuevoQuery)){
            while($row=$db->fetchRow()){
                    $lista[]=$row["boCorreoxJerarquia_id"];
            }
         }
         $json["extra"] = array(
            "esFiltrado"=>$ngTabula->esFiltrado(),
            "asociacionLista"=>$lista
         );
    break;
    case "subirSuscripciones":
        $userId= $_SESSION[MID . "userId"];
        $respuesta = $firma->setFile($_FILES, $userId);
        $json["respuesta"] = $respuesta;
    break;
    case "cargaSuscripciones":
        $g=jsonStart();
        $errores=array();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]=$lang["No tiene permiso"];
        }else{
            $lstArchivos=$g["lstArchivos"];
            $lstCategoriasMarcadas=$g["lstCategoriasMarcadas"];
            $errores = array();
            if(count($lstArchivos) > 0){
                $lineasArchivo = 0;
                foreach($lstArchivos as $arch){
                    $archivoUplaod = $firma->viewFile($arch["fileId"]);
                    $archivo = BASEFOLDER.$archivoUplaod;
                    //busca signo de división
                    $archivoCorreo = fopen($archivo, "r");
                    //Crea arreglo de datos a insertar
                    $lstCorreos = array();
                    if($archivoCorreo){
                        $filas = file($archivo);
                        $lineasArchivo = $lineasArchivo + count($filas);
                        if($lineasArchivo <= 10000){
                            foreach($filas as $reg){
                                $reg = str_replace('´', '', $reg);
                                $reg = str_replace(',', ';', $reg);
                                $datosLinea = array();
                                if(strpos($archivoUplaod,'.csv') !== false){
                                    $datosLinea = explode(';',$reg);
                                }else{
                                    $datosLinea = explode('|',$reg);
                                }
                                $caracteresLimpiezaTexto = array('"','°','+','(',')','!','|','#','$','%','&','/','=','?','¡','¿','´','¨','*','~','{','[','^','}',']','`',';',',',':','\\');
                                $caracteresLimpiezaNumeros = array('"','-','°',' ','+','(',')','!','|','#','$','%','&','/','=','?','¡','¿','´','¨','*','~','{','[','^','}',']','`',';',',',':','.','_','\\');
                                $lstCorreos[] = array("susEmail"=>strip_tags(trim($datosLinea[0])),
                                                      "susNombre"=>isset($datosLinea[1]) ? ($datosLinea[1] != "" ? expect_safe_html(trim($datosLinea[1])) : "") : "",
                                                      "susIdentificacion"=>isset($datosLinea[2]) ? ($datosLinea[2] != "" ? expect_safe_html(trim(str_replace("'", "",$datosLinea[2]))) : "") : "",
                                                      "susFechanacimientounix"=>isset($datosLinea[3]) ? ($datosLinea[3] > 0 ? strtotime(trim($datosLinea[3])) : 0) : "",
                                                      "susTelefono"=>isset($datosLinea[4]) ? ($datosLinea[4] != "" ? expect_safe_html(trim(str_replace($caracteresLimpiezaNumeros,'',$datosLinea[4]))) : "") : "",
                                                      "susCelular"=>isset($datosLinea[5]) ? ($datosLinea[5] != "" ? expect_safe_html(trim(str_replace($caracteresLimpiezaNumeros,'',$datosLinea[5]))) : "") : "",
                                                      "susEmpresa"=>isset($datosLinea[6]) ? ($datosLinea[6] != "" ? expect_safe_html(trim(str_replace($caracteresLimpiezaTexto,'',$datosLinea[6]))) : "") : "",
                                                      "susDireccion"=>isset($datosLinea[7]) ? ($datosLinea[7] != "" ? expect_safe_html(trim(str_replace($caracteresLimpiezaTexto,'',$datosLinea[7]))) : "") : ""
                                                     );
                            }
                            fclose($archivoCorreo);
                            if(count($lstCorreos) > 0){
                                $correosInvalidos = "";
                                foreach ($lstCorreos as $sus){
                                    $db = new MYSQLDB();
                                    $correo = expect_email($sus["susEmail"]);
                                    if($correo != ""){
                                        $nombres = "";
                                        $identificacion = "";
                                        if(isset($sus["susNombre"])){
                                            $nombres = $sus["susNombre"];
                                        }
                                        if(isset($sus["susIdentificacion"])){
                                            $identificacion = $sus["susIdentificacion"];
                                        }
                                        $fechaNacimiento=0;
                                        if(isset($sus["susFechanacimientounix"])){
                                            $fechaNacimiento = $sus["susFechanacimientounix"];
                                        }
                                        $telefono ="";
                                        if(isset($sus["susTelefono"])){
                                            $telefono = $sus["susTelefono"];
                                        }
                                        $celular = "";
                                        if(isset($sus["susCelular"])){
                                            $celular = $sus["susCelular"];
                                        }
                                        $empresa = "";
                                        if(isset($sus["susEmpresa"])){
                                            $empresa = $sus["susEmpresa"];
                                        }
                                        $direccion = "";
                                        if(isset($sus["susDireccion"])){
                                            $direccion = $sus["susDireccion"];
                                        }

                                        $datos = array("email"=>$correo,"identificacion"=>$identificacion,"fechanacimientounix"=>$fechaNacimiento,"telefono"=>$telefono,"celular"=>$celular,"nombre"=>$nombres,"empresa"=>$empresa,"direccion"=>$direccion);
                                        require_once("../boletines/classes/class.boCorreo.php");
                                        $bco = new boCorreo();
                                        //print_h($datos);
                                        $idCorreo = $bco->insertUpdate($datos);
                                        if($idCorreo != 0){
                                           require_once("../boletines/classes/class.boCorreoxJerarquia.php");
                                           $suscribe = new boCorreoxJerarquia();
                                           foreach ($lstCategoriasMarcadas as $item){
                                               $idCorreoJeraquia=0;
                                               $idCorreoJeraquia = $suscribe->guardaSuscripcion($idCorreo, $item["id"]);
                                               $json["resultado"]=$g;
                                               unset($json["resultado"]["errores"]);
                                           }
                                        }
                                    }else{
                                        $correosInvalidos.="Correo: ".$sus["susEmail"]." Inválido.|";
                                    }
                                }
                                if($correosInvalidos != ""){
                                    $errores[]= $correosInvalidos;
                                }
                            }else{
                                $errores[]=$lang["No existen correos por suscribir en archivo: "].$archivoUplaod;
                            }
                            if(count($errores)>0){
                                $json["resultado"]["errores"]=$errores;
                            }
                            //print fin
                        }else{
                            $errores[]="El archivo: ".$archivoUplaod. " supera el total de líneas de lectura permitidas";
                        }
                    }else{
                        $errores[]=$lang["No se pudo abrir el archivo: "].$archivoUplaod;
                    }
                }
            }else{
                $errores[]=$lang["No existen archivos para cargar"];
            }
            
            if(count($errores)>0){
                $json["resultado"]["errores"] = $errores;
            }
        }
    break;
    case "reporteSuscripcioUpload":
        $ngTabula = new coTabulaAngular();
        $ngTabula = $firma->getFile($_SESSION[MID . "userId"], expect_safe_html($_GET['field']));
        $limpiar = array("dctAdjunto_" => "ng_");
        $json = $ngTabula->responde();
        $json["extra"]["view"] = $Central->conPermiso("Boletines Electronicos,Administrador");
        $json["extra"]["delete"] = $Central->conPermiso("Boletines Electronicos,Administrador");
    break;
    case "verUpload":
        $json["respuesta"] = $firma->viewFile(expect_safe_html(trim(limpiaInput("fileId"))));
    break;    
    case "deleteUpload":
        $del= $firma->deleteFile(expect_safe_html(trim(limpiaInput("fileId"))));
        $json["respuesta"] =$del;
    break;
    case "eliminaSuscripcionesReporte":
        $g=jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]=$lang["No tiene permiso"];
        }else{
            $error="";
            $asociaciones=$g["asociaciones"];
            $asociaciones = array_unique($asociaciones);
            if(count($asociaciones)>0){
                require_once("../boletines/classes/class.boCorreoxJerarquia.php");
                $suscribe = new boCorreoxJerarquia();
                $suscribe->eliminaSuscripcionXlistaIds($asociaciones);
                $json["resultado"]=$g;
                $json["resultado"]["mensaje"] = $lang["Se han eliminado"]." ".count($asociaciones)." ".$lang["registros"].".";
                unset($json["resultado"]["errores"]);
            }else{
                $error = $lang["No existen valores a eliminar"];
            }
            //existen errores?
            if($error != ""){
                $json["resultado"]["errores"] = $error;
            }
        }
    break;
    case "reporteSuscripcionesPorCorreo":
        $d = jsonStart();
        $limpiar=array("boCategoria_"=>"bca_","boJerarquia_"=>"bjr_","boCorreo_"=>"bco_","boCorreoxJerarquia_"=>"bcj_");
        $correo = expect_safe_html($_REQUEST["correos"]);
        $correo = str_replace('"', '', $correo);
        $correo = str_replace('[', '', $correo);
        $correo = str_replace(']', '', $correo);
        $listaCorreos = explode(',', $correo);
        $emailValidos = array();
        foreach($listaCorreos as $email){
            $emailValidos[] = trim($email);
        }
        if(count($emailValidos) > 0){
            $emailsValidosStr = implode("','", $emailValidos);
            $emailsValidosStr= "'".$emailsValidosStr."'";
            $db = new MYSQLDB();
            //print_h($emailsValidosStr);
            $ngTabula = new coTabulaAngular();
            $ngTabula->setInput($d);
            $ngTabula->setLimpiador($limpiar);
            $ngTabula->setOrdenDefault("boCorreo_email");
            $sql = $db->mkSQL("SELECT 
                                            boCategoria_id 
                                            ,boCategoria_nombre 
                                            ,boJerarquia_id 
                                            ,boJerarquia_descripcion 
                                            ,boJerarquia_nombre 
                                            ,boJerarquia_padreId
                                            ,boCorreo_id
                                            ,boCorreoxJerarquia_id
                                            ,boCorreo_email
                                            ,boCorreo_nombre
                                            ,boCorreo_telefono
                                            ,boCorreo_identificacion
                                            ,boCorreo_fechaNacimiento
                                            ,boCorreo_empresa
                                            ,boCorreo_direccion
                                            ,boCorreo_celular
                                        FROM `bocategoria` 
                                            INNER JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId 
                                            INNER JOIN bocorreoxjerarquia ON boJerarquia_id = boCorreoxJerarquia_jerarquiaId 
                                            INNER JOIN bocorreo ON boCorreo_id = boCorreoxJerarquia_correoId 
                                        WHERE boJerarquia_activo=%N AND boCorreo_activo=%N AND boCorreo_email IN (".$emailsValidosStr.")",1,1);
            $ngTabula->setQueryDatos($sql);
            $ngTabula->setCamposConTabla(false);
            $ngTabula->permiteExportar(false);
            $json=$ngTabula->responde();
            $nuevoQuery = $ngTabula->getSQLFiltrado(); // preg_replace("/LIMIT\s.+/is"," ",$ngTabula->getSQLFiltrado());
            $lista=array();
            if($db->query($nuevoQuery)){
                while($row=$db->fetchRow()){
			$lista[]=$row["boCorreoxJerarquia_id"];
		}
            }
            $json["extra"] = array(
                "esFiltrado"=>$ngTabula->esFiltrado(),
                "asociacionListaBuscados"=>$lista
            );
        }
    break;
    case "reporteSuscripcionesLimpieza":
        $d = jsonStart();
        $lista = expect_safe_html($_REQUEST["lista"]);
        $jerarquias = array();
        $seleccion = explode(";",$lista);
        foreach ($seleccion as $se){
            $partes = explode("|",$se);
            if(isset($partes[0]) && $partes[0] > 0){
                $jerarquias[] = $partes[0];
            }
        }
        $grupo = "";
        if(count($jerarquias) > 0){
            $grupo = " boJerarquia_id IN (".implode(',',$jerarquias).")";
        }
        if($grupo != ""){
            $db = new MYSQLDB();
            $sql = $db->mkSQL("SELECT 
                       boCorreoxJerarquia_id,
                       boCorreoxJerarquia_correoId,
                       boCorreo_email,
                       GROUP_CONCAT(boJerarquia_nombre,'|',boJerarquia_id) boCorreoxJerarquia_relacion,
                       COUNT(boCorreoxJerarquia_jerarquiaId) boCorreoxJerarquia_cuantos
                   FROM 
                   bocorreoxjerarquia 
                   LEFT JOIN bojerarquia ON boCorreoxJerarquia_jerarquiaId = boJerarquia_id
                   LEFT JOIN bocorreo ON boCorreoxJerarquia_correoId = boCorreo_id
                   WHERE ".$grupo."
                   GROUP BY boCorreoxJerarquia_correoId
                   HAVING COUNT(boCorreoxJerarquia_jerarquiaId)>%N",1);
            $sqlCuenta = $db->mkSQL("SELECT * FROM bocorreoxjerarquia 
                   LEFT JOIN bojerarquia ON boCorreoxJerarquia_jerarquiaId = boJerarquia_id
                   LEFT JOIN bocorreo ON boCorreoxJerarquia_correoId = boCorreo_id
                   WHERE ".$grupo."
                   GROUP BY boCorreoxJerarquia_correoId
                   HAVING COUNT(boCorreoxJerarquia_jerarquiaId)>%N",1);
            $limpiar=array( "boCorreoxJerarquia_"=>"bcj_","boCorreo_"=>"bco_");
            $ngTabula = new coTabulaAngular();
            $ngTabula->setInput($d);
            $ngTabula->setLimpiador($limpiar);
            $ngTabula->setOrdenDefault("boCorreoxJerarquia_id DESC");
            $ngTabula->setQueryDatos($sql);
            $ngTabula->setCamposConTabla(false);
            $ngTabula->permiteExportar(false);
            $ngTabula->setQueryCuenta($sqlCuenta);
            $ngTabula->setPreparaDatos(function($fila){
                $asociacion = explode(',', $fila["boCorreoxJerarquia_relacion"]);
                $relaciones = array();
                foreach($asociacion as $aso){
                    $partes = explode("|", $aso);
                    $id = isset($partes[1]) ? $partes[1] : 0;
                    $jerarquia = isset($partes[0]) ? $partes[0] : "";
                    if($id > 0){
                       $relaciones[] = array("id"=>$id,"jerarquia"=>$jerarquia);
                    }
                }
                $fila["bcj_relacion"] = $relaciones;
                return $fila;
            });
            $json=$ngTabula->responde();
        }else{
            $json = "";
        }
    break;
    case "eliminarSuscripcionDuplicada":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $correoId = isset($d["correoId"]) ? expect_integer($d["correoId"]) : 0;
            $jerarquiaId = isset($d["jerarquiaId"]) ? expect_integer($d["jerarquiaId"]) : 0;
            require_once("../boletines/classes/class.boCorreoxJerarquia.php");
            $suscribe = new boCorreoxJerarquia();
            $suscribe->eliminaSuscripcion($correoId,$jerarquiaId);
            $json["resultado"] = $correoId;
        }
    break;
    //Contenidos
    case "inicializarEnvio":
        $d = jsonStart();
        require_once("../boletines/classes/class.boRedesConf.php");
        require_once("../boletines/classes/class.boConfiguracion.php");
        $brc = new boRedesConf();
        $objConf = new boConfiguracion();
        $conexiones = $brc->getConfig();
        $plugins = $objConf->getConfiguracionPorTipo("Boletin");
        $json["resultado"]["conexiones"] = $conexiones;
        $json["resultado"]["plugins"] = $plugins;
    break;
    case "reporteBoletinesActivos":
        $d = jsonStart();
        $limpiar=array("boContenido_"=>"boc_");
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bocontenido WHERE boContenido_estado=%Q","activo");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);  //indica si en el query voy a usar los campos con todo y el nombre de tabla
        $ngTabula->permiteExportar(true);
        $ngTabula->setOrdenDefault("boContenido_fechaCreacion DESC"); //categorias_created
        $json=$ngTabula->responde();
    break;
    case "actualizarBoletin":
	if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
	    $g=jsonStart();  // lee la información del objeto del html
	    $errores=array();
	    $contenidoId=expect_integer($g["boc_id"]);
	    $nombre=expect_safe_html($g["boc_nombre"]);
            $enlace=isset($g["boc_enlace"]) ? expect_safe_html($g["boc_enlace"]) : "";
            $descripcion=isset($g["boc_descripcion"]) ? expect_safe_html($g["boc_descripcion"]) : "";
            $url=isset($g["boc_url"]) ? expect_safe_html($g["boc_url"]) : "";
            $tipo=expect_safe_html($g["boc_tipo"]);
            $conexId=isset($g["boc_conexId"]) ? expect_safe_html($g["boc_conexId"]) : "";
            $categoriaId=isset($g["boc_categoriaId"]) ? expect_integer($g["boc_categoriaId"]) : 0;
            $origen=expect_safe_html($g["boc_origen"]);
            $mensaje=isset($g["boc_mensaje"]) ? expect_safe_html($g["boc_mensaje"]) : "";
            $plugin=isset($g["boc_plugin"]) ? expect_safe_html($g["boc_plugin"]) : "";
            if(empty($g["boc_nombre"])){
                $errores["boc_nombre"]=$lang["Campo no puede estar vacío"];
            }
            if(empty($categoriaId) && $tipo!=="sms"){
                if(empty($g["boc_url"])){
                    $errores["boc_url"]=$lang["Campo no puede estar vacío"];
                }
            }
            if(empty($origen)){
                $errores["boc_origen"]=$lang["Campo no puede estar vacío"];
            }
            if($tipo == 'facebook' && (!(strpos($g["boc_url"], "jpg") !== false || strpos($g["boc_url"], "png") !== false) || strpos($g["boc_url"], "ico") !== false)){
                $errores["boc_url"]=$lang["Url inválida, debe contener ruta hacia un archivo HTML"];
            }
	    if(count($errores)==0){
                require_once("../boletines/classes/class.boContenido.php");
                $conten = new boContenido();
                $conten->initFromDB($contenidoId);
                if($contenidoId > 0 && $conten->get("id") == $contenidoId){
                    $usuarioId = $_SESSION[MID . "userId"];
                    $html = $conten->guardarConfiguracionBoletin($categoriaId,$contenidoId,$nombre,$url,$tipo,$usuarioId,time(),$conexId,$enlace,$descripcion,$origen,$mensaje,$plugin);
                    $g["boc_contenido"] = $html;
                    $g["boc_url"] = $url;
                    $json["resultado"]=$g;
                    unset($json["resultado"]["errores"]);
                }else{
                    $json["resultado"]["errores"]=$lang["Identificador de contenido inválido"];
                }
	    }else{
                $json["resultado"]["errores"] = $errores;
	    }
	}
    break;
    case "nuevoBoletin":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g = jsonStart();
            require_once("../boletines/classes/class.boContenido.php");
            $conten = new boContenido();
            $nombre = $lang["Nuevo Boletín"];
            $id = $conten->insert($nombre, time());
            $json["resultado"] = $id;
        }
    break;
    case "eliminarBoletin":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["error"]="No tiene permiso";
	}else{
            $g = jsonStart();
            $id = isset($g["id"]) ? expect_integer($g["id"]) : 0;
            if($id > 0){
                require_once("../boletines/classes/class.boContenido.php");
                $conten = new boContenido();
                $conten->eliminar($id);
            }else{
                $json["resultado"]["error"]="Boletín no definido";
            }
            $json["resultado"] = $g;
        }
    break;
    case "cambiarEstadoBoletin":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["error"]="No tiene permiso";
	}else{
            $g = jsonStart();
            $id = isset($g["id"]) ? expect_integer($g["id"]) : 0;
            if($id > 0){
                require_once("../boletines/classes/class.boContenido.php");
                $conten = new boContenido();
                $conten->initFromDB($id);
                if($conten->get("id") == $id){
                    $conten->cambiarEstado("inactivo");
                    $json["resultado"] = $g;
                }else{
                    $json["resultado"]["error"]="Boletín inválido: ".$id;
                }
            }else{
                $json["resultado"]["error"]="Boletín no definido";
            }
        }
    break;
    case "actualizarContenidoHTML":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g = jsonStart();
            $id = isset($g["id"]) ? expect_integer($g["id"]) : 0;
            $html = "";
            if($id > 0){
                require_once("../boletines/classes/class.boContenido.php");
                require_once("../boletines/classes/class.boCorreo.php");
                $bco = new boCorreo();
                $conten = new boContenido();
                $conten->initFromDB($id);
                $url = $conten->get("url");
                $tipo = $conten->get("tipo");
                if($tipo == 'correo'){
                    $html = $conten->generarContenidoHtml($url);
                    $conten->setContenido($html);
                }
            }
            $json["resultado"]["html"] = $html;
        }
    break;
    case "obtenerBoletin":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g = jsonStart();
            $boletinId = isset($g["boletinId"]) ? expect_integer($g["boletinId"]) : 0;
            if(empty($boletinId)){
                $json["resultado"]["errores"]="Boletín invalido";
            }else{
                require_once("../boletines/classes/class.boContenido.php");
                $objConten = new boContenido();
                $objConten->initFromDB($boletinId);
                $json["resultado"]["boletin"]["boc_id"] = $objConten->get("id");
                $json["resultado"]["boletin"]["boc_nombre"] = $objConten->get("nombre");
                $json["resultado"]["boletin"]["boc_tipo"] = $objConten->get("tipo");
                $json["resultado"]["boletin"]["boc_origen"] = $objConten->get("origen");
                $json["resultado"]["boletin"]["boc_contenido"] = $objConten->get("contenido");
                $json["resultado"]["boletin"]["boc_url"] = $objConten->get("url");
            }
        }
    break;
    case "jEditorAntiguo":
        $d = jsonStart();
        $subaccion = expect_pure_alpha($d["subaccion"]);
        $db = new MYSQLDB();
        switch ($subaccion) {
            case "cargaDatos":
                require_once ("../boletines/classes/class.boCategoria.php");
                $bct = new boCategoria();
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                isset($d["abrir"]) ? $abrir = expect_integer($d["abrir"]) : $abrir = 0;
                $abrirOriginal = $abrir;
                //Funcion que obtiene la Genealogia
                $genealogia = $bct->obtenerGenealogiaCategorias($abrir, $ubicacionBase);
                //Funcion que obtiene el arbol jerarquico
                $ramas = $bct->obtenerArbolCategorias($genealogia, $abrirOriginal, false);
                $json["ramas"] = $ramas;
            break;
        }
    break;
    case "subirArchivosBoletin":
        $userId= $_SESSION[MID . "userId"];
        $respuesta = $firmaRs->setFile($_FILES, $userId);
        $json["respuesta"] = $respuesta;
    break;
    case "reporteArchivosBoletin":
        $ngTabula = new coTabulaAngular();
        $ngTabula = $firmaRs->getFile($_SESSION[MID . "userId"], expect_safe_html($_GET['field']));
        $limpiar = array("dctAdjunto_" => "ng_");
        $json = $ngTabula->responde();
        $json["extra"]["view"] = $Central->conPermiso("Boletines Electronicos,Administrador");
        $json["extra"]["delete"] = $Central->conPermiso("Boletines Electronicos,Administrador");
    break;
    case "verArchivoBoletin":
        $json["respuesta"] = $firmaRs->viewFile(expect_safe_html(trim(limpiaInput("fileId"))));
    break;
    case "eliminarArchivoBoletin":
        $del = $firmaRs->deleteFile(expect_safe_html(trim(limpiaInput("fileId"))));
        $json["respuesta"] = $del;
    break;
    case "obtenerUrlUploadBoletin":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $fileId = expect_integer($d["fileId"]);
            if($fileId > 0){
                $result = $firmaRs->viewFile($fileId);
            }
            $json["resultado"] = $result;
        }
    break;
    //Envío por Archivo
    case "inicializarArchivo":
        $d = jsonStart();
        $tipo = expect_safe_html($d["tipo"]);
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $archivos = $objConf->getArchivosProcesamiento($tipo);
        $json["resultado"]["archivos"] = $archivos;
    break;
    case "generarVistaPreviaArchivo":
        $d = jsonStart();
        $archivo = expect_safe_html($d["archivo"]);
        require_once("../boletines/classes/class.boConfiguracion.php");
        $objConf = new boConfiguracion();
        $contenido = $objConf->getContenidoArchivo($archivo);
        $json["resultado"]["contenido"] = $contenido;
    break;
    case "envioBoletinArchivo":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $g=jsonStart();  // lee la información del objeto del html
            $g["boletinId"]=expect_integer($g["boletinId"]); // id de boletín seleccionado
            $archivo = expect_safe_html($g["archivo"]); //lista de marcados
            $fechaProgramada = isset($g["fechaProgramada"]) ? $g["fechaProgramada"] : -1;
            $horaProgramada = isset($g["horaProgramada"]) ? $g["horaProgramada"] : -1;
            $minutoProgramado = isset($g["minutoProgramado"]) ? $g["minutoProgramado"] : -1;
            require_once("../boletines/classes/class.boCorreo.php");
            $objCorreo = new boCorreo();
            $result = $objCorreo->envioBoletinArchivo($g["boletinId"],$archivo,$fechaProgramada,$horaProgramada,$minutoProgramado);
            if(is_string($result)){
                $json["resultado"]["error"] = $result;
            }else{
                $json["resultado"]=$g;
            }
        }
    break;
    //Grupos y Categorías
    case "categorias":
        require_once ("../comunes/classes/class.coCompleteAngular.php");
        $d = jsonStart();
        $ngComplete = new coCompleteAngular();
        $ngComplete->setInput($d);
        $limpiar=array( "boCategoria_"=>"cat_");
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bocategoria");
        $ngComplete->setQueryDatos($sql,"boCategoria_nombre",$limpiar);
        $ngComplete->setCamposConTabla(false);
        $json=$ngComplete->responde();
    break;
    case "eliminarCategoria":
	$g = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g["cat_id"]=expect_integer($g["cat_id"]);
            require_once("../boletines/classes/class.boCategoria.php");
            $cat = new boCategoria();
            $result = $cat->eliminarCategoria($g["cat_id"]);
            if(is_string($result)){
                $json["resultado"]["errores"]=$result;
            }else{
                $json["resultado"]=$result;
            }
        }
    break;
    case "guardarCategoria":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $g=jsonStart();  // lee la información del objeto del html
            $errores=array();
            $g["cat_id"]=expect_integer($g["cat_id"]);
            $g["cat_nombre"]=expect_safe_html($g["cat_nombre"]);
            $g["cat_ultimoNivel"]=(string)expect_integer($g["cat_ultimoNivel"]);
            if(empty($g["cat_nombre"])){
                $errores["cat_nombre"]=$lang["Campo no puede estar vacío"];
            }
            if(count($errores)==0){
                if($g["cat_id"]==-1){
                    require_once("../boletines/classes/class.boCategoria.php");
                    $cat = new boCategoria();
                    $a=$cat->insertaBoCategoria($g["cat_nombre"], $g["cat_ultimoNivel"]);
                    if($a){
                        $g["cat_id"]=$a;
                       $json["resultado"]=$g;
                       unset($json["resultado"]["errores"]); 
                    }
                    else{
                        $json["resultado"]["errores"]=$errores;
                    }
                }
                else{
                    require_once("../boletines/classes/class.boCategoria.php");
                    $cat = new boCategoria();
                    $cat->actualizaBoCategoria($g["cat_nombre"],$g["cat_ultimoNivel"], $g["cat_id"]);
                    $json["resultado"]=$g;
                    unset($json["resultado"]["errores"]);
                }
            }
            else{
                $json["resultado"]["errores"]["cat_nombre"]="Error al actualizar el registro"; 
            }
        }
    break;
    case "reporteCategorias":
        $d = jsonStart();
        $limpiar=array(  "boCategoria_"=>"cat_", );
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("boCategoria_nombre");
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * from bocategoria");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);  //indica si en el query voy a usar los campos con todo y el nombre de tabla
        $ngTabula->permiteExportar(true);
        $json=$ngTabula->responde();
    break;
    //Jerarquia
    case "traerInfoCategoria":
	$g = jsonStart();
	$id=expect_integer($g["cat_id"]);
        require_once("../boletines/classes/class.boJerarquia.php");
        $jer = new boJerarquia();
        $datos = $jer->obtieneInfoJerarquia($id);
	$json["Resultado"]=$datos; 
    break;
    case "saveJerarquia":
	if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
		$json["resultado"]["errores"]="No tiene permiso";
	}else{
	    $g=jsonStart();  // lee la información del objeto del html
	    $errores=array();
	    $g["id"]=expect_integer($g["id"]);
	    $g["nombre"]=expect_safe_html($g["nombre"]);
	    $g["descripcion"]=expect_safe_html($g["descripcion"]);
	    $g["activo"]=expect_integer($g["activo"]);
	    if(empty($g["nombre"])){
                $errores["nombre"]=$lang["Campo no puede estar vacío"];
	    }
	    if(count($errores)==0){
                require_once("../boletines/classes/class.boJerarquia.php");
                $jer = new boJerarquia();
                $jer->actualizaJerarquiaXId($g["nombre"], $g["descripcion"], $g["activo"], $g["id"]);
		$json["resultado"]=$g;
		unset($json["resultado"]["errores"]);
	    }
	    else{
		$json["resultado"]["errores"]["rs_nombre"]="Error al actualizar el registro"; 
	    }
	}
    break;
    case "jcategoria":
        $d = jsonStart();
        $categoriaId = expect_integer($_REQUEST["categoriaId"]);
	$subaccion = expect_pure_alpha($d["subaccion"]);
        switch ($subaccion) {
	    case "creaHija":
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                $nombre = "Nueva Categoría";
                require_once("../boletines/classes/class.boJerarquia.php");
                $jer = new boJerarquia();
                $nuevoId = $jer->insertaBoJerarquia($nombre, $categoriaId, $ubicacionBase);
                if ($nuevoId == 0) {
                    $json["errores"] = "No se pudo crear una nueva ubicación";
                } else {
                    $json["hija"] = array(
                        "id" => $nuevoId,
                        "nombre" => $nombre,
                        "cerrada" => false,
                        "nueva" => false,
                        "seleccionada" => false,
                        "editable" => true,
                        "padreId" => $ubicacionBase,
                        "hijos" => array(),
                    );
                }
                break;
            case "elimina":
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                if ($ubicacionBase == 0) {
                    $json["errores"] = "No se pudo eliminar la ubicación";
                } else {
                    require_once("../boletines/classes/class.boJerarquia.php");
                    $jer = new boJerarquia();
                    $resultado = $jer->eliminaJerarquia($ubicacionBase);
                    if ($resultado == 0) {
                        $json["errores"] = $lang["Error al eliminar el registro"];
                    } else {
                        $json["resultado"] = "";
                    }
                }
                break;
            case "cargaDatos":
                require_once ("../boletines/classes/class.boJerarquia.php");
                $jerarquia = new boJerarquia();
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                isset($d["abrir"]) ? $abrir = expect_integer($d["abrir"]) : $abrir = 0;
                $abrirOriginal = $abrir;
                $genealogia = array();
                //Funcion que obtiene la Genealogia
                $genealogia = $jerarquia->obtenerGenealogia($categoriaId, $abrir, $ubicacionBase);
                //Funcion que obtiene el arbol jerarquico
                $ramas = $jerarquia->obtenerArbolBojerarquia($categoriaId, $genealogia, $abrirOriginal);

                $json["ramas"] = $ramas;
                break;
            case "mueve":
                require_once ("../empresas/classes/class.boJerarquia.php");
                $jer = new boJerarquia();
                //quién es la ubicacion que se mueve? 
                $ubicacionBase = expect_integer($d["ubicacionBase"]);
                //bajo qué padre se mueve?
                $destinoPadre = expect_integer($d["destinoPadre"]);
                //a qué posición de los hijos llega?
                $destinoLugar = expect_integer($d["destinoLugar"]); //en este caso no importa la posición
                //aquí decidimos si el movimiento es permitido o no
                // ------ en este caso el padre debe ser mayor que 0 ------
                if ($destinoPadre <= 0) {
                    $json["errores"] = "La ubicación debe estar por debajo de la matriz";
                } else {
                    $resultado = 0;//$jer->moverRama($destinoPadre, $empresaId, $ubicacionBase);
                    if ($resultado == 1) {
                        $json["resultado"] = "Exito";
                    } else {
                        $json["errores"] = "No se pudo mover esta ubicación";
                    }
                }
                break;
        }
        break;
    //Envío
    case "reporteSuscripcionesEnvio":
         $d = jsonStart();
         $lista = expect_safe_html($d["lista"]);
         $lista = explode(";",$lista);
         $pagina = expect_integer($d["pagina"]);
         $filtro = isset($d["filtro"]) ? expect_safe_html($d["filtro"]):"";
         require_once("../boletines/classes/class.boCorreo.php");
         $cor = new boCorreo();
         $correos = $cor->obtieneListaCorreos($lista,$pagina,$filtro);
         $json["lista"]= $correos["datos"];
         $json["Total"]= $correos["registros"];
         $json["Paginas"]= $correos["paginas"];
    break;
    case "envioManualBoletin":
	if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g=jsonStart();  // lee la información del objeto del html
	    $g["correos"]=expect_safe_html($g["correos"]);
	    $g["boletinId"]=expect_integer($g["boletinId"]);
            require_once("../boletines/classes/class.boCorreo.php");
            $objCorreo = new boCorreo();
            $result = $objCorreo->envioManual($g["correos"], $g["boletinId"]);
            if(is_string($result)){
                $json["resultado"]["errores"] = $result;
            }else{
                $json["resultado"]=$g; 
                unset($json["resultado"]["errores"]);
            }
	}
    break;
    case "envioListaBoletin":
	if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
		$json["resultado"]["errores"]="No tiene permiso";
	}else{
            $g=jsonStart();  // lee la información del objeto del html
	    $g["correos"]=expect_safe_html($g["correos"]);
	    $g["boletinId"]=expect_integer($g["boletinId"]);
            require_once("../boletines/classes/class.boCorreo.php");
            $objCorreo = new boCorreo();
            $result = $objCorreo->envioListaBoletin($g["correos"], $g["boletinId"]);
            if(is_string($result)){
                $json["resultado"]["errores"] = $result;
            }else{
                $json["resultado"]=$g; 
                unset($json["resultado"]["errores"]);
            }
	}
    break;
    case "envioBoletinBitacora":
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
        }else{
            $g=jsonStart();  // lee la información del objeto del html
            $g["boletinId"]=expect_integer($g["boletinId"]); // id de boletín seleccionado
            $lista = expect_safe_html($g["lista"]); //lista de marcados
            $fechaProgramada = isset($g["fechaProgramada"]) ? $g["fechaProgramada"] : -1;
            $horaProgramada = isset($g["horaProgramada"]) ? $g["horaProgramada"] : -1;
            $minutoProgramado = isset($g["minutoProgramado"]) ? $g["minutoProgramado"] : -1;
            require_once("../boletines/classes/class.boCorreo.php");
            $objCorreo = new boCorreo();
            $result = $objCorreo->envioBoletinBitacora($g["boletinId"],$lista,$fechaProgramada,$horaProgramada,$minutoProgramado);
            if(is_string($result)){
                $json["resultado"]["errores"] = $result;
            }else{
                $json["resultado"]=$g; 
                unset($json["resultado"]["errores"]);
            }
        }
    break;
    case "reporteDesuscritosLimpieza":
        $d = jsonStart();
         //Arma Consulta sql
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT boCorreoxJerarquia_id,boRegistro_correoId,boRegistro_correo,boRegistro_desuscritos,boJerarquia_id,boJerarquia_nombre
                FROM boregistro
                INNER JOIN bojerarquia ON FIND_IN_SET(boJerarquia_id,boRegistro_desuscritos)
                INNER JOIN bocorreoxjerarquia ON boCorreoxJerarquia_correoId = boRegistro_correoId AND boJerarquia_id=boCorreoxJerarquia_jerarquiaId
                WHERE boRegistro_tipo LIKE %Q",'DeSuscripcion');
        $limpiar=array( "boRegistro_"=>"bor_","boJerarquia_"=>"boj_","boCorreoxJerarquia_"=>"bcj_");
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("boRegistro_correo,boRegistro_correoId,boRegistro_desuscritos,boJerarquia_id,boJerarquia_nombre DESC");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(true);
        $ngTabula->setPreparaDatos(function($fila){
             return $fila;
        });
         $ngTabula->setPreparaDatosExportar(function($fila){
             return $fila; 
         });
         $json=$ngTabula->responde();
    break;
    case "eliminarDesuscritosTodos":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            //Elimina suscripción
            require_once("../boletines/classes/class.boCorreoxJerarquia.php");
            $suscribe = new boCorreoxJerarquia();
            $suscribe->eliminarDesuscripcionesEncontradas();
            $json["resultado"] = $d;
        }
        break;
    case "eliminarDesuscrito":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador") || $Central->conPermiso("Boletines Electronicos,Operador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $id = expect_integer($d["id"]);
            if($id > 0){
                //Elimina suscripción
                require_once("../boletines/classes/class.boCorreoxJerarquia.php");
                $suscribe = new boCorreoxJerarquia();
                $suscribe->eliminaSuscripcionXId($id);
            }
            $json["resultado"] = $d;
        }
        break;
    //Configuración
    case "traerConfiguracion":
        $d = jsonStart();
        $limpiar=array("boConfiguracion_"=>"bof_");
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM boconfiguracion");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);  //indica si en el query voy a usar los campos con todo y el nombre de tabla
        $ngTabula->permiteExportar(true);
        $ngTabula->setOrdenDefault("boConfiguracion_fechaCreacion DESC"); //categorias_created
        $json=$ngTabula->responde();
    break;
    case "nuevaConfiguracion":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            require_once("../boletines/classes/class.boConfiguracion.php");
            $objConf = new boConfiguracion();
            $nombre = $lang["Nueva Configuración"];
            $nombre = $objConf->nombresRepetidos($nombre);
            $id = $objConf->insertar($nombre);
            $json["resultado"] = $id;
        }
    break;
    case "guardarConfiguracion":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $datos = expect_safe_html($d["datos"]);
            $id = isset($datos["id"]) ? $datos["id"] : 0;
            $nombre = isset($datos["nombre"]) ? $datos["nombre"] : "";
            $titulo = isset($datos["titulo"]) ? $datos["titulo"] : "";
            $correos = isset($datos["correos"]) ? $datos["correos"] : "";
            $grupoSuscripcion = isset($datos["grupoSuscripcion"]) ? $datos["grupoSuscripcion"] : "";
            $pluginEnvio = isset($datos["pluginEnvio"]) ? $datos["pluginEnvio"] : "";
            $tipo = isset($datos["tipo"]) ? $datos["tipo"] : "Contacto";
            require_once("../boletines/classes/class.boConfiguracion.php");
            $objConf = new boConfiguracion();
            if(!empty($id)){
                $objConf->initFromDB($id);
                if($id == $objConf->get("id")){
                    $result = $objConf->actualizar($nombre, $correos, $titulo, $grupoSuscripcion, $pluginEnvio, $tipo);
                    if(is_string($result)){
                        $json["resultado"]["error"] = $result;
                    }else{
                        $json["resultado"] = "ok";
                    }
                }else{
                    $json["resultado"]["error"] = $lang["Identificador de configuración no encontrado"].": ".$id;
                }
            }else{
                $json["resultado"]["error"] = $lang["Identificador de configuración inválido"].": ".$id;
            }
        }
    break;
    case "eliminarConfiguracion":
        $d = jsonStart();
        if(!($Central->conPermiso("Boletines Electronicos,Administrador"))){
            $json["resultado"]["errores"]="No tiene permiso";
	}else{
            $id = isset($d["id"]) ? expect_integer($d["id"]) : 0;
            if(!empty($id)){
                require_once("../boletines/classes/class.boConfiguracion.php");
                $objConf = new boConfiguracion();
                $objConf->initFromDB($id);
                if($id == $objConf->get("id")){
                    $objConf->eliminar();
                    $json["resultado"] = "ok";
                }else{
                    $json["resultado"]["error"] = $lang["Identificador de configuración no encontrado"].": ".$id;
                }
            }else{
                $json["resultado"]["error"] = $lang["Identificador de configuración inválido"].": ".$id;
            }
        }
    break;
    //Reportes
    case "procesarReporte":
        $d = jsonStart();
        $reporte = isset($_REQUEST["reporte"]) ? expect_safe_html($_REQUEST["reporte"]) : "";
        $acc = isset($_REQUEST["acc"]) ? expect_safe_html($_REQUEST["acc"]) : "";
        $requiere = "../boletines/reportes/class.".$reporte.".php";
        $json = "";
        if(file_exists($requiere)){
            require_once($requiere);
            $objReport = new $reporte;
            $limpiar = $objReport->getLimpiar();
            $result = $objReport->ejecuta($acc,$d,$formatoRedondeo,$formatoMiles,$formatoDecimales);
            $json = $result;
        }
    break;
}
jsonEnd($json,$limpiar);
?><? //_FIN_DE_ARCHIVO ?>