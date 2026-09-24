<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

require_once('../../_configBasico.inc.php');
require_once("../comunes/classes/class.mymongodb.php");
require_once("../comunes/classes/class.mysqldb.php");
require_once '../comunes/classes/class.myredisdb.php';
require_once("../functions/basic.php");
require_once("../canalesMasivos/apis/class.retellAPI.php");

$coleccion = "TemporalCampaniaHyundai_llamadas";
$mongo = new MYMONGODB();
$mongo2 = new MYMONGODB();
$retellAPI = new retellAPI();

$personas = [
    ["Juan Perez", "0956256325", "+593995633305", "Hyundai grand i10"],
    ["José Ramirez", "0945123698", "+593995633305", "Hyundai accent"],
    ["María Ruiz", "0945239874", "+593995633305", "Hyundai tucson"],
    ["Ricardo Jarrin", "0915263698", "+593995633305", "Hyundai ioniq 5"],
];

$dias = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
$meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

$hoy = time();

foreach ($personas as $persona) {
    $nombre = $persona[0];
    $cedula = $persona[1];
    $telefono = $persona[2];
    $auto = $persona[3];

    $data = [
        "genero_agente" => "",
        "tipo_agente" => "agent_94e2952f9cd5f025be7aed0240", //id del agente
        "telefono" => $telefono,
        "numero_telefono_agente" => "+593964285881"
    ];
    $dinamicos = [
        "fecha_hora_local" => ($dias[intval(date("w", $hoy)) - 1]) . ", " . ($meses[(intval(date("m", $hoy)))]) . " " . intval(date("d")) . ", " . date("Y") . " ECT (GMT-05:00) " . date("H:i:s") . " (Hoy)",
        "nombre_cliente" => $nombre,
        "modelo_vehiculo" => $auto
    ];
    $dataEnvio = array_merge($data, $dinamicos);

    print_h($dataEnvio);
}


?><?php
    //_FIN_DE_ARCHIVO 
    ?>