<?php

use function PHPSTORM_META\map;

require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
ini_set("max_execution_time", "1800000");
ini_set("memory_limit", "8096M");

if (!isset($_REQUEST["act"])) {
    exit;
}
global $Central;
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

$cnf = getConf("Canales Masivos");
$estadosConfig = $cnf["Dashboard equivalencias estados"];
$sentimientosConfig = $cnf["Dashboard equivalencias sentimientos"];
$desconexionConfig = $cnf["Dashboard equivalencias desconexion"];
$paletaColor = $cnf["Dashboard paleta colores"];
$traduceEstados = [];
foreach ($estadosConfig as $estado) {
    $partes = explode(":::", $estado);
    $traduceEstados[$partes[0]] = $partes[1];
}
$traducSentimiento = [];
foreach ($sentimientosConfig as $sentimiento) {
    $partes = explode(":::", $sentimiento);
    $traducSentimiento[$partes[0]] = $partes[1];
}
$traduceDesconexion = [];
foreach ($desconexionConfig as $estado) {
    $partes = explode(":::", $estado);
    $traduceDesconexion[$partes[0]] = $partes[1];
}

$separador = ";";
$saltoLinea = PHP_EOL;

$permisos = [
    "soyDesarrollo" => $Central->conPermiso("Canales Masivos,Desarrollo"),
    "soySupervisor" => $Central->conPermiso("Canales Masivos,Supervisor") || $Central->conPermiso("Canales Masivos,Supervisor Cobranzas"),
    "soyObservador" => $Central->conPermiso("Canales Masivos,Observador"),
    "soyGerente" => $Central->conPermiso("Canales Masivos,Gerente"),
];

$idsCarterasGeneral = obtenerCarterasPermitidas($permisos); //siempre se filtra por estas carteras, solo para los usuarios que no son desarrollo

