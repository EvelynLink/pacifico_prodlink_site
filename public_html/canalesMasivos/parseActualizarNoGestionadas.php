<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseActualizarNoGestionadas no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseActualizarNoGestionadas.php", "", $_SERVER["argv"][0]);
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

// Historico de cargas diarias, solo existe para Cobranza (se usa para fechaAsignacion).
define('COLECCION_HISTORICO_ASIGNACIONES', 'avHistorialAsignacionesDiarias');

// ============================================================================
// USO:
//   Periodo actual (por defecto, igual que antes):
//       Web:  parseActualizarNoGestionadas.php
//       CLI:  php parseActualizarNoGestionadas.php
//   Periodo anterior:
//       Web:  parseActualizarNoGestionadas.php?periodo=anterior
//       CLI:  php parseActualizarNoGestionadas.php --periodo=anterior
// ============================================================================

$modoPeriodo = 'actual';

if (php_sapi_name() === 'cli') {
    if (isset($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            if (strpos($arg, '--periodo=') === 0) {
                $modoPeriodo = substr($arg, strlen('--periodo='));
            }
        }
    }
} else {
    if (isset($_GET['periodo'])) {
        $modoPeriodo = $_GET['periodo'];
    }
}

if (!in_array($modoPeriodo, ['actual', 'anterior'], true)) {
    $modoPeriodo = 'actual';
}

echo "============================================================\n";
echo "Modo periodo: {$modoPeriodo}\n";
echo "============================================================\n";

// COBRANZA
procesarCarteras('COBRANZA', [
    'coleccionAsignacion' => 'cuAsignacionesGestionAP',
    'coleccionGestion'    => 'cuGestionCobranzaMysql',
    'prefAsignacion'      => 'cubAG',
    'prefGestion'         => 'cubGC'
], $modoPeriodo);

// VENTAS
procesarCarteras('VENTAS', [
    'coleccionAsignacion' => 'cuAsignacionesGestionVentas',
    'coleccionGestion'    => 'cuGestionVentas',
    'prefAsignacion'      => 'cubAV',
    'prefGestion'         => 'cubGV'
], $modoPeriodo);


