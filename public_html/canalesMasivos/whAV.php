<?php
require_once("../../_configBasico.inc.php");
set_time_limit(60);
ini_set('memory_limit', '1024M');
$mongo = new MYMONGODB();

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['token']) && isset($data['tipo']) && ($data['tipo'] == 'fin_llamada_AV' || $data['tipo'] == 'mensaje_whastapp')) {

    $fecha = time();

    $c = $mongo->buscar('avProgramadas', ['_id' => $mongo->String2MongoId($data['token'])]);
    if ($c == 1) {
        if ($data['tipo'] == 'fin_llamada_AV') {
            $mongo->guardar('avWebhookAgentesVirtuales', ['fecha' => (int) $fecha, 'fechaStr' => (string) date('d/m/Y H:i:s', $fecha), 'data' => $data]);
            echo json_encode("registrado");
        }
        if ($data['tipo'] == 'mensaje_whastapp') {
            $mongo->guardar('avWebhookAgentesVirtuales', ['fecha' => (int) $fecha, 'fechaStr' => (string) date('d/m/Y H:i:s', $fecha), 'data' => $data]);
            require_once("../canalesMasivos/apis/class.whatsappAPI.php");
            $wh = new whatsappAPI();

            $wh->enviar(str_replace('+', '', $data['datos']['numero']), $data['datos']['mensaje'], '593984771316');

            echo json_encode("enviado");
        }
    } else {
        echo json_encode("");
    }
} else {
    echo json_encode("");
}
?>
<?

//_FIN_DE_ARCHIVO ?>