<?
require_once "../comunes/top.inc.php";
require_once ("../comunes/classes/class.clase.php");
require_once ("../comunes/classes/class.coTabulaMongo.php");

//__Descripcion:__ Reporte que permite generar un reporte de bitácora de suscripciones
class rpContactos extends Clase {

    //__Descripción:__ Función que permite ejecutar la acción solicitada desde interfaz al reporte
    //__Inputs:__ $act:string acción del reporte
    //            $d:array configuración json
    //__Outputs:__ $result:array resultado de ejecución
    public function ejecuta($act,$d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        global $Central,$lang;
        $result = array();
        switch ($act){
            case "getData":
                $result = $this->getData($d,$formatoRedondeo,$formatoDecimales,$formatoMiles);
            break;
            case "getConfiguraciones":
                require_once ("../boletines/classes/class.boConfiguracion.php");
                $objConf = new boConfiguracion();
                $configuraciones = $objConf->getConfiguracionPorTipo("Contacto");
                $configuracionesData = $this->getConfiguracionesReporte();
                $todas = array_merge($configuraciones,$configuracionesData);
                $result["resultado"]["tipos"] = $todas;
            break;
        }
        return $result;
    }
    
    //__Descripción:__ Función que permite obtener los parametros de limpieza de la información devuelta por el jsonEnd
    //__Inputs:__ 
    //__Outputs:__ $result:array parametros de limpieza del reporte
    public function getLimpiar(){
         $limpiar = array("boMensajeContacto_"=>"bmc_");
        return $limpiar;
    }
    
    //__Descripción:__ Función que permite permite retornar la información final del reporte
    //__Inputs:__ $d:json datos iniciales
    //            $formatoRedondeo:int numero de decimales a redondear
    //            $formatoDecimales:string separador de decimales
    //            $formatoMiles:string separador de miles
    //__Outputs:__ $result:array datos de reporte
    private function getData($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles="") {
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $limpiar = $this->getLimpiar();
        $ngTabula->setLimpiador($limpiar);
        $tipo = isset($_REQUEST["tipo"]) ? expect_safe_html($_REQUEST["tipo"]) : "";
        $condiciones = array();
        if(!empty($tipo)){
            $condiciones["boMensajeContacto_tipo"] = $tipo;
        }
        $ngTabula->setQueryDatos("boMensajesContacto", $condiciones, array(), array("boMensajeContacto_fecha"=>-1));
        $ngTabula->permiteExportar(true,"Reporte de Contactos");
        $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera) {
            switch ($cabecera) {
                case "boMensajeContacto_fecha": $cabecera = "FECHA CREACIÓN";break;
                case "boMensajeContacto_email": $cabecera = "CORREO ELECTRÓNICO";break;
                case "boMensajeContacto_nombre": $cabecera = "NOMBRES";break;
                case "boMensajeContacto_apellido": $cabecera = "APELLIDOS";break;
                case "boMensajeContacto_identificacion": $cabecera = "IDENTIFICACIÓN";break;
                case "boMensajeContacto_fechaNacimiento": $cabecera = "FECHA NACIMIENTO";break;
                case "boMensajeContacto_telefono": $cabecera = "TELÉFONO";break;
                case "boMensajeContacto_celular": $cabecera = "CELULAR";break;
                case "boMensajeContacto_empresa": $cabecera = "EMPRESA";break;
                case "boMensajeContacto_ciudad": $cabecera = "CIUDAD";break;
                case "boMensajeContacto_direccion": $cabecera = "DIRECCIÓN";break;
                case "boMensajeContacto_mensaje": $cabecera = "MENSAJE";break;
                case "boMensajeContacto_tipo": $cabecera = "ORIGEN";break;
                case "boMensajeContacto_suscripcion": $cabecera = "SUSCRIPCION"; break;
            }
            return $cabecera;
        });
        $ngTabula->setPreparaDatosExportar(function($fila) {
            $fila["boMensajeContacto_fecha"] = date("Y-m-d H:i:s",$fila["boMensajeContacto_fecha"]);
            $fila["boMensajeContacto_fechaNacimiento"] = ($fila["boMensajeContacto_fechaNacimiento"] != 0 && $fila["boMensajeContacto_fechaNacimiento"] != -1) ? date("Y-m-d H:i:s",$fila["boMensajeContacto_fechaNacimiento"]) : "";
            $filaOrdenada = array(
                "boMensajeContacto_fecha"=>$fila["boMensajeContacto_fecha"],
                "boMensajeContacto_email"=>$fila["boMensajeContacto_email"],
                "boMensajeContacto_nombre"=>$fila["boMensajeContacto_nombre"],
                "boMensajeContacto_apellido"=>$fila["boMensajeContacto_apellido"],
                "boMensajeContacto_identificacion"=>$fila["boMensajeContacto_identificacion"],
                "boMensajeContacto_fechaNacimiento"=>$fila["boMensajeContacto_fechaNacimiento"],
                "boMensajeContacto_telefono"=> $fila["boMensajeContacto_telefono"],
                "boMensajeContacto_celular"=> $fila["boMensajeContacto_celular"],
                "boMensajeContacto_empresa"=> $fila["boMensajeContacto_empresa"],
                "boMensajeContacto_ciudad"=> (isset($fila["boMensajeContacto_ciudad"]) ? $fila["boMensajeContacto_ciudad"] : ""),
                "boMensajeContacto_direccion"=> $fila["boMensajeContacto_direccion"],
                "boMensajeContacto_mensaje"=> $fila["boMensajeContacto_mensaje"],
                "boMensajeContacto_tipo"=> $fila["boMensajeContacto_tipo"],
                "boMensajeContacto_suscripcion"=> (isset($fila["boMensajeContacto_suscripcion"]) ? $fila["boMensajeContacto_suscripcion"] : "")
            );
            return $filaOrdenada;
        });
        $respuesta = $ngTabula->responde();
        return $respuesta;
    }
    
    /*Funciones Personalizadas*/
    private function getConfiguracionesReporte(){
        $configuraciones = array();
        $mdb=new MYMONGODB();
        $archivo = $mdb->buscarDistinct("boMensajeContacto_tipo",'boMensajesContacto');
        foreach ($archivo as $doc){
            $configuraciones[] = array("nombre"=>$doc,"titulo"=>$doc);
        }
        return $configuraciones;
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>