switch ($act) {
    case "cargarTablaDias":
        #region obtenerdias carga 
        $d = jsonStart();
        $filtroTiempo = $d["filtroTiempo"];
        switch ($filtroTiempo) {
            case 'hora':
                // $desde = strtotime("-1 hour");
                // $hasta = time();
                $desde = strtotime(date("Y-m-d H") . ":00:00");
                $hasta = strtotime(date("Y-m-d H") . ":59:00");
                $campo = "av_fechaGeneraLlamada";
                break;
            case 'dia':
                $desde = strtotime(date("Y-m-d") . " 00:00:00");
                $hasta = strtotime(date("Y-m-d") . " 23:59:59");
                break;
            case 'ayer':
                $desde = strtotime(date("Y-m-d", strtotime("-1 days")) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime("-1 days")) . " 23:59:59");
                break;
            case 'semana':
                $desde = strtotime(date("Y-m-d", strtotime('monday this week')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('sunday this week')) . " 23:59:59");
                break;
            case 'semanaanterior':
                $desde = strtotime(date("Y-m-d", strtotime('monday previous week')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('sunday previous week')) . " 23:59:59");
                break;
            case 'mes':
                $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                break;
            case 'mesanterior':
                $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                break;
            case 'asigActual':
                $desde = strtotime(date("Y-m-d", strtotime('first day of this month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of this month')) . " 23:59:59");
                break;
            case 'asigAnterior':
                $desde = strtotime(date("Y-m-d", strtotime('first day of previous month')) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", strtotime('last day of previous month')) . " 23:59:59");
                break;
            case 'rango':
                $filtroDesde = expect_integer($d["filtroDesde"]);
                $filtroHasta = expect_integer($d["filtroHasta"]);
                $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                break;
        }
        $mongo = new MYMONGODB();
        $mongo->buscar('cbCargaDetallePacifico', ['cargaUT' => ['$gte' => $desde, '$lte' => $hasta]]);
        $filas = [];
        while ($row = $mongo->siguiente()) {
            $dia = (int) date('d', $row['cargaUT']);
            if (isset($filas[$dia])) {
                $filas[$dia]['total'] = $filas[$dia]['total'] + 1;
            } else {
                $filas[$dia] = ['dia' => (int) $dia, 'premora' => 0, 'nuevoPremora' => 0, 'vencida' => 0, 'nuevoVencida' => 0, 'total' => (int) 1];
            }
            if (strpos($row['carteraNombre'], 'PREMORA') !== false) {
                $filas[$dia]['premora'] = $filas[$dia]['premora'] + 1;
                if ($row['inicial'] == 1) {
                    $filas[$dia]['nuevoPremora'] = $filas[$dia]['nuevoPremora'] + 1;
                }
            } else {
                $filas[$dia]['vencida'] = $filas[$dia]['vencida'] + 1;
                if ($row['inicial'] == 1) {
                    $filas[$dia]['nuevoVencida'] = $filas[$dia]['nuevoVencida'] + 1;
                }
            }
        }
        $f = [];
        foreach ($filas as $val) {
            $f[] = $val;
        }
        $json = $f;
        break;
    case "obtenerParametrizacion":
        #region obtenerParametrizacion 
        $d = jsonStart();
        //filtros
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $error = 0;
        $mensaje = "";

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    break;
            }
        }


        $mongo = new MYMONGODB();

        //Carteras
        $carteras = [];
        $carteras = [
            [
                "id" => "todo",
                "nombre" => "Todas las carteras"
            ]
        ];
        $mysql = new MYSQLDB();
        $tabla = "cobcartera";
        $carterasConfiguradas = [];
        $sql = $mysql->mkSQL("SELECT cobCartera_id,cobCartera_nombre FROM " . $tabla . " WHERE cobCartera_estado=%N AND cobCartera_tipo='VENTAS'", 1);
        $mysql->query($sql);
        while ($row = $mysql->fetchRow()) {
            $carteras[] = [
                "id" => $row["cobCartera_id"],
                "nombre" => $row["cobCartera_nombre"]
            ];
        }

        //Productos
        $productos = [];
        $productos = [
            [
                "id" => "todo",
                "nombre" => "Todos los productos"
            ]
        ];

        $prod = $mongo->buscarDistinct("cubGV_producto", 'cuGestionVentas');
        $productosUnicos = [];
        foreach ($prod as $p) {
            $nombre = strtoupper(trim($p));

            if (!isset($productosUnicos[$nombre])) {
                $productosUnicos[$nombre] = [
                    "id" => $nombre,
                    "nombre" => $nombre
                ];
            }
        }

        $productos = array_merge($productos, array_values($productosUnicos));

        $json["carteras"] = $carteras;
        $json["productos"] = $productos;
        $json["permisos"] = $permisos;
        $json["error"] = $error;
        $json["mensaje"] = $mensaje;
        #endregion 
        break;


    case "obtenerTotales":


        #region obtenerTotales 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAV_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGV_carteraId");
        $desde = 0;
        $hasta = 0;

        $mongo = new MYMONGODB();


        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAV_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAV_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAV_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGV_carteraId"]['$in'])) {
            $condicionCubGestion["cubGV_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGV_carteraId"]['$in']);
        }


        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    break;
            }

            //Buscar en control_carga_periodo

            $coleccion = "control_carga_periodo";

            $condicionPeriodo = [
                "cartera" => intval($filtroCartera),
                "fecha" => ['$lte' => $desde],
                "fechaFin" => ['$gte' => strtotime(date("Y-m-d", $hasta) . " 00:00:00")]
            ];

            $mongo->buscar($coleccion, $condicionPeriodo);

            $periodo = $mongo->siguiente();

            //trigger_error(print_r($periodo, true));

            if ($periodo) {

                // PERIODO ACTIVO
                if (isset($periodo['activo']) && intval($periodo['activo']) == 1) {

                    $condicionCubAsignacion["cubAV_fechaPeriodo"] = [
                        '$gte' => $desde
                    ];

                    $condicionCubGestion["cubGV_fechaGestion"] = [
                        '$gte' => $desde,
                        '$lte' => $hasta
                    ];
                } else {

                    // PERIODO INACTIVO (CERRADO)
                    $condicionCubAsignacion["cubAV_fechaPeriodo"] = [
                        '$gte' => $desde,
                        '$lte' => $hasta
                    ];

                    $condicionCubGestion["cubGV_fechaGestion"] = [
                        '$gte' => $desde,
                        '$lte' => $hasta
                    ];
                }
            }



             //$condicionCubAsignacion["cubAV_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            //$condicionCubGestion["cubGV_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAV_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGV_carteraId"] = strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
            $condicionCubGestion["cubGV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }


        //trigger_error("entro a condicionCubAsignacion " . print_r($condicionCubAsignacion, true));
        //trigger_error("entro a condicionCubGestion " . print_r($condicionCubGestion, true));

        trigger_error("condicionCubAsignacion".print_r($condicionCubAsignacion,true));
        trigger_error("condicionCubGestion".print_r($condicionCubGestion,true));

        $totales = obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion);
        $json["totales"] = $totales;


        #endregion
        break;
    case "descargarGestiones":

        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAV_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGV_carteraId");
        $nombreCartera = "";

        // Convertir valores a string
        if (!empty($condicionCubAsignacion["cubAV_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAV_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAV_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGV_carteraId"]['$in'])) {
            $condicionCubGestion["cubGV_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGV_carteraId"]['$in']);
        }

        $desde = 0;
        $hasta = 0;

        if ($filtroTiempo != "" && $filtroTiempo != "todo") {

            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($d["filtroDesde"]);
                    $filtroHasta = expect_integer($d["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");
                    break;
            }

            $condicionCubAsignacion["cubAV_fechaPeriodo"] = ['$gt' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGV_fechaGestion"] = ['$gt' => $desde, '$lte' => $hasta];
        }

        if ($filtroCartera != "" && $filtroCartera != "todo") {

            $condicionCubAsignacion["cubAV_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGV_carteraId"] = strval($filtroCartera);

            // obtener nombre cartera
            $mysql = new MYSQLDB();
            $sql = $mysql->mkSQL(
                "SELECT cobCartera_nombre FROM cobcartera WHERE cobCartera_id=%N",
                $filtroCartera
            );

            $mysql->query($sql);

            if ($row = $mysql->fetchRow()) {
                $nombreCartera = $row["cobCartera_nombre"];
            }
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {

            $condicionCubAsignacion["cubAV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionCubGestion["cubGV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        $mdbAsigV = new MYMONGODB();

        $dataCubAasignacion = [
            'cubAV_numFactura',
            'cubAV_carteraNombre',
            'cubAV_gestionada',
            'cubAV_tipificacion1',
            'cubAV_tipificacion2',
            'cubAV_carteraId',
            'cubAV_producto',
            'cubAV_fechaCarga',
            'cubAV_fechaGestion'
        ];

        $mdbAsigV->buscar('cuAsignacionesGestionVentas', $condicionCubAsignacion, $dataCubAasignacion, ["cubAV_fechaCarga" => 1]);


        $mdbCuGV = new MYMONGODB();

        while ($f = $mdbAsigV->siguiente()) {
           /* $condCuGV = [
                "cubGV_numFactura" => $f["cubAV_numFactura"],
                "cubGV_carteraId" => $f["cubAV_carteraId"],
                "cubGV_tipificacion_respuesta1" => $f["cubAV_tipificacion1"],
                "cubGV_tipificacion_respuesta2" => $f["cubAV_tipificacion2"],
                "cubGV_producto" => $f["cubAV_producto"],
                "cubGV_fechaGestion" => $f["cubAV_producto"],
               //"cubGV_fechaGestion" => ['$gte' => $desde, '$lte' => $hasta]
            ];

            // Buscar la más reciente 
            $mdbCuGV->buscar('cuGestionVentas', $condCuGV, [], ['cubGV_fechaGestion' => -1], 1);
            $fechaGestion = null;
            if ($doc = $mdbCuGV->siguiente()) {
                $fechaGestion = $doc['cubGV_fechaGestion'] ?? null;
            }
            $f["fechaGestion"] = $fechaGestion;*/
            $resultado[] = $f;
        }

        // Nombre archivo
        $fechaHoy = date("Y_m_d");

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $nombreArchivo = "GESTIONES_VENTAS_" . $nombreCartera . "_" . $fechaHoy;
        } else {
            $nombreArchivo = "GESTIONES_VENTAS_" . $fechaHoy;
        }

        $ruta = generarExcel($resultado, $nombreArchivo);

        $json["ruta"] = $ruta;

        trigger_error("archivo ruta: " . $ruta);

        break;


    case "listaAsignacionGestiones":

        #region listaAsignacionGestiones 
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAV_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGV_carteraId");

        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAV_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAV_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAV_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGV_carteraId"]['$in'])) {
            $condicionCubGestion["cubGV_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGV_carteraId"]['$in']);
        }


        if ($filtroTiempo != "" && $filtroTiempo != "todo") {
            $desde = 0;
            $hasta = 0;
            switch ($filtroTiempo) {

                case 'rango':
                    $filtroDesde = expect_integer($_GET["filtroDesde"]);
                    $filtroHasta = expect_integer($_GET["filtroHasta"]);
                    $desde = strtotime(date("Y-m-d", $filtroDesde) . " 00:00:00");
                    $hasta = strtotime(date("Y-m-d", $filtroHasta) . " 23:59:59");

                    break;
            }

            $condicionCubAsignacion["cubAV_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGV_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAV_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGV_carteraId"] = strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
            $condicionCubGestion["cubGV_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }


        //<editor-fold defaultstate="collapsed" desc=" Lista las asignaciones para presentarlos en la tabula ">
        $coleccion = "cuAsignacionesGestionVentas";

        //$campos = ["cubAV_numFactura", "cubAV_carteraId", "cubAV_carteraNombre", "cubAV_gestionada", "cubAV_tipificacion1", "cubAV_tipificacion2", "cubAV_producto"];
        $campos = [];
        $orden = ["cubAV_carteraId" => 1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);

        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);

        $ngTabula->setPreparaDatos(function ($campos) {
            $campos["cubAV_gestionada"] = (isset($campos['cubAV_gestionada']) && $campos['cubAV_gestionada'] == 1) ? "SI" : "NO";
            return $campos;
        });
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        //</editor-fold>



        $filas = $json["filas"];

      /*  if (!empty($filas)) {

            $mdbCuGV = new MYMONGODB();

            foreach ($filas as &$f) {


                $condCuGV = [
                    "cubGV_numFactura" => $f["cubAV_numFactura"],
                    "cubGV_carteraId" => $f["cubAV_carteraId"],
                    "cubGV_tipificacion_respuesta1" => $f["cubAV_tipificacion1"],
                    "cubGV_tipificacion_respuesta2" => $f["cubAV_tipificacion2"],
                    "cubGV_producto" => $f["cubAV_producto"]
                ];


                // ?? Filtro fecha

                $condCuGV["cubGV_fechaGestion"] = [
                    '$gte' => $desde,
                    '$lte' => $hasta
                ];

                // ?? Buscar SOLO la más reciente
                $mdbCuGV->buscar('cuGestionVentas', $condCuGV, [], ['cubGV_fechaGestion' => -1], 1);

                $fechaGestion = null;

                while ($doc = $mdbCuGV->siguiente()) {
                    $fechaGestion = isset($doc['cubGV_fechaGestion']) ? $doc['cubGV_fechaGestion'] : null;
                    break;
                }

                $f["fechaGestion"] = $fechaGestion;
            }

            $json["filas"] = $filas;
        }*/


        break;
}






//TOTALES GESTION NUEVOS
function obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion)
{
    $resultado = [
        "gestion" => [
            [
                "nombre" => "Cartera Asignada",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],
            [
                "nombre" => "Cartera Gestionada",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],
            [
                "nombre" => "Cartera no gestionada",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],
            [
                "nombre" => "Cartera gestionada con contacto para total",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],
            [
                "nombre" => "Cartera gestionada interesados",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],
            [
                "nombre" => "Cartera con contacto por gestiones ",
                "total" => 0,
                "porcentaje" => "0",
                "monto" => "$0.00",
            ],


        ],
        "tipificacion" => [
            [
                "nombre" => "Contacto directo",
                "total" => 0,
                "porcentaje" => "0,0",
                "monto" => "$0.00 de $0.00",
            ],
            [
                "nombre" => "Contacto indirecto",
                "total" => 0,
                "porcentaje" => "0,0",
                "monto" => "$0.00 de $0.00",
            ],
            [
                "nombre" => "Sin contacto",
                "total" => 0,
                "porcentaje" => "0,0",
                "monto" => "$0.00 de $0.00",
            ]
        ],
        "intensidad" => [
            [
                "nombre" => "Intensidad",
                "total" => 0,
                "tooltip" => ""
            ],
        ],

        "horarios" => [
            [
                "label" => "",
                "total" => 0,
                "contactados" => 0,
                "porcentaje" => 0
            ],
        ],
    ];

    $total = 0;
    $totalGestionado = 0;
    $sinGestion = 0;
    $totalContactado = 0;
    $contactoDirecto        = 0;
    $contactoIndirecto = 0;
    $sinContactoGestionado = 0;
    $interesados = 0;

    $rangos = [
        "0-10" => ["total" => 0, "contactados" => 0],
        "10-12" => ["total" => 0, "contactados" => 0],
        "12-14" => ["total" => 0, "contactados" => 0],
        "14-16" => ["total" => 0, "contactados" => 0],
        "16-18" => ["total" => 0, "contactados" => 0],
        "18+" => ["total" => 0, "contactados" => 0]
    ];

    //Total asignado
    $mdbAsig = new MYMONGODB();


    if (!empty($condicionCubAsignacion["cubAV_fechaPeriodo"])) {

    $pipeline = [
        [
            '$match' => $condicionCubAsignacion
        ],
        [
            '$group' => [
                '_id' => null,
                // Total registros
                'totalRegistros' => ['$sum' => 1],
                // Total gestionados
                'totalGestionados' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAV_gestionada', 1]],
                            1,
                            0
                        ]
                    ]
                ],
                // Total sin gestión
                'totalSinGestion' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAV_gestionada', 0]],
                            1,
                            0
                        ]
                    ]
                ],
                // Contacto Directo
                'totalContactoDirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAV_gestionada', 1]],
                                    ['$eq' => ['$cubAV_tipificacion1', 'CONTACTO DIRECTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Contacto Indirecto
                'totalContactoIndirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAV_gestionada', 1]],
                                    ['$eq' => ['$cubAV_tipificacion1', 'CONTACTO INDIRECTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],

                // Sin Contacto
                'totalSinContacto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAV_gestionada', 1]],
                                    ['$eq' => ['$cubAV_tipificacion1', 'SIN CONTACTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Interesados
                'totalInteresados' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAV_gestionada', 1]],
                                    ['$eq' => ['$cubAV_tipificacion1', 'CONTACTO DIRECTO']],
                                    ['$in' => ['$cubAV_tipificacion2', ['ACEPTA', 'ACEPTA OFERTA']]]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],

            ]
        ]
    ];

    $mdbAsig->aggregate("cuAsignacionesGestionVentas", $pipeline);
    if ($row = $mdbAsig->siguiente()) {
        $total              = (int)$row['totalRegistros'];
        $totalGestionado    = (int)$row['totalGestionados'];
        $sinGestion  = (int) $row['totalSinGestion'];

        $contactoDirecto        = (int)$row['totalContactoDirecto'];
        $contactoIndirecto      = (int)$row['totalContactoIndirecto'];
        $sinContactoGestionado  = (int)$row['totalSinContacto'];
        $interesados            = (int)$row['totalInteresados'];
    }
    $totalContactado = $contactoDirecto + $contactoIndirecto;

    trigger_error("total". $total);

    //OJO: PARA CLIENTES EFECTIVOS: no tiene retorno de parte del banco para saber cuales son efectivos

    }
    if ($total > 0 && !empty($condicionCubGestion["cubGV_fechaGestion"])) {

        //pipeline intensidades
        $mdbIntensidad = new MYMONGODB();
        $pipelineIntensidad = [
            [
                '$match' => $condicionCubGestion
            ],
            [
                '$group' => [
                    '_id' => null,

                    // Total gestiones
                    'totalGestiones' => [
                        '$sum' => 1
                    ],

                    // Telefónica
                    'totalTelefonica' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGV_canal', 'TELEFONICA']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // WhatsApp
                    'totalWhatsapp' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGV_canal', 'WHATSAPP']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // Email
                    'totalEmail' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGV_canal', 'EMAIL']],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $mdbIntensidad->aggregate("cuGestionVentas", $pipelineIntensidad);

        if ($row = $mdbIntensidad->siguiente()) {

            $totalGestionesInt  = (int)$row['totalGestiones'];
            $totalTelefonica = (int)$row['totalTelefonica'];
            $totalWhatsapp   = (int)$row['totalWhatsapp'];
            $totalEmail      = (int)$row['totalEmail'];
        }

        $resultado["intensidad"][0]["total"] = formatea_numero(round(isset($totalGestionesInt) && $total > 0 ? ($totalGestionesInt / $total) : 0, 2), 2, ",", ".");
        $intLLamadas =  $total > 0 ? formatea_numero(round(isset($totalTelefonica) && $total > 0 ? ($totalTelefonica / $total) : 0, 2), 2, ",", ".") : 0;
        $intCorreo = $total > 0 ? formatea_numero(round(isset($totalEmail) && $total > 0 ? ($totalEmail / $total) : 0, 2), 2, ",", ".") : 0;
        $intWs = $total > 0 ? formatea_numero(round(isset($totalWhatsapp) && $total > 0 ? ($totalWhatsapp / $total) : 0, 2), 2, ",", ".") : 0;
        $resultado["intensidad"][0]["tooltip"] = [
            $intLLamadas, //. " Intensidad telefonía agente virtual",
            $intCorreo, //. " Intensidad correo electrónico",
            $intWs //. " Intensidad WhatsApp"
        ];


        //if ($totalGestionado > 0) {
        //Porcentajes para chartPie, se dividen  para el total
        //cartera asignada
        $resultado["gestion"][0]["total"] = formatea_numero($total, 0, ",", ".");
        //cartera gestionada
        $resultado["gestion"][1]["total"] = formatea_numero($totalGestionado, 0, ",", ".");
        $resultado["gestion"][1]["porcentaje"] = $total > 0 ?  round(($totalGestionado * 100) / $total, 2) : 0;
        //cartera no gestionada
        $resultado["gestion"][2]["total"] = formatea_numero($sinGestion, 0, ",", ".");
        $resultado["gestion"][2]["porcentaje"] = $total > 0 ? round(($sinGestion * 100) / $total, 2) : 0;
        //total contactado
        $resultado["gestion"][3]["total"] = formatea_numero($totalContactado, 0, ",", ".");
        $resultado["gestion"][3]["porcentaje"] = $total > 0 ? formatea_numero(($totalContactado * 100) / $total, 2, ",", ".") : 0;


        //contacto directo
        //Porcentajes para chartPie, se dividen  para el total
        $resultado["tipificacion"][0]["total"] = formatea_numero($contactoDirecto, 0, ",", ".");
        $resultado["tipificacion"][0]["porcentaje"] = $total > 0 ? round(($contactoDirecto * 100) / $total, 2) : 0;
        //contacto indirecto
        $resultado["tipificacion"][1]["total"] = formatea_numero($contactoIndirecto, 0, ",", ".");
        $resultado["tipificacion"][1]["porcentaje"] = $total > 0 ? round(($contactoIndirecto * 100) / $total, 2) : 0;
        //sin contacto
        $resultado["tipificacion"][2]["total"] = formatea_numero($sinContactoGestionado, 0, ",", ".");
        $resultado["tipificacion"][2]["porcentaje"] =  $total > 0 ? round(($sinContactoGestionado * 100) / $total, 2) : 0;

        //Porcentajes para  chartFunnel, se divide para gestionados y contactados
        //interesados
        $resultado["gestion"][4]["total"] = formatea_numero($interesados, 0, ",", ".");
        $resultado["gestion"][4]["porcentaje"] =  $totalContactado > 0 ? formatea_numero(($interesados * 100) / $totalContactado, 2) : 0;

        //total contactado
        $resultado["gestion"][5]["total"] = formatea_numero($totalContactado, 0, ",", ".");
        $resultado["gestion"][5]["porcentaje"] = $totalGestionado > 0 ? formatea_numero(($totalContactado * 100) / $totalGestionado, 2, ",", ".") : 0;
   // }


        //Consulta para mostrar horarios

    $mdbGestion = new MYMONGODB();

    $pipelineGestion = [
        [
            '$match' => $condicionCubGestion
        ],
        [
            '$addFields' => [
                'hora' => [
                    '$hour' => [
                        'date' => [
                            '$toDate' => [
                                '$multiply' => ['$cubGV_fechaGestion', 1000]
                            ]
                        ],
                        'timezone' => '-05:00'
                    ]
                ]
            ]
        ],
        [
            '$addFields' => [
                'rangoHora' => [
                    '$switch' => [
                        'branches' => [
                            [
                                'case' => ['$lt' => ['$hora', 10]],
                                'then' => '0-10'
                            ],
                            [
                                'case' => [
                                    '$and' => [
                                        ['$gte' => ['$hora', 10]],
                                        ['$lt' => ['$hora', 12]]
                                    ]
                                ],
                                'then' => '10-12'
                            ],
                            [
                                'case' => [
                                    '$and' => [
                                        ['$gte' => ['$hora', 12]],
                                        ['$lt' => ['$hora', 14]]
                                    ]
                                ],
                                'then' => '12-14'
                            ],
                            [
                                'case' => [
                                    '$and' => [
                                        ['$gte' => ['$hora', 14]],
                                        ['$lt' => ['$hora', 16]]
                                    ]
                                ],
                                'then' => '14-16'
                            ],
                            [
                                'case' => [
                                    '$and' => [
                                        ['$gte' => ['$hora', 16]],
                                        ['$lt' => ['$hora', 18]]
                                    ]
                                ],
                                'then' => '16-18'
                            ]
                        ],
                        'default' => '18+'
                    ]
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => '$rangoHora',
                'total' => ['$sum' => 1],

                // total contactados (directo + indirecto)
                'contactados' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$in' => [
                                    '$cubGV_tipificacion_respuesta1',
                                    ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ]
            ]
        ],
        [
            '$sort' => ['_id' => 1]
        ]
    ];




    $mdbGestion->aggregate("cuGestionVentas", $pipelineGestion);

    while ($row = $mdbGestion->siguiente()) {
        $rango = $row['_id'];
        $rangos[$rango]["total"] = (int)$row['total'];
        $rangos[$rango]["contactados"] = (int)$row['contactados'];
    }

    $resultado["horarios"] = [];

    foreach ($rangos as $label => $valores) {

        $total = $valores["total"];
        $contactados = $valores["contactados"];

            $porcentaje = $total > 0 ? round(($contactados * 100) / $total, 2) : 0;

        $resultado["horarios"][] = [
            "label" => $label,
            "total" => $total,
            "contactados" => $contactados,
            "porcentaje" => $porcentaje
        ];
    }
    }

    return $resultado;
}

function generarExcel($data, $archivoExcelNombre)
{

    require_once("../comunes/classes/class.coGeneraExcel.php");

    $anio = date('Y');
    $mes = date('m');


    // RUTA RELATIVA (para la clase)
    $rutaRelativaExcel = "/canalesMasivos/reportesExcel/ventas/$anio/$mes/";

    //RUTA FÍSICA (DONDE SE GUARDA)
    $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . "/canalesMasivos/reportesExcel/ventas/$anio/$mes";

    if (!is_dir($rutaFisica)) {
        mkdir($rutaFisica, 0777, true);
    }

    $objExcel = new coGeneraExcel();
    $objExcel->setNombreArchivo($archivoExcelNombre);
    $objExcel->setUbicacion($rutaRelativaExcel);

    $nombreHoja = "Resumen";
    $objExcel->addHoja(0, $nombreHoja);
    $objExcel->setAnchoColumna(30);

    // CABECERAS

    $cabeceras = array(
        "Operación",
        "Cartera",
        "Gestionado",
        "Contactabilidad",
        "Gestión Realizada",
        "Fecha y Hora Gestión"
    );


    $filasExcel = array();
    $i = 0;

    $filasExcel["titulo_" . $i] = $cabeceras;
    $i++;

    // FILAS
    foreach ($data as $fila) {


        // GESTIONES
        $nuevaFila = [
            $fila['cubAV_numFactura'] ?? '',
            $fila['cubAV_carteraNombre'] ?? '',
            (isset($fila['cubAV_gestionada']) && $fila['cubAV_gestionada'] == 1) ? "SI" : "NO",
            $fila['cubAV_tipificacion1'] ?? '',
            $fila['cubAV_tipificacion2'] ?? '',
            (isset($fila['cubAV_fechaGestion']) && $fila['cubAV_fechaGestion'] > 0) 
            ? date('Y-m-d H:i:s', $fila['cubAV_fechaGestion']) : ''

        ];


        $filasExcel["fila_" . $i] = $nuevaFila;
        $i++;
    }

    //ESTILOS
    foreach ($filasExcel as $key => $fila) {

        $partes = explode("_", $key);
        $style = $partes[0];

        switch ($style) {

            case "titulo":
                $font = $objExcel->setFilaEstiloFuente("Calibri", "14", true, "#ffffff");
                $background = $objExcel->setFilaEstiloFondo("solid", "1e2040");
                $borders = array();
                $alinear = $objExcel->setFilaEstiloAlineacion('center', 'center');
                $merge = "";
                break;

            default:
                $font = $objExcel->setFilaEstiloFuente("Calibri", "11", false, "#000000");
                $background = $borders = $alinear = array();
                $alinear = $objExcel->setFilaEstiloAlineacion('left', 'center');
                $merge = "";
                break;
        }

        $objExcel->addFila($nombreHoja, $fila, $font, $background, $borders, $alinear, $merge);
    }

    // GENERAR ARCHIVO
    $archivo = $objExcel->genera();

    // RUTA PARA USAR EN FRONT / DESCARGA
    $rutaRelativa = "/canalesMasivos/reportesExcel/ventas/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();

    return $rutaRelativa;
}







//abrevia y muestra numeros como: 1K, 10M, ect
function abreviaNumero($n, $precision)
{
    $negativo = 0;
    if ($n < 0) {
        $negativo = 1;
        $n = $n * -1;
    }
    if ($n < 900) {
        // 0 - 900
        $n_format = number_format($n, $precision);
        $suffix = '';
    } else if ($n < 900000) {
        // 0.9k-850k
        $n_format = number_format($n / 1000, $precision);
        $suffix = 'K';
    } else if ($n < 900000000) {
        // 0.9m-850m
        $n_format = number_format($n / 1000000, $precision);
        $suffix = 'M';
    } else if ($n < 900000000000) {
        // 0.9b-850b
        $n_format = number_format($n / 1000000000, $precision);
        $suffix = 'B';
    } else {
        // 0.9t+
        $n_format = number_format($n / 1000000000000, $precision);
        $suffix = 'T';
    }
    // Remover ceros innecesarios despues del decimal. "1.0" -> "1"; "1.00" -> "1"
    // pero intencionalmente no afecta parciales, eg "1.50" -> "1.50"
    if ($precision > 0) {
        $dotzero = '.' . str_repeat('0', $precision);
        $n_format = str_replace($dotzero, '', $n_format);
    }
    if ($negativo == 1) {
        $n_format = '-' . $n_format;
    }
    return $n_format . $suffix;
}

#endregion

jsonEnd($json, $limpiar);

//devuelve un listado de las carteras que puede ver el usuario
function obtenerCarterasPermitidas($permisos)
{
    $idsCarterasGeneral = null;
    if ($permisos["soySupervisor"] || $permisos["soyObservador"] || $permisos["soyGerente"]) {
        //saco el listado de carteras activas
        $mysql = new MYSQLDB();
        $tabla = "cobcartera";
        $carterasConfiguradas = [];
        $sql = $mysql->mkSQL("SELECT * FROM " . $tabla . " WHERE cobCartera_estado=%N", 1);
        if ($mysql->query($sql) > 0) {
            while ($row = $mysql->fetchRow()) {
                $item = [];
                foreach ($row as $key => $value) {
                    $k = str_replace("cobCartera_", "", $key);
                    $item[$k] = $value;
                }
                $carterasConfiguradas[] = $item;
            }
        }
        require_once("../permisos/classes/class.permisoUniversal.php");
        $permisoUniversal = new permisoUniversal();
        $quePuedoVer = $permisoUniversal->validaRegistrosUsuarioSesion($carterasConfiguradas, $tabla);
        //$quePuedoVer = $permisoUniversal->validaRegistrosPorUsuarioId($carterasConfiguradas, $tabla, 910797409);
        foreach ($quePuedoVer as $value) {
            $idsCarterasGeneral[] = intval($value["id"]);
        }
        if ($idsCarterasGeneral == null || count($idsCarterasGeneral) == 0) { //siempre debe tener permisos universales
            trigger_error("No tiene permisos universales configurados para usuario " . $_SESSION[MID . "userId"]);
            echo "No tiene permisos universales configurados";
            die;
        }
    } else if ($permisos["soyDesarrollo"]) {
        $idsCarterasGeneral = [];
    } else {
        trigger_error("No tiene permisos configurados para usuario " . $_SESSION[MID . "userId"]);
        echo "No tiene permisos configurados";
        die;
    }

    // if ($_SESSION[MID . "userId"] == 910753601) { //TODO: solo para pruebas
    //     $idsCarterasGeneral = [2121]; //TODO: solo para pruebas
    // } else {
    //     $idsCarterasGeneral = [];
    // }
    return $idsCarterasGeneral;
}

//establece una condicion principal de carteras
function establecerCondicionMaster($tabla = "av_carteraId")
{
    global $idsCarterasGeneral;
    if ($idsCarterasGeneral != null && count($idsCarterasGeneral) > 0) {
        return [
            $tabla => count($idsCarterasGeneral) == 1 ? $idsCarterasGeneral[0] : ['$in' => $idsCarterasGeneral]
        ];
    }
    return [];
}

?>
<? //_FIN_DE_ARCHIVO                                                                                                                                  
?>