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

function diferenciaDias($fechaUnix1, $fechaUnix2) {
    //<editor-fold defaultstate="collapsed" desc=" código ">
    $fechaDate1 = date("Y-m-d", $fechaUnix1);
    $fechaDate2 = date("Y-m-d", $fechaUnix2);

    $fecha1 = new DateTime($fechaDate1);
    $fecha2 = new DateTime($fechaDate2);
    $diff = $fecha1->diff($fecha2);
    return $diff->days;
    //</editor-fold>    
}

function extractKeyWords($string) {
    //<editor-fold defaultstate="collapsed" desc=" código ">
    //funcion helper GEAM para extraer keywords de una frase o string
    //para usarla como nombre de archivo, por ejemplo, asociado a este string (de un documento en fabrica de credito)
    mb_internal_encoding('UTF-8');
    //escribo a continuacion todos los stopwords en español que quiero remover
    $stopwords = array('a', 'ante', 'bajo', 'cabe', 'con', 'contra', 'de', 'desde', 'durante', 'en', 'entre', 'hacia', 'hasta', 'mediante', 'para', 'por', 'segun', 'sin', 'so', 'sobre', 'tras', 'versus', 'vía', 'si', 'del', 'el', 'la');
    $string = preg_replace('/[\pP]/u', '', trim(preg_replace('/\s\s+/iu', '', mb_strtolower($string))));
    $matchWords = array_filter(explode(' ', $string), function ($item) use ($stopwords) {
        return !($item == '' || in_array($item, $stopwords) || mb_strlen($item) <= 2 || is_numeric($item));
    });
    $wordCountArr = array_count_values($matchWords);
    if (count($wordCountArr) > 3) {
        $wordCountArr2 = array_slice($wordCountArr, 0, 2);
        $wordCountArr2[] = end($wordCountArr);
        $wordCountArr = $wordCountArr2;
    }
    arsort($wordCountArr);
    return array_keys(array_slice($wordCountArr, 0, 10));
    //</editor-fold>
}

function traerCampania($campaniaId) {
    //<editor-fold defaultstate="collapsed" desc=" Trae la cabecera según el id ">
    $mongo = new MYMONGODB();
    $coleccion = "fcCampaniasOriginacion";
    $condicion = array("_id" => $mongo->String2MongoId($campaniaId));
    $mongo->buscar($coleccion, $condicion);
    $cabecera = [];
    while ($row = $mongo->siguiente()) {
        $cabecera = $row;
    }
    return $cabecera;
    //</editor-fold>
}

function traerObjetoCampania($campaniaId) {
    //<editor-fold defaultstate="collapsed" desc=" código ">
    $campania = traerCampania($campaniaId);
   
    foreach ($campania as $key2 => $value2) {
        if ($key2 == "fechaCreacion") {
            $campania["fechaCreacion"] = date('Y-m-d', $value2);
        }
        if ($key2 == "fechaInicioComercial") {
            $campania["fechaInicioComercial"] = date('Y-m-d', $value2);
        }
        if ($key2 == "fechaFinComercial") {
            $campania["fechaFinComercial"] = date('Y-m-d', $value2);
        }
        if ($key2 == "fechaFinOperativo") {
            $campania["fechaFinOperativo"] = date('Y-m-d', $value2);
        }
    }

    $cnf = getConf("Parametrización Crédito");
    $afectacion = $cnf["Tipos de Afectacion Nuevo"];
    $tam = (count($campania["detalle"]["condiciones"]) >= count($campania["detalle"]["beneficios"])) ? count($campania["detalle"]["condiciones"]) : count($campania["detalle"]["beneficios"]);
    for ($index = 0; $index < $tam; $index++) {
        foreach ($afectacion as $value) {
            $partes = explode(':::', $value);
            if ($partes[3] == 1) {
                if (array_key_exists($index, $campania["detalle"]["condiciones"])) {
                    $campoAfectado = $campania["detalle"]["condiciones"][$index]["campoAfectado"];
                    if ($partes[0] == $campoAfectado) {
                        $campania["detalle"]["condiciones"][$index]["campoAfectado"] = ["afectacion" => $campoAfectado, "tipoDato" => $partes[1]];
//                        if ($partes[0] == "PLAZOS") {
//                            //explode(' - ', $campania["detalle"]["condiciones"][$index]["valorAsignado"])
//                            $campania["detalle"]["condiciones"][$index]["valorAsignado"] = $campania["detalle"]["condiciones"][$index]["valorAsignado"]; 
//                        }
                    }
                }
                if (array_key_exists($index, $campania["detalle"]["beneficios"])) {
                    $campoAfectado = $campania["detalle"]["beneficios"][$index]["campoAfectado"];
                    if ($partes[0] == $campoAfectado) {
                        $campania["detalle"]["beneficios"][$index]["campoAfectado"] = ["afectacion" => $campoAfectado, "tipoDato" => $partes[1]];
                    }
                }
            }
        }
    }

    return $campania;
    //</editor-fold> 
}

