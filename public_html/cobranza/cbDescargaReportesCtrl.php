<?php

//set_time_limit(60);
set_time_limit(60 * 60);
ini_set('memory_limit', '2048M');
//require_once("/home/pacifico/_configBasico.inc.php");
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
require_once("../comunes/classes/class.coCompleteMongo.php");
//require_once("../comunes/classes/class.coCompleteAngular.php");

if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];
//documetos
switch ($act) {
    case "traerCatalogos":
        //<editor-fold defaultstate="collapsed" desc="codigo"> 
        $d = jsonStart();
        $reportes = [];
        $campanias = 0;
        $cnf = getConf("Parametrización");
        $t = $cnf["Reportes disponibles"];
        /*foreach ($t as $value) {
            $car = [];
            if (strpos($value, '||') !== false) {
                $b = explode('||', $value);
                $value = $b[0];
                if (strpos($b[1], '***') !== false) {
                    $s = explode('***', $b[1]);
                    foreach ($s as $f) {
                        $p = explode(':::', $f);
                        $car[] = ['name' => $p[1], 'value' => $p[0]];
                    }
                    $campanias = 1;
                } else {
                    $s = explode('**', $b[1]);
                    foreach ($s as $f) {
                        $p = explode(':::', $f);
                        $car[] = ['name' => $p[1], 'value' => $p[0]];
                    }
                }
            }
            $partes = explode(':::', $value);
            if (count($partes) == 2) {
                $reportes[] = ['name' => $partes[0], 'value' => $partes[1], 'cartera' => $car];
            }
        }*/
        foreach ($t as $value) {
            if (strpos($value, '||') !== false) {
                $car = [];

                $seccionesArr = explode('||', $value);
                //Sección Cartera

                if ($seccionesArr[1] != 9999) {
                    if (strpos($seccionesArr[1], '**') !== false) {
                        $s = explode('**', $seccionesArr[1]);
                        foreach ($s as $f) {
                            $p = explode(':::', $f);
                            $car[] = ['name' => $p[1], 'value' => $p[0]];
                        }
                    } else {
                        $p = explode(':::', $seccionesArr[1]);
                        $car[] = ['name' => $p[1], 'value' => $p[0]];
                    }
                } else {
                    $car[] = 9999;
                }
                //Sección Campañas
                $cam = [];
                if (isset($seccionesArr[2])) {
                    if ($seccionesArr[2] != ' ') {
                        $cam[] = 9999;

                        if ($seccionesArr[2] != 9999 && $seccionesArr[2] != ' ') {
                            if (strpos($seccionesArr[2], '**') !== false) {
                                $s = explode('**', $seccionesArr[2]);
                                foreach ($s as $f) {
                                    $p = explode(':::', $f);
                                    $cam[] = ['name' => $p[1], 'value' => $p[0]];
                                }
                            } else {
                                $p = explode(':::', $seccionesArr[2]);
                                $cam[] = ['name' => $p[1], 'value' => $p[0]];
                            }
                        }
                    }
                }
                //Sección filtro por fechas
                $fecha = '1';
                if (isset($seccionesArr[3])) {
                    $p = explode(':::', $seccionesArr[3]);
                    $fecha = $p[1];
                }
                $partes = explode(':::', $seccionesArr[0]);
                $reportes[] = ['name' => $partes[0], 'value' => $partes[1], 'cartera' => $car, 'campanias' => $cam, 'filtroFechas' => $fecha];
            }
        }

        $json["respuesta"] = ["reportes" => $reportes];
        //</editor-fold> 
        break;
    case "listaCampanias":
        $d = jsonStart();
        $carteraId = $d['cartera']['value'];
        eval ('$db=new ' . DB1 . 'DB();');
        $listaCamp = [];
        $listaCampSql[] = ['name' => 'Todas', 'value' => 9999];
        $sql = $db->mkSQL("SELECT DISTINCT scRamas_id, scRamas_nombre, scRamas_subTipo
                           FROM scramas
                           INNER JOIN cobcarteraramas ON cobCarteraRamas_ramaIdfk = scRamas_id
                           inner join cobcartera on cobCartera_id=cobCarteraRamas_carteraIdfk and cobCartera_estado=1 
                           WHERE cobCarteraRamas_carteraIdfk =" . $carteraId . " and scRamas_subTipo<>'' order by scRamas_nombre", "scRamas_nombre");
        //                    print_h($sql); 
        //trigger_error($sql);
        if ($db->query($sql)) {

            while ($row = $db->fetchRow()) {
                $listaCamp[] = ['name' => $row['scRamas_nombre'], 'value' => $row['scRamas_id']];
            }
            $listaCamp = array_merge($listaCampSql, $listaCamp);
        }
        //trigger_error(json_encode($listaCamp));
        $json["resp"] = ["listaCamp" => $listaCamp];
        break;
    case "listaCarteras":
        $d = jsonStart();
        $tipoCartera = $d['tipoCartera'];
        $carteraId = $d['cartera']['value'];
        eval ('$db=new ' . DB1 . 'DB();');
        $listaCartera = [];
        if ($tipoCartera != 'Grupo') {
            $listaCartera[] = ['name' => 'Todas', 'value' => 9999];
        }
        $sql = $db->mkSQL("SELECT cobCartera_id,cobCartera_nombre
                               FROM cobcartera 
                               WHERE cobCartera_estado=1  order by cobCartera_nombre", "cobCartera_nombre");
        //                    print_h($sql); 
        //trigger_error($sql);
        if ($db->query($sql)) {
            while ($row = $db->fetchRow()) {
                $row['cobCartera_nombre'] = ucwords(strtolower($row['cobCartera_nombre']));
                $listaCartera[] = ['name' => $row['cobCartera_nombre'], 'value' => $row['cobCartera_id']];
            }
            //$listaCamp = array_merge($listaCampSql, $listaCamp);
        }
        //trigger_error(json_encode($listaCamp));
        $json["resp"] = ["listaCarteras" => $listaCartera];
        break;
    case "cargarTabula":
        //<editor-fold defaultstate="collapsed" desc="codigo"> 
        $d = jsonStart();

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);

        $coleccion = "cbReportesGenerados";
        $condition = ["rep_inactivo" => 0];
        $campos = [];
        $limpiar = [];

        $ngTabula->setQueryDatos(
            $coleccion,
            $condition,
            $campos,
            ['rep_generadoEl' => -1]
        );

        $ngTabula->setPreparaDatos(function ($fila) {
            if (!isset($fila["rep_urlSitio"])) {
                $fila["rep_urlSitio"] = BASEURL;
            }
            return $fila;
        });

        //$ngTabula->permiteExportar(true, "Precalificacion");

        $json = $ngTabula->responde();
        //</editor-fold> 
        break;
    case "eliminarReporte":
        //<editor-fold defaultstate="collapsed" desc="codigo"> 
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);

        $mongo2 = new MYMONGODB();
        if ($mongo2->actualizar("cbReportesGenerados", ['rep_id' => $id], ['rep_inactivo' => 1]) > 0) {
            //si hubiera debo eliminar los archivos tambien?
            //TODO:
            $json["respuesta"] = "ok";
        } else {
            $json["error"] = "No se pudo eliminar el registro";
        }
        //</editor-fold> 
        break;
    case "generarReporte":
        //<editor-fold defaultstate="collapsed" desc="codigo"> 
        $mongo2 = new MYMONGODB();
        $d = jsonStart();
        $tipo = expect_safe_html($d["tipo"]);
        $fd2 = expect_integer($d["fechaDesde"]);
        $fh2 = expect_integer($d['fechaHasta']);
        $cartera = $d['cartera'];
        $campania = $d['campania'];
        $json = [];
        $fd1 = round(($fd2 / 1000), 0);
        $fh1 = round(($fh2 / 1000), 0);
        $fdt = date('Y-m-d', $fd1);
        $fechaDesde = strtotime($fdt . " 00:00:00");

        $fht = date('Y-m-d', $fh1);
        $fechaHasta = strtotime($fht . " 23:59:59");
        $con1 = [
            'rep_tipo' => (string) $tipo,
            'rep_generadoPorId' => (int) $_SESSION[MID . "userId"],
            'rep_estado' => "Generando archivo",
            'rep_inactivo' => (int) 0
        ];
        $cursor = $mongo2->buscar('cbReportesGenerados', $con1);
        if ($cursor > 0) {
            $json["error"] = "Existe un reporte previo procesando, Por favor espere.";
        }
        if ($tipo == '') {
            $json["error"] = "Seleccione un tipo de reporte";
        }
        if ($fd1 == 0 || $fh1 == 0) {
            $json["error"] = "Seleccione la fecha desde/hasta";
        }

        if (!isset($json["error"])) {
            $fechaId = date('YmdHisv');
            $identificador = $fechaId . "_" . (strtolower(str_replace(" ", "_", $tipo))) . "_" . (rand(1000, 9999));
            //$fechaDesde = round(($fd / 1000), 0);
            //$fechaHasta = round(($fh / 1000), 0);


            $item = [
                "rep_id" => $identificador,
                "rep_tipo" => $tipo,
                "rep_fechaDesde" => $fechaDesde,
                "rep_fechaHasta" => $fechaHasta,
                "rep_generadoPorId" => intval($_SESSION[MID . "userId"]),
                "rep_generadoPor" => $_SESSION[MID . "userNombre"],
                "rep_generadoEl" => time(),
                "rep_estado" => "Generando archivo",
                "rep_errorEtl" => "",
                "rep_tramas" => [],
                "rep_cartera" => (string) $cartera['name'],
                "rep_carteraId" => (string) $cartera['value'],
                "rep_urlSitio" => (string) BASEURL,
                "rep_inactivo" => 0
            ];
            $nuevo = $mongo2->guardar('cbReportesGenerados', $item);
            if ($nuevo != 0) {
                //ejecute el etl
                $etl = ejecutarEtl($tipo, $fechaDesde, $fechaHasta, $nuevo, $cartera['value'], $campania['value']);
                if ($etl == "ok") {
                    $json["respuesta"] = $identificador;
                } else {
                    $json["error"] = $etl;
                }
            } else {
                $json["error"] = "No se pudo generar el registro para la creación del reporte";
            }
        }

        //</editor-fold> 
        break;
}
jsonEnd($json, $limpiar);

