<?
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.clase.php");
require_once ("../comunes/classes/class.coTabulaAngular.php");
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");

//__Descripcion:__ Reporte que permite generar un reporte de bitácora de envíos
class rpBitacora extends Clase {

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
            case "enviados":
                $result = $this->getEnviados($d,$formatoRedondeo,$formatoDecimales,$formatoMiles);
            break;
            case "registroBitacora":
                $result = $this->getRegistroBitacora($d,$formatoRedondeo,$formatoDecimales,$formatoMiles);
            break;
            case "leidos":
                $result = $this->getLeidos($d,$formatoRedondeo,$formatoDecimales,$formatoMiles);
            break;
            case "desuscritos":
                $result = $this->getDesuscritos($d, $formatoRedondeo, $formatoDecimales, $formatoMiles);
            break;
            case "cambiarEstadoEnvio":
                $id=expect_integer($d["bitacoraId"]);
                $estado=expect_integer($d["estado"]);
                if($estado != 1){
                    if($estado == 0){
                        $estado = 2;//Pendiente
                    }else{
                        $estado = 0;//Reiniciado
                    }
                    require_once("../boletines/classes/class.boBitacora.php");
                    $bit = new boBitacora();
                    $bit->actualizaEstadoEnvio($estado, $id);
                    $result["resultado"]= $d;
                }else{
                    $result["resultado"]["errores"] = $lang["No se puede detener un envío completo"];
                }
            break;
            case "estadisticasEnvio":
                $bitacoraId = expect_integer($d["bitacoraId"]);
                $estadistica = array();
                $db = new MYSQLDB();
                $sql = $db->mkSQL("SELECT * FROM bobitacora WHERE boBitacora_id =%N",$bitacoraId);
                if($db->query($sql)){
                   $row = $db->fetchRow();
                    $strLista = $row["boBitacora_marcados"];
                    if($strLista != ""){
                        require_once("../boletines/classes/class.boJerarquia.php");
                        $jer = new boJerarquia();
                        $jerMarcadas = $jer->obtieneInfoJerarquiaLista($strLista);
                        $estadistica = $jerMarcadas;
                    } 
                } 
                $result["resultado"]["marcados"]=$estadistica;
            break;
            case "detalleDesuscritos":
                $d = jsonStart();
                $bitacoraId = expect_integer($d["bitacoraId"]);
                require_once("../boletines/classes/class.boJerarquia.php");
                $jer = new boJerarquia();
                $desuscritos = array();
                $db = new MYSQLDB();
                $sql = $db->mkSQL("SELECT * FROM boregistro WHERE boRegistro_bitacoraId=%N and boRegistro_tipo=%Q",$bitacoraId,'DeSuscripcion');
                if($db->query($sql)){
                   $row = $db->fetchRow();
                   $strLista = $row["boRegistro_desuscritos"];
                   if($strLista != ""){
                       $jerDesuscritos = $jer->obtieneInfoJerarquiaLista($strLista);
                       $desuscritos = $jerDesuscritos;
                   } 
                }
                $result["resultado"]["jerarquias"]=$desuscritos;
            break;
        }
        return $result;
    }
    
    //__Descripción:__ Función que permite obtener los parametros de limpieza de la información devuelta por el jsonEnd
    //__Inputs:__ 
    //__Outputs:__ $result:array parametros de limpieza del reporte
    public function getLimpiar(){
        $limpiar = array("boBitacora_"=>"bbi_",
                        "boContenido_"=>"boc_",
                        "usUsuarios_"=>"usu_",
                        "categorias_"=>"cat_",
                        "alEnvelopes_"=>"aev_",
                        "boRegistro_"=>"brg_",
                        "boJerarquia_"=>"boj_",
                        "bolRegistroBitacora_"=>"brb_");
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
                        boContenido_nombre,
                        boContenido_categoriaId,
                        boBitacora_id,
                        boBitacora_userId,
                        usUsuarios_nombres,
                        usUsuarios_apellidos,
                        categorias_nombre,
                        GROUP_CONCAT(distinct boJerarquia_nombre) bbi_jerarquias,
                        boBitacora_archivoEnvio,
                        boBitacora_envioProgramado,
                        boBitacora_horaEnvio,
                        boBitacora_creado,
                        boBitacora_filasLectura,
                        boBitacora_filasTotal,
                        '' as bbi_descripEstado,
                        boBitacora_enviados,
                        boBitacora_noEnviados,
                        boBitacora_estadoEnvio,
                        boBitacora_leidos,
                        boBitacora_desuscritos
                    FROM bobitacora
                        LEFT JOIN bocontenido ON boContenido_id = boBitacora_contenidoId
                        LEFT JOIN categorias ON categorias_id = boBitacora_contenidoId
                        LEFT JOIN ususuarios ON usUsuarios_id = boBitacora_userId
                        LEFT JOIN bojerarquia ON FIND_IN_SET(boJerarquia_id,boBitacora_marcados)
                    GROUP BY boBitacora_id");
         $sqlCuenta = $db->mkSQL("SELECT * FROM bobitacora");
         //Ejecución de tabula con clausula
         $ngTabula = new coTabulaAngular();
         $ngTabula->setInput($d);
         $ngTabula->setLimpiador($limpiar);
         $ngTabula->setOrdenDefault("boBitacora_id DESC");
         $ngTabula->setQueryDatos($sql);
         $ngTabula->setCamposConTabla(false);
         $ngTabula->permiteExportar(true,"BitacoraBoletines");
         $ngTabula->setQueryCuenta($sqlCuenta);
         $ngTabula->setPreparaDatos(function($fila){
             global $lang;
             $fila["boContenido_nombre"] = !empty($fila["boContenido_nombre"]) ?  $fila["boContenido_nombre"] : $fila["categorias_nombre"];
             $fila["bbi_descripEstado"] = $fila["boBitacora_estadoEnvio"] == 1 ? $lang["Enviado"] : $lang["No Enviado"];
             return $fila;
         });
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "boContenido_nombre": $cabecera = "NOMBRE BOLETÍN";break;
                case "usUsuarios_nombres": $cabecera = "ENVIADO POR";break;
                case "boBitacora_envioProgramado": $cabecera = "FECHA ENVÍO";break;
                case "boBitacora_horaEnvio": $cabecera = "HORA PROGRAMADA";break;
                case "boBitacora_creado": $cabecera = "FECHA CREACIÓN";break;
                case "boBitacora_filasLectura": $cabecera = "FILAS LEÍDAS";break;
                case "boBitacora_estadoEnvio": $cabecera = "ESTADO";break;
                case "bbi_jerarquias": $cabecera = "MARCADOS";break;
                case "boBitacora_enviados": $cabecera = "ENVIADOS";break;
                case "boBitacora_noEnviados": $cabecera = "NO ENVIADOS";break;
                case "boBitacora_leidos": $cabecera = "LEÍDOS";break;
                case "boBitacora_desuscritos": $cabecera = "DESUSCRITOS";break;
            }
            return $cabecera;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
            $fila["boContenido_nombre"] = !empty($fila["boContenido_nombre"]) ?  $fila["boContenido_nombre"] : $fila["categorias_nombre"];
            $fila["usUsuarios_nombres"] = $fila["usUsuarios_nombres"]." ".$fila["usUsuarios_apellidos"];
            $fila["boBitacora_filasLectura"] = $fila["boBitacora_filasLectura"]." DE ".$fila["boBitacora_filasTotal"];
            $fila["boBitacora_envioProgramado"] = $fila["boBitacora_envioProgramado"] > 0 ? date("Y-m-d",$fila["boBitacora_envioProgramado"]) : ($fila["boBitacora_creado"] > 0 ? date("Y-m-d h:i:s",$fila["boBitacora_creado"]) : "");
            $estado = "Enviado";
            if($fila["boBitacora_estadoEnvio"] == 0){
                $estado = "No Enviado";
            }else if($fila["boBitacora_estadoEnvio"] == 2){
                $estado = "Detenido";
            }
            $fila["boBitacora_estadoEnvio"] = $estado;
            unset($fila["boBitacora_id"]);
            unset($fila["boBitacora_userId"]);
            unset($fila["boBitacora_fechaEnvioFrmt"]);
            unset($fila["boBitacora_descripEstado"]);
            unset($fila["boBitacora_filasTotal"]);
            unset($fila["boBitacora_archivoEnvio"]);
            unset($fila["bbi_descripEstado"]);
            unset($fila["usUsuarios_apellidos"]);
            unset($fila["categorias_nombre"]);
            unset($fila["boBitacora_creado"]);
            return $fila; 
        });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
    private function getEnviados($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        $bitacoraId = expect_integer($_REQUEST["bitacoraId"]);
        //Arma Consulta sql
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT 
                    alEnvelopes_id,
                    alEnvelopes_subject,
                    alEnvelopes_to,
                    alEnvelopes_when,
                    0 as alEnvelopes_whenFrmt,
                    alEnvelopes_success
                 FROM 
                    alenvelopes
                 WHERE alEnvelopes_bitacoraId=%N ORDER BY alEnvelopes_success ASC",$bitacoraId);      
        //Ejecución de tabula con clausula
        //boCorreo_nombre as alEnvelopes_nombre
        //LEFT JOIN bocorreo ON trim(alEnvelopes_to) = trim(boCorreo_email)
        $limpiar = $this->getLimpiar();
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("alEnvelopes_id");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(true,"BitacoraEnviados");
        $ngTabula->setPreparaDatos(function($fila){
            global $lang;
            //Fecha
            if($fila["alEnvelopes_when"] > 0){
                $fila["alEnvelopes_whenFrmt"] = date('Y-m-d h:i:s',$fila["alEnvelopes_when"]);
            }else{
                $fila["alEnvelopes_when"] = '';
                $fila["alEnvelopes_whenFrmt"] = '';
            }
            //Estado
            $fila["alEnvelopes_success"] = $fila["alEnvelopes_success"] == 1 ? $lang["Enviado"] : $lang["No Enviado"];
            return $fila;
        });
        $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "alEnvelopes_subject": $cabecera="BOLETÍN"; break;
                case "alEnvelopes_to": $cabecera="PARA"; break;
                case "alEnvelopes_when": $cabecera="FECHA ENVÍO"; break;
                case "alEnvelopes_success": $cabecera="ENVIADO"; break;
            }
            return $cabecera;
        });
        $ngTabula->setPreparaDatosExportar(function($fila){
             unset($fila["alEnvelopes_id"]);
             unset($fila["alEnvelopes_whenFrmt"]);
             $fila["alEnvelopes_when"] = $fila["alEnvelopes_when"] > 0 ? date("Y-m-d h:i:s",$fila["alEnvelopes_when"]) : "";
             $fila["alEnvelopes_success"] = $fila["alEnvelopes_success"] == 1 ? "Enviado" : "No Enviado";
             return $fila; 
        });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
    /*---------------------------------------ADICIONAL---------------------------------------------------------------*/
     private function getRegistroBitacora($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        $bitacoraId = expect_integer($_REQUEST["bitacoraId"]);
        $campos=["bolRegistroBitacora_nombreBoletin",
                 "bolRegistroBitacora_correoElectronico",
                 "bolRegistroBitacora_fecha",
                 "bolRegistroBitacora_estado",
                 "bolRegistroBitacora_nota"];
        $mongo= new MYMONGODB();
        $condition=['bolRegistroBitacora_idBitacora'=>(int)$bitacoraId];
        $coleccion="bolRegistroBitacora";
        $limpiar = $this->getLimpiar();
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos("bolRegistroBitacora",$condition,$campos,array("bolRegistroBitacora_fecha"=>-1));
        $ngTabula->permiteExportar(true,"BitacoraGeneral");
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "bolRegistroBitacora_nombreBoletin": $cabecera="BOLETÍN"; break;
                case "bolRegistroBitacora_correoElectronico": $cabecera="PARA"; break;
                case "bolRegistroBitacora_fecha": $cabecera="FECHA ENVÍO"; break;
                case "bolRegistroBitacora_estado": $cabecera="ESTADO"; break;
                case "bolRegistroBitacora_nota":$cabecera="NOTA"; break;
            }
            return $cabecera;
        });
        $ngTabula->setPreparaDatosExportar(function($fila){
            unset($fila["_id"]);
            unset($fila["id"]);
            $fila["bolRegistroBitacora_fecha"] = !empty($fila["bolRegistroBitacora_fecha"]) ? date("Y-m-d h:i:s",$fila["bolRegistroBitacora_fecha"]) : "";
            return $fila; 
        });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
    /*--------------------------------------------------------------------------------------------------------------*/
    private function getLeidos($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        $bitacoraId = expect_integer($_REQUEST["bitacoraId"]);
         //Arma Consulta sql
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT *,'' as boRegistro_fechaRegistroFrmt FROM boregistro
                           WHERE boRegistro_tipo = 'Lectura' and boRegistro_bitacoraId=%N",$bitacoraId);
        //Ejecución de tabula con clausula
        $limpiar = $this->getLimpiar();
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("boRegistro_id");
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(true,"BitacoraLeidos");
        $ngTabula->setPreparaDatos(function($fila){
            //Fecha
            if($fila["boRegistro_fechaRegistro"] > 0){
                $fila["boRegistro_fechaRegistroFrmt"] = date('Y-m-d h:i:s',$fila["boRegistro_fechaRegistro"]);
            }
            return $fila;
         });
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "boRegistro_correo": $cabecera="CORREO"; break;
                case "boRegistro_tipo": $cabecera="TIPO"; break;
                case "boRegistro_fechaRegistro": $cabecera="FECHA LECTURA"; break;
                case "boRegistro_visitas": $cabecera="VISITAS"; break;
            }
            return $cabecera;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
            unset($fila["boRegistro_id"]);
            unset($fila["boRegistro_bitacoraId"]);
            unset($fila["boRegistro_correoId"]);
            unset($fila["boRegistro_desuscritos"]);
            unset($fila["boRegistro_fechaRegistroFrmt"]);
            $fila["boRegistro_fechaRegistro"] = $fila["boRegistro_fechaRegistro"] > 0 ? date("Y-m-d h:i:s",$fila["boRegistro_fechaRegistro"]) : "";
            return $fila;
         });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
    private function getDesuscritos($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        $bitacoraId = expect_integer($_REQUEST["bitacoraId"]);
         //Arma Consulta sql
        $db = new MYSQLDB();
         $sql = $db->mkSQL("SELECT *,'' as boRegistro_fechaRegistroFrmt,0 as boRegistro_activo FROM boregistro
                            WHERE boRegistro_tipo = 'DeSuscripcion' and boRegistro_bitacoraId=%N",$bitacoraId);
         //Ejecución de tabula con clausula
         $limpiar= $this->getLimpiar();
         $ngTabula = new coTabulaAngular();
         $ngTabula->setInput($d);
         $ngTabula->setLimpiador($limpiar);
         $ngTabula->setOrdenDefault("boRegistro_id");
         $ngTabula->setQueryDatos($sql);
         $ngTabula->setCamposConTabla(false);
         $ngTabula->permiteExportar(true,"BitacoraDesuscritos");
         $ngTabula->setPreparaDatos(function($fila){
             //Fecha
             if($fila["boRegistro_fechaRegistro"] > 0){
                 $fila["boRegistro_fechaRegistroFrmt"] = date('Y-m-d h:i:s',$fila["boRegistro_fechaRegistro"]);
             }
             return $fila;
         });
         $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera){
            switch ($cabecera) {
                case "boRegistro_correo": $cabecera="CORREO"; break;
                case "boRegistro_tipo": $cabecera="TIPO"; break;
                case "boRegistro_fechaRegistro": $cabecera="ÚLTIMA DESUSCRIPCIÓN"; break;
                case "boRegistro_visitas": $cabecera="VISITAS"; break;
            }
            return $cabecera;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
             unset($fila["boRegistro_id"]);
             unset($fila["boRegistro_bitacoraId"]);
             unset($fila["boRegistro_correoId"]);
             unset($fila["boRegistro_visitas"]);
             unset($fila["boRegistro_desuscritos"]);
             unset($fila["boRegistro_fechaRegistroFrmt"]);
             unset($fila["boRegistro_activo"]);
             $fila["boRegistro_fechaRegistro"] = $fila["boRegistro_fechaRegistro"] > 0 ? date("Y-m-d h:i:s",$fila["boRegistro_fechaRegistro"]) : "";
             return $fila;
         });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>