<?
require_once "../comunes/top.inc.php";
require_once "../cubos/classes/class.cuCubos.php";
require_once "../bi/classes/class.biGraficos.php";

if (!isset($_REQUEST["act"])) {
    exit;
}
$bigraf = new biGraficos();
$bigraf->checkStructure();
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];

switch ($act) {
    case "inicializa":
        //lee los cubos en los que tiene permisos este usuario
        require_once "../permisos/classes/class.permisoUniversal.php";
        $cubos = [];
        $db->query($db->mkSQL("SELECT * FROM cucubos ORDER BY cuCubos_nombre ASC"));
        while ($row = $db->fetchRow()) {
            $row['id'] = $row['cuCubos_id'];
            $permisoUniversal = new permisoUniversal();
            $cuboConPermiso = $permisoUniversal->setPreparaPermisosUniversales([$row], "cucubos", expect_safe_html($_SESSION[MID . "rolesId"]));
            //si no se han seteado permisos
            if (count($cuboConPermiso) == 0) {
                //no tiene permiso
            } else {
                $cubos[] = [
                    'id' => $row['cuCubos_id'],
                    'nombre' => $row['cuCubos_nombre']
                ];
            }
        }
        $json['cubos'] = $cubos;
        $_SESSION[MID . 'cubosPermitidos'] = $cubos;
        //lee si el usuario puede crear y editar graficos
        $json['editor'] = $Central->conPermiso('Business Intelligence,Editor de Gráficos');
        //lee todos los gráficos creados  por este usuario
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        $graficosPublicos = $bigraf->graficosPublicos();
        $json['graficosPublicos'] = $graficosPublicos;
        $cnf = getConf('Business Intelligence');
        $tiempoDeVida = $cnf['Tiempo de vida de datos de cubos'][0];
        $json['tiempoDeVida'] = $tiempoDeVida;
        $json['quienSoy'] = $_SESSION[MID . 'userId'];
        break;
    case "nuevoGrafico":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $carpeta = expect_safe_html($d['carpeta']);
        if ($carpeta == $lang['No Agrupados']) {
            $carpeta = '';
        }
        $bigraf->nuevoGrafico($carpeta);
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "publicarGrafico":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $graf = expect_safe_html($d['graf']);
        $bigraf->publicarGrafico($graf['biGraficos_id']);
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "despublicarGrafico":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $graf = expect_safe_html($d['graf']);
        $bigraf->despublicarGrafico($graf['biGraficos_id']);
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "borrarGrafico":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $graf = expect_safe_html($d['graf']);
        $miGrafico = $bigraf->miGrafico($graf['biGraficos_id']);
        $bigraf->borrarGrafico($graf['biGraficos_id']);
        
        // Borramos vista relacionada
        $cub = new cuCubos();
        $cub->initFromDB($miGrafico['biGraficos_cuboId']);
        require_once '../cubos/plugins/cu.' . $cub->get('plugin') . '.class.php';
        $plug = new ('cuPG' . $cub->get('plugin'));
        $nombreVista = "vw_{$cub->get('plugin')}_{$graf['biGraficos_id']}";
        $mdb = new MYMONGODB();
        $mdb->borrarVista($nombreVista);

        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "guardaValor":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $graficoId = expect_integer($d['graficoId']);
        $campo = expect_safe_html($d['campo']);
        $valor = expect_safe_html($d['valor']);
        if ($valor != '') {
            $bigraf->guardaValor($graficoId, $campo, $valor);
        }
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "guardaCarpeta":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $carpetaAnterior = expect_safe_html($d['carpetaAnterior']);
        $carpetaNueva = expect_safe_html($d['carpetaNueva']);
        if ($carpetaNueva != '') {
            $bigraf->guardaCarpeta($carpetaAnterior, $carpetaNueva);
        }
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "cambiaFuenteDatos":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        $graficoId = expect_integer($d['graficoId']);
        if ($cuboId && $graficoId) {
            $bigraf->cambiaFuenteDatos($graficoId, $cuboId);
        }
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "guardarConfiguracion":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $graficoId = expect_integer($d['graficoId']);
        $config_json = expect_safe_html($d['config_json']);
        $limite = expect_integer($d['limite'] ?? 0);
        $bigraf->guardarConfiguracion($graficoId, $config_json, $limite);
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        break;
    case "copiarGrafico":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $grafico = expect_safe_html($d['graf']);
        $graficoCopiado = $bigraf->copiarGrafico($grafico);
        if($grafico['biGraficos_tipo'] === 'HEREDADO'){
            $cub = new cuCubos();
            $cub->initFromDB($grafico['biGraficos_cuboId']);
            $plugin = $cub->cargarTipoPlugin($cub->get('plugin'));
            if($plugin['tipo'] === 'new'){
                $bigraf->crearVistaCubo($plugin['plugin'], $cub, $grafico['biGraficos_configuracion'], $graficoCopiado);
            }
        }
        $misGraficos = $bigraf->misGraficos();
        $json['misGraficos'] = $misGraficos;
        $json['graficoCopiado'] = $graficoCopiado;
        break;
    case "crearGraficoHeredado":
        if (!$Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            exit;
        }
        $d = jsonStart();
        $grafico = expect_safe_html($d['grafico']);
        $configuracion = expect_safe_html($d['configuracion']);
        
        $cub = new cuCubos();
        $cub->initFromDB($grafico['biGraficos_cuboId']);
        $plugin = $cub->cargarTipoPlugin($cub->get('plugin'));
        if($plugin['tipo'] !== false){
            if($plugin['tipo'] === 'new'){
                
                $graficoHeredado = $bigraf->nuevoGraficoHeredado($grafico, $configuracion);
                if ($graficoHeredado) {
                    $miGrafico = $bigraf->miGrafico($graficoHeredado);
                    if ($miGrafico) {
                        if ($miGrafico['biGraficos_tipo'] === 'HEREDADO') {

                            $plug = $plugin['plugin'];

                            $configuracion = json_decode(mb_convert_encoding($miGrafico['biGraficos_configuracion'], 'UTF-8', 'ISO-8859-1'), true);
                            $configuracion = $bigraf->utf8_to_latin1_recursive($configuracion);

                            $bigraf->crearVistaCubo($plugin['plugin'], $cub, $configuracion, $graficoHeredado);
                        }
                    }
                }
                $misGraficos = $bigraf->misGraficos();
                $json['misGraficos'] = $misGraficos;
                $json['graficoCopiado'] = $graficoHeredado;
                $json['error'] = null;

            }else{
                $json['error'] = "Cubo no permite la creación de este tipo de gráfico";
            }
        }else{
            $json['error'] = "No se pudo cargar el plugin.";
        }
        break;
    case "nroRegistrosDatosUnCubo":
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);
        if ($cuboId) {
            $cub = new cuCubos();
            $cub->initFromDB($cuboId);
            require_once '../cubos/plugins/cu.' . $cub->get('plugin') . '.class.php';
            $plug = new ('cuPG' . $cub->get('plugin'));

            $nroRegistros = $plug->count();

            $json['datos']['nroRegistros'] = $nroRegistros;
        }
        break;
    case "cargaDatosUnCubo":
        ini_set('memory_limit', '5000M');
        ini_set('max_execution_time', 6000);
        $d = jsonStart();
        $cuboId = expect_integer($d['cuboId']);

        $datos = [];
        $error = null;
        $nroRegistros = 0;

        if ($cuboId) {
            $cub = new cuCubos();
            $cub->initFromDB($cuboId);
            $plugin = $cub->cargarTipoPlugin($cub->get('plugin'));
            if($plugin['tipo'] !== false){

                $plug = $plugin['plugin']; // Asignamos el plugin verificado e instanciado.
                $datos = [];
                $nroRegistros = $plug->count();
                switch ($plugin['tipo']) {
                    case 'new':
                        $limite = expect_integer($d['limite'] ?? 0);
                        $graficoId = expect_integer($d['graficoId']);
                        if($graficoId){

                            $miGrafico = $bigraf->miGrafico($graficoId);
                            if ($miGrafico) {
                                if ($miGrafico['biGraficos_tipo'] === 'ORIGINAL') {
                                    $datos = $plug->returnAll($limite);
                                } elseif ($miGrafico['biGraficos_tipo'] === 'HEREDADO') {

                                    $configuracion = json_decode(mb_convert_encoding($miGrafico['biGraficos_configuracion'], 'UTF-8', 'ISO-8859-1'), true);
                                    $configuracion = $bigraf->utf8_to_latin1_recursive($configuracion);
                                    $camposMapeo = $plug->getMapeoCampos();
                                    $camposMapeoFinales = [];
                                    foreach ($configuracion['rows'] as $key => $value) {
                                        $camposMapeoFinales[$value] = $camposMapeo[$value];
                                        $datos[0][] = ['header' => $value, 'mongo' => $camposMapeoFinales[$value]['mdb']];
                                    }
                                    foreach ($configuracion['cols'] as $key => $value) {
                                        $camposMapeoFinales[$key] = $camposMapeo[$key];
                                        foreach ($value as $operacion) {
                                            $datos[0][] = ['header' => $key . '_' . $operacion, 'mongo' => $camposMapeoFinales[$key]['mdb'] . '_' . $operacion];
                                        }
                                    }
                                    $datos[0][] = ['header' => 'total_registros', 'mongo' => 'total_registros'];

                                    $mdb = new MYMONGODB();
                                    $resultado = $mdb->buscar("vw_{$cub->get('plugin')}_{$graficoId}");
                                    while ($doc = $mdb->siguiente()) {
                                        $dato = [];
                                        foreach ($datos[0] as $value) {
                                            $dato[] = $doc[$value['mongo']];
                                        }
                                        $datos[] = $dato;
                                    }
                                    $datos[0] = array_map(function ($n) {
                                        return $n['header'];
                                    }, $datos[0]);
                                } else {
                                    $error = 'Tipo gráfico no reconocido.';
                                }
                            }
                        }
                        break;

                    case 'old':
                        //construya la fila de titulos
                        $titulos = [];
                        //y normaliza las filas de datos
                        $datosOld = [];
                        $all = $plug->returnAll();
                        foreach ($all as $row) {
                            foreach ($row as $key => $val) {
                                if (!in_array($key, $titulos)) {
                                    $titulos[] = $key;
                                }
                            }
                        }
                        sort($titulos);
                        foreach ($all as $row) {
                            $rowNormalizada = [];
                            foreach ($titulos as $key) {
                                $rowNormalizada[] = isset($row[$key]) ? $row[$key] : '';
                            }
                            $datosOld[] = $rowNormalizada;
                        }
                        $datos = array_merge([$titulos], $datosOld);
                        break;
                }
 
            }else{
                $error = $plugin['error'];
            }
        }else{
            $error = 'Cubo campo requerido.';
        }
        $json['datos'] = $datos;
        $json['error'] = $error;
        $json['nroRegistros'] = $nroRegistros;
        break;
}
jsonEnd($json, $limpiar);
?><? //_FIN_DE_ARCHIVO ?>