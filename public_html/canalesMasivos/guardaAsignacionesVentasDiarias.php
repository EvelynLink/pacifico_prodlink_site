<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "guardaAsignacionesVentasDiarias no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/guardaAsignacionesVentasDiarias.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}

//CONEXIONES
$mongo = new MYMONGODB();
$mdbAsig = new MYMONGODB();
$mongoExiste = new MYMONGODB();
$mongoEmail = new MYMONGODB();
$mysql = new MYSQLDB();
$mysqlTel = new MYSQLDB();

// cache para no repetir consultas del mismo usuario/cedula varias veces
$cacheTelefonos = [];
$cacheCorreos = [];

//OBTENER CARTERAS DE VENTAS ACTIVAS

$sql = $mysql->mkSQL("SELECT cobCartera_id FROM cobcartera WHERE cobCartera_estado = %N AND cobCartera_tipo = 'VENTAS'", 1);

$mysql->query($sql);

$carterasVentas = [];

while ($rowMysql = $mysql->fetchRow()) {

    $carterasVentas[$rowMysql["cobCartera_id"]] = true;
}



$carterasProcesadas = [];
$inicioDia = strtotime(date('Y-m-d 00:00:00'));
$finDia    = strtotime(date('Y-m-d 23:59:59'));

$condicion = [
    'activo' => (int)1
];

$mongo->buscar("control_carga_periodo", $condicion);

