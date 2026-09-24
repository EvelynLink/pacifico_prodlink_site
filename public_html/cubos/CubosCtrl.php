<?
require_once "../comunes/top.inc.php";
require_once "../cubos/classes/class.cuCubos.php";
if (!isset($_REQUEST["act"])) {
    exit;
}
$cuCubos = new cuCubos();
$cuCubos->checkStructure();
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = ["cuCubos_" => "cu_"];

switch ($act) {
    case "buscaTablas":
        //en MYSQL
        require_once "../comunes/classes/class.coCompleteAngular.php";
        $d = jsonStart();
        $ngComplete = new coCompleteAngular();
        $ngComplete->setInput($d);
        $limpiar = [];
        $db = new MYSQLDB();
        //lea todas las tablas de MYSQL
        $db->queryAdministrativo($db->mkSQL("SELECT table_name
		FROM information_schema.tables    
		WHERE table_type = 'BASE TABLE' AND table_schema=%Q  
		AND table_name LIKE %Q
		AND table_name NOT LIKE %Q
		ORDER BY table_name ASC", $db->getCurrentDatabaseName(), "%" . $d['filter'] . "%", "cucubos"));
        $tablas = [];
        while ($row = $db->fetchRow()) {
            $tablas[] = $row["table_name"];
        }
        $json = $ngComplete->respondeConArray($tablas);
        break;
    case "buscaColecciones":
        //en MONGODB
        require_once "../comunes/classes/class.coCompleteAngular.php";
        $d = jsonStart();
        $ngComplete = new coCompleteAngular();
        $ngComplete->setInput($d);
        $limpiar = [];
        //lea todas las colecciones de mongo
        $mdb = new MYMONGODB();
        $cols = $mdb->traerColecciones();
        $colecciones = [];
        foreach ($cols as $col) {
            $nombre = $col->getName();
            if (strpos(strtolower($nombre), strtolower($d['filter'])) !== false) {
                $colecciones[] = $nombre;
            }
        }
        sort($colecciones);
        $json = $ngComplete->respondeConArray($colecciones);
        break;
    case "getAll":
        require_once "../comunes/classes/class.coTabulaAngular.php";
        $d = jsonStart();
        $limpiar = array("cuCubos_" => "cu_");
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setOrdenDefault("cuCubos_nombre");
        $ngTabula->setQueryDatos("SELECT *
		FROM cucubos");
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(false);
        $ngTabula->setPreparaDatos(function ($item) {
            $item["cuCubos_tablas"] = unserialize($item["cuCubos_tablas"]);
            $item["cuCubos_colecciones"] = unserialize($item["cuCubos_colecciones"]);
            //verifica que exista el plugin
            $cuCubos = new cuCubos();
            $item["pluginExiste"] = $cuCubos->pluginExiste($item["cuCubos_plugin"]);
            if ($item["pluginExiste"]) {
                include_once '../cubos/plugins/cu.' . $item["cuCubos_plugin"] . ".class.php";
                $objName = "cuPG" . $item["cuCubos_plugin"];
                $plg = new $objName();
                $item["cuenta"] = $plg->count();
            }
            return $item;
        });
        $cuCubos = new cuCubos();
        $cuCubos->creaCacheCubos();
        $json = $ngTabula->responde();
        break;

    case "saveOne":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $g = jsonStart();
        $errores = array();
        $g["cu_id"] = expect_integer($g["cu_id"]);
        $g["cu_nombre"] = expect_safe_html($g["cu_nombre"]);
        $g["cu_plugin"] = expect_safe_html($g["cu_plugin"]);
        if (empty($g["cu_nombre"])) {
            $errores["cu_nombre"] = $lang["Campo no puede estar vacío"];
        }
        $nombreLimpio = trim(strip_tags(stripslashes($g["cu_nombre"])));
        if ($g["cu_nombre"] != $nombreLimpio) {
            $errores["cu_nombre"] = $lang["Debe ser un nombre de campo válido en la base de datos"];
        }
        if (strlen($g["cu_nombre"]) > 50) {
            $errores["cu_nombre"] = $lang["Debe ser un nombre de 50 caracteres o menos"];
        }
        $nombrePlugin = preg_replace("/[^a-zA-Z0-9]/", "", $g["cu_plugin"]);
        if ($g["cu_plugin"] != $nombrePlugin) {
            $errores["cu_plugin"] = $lang["El nombre del plugin solo debe contener letras y números. No debe contener espacios ni caracteres especiales"];
        }
        if ($g["cu_id"] == -1) {
            //es un pedido de insertar nuevo
            if ($g["cu_nombre"] != $cuCubos->nombresRepetidos($g["cu_nombre"])) {
                $errores["cu_nombre"] = $lang["Nombre repetido"];
            }
            if (count($errores) == 0) {
                $g["cu_id"] = $cuCubos->insert($g["cu_nombre"], $g["cu_plugin"]);
                $g["cu_tablas"] = serialize([]);
                $g["cu_colecciones"] = serialize([]);
                $g["cu_dias"] = '';
                $g["cu_horas"] = '';
                $cuCubos->creaCacheCubos();
                $json["resultado"] = $g;
                unset($json["resultado"]["errores"]);
            } else {
                $json["resultado"]["errores"] = $errores;
            }
        } else {
            //es un pedido de guardar existente
            $g["cu_id"] = expect_integer($g["cu_id"]);
            $cuCubos->initFromDB($g["cu_id"]);
            if ($g["cu_nombre"] != $cuCubos->nombresRepetidos($g["cu_nombre"])) {
                $errores["cu_nombre"] = $lang["Nombre repetido"];
            }
            if (count($errores) == 0) {
                if ($cuCubos->update($g["cu_nombre"], $g["cu_plugin"])) {
                    $cuCubos->creaCacheCubos();
                    $json["resultado"] = $g;
                    unset($json["resultado"]["errores"]);
                } else {
                    $errores["cu_nombre"] = $lang["No se actualizaron los datos"];
                    $json["resultado"]["errores"] = $errores;
                }
            } else {
                $json["resultado"]["errores"] = $errores;
            }
        }
        break;

    case "deleteOne":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $g = jsonStart();
        $g["cu_id"] = expect_integer($g["cu_id"]);
        $cuCubos->initFromDB($g["cu_id"]);
        $res = $cuCubos->delete();
        $json["resultado"] = $res;
        break;

    case "leeCubo":
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $db = new MYSQLDB();
        if ($db->query($db->mkSQL("SELECT * FROM cucubos WHERE cuCubos_id=%N", $cuboId))) {
            $row = $db->fetchRow();
            $row["cuCubos_tablas"] = unserialize($row["cuCubos_tablas"]);
            $row["cuCubos_colecciones"] = unserialize($row["cuCubos_colecciones"]);
            //verifica que exista el plugin
            $row["pluginExiste"] = $cuCubos->pluginExiste($row["cuCubos_plugin"]);
            $json["item"] = $row;
        }
        break;

    case "recreateCubo":
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $cuCubos->initFromDB($cuboId);
        $cuCubos->recreate();
        break;

    case "aniadeTabla":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $tabla = [
            'nombre' => expect_safe_html($d['tabla']),
            'identificador' => ''
        ];
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $tablas = unserialize($cuCubos->get("tablas"));
            $tablas[] = $tabla;
            usort($tablas, function ($a, $b) {
                if (strtolower($a['nombre']) > strtolower($b['nombre'])) {
                    return 1;
                } else {
                    return -1;
                }
            });
            //limpie valor vacio si existe
            if (in_array(['nombre' => '', 'identificador' => ''], $tablas)) {
                array_splice($tablas, array_search(['nombre' => '', 'identificador' => ''], $tablas), 1);
            }
            //guarde a la base
            $cuCubos->setTablas(serialize($tablas));
            $json["tab"] = $tabla;
        }
        break;

    case "quitaTabla":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $tabla = expect_safe_html($d['tabla']);
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $tablas = unserialize($cuCubos->get("tablas"));
            if (in_array($tabla, $tablas)) {
                array_splice($tablas, array_search($tabla, $tablas), 1);
                $cuCubos->setTablas(serialize($tablas));
            }
        }
        break;

    case "guardaIdentificadorTB":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $tb = expect_safe_html($d['tb']);
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $tablas = unserialize($cuCubos->get("tablas"));
            foreach ($tablas as &$tab) {
                if ($tab["nombre"] == $tb["nombre"]) {
                    $tab["identificador"] = $tb["identificador"];
                    $json["tab"] = $tab;
                    break;
                }
            }
            unset($tab);
            $cuCubos->setTablas(serialize($tablas));
        }
        break;

    case "aniadeColeccion":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $coleccion = [
            'nombre' => expect_safe_html($d['coleccion']),
            'identificador' => '_id'
        ];
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $colecciones = unserialize($cuCubos->get("colecciones"));
            $colecciones[] = $coleccion;
            usort($colecciones, function ($a, $b) {
                if (strtolower($a['nombre']) > strtolower($b['nombre'])) {
                    return 1;
                } else {
                    return -1;
                }
            });
            //limpie valor vacio si existe
            if (in_array(['nombre' => '', 'identificador' => ''], $colecciones)) {
                array_splice($colecciones, array_search(['nombre' => '', 'identificador' => ''], $colecciones), 1);
            }
            //guarde a la base
            $cuCubos->setColecciones(serialize($colecciones));
            $json["cc"] = $coleccion;
        }
        break;

    case "quitaColeccion":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $coleccion = expect_safe_html($d['coleccion']);
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $colecciones = unserialize($cuCubos->get("colecciones"));
            if (in_array($coleccion, $colecciones)) {
                array_splice($colecciones, array_search($coleccion, $colecciones), 1);
                $cuCubos->setColecciones(serialize($colecciones));
            }
        }
        break;

    case "guardaIdentificadorCC":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $cc = expect_safe_html($d['cc']);
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            $colecciones = unserialize($cuCubos->get("colecciones"));
            foreach ($colecciones as &$col) {
                if ($col["nombre"] == $cc["nombre"]) {
                    $col["identificador"] = $cc["identificador"];
                    $json["cc"] = $col;
                    break;
                }
            }
            unset($col);
            $cuCubos->setColecciones(serialize($colecciones));
        }
        break;
    case "toggleEstado":
        if (!$Central->conPermiso("Cubos,Administrador")) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $cuCubos->initFromDB($cuboId);
        if ($cuCubos->get("id") == $cuboId) {
            if ($cuCubos->get('estado') == 'Activo') {
                $nuevoEstado = 'Inactivo';
                $json['ultimoInactivo'] = time();
            } else {
                $nuevoEstado = 'Activo';
                $json['ultimoInactivo'] = 0;
            }
            $cuCubos->setEstado($nuevoEstado);
            $json['nuevoEstado'] = $nuevoEstado;
        }
        break;
}
jsonEnd($json, $limpiar);
?><? //_FIN_DE_ARCHIVO ?>