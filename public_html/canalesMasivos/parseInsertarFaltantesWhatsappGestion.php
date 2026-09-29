<?php
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseInsertarFaltantesWhatsappGestion no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseInsertarFaltantesWhatsappGestion.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

define('CUBO_COBRANZA', 'cuGestionCobranzaMysql');
define('CUBO_VENTAS', 'cuGestionVentas');
define('SPONSOR', 'BANCO DEL PACÍFICO');


/* Simular el mes actual, todas las carteras
parseInsertarFaltantesWhatsappGestion.php?simular=1

 Simular una cartera en un mes específico
parseInsertarFaltantesWhatsappGestion.php?mes=2026-09&cartera=2121&simular=1

 Insertar una sola cartera
parseInsertarFaltantesWhatsappGestion.php?mes=2026-09&cartera=2121

 Insertar el mes actual, todas las carteras
parseInsertarFaltantesWhatsappGestion.php*/

$params = ['mes' => date('Y-m'), 'cartera' => '', 'simular' => '0'];

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
        if (isset($_GET[$nombre]) && $_GET[$nombre] !== '') {
            $params[$nombre] = trim((string)$_GET[$nombre]);
        }
    }
}

if (!preg_match('/^(\d{4})-(\d{2})$/', $params['mes'], $m) || (int)$m[2] < 1 || (int)$m[2] > 12) {
    echo "ERROR: parametro mes invalido. Formato esperado YYYY-MM.<br>\n";
    exit;
}
if ($params['cartera'] !== '' && !ctype_digit($params['cartera'])) {
    echo "ERROR: parametro cartera invalido.<br>\n";
    exit;
}

$desde   = mktime(0, 0, 0, (int)$m[2], 1, (int)$m[1]);
$hasta   = mktime(0, 0, 0, (int)$m[2] + 1, 1, (int)$m[1]);
$simular = ($params['simular'] === '1');

// Carteras de ventas segun MySQL, mismo criterio que cuPGgestionVentas
$carterasVentas = [];
$db = new MYSQLDB();
$db->query('SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo="VENTAS"');
while ($row = $db->fetchRow()) {
    $carterasVentas[(string)$row['cobCartera_id']] = true;
}

echo "============================================================<br>\n";
echo "Canal: WHATSAPP | Cubos: " . CUBO_COBRANZA . " (todas) y " . CUBO_VENTAS . " (solo ventas)<br>\n";
echo "Rango ws_fecha: " . date('Y-m-d H:i:s', $desde) . " a " . date('Y-m-d H:i:s', $hasta - 1) . "<br>\n";
echo "Cartera: " . ($params['cartera'] !== '' ? $params['cartera'] : 'TODAS') . "<br>\n";
echo "Carteras de ventas: " . (empty($carterasVentas) ? 'ninguna' : implode(', ', array_keys($carterasVentas))) . "<br>\n";
echo "Modo: " . ($simular ? "SIMULACION (no guarda)" : "INSERCION") . "<br>\n";
echo "============================================================<br>\n";

// WhatsApp del mes que ya estan en cada cubo (fechaProgramacion guarda ws_fecha)
$existentesGC = cargarExistentes(CUBO_COBRANZA, 'cubGC', $desde, $hasta);
$existentesGV = cargarExistentes(CUBO_VENTAS, 'cubGV', $desde, $hasta);

echo "Ya existentes en " . CUBO_COBRANZA . ": " . count($existentesGC) . "<br>\n";
echo "Ya existentes en " . CUBO_VENTAS . ": " . count($existentesGV) . "<br>\n";

$condWs = ['ws_fecha' => ['$gte' => $desde, '$lt' => $hasta]];
if ($params['cartera'] !== '') {
    $condWs['ws_carteraId'] = (int)$params['cartera'];
}
$camposWs = [
    '_id',
    'ws_factura',
    'ws_carteraId',
    'ws_fecha',
    'ws_estadoEnvioFecha',
    'ws_numeroWP',
    'ws_campaniaId',
    'ws_campaniaNombre',
    'ws_duracionSegundos',
    'ws_tipificacion',
];

$mdbWs = new MYMONGODB();
$totalWs = $mdbWs->buscar('avProgramadasWhatsApp', $condWs, $camposWs, ['_id' => 1]);