while ($row = $mongo->siguiente()) {

    $cartera = (string)$row["cartera"];

    if (!isset($carterasVentas[$cartera])) {
        continue;
    }

    $periodo = (string)$row["periodo"];
    $fechaPeriodo = (int)$row["fecha"];

    // evitar repetir misma cartera-periodo-fecha
    $key = $cartera . "_" . $periodo . "_" . $fechaPeriodo;

    if (in_array($key, $carterasProcesadas)) {
        continue;
    }

    $carterasProcesadas[] = $key;

    echo "<hr>";
    echo "Procesando cartera: $cartera ";
    echo "Periodo: $periodo ";
    echo "Fecha: " . date('Y-m-d', $fechaPeriodo);
    echo "<br>";

    // condiciones de búsqueda
    $condicionAsignaciones = [
        "cubAV_carteraId" => (string) $cartera,
        "cubAV_ciclo" => (int)$periodo,
        "cubAV_fechaPeriodo" => (int)$fechaPeriodo
    ];

    // buscar asignaciones
    $mdbAsig->buscar("cuAsignacionesGestionVentas", $condicionAsignaciones);

    $total = 0;

    while ($asig = $mdbAsig->siguiente()) {

        //Buscamos si ya existe el registro el día actual
        $condicionExiste = [
            "avHistAsigV_numFactura"   => $asig["cubAV_numFactura"],
            "avHistAsigV_carteraId"    => $asig["cubAV_carteraId"],
            "avHistAsigV_ciclo"        => $asig["cubAV_ciclo"],
            "avHistAsigV_fechaPeriodo" => $asig["cubAV_fechaPeriodo"],
            "avHistAsigV_fechaCreacion" => ['$gte' => $inicioDia, '$lte' => $finDia]
        ];


        $existe = $mongoExiste->buscar("avHistorialAsignacionesVentasDiarias", $condicionExiste);


        if ($existe < 1) {

            // Tiene telefonos? (MySQL: ustelfs.usTelfs_relId = usuario)
            $usuarioId = $asig["cubAV_usuariosId"] ?? "";

            if ($usuarioId !== "") {
                if (!isset($cacheTelefonos[$usuarioId])) {
                    $sqlTel = $mysqlTel->mkSQL("SELECT usTelfs_relId FROM ustelfs WHERE usTelfs_relId = %N LIMIT 1", $usuarioId);

                    $mysqlTel->query($sqlTel);
                    $cacheTelefonos[$usuarioId] = $mysqlTel->fetchRow() ? 1 : 0;
                }
                $tieneTelefono = $cacheTelefonos[$usuarioId];
            } else {
                $tieneTelefono = 0;
            }

            // Tiene correos? (Mongo: cbEmail.mail_cedula = cedula)
            $cedula = $asig["cubAV_cedula"] ?? "";

            if ($cedula !== "") {
                if (!isset($cacheCorreos[$cedula])) {
                    $condicionEmail = ["mail_cedula" => (string)$cedula];
                    $totalEmail = $mongoEmail->buscar("cbEmail", $condicionEmail);
                    $cacheCorreos[$cedula] = ($totalEmail > 0) ? 1 : 0;
                }
                $tieneCorreo = $cacheCorreos[$cedula];
            } else {
                $tieneCorreo = 0;
            }

            $mejorGestion = $asig["cubAV_mejorGestion"] ?? [];

            $obj = [
                "avHistAsigV_idAsignaciones"   => $asig["_id"] ?? "",
                "avHistAsigV_cedula"           => $asig["cubAV_cedula"] ?? "",
                "avHistAsigV_nombres"          => $asig["cubAV_nombres"] ?? "",
                "avHistAsigV_apellidos"        => $asig["cubAV_apellidos"] ?? "",
                "avHistAsigV_usuariosId"       => $asig["cubAV_usuariosId"] ?? "",
                "avHistAsigV_numFactura"       => $asig["cubAV_numFactura"] ?? "",
                "avHistAsigV_carteraId"        => $asig["cubAV_carteraId"] ?? "",
                "avHistAsigV_carteraNombre"    => $asig["cubAV_carteraNombre"] ?? "",
                "avHistAsigV_ciclo"            => $asig["cubAV_ciclo"] ?? 0,
                "avHistAsigV_fechaPeriodo"     => $asig["cubAV_fechaPeriodo"] ?? 0,
                "avHistAsigV_sponsor"          => $asig["cubAV_sponsor"] ?? "",
                "avHistAsigV_producto"         => $asig["cubAV_producto"] ?? "",
                "avHistAsigV_ciudad"           => $asig["cubAV_ciudad"] ?? "",
                "avHistAsigV_canton"           => $asig["cubAV_canton"] ?? "",
                "avHistAsigV_fechaCarga"       => $asig["cubAV_fechaCarga"] ?? 0,
                "avHistAsigV_fechaInicio"      => $asig["cubAV_fechaInicio"] ?? 0,
                "avHistAsigV_fechaFin"         => $asig["cubAV_fechaFin"] ?? 0,
                "avHistAsigV_fechaAsignacion"  => $asig["cubAV_fechaAsignacion"] ?? 0,
                "avHistAsigV_gestionada"       => $asig["cubAV_gestionada"] ?? 0,
                "avHistAsigV_canal"            => $asig["cubAV_canal"] ?? "",
                "avHistAsigV_ponderacion"      => $asig["cubAV_ponderacion"] ?? 0,
                "avHistAsigV_tipificacion1"    => $asig["cubAV_tipificacion1"] ?? "",
                "avHistAsigV_tipificacion2"    => $asig["cubAV_tipificacion2"] ?? "",
                "avHistAsigV_fechaGestion"     => $asig["cubAV_fechaGestion"] ?? 0,
                "avHistAsigV_telefono"         => $asig["cubAV_telefono"] ?? "",
                "avHistAsigV_email"            => $asig["cubAV_email"] ?? "",
                "avHistAsigV_mejorGestion"     => $mejorGestion,
                "avHistAsigV_tieneTelefono"    => $tieneTelefono,
                "avHistAsigV_tieneCorreo"      => $tieneCorreo,

                "avHistAsigV_fechaCreacion" => time(),

            ];

            // guardar en historial
            $mongo->guardar("avHistorialAsignacionesVentasDiarias", $obj);
            $total++;
        }
    }

    echo "Total guardados ventas: $total <br>";
}


echo "EJECUCION_COMPLETA";


?><?

    //_FIN_DE_ARCHIVO 
    ?>
