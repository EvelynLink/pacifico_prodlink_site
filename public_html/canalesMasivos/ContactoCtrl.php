<?

require_once "../comunes/top.inc.php";
//require_once("../boletines/classes/class.boCorreo.php");
//$cor=new boCorreo();
if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
global $Central,$lang;
$json = array(); $limpiar = array();

switch ($act) {
    //Contacto
    case "inicializarContacto":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $editable = false;
            if($Central->conPermiso("Boletines Electronicos,Administrador de Contacto")){ $editable = true; }
            $verificador = $objContacto->obtenerChallenge();
            $json["resultado"]["editable"] = $editable;
            $json["resultado"]["verificador"] = $verificador;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    case "obtenerConfiguracionCorreos":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $correos = $objContacto->obtenerConfiguracionCorreosString($name);
            $json["resultado"]["correos"] = $correos;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    case "guardarConfiguracionCorreos":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
            $correos = expect_safe_html($d["correos"]);
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $objContacto->guardarConfiguracionCorreos($name,$correos);
            $json["resultado"]["correos"] = $correos;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    case "guardarContacto":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        $contactOnly = isset($d["contact"]) ? expect_safe_html($d["contact"]) : "";
        $tipo = isset($d["type"]) ? expect_safe_html($d["type"]) : "";
        $keywords = isset($d["keywords"]) ? expect_safe_html($d["keywords"]) : "";
        $ultimaVisitada = isset($d["ultimaVisitada"]) ? expect_safe_html($d["ultimaVisitada"]) : "";
        $datos = expect_safe_html($d["datos"]);
        $email = isset($datos["email"]) ? expect_safe_html($datos["email"]) : "";
        $turningH = isset($d["turningH"]) ? expect_safe_html($d["turningH"]) : "";
        $checker = isset($d["checker"]) ? expect_safe_html($d["checker"]) : true;
        $procede = false;
        if(!empty($name)){
            //Obtengo parámetros de envío
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $parametros = $objContacto->obtenerParametrosEnvio($name);
            $subject = isset($parametros["subject"]) ? $parametros["subject"] : "";
            $pluginEnvio = isset($parametros["pluginEnvio"]) ? $parametros["pluginEnvio"] : "";
            if(!empty($subject) && !empty($pluginEnvio)){
                //Valida Codigo verificador?
                if($checker){
                    if(!empty($turningH)){
                        require_once("../comunes/classes/class.sesionPropia.php");
                        $ses = new SesionPropia();
                        $foundClave = $ses->validaCaptcha($turningH);
                        if ($foundClave) {
                            $procede = true;
                        }else{
                            $json["resultado"]["error"] = $lang["Verificador inválido"];
                        }
                    }else{
                        $json["resultado"]["error"] = "SubscriptionNewsletters::".$lang["Verificador requiere el envío de datos de verificación"];
                    }
                }else{
                    $procede = true;
                }
                if($procede){
                    if(!empty($email)){
                        if($tipo == "contact"){
                            if(in_array($contactOnly, array("admin","client","both"))){
                                $result = $objContacto->guardarContacto($name,$datos,$contactOnly,$keywords,$ultimaVisitada,$pluginEnvio,$subject);
                            }else{
                                $result = $lang["El parámetro de configuración onlyContact solo puede contener las opciones admin,client,both"];
                            }
                        }else if($tipo =="subscription"){
                            if(in_array($contactOnly, array("admin","client","both"))){
                                $result = $objContacto->guardarSuscripcion($name,$datos,$contactOnly,$keywords,$ultimaVisitada,$pluginEnvio,$subject);
                            }else{
                                $result = $lang["El parámetro de configuración onlyContact solo puede contener las opciones admin,client,both"];
                            }
                        }else if($tipo =="both"){
                            if(in_array($contactOnly, array("admin","client","both"))){
                                //$result = $objContacto->guardarContacto($name,$datos,"none",$keywords,$ultimaVisitada,$pluginEnvio,$subject);
                                //if(!is_string($result)){
                                    $result = $objContacto->guardarSuscripcion($name,$datos,$contactOnly,$keywords,$ultimaVisitada,$pluginEnvio,$subject);
                                //}
                            }else{
                                $result = $lang["El parámetro de configuración onlyContact solo puede contener las opciones admin,client,both"];
                            }
                        }else{
                            $result = $lang["Tipo no definido"];
                        }
                        if(is_string($result)){
                            $json["resultado"]["error"] = $result;
                        }else{
                            $json["resultado"]=$d;
                        }
                    }else{
                        $json["resultado"]["error"] = $lang["Registre el campo email"];
                    }
                }
            }else{
                $json["resultado"]["error"] = $lang["Parámetros de envío incompletos"].": ".$subject.", ".$pluginEnvio;
            }
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    //Sucripción
    case "inicializarSuscripcion":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $verificador = $objContacto->obtenerChallenge();
            $nombreCategoria = $objContacto->obtenerNombreCategoriaGrupoInteres($name);
            $grupos = $objContacto->obtenerGruposInteres($nombreCategoria);
            $editable = false;
            if($Central->conPermiso("Boletines Electronicos,Administrador de Contacto")){ $editable = true; }
            $json["resultado"]["editable"] = $editable;
            $json["resultado"]["verificador"] = $verificador;
            $json["resultado"]["grupos"] = $grupos;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    case "obtieneGrupos":
        $d = jsonStart();
        $name = expect_safe_html($_REQUEST["name"]);
        $subaccion = expect_pure_alpha($d["subaccion"]);
        switch ($subaccion) {
            case "cargaDatos":
                $ubicacionBase = expect_pure_alphanumeric($d["ubicacionBase"]);
                $categoriaId = $ubicacionBase;
                isset($d["abrir"]) ? $abrir = expect_integer($d["abrir"]) : $abrir = 0;
                $abrirOriginal = $abrir;
                require_once("../boletines/classes/class.boContacto.php");
                $objContacto = new boContacto();
                $nombreCategoria = $objContacto->obtenerNombreCategoriaGrupoInteres($name);
                $ramas = $objContacto->cargarJerarquia($ubicacionBase,$categoriaId,$abrir,$abrirOriginal,$nombreCategoria);
                $json["ramas"] = $ramas;
            break;
        }
    break;
    //Ambos
    case "inicializarSuscripcionContacto":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
        $editable = false;
        if($Central->conPermiso("Boletines Electronicos,Administrador de Contacto")){ $editable = true; }
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $verificador = $objContacto->obtenerChallenge();
            $nombreCategoria = $objContacto->obtenerNombreCategoriaGrupoInteres($name);
            $grupos = $objContacto->obtenerGruposInteres($nombreCategoria);
            $json["resultado"]["editable"] = $editable;
            $json["resultado"]["verificador"] = $verificador;
            $json["resultado"]["grupos"] = $grupos;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    //Registro
    case "inicializarRegistro":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        if(!empty($name)){
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $verificador = $objContacto->obtenerChallenge();
            $json["resultado"]["verificador"] = $verificador;
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
    case "guardarRegistro":
        $d = jsonStart();
        $name = expect_safe_html($d["name"]);
        $keywords = isset($d["keywords"]) ? expect_safe_html($d["keywords"]) : "";
        $ultimaVisitada = isset($d["ultimaVisitada"]) ? expect_safe_html($d["ultimaVisitada"]) : "";
        $datos = expect_safe_html($d["datos"]);
        $email = isset($datos["email"]) ? expect_safe_html($datos["email"]) : "";
        $turningH = isset($d["turningH"]) ? expect_safe_html($d["turningH"]) : "";
        $checker = isset($d["checker"]) ? expect_safe_html($d["checker"]) : true;
        $template = isset($d["templateSend"]) ? expect_safe_html($d["templateSend"]) : "";
        $procede = false;
        if(!empty($name)){
            //Obtengo parámetros de envío
            require_once("../boletines/classes/class.boContacto.php");
            $objContacto = new boContacto();
            $parametros = $objContacto->obtenerParametrosEnvio($name);
            $subject = isset($parametros["subject"]) ? $parametros["subject"] : "";
            $pluginEnvio = isset($parametros["pluginEnvio"]) ? $parametros["pluginEnvio"] : "";
            //Valida Codigo verificador?
            if($checker){
                if(!empty($turningH)){
                    require_once("../comunes/classes/class.sesionPropia.php");
                    $ses = new SesionPropia();
                    $foundClave = $ses->validaCaptcha($turningH);
                    if ($foundClave) {
                        $procede = true;
                    }else{
                        $json["resultado"]["error"] = $lang["Verificador inválido"];
                    }
                }else{
                    $json["resultado"]["error"] = "ContactRecords::".$lang["Verificador requiere el envío de datos de verificación"];
                }
            }else{
                $procede = true;
            }
            if($procede){
                if(!empty($email)){
                    $result = $objContacto->guardarRegistro($name,$datos,$keywords,$ultimaVisitada,$pluginEnvio,$subject,$template);
                    if(is_string($result)){
                        $json["resultado"]["error"] = $result;
                    }else{
                        $json["resultado"]=$d;
                    }
                }else{
                    $json["resultado"]["error"] = $lang["Registre el campo email"];
                }
            }
        }else{
            $json["resultado"]["error"] = $lang["Nombre de directiva inválida"];
        }
    break;
}
jsonEnd($json, $limpiar);
?><?

//_FIN_DE_ARCHIVO ?>