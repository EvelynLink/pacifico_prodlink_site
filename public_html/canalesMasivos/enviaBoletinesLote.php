<?php
require_once("../../_configBasico.inc.php");
require_once("../boletines/classes/class.boBitacora.php");

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

ini_set("max_execution_time", "15000");
ini_set("memory_limit", "1000M");

$db = new MYSQLDB();
$db1 = new MYSQLDB();
$db2 = new MYSQLDB();

//Obtengo parámetros de configuración de envío masivo
$variablesConf = getConf("Boletines Electronicos");
$mailsPorBloque = isset($variablesConf["Numero de mails por bloque"][0]) ? $variablesConf["Numero de mails por bloque"][0] : 100;

//---------------------------- Sin Programación ------------------------------//
//Obtiene listado de boletines pendientes de envío
$sql = $db->mkSQL("SELECT * FROM bobitacora WHERE boBitacora_estadoEnvio = %N AND boBitacora_filasLectura < boBitacora_filasTotal AND boBitacora_envioProgramado = %N",0,0);
$errores = array();
if($db->query($sql)){
    while($row = $db->fetchRow()){
        $id = $row["boBitacora_id"];
        $objBitacoraIndividual = new boBitacora();
        $objBitacoraIndividual->initFromDB($id);
        if($id == $objBitacoraIndividual->get("id")){
            $erroresIndividuales = $objBitacoraIndividual->ejecutarBitacora($mailsPorBloque);
            foreach ($erroresIndividuales as $e){
                $errores[] = $e;
            }
        }else{
            $errores[] = "No se pudo instanciar bitacora: ".$id;
        }
    }
}
if(count($errores) > 0){
    echo "<br>ERRORES SIN PROGRAMACIÓN";
    foreach ($errores as $e){
        echo $e."<br>";
    }
}

//---------------------------- Con Programación ------------------------------//
//Obtiene listado de boletines pendientes de envío
$sqlP = $db->mkSQL("SELECT * FROM bobitacora WHERE boBitacora_estadoEnvio = %N AND boBitacora_filasLectura < boBitacora_filasTotal AND boBitacora_envioProgramado > %N",0,0);
$erroresP = array();
if($db->query($sqlP)){
    while($row = $db->fetchRow()){
        //Evalua tiempo de envío
        $tiempoEnvio = false;
        $fechaActual = date("Y-m-d",time());
        $horaActual = date("H",time());
        $minutoActual = date("i",time());
        $fechaProgramada = date("Y-m-d",$row["boBitacora_envioProgramado"]);
        $horaEnvio = explode(':',$row["boBitacora_horaEnvio"]);
        $horaProgramada = $horaEnvio[0];
        $minutoProgramado = $horaEnvio[1];
        $unxFechaActual = strtotime($fechaActual);
        $unxFechaProgramada = strtotime($fechaProgramada);
        if($unxFechaActual == $unxFechaProgramada){
            if($horaActual == $horaProgramada){
                if($minutoActual >= $minutoProgramado){
                    $tiempoEnvio = true;
                }
            }elseif($horaActual >= $horaProgramada){
               $tiempoEnvio = true;
            }
        }else if($unxFechaActual > $unxFechaProgramada){
            $tiempoEnvio = true;
        }
        if($tiempoEnvio){
            $id = $row["boBitacora_id"];
            $objBitacoraIndividual = new boBitacora();
            $objBitacoraIndividual->initFromDB($id);
            if($id == $objBitacoraIndividual->get("id")){
                $erroresIndividuales = $objBitacoraIndividual->ejecutarBitacora($mailsPorBloque);
                foreach ($erroresIndividuales as $e){
                    $erroresP[] = $e;
                }
            }else{
                $erroresP[] = "No se pudo instanciar bitacora programada: ".$id;
            }
        }
    }
}
if(count($erroresP) > 0){
    echo "<br>ERRORES CON PROGRAMACIÓN";
    foreach ($erroresP as $e){
        echo $e;
    }
}

echo "EJECUCION_COMPLETA";
?>
<? //_FIN_DE_ARCHIVO ?>