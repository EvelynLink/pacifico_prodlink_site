<?
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.clase.php");
require_once ("../comunes/classes/class.coTabulaAngular.php");

//__Descripcion:__ Reporte que permite generar un reporte de bitácora de suscripciones
class rpSuscripciones extends Clase {

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
            case "eliminarCorreoYasociaciones":
                $id = isset($d["id"]) ? expect_integer($d["id"]) : 0;
                if($id > 0){
                    require_once("../boletines/classes/class.boCorreo.php");
                    $bco = new boCorreo();
                    $bco->eliminarCorreoSuscripciones($id);
                }
                $result["resultado"] = $d;
            break;
        }
        return $result;
    }
    
    //__Descripción:__ Función que permite obtener los parametros de limpieza de la información devuelta por el jsonEnd
    //__Inputs:__ 
    //__Outputs:__ $result:array parametros de limpieza del reporte
    public function getLimpiar(){
         $limpiar = array( 
                        "boCategoria_"=>"bca_",
                        "boJerarquia_"=>"bjr_",
                        "boCorreo_"=>"bco_",
                        "boCorreoxJerarquia_"=>"bsc_");
        return $limpiar;
    }
    
    //__Descripción:__ Función que permite permite retornar la información final del reporte
    //__Inputs:__ $d:json datos iniciales
    //            $formatoRedondeo:int numero de decimales a redondear
    //            $formatoDecimales:string separador de decimales
    //            $formatoMiles:string separador de miles
    //__Outputs:__ $result:array datos de reporte
    private function getData($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles="") {
        $db = new MYSQLDB();
        $limpiar = $this->getLimpiar();
        $sql = $db->mkSQL("SELECT 
                    boCorreoxJerarquia_fechaCreacion
                    ,boCorreo_email
                    ,boCorreo_nombre 
                    ,boCorreo_identificacion 
                    ,boJerarquia_descripcion 
                    ,boJerarquia_nombre
                    ,boCategoria_nombre
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
                WHERE boJerarquia_activo=%N AND boCorreo_activo =%N",1,1);
         $ngTabula = new coTabulaAngular();
         $ngTabula->setInput($d);
         $ngTabula->setLimpiador($limpiar);
         $ngTabula->setOrdenDefault("boCorreoxJerarquia_fechaCreacion DESC, boCorreo_nombre ASC");
         $ngTabula->setQueryDatos($sql);
         $ngTabula->setCamposConTabla(false);
         $ngTabula->permiteExportar(true,"ReporteSuscripciones");
         $ngTabula->setPreparaDatos(function($fila){
             if($fila["boCorreo_fechaNacimiento"] > 0){
                 $fila["boCorreo_fechaNacimientoFrmt"] = date('Y-m-d',$fila["boCorreo_fechaNacimiento"]);
             }else{
                 $fila["boCorreo_fechaNacimientoFrmt"] = '';
             }
             $aBuscar = array("á","é","í","ó","ú");
             $aReemplazar=array("Á","É","Í","Ó","Ú");
             $fila["boJerarquia_nombre"] = str_replace($aBuscar,$aReemplazar,strtoupper($fila["boJerarquia_nombre"]));
             $fila["boCategoria_nombre"] = str_replace($aBuscar,$aReemplazar,strtoupper($fila["boCategoria_nombre"]));
             $fila["boCorreo_empresa"] = str_replace($aBuscar,$aReemplazar,strtoupper($fila["boCorreo_empresa"]));
             $fila["boCorreo_direccion"] = str_replace($aBuscar,$aReemplazar,strtoupper($fila["boCorreo_direccion"]));
             return $fila;
         });
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "boCorreo_fechaCreacion": $cabecera="FECHA SUSCRIPCION"; break;
                case "boCorreo_correo": $cabecera="CORREO";break;
                case "boCorreo_nombre": $cabecera="NOMBRE";break;
                case "boCorreo_identificacion": $cabecera="IDENTIFICACIÓN";break;
                case "boCorreo_fechaNacimiento": $cabecera="FECHA DE NACIMIENTO";break;
                case "boCorreo_fechaNacimientoFrmt": $cabecera="FECHA DE NACIMIENTO";break;
                case "boCorreo_telefono": $cabecera="TELÉFONO";break;
                case "boCorreo_celular": $cabecera="CELULAR";break;
                case "boCorreo_empresa": $cabecera="EMPRESA";break;
                case "boCorreo_direccion": $cabecera="DIRECCIÓN";break;
                case "boJerarquia_nombre": $cabecera="JERARQUÍA";break;
                case "boCategoria_nombre": $cabecera="CATEGORÍA";break;
            }
            return $cabecera;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
             unset($fila["boCategoria_id"]);
             unset($fila["boJerarquia_id"]);
             unset($fila["boJerarquia_padreId"]);
             unset($fila["boCorreo_id"]);
             unset($fila["boCorreoxJerarquia_id"]);
             unset($fila["boJerarquia_descripcion"]);
             $fila["boCorreo_fechaCreacion"] = $fila["boCorreo_fechaCreacion"] > 0 ? date('Y-m-d',$fila["boCorreo_fechaCreacion"]) : "";
             $fila["boCorreo_fechaNacimientoFrmt"] = ($fila["boCorreo_fechaNacimiento"] != -1 && $fila["boCorreo_fechaNacimiento"] != 0) ? date('Y-m-d',$fila["boCorreo_fechaNacimiento"]) : "";
             unset($fila["boCorreo_fechaNacimiento"]);
             return $fila;
         });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>