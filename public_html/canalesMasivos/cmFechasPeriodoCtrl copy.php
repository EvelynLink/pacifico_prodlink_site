<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once("../cobranza/classes/class.cobCartera.php");
require_once("../cobranza/classes/class.cobCarteraRamas.php");
require_once("../fabricaCredito/classes/class.logParamCredito.php");
require_once("../comunes/classes/class.coCompleteMongo.php");
$logParamCredito = new logParamCredito();

if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();
$mongo = new MYMONGODB();
$mongo1 = new MYMONGODB();
$mongo2 = new MYMONGODB();




/*function guardarNuevoRegistro($fechasPeriodo)
{
    $mongo = new MYMONGODB();

    $cartera = 0;
    $periodo = 0;
    $fechaInicio = 0;
    $fechaFin = 0;

    foreach ($fechasPeriodo as $key2 => $value2) {
        if ($key2 == "carteraId") {
            $cartera = $value2;
        }
        if ($key2 == "periodo") {
            $periodo = $value2;
        }
        if ($key2 == "fechaInicio") {
            $fechaInicio = strtotime($value2);
        }
        if ($key2 == "fechaFin") {
            $fechaFin = strtotime($value2);
        }
    }

    $objFechas = [
        "cartera" => (int)$cartera,
        "periodo" => (int)$periodo,
        "fecha" => (int)$fechaInicio,
        "fechaFin" => (int)$fechaFin,
        "activo" => (int)1,
        "usuarioCreacion" => strtoupper($_SESSION[MID . "userNombre"]),
        "usuarioIdCreacion" => (int)$_SESSION[MID . "userId"],
        "fechaCreacion" => time()
    ];

    $condicionPeriodo = [
        "cartera" => (int)$cartera,
        "activo" => (int)1
    ];

    $resBusq = $mongo->buscar("control_carga_periodo", $condicionPeriodo, [], [], 1);

    if ($resBusq > 0) {

        $row = $mongo->siguiente();

        $fechaInicioGuardada = $row["fecha"];
        $fechaFinGuardada = $row["fechaFin"];
        $periodoGuardado = $row["periodo"];

        if ($periodoGuardado != $periodo &&  $fechaInicioGuardada != $fechaInicio || $fechaFinGuardada != $fechaFin) {
            return 2; // fechas diferentes
        }

        $resGuardado = $mongo->guardar('control_carga_periodo', $objFechas);

        if ($resGuardado > 0) {
            return 1;
        } else {
            return 0;
        }
    } else {

        // No existe registro para esa cartera, se puede guardar
        $resGuardado = $mongo->guardar('control_carga_periodo', $objFechas);

        if ($resGuardado > 0) {
            return 1;
        } else {
            return 0;
        }
    }
}*/

function guardarNuevoRegistro($fechasPeriodo)
{
    $mongo = new MYMONGODB();

    $cartera = 0;
    $periodo = 0;
    $fechaInicio = 0;
    $fechaFin = 0;

    // ?? Extraer datos
    foreach ($fechasPeriodo as $key2 => $value2) {
        if ($key2 == "carteraId") {
            $cartera = $value2;
        }
        if ($key2 == "periodo") {
            $periodo = $value2;
        }
        if ($key2 == "fechaInicio") {
            $fechaInicio = strtotime($value2);
        }
        if ($key2 == "fechaFin") {
            $fechaFin = strtotime($value2);
        }
    }

    // ?? Objeto a guardar
    $objFechas = [
        "cartera" => (int)$cartera,
        "periodo" => (int)$periodo,
        "fecha" => (int)$fechaInicio,
        "fechaFin" => (int)$fechaFin,
        "activo" => (int)1,
        "usuarioCreacion" => strtoupper($_SESSION[MID . "userNombre"]),
        "usuarioIdCreacion" => (int)$_SESSION[MID . "userId"],
        "fechaCreacion" => time()
    ];

    // =========================================================
    // ?? UNA SOLA BÚSQUEDA
    // =========================================================
    $condicionCartera = [
        "cartera" => (int)$cartera,
        "activo" => (int)1
    ];

    $res = $mongo->buscar("control_carga_periodo", $condicionCartera);

    if ($res > 0) {

        while ($row = $mongo->siguiente()) {

            // Duplicado exacto
            if (
                $row["periodo"] == $periodo &&
                $row["fecha"] == $fechaInicio &&
                $row["fechaFin"] == $fechaFin
            ) {
                return 3;
            }

            // Mismo periodo con fechas distintas
            if (
                $row["periodo"] == $periodo &&
                ($row["fecha"] != $fechaInicio || $row["fechaFin"] != $fechaFin)
            ) {
                return 2;
            }

            // Misma cartera (otro periodo) con fechas distintas
            if (
                $row["periodo"] != $periodo &&
                ($row["fecha"] != $fechaInicio || $row["fechaFin"] != $fechaFin)
            ) {
                return 4;
            }
        }
    }

    $resGuardado = $mongo->guardar('control_carga_periodo', $objFechas);

    if ($resGuardado > 0) {
        return 1;
    } else {
        return 0;
    }
}




