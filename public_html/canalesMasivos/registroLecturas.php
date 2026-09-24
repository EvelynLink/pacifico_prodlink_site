<?php
require_once("../../_configBasico.inc.php");
header ("Location: ".BASEURL."boletines/img/fondo.png");
if(!isset($_REQUEST["act"])){
	exit;
}
$act = expect_safe_html($_REQUEST["act"]);
switch($act){
    //Lectura de correos
    case "registraLectura":
        $idBitacora = expect_integer($_REQUEST["bit"]);
        $correo = expect_safe_html($_REQUEST["cr"]);
        $hashRecibida = expect_safe_html($_REQUEST["hash"]);
        $hashValida = hash("md5", $idBitacora."mhfg$%/(2345&!xs")."/(x".hash("md5", $correo."mhfg$%/(2345&!xs");
        if($hashRecibida == $hashValida){
            require_once("../boletines/classes/class.boRegistro.php");
            require_once("../boletines/classes/class.boCorreo.php");
            $reg = new boRegistro();
            $cor = new boCorreo();
            $idCorreo = $cor->getEmailId($correo);
            if($idCorreo > 0){
                $reg->insertaRegistro($idBitacora, $correo, $idCorreo, 'Lectura', time());
            }
        }
    break;
}
?>
<? //_FIN_DE_ARCHIVO ?>
