<?php
require_once("../../_configBasico.inc.php");

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

// <editor-fold defaultstate="collapsed" desc="REQUIRES TO SISBAK FRAMEWORK">
require_once("../boletines/classes/class.boRedesConf.php");
// </editor-fold>

ini_set("max_execution_time", "15000");
ini_set("memory_limit", "1000M");

$db = new MYSQLDB();
$db1 = new MYSQLDB();

$updated = 0;
$sql = $db->mkSQL("SELECT boBitacoraRedes_id, boBitacoraRedes_conexId, boBitacoraRedes_estadoPublicacion FROM bobitacoraredes WHERE boBitacoraRedes_tipo=%Q", "facebook");
if ($db->query($sql)) {
    while ($row = $db->fetchRow()) {
        $keys = $row["boBitacoraRedes_conexId"];
        $who = $row["boBitacoraRedes_id"];
        $state = $row["boBitacoraRedes_estadoPublicacion"];

        if ($keys != "" && $state != 'DELETED') {
            $parts = explode("#", $keys);
            if (count($parts) > 1) {
                $config_id = $parts[0]; //Adset ID
                $ad_id = $parts[1]; //AD published ID
                $ad = new boRedesConf();
                $resp = $ad->getAd($ad_id, $config_id, true);
                if ($resp['op']) {
                    $translated_status = "";
                    $par = [
                        'ACTIVE' => 'ACTIVO',
                        'PAUSED' => 'PAUSADO',
                        'DELETED' => 'ELIMINADO',
                        'PENDING_REVIEW' => 'PENDIENTE POR REVISION',
                        'DISAPPROVED' => 'DESAPROVADO',
                        'PREAPPROVED' => 'PREAPROVADO',
                        'PENDING_BILLING_INFO' => 'PENDIENTE POR INFO. FACTURACIÓN',
                        'CAMPAIGN_PAUSED' => 'CAMPAÑA EN PAUSA',
                        'ARCHIVED' => 'ARCHIVADO',
                        'ADSET_PAUSED' => 'CONJUNTO EN PAUSA'
                    ];
                    $resp['data'] = (string) trim($resp['data']);
                    if (array_key_exists($resp['data'], $par)) {
                        $translated_status = $par[$resp['data']];
                        if ($translated_status != $state) {
                            $sql1 = $db1->mkSQL("UPDATE bobitacoraredes 
                                                SET boBitacoraRedes_estadoPublicacion=%Q
                                                WHERE boBitacoraRedes_id=%N",$translated_status,$who);
                            if ($db1->query($sql1)) {
                                $updated++;
                            }
                        } else {
                            echo "<br/>No actualizado por el ser mismo status";
                        }
                    }
                }
            }
        }
    }
}
if (count($updated) > 0) {
    echo "<br/>Se actualizaron $updated publicaciones desde Facebook<br/>";
} else {
    echo "<br/>No hay publicaciones en Facebook para actualizar sus status<br/>";
}
echo "EJECUCION_COMPLETA";
exit;
?>
<? //_FIN_DE_ARCHIVO ?>