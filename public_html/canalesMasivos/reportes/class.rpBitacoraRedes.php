<?
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.clase.php");
require_once ("../comunes/classes/class.coTabulaAngular.php");

//__Descripcion:__ Reporte que permite generar un reporte de bitácora de publicaciones
class rpBitacoraRedes extends Clase {

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
                $result = $this->getEnviados($d, $formatoRedondeo, $formatoDecimales, $formatoMiles);
            break;
            case "actualizarPreview":
                $bitacoraId = expect_integer($d["bitacoraId"]);
                $filter = $d["filter"];
                $ans = '';
                $db = new MYSQLDB();
                $sql = $db->mkSQL("SELECT boBitacoraRedes_conexId FROM bobitacoraredes WHERE boBitacoraRedes_id=%N",$bitacoraId);
                $keys = "";
                if ($db->query($sql)) {
                    while ($row = $db->fetchRow()) {
                        $keys = $row["boBitacoraRedes_conexId"];
                    }
                }
                if ($keys != "") {
                    $parts = explode("#", $keys);
                    if (count($parts) > 1) {
                        $config_id = $parts[0]; //Adset ID
                        $ad_id = $parts[1]; //AD published ID
                        require_once("../boletines/classes/class.boRedesConf.php");
                        $preview = $face = new boRedesConf($config_id);
                        $resp = $preview->getPreview($ad_id, $filter);
                        if ($resp['op']) {
                            $ans = $resp['data'];
                        }
                    }
                }
                $result['respuesta'] = $ans;
            break;
            case "actualizarEstado":
                $bitacoraId = expect_integer($d["bitacoraId"]);
                $state = $d["estado"];
                $ans = [ 'op' => 'false', 'data' => '' ];
                //Busca bitacora para extrar AD ID en Facebook
                $db = new MYSQLDB();
                $sql = $db->mkSQL("SELECT boBitacoraRedes_conexId FROM bobitacoraredes WHERE boBitacoraRedes_id=%N",$bitacoraId);
                if ($db->query($sql)) {
                    while ($row = $db->fetchRow()) {
                        $keys = $row["boBitacoraRedes_conexId"];
                    }
                }
                if ($keys != "") {
                    $parts = explode("#", $keys);
                    if (count($parts) > 1) {
                        $config_id = $parts[0]; //Adset ID
                        $ad_id = $parts[1]; //AD published ID
                        require_once("../boletines/classes/class.boRedesConf.php");
                        $preview = $face = new boRedesConf($config_id);
                        $resp = $preview->changeAdStatus($ad_id, $state, $bitacoraId);
                        if ($resp['op']) {
                            $ans = [ 'op' => "true", 'data' => $resp['data'] ];
                        }
                    }
                }
                $result['respuesta'] = $ans;
                break;
        }
        return $result;
    }
    
    //__Descripción:__ Función que permite obtener los parametros de limpieza de la información devuelta por el jsonEnd
    //__Inputs:__ 
    //__Outputs:__ $result:array parametros de limpieza del reporte
    public function getLimpiar(){
         $limpiar = array("boBitacoraRedes_"=>"bbi_",
                            "boContenido_"=>"boc_",
                            "usUsuarios_"=>"usu_");
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
                        boBitacoraRedes_id,
                        boBitacoraRedes_userId,
                        usUsuarios_nombres,
                        usUsuarios_apellidos,
                        boBitacoraRedes_envioProgramado,
                        boBitacoraRedes_horaEnvio,
                        boBitacoraRedes_creado,
                        boBitacoraRedes_elementosTotal,
                        boBitacoraRedes_estadoPublicacion,
                        boContenido_nombre,
                        GROUP_CONCAT(distinct boJerarquia_nombre) bbi_jerarquias,
                        boBitacoraRedes_tipo
                    FROM bobitacoraredes
                        LEFT JOIN bocontenido ON boContenido_id = boBitacoraRedes_contenidoId
                        LEFT JOIN ususuarios ON usUsuarios_id = boBitacoraRedes_userId
                        LEFT JOIN bojerarquia ON FIND_IN_SET(boJerarquia_id,boBitacoraRedes_marcados)
                    GROUP BY boBitacoraRedes_id");
         $sqlCuenta = $db->mkSQL("SELECT * FROM bobitacoraredes");
         $ngTabula = new coTabulaAngular();
         $ngTabula->setInput($d);
         $ngTabula->setLimpiador($limpiar);
         $ngTabula->setOrdenDefault("boBitacoraRedes_id DESC");
         $ngTabula->setQueryDatos($sql);
         $ngTabula->setCamposConTabla(false);
         $ngTabula->permiteExportar(true);
         $ngTabula->setQueryCuenta($sqlCuenta);
         $ngTabula->setPreparaDatos(function($fila){
             return $fila;
         });
         $ngTabula->setPreparaDatosExportar(function($fila){
            unset($fila["boBitacoraRedes_id"]);
            unset($fila["boBitacoraRedes_userId"]);
            $fila["boBitacoraRedes_envioProgramado"] = $fila["boBitacoraRedes_envioProgramado"] > 0 ? date("Y-m-d",$fila["boBitacoraRedes_envioProgramado"]) : "";
            $fila["boBitacoraRedes_creado"] = $fila["boBitacoraRedes_creado"] > 0 ? date("Y-m-d h:i:s",$fila["boBitacoraRedes_creado"]) : "";
            return $fila; 
        });
        $respuesta=$ngTabula->responde();
        return $respuesta;
    }
    private function getEnviados($d,$formatoRedondeo=2,$formatoDecimales=".",$formatoMiles=""){
        if (isset($_REQUEST["bitacoraId"]) && isset($_REQUEST["filter"])) {
            $bitacoraId = expect_integer($_REQUEST["bitacoraId"]);
            $filter = expect_safe_html($_REQUEST["filter"]);
            $obj = [ 'cuantos' => 0, 'extra' => [], 'filas' => [] ];
            $connect = 'no';
            $db = new MYSQLDB();
            $sql = $db->mkSQL("SELECT boBitacoraRedes_conexId FROM bobitacoraredes WHERE boBitacoraRedes_id=%N",$bitacoraId);
            $keys = "";
            if ($db->query($sql)) {
                while ($row = $db->fetchRow()) {
                    $keys = $row["boBitacoraRedes_conexId"];
                }
            }
            if ($keys != "") {
                $parts = explode("#", $keys);
                if (count($parts) > 1) {
                    $config_id = $parts[0]; //Adset ID
                    $ad_id = $parts[1]; //AD published ID
                    require_once("../boletines/classes/class.boRedesConf.php");
                    $insights = $face = new boRedesConf();
                    $resp = $insights->getInsights($ad_id, $config_id, $filter);
                    if ($resp['op']) {
                        $extra = [];
                        if (isset($resp['message'])) {
                            $formated = $resp['message'];
                            $formated['ad_info']['preview'] = $formated['preview'];
                            $extra = [
                                'account_name' => $formated['account_name'],
                                'adset_name' => $formated['adset_name'],
                                'campaign_name' => $formated['campaign_name'],
                                'preview' => $formated['preview'],
                                'ad_info' => $formated['ad_info']
                            ];
                            $obj = [
                                'cuantos' => count($resp['message']),
                                'extra' => $extra,
                                'filas' => ($resp['message']['ctr'] != "") ? [$resp['message']] : []
                            ];
                        }
                        $connect = 'si';
                    }
                } else {
                    //There isn't relation ID
                }
            } else {
                //There isn't relation ID
            }
            $json = $obj;
            $json["extra"]["connect"] = $connect;
            $json["extra"]["noEditable"] = true;
            $json["extra"]["read"] = true;
            $json["extra"]["write"] = true;
            $json["extra"]["config"] = true;
        } else {
            $json = [ 'cuantos' => 0, 'extra' => [], 'filas' => [] ];
            $json["extra"]["connect"] = 'no';
            $json["extra"]["noEditable"] = true;
            $json["extra"]["read"] = true;
            $json["extra"]["write"] = true;
            $json["extra"]["config"] = true;
        }
        return $json;
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>