echo "WhatsApp en avProgramadasWhatsApp: " . (int)$totalWs . "<br>\n";

$resGC = nuevoResumen();
$resGV = nuevoResumen();
$sinCredito  = [];
$errores     = [];
$cacheCred   = [];
$cacheBaseGC = [];
$cacheBaseGV = [];
$mdbCubo     = new MYMONGODB();

while ($ws = $mdbWs->siguiente()) {

    $idWs     = (string)($ws['_id'] ?? '');
    $factura  = (string)($ws['ws_factura'] ?? '');
    $cartera  = (string)($ws['ws_carteraId'] ?? '');
    $esVentas = isset($carterasVentas[$cartera]);

    $faltaGC = !isset($existentesGC[$idWs]);
    $faltaGV = $esVentas && !isset($existentesGV[$idWs]);

    if (!$faltaGC) $resGC['yaExistian']++;
    if ($esVentas && !$faltaGV) $resGV['yaExistian']++;

    if (!$faltaGC && !$faltaGV) {
        continue;
    }

    if ($factura === '' || $cartera === '') {
        $errores[] = "WhatsApp {$idWs} sin ws_factura o ws_carteraId";
        continue;
    }

    try {
        // Mismo criterio que process(): un credito por factura y cartera
        $clave = $cartera . '|' . $factura;
        if (!array_key_exists($clave, $cacheCred)) {
            $cacheCred[$clave] = null;
            $mdbCred = new MYMONGODB();
            $mdbCred->buscar('cbCreditos', ['cre_factura' => $factura, 'cre_carteraId' => $cartera], [], [], 1);
            if ($row = $mdbCred->siguiente()) {
                $cacheCred[$clave] = $row;
            }
        }

        $credito = $cacheCred[$clave];
        if ($credito === null) {
            $sinCredito[] = "{$idWs} (factura {$factura}, cartera {$cartera})";
            continue;
        }

        $etiqueta = "{$idWs} (factura {$factura})";

        // COBRANZA: todas las carteras
        if ($faltaGC) {
            if (!isset($cacheBaseGC[$clave])) {
                $cacheBaseGC[$clave] = crearDatosBaseGC($credito);
            }
            $datos = $cacheBaseGC[$clave];

            // createData() de cobranza solo toma WhatsApp desde el inicio del periodo
            if ((int)($ws['ws_fecha'] ?? 0) < (int)$datos['cubGC_fechaInicio']) {
                $resGC['fueraPeriodo'][] = $etiqueta;
            } else {
                $fila = crearFilaWhatsappGC($datos, $ws);
                insertarSiFalta($mdbCubo, CUBO_COBRANZA, 'cubGC', $fila, $idWs, $simular, $existentesGC, $resGC, $etiqueta);
            }
        }

        // VENTAS: solo carteras de ventas
        if ($faltaGV) {
            if (!isset($cacheBaseGV[$clave])) {
                $cacheBaseGV[$clave] = crearDatosBaseGV($credito);
            }
            $fila = crearFilaWhatsappGV($cacheBaseGV[$clave], $ws);
            insertarSiFalta($mdbCubo, CUBO_VENTAS, 'cubGV', $fila, $idWs, $simular, $existentesGV, $resGV, $etiqueta);
        }
    } catch (Throwable $e) {
        $errores[] = "WhatsApp {$idWs}: " . $e->getMessage();
    }
}

imprimirResumen(CUBO_COBRANZA, $resGC, $simular);
imprimirResumen(CUBO_VENTAS, $resGV, $simular);

echo "<hr>\n";
echo "Sin credito en cbCreditos (omitidos en ambos cubos): " . count($sinCredito) . "<br>\n";
echo "Errores: " . count($errores) . "<br>\n";
imprimirLista("Detalle errores", $errores);
imprimirLista("Sin credito", $sinCredito);

function nuevoResumen(): array
{
    return ['yaExistian' => 0, 'fueraPeriodo' => [], 'insertados' => []];
}

