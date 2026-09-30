<?php

require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
ini_set("max_execution_time", "1800000");
ini_set("memory_limit", "8096M");

// Periodo de asignacion (ver obtenerPeriodoAsignacionDesde)
const COLECCION_PERIODOS = "control_carga_periodo";
const FILTROS_PERIODO_ASIGNACION = ["asigActual", "asigAnterior"];
const CICLO_TODOS = -1;

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
    case "obtenerParametrizacion":
        #region obtenerParametrizacion
        $d = jsonStart();
        //filtros
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $error = 0;
        $mensaje = "";

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
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
        $sql = $mysql->mkSQL("SELECT cobCartera_id,cobCartera_nombre FROM " . $tabla . " WHERE cobCartera_estado=%N AND cobCartera_tipo='COBRANZA'", 1);
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

        $fechasCarga = [
            [
                "id" => "todo",
                "nombre" => "Fechas de Carga"
            ]
        ];

        // Los <select> de productos y fechas de carga solo se llenan cuando
        // ya hay una cartera especifica seleccionada. Sin cartera, listar
        // TODOS los productos/fechas del sistema no tiene utilidad para el
        // usuario y es una consulta innecesariamente pesada, asi que se
        // dejan vacios (solo la opcion "todo") hasta que elija una cartera.
        if ($filtroCartera != "" && $filtroCartera != "todo") {

            $condicionProductos = ["cubGC_carteraId" => strval($filtroCartera)];

            $mdbProductos = new MYMONGODB();
            $pipelineProductos = [];
            $pipelineProductos[] = ['$match' => $condicionProductos];
            $pipelineProductos[] = ['$group' => ['_id' => '$cubGC_producto']];
            $mdbProductos->aggregate('cuGestionCobranzaMysql', $pipelineProductos);

            $productosUnicos = [];
            while ($row = $mdbProductos->siguiente()) {
                if (!isset($row['_id']) || $row['_id'] === null) {
                    continue;
                }
                $nombre = strtoupper(trim($row['_id']));
                if ($nombre === '') {
                    continue;
                }
                if (!isset($productosUnicos[$nombre])) {
                    $productosUnicos[$nombre] = [
                        "id" => $nombre,
                        "nombre" => $nombre
                    ];
                }
            }

            $productos = array_merge($productos, array_values($productosUnicos));

            // Fechas de Carga (cubAG_fechaCarga): igual que en el dashboard de
            // Cartera por Mora, se acotan por cartera + período: el elegido o,
            // si no se eligió ninguno, el período activo de la cartera. Este
            // dato es nativo de cuAsignacionesGestionAP, no de cuGestionCobranzaMysql.
            $periodoFechasCarga = esFiltroPeriodoAsignacion($filtroTiempo)
                ? $periodoAsignacion
                : obtenerPeriodoAsignacionDesde(array_merge($d, ["filtroTiempo" => "asigActual"]));
            $condicionFechaCargaDistinct = ["cubAG_carteraId" => strval($filtroCartera)];
            if ($periodoFechasCarga["desde"] > 0) {
                $condicionFechaCargaDistinct["cubAG_fechaPeriodo"] = ['$gte' => $periodoFechasCarga["desde"], '$lte' => $periodoFechasCarga["hasta"]];
            }
            aplicaCondicionCiclo($condicionFechaCargaDistinct, "cubAG_ciclo", $periodoAsignacion);

            $mdbFechaCarga = new MYMONGODB();
            $fechaCargaDistinct = $mdbFechaCarga->buscarDistinct("cubAG_fechaCarga", 'cuAsignacionesGestionAP', $condicionFechaCargaDistinct);

            $fechasCargaUnicos = [];
            foreach ($fechaCargaDistinct as $p) {
                if (intval($p) > 0) {
                    $timestamp = intval($p);
                    $nombre = date('d/m/Y', $timestamp);
                    if (!isset($fechasCargaUnicos[$nombre])) {
                        // "id" como string: si vuelve del querystring y aqui
                        // fuera numero, Angular no calza el <option> y se ve en blanco.
                        $fechasCargaUnicos[$nombre] = [
                            "id" => strval($timestamp),
                            "nombre" => $nombre
                        ];
                    }
                }
            }

            $fechasCarga = array_merge($fechasCarga, array_values($fechasCargaUnicos));
        }

        $json["carteras"] = $carteras;
        $json["productos"] = $productos;
        $json["fechasCarga"] = $fechasCarga;
        $json["periodos"] = obtenerCiclosPeriodoAsignacion($filtroCartera, $filtroTiempo);
        $json["periodoAsignacion"] = obtenerPeriodoAsignacionDesde($d);
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
        $filtroCanal = expect_safe_html($d["filtroCanal"] ?? "todo");
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"] ?? "todo");
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $desde = 0;
        $hasta = 0;

        // El filtro de canal viaja del front con las MISMAS llaves que guarda
        // cubAG_canal: AV, EMAIL, WHATSAPP (canal de la mejor gestión general
        // del cliente) -> no requiere mapeo para cuAsignacionesGestionAP.
        // cuGestionCobranzaMysql en cambio guarda cada gestión individual con
        // cubGC_canal = TELEFONICA/EMAIL/WHATSAPP, así que ahí sí traducimos.
        $mapaCanalGestion = [
            'AV' => 'TELEFONICA',
            'EMAIL' => 'EMAIL',
            'WHATSAPP' => 'WHATSAPP',
        ];

        $canalAsignacion = ($filtroCanal !== "" && $filtroCanal !== "todo" && isset($mapaCanalGestion[$filtroCanal]))
            ? $filtroCanal
            : "";

        $mongo = new MYMONGODB();


        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }


        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {

            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];

            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
            $condicionCubGestion["cubGC_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        // Filtro de canal: se aplica directo (por $match) sobre cuGestionCobranzaMysql,
        // que ya es un cubo por gestión/canal individual -> afecta Horarios e
        // Intensidad. NO se aplica sobre $condicionCubAsignacion, porque ese
        // cubo alimenta el KPI de Gestión de Base (que debe seguir mostrando
        // la base asignada completa); el canal para ese cubo se pasa aparte
        // a obtenerTotalesGestion2() para que solo afecte Contactabilidad y
        // Efectividad, no la Base.
        if ($canalAsignacion !== "" && isset($mapaCanalGestion[$filtroCanal])) {
            $condicionCubGestion["cubGC_canal"] = $mapaCanalGestion[$filtroCanal];
        }

        // Filtro de Fecha de Carga: cubAG_fechaCarga es nativo de
        // cuAsignacionesGestionAP, así que aquí SÍ se aplica directo
        // (a diferencia del canal, esto incluye al KPI de Gestión de Base).
        // cuGestionCobranzaMysql no tiene ese campo, así que para que Horarios e
        // Intensidad respeten el mismo filtro, primero buscamos qué
        // facturas se cargaron esa fecha (ya con cartera/producto/período
        // aplicados) y filtramos las gestiones por esas facturas.
        if (aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga")) {
            $mdbFacturasCarga = new MYMONGODB();
            $mdbFacturasCarga->buscar('cuAsignacionesGestionAP', $condicionCubAsignacion, ['cubAG_numFactura']);
            $facturasFechaCarga = [];
            while ($rowFactura = $mdbFacturasCarga->siguiente()) {
                if (!empty($rowFactura['cubAG_numFactura'])) {
                    $facturasFechaCarga[] = $rowFactura['cubAG_numFactura'];
                }
            }
            $condicionCubGestion["cubGC_numFactura"] = ['$in' => array_values(array_unique($facturasFechaCarga))];
        }

        $condicionCubGestion = excluirWhatsappInvalido($condicionCubGestion);

        //trigger_error("entro a condicionCubAsignacion " . print_r($condicionCubAsignacion, true));
        //trigger_error("entro a condicionCubGestion " . print_r($condicionCubGestion, true));

        $totales = obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $canalAsignacion);
        $json["totales"] = $totales;


        #endregion
        break;

    case "obtenerTotalesGestionBase":

        #region obtenerTotalesGestionBase
        // KPI propio de Detalle > Gestión de Base (Base Asignada/Gestionados/
        // Sin Gestión). Con canal elegido usa la MEJOR GESTIÓN DE ESE CANAL
        // (cubAG_mejorGestion.<canal>); sin canal, la general (como antes).
        $canalAsignacion = obtenerCanalAsignacionDesde($_GET["filtroCanal"] ?? "todo");
        $condicionCubAsignacion = construirCondicionAsignacionGestionBase();

        $totalBase = 0;
        $totalGestionados = 0;
        $totalSinGestion = 0;

        // Campo de "gestionado" segun canal: mejor gestion DE ESE CANAL,
        // o la general (cubAG_gestionada) si no hay canal elegido.
        // $ifNull cubre al cliente que nunca fue gestionado por ese canal
        // (ni siquiera tiene el sub-objeto), para que no cuente como "".
        if ($canalAsignacion !== "") {
            $campoCuGestionId = ['$ifNull' => ['$cubAG_mejorGestion.' . $canalAsignacion . '.cuGestionId', '']];
            $condGestionado = ['$ne' => [$campoCuGestionId, '']];
        } else {
            $condGestionado = ['$eq' => ['$cubAG_gestionada', 1]];
        }

        $pipeline = [
            ['$match' => $condicionCubAsignacion],
            ['$group' => [
                '_id' => null,
                'totalBase' => ['$sum' => 1],
                'totalGestionados' => ['$sum' => ['$cond' => [$condGestionado, 1, 0]]],
            ]],
        ];

        $mdb = new MYMONGODB();
        $mdb->aggregate("cuAsignacionesGestionAP", $pipeline);
        if ($row = $mdb->siguiente()) {
            $totalBase = (int) $row['totalBase'];
            $totalGestionados = (int) $row['totalGestionados'];
            $totalSinGestion = $totalBase - $totalGestionados;
        }

        $json["totales"] = [
            "gestion" => [
                ["nombre" => "Base Asignada", "total" => formatea_numero($totalBase, 0, ",", ".")],
                ["nombre" => "Clientes Gestionados", "total" => formatea_numero($totalGestionados, 0, ",", ".")],
                ["nombre" => "Sin Gestión", "total" => formatea_numero($totalSinGestion, 0, ",", ".")],
            ]
        ];
        #endregion
        break;

    case "descargarGestiones":

        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"]);
        $filtroProducto = expect_safe_html($d["filtroProducto"]);
        $filtroTiempo = expect_safe_html($d["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $nombreCartera = "";

        // Convertir valores a string
        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }

        $desde = 0;
        $hasta = 0;

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {

            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];

            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {

            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);

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

            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];

            $condicionCubGestion["cubGC_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        $mdbAsigV = new MYMONGODB();

        $dataCubAasignacion = [
            'cubAG_numFactura',
            'cubAG_carteraNombre',
            'cubAG_gestionada',
            'cubAG_tipificacion1',
            'cubAG_tipificacion2',
            'cubAG_carteraId',
            'cubAG_producto',
            'cubAG_fechaCarga',
            'cubAG_fechaGestion'
        ];

        $mdbAsigV->buscar('cuAsignacionesGestionAP', $condicionCubAsignacion, $dataCubAasignacion, ["cubAG_fechaCarga" => 1]);


        $mdbCuGV = new MYMONGODB();

        while ($f = $mdbAsigV->siguiente()) {
            /* $condCuGV = [
                "cubGC_numFactura" => $f["cubAG_numFactura"],
                "cubGC_carteraId" => $f["cubAG_carteraId"],
                "cubGC_tipificacion_respuesta1" => $f["cubAG_tipificacion1"],
                "cubGC_tipificacion_respuesta2" => $f["cubAG_tipificacion2"],
                "cubGC_producto" => $f["cubAG_producto"],
                "cubGC_fechaGestion" => $f["cubAG_producto"],
               //"cubGC_fechaGestion" => ['$gte' => $desde, '$lte' => $hasta]
            ];

            // Buscar la más reciente
            $mdbCuGV->buscar('cuGestionCobranzaMysql', $condCuGV, [], ['cubGC_fechaGestion' => -1], 1);
            $fechaGestion = null;
            if ($doc = $mdbCuGV->siguiente()) {
                $fechaGestion = $doc['cubGC_fechaGestion'] ?? null;
            }
            $f["fechaGestion"] = $fechaGestion;*/
            $resultado[] = $f;
        }

        // Nombre archivo
        $fechaHoy = date("Y_m_d");

        // El nombre de la cartera reemplaza a "COBRANZA"; sin cartera se deja "COBRANZA".
        if ($nombreCartera !== "") {
            $nombreArchivo = "GESTIONES_" . $nombreCartera . "_" . $fechaHoy;
        } else {
            $nombreArchivo = "GESTIONES_COBRANZA_" . $fechaHoy;
        }

        $ruta = generarExcel($resultado, $nombreArchivo);

        $json["ruta"] = $ruta;

        trigger_error("archivo ruta: " . $ruta);

        break;

    case "exportarDetalle":

        #region exportarDetalle
        // Excel de una tabula de detalle, con TODOS los registros que
        // cumplen los filtros vigentes (sin paginacion). Reutiliza las
        // mismas funciones de condicion que usa cada "listaXxx" para que
        // el Excel refleje exactamente lo que la tabla tiene filtrado.
        $tipo = expect_safe_html($_GET["tipo"] ?? "");
        $filtroCartera = expect_safe_html($_GET["filtroCartera"] ?? "");
        $canalAsignacion = obtenerCanalAsignacionDesde($_GET["filtroCanal"] ?? "todo");
        $nombreCartera = "";

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $mysql = new MYSQLDB();
            $sql = $mysql->mkSQL("SELECT cobCartera_nombre FROM cobcartera WHERE cobCartera_id=%N", $filtroCartera);
            $mysql->query($sql);
            if ($row = $mysql->fetchRow()) {
                $nombreCartera = $row["cobCartera_nombre"];
            }
        }

        $coleccion = "";
        $condicion = [];
        $orden = [];
        $normaliza = null;
        $forma = "";

        switch ($tipo) {

            case "listaContactoDirecto":
                $condicion = construirCondicionContactabilidadGestion('CONTACTO DIRECTO');
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaContactoIndirecto":
                $condicion = construirCondicionContactabilidadGestion('CONTACTO INDIRECTO');
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaSinContacto":
                $condicion = construirCondicionContactabilidadGestion('SIN CONTACTO');
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaNoContactado":
                $condicion = construirCondicionNoContactado();
                $coleccion = "cuAsignacionesGestionAP";
                $orden = ["cubAG_fechaCarga" => -1];
                $normaliza = 'normalizaFilaNoContactado';
                $forma = "noContactado";
                break;

            case "listaGestionados":
                $condicion = construirCondicionBaseGestion();
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaContactados":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_tipificacion_respuesta1"] = ['$in' => ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']];
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaInteresados":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_tipificacion_respuesta1"] = 'CONTACTO DIRECTO';
                $condicion["cubGC_tipificacion_respuesta2"] = ['$in' => tipificacionesCompromisoPago()];
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaEfectivos":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_tipificacion_respuesta1"] = 'CONTACTO DIRECTO';
                $condicion["cubGC_tipificacion_respuesta2"] = ['$in' => tipificacionesCompromisoPago()];
                // $and: no pisar el filtro de facturas por fecha de carga
                $condicion['$and'][] = ['cubGC_numFactura' => ['$in' => obtenerFacturasPagadasDesde($_GET)]];
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaContactabilidadGestion';
                $forma = "contactabilidad";
                break;

            case "listaCallCenter":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_canal"] = "TELEFONICA";
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaIntensidadCanal';
                $forma = "intensidadTelefono";
                break;

            case "listaWhatsapp":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_canal"] = "WHATSAPP";
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaIntensidadCanal';
                $forma = "intensidadTelefono";
                break;

            case "listaCorreo":
                $condicion = construirCondicionBaseGestion();
                $condicion["cubGC_canal"] = "EMAIL";
                $coleccion = "cuGestionCobranzaMysql";
                $orden = ["cubGC_fechaGestion" => -1];
                $normaliza = 'normalizaFilaIntensidadCanal';
                $forma = "intensidadCorreo";
                break;

            case "listaBaseAsignada":
                $condicion = construirCondicionAsignacionGestionBase();
                $coleccion = "cuAsignacionesGestionAP";
                $orden = ["cubAG_carteraId" => 1];
                $forma = "baseAsignada";
                break;

            case "listaClientesGestionados":
                $condicion = construirCondicionAsignacionGestionBase();
                aplicaCondicionGestionadoPorCanal($condicion, $canalAsignacion, true);
                $coleccion = "cuAsignacionesGestionAP";
                $orden = ["cubAG_fechaGestion" => -1];
                $normaliza = 'normalizaFilaClientesGestionados';
                $forma = "clientesGestionados";
                break;

            case "listaClientesSinGestion":
                $condicion = construirCondicionAsignacionGestionBase();
                aplicaCondicionGestionadoPorCanal($condicion, $canalAsignacion, false);
                $coleccion = "cuAsignacionesGestionAP";
                $orden = ["cubAG_carteraId" => 1];
                $normaliza = 'normalizaFilaClientesSinGestion';
                $forma = "clientesSinGestion";
                break;
        }

        if ($coleccion === "") {
            $json["error"] = 1;
            $json["mensaje"] = "Tipo de exportación no reconocido";
            break;
        }

        $mdb = new MYMONGODB();
        $mdb->buscar($coleccion, $condicion, [], $orden);

        $filasCrudas = [];
        while ($fila = $mdb->siguiente()) {
            $filasCrudas[] = $fila;
        }

        // Solo "Clientes Gestionados" cruza contra cuGestionCobranzaMysql para las
        // columnas operativas (llamadas/whatsapp/correo); el resto no lo necesita.
        $operativoPorFactura = ($tipo === 'listaClientesGestionados')
            ? obtenerOperativoGestionCobranzaPorFactura($filasCrudas)
            : [];

        $filas = [];
        foreach ($filasCrudas as $fila) {
            $filas[] = $normaliza ? call_user_func($normaliza, $fila, $canalAsignacion, $operativoPorFactura) : $fila;
        }

        list($cabeceras, $filasExcel, $columnasTexto) = armarFilasExcelDetalle($forma, $filas);

        $fechaHoy = date("Y_m_d");
        // El archivo empieza con el nombre de la cartera; sin cartera se deja "COBRANZA".
        $prefijoArchivo = $nombreCartera !== "" ? $nombreCartera : "COBRANZA";
        $nombreArchivo = $prefijoArchivo . "_" . strtr(strtoupper(preg_replace('/^lista/', '', $tipo)), ["INTERESADOS" => "COMPROMISO_PAGO", "EFECTIVOS" => "PAGADOS"]) . "_" . $fechaHoy;

        $json["ruta"] = generarExcelGenerico($cabeceras, $filasExcel, $nombreArchivo, $columnasTexto);
        #endregion
        break;


    case "listaAsignacionGestiones":

        #region listaAsignacionGestiones
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");
        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");

        // Convertir valores a string

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }


        $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];

            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);
        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
            $condicionCubGestion["cubGC_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }


        //<editor-fold defaultstate="collapsed" desc=" Lista las asignaciones para presentarlos en la tabula ">
        $coleccion = "cuAsignacionesGestionAP";

        //$campos = ["cubAG_numFactura", "cubAG_carteraId", "cubAG_carteraNombre", "cubAG_gestionada", "cubAG_tipificacion1", "cubAG_tipificacion2", "cubAG_producto"];
        $campos = [];
        $orden = ["cubAG_carteraId" => 1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);

        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);

        $ngTabula->setPreparaDatos(function ($campos) {
            $campos["cubAG_gestionada"] = (isset($campos['cubAG_gestionada']) && $campos['cubAG_gestionada'] == 1) ? "SI" : "NO";
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
                    "cubGC_numFactura" => $f["cubAG_numFactura"],
                    "cubGC_carteraId" => $f["cubAG_carteraId"],
                    "cubGC_tipificacion_respuesta1" => $f["cubAG_tipificacion1"],
                    "cubGC_tipificacion_respuesta2" => $f["cubAG_tipificacion2"],
                    "cubGC_producto" => $f["cubAG_producto"]
                ];


                // ?? Filtro fecha

                $condCuGV["cubGC_fechaGestion"] = [
                    '$gte' => $desde,
                    '$lte' => $hasta
                ];

                // ?? Buscar SOLO la más reciente
                $mdbCuGV->buscar('cuGestionCobranzaMysql', $condCuGV, [], ['cubGC_fechaGestion' => -1], 1);

                $fechaGestion = null;

                while ($doc = $mdbCuGV->siguiente()) {
                    $fechaGestion = isset($doc['cubGC_fechaGestion']) ? $doc['cubGC_fechaGestion'] : null;
                    break;
                }

                $f["fechaGestion"] = $fechaGestion;
            }

            $json["filas"] = $filas;
        }*/


        break;

    case "listaBaseAsignada":

        #region listaBaseAsignada
        // Detalle KPI "Gestión de Base" -> columna izquierda: TODA la base asignada (gestionada o no), con búsqueda por cliente.
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
        $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
        } else {
            // Sin una cartera específica seleccionada no debe mostrarse
            // nada en esta lista ? igual que el KPI/gráfica del dashboard principal
            $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        if ($buscarCliente != "") {
            $condicionCubAsignacion["cubAG_numFactura"] = [
                '$regex' => preg_quote($buscarCliente, '/'),
                '$options' => 'i'
            ];
        }

        // Fecha de Carga: cubAG_fechaCarga vive directo en esta colección.
        aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

        $coleccion = "cuAsignacionesGestionAP";
        $campos = [];
        $orden = ["cubAG_carteraId" => 1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaClientesGestionados":

        #region listaClientesGestionados
        // Detalle KPI "Gestión de Base" -> columna central: solo los registros con cubAG_gestionada = 1.
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
        $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
        $filtroCanal = expect_safe_html($_GET["filtroCanal"] ?? "todo");
        $canalAsignacion = obtenerCanalAsignacionDesde($filtroCanal);
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
        } else {
            // Sin una cartera específica seleccionada no debe mostrarse
            // nada en esta lista ? igual que el KPI/gráfica del dashboard principal
            $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        if ($buscarCliente != "") {
            $condicionCubAsignacion["cubAG_numFactura"] = [
                '$regex' => preg_quote($buscarCliente, '/'),
                '$options' => 'i'
            ];
        }

        // Fecha de Carga: cubAG_fechaCarga vive directo en esta colección.
        aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

        // Solo gestionados (por canal si hay uno elegido, si no la general)
        aplicaCondicionGestionadoPorCanal($condicionCubAsignacion, $canalAsignacion, true);

        $coleccion = "cuAsignacionesGestionAP";
        $campos = [];
        $orden = ["cubAG_fechaGestion" => -1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);
        $ngTabula->setPreparaDatos(function ($campos) use ($canalAsignacion) {
            $campos["cubAG_estado"] = "Gestionado";
            // Con canal, la fecha mostrada es la de LA GESTIÓN DE ESE CANAL,
            // no la de la mejor gestión general del cliente.
            if ($canalAsignacion !== "" && !empty($campos['cubAG_mejorGestion'][$canalAsignacion]['fechaGestion'])) {
                $campos['cubAG_fechaGestion'] = $campos['cubAG_mejorGestion'][$canalAsignacion]['fechaGestion'];
            }
            return $campos;
        });
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaClientesSinGestion":

        #region listaClientesSinGestion
        // Detalle KPI "Gestión de Base" -> columna derecha: solo los registros con cubAG_gestionada = 0.
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"]);
        $filtroProducto = expect_safe_html($_GET["filtroProducto"]);
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"]);
        $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
        $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
        $filtroCanal = expect_safe_html($_GET["filtroCanal"] ?? "todo");
        $canalAsignacion = obtenerCanalAsignacionDesde($filtroCanal);
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
        } else {
            // Sin una cartera específica seleccionada no debe mostrarse
            // nada en esta lista ? igual que el KPI/gráfica del dashboard principal
            $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        if ($buscarCliente != "") {
            $condicionCubAsignacion["cubAG_numFactura"] = [
                '$regex' => preg_quote($buscarCliente, '/'),
                '$options' => 'i'
            ];
        }

        // Fecha de Carga: cubAG_fechaCarga vive directo en esta colección.
        aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

        // Solo sin gestión (por canal si hay uno elegido, si no la general)
        aplicaCondicionGestionadoPorCanal($condicionCubAsignacion, $canalAsignacion, false);

        $coleccion = "cuAsignacionesGestionAP";
        $campos = [];
        $orden = ["cubAG_carteraId" => 1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);
        $ngTabula->setPreparaDatos(function ($campos) {
            $campos["cubAG_estado"] = "Sin gestión";
            return $campos;
        });
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "obtenerTotalesContactabilidad":

        #region obtenerTotalesContactabilidad
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"] ?? "");
        $filtroProducto = expect_safe_html($d["filtroProducto"] ?? "");
        $filtroTiempo = expect_safe_html($d["filtroTiempo"] ?? "");
        $filtroCanal = expect_safe_html($d["filtroCanal"] ?? "todo");
        $canalGestion = obtenerCanalGestionDesde($filtroCanal);
        $canalAsignacion = obtenerCanalAsignacionDesde($filtroCanal);
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"] ?? "todo");

        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }
        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
        } else {
            // Sin una cartera específica seleccionada, los 4 totales quedan en 0.
            $condicionCubGestion["cubGC_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
            $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubGestion["cubGC_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
            $condicionCubAsignacion["cubAG_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
        }

        // Contacto Directo/Indirecto/Sin Contacto salen de cuGestionCobranzaMysql
        // (cubGC_canal); No Contactado sale de cuAsignacionesGestionAP
        // (cubAG_canal, sin traducir).
        if ($canalGestion !== "") {
            $condicionCubGestion["cubGC_canal"] = $canalGestion;
        }
        if ($canalAsignacion !== "") {
            $condicionCubAsignacion["cubAG_canal"] = $canalAsignacion;
        }

        // Fecha de Carga: cubAG_fechaCarga se aplica directo sobre
        // cuAsignacionesGestionAP (afecta también "No Contactado", que
        // sale de ese cubo). cuGestionCobranzaMysql no tiene ese campo, así que
        // para Contacto Directo/Indirecto/Sin Contacto buscamos primero las
        // facturas cargadas esa fecha y filtramos las gestiones por ellas.
        if (aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga")) {
            $mdbFacturasCarga = new MYMONGODB();
            $mdbFacturasCarga->buscar('cuAsignacionesGestionAP', $condicionCubAsignacion, ['cubAG_numFactura']);
            $facturasFechaCarga = [];
            while ($rowFactura = $mdbFacturasCarga->siguiente()) {
                if (!empty($rowFactura['cubAG_numFactura'])) {
                    $facturasFechaCarga[] = $rowFactura['cubAG_numFactura'];
                }
            }
            $condicionCubGestion["cubGC_numFactura"] = ['$in' => array_values(array_unique($facturasFechaCarga))];
        }

        $condicionCubGestion = excluirWhatsappInvalido($condicionCubGestion);

        $totalContactoDirecto = 0;
        $totalContactoIndirecto = 0;
        $totalSinContacto = 0;
        $totalNoContactado = 0;

        $mdbGestionTot = new MYMONGODB();
        $pipelineGestionTot = [
            ['$match' => $condicionCubGestion],
            [
                '$group' => [
                    '_id' => '$cubGC_tipificacion_respuesta1',
                    'total' => ['$sum' => 1]
                ]
            ]
        ];
        $mdbGestionTot->aggregate("cuGestionCobranzaMysql", $pipelineGestionTot);
        while ($row = $mdbGestionTot->siguiente()) {
            switch ($row['_id']) {
                case 'CONTACTO DIRECTO':
                    $totalContactoDirecto = (int) $row['total'];
                    break;
                case 'CONTACTO INDIRECTO':
                    $totalContactoIndirecto = (int) $row['total'];
                    break;
                case 'SIN CONTACTO':
                    $totalSinContacto = (int) $row['total'];
                    break;
            }
        }

        $condicionCubAsignacion["cubAG_gestionada"] = 0;
        $mdbAsigTot = new MYMONGODB();
        $pipelineAsigTot = [
            ['$match' => $condicionCubAsignacion],
            [
                '$group' => [
                    '_id' => null,
                    'total' => ['$sum' => 1]
                ]
            ]
        ];
        $mdbAsigTot->aggregate("cuAsignacionesGestionAP", $pipelineAsigTot);
        if ($row = $mdbAsigTot->siguiente()) {
            $totalNoContactado = (int) $row['total'];
        }

        // Clientes unicos (no gestiones) por cada tarjeta, para el texto chico.
        $clientesPorTipificacion = contarClientesUnicosPorCategoria($condicionCubGestion, 'cubGC_tipificacion_respuesta1');
        $clientesContactoDirecto = $clientesPorTipificacion['CONTACTO DIRECTO'] ?? 0;
        $clientesContactoIndirecto = $clientesPorTipificacion['CONTACTO INDIRECTO'] ?? 0;
        $clientesSinContacto = $clientesPorTipificacion['SIN CONTACTO'] ?? 0;

        $json["totales"] = [
            "contactoDirecto" => formatea_numero($totalContactoDirecto, 0, ",", "."),
            "contactoIndirecto" => formatea_numero($totalContactoIndirecto, 0, ",", "."),
            "sinContacto" => formatea_numero($totalSinContacto, 0, ",", "."),
            "noContactado" => formatea_numero($totalNoContactado, 0, ",", "."),
            "contactoDirectoClientes" => formatea_numero($clientesContactoDirecto, 0, ",", "."),
            "contactoIndirectoClientes" => formatea_numero($clientesContactoIndirecto, 0, ",", "."),
            "sinContactoClientes" => formatea_numero($clientesSinContacto, 0, ",", "."),
        ];
        #endregion
        break;

    case "listaContactoDirecto":
        #region listaContactoDirecto
        $d = jsonStart();
        $condicionCubGestion = construirCondicionContactabilidadGestion('CONTACTO DIRECTO');
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaContactoIndirecto":
        #region listaContactoIndirecto
        $d = jsonStart();
        $condicionCubGestion = construirCondicionContactabilidadGestion('CONTACTO INDIRECTO');
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaSinContacto":
        #region listaSinContacto
        $d = jsonStart();
        $condicionCubGestion = construirCondicionContactabilidadGestion('SIN CONTACTO');
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaNoContactado":

        #region listaNoContactado
        $d = jsonStart();
        $filtroCartera = expect_safe_html($_GET["filtroCartera"] ?? "");
        $filtroProducto = expect_safe_html($_GET["filtroProducto"] ?? "");
        $filtroTiempo = expect_safe_html($_GET["filtroTiempo"] ?? "");
        $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
        $filtroCanal = expect_safe_html($_GET["filtroCanal"] ?? "todo");
        $canalAsignacion = obtenerCanalAsignacionDesde($filtroCanal);
        $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
        $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

        if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
            $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = 0;
            $hasta = 0;
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
        } else {
            $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubAsignacion["cubAG_producto"] = [
                '$regex' => '^' . $filtroProducto . '$',
                '$options' => 'i'
            ];
        }

        if ($canalAsignacion !== "") {
            $condicionCubAsignacion["cubAG_canal"] = $canalAsignacion;
        }

        // Fecha de Carga: cubAG_fechaCarga vive directo en esta colección.
        aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

        if ($buscarCliente != "") {
            $condicionCubAsignacion['$or'] = [
                ['cubAG_numFactura' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
                ['cubAG_nombres' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
                ['cubAG_apellidos' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
            ];
        }

        // Solo los que nunca se han gestionado
        $condicionCubAsignacion["cubAG_gestionada"] = 0;

        $coleccion = "cuAsignacionesGestionAP";
        $campos = [];
        $orden = ["cubAG_fechaCarga" => -1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubAsignacion, $campos, $orden);
        $ngTabula->setPreparaDatos(function ($campos) {
            // Nombre del cliente en mayusculas
            $nombreCliente = strtoupper(trim(($campos['cubAG_nombres'] ?? '') . ' ' . ($campos['cubAG_apellidos'] ?? '')));
            $campos['operacion'] = $campos['cubAG_numFactura'] ?? '';
            $campos['nombreCliente'] = $nombreCliente;
            $campos['telefono'] = '';
            $campos['campania'] = $campos['cubAG_producto'] ?? '';
            $campos['fechaMostrar'] = $campos['cubAG_fechaCarga'] ?? null;
            $campos['tipificacion'] = '';
            $campos['mejorGestion'] = '';
            return $campos;
        });
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "obtenerTotalesEfectividad":

        #region obtenerTotalesEfectividad
        // "Interesados" = compromiso de pago; "Efectivos" = pagados
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"] ?? "");
        $filtroProducto = expect_safe_html($d["filtroProducto"] ?? "");
        $filtroTiempo = expect_safe_html($d["filtroTiempo"] ?? "");
        $filtroCanal = expect_safe_html($d["filtroCanal"] ?? "todo");
        $canalGestion = obtenerCanalGestionDesde($filtroCanal);
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"] ?? "todo");

        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $desde = 0;
        $hasta = 0;

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
        } else {
            // sin una cartera específica seleccionada, los 4 totales quedan en 0.
            $condicionCubGestion["cubGC_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubGestion["cubGC_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
        }

        if ($canalGestion !== "") {
            $condicionCubGestion["cubGC_canal"] = $canalGestion;
        }

        // Fecha de Carga: cuGestionCobranzaMysql no la guarda -> se resuelve vía
        // las facturas cargadas esa fecha en cuAsignacionesGestionAP.
        $facturasFechaCarga = obtenerFacturasPorFechaCarga($filtroCartera, $filtroProducto, $desde, $hasta, $filtroFechaCarga);
        if ($facturasFechaCarga !== null) {
            $condicionCubGestion["cubGC_numFactura"] = ['$in' => $facturasFechaCarga];
        }

        $condicionCubGestion = excluirWhatsappInvalido($condicionCubGestion);

        // El pago solo existe en la asignación: una gestión es "pagada" si es
        // compromiso de pago y su factura pagó en el período.
        $facturasPagadas = obtenerFacturasPagadasDesde($d);

        $totalGestionados = 0;
        $totalContactados = 0;
        $totalInteresados = 0;
        $totalEfectivos = 0;

        $mdbEfecTot = new MYMONGODB();
        $pipelineEfecTot = [
            ['$match' => $condicionCubGestion],
            [
                '$group' => [
                    '_id' => null,
                    'totalRegistros' => ['$sum' => 1],
                    'totalContactados' => [
                        '$sum' => [
                            '$cond' => [
                                ['$in' => ['$cubGC_tipificacion_respuesta1', ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']]],
                                1,
                                0
                            ]
                        ]
                    ],
                    'totalInteresados' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubGC_tipificacion_respuesta1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubGC_tipificacion_respuesta2', tipificacionesCompromisoPago()]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    'totalEfectivos' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubGC_tipificacion_respuesta1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubGC_tipificacion_respuesta2', tipificacionesCompromisoPago()]],
                                        ['$in' => ['$cubGC_numFactura', $facturasPagadas]]
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
        $mdbEfecTot->aggregate("cuGestionCobranzaMysql", $pipelineEfecTot);
        if ($row = $mdbEfecTot->siguiente()) {
            $totalGestionados = (int) $row['totalRegistros'];
            $totalContactados = (int) $row['totalContactados'];
            $totalInteresados = (int) $row['totalInteresados'];
            $totalEfectivos = (int) $row['totalEfectivos'];
        }

        // Clientes unicos por tarjeta (no gestiones), para el texto chico.
        // Contactados/Interesados no son categorias excluyentes entre si (un
        // mismo cliente puede tener varias gestiones), asi que se agrupa por
        // cliente primero y se marca si AL MENOS UNA de sus gestiones cumple
        // cada condicion (mismas condiciones que arriba, a nivel de gestion).
        $clientesGestionados = 0;
        $clientesContactados = 0;
        $clientesInteresados = 0;
        $clientesEfectivos = 0;

        $mdbClientesEfecTot = new MYMONGODB();
        $pipelineClientesEfecTot = [
            ['$match' => $condicionCubGestion],
            [
                '$group' => [
                    '_id' => '$cubGC_numFactura',
                    'esContactado' => [
                        '$max' => [
                            '$cond' => [
                                ['$in' => ['$cubGC_tipificacion_respuesta1', ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']]],
                                1,
                                0
                            ]
                        ]
                    ],
                    'esInteresado' => [
                        '$max' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubGC_tipificacion_respuesta1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubGC_tipificacion_respuesta2', tipificacionesCompromisoPago()]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    'esEfectivo' => [
                        '$max' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubGC_tipificacion_respuesta1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubGC_tipificacion_respuesta2', tipificacionesCompromisoPago()]],
                                        ['$in' => ['$cubGC_numFactura', $facturasPagadas]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'totalClientes' => ['$sum' => 1],
                    'clientesContactados' => ['$sum' => '$esContactado'],
                    'clientesInteresados' => ['$sum' => '$esInteresado'],
                    'clientesEfectivos' => ['$sum' => '$esEfectivo'],
                ]
            ]
        ];
        $mdbClientesEfecTot->aggregate("cuGestionCobranzaMysql", $pipelineClientesEfecTot);
        if ($row = $mdbClientesEfecTot->siguiente()) {
            $clientesGestionados = (int) $row['totalClientes'];
            $clientesContactados = (int) $row['clientesContactados'];
            $clientesInteresados = (int) $row['clientesInteresados'];
            $clientesEfectivos = (int) $row['clientesEfectivos'];
        }

        $json["totales"] = [
            "gestionados" => formatea_numero($totalGestionados, 0, ",", "."),
            "contactados" => formatea_numero($totalContactados, 0, ",", "."),
            "interesados" => formatea_numero($totalInteresados, 0, ",", "."),
            "efectivos" => formatea_numero($totalEfectivos, 0, ",", "."),
            "gestionadosClientes" => formatea_numero($clientesGestionados, 0, ",", "."),
            "contactadosClientes" => formatea_numero($clientesContactados, 0, ",", "."),
            "interesadosClientes" => formatea_numero($clientesInteresados, 0, ",", "."),
            "efectivosClientes" => formatea_numero($clientesEfectivos, 0, ",", "."),
        ];
        #endregion
        break;

    case "listaGestionados":
        #region listaGestionados

        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaContactados":
        #region listaContactados
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_tipificacion_respuesta1"] = ['$in' => ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']];
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaInteresados":
        #region listaInteresados
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_tipificacion_respuesta1"] = 'CONTACTO DIRECTO';
        $condicionCubGestion["cubGC_tipificacion_respuesta2"] = ['$in' => tipificacionesCompromisoPago()];
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaEfectivos":
        #region listaEfectivos
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_tipificacion_respuesta1"] = 'CONTACTO DIRECTO';
        $condicionCubGestion["cubGC_tipificacion_respuesta2"] = ['$in' => tipificacionesCompromisoPago()];
        // $and: no pisar el filtro de facturas por fecha de carga
        $condicionCubGestion['$and'][] = ['cubGC_numFactura' => ['$in' => obtenerFacturasPagadasDesde($_GET)]];
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorContactabilidadGestion();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaContactabilidadGestion');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "obtenerTotalesIntensidadCanales":

        #region obtenerTotalesIntensidadCanales
        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"] ?? "");
        $filtroProducto = expect_safe_html($d["filtroProducto"] ?? "");
        $filtroTiempo = expect_safe_html($d["filtroTiempo"] ?? "");
        $filtroCanal = expect_safe_html($d["filtroCanal"] ?? "todo");
        $canalGestion = obtenerCanalGestionDesde($filtroCanal);
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"] ?? "todo");

        $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");
        $desde = 0;
        $hasta = 0;

        if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
            $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
        }

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);
        if (esFiltroPeriodoAsignacion($filtroTiempo)) {
            $desde = $periodoAsignacion["desde"];
            $hasta = $periodoAsignacion["hasta"];
            $condicionCubGestion["cubGC_fechaGestion"] = ['$gte' => $desde, '$lte' => $hasta];
        }
        aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);

        if ($filtroCartera != "" && $filtroCartera != "todo") {
            $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
        } else {
            //sin una cartera especifica seleccionada, los 3 totales quedan en 0.
            $condicionCubGestion["cubGC_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
        }

        if ($filtroProducto != "" && $filtroProducto != "todo") {
            $condicionCubGestion["cubGC_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
        }

        // Nota: si se selecciona un canal, esto reduce el universo de
        // gestiones ANTES de repartir por cubGC_canal en el $group -> las
        // 2 pestañas no seleccionadas quedan naturalmente en 0, igual que
        // en las demás pantallas.
        if ($canalGestion !== "") {
            $condicionCubGestion["cubGC_canal"] = $canalGestion;
        }

        // Fecha de Carga: cuGestionCobranzaMysql no la guarda -> se resuelve vía
        // las facturas cargadas esa fecha en cuAsignacionesGestionAP.
        $facturasFechaCarga = obtenerFacturasPorFechaCarga($filtroCartera, $filtroProducto, $desde, $hasta, $filtroFechaCarga);
        if ($facturasFechaCarga !== null) {
            $condicionCubGestion["cubGC_numFactura"] = ['$in' => $facturasFechaCarga];
        }

        $condicionCubGestion = excluirWhatsappInvalido($condicionCubGestion);

        $totalCallCenter = 0;
        $totalWhatsapp = 0;
        $totalCorreo = 0;

        $mdbIntTot = new MYMONGODB();
        $pipelineIntTot = [
            ['$match' => $condicionCubGestion],
            [
                '$group' => [
                    '_id' => null,
                    'totalCallCenter' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'TELEFONICA']],
                                1,
                                0
                            ]
                        ]
                    ],
                    'totalWhatsapp' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'WHATSAPP']],
                                1,
                                0
                            ]
                        ]
                    ],
                    'totalCorreo' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'EMAIL']],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Leidos: mismo criterio que correosLeidos (cubGC_cantidad_abierto > 0).
                    'totalCorreoLeidos' => [
                        '$sum' => [
                            '$cond' => [
                                ['$and' => [
                                    ['$eq' => ['$cubGC_canal', 'EMAIL']],
                                    ['$gt' => ['$cubGC_cantidad_abierto', 0]],
                                ]],
                                1,
                                0
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $mdbIntTot->aggregate("cuGestionCobranzaMysql", $pipelineIntTot);
        $totalCorreoLeidos = 0;
        if ($row = $mdbIntTot->siguiente()) {
            $totalCallCenter = (int) $row['totalCallCenter'];
            $totalWhatsapp = (int) $row['totalWhatsapp'];
            $totalCorreo = (int) $row['totalCorreo'];
            $totalCorreoLeidos = (int) $row['totalCorreoLeidos'];
        }

        // Clientes unicos (no gestiones) por cada tarjeta, para el texto chico.
        $clientesPorCanal = contarClientesUnicosPorCategoria($condicionCubGestion, 'cubGC_canal');
        $clientesCallCenter = $clientesPorCanal['TELEFONICA'] ?? 0;
        $clientesWhatsapp = $clientesPorCanal['WHATSAPP'] ?? 0;
        $clientesCorreo = $clientesPorCanal['EMAIL'] ?? 0;

        // Clientes unicos (por numFactura) que leyeron al menos un correo: 2
        // correos leidos del mismo cliente cuentan 1 sola vez aqui, a diferencia
        // de totalCorreoLeidos que cuenta cada gestion/correo por separado.
        // 2 $match (no 1 solo con $and) para no pisar un cubGC_canal que ya
        // venga fijado en $condicionCubGestion por el filtro de canal.
        $mdbClientesLeidos = new MYMONGODB();
        $mdbClientesLeidos->aggregate("cuGestionCobranzaMysql", [
            ['$match' => $condicionCubGestion],
            ['$match' => ['cubGC_canal' => 'EMAIL', 'cubGC_cantidad_abierto' => ['$gt' => 0]]],
            ['$group' => ['_id' => '$cubGC_numFactura']],
            ['$count' => 'total'],
        ]);
        $clientesCorreoLeidos = 0;
        if ($rowClientesLeidos = $mdbClientesLeidos->siguiente()) {
            $clientesCorreoLeidos = (int) $rowClientesLeidos['total'];
        }

        $json["totales"] = [
            "callCenter" => formatea_numero($totalCallCenter, 0, ",", "."),
            "whatsapp" => formatea_numero($totalWhatsapp, 0, ",", "."),
            "correo" => formatea_numero($totalCorreo, 0, ",", "."),
            "callCenterClientes" => formatea_numero($clientesCallCenter, 0, ",", "."),
            "whatsappClientes" => formatea_numero($clientesWhatsapp, 0, ",", "."),
            "correoClientes" => formatea_numero($clientesCorreo, 0, ",", "."),
            "correoLeidos" => formatea_numero($totalCorreoLeidos, 0, ",", "."),
            "correoClientesLeidos" => formatea_numero($clientesCorreoLeidos, 0, ",", "."),
        ];
        #endregion
        break;

    case "listaCallCenter":
        #region listaCallCenter
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_canal"] = "TELEFONICA";
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorIntensidadCanal();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaIntensidadCanal');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaWhatsapp":
        #region listaWhatsapp
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_canal"] = "WHATSAPP";
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorIntensidadCanal();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaIntensidadCanal');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "listaCorreo":
        #region listaCorreo
        $d = jsonStart();
        $condicionCubGestion = construirCondicionBaseGestion();
        $condicionCubGestion["cubGC_canal"] = "EMAIL";
        $coleccion = "cuGestionCobranzaMysql";
        $campos = [];
        $orden = ["cubGC_fechaGestion" => -1];
        $limpiar = limpiadorIntensidadCanal();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condicionCubGestion, $campos, $orden);
        $ngTabula->setPreparaDatos('normalizaFilaIntensidadCanal');
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        #endregion
        break;

    case "obtenerAudioTranscripcionLlamada":
        #region obtenerAudioTranscripcionLlamada
        // Subset de cmReporteAgenteVirtualCtrl.php->obtenerDetalleConversacion:
        // solo audio y transcripcion, a partir del avId guardado en cuGestionCobranzaMysql.
        $d = jsonStart();
        $avId = expect_safe_html($d["avId"] ?? "");
        $json["respuesta"] = obtenerAudioTranscripcionPorAvId($avId);
        #endregion
        break;

    case "obtenerTranscripcionWhatsapp":
        #region obtenerTranscripcionWhatsapp
        // Subset de cmReporteAgenteVirtualWhatsappCtrl.php->obtenerDetalleChat:
        // solo la transcripcion, a partir del avId guardado en cuGestionCobranzaMysql.
        $d = jsonStart();
        $avId = expect_safe_html($d["avId"] ?? "");
        $json["respuesta"] = obtenerTranscripcionWhatsappPorAvId($avId);
        #endregion
        break;

    case "obtenerDetalleCorreo":
        #region obtenerDetalleCorreo
        // Body html del correo (cbEnvioMails), a partir del avId guardado en
        // cuGestionCobranzaMysql para el canal EMAIL.
        $d = jsonStart();
        $avId = expect_safe_html($d["avId"] ?? "");
        $json["respuesta"] = obtenerDetalleCorreoPorAvId($avId);
        #endregion
        break;

    case "obtenerVariacionHorarios":

        #region obtenerVariacionHorarios

        $d = jsonStart();
        $filtroCartera = expect_safe_html($d["filtroCartera"] ?? "");
        $filtroProducto = expect_safe_html($d["filtroProducto"] ?? "");
        $filtroTiempo = expect_safe_html($d["filtroTiempo"] ?? "");
        $filtroCanal = expect_safe_html($d["filtroCanal"] ?? "todo");
        $canalGestion = obtenerCanalGestionDesde($filtroCanal);
        $filtroFechaCarga = expect_safe_html($d["filtroFechaCarga"] ?? "todo");

        $json["variacion"] = [];

        $periodoAsignacion = obtenerPeriodoAsignacionDesde($d);

        if ($periodoAsignacion["desde"] > 0) {

            $desdeActual = $periodoAsignacion["desde"];
            $hastaActual = $periodoAsignacion["hasta"];

            // Se compara contra el período inmediatamente anterior al elegido:
            // con "Actual" es el último cerrado; con "Anterior", el previo a ese.
            $periodoAnterior = obtenerPeriodoAsignacionPrevio($filtroCartera, $desdeActual, $periodoAsignacion["ciclo"]);

            if ($periodoAnterior) {

                $desdeAnt = intval($periodoAnterior["fecha"]);
                $hastaAnt = strtotime(date("Y-m-d", intval($periodoAnterior["fechaFin"])) . " 23:59:59");

                $condicionCubGestionAnt = establecerCondicionMaster("cubGC_carteraId");
                $condicionCubGestionAnt["cubGC_carteraId"] = strval($filtroCartera);
                $condicionCubGestionAnt["cubGC_fechaGestion"] = ['$gte' => $desdeAnt, '$lte' => $hastaAnt];
                aplicaCondicionCiclo($condicionCubGestionAnt, "cubGC_ciclo", $periodoAsignacion);

                if ($filtroProducto != "" && $filtroProducto != "todo") {
                    $condicionCubGestionAnt["cubGC_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
                }

                if ($canalGestion !== "") {
                    $condicionCubGestionAnt["cubGC_canal"] = $canalGestion;
                }

                // Fecha de Carga: comparamos, contra el período anterior,
                // las MISMAS facturas que se cargaron esa fecha en el
                // período actual (cuGestionCobranzaMysql no tiene fechaCarga).
                $facturasFechaCarga = obtenerFacturasPorFechaCarga($filtroCartera, $filtroProducto, $desdeActual, $hastaActual, $filtroFechaCarga);
                if ($facturasFechaCarga !== null) {
                    $condicionCubGestionAnt["cubGC_numFactura"] = ['$in' => $facturasFechaCarga];
                }

                $mdbGestionAnt = new MYMONGODB();
                $pipelineGestionAnt = [
                    ['$match' => $condicionCubGestionAnt],
                    [
                        '$addFields' => [
                            'hora' => [
                                '$hour' => [
                                    'date' => ['$toDate' => ['$multiply' => ['$cubGC_fechaGestion', 1000]]],
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
                                        ['case' => ['$lt' => ['$hora', 10]], 'then' => '0-10'],
                                        ['case' => ['$and' => [['$gte' => ['$hora', 10]], ['$lt' => ['$hora', 12]]]], 'then' => '10-12'],
                                        ['case' => ['$and' => [['$gte' => ['$hora', 12]], ['$lt' => ['$hora', 14]]]], 'then' => '12-14'],
                                        ['case' => ['$and' => [['$gte' => ['$hora', 14]], ['$lt' => ['$hora', 16]]]], 'then' => '14-16'],
                                        ['case' => ['$and' => [['$gte' => ['$hora', 16]], ['$lt' => ['$hora', 18]]]], 'then' => '16-18'],
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
                            'contactados' => [
                                '$sum' => [
                                    '$cond' => [
                                        ['$in' => ['$cubGC_tipificacion_respuesta1', ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO']]],
                                        1,
                                        0
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                $mdbGestionAnt->aggregate("cuGestionCobranzaMysql", $pipelineGestionAnt);

                $rangosAnt = [];
                while ($row = $mdbGestionAnt->siguiente()) {
                    $rangosAnt[$row['_id']] = [
                        "total" => (int) $row['total'],
                        "contactados" => (int) $row['contactados']
                    ];
                }

                $ordenFranjas = ['0-10', '10-12', '12-14', '14-16', '16-18', '18+'];

                foreach ($ordenFranjas as $rango) {
                    $totalAnt = $rangosAnt[$rango]["total"] ?? 0;
                    $contactadosAnt = $rangosAnt[$rango]["contactados"] ?? 0;
                    $porcentajeAnt = $totalAnt > 0 ? round(($contactadosAnt * 100) / $totalAnt, 2) : 0;

                    $json["variacion"][] = [
                        "hayDato" => true,
                        "porcentajeAnterior" => $porcentajeAnt,
                        "totalAnterior" => $totalAnt,
                        "contactadosAnterior" => $contactadosAnt
                    ];
                }
            }
        }
        #endregion
        break;
}

// Reparte conteos que suman exactamente $total en porcentajes que tambien
// suman EXACTO 100 (metodo del mayor residuo). Evita que redondear cada
// porcentaje por separado deje el pie de Contactabilidad en 99.99% o 100.01%.
function distribuirPorcentajes100($valores, $total, $decimales = 2)
{
    if ($total <= 0) {
        return array_fill(0, count($valores), 0);
    }

    $escala = pow(10, $decimales);
    $unidadesObjetivo = 100 * $escala;

    $unidades = [];
    $residuos = [];
    foreach ($valores as $i => $valor) {
        $exacto = ($valor * $unidadesObjetivo) / $total;
        $unidades[$i] = floor($exacto);
        $residuos[$i] = $exacto - $unidades[$i];
    }

    // Lo que falta para llegar a 100 se reparte de a 1 unidad (0.01) en los
    // valores con el residuo mas grande, que son los mas cercanos a redondear hacia arriba.
    $faltante = (int) ($unidadesObjetivo - array_sum($unidades));
    arsort($residuos);
    foreach (array_keys($residuos) as $i) {
        if ($faltante <= 0) {
            break;
        }
        $unidades[$i]++;
        $faltante--;
    }

    $resultado = [];
    foreach ($valores as $i => $valor) {
        $resultado[$i] = $unidades[$i] / $escala;
    }
    return $resultado;
}




//TOTALES GESTION NUEVOS
function obtenerTotalesGestion2($condicionCubAsignacion, $condicionCubGestion, $canalAsignacion = "")
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
                "nombre" => "Cartera con compromiso de pago",
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
            [
                "nombre" => "Cartera con compromiso de pago pagada",
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
    $pagados = 0;

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

    // KPI "Gestión de Base": con canal elegido usa la MEJOR GESTIÓN DE ESE
    // CANAL (cubAG_mejorGestion.<canal>), no la mejor gestión general.
    // $ifNull cubre al cliente sin ese sub-objeto (nunca gestionado por
    // ese canal), para que no cuente por error como "".
    if ($canalAsignacion !== "") {
        $campoCuGestionId = ['$ifNull' => ['$cubAG_mejorGestion.' . $canalAsignacion . '.cuGestionId', '']];
        $condGestionadoBase = ['$ne' => [$campoCuGestionId, '']];
        $condSinGestionBase = ['$eq' => [$campoCuGestionId, '']];
        // Contacto Directo/Indirecto/Sin Contacto deben salir del MISMO lugar
        // que "gestionado por ese canal" (cubAG_mejorGestion.<canal>), no de
        // la mejor gestion general (cubAG_canal); si no, un cliente puede
        // quedar "gestionado" por WhatsApp pero con AV como mejor gestion
        // general, y no cae en ningun bucket de tipificacion -> el pie no
        // suma 100% al filtrar por canal.
        $campoTipif1Canal = ['$ifNull' => ['$cubAG_mejorGestion.' . $canalAsignacion . '.tipificacion1', '']];
        $campoTipif2Canal = ['$ifNull' => ['$cubAG_mejorGestion.' . $canalAsignacion . '.tipificacion2', '']];
    } else {
        $condGestionadoBase = ['$eq' => ['$cubAG_gestionada', 1]];
        $condSinGestionBase = ['$eq' => ['$cubAG_gestionada', 0]];
        $campoTipif1Canal = '$cubAG_tipificacion1';
        $campoTipif2Canal = '$cubAG_tipificacion2';
    }

    if (!empty($condicionCubAsignacion["cubAG_fechaPeriodo"])) {

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
                                $condGestionadoBase,
                                1,
                                0
                            ]
                        ]
                    ],
                    // Total sin gestión
                    'totalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                $condSinGestionBase,
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
                                        $condGestionadoBase,
                                        ['$eq' => [$campoTipif1Canal, 'CONTACTO DIRECTO']]
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
                                        $condGestionadoBase,
                                        ['$eq' => [$campoTipif1Canal, 'CONTACTO INDIRECTO']]
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
                                        $condGestionadoBase,
                                        ['$eq' => [$campoTipif1Canal, 'SIN CONTACTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Compromiso de pago (antes "Interesados")
                    'totalInteresados' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $condGestionadoBase,
                                        ['$eq' => [$campoTipif1Canal, 'CONTACTO DIRECTO']],
                                        ['$in' => [$campoTipif2Canal, tipificacionesCompromisoPago()]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Pagados: compromiso de pago con pagos registrados en el período
                    'totalPagados' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        $condGestionadoBase,
                                        ['$eq' => [$campoTipif1Canal, 'CONTACTO DIRECTO']],
                                        ['$in' => [$campoTipif2Canal, tipificacionesCompromisoPago()]],
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
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

        // trigger_error("cond cub asignacion " . print_r($condicionCubAsignacion, true));

        $mdbAsig->aggregate("cuAsignacionesGestionAP", $pipeline);
        if ($row = $mdbAsig->siguiente()) {
            $total              = (int)$row['totalRegistros'];
            $totalGestionado    = (int)$row['totalGestionados'];
            $sinGestion  = (int) $row['totalSinGestion'];

            $contactoDirecto        = (int)$row['totalContactoDirecto'];
            $contactoIndirecto      = (int)$row['totalContactoIndirecto'];
            $sinContactoGestionado  = (int)$row['totalSinContacto'];
            $interesados            = (int)$row['totalInteresados'];
            $pagados                = (int)$row['totalPagados'];
        }
        $totalContactado = $contactoDirecto + $contactoIndirecto;


    }
    if ($total > 0 && !empty($condicionCubGestion["cubGC_fechaGestion"])) {

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
                                ['$eq' => ['$cubGC_canal', 'TELEFONICA']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // WhatsApp
                    'totalWhatsapp' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'WHATSAPP']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // Email
                    'totalEmail' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'EMAIL']],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $mdbIntensidad->aggregate("cuGestionCobranzaMysql", $pipelineIntensidad);

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


        if ($totalGestionado > 0) {
            //Porcentajes para chartPie, se dividen  para el total
            //cartera asignada
            $resultado["gestion"][0]["total"] = formatea_numero($total, 0, ",", ".");
            //cartera gestionada
            $resultado["gestion"][1]["total"] = formatea_numero($totalGestionado, 0, ",", ".");
            $resultado["gestion"][1]["porcentaje"] = $total > 0 ? formatea_numero(($totalGestionado * 100) / $total, 2, ",", ".") : 0;
            //cartera no gestionada
            $resultado["gestion"][2]["total"] = formatea_numero($sinGestion, 0, ",", ".");
            //total contactado
            $resultado["gestion"][3]["total"] = formatea_numero($totalContactado, 0, ",", ".");
            $resultado["gestion"][3]["porcentaje"] = $total > 0 ? formatea_numero(($totalContactado * 100) / $total, 2, ",", ".") : 0;

            //contacto directo
            $resultado["tipificacion"][0]["total"] = formatea_numero($contactoDirecto, 0, ",", ".");
            //contacto indirecto
            $resultado["tipificacion"][1]["total"] = formatea_numero($contactoIndirecto, 0, ",", ".");
            //sin contacto
            $resultado["tipificacion"][2]["total"] = formatea_numero($sinContactoGestionado, 0, ",", ".");

            // Los 4 porcentajes del pie de Contactabilidad (Contacto Directo/
            // Indirecto/Sin Contacto + No Contactado) se reparten juntos para
            // que la suma de en 100% exacto, sin importar el redondeo de cada uno.
            list($pctContactoDirecto, $pctContactoIndirecto, $pctSinContacto, $pctSinGestion) = array_values(
                distribuirPorcentajes100([$contactoDirecto, $contactoIndirecto, $sinContactoGestionado, $sinGestion], $total)
            );
            $resultado["tipificacion"][0]["porcentaje"] = formatea_numero($pctContactoDirecto, 2, ",", ".");
            $resultado["tipificacion"][1]["porcentaje"] = formatea_numero($pctContactoIndirecto, 2, ",", ".");
            $resultado["tipificacion"][2]["porcentaje"] = formatea_numero($pctSinContacto, 2, ",", ".");
            $resultado["gestion"][2]["porcentaje"] = formatea_numero($pctSinGestion, 2, ",", ".");

            //Porcentajes para  chartFunnel, se divide para gestionados y contactados
            //compromisos de pago
            $resultado["gestion"][4]["total"] = formatea_numero($interesados, 0, ",", ".");
            $resultado["gestion"][4]["porcentaje"] = $totalContactado > 0 ? formatea_numero(($interesados * 100) / $totalContactado, 2, ",", ".") : 0;

            //pagados: % sobre los compromisos de pago
            $resultado["gestion"][6]["total"] = formatea_numero($pagados, 0, ",", ".");
            $resultado["gestion"][6]["porcentaje"] = $interesados > 0 ? formatea_numero(($pagados * 100) / $interesados, 2, ",", ".") : 0;

            //total contactado
            $resultado["gestion"][5]["total"] = formatea_numero($totalContactado, 0, ",", ".");
            $resultado["gestion"][5]["porcentaje"] = $totalGestionado > 0 ? formatea_numero(($totalContactado * 100) / $totalGestionado, 2, ",", ".") : 0;
        }


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
                                    '$multiply' => ['$cubGC_fechaGestion', 1000]
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
                                        '$cubGC_tipificacion_respuesta1',
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





        $mdbGestion->aggregate("cuGestionCobranzaMysql", $pipelineGestion);

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
    $rutaRelativaExcel = "/canalesMasivos/reportesExcel/cobranza/$anio/$mes/";

    //RUTA FÍSICA (DONDE SE GUARDA)
    $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . "/canalesMasivos/reportesExcel/cobranza/$anio/$mes";

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
            $fila['cubAG_numFactura'] ?? '',
            $fila['cubAG_carteraNombre'] ?? '',
            (isset($fila['cubAG_gestionada']) && $fila['cubAG_gestionada'] == 1) ? "SI" : "NO",
            $fila['cubAG_tipificacion1'] ?? '',
            $fila['cubAG_tipificacion2'] ?? '',
            (isset($fila['cubAG_fechaGestion']) && $fila['cubAG_fechaGestion'] > 0)
                ? date('Y-m-d H:i:s', $fila['cubAG_fechaGestion']) : ''

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
                $font = $objExcel->setFilaEstiloFuente("Calibri", "12", true, "#ffffff");
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
    $rutaRelativa = "/canalesMasivos/reportesExcel/cobranza/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();

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

// -----------------------------------------------------------------------
// Período de asignación (control_carga_periodo). Reemplaza al rango
// Desde/Hasta del dashboard de ventas: el front manda en filtroTiempo
// "asigActual" o "asigAnterior" y en filtroPeriodo el ciclo (-1 = todos).
//   - Actual:   el registro activo=1 más reciente de la cartera.
//   - Anterior: el activo=0 más reciente con fecha de inicio menor a la
//               del período actual (si no hay actual, el último cerrado).
// Todos los ciclos activos de una cartera comparten fechas (lo valida
// cmFechasPeriodoCtrl.php), así que con "todos los ciclos" basta el primero.
// -----------------------------------------------------------------------

/**
 * Indica si el valor de filtroTiempo corresponde a un período de asignación.
 * @param string $filtroTiempo Valor recibido del front
 * @return bool
 */
function esFiltroPeriodoAsignacion(string $filtroTiempo): bool
{
    return in_array($filtroTiempo, FILTROS_PERIODO_ASIGNACION, true);
}

/**
 * Resuelve el rango del período de asignación a partir de los filtros de la
 * petición (filtroCartera, filtroTiempo y filtroPeriodo).
 * Si no hay cartera, no se pidió un período o no existe, desde/hasta van en 0.
 * El ciclo aplica con solo tener cartera, aunque no se haya elegido período.
 * @param array $origen $d (jsonStart) o $_GET
 * @return array{desde:int, hasta:int, ciclo:int, fechaInicio:string, fechaFin:string}
 */
function obtenerPeriodoAsignacionDesde(array $origen): array
{
    $filtroCartera = expect_safe_html($origen["filtroCartera"] ?? "");
    $filtroTiempo = expect_safe_html($origen["filtroTiempo"] ?? "");
    $ciclo = (isset($origen["filtroPeriodo"]) && $origen["filtroPeriodo"] !== "") ? intval($origen["filtroPeriodo"]) : CICLO_TODOS;

    $resultado = ["desde" => 0, "hasta" => 0, "ciclo" => $ciclo, "fechaInicio" => "", "fechaFin" => ""];
    if ($filtroCartera === "" || $filtroCartera === "todo") {
        $resultado["ciclo"] = CICLO_TODOS;
        return $resultado;
    }
    if (!esFiltroPeriodoAsignacion($filtroTiempo)) {
        return $resultado;
    }

    $periodo = buscarPeriodoAsignacion($filtroCartera, $filtroTiempo, $ciclo);
    if ($periodo === null) {
        return $resultado;
    }

    $resultado["desde"] = intval($periodo["fecha"]);
    $resultado["hasta"] = strtotime(date("Y-m-d", intval($periodo["fechaFin"])) . " 23:59:59");
    $resultado["fechaInicio"] = date("d/m/Y", $resultado["desde"]);
    $resultado["fechaFin"] = date("d/m/Y", $resultado["hasta"]);
    return $resultado;
}

/**
 * Busca en control_carga_periodo el período actual o el anterior de la cartera.
 * @param string $filtroCartera Id de la cartera
 * @param string $filtroTiempo "asigActual" | "asigAnterior"
 * @param int $ciclo Ciclo a filtrar, CICLO_TODOS para no filtrar
 * @return array|null Documento de control_carga_periodo o null si no hay
 */
function buscarPeriodoAsignacion(string $filtroCartera, string $filtroTiempo, int $ciclo): ?array
{
    $condicion = ["cartera" => intval($filtroCartera), "activo" => 1];
    if ($ciclo >= 0) {
        $condicion["periodo"] = $ciclo;
    }

    $mongo = new MYMONGODB();
    $mongo->buscar(COLECCION_PERIODOS, $condicion, [], ["fecha" => -1], 1);
    $actual = $mongo->siguiente() ?: null;

    if ($filtroTiempo === "asigActual") {
        return $actual;
    }
    return obtenerPeriodoAsignacionPrevio($filtroCartera, $actual !== null ? intval($actual["fecha"]) : 0, $ciclo);
}

/**
 * Devuelve el período cerrado (activo=0) más reciente que empezó antes de
 * $fechaReferencia. Con $fechaReferencia en 0 devuelve el último cerrado.
 * @param string $filtroCartera Id de la cartera
 * @param int $fechaReferencia Timestamp de inicio del período de referencia
 * @param int $ciclo Ciclo a filtrar, CICLO_TODOS para no filtrar
 * @return array|null Documento de control_carga_periodo o null si no hay
 */
function obtenerPeriodoAsignacionPrevio(string $filtroCartera, int $fechaReferencia, int $ciclo): ?array
{
    $condicion = ["cartera" => intval($filtroCartera), "activo" => 0];
    if ($fechaReferencia > 0) {
        $condicion["fecha"] = ['$lt' => $fechaReferencia];
    }
    if ($ciclo >= 0) {
        $condicion["periodo"] = $ciclo;
    }

    $mongo = new MYMONGODB();
    $mongo->buscar(COLECCION_PERIODOS, $condicion, [], ["fecha" => -1], 1);
    return $mongo->siguiente() ?: null;
}

/**
 * Lista los ciclos del período elegido para el select de ciclo del front.
 * Sin período elegido se listan los ciclos del período actual de la cartera.
 * @param string $filtroCartera Id de la cartera
 * @param string $filtroTiempo "asigActual" | "asigAnterior" | "ini"
 * @return array<int, array{id:int, nombre:string}>
 */
function obtenerCiclosPeriodoAsignacion(string $filtroCartera, string $filtroTiempo): array
{
    $ciclos = [["id" => CICLO_TODOS, "nombre" => "Todos los ciclos"]];
    if ($filtroCartera === "" || $filtroCartera === "todo") {
        return $ciclos;
    }
    if (!esFiltroPeriodoAsignacion($filtroTiempo)) {
        $filtroTiempo = "asigActual";
    }

    $periodo = buscarPeriodoAsignacion($filtroCartera, $filtroTiempo, CICLO_TODOS);
    if ($periodo === null) {
        return $ciclos;
    }

    // Los ciclos del período son los registros de la cartera con su misma
    // fecha de inicio y el mismo estado (activo/cerrado).
    $condicion = [
        "cartera" => intval($filtroCartera),
        "activo" => intval($periodo["activo"]),
        "fecha" => intval($periodo["fecha"])
    ];
    $mongo = new MYMONGODB();
    $mongo->buscar(COLECCION_PERIODOS, $condicion, [], ["periodo" => 1]);

    $vistos = [];
    while ($row = $mongo->siguiente()) {
        $idCiclo = intval($row["periodo"]);
        if (isset($vistos[$idCiclo])) {
            continue;
        }
        $vistos[$idCiclo] = true;
        $ciclos[] = [
            "id" => $idCiclo,
            "nombre" => "Ciclo " . $idCiclo . " - " . date("d/m/Y", intval($row["fecha"]))
        ];
    }
    return $ciclos;
}

/**
 * Agrega a la condición de Mongo el filtro de ciclo, si se eligió uno.
 * @param array $condicion Condición a completar (por referencia)
 * @param string $campo Campo de ciclo del cubo (cubAG_ciclo / cubGC_ciclo)
 * @param array $periodoAsignacion Resultado de obtenerPeriodoAsignacionDesde()
 * @return void
 */
function aplicaCondicionCiclo(array &$condicion, string $campo, array $periodoAsignacion): void
{
    $ciclo = intval($periodoAsignacion["ciclo"] ?? CICLO_TODOS);
    if ($ciclo < 0) {
        return;
    }
    $condicion[$campo] = $ciclo;
}

// -----------------------------------------------------------------------
// Efectividad de cobranza.
//   Compromiso de Pago = CONTACTO DIRECTO + una de estas tipificaciones 2.
//   Pagados            = compromiso de pago cuya factura tiene pagos en el
//                        período (cubAG_montoTotalPago > 0).
// -----------------------------------------------------------------------

/**
 * Tipificaciones 2 que cuentan como compromiso de pago.
 * Debe ser la misma lista que tipificacionesCompromisoPago() de
 * cmDashboardGestionesCtrl.php y guardaGestionesDiarias.php.
 * @return string[]
 */
function tipificacionesCompromisoPago(): array
{
    return ['PAGA EN FECHA', 'ABONO CUOTA', 'CONFIRMACION DE PAGO', 'NEGOCIACION EN CURSO ALIVIO', 'COMPROMISO DE PAGO'];
}

/**
 * Facturas de la cartera con pagos en el período elegido (cubAG_montoTotalPago > 0),
 * respetando producto, ciclo y fecha de carga. Sin cartera devuelve [].
 * @param array $origen $d (jsonStart) o $_GET
 * @return string[] Valores de cubAG_numFactura
 */
function obtenerFacturasPagadasDesde(array $origen): array
{
    $filtroCartera = expect_safe_html($origen["filtroCartera"] ?? "");
    if ($filtroCartera === "" || $filtroCartera === "todo") {
        return [];
    }
    $filtroProducto = expect_safe_html($origen["filtroProducto"] ?? "");
    $periodoAsignacion = obtenerPeriodoAsignacionDesde($origen);

    $condicion = [
        "cubAG_carteraId" => strval($filtroCartera),
        "cubAG_montoTotalPago" => ['$gt' => 0]
    ];
    if ($periodoAsignacion["desde"] > 0) {
        $condicion["cubAG_fechaPeriodo"] = ['$gte' => $periodoAsignacion["desde"], '$lte' => $periodoAsignacion["hasta"]];
    }
    aplicaCondicionCiclo($condicion, "cubAG_ciclo", $periodoAsignacion);
    if ($filtroProducto !== "" && $filtroProducto !== "todo") {
        $condicion["cubAG_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
    }
    aplicaCondicionFechaCarga($condicion, $origen["filtroFechaCarga"] ?? "todo", "cubAG_fechaCarga");

    $mongo = new MYMONGODB();
    $facturas = $mongo->buscarDistinct("cubAG_numFactura", "cuAsignacionesGestionAP", $condicion);
    return array_values(array_map('strval', $facturas ?: []));
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

// -----------------------------------------------------------------------
// Filtro de canal (AV / WHATSAPP / EMAIL), compartido entre los endpoints
// de detalle de Contactabilidad, Efectividad, Horarios e Intensidad.
// El front manda los MISMOS valores que se guardan en cubAG_canal (AV,
// EMAIL, WHATSAPP). cuGestionCobranzaMysql en cambio guarda cada gestión
// individual con cubGC_canal = TELEFONICA/EMAIL/WHATSAPP, así que ahí sí
// hay que traducir AV -> TELEFONICA.
// -----------------------------------------------------------------------
function mapaCanalGestionCobranza()
{
    return [
        'AV' => 'TELEFONICA',
        'EMAIL' => 'EMAIL',
        'WHATSAPP' => 'WHATSAPP',
    ];
}

// Valor de cubGC_canal (TELEFONICA/EMAIL/WHATSAPP) a aplicar sobre
// cuGestionCobranzaMysql, o "" si no hay filtro de canal válido.
function obtenerCanalGestionDesde($filtroCanalRaw)
{
    $filtroCanal = trim((string) $filtroCanalRaw);
    $mapa = mapaCanalGestionCobranza();
    return ($filtroCanal !== "" && $filtroCanal !== "todo" && isset($mapa[$filtroCanal]))
        ? $mapa[$filtroCanal]
        : "";
}

// Valor de cubAG_canal (AV/EMAIL/WHATSAPP, SIN traducir) a aplicar sobre
// cuAsignacionesGestionAP, o "" si no hay filtro de canal válido.
function obtenerCanalAsignacionDesde($filtroCanalRaw)
{
    $filtroCanal = trim((string) $filtroCanalRaw);
    $mapa = mapaCanalGestionCobranza();
    return ($filtroCanal !== "" && $filtroCanal !== "todo" && isset($mapa[$filtroCanal]))
        ? $filtroCanal
        : "";
}

// Gestionado/sin gestión para GestionBase. Con canal, usa la MEJOR GESTIÓN
// DE ESE CANAL (cubAG_mejorGestion.<canal>), no la mejor gestión general.
function aplicaCondicionGestionadoPorCanal(&$condicion, $canalAsignacion, $gestionado)
{
    if ($canalAsignacion !== "") {
        $campo = "cubAG_mejorGestion." . $canalAsignacion . ".cuGestionId";
        // $ne solo no basta: en Mongo, un campo INEXISTENTE tambien matchea
        // $ne "", asi que un cliente nunca gestionado (sin ese sub-objeto)
        // se contaba como gestionado. Exigimos que el campo SI exista.
        if ($gestionado) {
            $condicion[$campo] = ['$exists' => true, '$ne' => ""];
        } else {
            $condicion['$or'] = [
                [$campo => ['$exists' => false]],
                [$campo => ""],
            ];
        }
        return;
    }
    $condicion["cubAG_gestionada"] = $gestionado ? 1 : 0;
}

// -----------------------------------------------------------------------
// Filtro de "Fecha de Carga" (cubAG_fechaCarga). Este dato SOLO existe en
// cuAsignacionesGestionAP (una fila por cliente/operación asignada);
// cuGestionCobranzaMysql (una fila por gestión/contacto individual) no lo guarda.
// Por eso, para acotar GESTIONES por fecha de carga hay que:
//   1) Buscar en cuAsignacionesGestionAP qué operaciones/facturas
//      (cubAG_numFactura) se cargaron esa fecha (respetando cartera,
//      producto y período vigentes).
//   2) Filtrar cuGestionCobranzaMysql por cubGC_numFactura IN (esas facturas).
// Sobre cuAsignacionesGestionAP en cambio se aplica directo
// (cubAG_fechaCarga), sin necesidad de este cruce.
// -----------------------------------------------------------------------
function aplicaCondicionFechaCarga(&$condicion, $filtroFechaCargaRaw, $campoFecha)
{
    $filtroFechaCarga = trim((string) $filtroFechaCargaRaw);
    if ($filtroFechaCarga === "" || $filtroFechaCarga === "todo") {
        return false;
    }

    $desdeCarga = strtotime(date('Y-m-d', (int) $filtroFechaCarga) . " 00:00:00");
    $hastaCarga = strtotime(date('Y-m-d', (int) $filtroFechaCarga) . " 23:59:59");
    $condicion[$campoFecha] = ['$gte' => $desdeCarga, '$lte' => $hastaCarga];
    return true;
}

// Devuelve el arreglo de cubAG_numFactura cargados en $filtroFechaCargaRaw
// (respetando cartera/producto/período), o null si no hay filtro de fecha
// de carga válido (para que el llamador sepa que no debe restringir nada).
function obtenerFacturasPorFechaCarga($filtroCartera, $filtroProducto, $desdePeriodo, $hastaPeriodo, $filtroFechaCargaRaw)
{
    $filtroFechaCarga = trim((string) $filtroFechaCargaRaw);
    if ($filtroFechaCarga === "" || $filtroFechaCarga === "todo") {
        return null;
    }

    $condicion = establecerCondicionMaster("cubAG_carteraId");
    if (!empty($condicion["cubAG_carteraId"]['$in'])) {
        $condicion["cubAG_carteraId"]['$in'] = array_map('strval', $condicion["cubAG_carteraId"]['$in']);
    }

    if ($filtroCartera !== "" && $filtroCartera !== "todo") {
        $condicion["cubAG_carteraId"] = strval($filtroCartera);
    } else {
        $condicion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
    }

    if ($filtroProducto !== "" && $filtroProducto !== "todo") {
        $condicion["cubAG_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
    }

    if (!empty($desdePeriodo) || !empty($hastaPeriodo)) {
        $condicion["cubAG_fechaPeriodo"] = ['$gte' => $desdePeriodo, '$lte' => $hastaPeriodo];
    }

    aplicaCondicionFechaCarga($condicion, $filtroFechaCarga, "cubAG_fechaCarga");

    $mdb = new MYMONGODB();
    $mdb->buscar('cuAsignacionesGestionAP', $condicion, ['cubAG_numFactura']);

    $facturas = [];
    while ($row = $mdb->siguiente()) {
        if (!empty($row['cubAG_numFactura'])) {
            $facturas[] = $row['cubAG_numFactura'];
        }
    }
    return array_values(array_unique($facturas));
}

// WHATSAPP CON ERROR / WHATSAPP NO ENVIADO no fueron intentos reales de
// gestion; se excluyen de todas las graficas y detalles de cuGestionCobranzaMysql.
function excluirWhatsappInvalido($condicion)
{
    // $and en vez de llave plana: si una pestaña ya tiene un filtro de
    // columna sobre este mismo campo (mejorGestion), el merge de la tabula
    // no debe pisarlo (ver mezclaCriterios en coTabulaMongo).
    $condicion['$and'][] = ['cubGC_tipificacion_respuesta2' => ['$nin' => ['WHATSAPP CON ERROR', 'WHATSAPP NO ENVIADO']]];
    return $condicion;
}

function construirCondicionBaseGestion()
{
    $filtroCartera = expect_safe_html($_GET["filtroCartera"] ?? "");
    $filtroProducto = expect_safe_html($_GET["filtroProducto"] ?? "");
    $filtroTiempo = expect_safe_html($_GET["filtroTiempo"] ?? "");
    $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
    $filtroCanal = expect_safe_html($_GET["filtroCanal"] ?? "todo");
    $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
    $filtroLeido = expect_safe_html($_GET["filtroLeido"] ?? "todo");

    $condicionCubGestion = establecerCondicionMaster("cubGC_carteraId");

    if (!empty($condicionCubGestion["cubGC_carteraId"]['$in'])) {
        $condicionCubGestion["cubGC_carteraId"]['$in'] = array_map('strval', $condicionCubGestion["cubGC_carteraId"]['$in']);
    }

    $desdePeriodo = 0;
    $hastaPeriodo = 0;

    $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
    if (esFiltroPeriodoAsignacion($filtroTiempo)) {
        $desde = 0;
        $hasta = 0;
        $desde = $periodoAsignacion["desde"];
        $hasta = $periodoAsignacion["hasta"];
        // $and en vez de llave plana: mismo motivo que en excluirWhatsappInvalido,
        // para no pisar un filtro de columna sobre fechaMostrar (cubGC_fechaGestion).
        $condicionCubGestion['$and'][] = ['cubGC_fechaGestion' => ['$gte' => $desde, '$lte' => $hasta]];
        $desdePeriodo = $desde;
        $hastaPeriodo = $hasta;
    }
    aplicaCondicionCiclo($condicionCubGestion, "cubGC_ciclo", $periodoAsignacion);

    if ($filtroCartera != "" && $filtroCartera != "todo") {
        $condicionCubGestion["cubGC_carteraId"] = strval($filtroCartera);
    } else {
        $condicionCubGestion["cubGC_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
    }

    if ($filtroProducto != "" && $filtroProducto != "todo") {
        $condicionCubGestion["cubGC_producto"] = [
            '$regex' => '^' . $filtroProducto . '$',
            '$options' => 'i'
        ];
    }

    if ($buscarCliente != "") {
        $condicionCubGestion['$or'] = [
            ['cubGC_numFactura' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
            ['cubGC_nombres' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
            ['cubGC_apellidos' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
        ];
    }

    // Filtro de canal (AV/WHATSAPP/EMAIL -> cubGC_canal). En las listas de
    // Intensidad (Call Center/WhatsApp/Correo) el caso que llama a esta
    // función sobreescribe cubGC_canal justo después con el canal fijo de
    // la pestaña activa, así que ahí manda la pestaña, no este filtro.
    $canalGestion = obtenerCanalGestionDesde($filtroCanal);
    if ($canalGestion !== "") {
        $condicionCubGestion["cubGC_canal"] = $canalGestion;
    }

    // Select de "Leido" (solo tiene sentido para EMAIL, es el unico canal con
    // lectura registrada): se exige canal EMAIL para no mezclar con AV/WHATSAPP,
    // que siempre tienen cubGC_cantidad_abierto en 0.
    if ($filtroLeido === "si") {
        $condicionCubGestion['$and'][] = ['cubGC_canal' => 'EMAIL'];
        $condicionCubGestion['$and'][] = ['cubGC_cantidad_abierto' => ['$gt' => 0]];
    } elseif ($filtroLeido === "no") {
        $condicionCubGestion['$and'][] = ['cubGC_canal' => 'EMAIL'];
        $condicionCubGestion['$and'][] = ['cubGC_cantidad_abierto' => ['$lte' => 0]];
    }

    // Fecha de Carga: cuGestionCobranzaMysql no la guarda, así que se resuelve vía
    // las facturas cargadas esa fecha en cuAsignacionesGestionAP.
    $facturasFechaCarga = obtenerFacturasPorFechaCarga($filtroCartera, $filtroProducto, $desdePeriodo, $hastaPeriodo, $filtroFechaCarga);
    if ($facturasFechaCarga !== null) {
        $condicionCubGestion["cubGC_numFactura"] = ['$in' => $facturasFechaCarga];
    }

    return excluirWhatsappInvalido($condicionCubGestion);
}


function construirCondicionContactabilidadGestion($tipificacionValor)
{
    $condicionCubGestion = construirCondicionBaseGestion();
    // $and en vez de llave plana: mismo motivo que en excluirWhatsappInvalido,
    // para no pisar un filtro de columna sobre tipificacion (respuesta1).
    $condicionCubGestion['$and'][] = ['cubGC_tipificacion_respuesta1' => $tipificacionValor];
    return $condicionCubGestion;
}

// Cuenta clientes (cubGC_numFactura) unicos por cada valor de $campoCategoria:
// agrupa primero por (categoria, factura) para deduplicar, y despues cuenta
// esos grupos por categoria. Sirve para las tarjetas KPI, donde el numero
// grande es gestiones pero el chico debe ser clientes unicos, no gestiones.
function contarClientesUnicosPorCategoria($condicion, $campoCategoria)
{
    $mdb = new MYMONGODB();
    $pipeline = [
        ['$match' => $condicion],
        ['$group' => ['_id' => ['cat' => '$' . $campoCategoria, 'factura' => '$cubGC_numFactura']]],
        ['$group' => ['_id' => '$_id.cat', 'total' => ['$sum' => 1]]],
    ];
    $mdb->aggregate("cuGestionCobranzaMysql", $pipeline);
    $resultado = [];
    while ($row = $mdb->siguiente()) {
        $resultado[$row['_id']] = (int) $row['total'];
    }
    return $resultado;
}

// alias publico (tabula-encabezado) => campo real en cuGestionCobranzaMysql, para que
// coTabulaMongo pueda ordenar/filtrar sobre los alias que arma normalizaFilaContactabilidadGestion.
// nombreCliente no es un campo real (es nombres+apellidos concatenados), asi que
// se mapea a los dos campos separados por espacio: coTabulaMongo ya soporta eso.
function limpiadorContactabilidadGestion()
{
    return [
        "cubGC_numFactura" => "operacion",
        "cubGC_nombres cubGC_apellidos" => "nombreCliente",
        "cubGC_telefono" => "telefono",
        "cubGC_email" => "correo",
        "cubGC_campaniaNombre" => "campania",
        "cubGC_tipificacion_respuesta1" => "tipificacion",
        "cubGC_tipificacion_respuesta2" => "mejorGestion",
        "cubGC_fechaGestion" => "fechaMostrar",
        "cubGC_canal" => "canal",
        "cubGC_cantidad_abierto" => "leido",
    ];
}

// Mismo proposito, para los alias que arma normalizaFilaIntensidadCanal.
function limpiadorIntensidadCanal()
{
    return [
        "cubGC_numFactura" => "operacion",
        "cubGC_nombres cubGC_apellidos" => "nombreCliente",
        "cubGC_telefono" => "telefono",
        "cubGC_email" => "correo",
        "cubGC_campaniaNombre" => "campania",
        "cubGC_fechaGestion" => "fechaMostrar",
        "cubGC_tipificacion_respuesta1" => "estado",
        "cubGC_cantidad_abierto" => "leido",
    ];
}

function normalizaFilaContactabilidadGestion($campos)
{
    // Nombre del cliente en mayusculas
    $nombreCliente = strtoupper(trim(($campos['cubGC_nombres'] ?? '') . ' ' . ($campos['cubGC_apellidos'] ?? '')));
    $campos['operacion'] = $campos['cubGC_numFactura'] ?? '';
    $campos['nombreCliente'] = $nombreCliente;
    $campos['telefono'] = $campos['cubGC_telefono'] ?? '';
    $campos['correo'] = $campos['cubGC_email'] ?? '';
    $campos['campania'] = $campos['cubGC_campaniaNombre'] ?? '';
    $campos['fechaMostrar'] = $campos['cubGC_fechaGestion'] ?? null;
    $campos['tipificacion'] = $campos['cubGC_tipificacion_respuesta1'] ?? '';
    $campos['mejorGestion'] = $campos['cubGC_tipificacion_respuesta2'] ?? '';
    // avId: si viene seteado, esta gestion tiene detalle en avProgramadas (llamada)
    // o avProgramadasWhatsApp (chat), segun cubGC_canal.
    $campos['avId'] = objectIdComoTexto($campos['cubGC_avId'] ?? '');
    $campos['canal'] = $campos['cubGC_canal'] ?? '';
    // Leido: mismo criterio que obtenerOperativoGestionCobranzaPorFactura (correosLeidos).
    $campos['leido'] = (($campos['cubGC_cantidad_abierto'] ?? 0) > 0);
    return $campos;
}

function normalizaFilaIntensidadCanal($campos)
{
    // Nombre del cliente en mayusculas
    $nombreCliente = strtoupper(trim(($campos['cubGC_nombres'] ?? '') . ' ' . ($campos['cubGC_apellidos'] ?? '')));
    $tipificacion = $campos['cubGC_tipificacion_respuesta1'] ?? '';

    $campos['operacion'] = $campos['cubGC_numFactura'] ?? '';
    $campos['nombreCliente'] = $nombreCliente;
    $campos['telefono'] = $campos['cubGC_telefono'] ?? '';
    $campos['correo'] = $campos['cubGC_email'] ?? '';
    $campos['campania'] = $campos['cubGC_campaniaNombre'] ?? '';
    $campos['fechaMostrar'] = $campos['cubGC_fechaGestion'] ?? null;
    $campos['estado'] = $tipificacion;
    // avId: si viene seteado, esta gestion tiene detalle en avProgramadas (llamada)
    // o avProgramadasWhatsApp (chat), segun cubGC_canal.
    $campos['avId'] = objectIdComoTexto($campos['cubGC_avId'] ?? '');
    $campos['canal'] = $campos['cubGC_canal'] ?? '';
    // Leido: mismo criterio que obtenerOperativoGestionCobranzaPorFactura (correosLeidos).
    $campos['leido'] = (($campos['cubGC_cantidad_abierto'] ?? 0) > 0);
    return $campos;
}

// cubGC_avId llega como objeto MongoDB\BSON\ObjectId (o array {"$oid":...} si ya
// paso por json_decode); lo pasamos a texto plano para poder mandarlo al front
// y volver a convertirlo con String2MongoId sin que explote.
function objectIdComoTexto($valor)
{
    if ($valor === '' || $valor === null) {
        return '';
    }
    if (is_array($valor)) {
        return $valor['$oid'] ?? '';
    }
    return (string) $valor;
}

// Condicion de "No Contactado" (cuAsignacionesGestionAP, cubAG_gestionada=0),
// igual a la de listaNoContactado, para que el Excel exporte lo mismo que la tabla.
function construirCondicionNoContactado()
{
    $filtroCartera = expect_safe_html($_GET["filtroCartera"] ?? "");
    $filtroProducto = expect_safe_html($_GET["filtroProducto"] ?? "");
    $filtroTiempo = expect_safe_html($_GET["filtroTiempo"] ?? "");
    $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
    $filtroCanal = expect_safe_html($_GET["filtroCanal"] ?? "todo");
    $canalAsignacion = obtenerCanalAsignacionDesde($filtroCanal);
    $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
    $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

    if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
        $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
    }

    $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
    if (esFiltroPeriodoAsignacion($filtroTiempo)) {
        $desde = 0;
        $hasta = 0;
        $desde = $periodoAsignacion["desde"];
        $hasta = $periodoAsignacion["hasta"];
        $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
    }
    aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

    if ($filtroCartera != "" && $filtroCartera != "todo") {
        $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
    } else {
        $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
    }

    if ($filtroProducto != "" && $filtroProducto != "todo") {
        $condicionCubAsignacion["cubAG_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
    }

    if ($canalAsignacion !== "") {
        $condicionCubAsignacion["cubAG_canal"] = $canalAsignacion;
    }

    aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

    if ($buscarCliente != "") {
        $condicionCubAsignacion['$or'] = [
            ['cubAG_numFactura' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
            ['cubAG_nombres' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
            ['cubAG_apellidos' => ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i']],
        ];
    }

    $condicionCubAsignacion["cubAG_gestionada"] = 0;

    return $condicionCubAsignacion;
}

function normalizaFilaNoContactado($campos)
{
    $nombreCliente = strtoupper(trim(($campos['cubAG_nombres'] ?? '') . ' ' . ($campos['cubAG_apellidos'] ?? '')));
    $campos['operacion'] = $campos['cubAG_numFactura'] ?? '';
    $campos['nombreCliente'] = $nombreCliente;
    $campos['campania'] = $campos['cubAG_producto'] ?? '';
    $campos['fechaMostrar'] = $campos['cubAG_fechaCarga'] ?? null;
    return $campos;
}

// Condicion base para las 3 listas de GestionBase (cuAsignacionesGestionAP),
// igual a la que arma cada case listaBaseAsignada/ClientesGestionados/SinGestion.
function construirCondicionAsignacionGestionBase()
{
    $filtroCartera = expect_safe_html($_GET["filtroCartera"] ?? "");
    $filtroProducto = expect_safe_html($_GET["filtroProducto"] ?? "");
    $filtroTiempo = expect_safe_html($_GET["filtroTiempo"] ?? "");
    $buscarCliente = expect_safe_html($_GET["buscarCliente"] ?? "");
    $filtroFechaCarga = expect_safe_html($_GET["filtroFechaCarga"] ?? "todo");
    $condicionCubAsignacion = establecerCondicionMaster("cubAG_carteraId");

    if (!empty($condicionCubAsignacion["cubAG_carteraId"]['$in'])) {
        $condicionCubAsignacion["cubAG_carteraId"]['$in'] = array_map('strval', $condicionCubAsignacion["cubAG_carteraId"]['$in']);
    }

    $periodoAsignacion = obtenerPeriodoAsignacionDesde($_GET);
    if (esFiltroPeriodoAsignacion($filtroTiempo)) {
        $desde = 0;
        $hasta = 0;
        $desde = $periodoAsignacion["desde"];
        $hasta = $periodoAsignacion["hasta"];
        $condicionCubAsignacion["cubAG_fechaPeriodo"] = ['$gte' => $desde, '$lte' => $hasta];
    }
    aplicaCondicionCiclo($condicionCubAsignacion, "cubAG_ciclo", $periodoAsignacion);

    if ($filtroCartera != "" && $filtroCartera != "todo") {
        $condicionCubAsignacion["cubAG_carteraId"] = strval($filtroCartera);
    } else {
        $condicionCubAsignacion["cubAG_carteraId"] = "__NINGUNA_CARTERA_SELECCIONADA__";
    }

    if ($filtroProducto != "" && $filtroProducto != "todo") {
        $condicionCubAsignacion["cubAG_producto"] = ['$regex' => '^' . $filtroProducto . '$', '$options' => 'i'];
    }

    if ($buscarCliente != "") {
        $condicionCubAsignacion["cubAG_numFactura"] = ['$regex' => preg_quote($buscarCliente, '/'), '$options' => 'i'];
    }

    aplicaCondicionFechaCarga($condicionCubAsignacion, $filtroFechaCarga, "cubAG_fechaCarga");

    return $condicionCubAsignacion;
}

function normalizaFilaClientesGestionados($campos, $canalAsignacion = "", $operativoPorFactura = [])
{
    $campos['cubAG_estado'] = "Gestionado";
    // Con canal, la fecha exportada es la de LA GESTIÓN DE ESE CANAL.
    if ($canalAsignacion !== "" && !empty($campos['cubAG_mejorGestion'][$canalAsignacion]['fechaGestion'])) {
        $campos['cubAG_fechaGestion'] = $campos['cubAG_mejorGestion'][$canalAsignacion]['fechaGestion'];
    }

    $clave = claveOperativoGestionCobranza(
        $campos['cubAG_numFactura'] ?? '',
        $campos['cubAG_carteraId'] ?? '',
        $campos['cubAG_fechaPeriodo'] ?? 0
    );
    $campos['_operativo'] = $operativoPorFactura[$clave] ?? [];

    return $campos;
}

// Clave compartida entre cuAsignacionesGestionAP y cuGestionCobranzaMysql para
// no mezclar gestiones de otro periodo con el mismo numero de factura.
function claveOperativoGestionCobranza($factura, $carteraId, $fechaPeriodo)
{
    return $factura . '|' . $carteraId . '|' . $fechaPeriodo;
}

// Totales de llamadas/whatsapp/correo (cuGestionCobranzaMysql) por factura+cartera+
// periodo, para las columnas operativas del Excel de Clientes Gestionados.
// Usa los mismos filtros vigentes (cartera/producto/fecha de gestion/carga)
// que el resto del reporte, sin restringir canal (se quieren los 3).
function obtenerOperativoGestionCobranzaPorFactura($filasAsignacion)
{
    if (empty($filasAsignacion)) {
        return [];
    }

    $facturas = [];
    foreach ($filasAsignacion as $fila) {
        if (!empty($fila['cubAG_numFactura'])) {
            $facturas[] = $fila['cubAG_numFactura'];
        }
    }
    $facturas = array_values(array_unique($facturas));
    if (empty($facturas)) {
        return [];
    }

    $condicion = construirCondicionBaseGestion();
    // Los totales operativos son siempre de los 3 canales; si hay un
    // filtroCanal activo en pantalla, no debe restringir esta consulta.
    unset($condicion['cubGC_canal']);
    $condicion['cubGC_numFactura'] = ['$in' => $facturas];

    $mdb = new MYMONGODB();
    $mdb->buscar('cuGestionCobranzaMysql', $condicion, [
        'cubGC_numFactura', 'cubGC_carteraId', 'cubGC_fechaPeriodo',
        'cubGC_canal', 'cubGC_tipificacion_respuesta1', 'cubGC_tipificacion_respuesta2',
        'cubGC_cantidad_abierto',
    ]);

    $porFactura = [];
    while ($row = $mdb->siguiente()) {
        $clave = claveOperativoGestionCobranza(
            $row['cubGC_numFactura'] ?? '',
            $row['cubGC_carteraId'] ?? '',
            $row['cubGC_fechaPeriodo'] ?? 0
        );
        if (!isset($porFactura[$clave])) {
            $porFactura[$clave] = [
                'totalLlamadas' => 0, 'llamadasAtendidas' => 0, 'llamadasNoAtendidas' => 0,
                'totalWhatsapp' => 0, 'whatsappAtendidos' => 0, 'whatsappNoAtendidos' => 0,
                'totalCorreos' => 0, 'correosEnviados' => 0, 'correosLeidos' => 0, 'correosNoEnviados' => 0,
            ];
        }

        $canal = $row['cubGC_canal'] ?? '';
        $r1 = $row['cubGC_tipificacion_respuesta1'] ?? '';
        $r2 = $row['cubGC_tipificacion_respuesta2'] ?? '';

        if ($canal === 'TELEFONICA') {
            $porFactura[$clave]['totalLlamadas']++;
            if (in_array($r1, ['CONTACTO DIRECTO', 'CONTACTO INDIRECTO'], true)) {
                $porFactura[$clave]['llamadasAtendidas']++;
            } elseif ($r1 === 'SIN CONTACTO') {
                $porFactura[$clave]['llamadasNoAtendidas']++;
            }
        } elseif ($canal === 'WHATSAPP') {
            // Con error/no enviado no cuenta ni como total: no fue un
            // intento real de gestion por whatsapp.
            if (!in_array($r2, ['WHATSAPP CON ERROR', 'WHATSAPP NO ENVIADO'], true)) {
                $porFactura[$clave]['totalWhatsapp']++;
                if ($r1 === 'CONTACTO DIRECTO' && in_array($r2, ['WHATSAPP LEIDO', 'WHATSAPP RESPONDIDO'], true)) {
                    $porFactura[$clave]['whatsappAtendidos']++;
                } elseif (in_array($r2, ['WHATSAPP ENVIADO', 'WHATSAPP NO LEIDO'], true)) {
                    $porFactura[$clave]['whatsappNoAtendidos']++;
                }
            }
        } elseif ($canal === 'EMAIL') {
            $porFactura[$clave]['totalCorreos']++;
            if ($r1 === 'CONTACTO DIRECTO' && $r2 === 'Mail Enviado') {
                $porFactura[$clave]['correosEnviados']++;
            } elseif ($r1 === 'SIN CONTACTO' && $r2 === 'Mail No Enviado') {
                $porFactura[$clave]['correosNoEnviados']++;
            }
            // Independiente de la tipificacion: cuenta si el correo fue abierto.
            if ((int) ($row['cubGC_cantidad_abierto'] ?? 0) > 0) {
                $porFactura[$clave]['correosLeidos']++;
            }
        }
    }

    return $porFactura;
}

function normalizaFilaClientesSinGestion($campos)
{
    $campos['cubAG_estado'] = "Sin gestión";
    return $campos;
}

// Trae solo audio y transcripcion de una llamada, a partir del cubGC_avId
// guardado en cuGestionCobranzaMysql (subset de obtenerDetalleConversacion, que usa
// av_idConversacion en vez de _id de avProgramadas).
// Comparte el chequeo de permisos entre el detalle de llamadas (avProgramadas)
// y el de chats de WhatsApp (avProgramadasWhatsApp): ambos guardan su avId en
// el mismo campo cubGC_avId de cuGestionCobranzaMysql.
function usuarioTienePermisoSobreAvId($avId)
{
    $condicionPermiso = establecerCondicionMaster("cubGC_carteraId");
    $mongoPermiso = new MYMONGODB();
    $condicionPermiso["cubGC_avId"] = $mongoPermiso->String2MongoId($avId);
    return $mongoPermiso->buscar("cuGestionCobranzaMysql", $condicionPermiso, [], [], 1) > 0;
}

function obtenerAudioTranscripcionPorAvId($avId)
{
    // Si algo mando el avId sin convertir (objeto/arreglo en vez de texto),
    // mejor un error controlado que un fatal de MongoDB\BSON\ObjectId.
    if (!is_string($avId) || $avId === "") {
        return ["error" => "No existe el detalle de la llamada (001)"];
    }

    if (!usuarioTienePermisoSobreAvId($avId)) {
        return ["error" => "No tiene permisos sobre esta llamada"];
    }

    $mongo = new MYMONGODB();
    if ($mongo->buscar("avProgramadas", ["_id" => $mongo->String2MongoId($avId)], [], [], 1) <= 0) {
        return ["error" => "No existe el detalle de la llamada (001)"];
    }
    $r = $mongo->siguiente();
    $conv = $r["av_idConversacion"];

    $coleccion = $r["av_proveedor"] == "RETELL" ? "avDetalleConversacionesRetell" : ($r["av_proveedor"] == "ELEVENLABS" ? "avDetalleConversaciones" : "avDetalleConversacionesLink");

    if ($mongo->buscar($coleccion, ["idConversacion" => $conv]) <= 0) {
        return ["error" => "No existe el detalle de la llamada (002)"];
    }

    while ($row = $mongo->siguiente()) {
        if (isset($row["archivoAudio"]) && ($row["archivoAudio"] != "" || $r["av_proveedor"] == "RETELL")) {
            foreach ($row["transcripcion"] as $key => $value) {
                if (isset($value["tiempoTranscurridoSegundos"]) && $value["tiempoTranscurridoSegundos"] != "") {
                    $row["transcripcion"][$key]["fechaHora"] = date("H:i:s", (strlen($row["fechaInicio"]) > 10 ? ($row["fechaInicio"] / 1000) + $value["tiempoTranscurridoSegundos"] : $row["fechaInicio"] + $value["tiempoTranscurridoSegundos"]));
                }
                $row["transcripcion"][$key]["mensaje"] = utf8_2_encode($value["mensaje"]);
            }

            return [
                "audio" => str_replace(BASEFOLDER, BASEURL, str_replace("\\", "", $row["archivoAudio"])),
                "transcripcion" => base64_encode(json_encode(utf8_converter($row["transcripcion"]))),
                "telefono" => $row["telefono"],
            ];
        }
    }

    return ["error" => "No existe el detalle de la llamada (002)"];
}

/**
 * Mensaje de plantilla con el que arranca el chat de WhatsApp (ws_plantilla y
 * ws_fecha de avProgramadasWhatsApp). Se envia siempre, responda o no el cliente.
 * @param array|\ArrayAccess $r Documento de avProgramadasWhatsApp
 * @return array<int, array{direccion:string, mensaje:string, fechaHora:string}> Vacio si no hay plantilla
 */
function mensajePlantillaWhatsapp(array|\ArrayAccess $r): array
{
    $plantilla = (string) ($r["ws_plantilla"] ?? "");
    if ($plantilla === "") {
        return [];
    }
    return [[
        "direccion" => "agente",
        "mensaje" => $plantilla,
        "fechaHora" => !empty($r["ws_fecha"]) ? date("d/m/Y H:i.s", (int) $r["ws_fecha"]) : "",
    ]];
}

// Trae solo la transcripcion de un chat de WhatsApp, a partir del cubGC_avId
// guardado en cuGestionCobranzaMysql (subset de cmReporteAgenteVirtualWhatsappCtrl.php
// -> obtenerDetalleChat, que ademas trae resumen/costo/analisis que aqui no hacen falta).
function obtenerTranscripcionWhatsappPorAvId($avId)
{
    if (!is_string($avId) || $avId === "") {
        return ["error" => "No existe el detalle del chat (001)"];
    }

    if (!usuarioTienePermisoSobreAvId($avId)) {
        return ["error" => "No tiene permisos sobre este chat"];
    }

    $mongo = new MYMONGODB();
    if ($mongo->buscar("avProgramadasWhatsApp", ["_id" => $mongo->String2MongoId($avId)], [], [], 1) <= 0) {
        return ["error" => "No existe el detalle del chat (001)"];
    }
    $r = $mongo->siguiente();

    // ws_tieneTranscripcion no es confiable (queda en 0 aun en chats ya
    // contestados con conversacion real); el indicador valido es que exista
    // ws_idConversacion. Si el cliente nunca respondio, ese campo no existe y no
    // hay chat que pedir al proveedor: se muestra solo el mensaje enviado.
    if (empty($r["ws_idConversacion"] ?? "")) {
        return [
            "transcripcion" => base64_encode(json_encode(utf8_converter(mensajePlantillaWhatsapp($r)))),
            "telefono" => $r["ws_telefono"] ?? "",
        ];
    }

    $idChat = $r["ws_idConversacion"];

    $mongo2 = new MYMONGODB();
    $mongo2->buscar("avParametros", ["_id" => $r["ws_agenteParametroId"]]);
    $agente = $mongo2->siguientex();
    $proveedor = $agente["proveedor"] ?? "";

    $transcripcion = [];

    if ($proveedor == "LINK") {
        require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
        $linkAPI = new linkLocalLlmAPI();
        $resp = $linkAPI->api_obtenerChat($idChat);

        if ($resp["estado"] != "OK") {
            return ["error" => "No existe el detalle del chat (002)"];
        }
        if (isset($resp["datos"]["mensajes"])) {
            foreach ($resp["datos"]["mensajes"] as $value) {
                if ($value["rol"] == "agente" || $value["rol"] == "usuario") {
                    $transcripcion[] = [
                        "direccion" => $value["rol"] == "agente" ? "agente" : "cliente",
                        "mensaje" => utf8_2_decode(base64_decode($value["mensaje"])),
                        "fechaHora" => isset($value["fecha"]) ? date("d/m/Y H:i.s", $value["fecha"]) : "",
                    ];
                }
            }
        }
    } elseif ($proveedor == "RETELL") {
        require_once("../canalesMasivos/apis/class.retellAPI.php");
        $retellAPI = new retellAPI();
        $resp = $retellAPI->api_obtenerDetalleChat($idChat, true);

        if ($resp["estado"] != "OK") {
            return ["error" => "No existe el detalle del chat (002)"];
        }
        $transcripcion = mensajePlantillaWhatsapp($r);
        if (isset($resp["datos"]["message_with_tool_calls"])) {
            foreach ($resp["datos"]["message_with_tool_calls"] as $value) {
                if ($value["role"] == "agent" || $value["role"] == "user") {
                    $transcripcion[] = [
                        "direccion" => $value["role"] == "agent" ? "agente" : "cliente",
                        "mensaje" => utf8_2_decode($value["content"]),
                        "fechaHora" => isset($value["created_timestamp"]) ? date("d/m/Y H:i.s", floatval($value["created_timestamp"]) / 1000) : "",
                    ];
                }
            }
        }
    } else {
        return ["error" => "No existe el detalle del chat (002)"];
    }

    return [
        "transcripcion" => base64_encode(json_encode(utf8_converter($transcripcion))),
        "telefono" => $r["ws_telefono"] ?? "",
    ];
}

// Trae el body html de un correo, a partir del avId guardado en cuGestionCobranzaMysql
// (canal EMAIL -> cbEnvioMails). cem_susUrl, a pesar del nombre, no es una URL:
// trae el html completo del correo en base64, ya con entidades (&oacute;, etc.),
// asi que se manda tal cual al front para decodificar con atob().
function obtenerDetalleCorreoPorAvId($avId)
{
    if (!is_string($avId) || $avId === "") {
        return ["error" => "No existe el detalle del correo (001)"];
    }

    if (!usuarioTienePermisoSobreAvId($avId)) {
        return ["error" => "No tiene permisos sobre este correo"];
    }

    $mongo = new MYMONGODB();
    if ($mongo->buscar("cbEnvioMails", ["_id" => $mongo->String2MongoId($avId)], [], [], 1) <= 0) {
        return ["error" => "No existe el detalle del correo (001)"];
    }
    $r = $mongo->siguiente();

    return [
        "html" => $r["cem_susUrl"] ?? "",
        "destinatario" => $r["cem_susEmail"] ?? "",
    ];
}

// Cambia el prefijo internacional de Ecuador (+593/593) por el 0 local.
// PhpSpreadsheet guarda como texto todo valor puramente numerico que
// empieza en "0", asi se evita que el telefono salga en notacion
// cientifica sin tener que tocar class.coGeneraExcel.php.
function telefonoComoTexto($telefono)
{
    $telefono = trim((string) ($telefono ?? ''));
    if ($telefono === '') {
        return '';
    }
    if (strpos($telefono, '+593') === 0) {
        return '0' . substr($telefono, 4);
    }
    if (strpos($telefono, '593') === 0) {
        return '0' . substr($telefono, 3);
    }
    return $telefono;
}

// Arma (cabeceras, filas) listas para generarExcelGenerico() segun la
// "forma" de cada tipo de exportacion (mismas columnas que su tabla en pantalla).
function armarFilasExcelDetalle($forma, $filas)
{
    // Indices (0-based) de columnas que deben forzarse a texto en Excel
    // (numeros largos como telefono, para que no salgan en notacion cientifica).
    $columnasTexto = [];

    switch ($forma) {

        case "noContactado":
            $cabeceras = ["Nro. Operación", "Nombre Cliente", "Producto", "Fecha Carga"];
            $filasExcel = array_map(function ($f) {
                return [
                    $f['operacion'] ?? '',
                    $f['nombreCliente'] ?? '',
                    $f['campania'] ?? '',
                    !empty($f['fechaMostrar']) ? date('d/m/Y H:i', $f['fechaMostrar']) : '',
                ];
            }, $filas);
            break;

        case "intensidadTelefono":
            $cabeceras = ["Nro. Operación", "Nombre Cliente", "Campaña", "Número Telefónico", "Fecha Gestión", "Estado"];
            $columnasTexto = [3];
            $filasExcel = array_map(function ($f) {
                return [
                    $f['operacion'] ?? '',
                    $f['nombreCliente'] ?? '',
                    $f['campania'] ?? '',
                    telefonoComoTexto($f['telefono'] ?? ''),
                    !empty($f['fechaMostrar']) ? date('d/m/Y H:i', $f['fechaMostrar']) : '',
                    ucwords(strtolower($f['estado'] ?? '')),
                ];
            }, $filas);
            break;

        case "intensidadCorreo":
            $cabeceras = ["Nro. Operación", "Nombre Cliente", "Campaña", "Correo Electrónico", "Fecha Gestión", "Estado", "Leído"];
            $filasExcel = array_map(function ($f) {
                return [
                    $f['operacion'] ?? '',
                    $f['nombreCliente'] ?? '',
                    $f['campania'] ?? '',
                    $f['correo'] ?? '',
                    !empty($f['fechaMostrar']) ? date('d/m/Y H:i', $f['fechaMostrar']) : '',
                    ucwords(strtolower($f['estado'] ?? '')),
                    !empty($f['leido']) ? 'Leído' : 'No leído',
                ];
            }, $filas);
            break;

        case "baseAsignada":
            $cabeceras = ["Cliente", "Producto", "Estado", "Fecha AV", "Fecha Email", "Fecha Whatsapp"];
            $filasExcel = array_map(function ($f) {
                // Estado usa la gestion GENERAL (cubAG_gestionada), no por canal.
                $estado = !empty($f['cubAG_gestionada']) ? "Gestionado" : "No Gestionado";
                $fechaCanal = function ($canal) use ($f) {
                    $fecha = $f['cubAG_mejorGestion'][$canal]['fechaGestion'] ?? '';
                    return !empty($fecha) ? date('d/m/Y H:i', $fecha) : '';
                };
                return [
                    $f['cubAG_numFactura'] ?? '',
                    $f['cubAG_producto'] ?? '',
                    $estado,
                    $fechaCanal('AV'),
                    $fechaCanal('EMAIL'),
                    $fechaCanal('WHATSAPP'),
                ];
            }, $filas);
            break;

        case "clientesGestionados":
            $cabeceras = [
                "Cliente", "Estado",
                "AV - Tipificación 1", "AV - Tipificación 2", "AV - Fecha Gestión", "AV - Ponderación", "AV - Teléfono",
                "EMAIL - Tipificación 1", "EMAIL - Tipificación 2", "EMAIL - Fecha Gestión", "EMAIL - Ponderación", "EMAIL - Correo",
                "WHATSAPP - Tipificación 1", "WHATSAPP - Tipificación 2", "WHATSAPP - Fecha Gestión", "WHATSAPP - Ponderación", "WHATSAPP - Teléfono",
                "Mejor Gestión - Canal", "Mejor Gestión - Tipificación 1", "Mejor Gestión - Tipificación 2", "Mejor Gestión - Fecha Gestión", "Mejor Gestión - Ponderación", "Mejor Gestión - Teléfono/Correo",
                "Total Llamadas", "Llamadas Atendidas", "Llamadas No Atendidas",
                "Total Whatsapp", "Whatsapp Atendidos", "Whatsapp No Atendidos",
                "Total Correos", "Correos Enviados", "Correos Leídos", "Correos No Enviados",
            ];
            // AV - Telefono, WHATSAPP - Telefono, Mejor Gestion - Telefono/Correo.
            $columnasTexto = [6, 16, 22];
            $filasExcel = array_map(function ($f) {
                $fecha = function ($valor) {
                    return !empty($valor) ? date('d/m/Y H:i', $valor) : '';
                };
                // Sub-objeto de cubAG_mejorGestion para AV/EMAIL/WHATSAPP.
                $canalDatos = function ($canal) use ($f) {
                    return $f['cubAG_mejorGestion'][$canal] ?? [];
                };
                // Un canal sin gestion real trae el sub-objeto en ceros/vacio
                // (cuGestionId => ''), no ausente; sirve para no mostrar
                // ponderacion 0 donde en realidad no hubo gestion.
                $tieneGestion = function ($datosCanal) {
                    return !empty($datosCanal['cuGestionId'] ?? '');
                };

                $av = $canalDatos('AV');
                $email = $canalDatos('EMAIL');
                $whatsapp = $canalDatos('WHATSAPP');

                // Contacto de la mejor gestion general: correo si gano EMAIL,
                // telefono si gano AV o WHATSAPP.
                $canalMejor = $f['cubAG_canal'] ?? '';
                $mejorTieneGestion = !empty($f['cubAG_cuGestionId'] ?? '');
                $contactoMejor = $canalMejor === 'EMAIL'
                    ? ($email['email'] ?? '')
                    : telefonoComoTexto($canalDatos($canalMejor)['telefono'] ?? '');

                $op = $f['_operativo'] ?? [];

                return [
                    $f['cubAG_numFactura'] ?? '',
                    $f['cubAG_estado'] ?? '',
                    $av['tipificacion1'] ?? '',
                    $av['tipificacion2'] ?? '',
                    $fecha($av['fechaGestion'] ?? null),
                    $tieneGestion($av) ? ($av['ponderacion'] ?? '') : '',
                    telefonoComoTexto($av['telefono'] ?? ''),
                    $email['tipificacion1'] ?? '',
                    $email['tipificacion2'] ?? '',
                    $fecha($email['fechaGestion'] ?? null),
                    $tieneGestion($email) ? ($email['ponderacion'] ?? '') : '',
                    $email['email'] ?? '',
                    $whatsapp['tipificacion1'] ?? '',
                    $whatsapp['tipificacion2'] ?? '',
                    $fecha($whatsapp['fechaGestion'] ?? null),
                    $tieneGestion($whatsapp) ? ($whatsapp['ponderacion'] ?? '') : '',
                    telefonoComoTexto($whatsapp['telefono'] ?? ''),
                    $canalMejor,
                    $f['cubAG_tipificacion1'] ?? '',
                    $f['cubAG_tipificacion2'] ?? '',
                    $fecha($f['cubAG_fechaGestion'] ?? null),
                    $mejorTieneGestion ? ($f['cubAG_ponderacion'] ?? '') : '',
                    $contactoMejor,
                    $op['totalLlamadas'] ?? 0,
                    $op['llamadasAtendidas'] ?? 0,
                    $op['llamadasNoAtendidas'] ?? 0,
                    $op['totalWhatsapp'] ?? 0,
                    $op['whatsappAtendidos'] ?? 0,
                    $op['whatsappNoAtendidos'] ?? 0,
                    $op['totalCorreos'] ?? 0,
                    $op['correosEnviados'] ?? 0,
                    $op['correosLeidos'] ?? 0,
                    $op['correosNoEnviados'] ?? 0,
                ];
            }, $filas);
            break;

        case "clientesSinGestion":
            $cabeceras = ["Cliente", "Estado"];
            $filasExcel = array_map(function ($f) {
                return [
                    $f['cubAG_numFactura'] ?? '',
                    $f['cubAG_estado'] ?? '',
                ];
            }, $filas);
            break;

        case "contactabilidad":
        default:
            $cabeceras = ["Nro. Operación", "Nombre Cliente", "Número Telefónico", "Email", "Campaña", "Tipificación 1", "Tipificación 2", "Fecha Gestión", "Canal", "Leído"];
            $columnasTexto = [2];
            $filasExcel = array_map(function ($f) {
                $canal = $f['canal'] ?? '';
                // En el Excel, TELEFONICA se muestra como "AV" (mismo canal,
                // nombre usado en el resto de la app para Agente Virtual).
                $canalExcel = $canal === 'TELEFONICA' ? 'AV' : $canal;
                return [
                    $f['operacion'] ?? '',
                    $f['nombreCliente'] ?? '',
                    telefonoComoTexto($f['telefono'] ?? ''),
                    $f['correo'] ?? '',
                    $f['campania'] ?? '',
                    $f['tipificacion'] ?? '',
                    $f['mejorGestion'] ?? '',
                    !empty($f['fechaMostrar']) ? date('d/m/Y H:i', $f['fechaMostrar']) : '',
                    $canalExcel,
                    // Leido solo aplica a canal EMAIL (ver cmDetalleContactabilidad/Efectividad.html).
                    $canal === 'EMAIL' ? (!empty($f['leido']) ? 'Leído' : 'No leído') : '-',
                ];
            }, $filas);
            break;
    }

    return [$cabeceras, $filasExcel, $columnasTexto];
}

// Convierte un indice de columna 0-based a su letra de Excel (0=A, 25=Z,
// 26=AA...), para armar el mapa de formato de celda que espera addFila().
function letraColumnaExcel($indiceCero)
{
    $n = $indiceCero + 1;
    $letra = '';
    while ($n > 0) {
        $n--;
        $letra = chr(65 + ($n % 26)) . $letra;
        $n = intdiv($n, 26);
    }
    return $letra;
}

// Version generica de generarExcel(): recibe cabeceras y filas ya armadas
// (en vez de un formato fijo), para reutilizar el mismo estilo en cualquier
// exportacion de detalle.
function generarExcelGenerico($cabeceras, $filas, $archivoExcelNombre, $columnasTexto = [])
{
    require_once("../comunes/classes/class.coGeneraExcel.php");

    $anio = date('Y');
    $mes = date('m');

    $rutaRelativaExcel = "/canalesMasivos/reportesExcel/cobranza/$anio/$mes/";
    $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . "/canalesMasivos/reportesExcel/cobranza/$anio/$mes";

    if (!is_dir($rutaFisica)) {
        mkdir($rutaFisica, 0777, true);
    }

    $objExcel = new coGeneraExcel();
    $objExcel->setNombreArchivo($archivoExcelNombre);
    $objExcel->setUbicacion($rutaRelativaExcel);

    $nombreHoja = "Resumen";
    $objExcel->addHoja(0, $nombreHoja);
    $objExcel->setAnchoColumna(30);

    // Columnas (telefonos largos) forzadas a formato de texto en toda la
    // hoja, para que Excel no las muestre en notacion cientifica.
    $formatoColumnas = [];
    if (!empty($columnasTexto)) {
        $formatoTexto = $objExcel->setCeldaFormato('texto');
        foreach ($columnasTexto as $indice) {
            $formatoColumnas[letraColumnaExcel($indice)] = $formatoTexto;
        }
    }

    $filasExcel = [];
    $i = 0;
    $filasExcel["titulo_" . $i] = $cabeceras;
    $i++;

    foreach ($filas as $fila) {
        $filasExcel["fila_" . $i] = $fila;
        $i++;
    }

    foreach ($filasExcel as $key => $fila) {
        $partes = explode("_", $key);
        $style = $partes[0];

        switch ($style) {
            case "titulo":
                $font = $objExcel->setFilaEstiloFuente("Calibri", "12", true, "#ffffff");
                $background = $objExcel->setFilaEstiloFondo("solid", "1e2040");
                $borders = [];
                $alinear = $objExcel->setFilaEstiloAlineacion('center', 'center');
                $merge = "";
                break; 
            default: 
                $font = $objExcel->setFilaEstiloFuente("Calibri", "11", false, "#000000");
                $background = $borders = $alinear = [];
                $alinear = $objExcel->setFilaEstiloAlineacion('left', 'center');
                $merge = "";
                break;
        }

        $objExcel->addFila($nombreHoja, $fila, $font, $background, $borders, $alinear, $merge, [], $formatoColumnas);
    }

    $archivo = $objExcel->genera();

    return "/canalesMasivos/reportesExcel/cobranza/$anio/$mes/" . basename($archivo["archivo"]) . "?v=" . time();
}

?>
<? //_FIN_DE_ARCHIVO
?>
