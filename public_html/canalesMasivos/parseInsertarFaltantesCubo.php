<?php
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseInsertarFaltantesCubo no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseInsertarFaltantesCubo.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

// Tipificaciones que no deben contar como gestion
define('TIPIFICACIONES_EXCLUIDAS', ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado']);

define('MAPA_CANALES', [
    'TELEFONICA' => 'AV',
    'EMAIL'      => 'EMAIL',
    'WHATSAPP'   => 'WHATSAPP',
]);


define('SPONSOR', 'BANCO DEL PACÍFICO');

// USO:
//   Web: parseInsertarFaltantesCubo.php?cartera=2127&fechaPeriodo=1788843600[&simular=1]
//   CLI: php parseInsertarFaltantesCubo.php --cartera=2127 --fechaPeriodo=1788843600 [--simular=1]
// simular=1 solo lista lo que insertaria, no guarda nada.

$params = ['cartera' => '', 'fechaPeriodo' => '', 'simular' => '0'];

if (php_sapi_name() === 'cli') {
    foreach (($argv ?? []) as $arg) {
        foreach (array_keys($params) as $nombre) {
            if (strpos($arg, '--' . $nombre . '=') === 0) {
                $params[$nombre] = trim(substr($arg, strlen('--' . $nombre . '=')));
            }
        }
    }
} else {
    foreach (array_keys($params) as $nombre) {
        if (isset($_GET[$nombre])) {
            $params[$nombre] = trim((string)$_GET[$nombre]);
        }
    }
}

if (!ctype_digit($params['cartera']) || !ctype_digit($params['fechaPeriodo'])) {
    echo "ERROR: parametros invalidos. Se requiere cartera y fechaPeriodo (unixtime) numericos.<br>\n";
    exit;
}

$cartera      = (string)$params['cartera'];
$fechaPeriodo = (int)$params['fechaPeriodo'];
$simular      = ($params['simular'] === '1');

// Tipo de cartera segun MySQL: VENTAS va a su cubo, el resto a cobranza
$db = new MYSQLDB();
$db->query($db->mkSQL('SELECT cobCartera_id, cobCartera_tipo FROM cobcartera WHERE cobCartera_id=%N', (int)$cartera));
$rowCartera = $db->fetchRow();

if (!$rowCartera) {
    echo "ERROR: la cartera {$cartera} no existe en cobcartera.<br>\n";
    exit;
}

$esVentas      = (strtoupper(trim((string)($rowCartera['cobCartera_tipo'] ?? ''))) === 'VENTAS');
$tipo          = $esVentas ? 'VENTAS' : 'COBRANZA';
$coleccionCubo = $esVentas ? 'cuAsignacionesGestionVentas' : 'cuAsignacionesGestionAP';
$pref          = $esVentas ? 'cubAV' : 'cubAG';

echo "============================================================<br>\n";
echo "Tipo: {$tipo} | Cubo: {$coleccionCubo}<br>\n";
echo "Cartera: {$cartera} | fechaPeriodo: {$fechaPeriodo} (" . date('Y-m-d H:i:s', $fechaPeriodo) . ")<br>\n";
echo "Modo: " . ($simular ? "SIMULACION (no guarda)" : "INSERCION") . "<br>\n";
echo "============================================================<br>\n";

// Facturas que ya estan en el cubo para esa cartera y periodo
$existentes = [];
$mdbCuboExist = new MYMONGODB();
$mdbCuboExist->buscar($coleccionCubo, [
    $pref . '_carteraId'    => $cartera,
    $pref . '_fechaPeriodo' => $fechaPeriodo,
], [$pref . '_numFactura']);

while ($doc = $mdbCuboExist->siguiente()) {
    $existentes[(string)($doc[$pref . '_numFactura'] ?? '')] = true;
}

echo "Registros ya existentes en el cubo: " . count($existentes) . "<br>\n";

// Creditos activos de la cartera en ese periodo (carteraId puede estar como string o int)
$condCreditos = [
    'cre_carteraId'    => ['$in' => [$cartera, (int)$cartera]],
    'cre_fechaPeriodo' => $fechaPeriodo,
    'cre_inactivo'     => 0,
];

$mdbCreditos = new MYMONGODB();
$totalCreditos = $mdbCreditos->buscar('cbCreditos', $condCreditos, [], ['_id' => 1]);

echo "Creditos activos en cbCreditos: " . (int)$totalCreditos . "<br>\n";

$mdbCubo    = new MYMONGODB();
$yaExistian = 0;
$insertados = [];
$errores    = [];

while ($doc = $mdbCreditos->siguiente()) {

    $factura = (string)($doc['cre_factura'] ?? '');

    if ($factura === '') {
        $errores[] = "Documento " . (string)($doc['_id'] ?? '') . " sin cre_factura";
        continue;
    }

    if (isset($existentes[$factura])) {
        $yaExistian++;
        continue;
    }

    try {
        // Revalida justo antes de insertar por si el process() lo inserto mientras corria
        $criteria = [
            $pref . '_numFactura'   => $factura,
            $pref . '_carteraId'    => (string)$doc['cre_carteraId'],
            $pref . '_fechaPeriodo' => (int)$doc['cre_fechaPeriodo'],
        ];

        if ($mdbCubo->buscar($coleccionCubo, $criteria, ['_id'], [], 1)) {
            $existentes[$factura] = true;
            $yaExistian++;
            continue;
        }

        if (!$simular) {
            // Campos que el process() solo guarda en el primer ingreso al periodo
            if ($esVentas) {
                $newRow = crearDatosVentas($doc);
                $newRow['cubAV_fechaAsignacion'] = (int)($doc['cre_fechaCarga'] ?? 0);
            } else {
                $newRow = crearDatosCobranza($doc);
                $newRow['cubAG_capitalInicialPeriodo'] = (float)($doc['cre_saldoCapital'] ?? 0);
                $newRow['cubAG_fechaAsignacion']       = (int)($doc['cre_fechaCarga'] ?? 0);
            }

            $mdbCubo->guardar($coleccionCubo, $newRow);
        }

        $existentes[$factura] = true;
        $insertados[] = $factura;
    } catch (Throwable $e) {
        $errores[] = "Factura {$factura}: " . $e->getMessage();
    }
}

echo "<hr>\n";
echo "Ya existian (omitidos): {$yaExistian}<br>\n";
echo ($simular ? "Se insertarian: " : "Insertados: ") . count($insertados) . "<br>\n";
echo "Errores: " . count($errores) . "<br>\n";

if (!empty($errores)) {
    echo "<hr>Detalle errores:<br>\n" . implode("<br>\n", $errores) . "<br>\n";
}

if (!empty($insertados)) {
    echo "<hr>" . ($simular ? "Facturas a insertar" : "Facturas insertadas") . ":<br>\n" . implode("<br>\n", $insertados) . "<br>\n";
}

// Copia de createData() de cuPGasignacionesGestionVentas (mantener sincronizado)
function crearDatosVentas(array $row): array
{
    $datos = [
        //mongo cbCreditos
        'cubAV_cbcreId'                           => $row['_id'] ?? null,
        'cubAV_numFactura'                        => $row['cre_factura'] ?? '',
        'cubAV_carteraId'                         => $row['cre_carteraId'] ?? '',
        'cubAV_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
        'cubAV_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
        'cubAV_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
        'cubAV_sponsor'                           => SPONSOR,
        'cubAV_usuariosId'                        => $row['usUsuarios_id'] ?? '',
        'cubAV_nombres'                           => $row['cre_nombres'] ?? '',
        'cubAV_apellidos'                         => $row['cre_apellidos'] ?? '',
        'cubAV_cedula'                            => $row['cre_cedula'] ?? '',
        'cubAV_producto'                          => $row['cre_producto'] ?? '',
        'cubAV_ciudad'                            => $row['cre_ciudad'] ?? '',
        'cubAV_fechaCarga'                        => $row['cre_fechaCarga'] ?? '',
        'cubAV_mejorBandaHoraria'                 => $row['cre_mejorBandaHoraria'] ?? '',

        //Mongo CRM
        'cubAV_crmId'                             => '',
        'cubAV_edad'                              => '',
        'cubAV_sexo'                              => '',
        'cubAV_estadoCivil'                       => '',
        //Mongo cbDirecciones
        'cubAV_dirId'                             => '',
        'cubAV_direccion'                         => '',
        //Mongo control_carga_periodo
        'cubAV_ctrId'                             => '',
        'cubAV_fechaInicio'                       => 0,
        'cubAV_fechaFin'                          => 0,

        //cubo gestiones: mejor gestion general, aplanada
        'cubAV_gestionada'                        => 0,
        'cubAV_cuGestionId'                       => '',
        'cubAV_canal'                             => '',
        'cubAV_ponderacion'                       => 0,
        'cubAV_tipificacion1'                     => '',
        'cubAV_tipificacion2'                     => '',
        'cubAV_fechaGestion'                      => 0,
        'cubAV_telefono'                          => '',
        'cubAV_email'                             => '',

        //cubo gestiones por canal (AV/EMAIL/WHATSAPP)
        'cubAV_mejorGestion'                      => [],
    ];

    //Mongo CRM
    $mdbCRM = new MYMONGODB();
    $mdbCRM->buscar('CRM', ['crm_cedula' => (string)$datos['cubAV_cedula']], ['_id', 'crm_edad', 'crm_sexo', 'crm_estadoCivil'], [], 1);
    while ($doc = $mdbCRM->siguiente()) {
        $datos['cubAV_crmId']       = $doc['_id'] ?? null;
        $datos['cubAV_edad']        = $doc['crm_edad'] ?? null;
        $datos['cubAV_sexo']        = $doc['crm_sexo'] ?? null;
        $datos['cubAV_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;
        break;
    }

    //Mongo cbDirecciones
    $mdbDir = new MYMONGODB();
    $mdbDir->buscar('cbDirecciones', ['dir_cedula' => (string)$datos['cubAV_cedula']], ['_id', 'dir_direccion'], [], 1);
    while ($doc = $mdbDir->siguiente()) {
        $datos['cubAV_dirId']     = $doc['_id'] ?? null;
        $datos['cubAV_direccion'] = $doc['dir_direccion'] ?? null;
        break;
    }

    //Mongo control_carga_periodo
    $mdbCargaPer = new MYMONGODB();
    $condCargaPer = [
        'cartera' => (int)$datos['cubAV_carteraId'],
        'periodo' => (int)$datos['cubAV_ciclo'],
        'fecha'   => (int)$datos['cubAV_fechaPeriodo'],
    ];
    $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, ['_id', 'fecha', 'fechaFin'], ['_id' => -1], 1);
    while ($doc = $mdbCargaPer->siguiente()) {
        $datos['cubAV_ctrId']       = $doc['_id'] ?? null;
        $datos['cubAV_fechaInicio'] = (int)($doc['fecha'] ?? 0);
        $datos['cubAV_fechaFin']    = (int)($doc['fechaFin'] ?? 0);
        break;
    }

    foreach (MAPA_CANALES as $codigoCanal) {
        $datos['cubAV_mejorGestion'][$codigoCanal] = [
            'cuGestionId'   => '',
            'ponderacion'   => 0,
            'tipificacion1' => '',
            'tipificacion2' => '',
            'fechaGestion'  => 0,
            'telefono'      => '',
            'email'         => '',
        ];
    }

    //Mongo cuGestionVentas: mejor gestion por ponderacion, global y por canal
    $mdbCuGV = new MYMONGODB();
    $condCuGV = [
        'cubGV_numFactura'              => (string)$datos['cubAV_numFactura'],
        'cubGV_carteraId'               => (string)$datos['cubAV_carteraId'],
        'cubGV_ciclo'                   => (int)$datos['cubAV_ciclo'],
        'cubGV_fechaGestion'            => ['$gte' => (int)$datos['cubAV_fechaInicio']],
        'cubGV_tipificacion_respuesta2' => ['$nin' => TIPIFICACIONES_EXCLUIDAS],
    ];
    $datCuGV = [
        '_id',
        'cubGV_avId',
        'cubGV_canal',
        'cubGV_ponderacion',
        'cubGV_tipificacion_respuesta1',
        'cubGV_tipificacion_respuesta2',
        'cubGV_fechaGestion',
        'cubGV_telefono',
        'cubGV_email',
    ];
    $mdbCuGV->buscar('cuGestionVentas', $condCuGV, $datCuGV, ['cubGV_ponderacion' => -1]);

    $mejorGlobal      = null;
    $mejoresPorCanal  = [];
    $gestionadaGlobal = 0;

    while ($doc = $mdbCuGV->siguiente()) {
        if (isset($doc['cubGV_avId'])) {
            $gestionadaGlobal = 1;
        }

        $codigoCanal = MAPA_CANALES[$doc['cubGV_canal'] ?? null] ?? null;

        // Telefono solo para AV/WHATSAPP, email solo para EMAIL
        $telefono = '';
        $email    = '';
        if ($codigoCanal === 'AV' || $codigoCanal === 'WHATSAPP') {
            $telefono = (string)($doc['cubGV_telefono'] ?? '');
        } elseif ($codigoCanal === 'EMAIL') {
            $email = (string)($doc['cubGV_email'] ?? '');
        }

        $candidato = [
            'cuGestionId'   => $doc['_id'],
            'canal'         => $codigoCanal ?? '',
            'ponderacion'   => (int)($doc['cubGV_ponderacion'] ?? 0),
            'tipificacion1' => (string)($doc['cubGV_tipificacion_respuesta1'] ?? ''),
            'tipificacion2' => (string)($doc['cubGV_tipificacion_respuesta2'] ?? ''),
            'fechaGestion'  => (int)($doc['cubGV_fechaGestion'] ?? 0),
            'telefono'      => $telefono,
            'email'         => $email,
        ];

        if ($mejorGlobal === null) {
            $mejorGlobal = $candidato;
        }
        if ($codigoCanal !== null && !isset($mejoresPorCanal[$codigoCanal])) {
            $mejoresPorCanal[$codigoCanal] = $candidato;
        }
    }

    $datos['cubAV_gestionada'] = $gestionadaGlobal;

    if ($mejorGlobal !== null) {
        $datos['cubAV_cuGestionId']   = $mejorGlobal['cuGestionId'];
        $datos['cubAV_canal']         = $mejorGlobal['canal'];
        $datos['cubAV_ponderacion']   = $mejorGlobal['ponderacion'];
        $datos['cubAV_tipificacion1'] = $mejorGlobal['tipificacion1'];
        $datos['cubAV_tipificacion2'] = $mejorGlobal['tipificacion2'];
        $datos['cubAV_fechaGestion']  = $mejorGlobal['fechaGestion'];
        $datos['cubAV_telefono']      = $mejorGlobal['telefono'];
        $datos['cubAV_email']         = $mejorGlobal['email'];
    }

    foreach ($mejoresPorCanal as $codigoCanal => $mejor) {
        $datos['cubAV_mejorGestion'][$codigoCanal] = $mejor;
    }

    return $datos;
}

// Copia de createData() de cuPGasignacionesGestion (mantener sincronizado)
function crearDatosCobranza(array $row): array
{
    $datos = [
        //mongo cbCreditos
        'cubAG_numFactura'                        => $row['cre_factura'] ?? '',
        'cubAG_carteraId'                         => $row['cre_carteraId'] ?? '',
        'cubAG_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
        'cubAG_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
        'cubAG_cbcreId'                           => $row['_id'] ?? null,
        'cubAG_sponsor'                           => SPONSOR,
        'cubAG_nombres'                           => $row['cre_nombres'] ?? '',
        'cubAG_apellidos'                         => $row['cre_apellidos'] ?? '',
        'cubAG_cedula'                            => $row['cre_cedula'] ?? '',
        'cubAG_riesgo'                            => $row['cre_calificacion'] ?? '',
        'cubAG_deudaNetaActual'                   => (float)($row['cre_deudaNeta'] ?? 0),
        'cubAG_diasMora'                          => (int)($row['cre_diasMoraFactura'] ?? 0),
        'cubAG_producto'                          => $row['cre_producto'] ?? '',
        'cubAG_diaProbable'                       => $row['cre_diaProbable'] ?? '',
        'cubAG_mejorBandaHoraria'                 => $row['cre_mejorBandaHoraria'] ?? '',
        'cubAG_regularidad'                       => $row['cre_regularidad'] ?? '',
        'cubAG_tramoSaldo'                        => $row['cre_tramoSaldo'] ?? '',
        'cubAG_tramoMora'                         => $row['cre_tramoMora'] ?? '',
        'cubAG_marca'                             => $row['cre_marca'] ?? '',
        'cubAG_canton'                            => $row['cre_canton'] ?? '',
        'cubAG_ciudad'                            => $row['cre_ciudad'] ?? '',
        'cubAG_capitalActual'                     => (float)($row['cre_saldoCapital'] ?? 0),
        'cubAG_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
        'cubAG_fechaCarga'                        => (int)($row['cre_fechaCarga'] ?? 0),
        'cubAG_usuariosId'                        => $row['usUsuarios_id'] ?? '',

        //Mongo CRM
        'cubAG_crmId'                             => '',
        'cubAG_edad'                              => '',
        'cubAG_sexo'                              => '',
        'cubAG_estadoCivil'                       => '',
        //Mongo cbDirecciones
        'cubAG_dirId'                             => '',
        'cubAG_direccion'                         => '',
        //Mongo cbCargaDetallePacifico
        'cubAG_cdetId'                            => '',
        'cubAG_capitalInicial'                    => 0,
        'cubAG_deudaNetaInicial'                  => 0,
        //Mongo control_carga_periodo
        'cubAG_ctrId'                             => '',
        'cubAG_fechaInicio'                       => 0,
        'cubAG_fechaFin'                          => 0,
        //cubo gestiones: mejor gestion general, aplanada
        'cubAG_gestionada'                        => 0,
        'cubAG_cuGestionId'                       => '',
        'cubAG_canal'                             => '',
        'cubAG_ponderacion'                       => 0,
        'cubAG_tipificacion1'                     => '',
        'cubAG_tipificacion2'                     => '',
        'cubAG_fechaGestion'                      => 0,
        'cubAG_compromiso'                        => '',
        'cubAG_montoCompromiso'                   => 0,
        'cubAG_telefono'                          => '',
        'cubAG_email'                             => '',

        //cubo gestiones por canal (AV/EMAIL/WHATSAPP)
        'cubAG_mejorGestion'                      => [],

        //PAGOS
        'cubAG_montoTotalPago'                    => 0,
        'cubAG_probabilidadPago'                  => 0,
        'cubAG_calificacionAcelerada'             => 0,
    ];

    //Mongo CRM
    $mdbCRM = new MYMONGODB();
    $mdbCRM->buscar('CRM', ['crm_cedula' => (string)$datos['cubAG_cedula']], ['_id', 'crm_edad', 'crm_sexo', 'crm_estadoCivil'], [], 1);
    while ($doc = $mdbCRM->siguiente()) {
        $datos['cubAG_crmId']       = $doc['_id'] ?? null;
        $datos['cubAG_edad']        = $doc['crm_edad'] ?? null;
        $datos['cubAG_sexo']        = $doc['crm_sexo'] ?? null;
        $datos['cubAG_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;
        break;
    }

    //Mongo cbDirecciones
    $mdbDir = new MYMONGODB();
    $mdbDir->buscar('cbDirecciones', ['dir_cedula' => (string)$datos['cubAG_cedula']], ['_id', 'dir_direccion'], [], 1);
    while ($doc = $mdbDir->siguiente()) {
        $datos['cubAG_dirId']     = $doc['_id'] ?? null;
        $datos['cubAG_direccion'] = $doc['dir_direccion'] ?? null;
        break;
    }

    //Mongo cbCargaDetallePacifico
    $mdbDet = new MYMONGODB();
    $condDet = [
        'cedula'    => (string)$datos['cubAG_cedula'],
        'carteraId' => (int)$datos['cubAG_carteraId'],
        'inicial'   => 1,
        'periodo'   => $datos['cubAG_ciclo'],
    ];
    $mdbDet->buscar('cbCargaDetallePacifico', $condDet, ['_id', 'saldoCapital', 'deudaNeta'], [], 1);
    while ($doc = $mdbDet->siguiente()) {
        $datos['cubAG_cdetId']           = $doc['_id'] ?? null;
        $datos['cubAG_capitalInicial']   = (float)($doc['saldoCapital'] ?? 0);
        $datos['cubAG_deudaNetaInicial'] = (float)($doc['deudaNeta'] ?? 0);
        break;
    }

    //Mongo control_carga_periodo
    $mdbCargaPer = new MYMONGODB();
    $condCargaPer = [
        'cartera' => (int)$datos['cubAG_carteraId'],
        'periodo' => (int)$datos['cubAG_ciclo'],
        'fecha'   => (int)$datos['cubAG_fechaPeriodo'],
    ];
    $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, ['_id', 'fecha', 'fechaFin'], ['_id' => -1], 1);
    while ($doc = $mdbCargaPer->siguiente()) {
        $datos['cubAG_ctrId']       = $doc['_id'] ?? null;
        $datos['cubAG_fechaInicio'] = (int)($doc['fecha'] ?? 0);
        $datos['cubAG_fechaFin']    = (int)($doc['fechaFin'] ?? 0);
        break;
    }

    foreach (MAPA_CANALES as $codigoCanal) {
        $datos['cubAG_mejorGestion'][$codigoCanal] = [
            'cuGestionId'     => '',
            'ponderacion'     => 0,
            'tipificacion1'   => '',
            'tipificacion2'   => '',
            'fechaGestion'    => 0,
            'compromiso'      => '',
            'montoCompromiso' => 0,
            'telefono'        => '',
            'email'           => '',
        ];
    }

    //Mongo cuGestionCobranzaMysql: mejor gestion por ponderacion, global y por canal
    $mdbCuGC = new MYMONGODB();
    $condCuGC = [
        'cubGC_numFactura'              => (string)$datos['cubAG_numFactura'],
        'cubGC_carteraId'               => (string)$datos['cubAG_carteraId'],
        'cubGC_ciclo'                   => (int)$datos['cubAG_ciclo'],
        'cubGC_fechaGestion'            => ['$gte' => (int)$datos['cubAG_fechaInicio']],
        'cubGC_tipificacion_respuesta2' => ['$nin' => TIPIFICACIONES_EXCLUIDAS],
    ];
    $datCuGC = [
        '_id',
        'cubGC_avId',
        'cubGC_canal',
        'cubGC_ponderacion',
        'cubGC_tipificacion_respuesta1',
        'cubGC_tipificacion_respuesta2',
        'cubGC_fechaGestion',
        'cubGC_tipificacion_compromiso',
        'cubGC_tipificacion_montoCompromiso',
        'cubGC_telefono',
        'cubGC_email',
    ];
    $mdbCuGC->buscar('cuGestionCobranzaMysql', $condCuGC, $datCuGC, ['cubGC_ponderacion' => -1]);

    $mejorGlobal      = null;
    $mejoresPorCanal  = [];
    $gestionadaGlobal = 0;

    while ($doc = $mdbCuGC->siguiente()) {
        if (isset($doc['cubGC_avId'])) {
            $gestionadaGlobal = 1;
        }

        $codigoCanal = MAPA_CANALES[$doc['cubGC_canal'] ?? null] ?? null;

        // Telefono solo para AV/WHATSAPP, email solo para EMAIL
        $telefono = '';
        $email    = '';
        if ($codigoCanal === 'AV' || $codigoCanal === 'WHATSAPP') {
            $telefono = (string)($doc['cubGC_telefono'] ?? '');
        } elseif ($codigoCanal === 'EMAIL') {
            $email = (string)($doc['cubGC_email'] ?? '');
        }

        $candidato = [
            'cuGestionId'     => $doc['_id'],
            'canal'           => $codigoCanal ?? '',
            'ponderacion'     => (int)($doc['cubGC_ponderacion'] ?? 0),
            'tipificacion1'   => (string)($doc['cubGC_tipificacion_respuesta1'] ?? ''),
            'tipificacion2'   => (string)($doc['cubGC_tipificacion_respuesta2'] ?? ''),
            'fechaGestion'    => (int)($doc['cubGC_fechaGestion'] ?? 0),
            'compromiso'      => (string)($doc['cubGC_tipificacion_compromiso'] ?? ''),
            'montoCompromiso' => (float)($doc['cubGC_tipificacion_montoCompromiso'] ?? 0),
            'telefono'        => $telefono,
            'email'           => $email,
        ];

        if ($mejorGlobal === null) {
            $mejorGlobal = $candidato;
        }
        if ($codigoCanal !== null && !isset($mejoresPorCanal[$codigoCanal])) {
            $mejoresPorCanal[$codigoCanal] = $candidato;
        }
    }

    $datos['cubAG_gestionada'] = $gestionadaGlobal;

    if ($mejorGlobal !== null) {
        $datos['cubAG_cuGestionId']     = $mejorGlobal['cuGestionId'];
        $datos['cubAG_canal']           = $mejorGlobal['canal'];
        $datos['cubAG_ponderacion']     = $mejorGlobal['ponderacion'];
        $datos['cubAG_tipificacion1']   = $mejorGlobal['tipificacion1'];
        $datos['cubAG_tipificacion2']   = $mejorGlobal['tipificacion2'];
        $datos['cubAG_fechaGestion']    = $mejorGlobal['fechaGestion'];
        $datos['cubAG_compromiso']      = $mejorGlobal['compromiso'];
        $datos['cubAG_montoCompromiso'] = $mejorGlobal['montoCompromiso'];
        $datos['cubAG_telefono']        = $mejorGlobal['telefono'];
        $datos['cubAG_email']           = $mejorGlobal['email'];
    }

    foreach ($mejoresPorCanal as $codigoCanal => $mejor) {
        $datos['cubAG_mejorGestion'][$codigoCanal] = $mejor;
    }

    //Mongo cbPagos
    $mdbPag = new MYMONGODB();
    $condPag = [
        'pagos_numFactura' => $datos['cubAG_numFactura'],
        'pagos_carteraId'  => $datos['cubAG_carteraId'],
        'pagos_periodo'    => (int)$datos['cubAG_ciclo'],
        'pagos_proceso'    => ['$gte' => (int)$datos['cubAG_fechaInicio']],
    ];
    $mdbPag->buscar('cbPagos', $condPag, ['pagos_monto']);
    $totalMonto = 0;
    while ($doc = $mdbPag->siguiente()) {
        $totalMonto += isset($doc['pagos_monto']) ? (float)$doc['pagos_monto'] : 0;
    }
    $datos['cubAG_montoTotalPago'] = round($totalMonto, 2);

    // Probabilidad de pago: promedio de pago/deudaNeta de hasta 6 periodos anteriores
    $mdbProbPago = new MYMONGODB();
    $condProbPago = [
        'cubAG_numFactura'   => (string)$datos['cubAG_numFactura'],
        'cubAG_carteraId'    => (string)$datos['cubAG_carteraId'],
        'cubAG_fechaPeriodo' => ['$lt' => (int)$datos['cubAG_fechaPeriodo']],
    ];
    $mdbProbPago->buscar('cuAsignacionesGestionAP', $condProbPago, ['cubAG_montoTotalPago', 'cubAG_deudaNetaActual'], ['cubAG_fechaPeriodo' => -1], 6);

    $sumaRazones   = 0;
    $totalPeriodos = 0;
    while ($doc = $mdbProbPago->siguiente()) {
        $pago  = (float)($doc['cubAG_montoTotalPago'] ?? 0);
        $deuda = (float)($doc['cubAG_deudaNetaActual'] ?? 0);
        $sumaRazones += $deuda > 0 ? ($pago / $deuda) : 0;
        $totalPeriodos++;
    }
    $datos['cubAG_probabilidadPago'] = $totalPeriodos > 0 ? round(($sumaRazones / $totalPeriodos) * 100, 2) : 0;

    // Calificacion acelerada: dias de mora x (1 - probabilidad en fraccion)
    $datos['cubAG_calificacionAcelerada'] = round($datos['cubAG_diasMora'] * (1 - $datos['cubAG_probabilidadPago'] / 100), 2);

    return $datos;
}

echo "EJECUCION_COMPLETA";

?><?

    //_FIN_DE_ARCHIVO
    ?>