function ejecutarEtl($tipo, $fecha, $fechaHasta, $idMongo, $cartera, $campania)
{
    if ($cartera != '') {
        $car = [];
        $cnf = getConf("Parametrización");
        $t = $cnf["Reportes disponibles"];
        foreach ($t as $value) {
            if (strpos($value, '||') !== false) {
                $b = explode('||', $value);
                $s = explode('**', $b[1]);
                foreach ($s as $f) {
                    $p = explode(':::', $f);
                    $car[$p[0]] = str_replace(' ', '_', $p[1]);
                }
            }
        }
    }
    $comando = '';
    $fechaGeneracion = date('Ymd', $fecha);
    $fechaId = date('YmdHisv');
    if ($cartera != '') {
        $nombre = "Reporte_" . (strtolower(str_replace(" ", "_", $tipo))) . "_" . $fechaGeneracion . "_" . $car[$cartera] . "_" . $fechaId;
    } else {
        $nombre = "Reporte_" . (strtolower(str_replace(" ", "_", $tipo))) . "_" . $fechaGeneracion . "_" . $fechaId;
    }
    //para cartera
    if (strtolower($tipo) == 'cartera') {
        if ($cartera != '') {
            $nombre = "Reporte_" . (strtolower(str_replace(" ", "_", $tipo))) . "_" . $fechaId . "_" . $car[$cartera] . "_" . (rand(1000, 9999));
        } else {
            $nombre = "Reporte_" . (strtolower(str_replace(" ", "_", $tipo))) . "_" . $fechaId . "_" . (rand(1000, 9999));
        }
    }

    $usuario = intval($_SESSION[MID . "userId"]);
    $ok = 0;
    $error = '';
    switch ($tipo) {
        case "Recaudaciones":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/ETLRecaudaciones/etlRecaudaciones.kjb' "
                . "-level=Basic -param:fechaPagoDesde=" . $fecha . " -param:fechaPagoHasta=" . $fechaHasta . " -param:nombreArchivo=" . $nombre . " -param:usuario=" . $usuario . " -param:cartera=" . $cartera
                //. "-level=Basic -param:fechaCarga=" . $fecha ." -param:nombreArchivo=".$nombre . " -param:usuario=".$usuario
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteReacudaciones.log' >> /dev/null 2>&1";
            break;
        case "Cancelaciones":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/ETLCancelaciones/etlCancelaciones.kjb' "
                . "-level=Basic -param:fechaPagoDesde=" . $fecha . " -param:fechaPagoHasta=" . $fechaHasta . " -param:nombreArchivo=" . $nombre . " -param:usuario=" . $usuario . " -param:cartera=" . $cartera
                //. "-level=Basic -param:fechaPago=" . $fecha ." -param:nombreArchivo=".$nombre. " -param:usuario=".$usuario
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCancelaciones.log' >> /dev/null 2>&1";
            break;
        case "Cartera":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/ETLCartera/etlCartera.kjb' "
                . "-level=Basic -param:fechaCartera=" . $fecha . " -param:nombreArchivo=" . $nombre . " -param:usuario=" . $usuario . " -param:cartera=" . $cartera
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCartera.log' >> /dev/null 2>&1";
            break;
        case "Saldo de Cartera":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/CierreGeneral/JobReporteCierre.kjb' "
                . "-level=Basic -param:fechaInicio=" . $fecha . " -param:fechaFin=" . $fechaHasta . " -param:nombreArchivo=" . $nombre
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCierreGeneral.log' >> /dev/null 2>&1";
            break;
        case "Cartera Vencida":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/CarteraVencida/JobReporteCarteraVencida.kjb' "
                . "-level=Basic -param:fechaInicio=" . $fecha . " -param:xfechaFin=" . $fechaHasta . " -param:znombreArchivo=" . $nombre
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCarteraVencida.log' >> /dev/null 2>&1";
            break;
        case "Condonaciones":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/ETLCondonaciones/ETLCondonaciones.kjb' "
                . "-level=Basic -param:fechaDesde=" . $fecha . " -param:fechaHasta=" . $fechaHasta . " -param:usuario=" . $usuario . " -param:nombreArchivo=" . $nombre . " -param:cartera=" . $cartera
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCondonaciones.log' >> /dev/null 2>&1";
            break;
        case "Reversos":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/PagosReversos/Job_PORTCOLL_PagosReversos.kjb' "
                . "-level=Basic -param:cartera=" . $cartera . " -param:fechaPagoDesde=" . $fecha . " -param:fechaPagoHasta=" . $fechaHasta . " -param:nombreArchivo=" . $nombre
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReportePagosReversos.log' >> /dev/null 2>&1";
            break;
        case "Cartera por Cuotas":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/ReporteCarteraCuota/Job_Portcoll_ReporteCarteraCuota.kjb' "
                . "-level=Basic -param:fechaCartera=" . $fecha . " -param:nombreArchivo=" . $nombre . " -param:usuario=" . $usuario . " -param:cartera=" . $cartera
                . " -logfile='/home/pacifico/public_html/ETL/logs/ReporteCarteraCuota.log' >> /dev/null 2>&1";
            break;
        case "Pagos Cartera Administrada":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/PagosCarteraAdministrada/jobReportePagosCA.kjb' "
                . "-level=Basic -param:fechaPagoDesde=" . $fecha . " -param:fechaPagoHasta=" . $fechaHasta . " -param:nombreArchivo=" . $nombre . " -param:usuario=" . $usuario . " -param:cartera=" . $cartera
                . " -logfile='/home/pacifico/public_html/ETL/logs/jobReportePagosCA.log' >> /dev/null 2>&1";
            break;
        case "Reporte de Eventos":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/reporteEventos/Job_reporteEventos.kjb' "
                . "-level=Basic -param:archivoNombre=" . $nombre . " -param:campania=" . $campania . " -param:cartera=" . $cartera . " -param:fechaFin=" . $fechaHasta . " -param:fechaInicio=" . $fecha
                . " -logfile='/home/pacifico/public_html/ETL/logs/Job_reporteEventos.log' >> /dev/null 2>&1";
            break;
        case "Reporte de Rubros":
            $comando = "sh /usr/local/Pentaho/etl/data-integration/kitchen.sh "
                . "-file='/home/pacifico/public_html/cobranza/etl/reportes/reporteRubros/jobReporteRubros.kjb' "
                . "-level=Basic -param:archivoNombre=" . $nombre . " -param:carteraId=" . $cartera
                . " -logfile='/home/pacifico/public_html/ETL/logs/jobReporteRubros.log' >> /dev/null 2>&1";
            break;
    }
    if ($comando != '') {
        $ejecucion = "";
        $resultado = system($comando, $ejecucion);
        trigger_error($comando);
        $extExcel = '.xls';
        if ($ejecucion === 0) {
            //EL JOB SE EJECUTÓ CORRECTAMENTE
            //actualizo la trama
            $archivo = BASEURL . "ETL/Pacifico/Procesamiento/Tramas/";
            switch ($tipo) {
                case "Recaudaciones":
                    $archivo .= "archivos/recaudaciones/" . $nombre;
                    //$archivo = BASEURL."ETL/Portcoll/Procesamiento/Tramas/DebitosBancarios/Guayaquil/".$nombre;
                    break;
                case "Cancelaciones":
                    $archivo .= "archivos/cancelaciones/" . $nombre;
                    break;
                case "Cartera":
                    $archivo .= "archivos/cartera/" . $nombre;
                    break;
                case "Cartera por Cuotas":
                    $archivo .= "archivos/ReporteCarteraCuotas/" . $nombre;
                    break;
                case "Saldo de Cartera":
                    $archivo .= "archivos/saldoCartera/" . $nombre;
                    break;
                case "Cartera Vencida":
                    $archivo = BASEURL . "ETL/Portcoll/Procesamiento/Reportes/Recibidos/ReporteCarteraVencida/" . $nombre;
                    //                    $archivo .= "archivos/carteraVencida/".$nombre;
                    break;
                case "Condonaciones":
                    $archivo .= "archivos/condonaciones/" . $nombre;
                    break;
                case "Reversos":
                    $archivo .= "archivos/PagosReversos/" . $nombre;
                    break;
                case "Pagos Cartera Administrada":
                    $archivo = "https://pacifico.prodlink.site/ETL/Pacifico/Procesamiento/Tramas/Venta/ReportesPagoCA/PagosTramaCA/" . $nombre;
                    break;
                case "Reporte de Eventos":
                    $extExcel = '.xlsx';
                    $archivo = "https://pacifico.prodlink.site/ETL/Pacifico/Procesamiento/Reportes/ReporteEventos/" . $nombre;
                    break;
                case "Reporte de Rubros":
                    $extExcel = '.csv';
                    $archivo = "https://pacifico.prodlink.site/ETL/Pacifico/Procesamiento/Reportes/ReporteRubros/" . $nombre;
                    break;
            }
            $mongo2 = new MYMONGODB();
            if ($mongo2->actualizar("cbReportesGenerados", ["_id" => $mongo2->String2MongoId($idMongo)], ["rep_estado" => "Archivo generado", "rep_tramas" => [$archivo . $extExcel]]) > 0) {
                $ok++;
            } else {
                $error = "No se pudo actualizar el estado";
            }
        } else {
            $errores = [];
            //tuvo errores        
            $catalogoErroresKitchen = array(
                "0" => "The job ran without a problem",
                "1" => "Errors occurred during processing",
                "2" => "An unexpected error occurred during loading / running of the job",
                "7" => "The job couldn't be loaded from XML or the Repository",
                "8" => "Error loading steps or plugins (error in loading one of the plugins mostly)",
                "9" => "Command line usage printing",
            );
            //separemos los errores
            $cuantosErrores = strlen($ejecucion);
            for ($i = 1; $i <= $cuantosErrores; $i++) {
                $codigoError = substr($ejecucion, $i - 1, 1);
                if (isset($catalogoErroresKitchen[$codigoError])) {
                    $errores[] = $catalogoErroresKitchen[$codigoError];
                }
            }
            $mongo2 = new MYMONGODB();
            if ($mongo2->actualizar("cbReportesGenerados", ["_id" => $mongo2->String2MongoId($idMongo)], ["rep_estado" => "Error al generar", "rep_errorEtl" => $errores]) > 0) {
                $ok++;
            } else {
                $error = "No se pudo actualizar el estado de error";
            }
        }
    } else {
        $error = "Comando vacío";
    }
    if ($ok > 0) {
        return "ok";
    } else {
        return $error;
    }
}
function getCampania($carteraId)
{
}
?>
<?

//_FIN_DE_ARCHIVO 
?>