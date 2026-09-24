<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "guardaAsignacionesDiarias no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/guardaAsignacionesDiarias.php", "", $_SERVER["argv"][0]);
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

//OBTENER CARTERAS DE COBRANZA ACTIVAS


$sql = $mysql->mkSQL("SELECT cobCartera_id FROM cobcartera WHERE cobCartera_estado = %N AND cobCartera_tipo = 'COBRANZA'", 1);

$mysql->query($sql);

$carterasCobranza = [];

while ($rowMysql = $mysql->fetchRow()) {

    $carterasCobranza[$rowMysql["cobCartera_id"]] = true;
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

    if (!isset($carterasCobranza[$cartera])) {
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
        "cubAG_carteraId" => (string) $cartera,
        "cubAG_ciclo" => (int)$periodo,
        "cubAG_fechaPeriodo" => (int)$fechaPeriodo
    ];

    // buscar asignaciones
    $mdbAsig->buscar("cuAsignacionesGestionAP", $condicionAsignaciones);

    $total = 0;

    while ($asig = $mdbAsig->siguiente()) {

        //Buscamos si ya existe el registro el día actual
        $condicionExiste = [
            "avHistAsig_numFactura"   => $asig["cubAG_numFactura"],
            "avHistAsig_carteraId"    => $asig["cubAG_carteraId"],
            "avHistAsig_ciclo"        => $asig["cubAG_ciclo"],
            "avHistAsig_fechaPeriodo" => $asig["cubAG_fechaPeriodo"],
            "avHistAsig_fechaCreacion" => ['$gte' => $inicioDia, '$lte' => $finDia]
        ];


        $existe = $mongoExiste->buscar("avHistorialAsignacionesDiarias", $condicionExiste);


        if ($existe < 1) {

            // Tiene telefonos? (MySQL: ustelfs.usTelfs_relId = usuario)
            $usuarioId = $asig["cubAG_usuariosId"] ?? "";

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
            $cedula = $asig["cubAG_cedula"] ?? "";

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

            $obj = [
                "avHistAsig_idAsignaciones"  => $asig["_id"] ?? "",
                "avHistAsig_cedula"          => $asig["cubAG_cedula"] ?? "",
                "avHistAsig_nombres"         => $asig["cubAG_nombres"] ?? "",
                "avHistAsig_apellidos"       => $asig["cubAG_apellidos"] ?? "",
                "avHistAsig_usuariosId"       => $asig["cubAG_usuariosId"] ?? "",
                "avHistAsig_numFactura"      => $asig["cubAG_numFactura"] ?? "",
                "avHistAsig_carteraId"       => $asig["cubAG_carteraId"] ?? "",
                "avHistAsig_carteraNombre"   => $asig["cubAG_carteraNombre"] ?? "",
                "avHistAsig_ciclo"           => $asig["cubAG_ciclo"] ?? 0,
                "avHistAsig_fechaPeriodo"    => $asig["cubAG_fechaPeriodo"] ?? 0,
                "avHistAsig_riesgo"          => $asig["cubAG_riesgo"] ?? "",
                "avHistAsig_deudaNetaActual" => $asig["cubAG_deudaNetaActual"] ?? 0,
                "avHistAsig_diasMora"        => $asig["cubAG_diasMora"] ?? 0,
                "avHistAsig_producto"        => $asig["cubAG_producto"] ?? "",
                "avHistAsig_ciudad"          => $asig["cubAG_ciudad"] ?? "",
                "avHistAsig_capitalActual"   => $asig["cubAG_capitalActual"] ?? 0,
                "avHistAsig_fechaCarga"      => $asig["cubAG_fechaCarga"] ?? 0,
                "avHistAsig_capitalInicial"  => $asig["cubAG_capitalInicial"] ?? 0,
                "avHistAsig_deudaNetaInicial" => $asig["cubAG_deudaNetaInicial"] ?? 0,
                "avHistAsig_montoPago"       => $asig["cubAG_montoTotalPago"] ?? 0,
                "avHistAsig_tramoSaldo"      => $asig["cubAG_tramoSaldo"] ?? "",
                "avHistAsig_tramoMora"       => $asig["cubAG_tramoMora"] ?? "",
                "avHistAsig_marca"           => $asig["cubAG_marca"] ?? "",
                "avHistAsig_canton"          => $asig["cubAG_canton"] ?? "",
                "avHistAsig_tieneTelefono"   => $tieneTelefono,
                "avHistAsig_tieneCorreo"     => $tieneCorreo,

                "avHistAsig_fechaCreacion" =>  time(),

            ];

            // guardar en historial
            $mongo->guardar("avHistorialAsignacionesDiarias", $obj);
            $total++;
        }
    }

    echo "Total guardados cobranza: $total <br>";
}


echo "EJECUCION_COMPLETA";


?><?

    //_FIN_DE_ARCHIVO 
    ?>