function cargarExistentes(string $coleccion, string $pref, int $desde, int $hasta): array
{
    $existentes = [];
    $mdb = new MYMONGODB();
    $mdb->buscar($coleccion, [
        $pref . '_canal'             => 'WHATSAPP',
        $pref . '_fechaProgramacion' => ['$gte' => $desde, '$lt' => $hasta],
    ], [$pref . '_avId']);
    while ($doc = $mdb->siguiente()) {
        if (!empty($doc[$pref . '_avId'])) {
            $existentes[(string)$doc[$pref . '_avId']] = true;
        }
    }
    return $existentes;
}

// Revalida con la misma condicion de la clase por si el process() lo inserto mientras corria
function insertarSiFalta($mdbCubo, string $coleccion, string $pref, array $fila, string $idWs, bool $simular, array &$existentes, array &$res, string $etiqueta): void
{
    $condCubo = [
        $pref . '_numFactura' => (string)$fila[$pref . '_numFactura'],
        $pref . '_carteraId'  => (string)$fila[$pref . '_carteraId'],
        $pref . '_avId'       => new MongoDB\BSON\ObjectId($idWs),
    ];

    if ($mdbCubo->buscar($coleccion, $condCubo, ['_id'], [], 1)) {
        $existentes[$idWs] = true;
        $res['yaExistian']++;
        return;
    }

    if (!$simular) {
        $mdbCubo->guardar($coleccion, $fila);
    }

    $existentes[$idWs] = true;
    $res['insertados'][] = $etiqueta;
}

function imprimirResumen(string $coleccion, array $res, bool $simular): void
{
    echo "<hr><b>{$coleccion}</b><br>\n";
    echo "Ya existian (omitidos): {$res['yaExistian']}<br>\n";
    if ($coleccion === CUBO_COBRANZA) {
        echo "Antes del inicio del periodo (omitidos): " . count($res['fueraPeriodo']) . "<br>\n";
    }
    echo ($simular ? "Se insertarian: " : "Insertados: ") . count($res['insertados']) . "<br>\n";

    imprimirLista("Antes del inicio del periodo", $res['fueraPeriodo']);
    imprimirLista($simular ? "WhatsApp a insertar" : "WhatsApp insertados", $res['insertados']);
}

function imprimirLista(string $titulo, array $lista, int $max = 1000): void
{
    if (empty($lista)) return;
    echo "{$titulo}:<br>\n" . implode("<br>\n", array_slice($lista, 0, $max)) . "<br>\n";
    if (count($lista) > $max) {
        echo "... y " . (count($lista) - $max) . " mas<br>\n";
    }
}

function buscarPonderacion(int $campaniaId, string $respuesta2, int $carteraId): ?array
{
    $dbMap = new MYSQLDB();
    $sql = $dbMap->mkSQL(
        "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst
         WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND cobMapArbolGst_carteraId=%N",
        $campaniaId,
        $respuesta2,
        $carteraId
    );
    $dbMap->query($sql);
    $rowMap = $dbMap->fetchRow();
    return $rowMap ?: null;
}