function procesarCarteras($tipoCartera, $config, $modoPeriodo = 'actual')
{
    $db = new MYSQLDB();

    $carteras = [];
    $procesados = [];
    $carterasSinPeriodo = [];

    // TRAER CARTERAS
    // Cobranza incluye todo lo que NO este marcado como VENTAS en MySQL
    // (la coleccion Mongo ya no tiene tipo, se separo de Ventas).
    if ($tipoCartera === 'COBRANZA') {
        $sql = "SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo IS NULL OR cobCartera_tipo <> 'VENTAS'";
    } else {
        $sql = $db->mkSQL(
            'SELECT cobCartera_id FROM cobcartera WHERE  cobCartera_tipo=%Q',
            $tipoCartera
        );
    }

    $db->query($sql);

    while ($rowMysql = $db->fetchRow()) {
        $carteras[] = (string)$rowMysql['cobCartera_id'];
    }

    $periodosAProcesar = obtenerPeriodosAProcesar($carteras, $modoPeriodo, $carterasSinPeriodo);

    foreach ($periodosAProcesar as $row) {

        $cartera = (string)$row["cartera"];

        if (!in_array($cartera, $carteras)) {
            continue;
        }

        $periodo  = (int)$row["periodo"];
        $fecha    = (int)$row["fecha"];
        $fechaFin = (int)$row["fechaFin"];

        $fechaLimiteSuperiorGestion = $row['_fechaLimiteSuperior'] ?? null;

        $key = $cartera . "_" . $periodo . "_" . $fecha . "_" . $fechaFin;



        if (in_array($key, $procesados)) {
            continue;
        }

        $procesados[] = $key;



        $desde = strtotime(date('Y-m-d', $fecha) . " 00:00:00");
        $hasta = strtotime(date('Y-m-d', $fechaFin) . " 23:59:59");

        // PREFIJOS
        $prefAsig = $config['prefAsignacion'];
        $prefGest = $config['prefGestion'];

        // CONDICION ASIGNACIONES
        // Ventas no usa ciclo (cre_periodo suele venir en 0), basta cartera + fechaPeriodo
        $condicionAsignacion = [
            $prefAsig . "_carteraId"    => (string)$cartera,
            $prefAsig . "_fechaPeriodo" => (int)$fecha
        ];
        if ($tipoCartera === 'COBRANZA') {
            $condicionAsignacion[$prefAsig . "_ciclo"] = (int)$periodo;
        }

        // trigger_error("condicionAsignacion ".print_r($condicionAsignacion,true));

        $mongoAsig = new MYMONGODB();

        $totalAsignaciones = $mongoAsig->buscar(
            $config['coleccionAsignacion'],
            $condicionAsignacion
        );

        // trigger_error("totalAsignaciones ".$totalAsignaciones);


        $conGestion = 0;
        $facturasConGestion = [];

        if ($totalAsignaciones > 0) {



            while ($datos = $mongoAsig->siguiente()) {

                //   - modo actual:   fechaGestion >= fechaInicio del periodo
                //                    activo, SIN tope superior (el periodo
                //                    sigue abierto).
                //   - modo anterior: fechaGestion >= fechaInicio del periodo
                //                    anterior Y fechaGestion < el instante en
                //                    que arranca el periodo activo actual
                //                    (no el 'fechaFin' guardado en el lote
                //                    anterior).
                $condicionFechaGestion = [
                    '$gte' => (int)$datos[$prefAsig . '_fechaInicio']
                ];

                if ($fechaLimiteSuperiorGestion !== null) {
                    $condicionFechaGestion['$lt'] = (int)$fechaLimiteSuperiorGestion;
                }

                $condGestion = [
                    $prefGest . "_numFactura"   => (string)$datos[$prefAsig . '_numFactura'],
                    $prefGest . "_carteraId"    => (string)$datos[$prefAsig . '_carteraId'],

                    $prefGest . "_fechaGestion" => $condicionFechaGestion,

                    // Excluir gestiones que no representan un contacto real
                    $prefGest . "_tipificacion_respuesta2" => [
                        '$nin' => TIPIFICACIONES_EXCLUIDAS
                    ]
                ];

                $camposGestion = [
                    '_id',
                    $prefGest . '_avId',
                    $prefGest . '_canal',
                    $prefGest . '_ponderacion',
                    $prefGest . '_tipificacion_respuesta1',
                    $prefGest . '_tipificacion_respuesta2',
                    $prefGest . '_fechaGestion',
                    $prefGest . '_telefono',
                    $prefGest . '_email'
                ];

                // Ventas: gestiones solo por factura + cartera, sin ciclo
                if ($tipoCartera === 'COBRANZA') {
                    $condGestion[$prefGest . "_ciclo"] = (int)$datos[$prefAsig . '_ciclo'];
                }

                // Compromiso/montoCompromiso: concepto exclusivo de Cobranza, Ventas no lo maneja.
                if ($tipoCartera === 'COBRANZA') {
                    $camposGestion[] = $prefGest . '_tipificacion_compromiso';
                    $camposGestion[] = $prefGest . '_tipificacion_montoCompromiso';
                }

                // La "mejor" gestion se decide unicamente por ponderacion
                // (mayor ponderacion gana).
                $mdbGestion = new MYMONGODB();

                $existeGestion = $mdbGestion->buscar(
                    $config['coleccionGestion'],
                    $condGestion,
                    $camposGestion,
                    [$prefGest . '_ponderacion' => -1]
                );

                //trigger_error("existeGestion ". print_r($existeGestion,true));

                // Estado actualmente guardado en la asignacion (general, aplanado)
                $gestionadaActual   = (int)($datos[$prefAsig . '_gestionada'] ?? 0);
                $mejorGeneralActual = [
                    'cuGestionId'     => $datos[$prefAsig . '_cuGestionId'] ?? '',
                    'canal'           => $datos[$prefAsig . '_canal'] ?? '',
                    'ponderacion'     => (int)($datos[$prefAsig . '_ponderacion'] ?? 0),
                    'tipificacion1'   => $datos[$prefAsig . '_tipificacion1'] ?? '',
                    'tipificacion2'   => $datos[$prefAsig . '_tipificacion2'] ?? '',
                    'fechaGestion'    => (int)($datos[$prefAsig . '_fechaGestion'] ?? 0),
                    'telefono'        => (string)($datos[$prefAsig . '_telefono'] ?? ''),
                    'email'           => (string)($datos[$prefAsig . '_email'] ?? ''),
                ];

                if ($tipoCartera === 'COBRANZA') {
                    $mejorGeneralActual['compromiso']      = (string)($datos[$prefAsig . '_compromiso'] ?? '');
                    $mejorGeneralActual['montoCompromiso'] = (float)($datos[$prefAsig . '_montoCompromiso'] ?? 0);
                }
                $mejorGestionActual = $datos[$prefAsig . '_mejorGestion'] ?? [];

                // Estado recalculado desde cero a partir de la coleccion de gestion
                $nuevaGestionada   = 0;
                $mejorGlobalNuevo  = null;
                $mejoresPorCanal   = [];
                $ultimaGlobalNueva = null;
                $ultimasPorCanal   = [];
                $intensidadNueva   = array_fill_keys(array_values(MAPA_CANALES), 0);

                if ($existeGestion > 0) {

                    while ($doc = $mdbGestion->siguiente()) {

                        // 'gestionada' es un indicador global: si CUALQUIER
                        // gestion (de cualquier canal) tiene avId, se marca 1.
                        if (isset($doc[$prefGest . '_avId'])) {
                            $nuevaGestionada = 1;
                        }

                        $codigoCanal = MAPA_CANALES[$doc[$prefGest . '_canal'] ?? null] ?? null;

                        // Telefono solo para AV/WHATSAPP, email solo para EMAIL.
                        $telefono = '';
                        $email    = '';
                        if ($codigoCanal === 'AV' || $codigoCanal === 'WHATSAPP') {
                            $telefono = (string)($doc[$prefGest . '_telefono'] ?? '');
                        } elseif ($codigoCanal === 'EMAIL') {
                            $email = (string)($doc[$prefGest . '_email'] ?? '');
                        }

                        $candidato = [
                            'cuGestionId'     => $doc['_id'] ?? null,
                            'canal'           => $codigoCanal ?? '',
                            'ponderacion'     => (int)$doc[$prefGest . '_ponderacion'],
                            'tipificacion1'   => (string)$doc[$prefGest . '_tipificacion_respuesta1'],
                            'tipificacion2'   => (string)$doc[$prefGest . '_tipificacion_respuesta2'],
                            'fechaGestion'    => (int)($doc[$prefGest . '_fechaGestion'] ?? 0),
                            'telefono'        => $telefono,
                            'email'           => $email,
                        ];

                        if ($tipoCartera === 'COBRANZA') {
                            $candidato['compromiso']      = (string)($doc[$prefGest . '_tipificacion_compromiso'] ?? '');
                            $candidato['montoCompromiso'] = (float)($doc[$prefGest . '_tipificacion_montoCompromiso'] ?? 0);
                        }

                        // Viene ordenado por ponderacion desc, asi que el
                        // primer documento -global y por cada canal- ya es
                        // el de mayor ponderacion.
                        if ($mejorGlobalNuevo === null) {
                            $mejorGlobalNuevo = $candidato;
                        }

                        if ($codigoCanal !== null && !isset($mejoresPorCanal[$codigoCanal])) {
                            $mejoresPorCanal[$codigoCanal] = $candidato;
                        }

                        // La "ultima" gestion se decide por fecha de gestion, no por ponderacion.
                        if (esGestionMasReciente($candidato, $ultimaGlobalNueva)) {
                            $ultimaGlobalNueva = $candidato;
                        }

                        if ($codigoCanal === null) continue;

                        // Intensidad: numero de gestiones del periodo por canal
                        $intensidadNueva[$codigoCanal]++;

                        if (esGestionMasReciente($candidato, $ultimasPorCanal[$codigoCanal] ?? null)) {
                            $ultimasPorCanal[$codigoCanal] = $candidato;
                        }
                    }
                }

                // Gestion vacia por canal y general (esta ultima lleva canal). Compromiso solo en Cobranza.
                $gestionVaciaCanal = [
                    'cuGestionId'     => '',
                    'ponderacion'     => 0,
                    'tipificacion1'   => '',
                    'tipificacion2'   => '',
                    'fechaGestion'    => 0,
                    'telefono'        => '',
                    'email'           => '',
                ];

                if ($tipoCartera === 'COBRANZA') {
                    $gestionVaciaCanal['compromiso']      = '';
                    $gestionVaciaCanal['montoCompromiso'] = 0;
                }

                $gestionVaciaGeneral = ['canal' => ''] + $gestionVaciaCanal;

                $mejorGlobalNuevo  = $mejorGlobalNuevo ?? $gestionVaciaGeneral;
                $ultimaGlobalNueva = $ultimaGlobalNueva ?? $gestionVaciaGeneral;

                // Completa con default los canales que no tuvieron ninguna gestion
                foreach (MAPA_CANALES as $codigoCanal) {
                    $mejoresPorCanal[$codigoCanal] = $mejoresPorCanal[$codigoCanal] ?? $gestionVaciaCanal;
                    $ultimasPorCanal[$codigoCanal] = $ultimasPorCanal[$codigoCanal] ?? $gestionVaciaCanal;
                }

                // Compara un objeto "mejor gestion" (general o de un canal) actual vs nuevo
                $comparaMejorGestion = function (array $actual, array $nuevo): bool {
                    return (
                        (string)($actual['cuGestionId'] ?? '') !== (string)($nuevo['cuGestionId'] ?? '') ||
                        (string)($actual['canal'] ?? '')         !== (string)($nuevo['canal'] ?? '') ||
                        (int)($actual['ponderacion'] ?? 0)       !== $nuevo['ponderacion'] ||
                        (string)($actual['tipificacion1'] ?? '') !== $nuevo['tipificacion1'] ||
                        (string)($actual['tipificacion2'] ?? '') !== $nuevo['tipificacion2'] ||
                        (int)($actual['fechaGestion'] ?? 0)      !== $nuevo['fechaGestion'] ||
                        (string)($actual['compromiso'] ?? '')    !== (string)($nuevo['compromiso'] ?? '') ||
                        abs((float)($actual['montoCompromiso'] ?? 0) - (float)($nuevo['montoCompromiso'] ?? 0)) > 0.001 ||
                        (string)($actual['telefono'] ?? '')      !== (string)($nuevo['telefono'] ?? '') ||
                        (string)($actual['email'] ?? '')         !== (string)($nuevo['email'] ?? '')
                    );
                };

                $huboCambioGeneral = (
                    $gestionadaActual !== $nuevaGestionada ||
                    $comparaMejorGestion($mejorGeneralActual, $mejorGlobalNuevo)
                );

                $canalesConCambio = [];
                foreach (MAPA_CANALES as $codigoCanal) {

                    $actualCanal = $mejorGestionActual[$codigoCanal] ?? [];
                    $nuevoCanal  = $mejoresPorCanal[$codigoCanal];

                    if ($comparaMejorGestion($actualCanal, $nuevoCanal)) {
                        $canalesConCambio[] = $codigoCanal;
                    }
                }

                // Ultima gestion general tal como esta guardada (aplanada en *_ultimaGestion_<campo>)
                $ultimaGeneralActual = [];
                foreach (array_keys($gestionVaciaGeneral) as $campo) {
                    $ultimaGeneralActual[$campo] = $datos[$prefAsig . '_ultimaGestion_' . $campo] ?? null;
                }

                $ultimaGestionActual    = $datos[$prefAsig . '_ultimaGestion'] ?? [];
                $canalesUltimaConCambio = [];
                foreach (MAPA_CANALES as $codigoCanal) {
                    if ($comparaMejorGestion($ultimaGestionActual[$codigoCanal] ?? [], $ultimasPorCanal[$codigoCanal])) {
                        $canalesUltimaConCambio[] = $codigoCanal;
                    }
                }

                $huboCambioUltima = (
                    $comparaMejorGestion($ultimaGeneralActual, $ultimaGlobalNueva) ||
                    !empty($canalesUltimaConCambio)
                );

                $intensidadActual     = $datos[$prefAsig . '_intensidad'] ?? [];
                $intensidadTotalNueva = array_sum($intensidadNueva);
                $huboCambioIntensidad = (int)($datos[$prefAsig . '_intensidad_total'] ?? 0) !== $intensidadTotalNueva;
                foreach ($intensidadNueva as $codigoCanal => $cantidad) {
                    if ((int)($intensidadActual[$codigoCanal] ?? 0) !== $cantidad) {
                        $huboCambioIntensidad = true;
                    }
                }

                // Asignaciones creadas antes de que existieran estos campos: se completan
                // aunque todo quede en vacio/0 (sin gestiones no habria "cambio" que detectar).
                $faltanCamposNuevos = (
                    !array_key_exists($prefAsig . '_ultimaGestion', $datos) ||
                    !array_key_exists($prefAsig . '_ultimaGestion_cuGestionId', $datos) ||
                    !array_key_exists($prefAsig . '_intensidad', $datos) ||
                    !array_key_exists($prefAsig . '_intensidad_total', $datos)
                );

                // fechaAsignacion: solo se backfillea si todavia no esta seteada, nunca se pisa.
                $fechaAsignacionActual   = (int)($datos[$prefAsig . '_fechaAsignacion'] ?? 0);
                $necesitaFechaAsignacion = ($fechaAsignacionActual === 0);
                $nuevaFechaAsignacion    = $fechaAsignacionActual;

                if ($necesitaFechaAsignacion) {
                    $nuevaFechaAsignacion = obtenerFechaAsignacion($tipoCartera, $prefAsig, $datos, $fecha);
                }

                $huboCambio = (
                    $huboCambioGeneral ||
                    !empty($canalesConCambio) ||
                    $necesitaFechaAsignacion ||
                    $huboCambioUltima ||
                    $huboCambioIntensidad ||
                    $faltanCamposNuevos
                );

                if ($huboCambio) {

                    if ($gestionadaActual === 0 && $nuevaGestionada === 1) {
                        $tipoCambio = 'NO GESTIONADA -> GESTIONADA';
                    } elseif ($gestionadaActual === 1 && $nuevaGestionada === 0) {
                        $tipoCambio = 'GESTIONADA -> NO GESTIONADA';
                    } elseif ($huboCambioGeneral) {
                        $tipoCambio = 'RESINCRONIZACION (cambio la mejor gestion general)';
                    } elseif (!empty($canalesConCambio)) {
                        $tipoCambio = 'ACTUALIZACION CANAL(ES): ' . implode(', ', $canalesConCambio);
                    } elseif ($necesitaFechaAsignacion) {
                        $tipoCambio = 'BACKFILL FECHA ASIGNACION';
                    } elseif ($faltanCamposNuevos) {
                        $tipoCambio = 'BACKFILL ULTIMA GESTION/INTENSIDAD';
                    } elseif ($huboCambioUltima) {
                        $tipoCambio = 'ACTUALIZACION ULTIMA GESTION'
                            . (empty($canalesUltimaConCambio) ? '' : ': ' . implode(', ', $canalesUltimaConCambio));
                    } elseif ($huboCambioIntensidad) {
                        $tipoCambio = 'ACTUALIZACION INTENSIDAD';
                    } else {
                        $tipoCambio = 'RESINCRONIZACION';
                    }

                    $facturasConGestion[] =
                        "[{$tipoCambio}] " .
                        $datos[$prefAsig . '_numFactura'] .
                        " | Gestionada: " .
                        $gestionadaActual .
                        " -> " .
                        $nuevaGestionada;

                    $conGestion++;

                    // UPDATE
                    $criteria = [
                        '_id' => $datos['_id']
                    ];

                    $newRow = [
                        $prefAsig . '_gestionada'       => $nuevaGestionada,
                        $prefAsig . '_cuGestionId'      => $mejorGlobalNuevo['cuGestionId'],
                        $prefAsig . '_canal'            => $mejorGlobalNuevo['canal'],
                        $prefAsig . '_ponderacion'      => $mejorGlobalNuevo['ponderacion'],
                        $prefAsig . '_tipificacion1'    => $mejorGlobalNuevo['tipificacion1'],
                        $prefAsig . '_tipificacion2'    => $mejorGlobalNuevo['tipificacion2'],
                        $prefAsig . '_fechaGestion'     => $mejorGlobalNuevo['fechaGestion'],
                        $prefAsig . '_telefono'         => $mejorGlobalNuevo['telefono'],
                        $prefAsig . '_email'            => $mejorGlobalNuevo['email'],
                        $prefAsig . '_mejorGestion'     => $mejoresPorCanal,

                        $prefAsig . '_ultimaGestion_cuGestionId'   => $ultimaGlobalNueva['cuGestionId'],
                        $prefAsig . '_ultimaGestion_canal'         => $ultimaGlobalNueva['canal'],
                        $prefAsig . '_ultimaGestion_ponderacion'   => $ultimaGlobalNueva['ponderacion'],
                        $prefAsig . '_ultimaGestion_tipificacion1' => $ultimaGlobalNueva['tipificacion1'],
                        $prefAsig . '_ultimaGestion_tipificacion2' => $ultimaGlobalNueva['tipificacion2'],
                        $prefAsig . '_ultimaGestion_fechaGestion'  => $ultimaGlobalNueva['fechaGestion'],
                        $prefAsig . '_ultimaGestion_telefono'      => $ultimaGlobalNueva['telefono'],
                        $prefAsig . '_ultimaGestion_email'         => $ultimaGlobalNueva['email'],
                        $prefAsig . '_ultimaGestion'               => $ultimasPorCanal,

                        $prefAsig . '_intensidad'       => $intensidadNueva,
                        $prefAsig . '_intensidad_total' => $intensidadTotalNueva,
                    ];

                    // compromiso/montoCompromiso: Ventas no tiene estos campos en su cubo.
                    if ($tipoCartera === 'COBRANZA') {
                        $newRow[$prefAsig . '_compromiso']      = $mejorGlobalNuevo['compromiso'];
                        $newRow[$prefAsig . '_montoCompromiso'] = $mejorGlobalNuevo['montoCompromiso'];
                        $newRow[$prefAsig . '_ultimaGestion_compromiso']      = $ultimaGlobalNueva['compromiso'];
                        $newRow[$prefAsig . '_ultimaGestion_montoCompromiso'] = $ultimaGlobalNueva['montoCompromiso'];
                    }

                    if ($necesitaFechaAsignacion) {
                        $newRow[$prefAsig . '_fechaAsignacion'] = $nuevaFechaAsignacion;
                    }

                    $mdbUpdate = new MYMONGODB();

                    $mdbUpdate->actualizar(
                        $config['coleccionAsignacion'],
                        $criteria,
                        $newRow
                    );
                }
            }

            if ($conGestion > 0) {

                $rangoGestionTexto = $fechaLimiteSuperiorGestion !== null
                    ? "desde fechaInicio de cada asignacion hasta " . date('Y-m-d H:i:s', (int)$fechaLimiteSuperiorGestion - 1) . " (justo antes de que arranque el periodo activo)"
                    : "desde fechaInicio de cada asignacion, sin tope superior (periodo abierto)";

                echo "
                    <hr>

                    Tipo: $tipoCartera <br>
                    Modo periodo: $modoPeriodo <br>
                    Cartera: $cartera <br>
                    Periodo: $periodo <br>

                    Desde: " . date('Y-m-d', $desde) . "<br>
                    Hasta: " . date('Y-m-d', $hasta) . "<br>
                    Rango usado para buscar gestiones: $rangoGestionTexto <br><br>

                    Total asignaciones: $totalAsignaciones <br>
                    Asignaciones actualizadas: $conGestion <br><br>

                    Facturas actualizadas:<br>
                " . implode("<br>", $facturasConGestion) . " <br>
                ";
            }
        }
    }

    if ($modoPeriodo === 'anterior' && !empty($carterasSinPeriodo)) {
        echo "
            <hr>
            Tipo: $tipoCartera | Modo periodo: anterior <br>
            Carteras sin periodo anterior para procesar:<br>
            " . implode("<br>", $carterasSinPeriodo) . "<br>
        ";
    }
}

