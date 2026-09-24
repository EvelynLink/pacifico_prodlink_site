<?php

// ?? IMPORTANTE: forzar UTF-8 en la salida
header('Content-Type: text/html; charset=UTF-8');

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

chdir(__DIR__);

require_once("/home/pacifico/_configBasico.inc.php");
require_once "../cobranza/apis/class.cbAPI.php";

$cb = new cbAPI();

//$cedula = '1700000000';
$cedula = '1718748997';

// =========================================================
// ?? CONSUMO API
// =========================================================
$bd_datos = $cb->api_consultaSercobaco('coreSisbak', $cedula, 'WEB');

// =========================================================
// ?? DECODIFICAR (SIN TOCAR ENCODING)
// =========================================================
$decoded = base64_decode($bd_datos);

// =========================================================
// ?? JSON
// =========================================================
$array = [json_decode($decoded)];

// =========================================================
// ?? PRUEBA DIRECTA
// =========================================================
echo "<pre>";
print_h($array);
echo "</pre>";