// Copia de la parte comun de createData() de cuPGgestionCobranzaMysql (mantener sincronizado)
function crearDatosBaseGC(array $row): array
{
    $datos = [
        //mongo cbCreditos
        'cubGC_cbcreId'                           => $row['_id'] ?? null,
        'cubGC_sponsor'                           => SPONSOR,
        'cubGC_nombres'                           => $row['cre_nombres'] ?? '',
        'cubGC_apellidos'                         => $row['cre_apellidos'] ?? '',
        'cubGC_cedula'                            => $row['cre_cedula'] ?? '',
        'cubGC_riesgo'                            => $row['cre_calificacion'] ?? '',
        'cubGC_deudaNetaActual'                   => (float) ($row['cre_deudaNeta'] ?? 0),
        'cubGC_diasMora'                          => (int) ($row['cre_diasMoraFactura'] ?? 0),
        'cubGC_producto'                          => $row['cre_producto'] ?? '',
        'cubGC_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
        'cubGC_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
        'cubGC_numFactura'                        => $row['cre_factura'] ?? '',
        'cubGC_ciudad'                            => $row['cre_ciudad'] ?? '',
        'cubGC_capitalActual'                     => (float)($row['cre_saldoCapital'] ?? 0),
        'cubGC_carteraId'                         => $row['cre_carteraId'] ?? '',
        'cubGC_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
        'cubGC_usuariosId'                        => $row['usUsuarios_id'] ?? '',

        //Mongo CRM
        'cubGC_crmId'                             => '',
        'cubGC_edad'                              => '',
        'cubGC_sexo'                              => '',
        'cubGC_estadoCivil'                       => '',
        //Mongo cbDirecciones
        'cubGC_dirId'                             => '',
        'cubGC_direccion'                         => '',
        //Mongo cbCargaDetallePacifico
        'cubGC_cdetId'                            => '',
        'cubGC_capitalInicial'                    => 0,
        'cubGC_deudaNetaInicial'                  => 0,
        //Mongo control_carga_periodo
        'cubGC_ctrId'                             => '',
        'cubGC_fechaInicio'                       => 0,
        'cubGC_fechaFin'                          => 0,
        //Mysql cobmaparbolgst
        'cubGC_cobmaparbolgstId'                  => '',
        'cubGC_ponderacion'                       => 0,
        //Mongo avProgramadasWhatsapp
        'cubGC_avId'                              => '',
        'cubGC_fechaGestion'                      => 0,
        'cubGC_fechaProgramacion'                 => 0,
        'cubGC_telefono'                          => '',
        'cubGC_email'                             => '',
        'cubGC_campaniaId'                        => '',
        'cubGC_campaniaNombre'                    => '',
        'cubGC_canal'                             => '',
        'cubGC_proveedor'                         => '',
        'cubGC_proveedorSip'                      => '',
        'cubGC_nivelContacto'                     => '',
        'cubGC_duracionGestionSeg'                => '',
        'cubGC_tipificacion_respuesta1'           => '',
        'cubGC_tipificacion_respuesta2'           => '',
        'cubGC_tipificacion_compromiso'           => '',
        'cubGC_tipificacion_montoCompromiso'      => 0,
        'cubGC_resumen'                           => '',
        'cubGC_llamadaId'                         => 0,
        'cubGC_analisisCalidad'                   => null,
        'cubGC_abierto'                           => 0,
        'cubGC_cantidad_abierto'                  => 0,
        'cubGC_horaInicio'                        => 0,
        'cubGC_horaFin'                           => 0,
    ];

    //Mongo CRM
    $mdbCrm = new MYMONGODB();
    $mdbCrm->buscar('CRM', ['crm_cedula' => (string)$datos['cubGC_cedula']], ['_id', 'crm_edad', 'crm_sexo', 'crm_estadoCivil'], [], 1);
    while ($doc = $mdbCrm->siguiente()) {
        $datos['cubGC_crmId']       = $doc['_id'] ?? null;
        $datos['cubGC_edad']        = $doc['crm_edad'] ?? null;
        $datos['cubGC_sexo']        = $doc['crm_sexo'] ?? null;
        $datos['cubGC_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;
        break;
    }

    //Mongo cbDirecciones
    $mdbDir = new MYMONGODB();
    $mdbDir->buscar('cbDirecciones', ['dir_cedula' => (string)$datos['cubGC_cedula']], ['_id', 'dir_direccion'], [], 1);
    while ($doc = $mdbDir->siguiente()) {
        $datos['cubGC_dirId']     = $doc['_id'] ?? null;
        $datos['cubGC_direccion'] = $doc['dir_direccion'] ?? null;
        break;
    }

    //Mongo cbCargaDetallePacifico
    $mdbDet = new MYMONGODB();
    $condDet = [
        'cedula'    => (string)$datos['cubGC_cedula'],
        'carteraId' => (int)$datos['cubGC_carteraId'],
        'periodo'   => (int)$datos['cubGC_ciclo'],
        'inicial'   => 1,
    ];
    $mdbDet->buscar('cbCargaDetallePacifico', $condDet, ['_id', 'saldoCapital', 'deudaNeta'], ['_id' => -1], 1);
    while ($doc = $mdbDet->siguiente()) {
        $datos['cubGC_cdetId']           = $doc['_id'] ?? null;
        $datos['cubGC_capitalInicial']   = (float)($doc['saldoCapital'] ?? 0);
        $datos['cubGC_deudaNetaInicial'] = (float)($doc['deudaNeta'] ?? 0);
        break;
    }

    //Mongo control_carga_periodo
    $mdbCargaPer = new MYMONGODB();
    $condCargaPer = [
        'cartera' => (int)$datos['cubGC_carteraId'],
        'periodo' => (int)$datos['cubGC_ciclo'],
        'fecha'   => (int)$datos['cubGC_fechaPeriodo'],
        'activo'  => 1,
    ];
    $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, ['_id', 'fecha', 'fechaFin'], ['_id' => -1], 1);
    while ($doc = $mdbCargaPer->siguiente()) {
        $datos['cubGC_ctrId']       = $doc['_id'] ?? null;
        $datos['cubGC_fechaInicio'] = (int)($doc['fecha'] ?? 0);
        $datos['cubGC_fechaFin']    = (int)($doc['fechaFin'] ?? 0);
        break;
    }

    return $datos;
}

