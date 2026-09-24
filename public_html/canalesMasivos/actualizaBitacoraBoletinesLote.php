<?php
require_once("../../_configBasico.inc.php");

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

ini_set("max_execution_time", "15000");
ini_set("memory_limit", "1000M");

$db = new MYSQLDB();
$db1 = new MYSQLDB();
$db2 = new MYSQLDB();

$sql = $db->mkSQL("SELECT * FROM bobitacora");
if($db->query($sql)){
    while ($row = $db->fetchRow()) {
        $suma =  ($row["boBitacora_enviados"]+$row["boBitacora_noenviados"]);
        if($row["boBitacora_filasLectura"] != $suma){
            //ENVIADOS
            $enviados = 0;
            /*$sql2 = $db2->mkSQL("SELECT count(alEnvelopes_success) enviados
                                                           FROM alenvelopes
                                                           WHERE alEnvelopes_bitacoraId = %N and alEnvelopes_success = %N",$row["boBitacora_id"],1);
            if($db2->query($sql2)){
                $rowTemp = $db2->fetchRow();
                $enviados = $rowTemp["enviados"];
            }
            $sql1 = $db1->mkSQL("UPDATE bobitacora 
                                SET 
                                    boBitacora_enviados = %N
                                WHERE boBitacora_id=%N",$enviados,$row["boBitacora_id"]);
            $db1->query($sql1);
            //NO ENVIADOS
            $noEnviados = 0;
            $sql2 = $db2->mkSQL("SELECT count(alEnvelopes_success) noEnviados
                                   FROM alenvelopes
                                   WHERE alEnvelopes_bitacoraId = %N and alEnvelopes_success = %N",$row["boBitacora_id"],0);
            if($db2->query($sql2)){
                $rowTemp = $db2->fetchRow();
                $noEnviados = $rowTemp["noEnviados"];
            }
            if($row["boBitacora_filasLectura"] == $row["boBitacora_filasTotal"] && $noEnviados == 0){
                $noEnviados = $row["boBitacora_filasTotal"]-$enviados;
                if($noEnviados < 0){ $noEnviados = 0; }
            }
            $sql1 = $db1->mkSQL("UPDATE bobitacora 
                                SET 
                                    boBitacora_noenviados = %N
                                WHERE boBitacora_id=%N",$noEnviados,$row["boBitacora_id"]);
            $db1->query($sql1);*/
            //MONGO SET ENVIADOS
            $mdb = new MYMONGODB();
            $condicion=["bolRegistroBitacora_idBitacora"=>(int)$row["boBitacora_id"],
                        "bolRegistroBitacora_estado"=>(string)"Enviado"];
            $condicionNE=["bolRegistroBitacora_idBitacora"=>(int)$row["boBitacora_id"],
                        "bolRegistroBitacora_estado"=>(string)"No Enviado"];
            $enviados = $mdb->buscar('bolRegistroBitacora',$condicion);
            $noEnviados = $mdb->buscar('bolRegistroBitacora',$condicionNE);
            $sql1 = $db1->mkSQL("UPDATE bobitacora 
                                SET boBitacora_enviados = %N,
                                    boBitacora_noenviados = %N
                                WHERE boBitacora_id=%N",$enviados,$noEnviados,$row["boBitacora_id"]);
            $db1->query($sql1);
            print_h("bitacora: ".$row["boBitacora_id"]." :: enviados: ".$enviados." no enviados: ".$noEnviados);
        }

        //LEIDOS
        $sql1 = $db1->mkSQL("SELECT count(boRegistro_id) bor_leidos
                             FROM boregistro
                             WHERE boRegistro_bitacoraId = %N and boRegistro_tipo = %Q",$row["boBitacora_id"],'Lectura');
        if($db1->query($sql1)){
            $rowl = $db1->fetchRow();
            $leidos = $rowl["bor_leidos"];
            if($leidos != $row["boBitacora_leidos"]){
                $leidosActual = 0;
                $sql2 = $db2->mkSQL("SELECT count(boRegistro_id) leidos
                                       FROM boregistro
                                       WHERE boRegistro_bitacoraId = %N and boRegistro_tipo = %Q",$row["boBitacora_id"],'Lectura');
                if($db2->query($sql2)){
                    $rowTemp = $db2->fetchRow();
                    $leidosActual = $rowTemp["leidos"];
                }
                $sql1 = $db1->mkSQL("UPDATE bobitacora 
                            SET 
                                boBitacora_leidos = %N
                            WHERE boBitacora_id=%N",$leidosActual,$row["boBitacora_id"]);
                $db1->query($sql1);
                print_h("bitacora: ".$row["boBitacora_id"]." :: leidos: ".$leidosActual);
            }
        }
        //DESUSCRITOS
        $sql1 = $db1->mkSQL("SELECT count(boRegistro_id) bor_desuscritos
                             FROM boregistro
                             WHERE boRegistro_bitacoraId = %N and boRegistro_tipo = %Q",$row["boBitacora_id"],'Lectura');
        if($db1->query($sql1)){
            $rowd = $db1->fetchRow();
            $desuscritos = $rowd["bor_desuscritos"];
            if($desuscritos != $row["boBitacora_desuscritos"]){
                $desuscritosActual = 0;
                $sql2 = $db2->mkSQL("SELECT count(boRegistro_id) desuscritos
                                     FROM boregistro
                                     WHERE boRegistro_bitacoraId = %N and boRegistro_tipo = %Q",$row["boBitacora_id"],'DeSuscripcion');
                if($db2->query($sql2)){
                    $rowTemp = $db2->fetchRow();
                    $desuscritosActual = $rowTemp["desuscritos"];
                }
                $sql1 = $db1->mkSQL("UPDATE bobitacora 
                            SET 
                                boBitacora_desuscritos = %N
                            WHERE boBitacora_id=%N",$desuscritosActual,$row["boBitacora_id"]);
                print_h("bitacora: ".$row["boBitacora_id"]." :: desuscritos: ".$desuscritosActual);
            }
        }
    }
}

echo "EJECUCION_COMPLETA";
exit;

?>
<? //_FIN_DE_ARCHIVO ?>