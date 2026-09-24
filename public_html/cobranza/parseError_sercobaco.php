<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

chdir(__DIR__);

require_once("/home/pacifico/_configBasico.inc.php");

require_once "../cobranza/apis/class.cbAPI.php";
$cb = new cbAPI();

$cedula = '1718748997';
print_h($cb->api_consultaSercobaco('coreSisbak', $cedula, 'WEB'));

?>
<?

//_FIN_DE_ARCHIVO                                                                                                       ?>