<?
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaAngular.php";
if(!isset($_REQUEST["act"])){
	exit;
}
$act=expect_pure_alphanumeric($_REQUEST["act"]);
$json=array();
$limpiar=array(); 

switch($act){
    case "validaAcceso":
        $g = jsonStart();
	$usrreg=expect_safe_html($g["usrreg"]);
        $idCorreo=  expect_integer($g["correo"]);
        if($usrreg != "" && $idCorreo > 0){
            $hashValida =  hash("md5", $idCorreo."mhfg$%/(2345&!xs");
            if($hashValida == $usrreg){
                $json["resultado"] = "1";
            }else{
                $json["resultado"] = $lang["Acceso inválido, consulte con el administrador del sistema."];
            }
        }else{
            $json["resultado"] = $lang["Acceso inválido, consulte con el administrador del sistema."];
        }
        break;
    case "reporteSuscripciones":
        $d = jsonStart();
        $limpiar=array("boCategoria_"=>"bca_","boJerarquia_"=>"bjr_","boCorreo_"=>"bco_","boCorreoxJerarquia_"=>"bcj_");
        $correoId = expect_integer($_REQUEST["id"]);
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("boCategoria_id ");
        $ngTabula->setQueryDatos("SELECT 
                                        boCategoria_id 
                                        ,boCategoria_nombre 
                                        ,boJerarquia_id 
                                        ,boJerarquia_descripcion 
                                        ,boJerarquia_nombre 
                                        ,boJerarquia_padreId
                                        ,boCorreo_id
                                        ,boCorreoxJerarquia_id
                                    FROM `bocategoria` 
                                        INNER JOIN bojerarquia ON boCategoria_id = boJerarquia_categoriaId 
                                        INNER JOIN bocorreoxjerarquia ON boJerarquia_id = boCorreoxJerarquia_jerarquiaId 
                                        INNER JOIN bocorreo ON boCorreo_id = boCorreoxJerarquia_correoId 
                                    WHERE boJerarquia_activo = 1 AND boCorreo_activo = 1 AND boCorreo_id = ".$correoId);
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(false);
        $json=$ngTabula->responde();
    break;
    case "obtieneDatos":
        $g = jsonStart();
        $correoId = expect_integer($g["correo"]);
        $db = new MYSQLDB();
	$sql = $db->mkSQL("SELECT * 
                              FROM 
                                   bocorreo 
                              WHERE boCorreo_id=%N",$correoId);
        if($db->query($sql)){
            $row=$db->fetchRow();
            $json["resultado"]= $row;
        }else{
            $json["resultado"]= "";
        }
        break;
    case "eliminaSuscripcion":
	$g = jsonStart();
	$g["corJer_id"]=expect_integer($g["bcj_id"]);
        $bitacoraId=expect_integer($_REQUEST["bitacoraId"]); 
        $db = new MYSQLDB();
        require_once("../boletines/classes/class.boCorreoxJerarquia.php");
        require_once("../boletines/classes/class.boRegistro.php");
        $reg = new boRegistro();
        $suscribe = new boCorreoxJerarquia();
        $dtCorreo = $suscribe->obtieneDetalleCorreoJerarquia($g["corJer_id"]);
        $id = $suscribe->eliminaSuscripcionXId($g["corJer_id"]);
        $reg->insertaRegistro($bitacoraId, $dtCorreo["correo"], $dtCorreo["correoId"], 'DeSuscripcion', time(),$dtCorreo["jerarquiaId"]);
	$json["resultado"]=$id;
    break;
}

jsonEnd($json,$limpiar);
?><? //_FIN_DE_ARCHIVO ?>