switch ($act) {


    case "editarRegistro":

        $d = jsonStart();
        $campania = $d['campania'];
        $mongo = new MYMONGODB();
        $fechaInicio = strtotime($campania['fecha']);
        $fechaFin = strtotime($campania['fechaFin']);


        $c = $mongo->actualizar(
            "control_carga_periodo",
            ['_id' => $mongo->String2MongoId($campania["id"])],
            [
                'cartera'  => (int) $campania['cartera'],
                'periodo'  => (int) $campania['periodo'],
                'fecha'    => $fechaInicio,
                'fechaFin' => $fechaFin
            ]
        );

        if ($c) {
            $json['respuesta'] = 'Modificado con exito';
        } else {
            $json['respuesta'] = 'El registro no se modificó';
        }

        break;

    case "cambiarEstadoRegistro":
        $d = jsonStart();
        $campania = $d['campania'];
        $estado  = (int)$d['estado'];


        $c = $mongo->actualizar(
            "control_carga_periodo",
            ['_id' => $mongo->String2MongoId($campania["id"])],
            [
                'activo'  => $estado,
            ]
        );

        if ($c) {
            $json['respuesta'] = 'Modificado con exito';
        } else {
            $json['respuesta'] = 'El registro no se modificó';
        }

        break;


    case "getListaCarteras":
        require_once "../comunes/classes/class.coTabulaAngular.php";

        $dbMap = new MYSQLDB();

        $sql = $dbMap->mkSQL("SELECT cobCartera_id, cobCartera_nombre,cobCartera_estado FROM cobcartera ");

        $resultado = [];

        $dbMap->query($sql);

        while ($row = $dbMap->fetchRow()) {
            $resultado[] = [
                'id'     => $row['cobCartera_id'],
                'nombre' => $row['cobCartera_id'] . " - " . $row['cobCartera_nombre'],
                'estado' => (int) $row['cobCartera_estado']
            ];
        }

        $json["resultado"] = $resultado;
        break;

    case "getListaPeriodos":
        require_once "../comunes/classes/class.coTabulaAngular.php";

        $dbMap = new MYSQLDB();

        $sql = $dbMap->mkSQL("SELECT perPeriodo_ciclo FROM perperiodo where perPeriodo_estado=1");

        $resultado = [];

        $dbMap->query($sql);

        while ($row = $dbMap->fetchRow()) {
            $resultado[] = $row['perPeriodo_ciclo'];
        }

        $json["resultado"] = $resultado;
        break;


    case "guardarRegistro":
        //<editor-fold defaultstate="collapsed" desc=" Guarda la campaña y configuraciones de condiciones, atendiendo si es nuevo o actualización de campos ">
        $d = jsonStart();
        $fechasPeriodo = $d["fechasPeriodo"];

        $resNuevo = guardarNuevoRegistro($fechasPeriodo);

        if ($resNuevo == 1) {
            $json["resultado"] = 'Registro guardado correctamente';
        } elseif ($resNuevo == 2) {
            // mismo periodo, fechas distintas
            $json["resultado"]["error"] = 'Error: El periodo ya tiene un rango de fechas configurado.';
        } elseif ($resNuevo == 3) {
            // duplicado exacto
            $json["resultado"]["error"] = 'Error: El registro ya existe.';
        } elseif ($resNuevo == 4) {
            // misma cartera, distintos periodos con fechas distintas
            $json["resultado"]["error"] = 'Error: No se puede registrar fechas diferentes para esta cartera.';
        } elseif ($resNuevo == 0) {
            $json["resultado"]["error"] = 'Error al guardar el registro.';
        } else {
            $json["resultado"]["error"] = "";
        }

        //</editor-fold>
        break;

    case "listaPeriodosActivos":
        //<editor-fold defaultstate="collapsed" desc=" Lista las campañas para presentarlos en la tabula ">
        $d = jsonStart();
        //coleccion para nueva estructura fcCampaniasOriginacion
        $coleccion = "control_carga_periodo";
        $condition = ["activo" => (int) 1];
        $campos = ["_id", "cartera", "periodo", "fecha", "fechaFin", "fechaCarga", "usuarioCreacion", "fechaCreacion", "activo"];
        $orden = ["fechaCreacion" => -1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condition, $campos, $orden);
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        //</editor-fold>
        break;

    case "listaPeriodosInactivos":
        //<editor-fold defaultstate="collapsed" desc=" Lista las campañas para presentarlos en la tabula ">
        $d = jsonStart();
        //coleccion para nueva estructura fcCampaniasOriginacion
        $coleccion = "control_carga_periodo";
        $condition = ["activo" => (int) 0];
        $campos = ["_id", "cartera", "periodo", "fecha", "fechaFin", "fechaCarga", "usuarioCreacion", "fechaCreacion", "activo"];
        $orden = ["fechaCreacion" => -1];
        $limpiar = [];

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condition, $campos, $orden);
        $ngTabula->permiteExportar(false);
        $json = $ngTabula->responde();
        //</editor-fold>
        break;
}
jsonEnd($json, $limpiar);
?><? //_FIN_DE_ARCHIVO                                                                                   
    ?>