// Indica si la gestion candidata es mas reciente que la actual: gana la de mayor
// fecha de gestion y, si empatan, la que entro despues al cubo de gestiones
// (_id mayor). Mismo criterio que auxEsMasReciente() de los cubos de asignaciones.
function esGestionMasReciente(array $candidato, ?array $actual): bool
{
    if ($actual === null) return true;
    if ($candidato['fechaGestion'] !== $actual['fechaGestion']) {
        return $candidato['fechaGestion'] > $actual['fechaGestion'];
    }
    return strcmp((string)$candidato['cuGestionId'], (string)$actual['cuGestionId']) > 0;
}

// fechaAsignacion: primera carga del periodo actual. Cobranza la busca en el
// historico diario; Ventas (por ahora, hasta que se arme su propio proceso)
// simplemente toma la fechaCarga actual de la asignacion.
function obtenerFechaAsignacion(string $tipoCartera, string $prefAsig, array $datos, int $fechaPeriodo): int
{
    if ($tipoCartera === 'COBRANZA') {
        $mdbHist = new MYMONGODB();
        $condHist = [
            'avHistAsig_numFactura'   => (string)($datos[$prefAsig . '_numFactura'] ?? ''),
            'avHistAsig_carteraId'    => (string)($datos[$prefAsig . '_carteraId'] ?? ''),
            'avHistAsig_fechaPeriodo' => (int)$fechaPeriodo,
        ];

        $mdbHist->buscar(
            COLECCION_HISTORICO_ASIGNACIONES,
            $condHist,
            ['avHistAsig_fechaCarga'],
            ['avHistAsig_fechaCarga' => 1],
            1
        );
        $docHist = $mdbHist->siguiente();

        if ($docHist && isset($docHist['avHistAsig_fechaCarga'])) {
            return (int)$docHist['avHistAsig_fechaCarga'];
        }
    }

    // Ventas, o Cobranza sin historico encontrado: usar la fechaCarga actual como respaldo.
    return (int)($datos[$prefAsig . '_fechaCarga'] ?? 0);
}