function guardarNuevo($cabecera, $detalle) {
    //<editor-fold defaultstate="collapsed" desc=" Método que guarda una nueva campaña ">
    $mongo = new MYMONGODB();
    $ajusteTimeFin = (24 * 3600) - 59;
    //$objCampania = [];
    //*********************
    //CABECERA    

    $nombre = "";
    $wizardSelected = "";
    $wizardSelectedRuc = "";
    $descripcion = "";
    $conRecursoConcesionario = 0;
    $conObsequio = 0;
    $fechaInicioComercial = $fechaFinComercial = $fechaFinOperativo = 0;
    $diasHastaFinComercial = "";
    $diasHastaFinOperativo = "";
    $fechaCreacion = time();
    $horaCreacion = date('H:i:s', $fechaCreacion);

    foreach ($cabecera as $key2 => $value2) {
        if ($key2 == "nombre") {
            $nombre = $value2;
        }
        if ($key2 == "descripcion") {
            $descripcion = $value2;
        }
        if ($key2 == "fechaInicioComercial") {
            $fechaInicioComercial = strtotime($value2);
        }
        if ($key2 == "fechaFinComercial") {
            $fechaFinComercial = strtotime($value2);
        }
        if ($key2 == "fechaFinOperativo") {
            $fechaFinOperativo = strtotime($value2);
        }
        if ($key2 == "wizardSelected") {
            $wizardSelected = $value2;
        }
        if ($key2 == "wizardSelectedRuc") {
            $wizardSelectedRuc = $value2;
        }
        if ($key2 == "conRecursoConcesionario") {
            $conRecursoConcesionario = $value2;
        }
        if ($key2 == "conObsequio") {
            $conObsequio = $value2;
        }

//        $fechaFinComercial = $fechaFinComercial + $ajusteTimeFin;
//        $fechaFinOperativo = $fechaFinOperativo + $ajusteTimeFin;

        $diasHastaFinComercial = diferenciaDias($fechaFinComercial, $fechaInicioComercial);
        $diasHastaFinOperativo = diferenciaDias($fechaFinOperativo, $fechaInicioComercial);
    }

    $nombreFormateado = iconv("ISO-8859-1", "UTF-8", $nombre);

    $objCampania = [
        //"campaniaId" => $campaniaId,
        "nombre" => (string) $nombreFormateado,
        "descripcion" => $descripcion,
        "fechaInicioComercial" => $fechaInicioComercial,
        "fechaFinComercial" => $fechaFinComercial,
        "fechaFinOperativo" => $fechaFinOperativo,
        "diasHastaFinComercial" => $diasHastaFinComercial,
        "diasHastaFinOperativo" => $diasHastaFinOperativo,
        "wizardSelected" => $wizardSelected,
        "wizardSelectedRuc" => $wizardSelectedRuc,
        //"priorizar" => $priorizar,
        "conRecursoConcesionario" => ($conRecursoConcesionario) ? (int) 1 : (int) 0,
        "conObsequio" => ($conObsequio) ? (int) 1 : (int) 0,
        "estado" => 1,
        "fechaCreacion" => (int) $fechaCreacion,
        "horaCreacion" => (string) $horaCreacion,
        "fechaEliminacion" => 0,
        "usuarioCreacion" => strtoupper($_SESSION[MID . "userNombre"]),
        "usuarioIdCreacion" => (int) $_SESSION[MID . "userId"],
        "detalle" => []
    ];

    //*********************
    //DETALE 
    //Por el momento se va a guardar solo un detalle para poder poner en la cabecera el tipoDetalle, con esa configuración solo podria ponerse un detalle en la campaña
    $sucursales = [];
    $subTipoDetalle = [];
    $productos = [];
    $condiciones = [];
    $beneficios = [];
    
    $formatear = function (string $valor): int {
        return (int) $valor;
    };
    
    foreach ($detalle as $key => $value) {
        if ($key == "sucursal") {
            foreach ($value as $subItem) {
                array_push($sucursales, ["id" => (int) $subItem["id"], "nombre" => strtoupper($subItem["nombre"])]);
            }
        }

        if ($key == "subtipoDetalle") {
            //$idSegunDetalle = "";
            foreach ($value as $subItem) {
                //                if ($detalle["tipoDetalle"] == "POR ARTICULO") { 
                //                    $idSegunDetalle = (string) $subItem["id"];
                //                } else {
                //                    $idSegunDetalle = (int) $subItem["id"];
                //                }
                if (array_key_exists("personalizo", $subItem)) {
                    array_push($subTipoDetalle, ["id" => (int) $subItem["id"], "nombre" => strtoupper($subItem["nombre"]), "personalizo" => (int) 1]);
                } else {
                    array_push($subTipoDetalle, ["id" => (int) $subItem["id"], "nombre" => strtoupper($subItem["nombre"])]);
                }
            }
        }

        if ($key == "productos") {
            foreach ($value as $subItem) {
                $keys = array_keys($subItem);
                $producto = [];
                foreach ($keys as $key) {
                    if ($key == "nombre") {
                        $producto[$key] = strtoupper($subItem[$key]);
                    } else if ($key == "idPadre" || $key == "id") {
                        $producto[$key] = (int) $subItem[$key];
                    } else if ($key == "codigo") {
                        $producto[$key] = (string) $subItem[$key];
                    }else if ($key == "ctArticulos_gama"){
                        $producto['gama'] = (string) $subItem[$key];
                    }
                }
                array_push($productos, $producto);

//                array_push($productos, ["id" => (int) $subItem["id"], 
//                    "nombre" => strtoupper($subItem["nombre"]),
//                    "codigo" => (string) $subItem["codigo"],
//                    "idPadre" => (int) $subItem["idPadre"]]);
            }
        }

        if ($key == "condiciones") {
            $auxCondicion = ["campoAfectado" => "", "valorAsignado" => 0.0];
            foreach ($value as $subItem) {
                $auxCondicion["campoAfectado"] = (string) $subItem["campoAfectado"]["afectacion"];
                //TODO: Validar el formateado de los datos con los diferentes "tipoDato"
                if ($subItem["campoAfectado"]["tipoDato"] == "PORCENTAJE") {
                    $auxCondicion["valorAsignado"] = (float) $subItem["valorAsignado"];
                }

                if ($subItem["campoAfectado"]["tipoDato"] == "NUMERO") {
                    if ($subItem["campoAfectado"]["afectacion"] == "PLAZOS") {
                        //implode(" - ", $subItem["valorAsignado"]); fn($value): int => $value * 2
                        
                        //TODO:VALIDAR QUE HACER CON LA DECLARACION DE ESTA FUNCION CALLBACK
                        //array_map(fn($value): int => (int) $value, $subItem["valorAsignado"])
                        
                        $auxCondicion["valorAsignado"] = array_map($formatear, $subItem["valorAsignado"]);
                    } else {
                        $auxCondicion["valorAsignado"] = (int) $subItem["valorAsignado"];
                    }
                }

                if ($subItem["campoAfectado"]["tipoDato"] == "MONTO") {
                    $auxCondicion["valorAsignado"] = (float) $subItem["valorAsignado"];
                }
                array_push($condiciones, $auxCondicion);
            }
        }
        if ($key == "beneficios") {
            $auxBeneficio = ["campoAfectado" => "", "valorAsignado" => 0.0];
            foreach ($value as $subItem) {
                $auxBeneficio["campoAfectado"] = (string) $subItem["campoAfectado"]["afectacion"];
                //TODO: Validar el formateado de los datos con los diferentes "tipoDato"
                if ($subItem["campoAfectado"]["tipoDato"] == "PORCENTAJE") {
                    $auxBeneficio["valorAsignado"] = (float) $subItem["valorAsignado"];
                }
                if ($subItem["campoAfectado"]["tipoDato"] == "NUMERO") {
                    $auxBeneficio["valorAsignado"] = (int) $subItem["valorAsignado"];
                }
                if ($subItem["campoAfectado"]["tipoDato"] == "EXCLUYENTE") {
                    $auxBeneficio["valorAsignado"] =  $subItem["valorAsignado"] == true ? 0 : 1;
                }
                if ($subItem["campoAfectado"]["tipoDato"] == "MONTO") {
                    $auxBeneficio["valorAsignado"] = (float) $subItem["valorAsignado"];
                    $auxBeneficio["baseImponible"] = (int) $subItem["baseImponible"];
                }
                array_push($beneficios, $auxBeneficio);
                if (array_key_exists("baseImponible", $auxBeneficio)) {  
                    unset($auxBeneficio["baseImponible"]);
                }
            }
        }
    }

    //Objeto para guardar el detalle de la campaña
    $detalleCondiciones = [
        "sucursal" => $sucursales,
        "tipoDetalle" => $detalle["tipoDetalle"],
        "subtipoDetalle" => $subTipoDetalle,
        "productos" => $productos,
        "condiciones" => $condiciones,
        "beneficios" => $beneficios,
        "obsequios" => $detalle["obsequios"]
    ];

    $objCampania["detalle"] = $detalleCondiciones;
    $resGuardado = $mongo->guardar('fcCampaniasOriginacion', $objCampania);

    if ($resGuardado > 0) {
        return 1;
    } else {
        return 0;
    }
    //</editor-fold>
}