// Copia del bloque avProgramadasWhatsApp de createData() de cobranza (mantener sincronizado)
function crearFilaWhatsappGC(array $datos, array $doc): array
{
    $fila = $datos;
    $fila['cubGC_avId']                    = $doc['_id'] ?? null;
    $fila['cubGC_canal']                   = 'WHATSAPP';
    $fila['cubGC_fechaGestion']            = (int)($doc['ws_estadoEnvioFecha'] ?? 0);
    $fila['cubGC_fechaProgramacion']       = (int)($doc['ws_fecha'] ?? 0);
    $fila['cubGC_telefono']                = $doc['ws_numeroWP'] ?? null;
    $fila['cubGC_campaniaId']              = (int)($doc['ws_campaniaId'] ?? 0);
    $fila['cubGC_campaniaNombre']          = $doc['ws_campaniaNombre'] ?? null;
    $fila['cubGC_nivelContacto']           = $doc['ws_tipificacion']['respuesta1'] ?? null;
    $fila['cubGC_duracionGestionSeg']      = $doc['ws_duracionSegundos'] ?? null;
    $fila['cubGC_tipificacion_respuesta1'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
    $fila['cubGC_tipificacion_respuesta2'] = $doc['ws_tipificacion']['respuesta2'] ?? null;
    $fila['cubGC_resumen']                 = $doc['ws_tipificacion']['resumen'] ?? null;

    $rowMap = buscarPonderacion((int)$fila['cubGC_campaniaId'], (string)$fila['cubGC_tipificacion_respuesta2'], (int)$fila['cubGC_carteraId']);
    if ($rowMap) {
        $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
        $fila['cubGC_ponderacion']      = (int)($rowMap['cobMapArbolGst_ponderacion'] ?? 0);
    }

    return $fila;
}

// Copia de la parte comun de createData() de cuPGgestionVentas (mantener sincronizado)
function crearDatosBaseGV(array $row): array
{
    $datos = [
        //mongo cbCreditos
        'cubGV_cbcreId'                           => $row['_id'] ?? null,
        'cubGV_sponsor'                           => SPONSOR,
        'cubGV_usuariosId'                        => $row['usUsuarios_id'] ?? '',
        'cubGV_nombres'                           => $row['cre_nombres'] ?? '',
        'cubGV_apellidos'                         => $row['cre_apellidos'] ?? '',
        'cubGV_cedula'                            => $row['cre_cedula'] ?? '',
        'cubGV_producto'                          => $row['cre_producto'] ?? '',
        'cubGV_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
        'cubGV_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
        'cubGV_numFactura'                        => $row['cre_factura'] ?? '',
        'cubGV_ciudad'                            => $row['cre_ciudad'] ?? '',
        'cubGV_carteraId'                         => $row['cre_carteraId'] ?? '',
        'cubGV_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
        // Igual que la clase: la clave lleva espacios al final
        'cubGV_fechaCarga   '                     => $row['cre_fechaCarga'] ?? '',

        //Mongo CRM
        'cubGV_crmId'                             => '',
        'cubGV_edad'                              => '',
        'cubGV_sexo'                              => '',
        'cubGV_estadoCivil'                       => '',
        //Mongo cbDirecciones
        'cubGV_dirId'                             => '',
        'cubGV_direccion'                         => '',

        //SQL cobmaparbolgst
        'cubGV_cobmaparbolgstId'                  => '',
        'cubGV_ponderacion'                       => 0,
        //Mongo avProgramadasWhatsapp
        'cubGV_avId'                              => '',
        'cubGV_fechaGestion'                      => 0,
        'cubGV_fechaProgramacion'                 => 0,
        'cubGV_telefono'                          => '',
        'cubGV_email'                             => '',
        'cubGV_campaniaId'                        => '',
        'cubGV_campaniaNombre'                    => '',
        'cubGV_canal'                             => '',
        'cubGV_proveedor'                         => '',
        'cubGV_proveedorSip'                      => '',
        'cubGV_nivelContacto'                     => '',
        'cubGV_duracionGestionSeg'                => '',
        'cubGV_tipificacion_respuesta1'           => '',
        'cubGV_tipificacion_respuesta2'           => '',
        'cubGV_resumen'                           => '',
        'cubGV_llamadaId'                         => 0,
        'cubGV_analisisCalidad'                   => null,
        'cubGV_abierto'                           => 0,
        'cubGV_cantidad_abierto'                  => 0,
        'cubGV_horaInicio'                        => 0,
        'cubGV_horaFin'                           => 0,
    ];

    //Mongo CRM
    $mdbCrm = new MYMONGODB();
    $mdbCrm->buscar('CRM', ['crm_cedula' => (string)$datos['cubGV_cedula']], ['_id', 'crm_edad', 'crm_sexo', 'crm_estadoCivil'], [], 1);
    while ($doc = $mdbCrm->siguiente()) {
        $datos['cubGV_crmId']       = $doc['_id'] ?? null;
        $datos['cubGV_edad']        = $doc['crm_edad'] ?? null;
        $datos['cubGV_sexo']        = $doc['crm_sexo'] ?? null;
        $datos['cubGV_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;
        break;
    }

    //Mongo cbDirecciones
    $mdbDir = new MYMONGODB();
    $mdbDir->buscar('cbDirecciones', ['dir_cedula' => (string)$datos['cubGV_cedula']], ['_id', 'dir_direccion'], [], 1);
    while ($doc = $mdbDir->siguiente()) {
        $datos['cubGV_dirId']     = $doc['_id'] ?? null;
        $datos['cubGV_direccion'] = $doc['dir_direccion'] ?? null;
        break;
    }

    return $datos;
}

// Copia del bloque avProgramadasWhatsApp de createData() de ventas (mantener sincronizado)
function crearFilaWhatsappGV(array $datos, array $doc): array
{
    $fila = $datos;
    $fila['cubGV_avId']                    = $doc['_id'] ?? null;
    $fila['cubGV_canal']                   = 'WHATSAPP';
    $fila['cubGV_fechaGestion']            = (int)($doc['ws_estadoEnvioFecha'] ?? 0);
    $fila['cubGV_fechaProgramacion']       = (int)($doc['ws_fecha'] ?? 0);
    $fila['cubGV_telefono']                = $doc['ws_numeroWP'] ?? null;
    $fila['cubGV_campaniaId']              = (int)($doc['ws_campaniaId'] ?? 0);
    $fila['cubGV_campaniaNombre']          = $doc['ws_campaniaNombre'] ?? null;
    $fila['cubGV_nivelContacto']           = $doc['ws_tipificacion']['respuesta1'] ?? null;
    $fila['cubGV_duracionGestionSeg']      = $doc['ws_duracionSegundos'] ?? null;
    $fila['cubGV_tipificacion_respuesta1'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
    $fila['cubGV_tipificacion_respuesta2'] = $doc['ws_tipificacion']['respuesta2'] ?? null;
    $fila['cubGV_resumen']                 = $doc['ws_tipificacion']['resumen'] ?? null;

    $rowMap = buscarPonderacion((int)$fila['cubGV_campaniaId'], (string)$fila['cubGV_tipificacion_respuesta2'], (int)$fila['cubGV_carteraId']);
    if ($rowMap) {
        $fila['cubGV_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
        $fila['cubGV_ponderacion']      = (int)($rowMap['cobMapArbolGst_ponderacion'] ?? 0);
    }

    return $fila;
}

echo "EJECUCION_COMPLETA";

?><?

    //_FIN_DE_ARCHIVO
    ?>