function buscarUnPeriodoCartera(string $coleccion, $cartera, array $condicionExtra, array $sort): ?array
{
    foreach ([(int)$cartera, (string)$cartera] as $carteraTipada) {

        $condicion = array_merge(['cartera' => $carteraTipada], $condicionExtra);

        $mdb = new MYMONGODB();
        $mdb->buscar($coleccion, $condicion, [], $sort, 1);
        $row = $mdb->siguiente();

        if ($row) {
            return $row;
        }
    }

    return null;
}

function buscarTodosPeriodoCartera(string $coleccion, $cartera, array $condicionExtra, array $sort = []): array
{
    foreach ([(int)$cartera, (string)$cartera] as $carteraTipada) {

        $condicion = array_merge(['cartera' => $carteraTipada], $condicionExtra);

        $mdb = new MYMONGODB();
        $total = $mdb->buscar($coleccion, $condicion, [], $sort, 0);

        if ($total > 0) {
            $filas = [];
            while ($row = $mdb->siguiente()) {
                $filas[] = $row;
            }
            return $filas;
        }
    }

    return [];
}

function obtenerPeriodosAProcesar(array $carteras, string $modoPeriodo, array &$carterasSinPeriodo): array
{
    $periodos = [];

    if ($modoPeriodo === 'anterior') {

        foreach ($carteras as $cartera) {

            $rowActivo = buscarUnPeriodoCartera('control_carga_periodo', $cartera, ['activo' => 1], ['fecha' => -1]);

            if (!$rowActivo) {
                $carterasSinPeriodo[] = "Cartera {$cartera}: sin periodo activo, no se pudo ubicar el anterior.";
                continue;
            }

            $fechaActivo    = (int)$rowActivo['fecha'];
            $fechaFinActivo = (int)($rowActivo['fechaFin'] ?? 0);

            echo "[DEBUG anterior] Cartera {$cartera}: periodo activo usado como referencia = periodo "
                . (int)($rowActivo['periodo'] ?? 0)
                . ", desde " . date('Y-m-d', $fechaActivo)
                . " hasta " . date('Y-m-d', $fechaFinActivo) . "\n";

            avisarSiActivoNoContieneHoy($cartera, $rowActivo, $fechaActivo, $fechaFinActivo);

            $rowAnteriorRef = buscarUnPeriodoCartera('control_carga_periodo', $cartera, [
                'activo' => 0,
                'fecha'  => ['$lt' => $fechaActivo],
            ], ['fecha' => -1]);

            if (!$rowAnteriorRef) {
                $carterasSinPeriodo[] = "Cartera {$cartera}: no se encontro periodo anterior (activo:0) antes del periodo activo.";
                continue;
            }

            $fechaAnterior    = (int)$rowAnteriorRef['fecha'];
            $fechaFinAnterior = (int)($rowAnteriorRef['fechaFin'] ?? 0);

            $filasLoteAnterior = buscarTodosPeriodoCartera('control_carga_periodo', $cartera, [
                'fecha'    => $fechaAnterior,
                'fechaFin' => $fechaFinAnterior,
            ]);

            if (empty($filasLoteAnterior)) {
                $carterasSinPeriodo[] = "Cartera {$cartera}: se ubico la fecha del periodo anterior pero no se pudieron releer sus registros.";
                continue;
            }

            echo "[DEBUG anterior] Cartera {$cartera}: lote anterior encontrado, desde "
                . date('Y-m-d', $fechaAnterior) . " hasta " . date('Y-m-d', $fechaActivo - 1)
                . " (limite superior real = inicio del periodo activo, no el fechaFin guardado)"
                . " -> " . count($filasLoteAnterior) . " periodo(s): "
                . implode(', ', array_map(fn($f) => (int)($f['periodo'] ?? 0), $filasLoteAnterior)) . "\n";

            foreach ($filasLoteAnterior as $filaAnterior) {

                $filaAnterior['_fechaLimiteSuperior'] = $fechaActivo;
                $periodos[] = $filaAnterior;
            }
        }

        return $periodos;
    }

    // modo 'actual' (comportamiento original: un solo query, activo:1)
    $mdbActual = new MYMONGODB();
    $mdbActual->buscar('control_carga_periodo', ['activo' => 1]);

    while ($row = $mdbActual->siguiente()) {

        $cartera = (string)$row['cartera'];

        if (!in_array($cartera, $carteras)) {
            continue;
        }

        avisarSiActivoNoContieneHoy($cartera, $row, (int)$row['fecha'], (int)($row['fechaFin'] ?? 0));

        $periodos[] = $row;
    }

    return $periodos;
}

function avisarSiActivoNoContieneHoy(string $cartera, array $rowActivo, int $fecha, int $fechaFin): void
{
    $ahora = time();

    if ($ahora >= $fecha && $ahora <= $fechaFin) {
        return;
    }

    // Solo informativo: el periodo se procesa igual
    echo "[AVISO] Cartera {$cartera}: el periodo activo:1 (periodo "
        . (int)($rowActivo['periodo'] ?? 0)
        . ", desde " . date('Y-m-d', $fecha)
        . " hasta " . date('Y-m-d', $fechaFin)
        . ") no contiene la fecha de hoy (" . date('Y-m-d', $ahora) . "). "
        . "Se procesa igual.<br>\n";
} 


echo "EJECUCION_COMPLETA";


?><?

    //_FIN_DE_ARCHIVO
    ?>