function existeNombre($nombre, $empresa) {
    //<editor-fold defaultstate="collapsed" desc=" Verifica que no se repitan los nombres en las campañas ">
    //PHP 8 -- utf8_2_decode
    $nombreUtf8 = utf8_decode($nombre);

    $existe = 0;

    $mongo = new MYMONGODB();
    $coleccion = "fcCampaniasOriginacion";
    $condicion = ["estado" => 1,
        "wizardSelected" => $empresa,
            //"campaniaId" => ['$ne' => (int) $campaniaId]
    ];

    $mongo->buscar($coleccion, $condicion);

    while ($row = $mongo->siguiente()) {
        //PHP 8 -- utf8_2_decode
        $tenemos[] = utf8_decode($row["nombre"]);
    }
//    trigger_error("campaña---------");
//    trigger_error($nombre);
//    trigger_error($nombreUtf8);
//    trigger_error(json_encode_any($tenemos));
    foreach ($tenemos as $value) {
        if ($value == $nombreUtf8) {
            $existe = 1;
        }
    }
    return $existe;
    //</editor-fold>
}

switch ($act) {
    case "editarFechasCampania":
        $d = jsonStart();
        $campania = $d['campania'];
        $mongo = new MYMONGODB();

        $c = $mongo->actualizar("fcCampaniasOriginacion",
                ['_id' => $mongo->String2MongoId($campania["id"])],
                ['fechaInicioComercial' => (int) $campania['fechaInicioComercial'],
                    'fechaFinComercial' => (int) $campania['fechaFinComercial'],
                    'fechaModificacion' => time(),
                    'usuarioIdModificoFecha' => (int) $_SESSION[MID . "userId"],
                    'usuarioModificoFecha' => strtoupper($_SESSION[MID . "userNombre"])]);

        if ($c) {
            $json['respuesta'] = 'Modificado con exito';
        } else {
            $json['respuesta'] = 'No se pudo modificar';
        }

        break;
        
    case "verificarRangoFechas":
        //<editor-fold defaultstate="collapsed" desc=" Para probar si la fecha de la campaña se cruza con otra ">
        $d = jsonStart();
        $fechaInicioComercial = strtotime($d['fechaInicio']);
        $fechaFinComercial = strtotime($d['fechaFin']);
        $wizard = $d['wizard'];

        $mongo = new MYMONGODB();
        $coleccion = "fcCampaniasOriginacion";

        $condicion = [//"fechaInicioComercial" => ['$lte' => (int) $fechaInicioComercial],
            //  "fechaFinComercial" => ['$gte' => (int) $fechaInicioComercial],
            "estado" => 1,
            "wizardSelected" => $wizard,
                //"campaniaId" => ['$ne' => (int) $campaniaId]
        ];
        $campos = ["nombre", "fechaInicioComercial", "fechaFinComercial"];
        $c = $mongo->buscar($coleccion, $condicion, $campos);

        if ($c) {
            $resultado = [];
            while ($row = $mongo->siguiente()) {
                //Para comprobar las fechas tanto en el inicio como en el fin
                if (($row['fechaInicioComercial'] <= $fechaInicioComercial && $row['fechaFinComercial'] >= $fechaInicioComercial) ||
                        ($row['fechaInicioComercial'] <= $fechaFinComercial && $row['fechaFinComercial'] >= $fechaFinComercial)) {
                    array_push($resultado, ["nombre" => $row['nombre'],
                        "fechaInicio" => date('Y-m-d', $row['fechaInicioComercial']),
                        "fechaFin" => date('Y-m-d', $row['fechaFinComercial'])]);
                }
            }
            $json["resultado"] = $resultado;
        } else {
            $json ["resultado"] = $c;
        }

        //</editor-fold>
        break;

    case "verificarNombre":
        //<editor-fold defaultstate="collapsed" desc=" Comprobar si el nombre de la campaña se repite, a diferencia de existeNombre, busca por empresa ">
        $d = jsonStart();
        $nombreCampaña = $d['nombre'];
        $nombreEmpresa = $d['wizard'];

        //trigger_error("Nombre de campaña utilizado: " . $nombreCampaña);

        $resultado = existeNombre($nombreCampaña, $nombreEmpresa);

        //        trigger_error("REsultado de la busqueda: ". $resultado);

        if ($resultado) {
            $json["resultado"] = "El nombre de campaña ya existe";
        } else {
            $json["resultado"] = "Correcto";
        }
        //</editor-fold>
        break;

    case "existeNombre":
        //<editor-fold defaultstate="collapsed" desc=" Para verificar si ya existe ese nombre de campaña ">
        $d = jsonStart();
        $nombre = $d["nombreCampania"];
        $nombreUtf8 = utf8_decode($nombre);

        $existe = 0;

        $coleccion = "fcCampaniaCabecera";
        $mongo->buscar($coleccion);

        while ($row = $mongo->siguiente()) {
            $tenemos[] = utf8_decode($row["nombre"]);
        }

        foreach ($tenemos as $value) {
            if ($value == $nombreUtf8) {
                $existe = 1;
            }
        }

        //trigger_error(print_r("****existe:". $existe , true));

        $json["resultado"] = $existe;
        //</editor-fold>
        break;

    case "getListaWizards":
        //<editor-fold defaultstate="collapsed" desc=" Extrae las empresas o wizards ">
        require_once "../comunes/classes/class.coTabulaAngular.php";
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $criterioAccion = array('tr_tipoWizard' => 'WIZARD', 'tr_estado' => 1);
        $coleccion = 'fcWizards';
        $campos = ['tr_Wizard'];
        $mongo->buscar($coleccion, $criterioAccion, $campos);
        while ($row = $mongo->siguiente()) {
            $resultado[] = strtoupper($row['tr_Wizard']); //['tr_nombreWizard' => $row['tr_Wizard']]            
        }


        sort($resultado);
        //print_h($resultado);
        //array_unshift($resultado);
        //ksort($resultado);

        $json["resultado"] = $resultado;
        //</editor-fold>
        break;

    case "getListaCalificacionesMatrizDual":
        //<editor-fold defaultstate="collapsed" desc=" Saca los tipos de calificaciones de perfiles ">
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $coleccion = "fcParamMatrizDual";
        $resultado = array();
        $result = $mongo->buscarDistinct('mdual_riesgo', $coleccion, ['mdual_tipo' => 'Titular']);
        //        array_unshift($result, 'TODOS');
        $json["resultado"] = $result;
        //</editor-fold>
        break;

    case "getArticulosPorCategoriaAutocomplete":
        //<editor-fold defaultstate="collapsed" desc=" Trae los artículos por categoría para llenar el autocomplete ">
        $autocomArticulos = new coCompleteMongo();

        $autocomArticulos->setInput($d);

        //</editor-fold>
        break;

    case "getArticulosPorCategoria":
        //<editor-fold defaultstate="collapsed" desc=" Traemos los articulos por categoria ">
        require_once("../comunes/classes/class.API.php");
        $api = new API();
        //$autocomArticulos = new coCompleteMongo();

        $d = jsonStart();
        $rucEmpresa = $d['ruc'];
        $categoria = $d['categoria'];

        $cnf = getConf("Parametrización Crédito");
        $clavesApiInventario = $cnf["API Inventarios OriginacionQA"]; //Apis de pruebas
        
        //Claves de originacion QA
        //$clavePublica = 'wtbCsFgQSsm4UIQkdzp40v3a3uUR6GTkDIjqepG1WucHM27smt5x2tO6v8FoVO4S';
        //$clavePrivada = '{e{FDa]PKXeREA)8Zo{p=X:jAmwelKejcP5mhe2wBzu9I*sE6TeBMPxLpn1lWR-t';

        $metodo = "api_getArticulosPorCategoria";
        $token = $api->generaToken($clavesApiInventario[1], $metodo);
        $arr = [
            "endPointHost" => 'https://orig.portcoll-qa.zona-link.com',
            "clavePublica" => $clavesApiInventario[0],
            "token" => $token,
            "metodo" => $metodo,
            "identificacionEmpresa" => $rucEmpresa,
            "categoriaId" => $categoria
        ];

        $datospuros = $api->comWS($arr);
        $array = json_decode($datospuros, true);

        if ($array) {
            $json["resultado"] = $array;
        } else {
            //. $categoria
            $json["resultado"]["error"] = 'No exiten articulos disponibles de esa categoria. ';
        }
//</editor-fold>
        break;
    case "getArticulosPorGama":
        $d = jsonStart();
        require_once("../comunes/classes/class.API.php");
        $cnf = getConf("Parametrización Crédito");
        $clavesApiInventario = $cnf["API Inventarios OriginacionQA"];
        $api = new API();
        $gama = array();
        $rucEmpresa = $d['ruc'];
        $gama = $d['gama'];
        array_walk($gama, function(&$valor){
            $valor = strtoupper($valor);
        });
        trigger_log("Las gamas que recibo en controlador php");
        trigger_log(json_encode($gama));
        trigger_log("El ruc que recibo ");
        trigger_log($rucEmpresa);
        $metodo = "api_obtenerArticulosPorGama";
        $token = $api->generaToken($clavesApiInventario[1], $metodo);
        $arr = [
            "endPointHost" => 'https://orig.portcoll-qa.zona-link.com',
            "clavePublica" => $clavesApiInventario[0],
            "token" => $token,
            "metodo" => $metodo,
            "rucEmpresa" => $rucEmpresa,
            "gamas" => $gama
        ];

        $datospuros = $api->postWS($arr);
        $array = json_decode($datospuros, true);
        trigger_log("Arreglo de articulos por gama ");
        trigger_log(json_encode($array));
        if ($array) {
            $json["resultado"] = $array;
        } else {
            //. $categoria
            $json["resultado"]["error"] = 'No exiten articulos disponibles de esa categoria. ';
        }

        break;

        case "getArticulosPorOrigen":
            $d = jsonStart();
            require_once("../comunes/classes/class.API.php");
            $cnf = getConf("Parametrización Crédito");
            $clavesApiInventario = $cnf["API Inventarios OriginacionQA"];
            $api = new API();
            $origen = array();
            $rucEmpresa = $d['ruc'];
            $origen = $d['origen'];
            array_walk($origen, function(&$valor){
                $valor = strtoupper($valor);
            });
            trigger_log("Los origenes que recibo en controlador php");
            trigger_log(json_encode($origen));
            trigger_log("El ruc que recibo ");
            trigger_log($rucEmpresa);
            $metodo = "api_obtenerArticulosPorOrigen";
            $token = $api->generaToken($clavesApiInventario[1], $metodo);
            $arr = [
                "endPointHost" => 'https://orig.portcoll-qa.zona-link.com',
                "clavePublica" => $clavesApiInventario[0],
                "token" => $token,
                "metodo" => $metodo,
                "rucEmpresa" => $rucEmpresa,
                "origenes" => $origen
            ];
    
            $datospuros = $api->postWS($arr);
            $array = json_decode($datospuros, true);
            if ($array) {
                $json["resultado"] = $array;
            } else {
                //. $categoria
                $json["resultado"]["error"] = 'No exiten articulos disponibles de esa categoria. ';
            }
    
            break;

    case "getMinMaxPrecioArticulos":
        //<editor-fold defaultstate="collapsed" desc="Método para traer las cantidades máximas y mínimas de los artículos por empresa">
        require_once("../comunes/classes/class.API.php");
        $api = new API();

        $d = jsonStart();
        $rucEmpresa = $d['ruc'];

        $cnf = getConf("Parametrización Crédito");
        $clavesApiInventario = $cnf["ClavesAPI Inventarios Pruebas"];

        $clavePublica = $clavesApiInventario[0];
        $clavePrivada = $clavesApiInventario[1];

        $metodo = "api_getMinMaxPrecioArticulos";
        $token = $api->generaToken($clavePrivada, $metodo);
        $arr = [
            "endPointHost" => 'https://pymes.devlink.site', //'https://pymes.prodlink.site'
            "clavePublica" => $clavePublica,
            "token" => $token,
            "metodo" => $metodo,
            "ruc" => $rucEmpresa,
            "categoriaExcluida" => 'SERVICIOS'
        ];

        $datospuros = $api->comWS($arr);

        $array = json_decode($datospuros, true);

        if ($array) {
            $json["resultado"] = $array;
        } else {
            $json["errores"] = 'Ocurrió un problema al buscar cantidades';
        }

        //</editor-fold>
        break;

    // TODO: Ver de donde se saca las gamas
    case "getListaGamas":
        //<editor-fold defaultstate="collapsed" desc=" código ">
        $d = jsonStart();
        $coleccion = 'fcWizards';
        $nombreWizard = $d['nombreWizard'];
        $mongo = new MYMONGODB();
        $criterio = [
            "tr_Wizard" => $nombreWizard,
            "tr_tipo" => "Porcentaje Entrada"
        ];
        $tiposGama = [];

        $cur = $mongo->buscarDistinct('tr_nombreRangoPrecio',$coleccion, $criterio);
        trigger_log("lista cur");
        trigger_log(json_encode_any($cur));
        foreach($cur as $gama){
            $tiposGama[] = $gama;
        }

        $json["resultado"] = $tiposGama;
        //</editor-fold>

        break;

    case "getListaTiposParametrizacion":
        //<editor-fold defaultstate="collapsed" desc=" Extrae de configuración del menú principal para la nueva condición de la campaña ">
        $d = jsonStart();
        $cnf = getConf("Parametrización Crédito");
        $aux = $cnf["Tipos de Parametrizacion Nuevo"];
        $afectacion = $cnf["Tipos de Afectacion Nuevo"];
        $plazos = $cnf["Plazos credito"];
        $tiposParametrizacion = [];
        $valoresAfectacion = [];
        $tiposAfectacion = [];

        //Ordenamos los plazos
        sort($plazos, SORT_NATURAL);

        //Ordenamos el arreglo
        sort($afectacion);

        $formatear = function (string $valor): int {
            return (int) $valor;
        };

        foreach ($afectacion as $value2) {
            $partes = explode(':::', $value2);
            $indicadoresPertenece = explode('-', $partes[2]);
            $indicadoresPertenece = array_map($formatear, $indicadoresPertenece);
            if ($partes[3] == 1) {
                array_push($tiposAfectacion,
                        [   "afectacion" => $partes[0],
                            "tipoDato" => $partes[1],
                            "pertenece" => $indicadoresPertenece]);
            }
        }

        foreach ($aux as $value) {
            $partes = explode(':::', $value);
            if ($partes[1] == 1) {
                array_push($tiposParametrizacion, ["nombre" => $partes[0], "desglosable" => (int) $partes[2]]);
            }
        }

        $json["resultado"] = ["tiposParametrizacion" => $tiposParametrizacion, "tiposAfectacion" => $tiposAfectacion, "plazos" => $plazos];
        //</editor-fold>
        break;

    case "getZonaVetada":
        //<editor-fold defaultstate="collapsed" desc=" Trae la información de las ciudades para las zonas vetadas ">
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $coleccion = "vetados";
        $condicion = array("vt_tipo" => "ZONA_VETADA");
        $zonaVetada = $mongo->buscarDistinct('vt_ciudad', $coleccion, $condicion);
        //        array_unshift($zonaVetada, 'TODAS');
        $json["resultado"] = $zonaVetada;
        //</editor-fold>
        break;

    case "getActividadVetada":
        //<editor-fold defaultstate="collapsed" desc=" Trae de CONFIGURACIÓN las actividades vetadas para la nueva condición ">
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $coleccion = "vetados";
        $condicion = array("vt_tipo" => "ACTIVIDAD_VETADA");
        $mongo->buscar($coleccion, $condicion, [], ["vt_palabraBusqueda" => 1]);
        $actividadVetada = [];
        while ($row = $mongo->siguiente()) {
            $actividadVetada[] = $row["vt_palabraBusqueda"];
        }
        //        array_unshift($actividadVetada, 'TODAS');
        $json["resultado"] = $actividadVetada;
        //</editor-fold>
        break;

    case "obtieneRuc":
        //<editor-fold defaultstate="collapsed" desc=" código ">
        $d = jsonStart();
        $nombreWizard = $d["nombreWizard"];
        $mongo = new MYMONGODB();
        $cuantos = $mongo->buscar('fcWizards', array('tr_Wizard' => $nombreWizard, "tr_tipoWizard" => "WIZARD"));
        if ($cuantos > 0) {
            while ($row = $mongo->siguiente()) {
                $ruc = $row['tr_ruc'];
            }
        }
        $json["resultado"] = $ruc;
        //</editor-fold>
        break;

    case "obtieneOrigen":
        //<editor-fold defaultstate="collapsed" desc=" Trae el origen de los productos, extrae de la configuracion del módulo ">
        $d = jsonStart();
        $nombreWizard = $d["nombreWizard"]; //KTM              

        $origen = [];
        if ($nombreWizard == "TODAS") {
            array_unshift($origen, 'TODAS');
            $json["resultado"] = $origen;
            break;
        }

        $mongo = new MYMONGODB();
        $coleccion = "fcWizards";
        $condicion = ['tr_Wizard' => $nombreWizard, 'tr_paisFabricacionOrigen' => ['$ne' => ""]];
        $origen = $mongo->buscarDistinct('tr_paisFabricacionOrigen', $coleccion, $condicion);
        if(empty($origen)){
            $origen = array(0 => 'TODOS');
        }
//        array_unshift($origen, 'TODAS');  
        $json["resultado"] = $origen;

        //</editor-fold>
        break;

    case "obtieneMarcas":
        //<editor-fold defaultstate="collapsed" desc=" Método para traer los artículos según la categoría que se envie">

        $d = jsonStart();
        $ruc = $d["ruc"];

        $articulos = [];

        //</editor-fold>
        break;

    case "obtieneCategorias":
        //<editor-fold defaultstate="collapsed" desc=" Obtiene las categorias para el detalle de la campaña ">
        $d = jsonStart();
        $ruc = $d["ruc"];

        $categorias = [];

        if (trim($ruc) == "") {
            array_unshift($categorias, 'TODAS');
            $json["resultado"] = $categorias;
            break;
        }
        
        require_once("../comunes/classes/class.API.php");
        $miapi = new API();
        
        $cnf = getConf("Parametrización Crédito");
        $clavesApiGI = $cnf["API Gestion de Inventarios OriginacionQA"];
        
        //$clavePublicaOriginacion = 'pP2S1NR2hAwJYAGxPUytMAeaMPGwXiz9XPPdBFiOVsi7ltWhXvenpaEfImjdwc9P';
        //$clavePrivadaOriginacion = 'pWKlqOJyLz!iFzW:J7DsMK5g6Txe#bv+p2!x*nvLxI0+q(CW5y-tLZ9T6fUF-zS[';

        $metodo = "api_getCategorias"; //solamente para empresas
        $token = $miapi->generaToken($clavesApiGI[1], $metodo);
        $arr = [
            "endPointHost" => 'https://orig.portcoll-qa.zona-link.com',
            "clavePublica" => $clavesApiGI[0],
            "token" => $token,
            "metodo" => $metodo,
            "identificacion" => $ruc
        ];

        $datospuros = $miapi->comWS($arr);

        $array = json_decode($datospuros, true);

        trigger_error("Traer categorias: ". json_encode_any($array));

        foreach ($array as $key => $value) {
            foreach ($value as $key2 => $value2) {
                if ($key2 == 'ID') {
                    $categorias[$value2] = $value["NOMBRE"];
                }
//                if($key2 == "NOMBRE"){
//                    $categorias[] = $value2;
//                }
            }
        }

//        trigger_error( 'Arreglo de categorias: '. json_encode_any($categorias));
//        asort($categorias);        
        array_unshift($categorias, 'TODAS');
//        ksort($categorias);


        $json["resultado"] = $array;
        //</editor-fold>
        break;

    case "obtieneCiudades":
        //<editor-fold defaultstate="collapsed" desc=" Obtiene las ciudades para los detalles de la campaña ">
        $d = jsonStart();
        $ruc = $d["ruc"];

        $oficinas = [];
        if (trim($ruc) == "") {
            array_unshift($oficinas, 'TODAS');
            $json["resultado"] = $oficinas;
            break;
        }


        require_once("../comunes/classes/class.API.php");
        $miapi = new API();

        /*         * PRODUCCION* */
        $clavePublica = 'LDexN8bw7WUoQhug1Fk9aSEE2QMJpnZvUPsw98xcofT7y0hr6RI66bwuP9H9pW4k'; //'FAH4FyZlHIYIC5pCNL7G7cfHOQ13zMkDmG5NhZgeShbgKObBaVs9rkaIR6khTgbq'  PRUEBAS
        $clavePrivada = 'kXENw;DZUWwCk{J]rK.iHbcY=NFPFoG{2vaEVZm106__YSj|!g(q]ju.gQlgGH0-'; //'!SLm5uQS9Y{C[.b2Kvy!_3d]G(TrsXQj2lKseLTZ8tsyX^;QkS#0(XQMJ3xyH2-^'  PRUEBAS

        $metodo = "api_getSucursales"; //solamente para empresas
        $token = $miapi->generaToken($clavePrivada, $metodo);
        $arr = [
            "endPointHost" => 'https://pymes.prodlink.site', //'https://pymes.devlink.site',
            "clavePublica" => $clavePublica,
            "token" => $token,
            "metodo" => $metodo,
            "ruc" => $ruc, // '0990304211001',
            "tipo" => 'CIUDAD'
        ];

        $datospuros = $miapi->comWS($arr);
        $datosFinales = unserialize(base64_decode($datospuros));

        foreach ($datosFinales as $value) {
            foreach ($value as $value2) {
                $oficinas[] = $value2["emSucursales_nombre"];
            }
        }

        asort($oficinas);
//        array_unshift($oficinas, 'TODAS');                  
        ksort($oficinas);

        $json["resultado"] = $oficinas;
        //</editor-fold>
        break;

    case "obtieneZonas":
        //<editor-fold defaultstate="collapsed" desc=" Obtiene las zonas para presentar en el nuevo detalle de la campaña ">
        $d = jsonStart();
        $ruc = $d["ruc"];

        $oficinas = [];
        if (trim($ruc) == "") {
            array_unshift($oficinas, 'TODAS');
            $json["resultado"] = $oficinas;
            break;
        }

        require_once("../comunes/classes/class.API.php");
        $miapi = new API();

        /*         * PRODUCCION* */
        $clavePublica = 'LDexN8bw7WUoQhug1Fk9aSEE2QMJpnZvUPsw98xcofT7y0hr6RI66bwuP9H9pW4k'; //'FAH4FyZlHIYIC5pCNL7G7cfHOQ13zMkDmG5NhZgeShbgKObBaVs9rkaIR6khTgbq'  PRUEBAS
        $clavePrivada = 'kXENw;DZUWwCk{J]rK.iHbcY=NFPFoG{2vaEVZm106__YSj|!g(q]ju.gQlgGH0-'; //'!SLm5uQS9Y{C[.b2Kvy!_3d]G(TrsXQj2lKseLTZ8tsyX^;QkS#0(XQMJ3xyH2-^'  PRUEBAS

        $metodo = "api_getSucursales"; //solamente para empresas
        $token = $miapi->generaToken($clavePrivada, $metodo);
        $arr = [
            "endPointHost" => 'https://pymes.prodlink.site', //'https://pymes.devlink.site',
            "clavePublica" => $clavePublica,
            "token" => $token,
            "metodo" => $metodo,
            "ruc" => $ruc,
            "tipo" => 'ZONA' // '0990304211001',
        ];

        $datospuros = $miapi->comWS($arr);
        $datosFinales = unserialize(base64_decode($datospuros));
        foreach ($datosFinales as $value) {
            foreach ($value as $value2) {
                $oficinas[] = $value2["emSucursales_nombre"];
            }
        }

        asort($oficinas);
//        array_unshift($oficinas, 'TODAS');                  
        ksort($oficinas);

        $json["resultado"] = $oficinas;
        //</editor-fold>
        break;
    case "getPlazos":
        //<editor-fold defaultstate="collapsed" desc=" Trae los plazos de configuración ">
        $d = jsonStart();
        $plazos = [];
        $cnf = getConf("Parametrización Crédito");
        $plazos = isset($cnf['Plazos credito']) ? $cnf['Plazos credito'] : [3, 6, 9, 12, 18, 24, 30, 36, 48, 60, 72];

        asort($plazos);
        //        array_unshift($plazos, 'TODAS');                  
        ksort($plazos);

        $json["resultado"] = $plazos;
        //</editor-fold>
        break;

    case "obtieneSucursalesConcesionarios":
        //<editor-fold defaultstate="collapsed" desc=" Obtiene las sucursules segun la empresa seleccionada  ">
        $d = jsonStart();
        $ruc = $d["ruc"];

        require_once("../comunes/classes/class.API.php");
        $miapi = new API();
        
        $cnf = getConf("Parametrización Crédito");
        $clavesApiEmpresas = $cnf["API Empresas OriginacionQA"];

        /*         * QA* */
        //$clavePublicaOriginacion = 'qkVGaSy5qGNqIjc2yDQUvSOGirh5pLZ3NqswsjiL7UMQ473dqrpGIiQpzpVrYlxA';
        //$clavePrivadaOriginacion = 'w]1Hy|/K{AcGJkD{]n}#:IU68{sZK}ongb{.]s1J^pQ9^JN+y2Vz[3}#XV*e.=tP';

        $metodo = "api_getSucursalesCampanias"; //solamente para empresas
        $token = $miapi->generaToken($clavesApiEmpresas[1], $metodo);
        $arr = [
            "endPointHost" => 'https://orig.portcoll-qa.zona-link.com', //"https://pymes.prodlink.site"
            "clavePublica" => $clavesApiEmpresas[0],
            "token" => $token,
            "metodo" => $metodo,
            "ruc" => $ruc
        ];

        $datospuros = $miapi->comWS($arr);
        $datosFinales = unserialize(base64_decode($datospuros));
        
        $oficinas = [];
        $niveles = [];
        $idSucursales = [];
        $idSucursalesAux = [];
        $tam = count($datosFinales);
        $cuantos = 0;
        $nivel = 0;
        while ($tam > $cuantos) {
            $cuantos = 0;
            foreach ($datosFinales as $key4 => $value4) {
                if ($value4["sucursalPadreId"] == 0 && !array_key_exists("procesado", $value4)) {
                    array_push($idSucursales, $value4["sucursalId"]);
                    $niveles[$nivel] = $value4["sucursalNombres"];
                    $datosFinales[$key4]["procesado"] = 1;
                    $nivel++;
                }
            }

            if (count($idSucursales) > 0) {
                $niveles[$nivel] = [];
                foreach ($idSucursales as $key => $value) {
                    foreach ($datosFinales as $key1 => $value1) {
                        if ((int) $value == (int) $value1["sucursalPadreId"] && !array_key_exists("procesado", $value1)) {
                            array_push($idSucursalesAux, $value1["sucursalId"]);
                            array_push($niveles[$nivel], [$value1["sucursalNombres"], $value1["sucursalPadreId"], $value1["sucursalId"]]);
                            $datosFinales[$key1]["procesado"] = 1;
                        }
                    }
                    array_splice($idSucursales, $key, 1);
                }
                $nivel++;
            }

            if (count($idSucursalesAux) > 0) {
                $niveles[$nivel] = [];
                foreach ($idSucursalesAux as $key2 => $value2) {
                    foreach ($datosFinales as $key3 => $value3) {
                        if ((int) $value2 == (int) $value3["sucursalPadreId"] && !array_key_exists("procesado", $value3)) {
                            array_push($idSucursales, $value3["sucursalId"]);
                            array_push($niveles[$nivel], [$value3["sucursalNombres"], $value3["sucursalPadreId"], $value3["sucursalId"]]);
                            $datosFinales[$key3]["procesado"] = 1;
                        }
                    }
                    array_splice($idSucursalesAux, $key2, 1);
                }
                $nivel++;
            }

            foreach ($datosFinales as $value) {
                if (array_key_exists("procesado", $value)) {
                    $cuantos++;
                }
            }

            //            print_h($idSucursalesAux);
            //            print_h($idSucursales);
            //            print_h("tamaño". $tam);   
            //            print_h("cuantos". $cuantos);
            //print_h($datosFinales);
            //                else if ($nivel == 1 && count($idSucursales) > 0 && $aux1){
            //                    if (in_array($value["sucursalPadreId"], $idSucursales)) {
            //                        $index = array_search($value["sucursalPadreId"], $idSucursales);
            //                        //array_splice($idSucursales, $index, 1);
            //                        array_push($idSucursalesAux, $value["sucursalId"]);
            //                        array_push($niveles[$nivel], $value["sucursalNombres"]);
            //                        array_splice($datosFinales, $key, 1);
            //                    }
            //                    $tam = (int) count($datosFinales);
            //                    if ($tam == $key) {
            //                        print_h("tamaño: ". $tam . " key: ". $key);
            //                    }
            //                    if (count($idSucursales) == 0) { 
            //                        $nivel++;
            //                        $niveles[$nivel] = [];
            //                        $aux2 = true;
            //                        $aux1 = false;
            //                        break;
            //                    }
            //                } else if ($nivel == 2 && count($idSucursalesAux) > 0 && $aux2){
            //                    if (in_array($value["sucursalPadreId"], $idSucursalesAux)) {
            //                        $index = array_search($value["sucursalPadreId"], $idSucursalesAux);
            //                        array_splice($idSucursalesAux, $index, 1);
            //                        array_push($idSucursales, $value["sucursalId"]);
            //                        array_push($niveles[$nivel], $value["sucursalNombres"]);
            //                        array_splice($datosFinales, $key, 1);
            //                    }
            //                    if (count($idSucursalesAux) == 0) { 
            //                        $nivel++;
            //                        $niveles[$nivel] = [];
            //                        $aux2 = false;
            //                        $aux1 = true;
            //                        break;
            //                    }
            //                    //(count($idSucursalesAux) == 0) ? $nivel++ : '';
            //                }
            //}
        }

        //print_h($datosFinales);
        //print_h($niveles);
        foreach ($datosFinales as $value) {
            //foreach ($value as $value2) {
            $oficinas[] = $value;
            //$oficinas[] = $value["sucursalNombres"];
            //}
        }

        $len = count($niveles) - 1;
        if (count($niveles[$len]) == 0) {
            array_splice($niveles, $len, 1);
        }


        sort($oficinas);
        //ksort($oficinas);
        //print_h($oficinas);
        $json["resultado"] = ["oficinas" => $oficinas, "niveles" => $niveles];

        //</editor-fold>
        break;

    case "guardarCondicion":
        //<editor-fold defaultstate="collapsed" desc=" Este método es de prueba para solo guardar condiciones además de comprobar si ya existe alguna condición igual en otra campaña ">
        $d = jsonStart();
        $condicionCampania = $d["condicionCampania"];
        $wizard = expect_safe_html($d["wizardSelected"]);

        //Primero busco las campañas activas de esa empresa
        $mongo = new MYMONGODB();
        $coleccion = "fcCampaniaCabecera";
        $condicion = ["wizardSelected" => $wizard, "estado" => 1];
        $mongo->buscar($coleccion, $condicion);
        $campaniasDelWizard = [];
        $campaniasRepetidas = [];
        $cuantoDetallesRepetidos = 0;

        while ($row = $mongo->siguiente()) {
            $campaniasDelWizard[] = $row;
        }

        //Recorro cada campaña según el wizard para buscar alguna condición repetida
        foreach ($campaniasDelWizard as $value) {
            $campaniaId = $value["campaniaId"];

            $coleccion = "fcCampaniaDetalle";

            $condicion = ["campaniaId" => $campaniaId,
                "tipoDetalle" => $condicionCampania["tipoDetalle"],
                //"detalleId" => ['$gte' => (int) 410],
                //"valorAsignado" => $condicionCampania["valorAsignado"],
                //"campoAfectado" => $condicionCampania["campoAfectado"],
                "estado" => 1];
            $campos = ["sucursal", "subtipoDetalle", "afectacion"];

            $resultado = $mongo->buscar($coleccion, $condicion, $campos);

            if ($resultado) {
                while ($row = $mongo->siguiente()) {
                    //$arrAux2 = [];
                    trigger_error("consulta de las campañas con condiciones: " . json_encode_any($row));
                    $arrAux1 = array_diff($condicionCampania["subtipoDetalle"], $row["subtipoDetalle"]);
                    $arrAux2 = array_diff($condicionCampania["sucursal"], $row["sucursal"]);
                    if (count($condicionCampania["afectacion"]) == count($row["afectacion"])) {
                        $principalRepetido = false;
                        //Estamos recorriendo el arreglo de afectaciones por el momento es solo uno(una condición con diferentes afectaciones)
                        foreach ($row["afectacion"] as $key3 => $value2) {
                            trigger_error("afectaciones: " . json_encode_any($value2));
                            if ($value2["campoAfectado"] == $condicionCampania["afectacion"][$key3]["campoAfectado"] &&
                                    $value2["valorAsignado"] == $condicionCampania["afectacion"][$key3]["valorAsignado"]) {
                                $principalRepetido = true;

                                if ($principalRepetido) {
                                    $adicionalesRepetidos = false;
                                    $itemsRepetidos = 0;
                                    if (count($value2["afectacionesAdicionales"]) == count($condicionCampania["afectacion"][$key3]["afectacionesAdicionales"])) {
                                        foreach ($value2["afectacionesAdicionales"] as $key => $value3) {
                                            trigger_error("items de afectaciones adicionales: " . json_encode_any($value3));
                                            foreach ($condicionCampania["afectacion"][$key3]["afectacionesAdicionales"] as $key1 => $value4) {
                                                ($value3["campoAfectado"] == $value4["campoAfectado"] &&
                                                        $value3["valorAsignado"] == $value4["valorAsignado"]) ?
                                                                $itemsRepetidos++ : '';
                                            }
                                        }

                                        if ($itemsRepetidos == count($value2["afectacionesAdicionales"])) {
                                            $adicionalesRepetidos = true;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (count($arrAux1) == 0 && count($arrAux2) == 0 && $principalRepetido && $adicionalesRepetidos) {
                        if (!in_array($value["nombre"], $campaniasRepetidas)) {
                            array_push($campaniasRepetidas, $value["nombre"]);
                        }
                        $cuantoDetallesRepetidos++;
                    }
                }
            }
        }

        //Aquí verificamos si hay repetidos para según eso guardar la condición
        if ($cuantoDetallesRepetidos) {
            $json["resultado"]["repetidos"] = $campaniasRepetidas;
        } else {
            //Guarda si ya está definida una campaña
            if ($condicionCampania["campaniaId"]) {
                $detalleCondiciones = [
                    "campaniaId" => $condicionCampania["campaniaId"],
                    "detalleId" => traerIdSiguiente("fcCampaniaDetalle", "detalleId"),
                    "tipoDetalle" => $condicionCampania["tipoDetalle"],
                    "subtipoDetalle" => $condicionCampania["subtipoDetalle"],
                    "campoAfectado" => $condicionCampania["campoAfectado"],
                    "valorAsignado" => $condicionCampania["valorAsignado"],
                    "estado" => 1,
                    "seleccionesSubtipoAdicionales" => $condicionCampania["seleccionesSubtipoAdicionales"]
                ];

                $resDetalle = $mongo->guardar('fcCampaniaDetalle', $detalleCondiciones);

                if ($resDetalle) {
                    $json["resultado"]["correcto"] = "Condición guardada exitosamente";
                } else {
                    $json["resultado"]["error"] = "No se pudo guardar la condición";
                }
            } else {
                $json["resultado"]["correctoSinGuardar"] = $cuantoDetallesRepetidos;
            }
        }


        //</editor-fold>
        break;

    case "guardarParametrizacion":
        //<editor-fold defaultstate="collapsed" desc=" Guarda la campaña y configuraciones de condiciones, atendiendo si es nuevo o actualización de campos ">
        $d = jsonStart();
        $detalle = $d["detalleCampania"];
        $cabecera = $d["cabeceraCampania"];

        $resNuevo = guardarNuevo($cabecera, $detalle);
        if ($resNuevo == 1) {
            $json["resultado"] = 'Nueva campaña guardada correctamente';
        } elseif ($resNuevo != 1) {
            $json["resultado"]["error"] = 'Error: Error al guardar nueva Campaña.';
        } else {
            $json["resultado"]["error"] = "";
        }
        //</editor-fold>
        break;

    case "listaCampaniasActivas":
        //<editor-fold defaultstate="collapsed" desc=" Lista las campañas para presentarlos en la tabula ">
        $d = jsonStart();
        //coleccion para nueva estructura fcCampaniasOriginacion
        $coleccion = "fcCampaniasOriginacion";
        $condition = ["estado" => (int) 1];
        $campos = ["_id", "nombre", "fechaInicioComercial", "fechaFinComercial", "fechaFinOperativo", "wizardSelected", "fechaCreacion", "horaCreacion", "usuarioCreacion", "fechaModificacion"];
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

    case "listaCampaniasInactivas":
        //<editor-fold defaultstate="collapsed" desc=" Lista las campañas para presentarlos en la tabula ">
        $d = jsonStart();
        //coleccion para nueva estructura fcCampaniasOriginacion
        $coleccion = "fcCampaniasOriginacion";
        $condition = ["estado" => (int) 2];
        $campos = ["_id", "nombre", "fechaInicioComercial", "fechaFinComercial", "fechaFinOperativo", "wizardSelected", "fechaCreacion", "horaCreacion", "usuarioCreacion"];
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

    case "traerObjetoCampania":
        $d = jsonStart();
        $campaniaId = $d['campaniaId'];
        $json["resultado"] = traerObjetoCampania($campaniaId);
        break;

    case "eliminaCampania":
        //<editor-fold defaultstate="collapsed" desc=" Elimina la campaña según su id ">
        $mongo = new MYMONGODB();
        $d = jsonStart();
        $collecion = "fcCampaniasOriginacion";
        $campaniaId = $d['campaniaId'];
        $nombreCampania = $d['campaniaNombre'];
        $proceso = $d['proceso'];
        $estado = 1;
        $item1 = 'fechaActivacion';
        $item2 = 'usuarioActivacion';
        $item3 = 'usuarioIdActivacion';

        if ($proceso == 'eliminar') {
            $estado = 0;
            $item1 = 'fechaEliminacion';
            $item2 = 'usuarioEliminacion';
            $item3 = 'usuarioIdEliminacion';
        } else if ($proceso == 'inactivar') {
            $estado = 2;
            $item1 = 'fechaInactivacion';
            $item2 = 'usuarioInactivacion';
            $item3 = 'usuarioIdInactivacion';
        }
        //eliminar campos que se guardan al pagar un rubro
        $eli = ['fechaActivacion' => 1,
            'usuarioActivacion' => 1,
            'usuarioIdActivacion' => 1,
            'fechaEliminacion' => 1,
            'usuarioEliminacion' => 1,
            'usuarioIdEliminacion' => 1,
            'fechaInactivacion' => 1,
            'usuarioInactivacion' => 1,
            'usuarioIdInactivacion' => 1,
            'fechaModificacion' => 1,
            'usuarioIdModificoFecha' => 1,
            'usuarioModificoFecha' => 1];
        $mongo->eliminarCampo($collecion, ['_id' => $mongo->String2MongoId($campaniaId)], $eli, true);

        //Cambiamos el estado a 0 para eliminar esa campaña, o 2 para inactivar la campaña, o 1 para activarla
        $criterioAccion = array('_id' => $mongo->String2MongoId($campaniaId));
        $nuevaData = [
            $item1 => time(),
            $item2 => $_SESSION[MID . "userNombre"],
            $item3 => (int) $_SESSION[MID . "userId"],
            "estado" => $estado
        ];

        $res = $mongo->actualizar($collecion, $criterioAccion, $nuevaData);

        $json["resultado"] = ["cuantas" => $res, "nombre" => $nombreCampania];
        //</editor-fold> 
        break;

    case "eliminaCondicion":
        //<editor-fold defaultstate="collapsed" desc=" Método para eliminar una condición en especifico ">
        $d = jsonStart();
        $campaniaId = expect_integer($d['campaniaId']);
        $detalleId = expect_integer($d['detalleId']);

        //Buscamos en fcCampaniaDetalle según el id
        $mongo = new MYMONGODB();
        $collecion = "fcCampaniaDetalle";
        $criterioAccion = array('campaniaId' => $campaniaId, 'detalleId' => $detalleId);
        $nuevaData = ['estado' => 0];

        $resDetalle = $mongo->actualizar($collecion, $criterioAccion, $nuevaData);

        if ($resDetalle) {
            $json["resultado"] = $resDetalle;
        } else {
            $json["resultado"]["error"] = 'Error: No se puede eliminar la condición.';
        }

        //</editor-fold>
        break;

    case "actualizarCondicion":
        //<editor-fold defaultstate="collapsed" desc=" Método para actualizar el campo de valorAsignado de una condicion ">
        $d = jsonStart();
        $campaniaId = expect_integer($d['campaniaId']);
        $detalleId = expect_integer($d['detalleId']);
        $valorAsignado = expect_integer($d['valorAsignado']);

        //Buscamos en fcCampaniaDetalle según el id
        $mongo = new MYMONGODB();
        $collecion = "fcCampaniaDetalle";
        $criterioAccion = array('campaniaId' => $campaniaId, 'detalleId' => $detalleId);
        $nuevaData = ['valorAsignado' => $valorAsignado];

        $resDetalle = $mongo->actualizar($collecion, $criterioAccion, $nuevaData);

        if ($resDetalle) {
            $json["resultado"] = $resDetalle;
        } else {
            $json["resultado"]["error"] = 'Error: No se puedo modificar la condición.';
        }

        //</editor-fold>
        break;
}
jsonEnd($json, $limpiar);
?><? //_FIN_DE_ARCHIVO                                                                                   ?>
