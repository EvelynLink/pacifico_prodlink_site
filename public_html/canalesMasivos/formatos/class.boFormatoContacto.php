<?
//__Descripción:__ Formato: Url Template notificacion de contacto
//                 Funcion: ContactoCtrl
//__Inputs:__ 
//__Outputs:__ 
require_once("../comunes/classes/class.clase.php");

class boFormatoContacto extends Clase {

    protected $correos = array();
    
    function __construct() {}
    
    //__Descripción:__ Función que permite indicar el nombre del archivo final a generar
    //__Inputs:__ 
    //__Outputs:__ $result:string nombre de archivo
    function getNombre(){
        global $lang;
        $fecha = date("Ymd",time());
        return $lang["CONTACTO"]."_".$fecha;
    }
    
    //__Descripción:__ Función que permite indicar la ubicación donde el archivo se generará
    //__Inputs:__ 
    //__Outputs:__ $result:string ubicación de archivo
    function getUbicacionArchivo(){
        return "boletines/contactos/";
    }
    
    //__Descripción:__ Función que permite obtener la información requerida para el ciclo de repetición de envíos de correo
    //__Inputs:__ 
    //__Outputs:__ $result:array
    function preparaDatos(){
        $empresaId = 35; //MRBOOKS
        return $empresaId;
    }
    
    //__Descripción:__ Función que permite obtener los datos que requiere el formato
    //__Inputs:__ $gpdf:object objeto proveniende de coGeneraPdf para el envío de correos con opción de generación de pdf
    //__Outputs:__ $result:array datos que serán recibidos por el formato
    function getDatos($gpdf,$titulo,$empresaId,$correos,$detalle,$categoriasMarcadas,$ultimaVisitada){
        //Datos de formato
        $urlLogo = ""; $direccionEmpresa = "";$nombreComercialEmpresa=""; $telf1 = ""; $telf2 = ""; $telf3 = ""; $tel1Nota = ""; $tel2Nota = ""; $tel3Nota = "";
        if($empresaId > 0){
            require_once ("../usuarios/classes/class.usEmpresa.php");
            $objEmpre = new usEmpresa();
            $objEmpre->initFromDB($empresaId);
            $direccionEmpresa = $objEmpre->get("direccion");
            $nombreComercialEmpresa = $objEmpre->get("nombreComercial");
            $telefonos = $objEmpre->getTelfs();
            $telf1 = isset($telefonos[0]) ? $telefonos[0]->get("area").$telefonos[0]->get("telefono") : "";
            $tel1Nota = isset($telefonos[0]) ? $telefonos[0]->get("comentario") : "";
            $telf2 = isset($telefonos[1]) ? $telefonos[1]->get("area").$telefonos[0]->get("telefono") : "";
            $tel2Nota = isset($telefonos[1]) ? $telefonos[1]->get("comentario") : "";
            $telf3 = isset($telefonos[2]) ? $telefonos[2]->get("area").$telefonos[0]->get("telefono") : "";
            $tel3Nota = isset($telefonos[2]) ? $telefonos[2]->get("comentario") : "";
            if(!empty($objEmpre->get("urlLogo"))){
               $urlLogo = "img src=\"".HTTPHOST."/".$objEmpre->get("urlLogo")."\" width=\"130\" alt=\"logo MrBooks\"/";
            }
        }
        $seccionSuscripciones = "";
        if (!empty($categoriasMarcadas)) {
            $seccionSuscripciones="<b>CATEGORÍAS DE SUSCRIPCIÓN</b>: " . $categoriasMarcadas;
        }
        if(count($correos)){
            $datos=array(
                "fecha"=>$gpdf->getFechaHumana(time()),
                "titulo"=>$titulo,
                "ultimaseccion"=>$ultimaVisitada, //"http://librimundip.mrbooks.com/lm#/contacto",
                "categoriasMarcadas"=>$seccionSuscripciones,
                "__tablaDetalle"=>$detalle,
                //básicos de formato
                "direccionEmpresa"=>$direccionEmpresa,
                "nombreComercialEmpresa"=>$nombreComercialEmpresa,
                "telf1"=>$telf1,"nt1"=>$tel1Nota,
                "telf2"=>$telf2,"nt2"=>$tel2Nota,
                "telf3"=>$telf3,"nt3"=>$tel3Nota,
                "urlLogo"=> $urlLogo,
                //"BASEURL"=>HTTPHOST."/" //BASEURL
            );
        }else{
            trigger_error("PLUGIN CONTACTO :: No se encontró correos para envío de datos de contacto");
        }
        return $datos;
    }
    
    //__Descripción:__ Función que permite setear correos a enviar
    //__Inputs:__ 
    //__Outputs:__ 
    function setCorreos($correos){
        $this->correos = array();
        $listaEnvio=array();
        foreach ($correos as $email){ 
            $listaEnvio[] = $email; 

        }
        $this->correos = $listaEnvio;
    }
    
    //__Descripción:__ Función que permite definir los correos a los cuales se realizará el envío del formato
    //                 si se encuentra configurado para envío
    //__Inputs:__ 
    //__Outputs:__ 
    function getCorreos(){
        return $this->correos;
    }
    
    //__Descripción:__ Función que permite obtener la descripción del email del formato en caso de ser enviado
    //__Inputs:__ 
    //__Outputs:__ 
    function getDescripcionEmail(){
        return "";
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>