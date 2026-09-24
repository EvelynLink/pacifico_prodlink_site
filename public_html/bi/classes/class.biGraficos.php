<?
require_once("../comunes/classes/class.clase.php");
/** Administración de graficos del BI de cada usuario
 * 	@package BI
 *  @author Antonio Páez (apaezp [arroba] gmail [punto] com)
 */
class biGraficos extends Clase{
    protected $id;
    protected $nombre; //por defecto el nombre del grafico segun pivottable
    protected $autorId; //id del creador+propietario del grafico
    protected $cuboId; //id del cubo relacionado con este grafico
    protected $esPublico; //boolean si se comparte con terceros o no
    protected $config; //JSON de la configuracion del grafico en pivottable
    protected $carpeta; //agrupador de gráficos

    public function __construct()
    {
        parent::init("bigraficos", "biGraficos_");
    }

    public function misGraficos()
    {
        global $lang;
        $db = new MYSQLDB();
        $misGraficos = [];
        $misCarpetas = [];
        $db->query($db->mkSQL("SELECT bigraficos.*,cucubos.*,ususuarios.usUsuarios_nombres,usUsuarios_apellidos
        FROM bigraficos
        INNER JOIN ususuarios ON usUsuarios_id=biGraficos_autorId
        LEFT JOIN cucubos ON cuCubos_id=biGraficos_cuboId
        WHERE biGraficos_autorId=%N
        ORDER BY bigraficos.biGraficos_carpeta, bigraficos.biGraficos_nombre", $_SESSION[MID . 'userId']));
        while ($row = $db->fetchRow()) {
            if ($row['biGraficos_cuboId'] > 0) {
                //valide que tenga acceso este usuario
                $cubosPermitidos = isset($_SESSION[MID . 'cubosPermitidos']) ? $_SESSION[MID . 'cubosPermitidos'] : array();
                foreach ($cubosPermitidos as $cp) {
                    if ($cp['id'] == $row['biGraficos_cuboId']) {
                        $carpeta = $row['biGraficos_carpeta'] != '' ? $row['biGraficos_carpeta'] : $lang['No Agrupados'];
                        $misGraficos[$carpeta][] = $row;
                        $misCarpetas[] = $carpeta;
                    }
                }
            } else {
                //aun no tiene cubo asociado, deje pasar sin problema
                $carpeta = $row['biGraficos_carpeta'] != '' ? $row['biGraficos_carpeta'] : $lang['No Agrupados'];
                $misGraficos[$carpeta][] = $row;
                $misCarpetas[] = $carpeta;
            }
        }
        if (!isset($misGraficos[$lang['No Agrupados']])) {
            $misGraficos[$lang['No Agrupados']] = [];
        }
        $misCarpetas = array_unique($misCarpetas);
        sort($misCarpetas);
        return ['graficos' => $misGraficos, 'carpetas' => $misCarpetas];
    }
    public function miGrafico(int $graficoId)
    {
        $db = new MYSQLDB();
        $miGrafico = null;
        $db->query($db->mkSQL("SELECT * FROM bigraficos WHERE biGraficos_id=%N", $graficoId));
        $miGrafico = $db->fetchRow();
        return $miGrafico;
    }
    public function graficosPublicos()
    {
        global $Central;
        $db = new MYSQLDB();
        $graficosPublicos = [];
        $carpetasPublicas = [];
        $sql = $db->mkSQL("SELECT bigraficos.*,cucubos.*,ususuarios.usUsuarios_nombres,usUsuarios_apellidos
        FROM bigraficos
        INNER JOIN ususuarios ON usUsuarios_id=biGraficos_autorId
        INNER JOIN cucubos ON cuCubos_id=biGraficos_cuboId
        WHERE biGraficos_esPublico=%N", 1);
        if ($Central->conPermiso('Business Intelligence,Editor de Gráficos')) {
            $sql .= $db->mkSQL(" AND biGraficos_autorId <> %N", $_SESSION[MID . 'userId']);
        }
        $db->query($sql);
        while ($row = $db->fetchRow()) {
            if ($row['biGraficos_cuboId'] > 0) {
                //valide que tenga acceso este usuario
                $cubosPermitidos = isset($_SESSION[MID . 'cubosPermitidos']) ? $_SESSION[MID . 'cubosPermitidos'] : array();
                foreach ($cubosPermitidos as $cp) {
                    if ($cp['id'] == $row['biGraficos_cuboId']) {
                        $carpeta = $row['biGraficos_carpeta'] != '' ? $row['biGraficos_carpeta'] : $lang['No Agrupados'];
                        $graficosPublicos[$carpeta][] = $row;
                        $carpetasPublicas[] = $carpeta;
                    }
                }
            } else {
                //aun no tiene cubo asociado, deje pasar sin problema
                $carpeta = $row['biGraficos_carpeta'] != '' ? $row['biGraficos_carpeta'] : $lang['No Agrupados'];
                $graficosPublicos[$carpeta][] = $row;
                $carpetasPublicas[] = $carpeta;
            }
        }
        $carpetasPublicas = array_unique($carpetasPublicas);
        sort($carpetasPublicas);
        return ['graficos' => $graficosPublicos, 'carpetas' => $carpetasPublicas];
    }

    public function nuevoGrafico($carpeta)
    {
        global $lang;
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "INSERT INTO bigraficos
        (biGraficos_nombre,biGraficos_autorId,biGraficos_cuboId,biGraficos_esPublico,biGraficos_config,biGraficos_carpeta,biGraficos_limiteRegistros)
        VALUES (%Q,%N,%N,%N,%Q,%Q,%N)",
            $lang['Nuevo Gráfico'],
            $_SESSION[MID . 'userId'],
            0,
            0,
            '',
            $carpeta,
            100
        ));
    }
    public function copiarGrafico($graf)
    {
        global $lang;
        $db = new MYSQLDB();
        $graficoCopiado = $db->query($db->mkSQL(
            "INSERT INTO bigraficos
        (biGraficos_nombre,biGraficos_autorId,biGraficos_cuboId,biGraficos_esPublico,biGraficos_config,biGraficos_carpeta,biGraficos_tipo,biGraficos_configuracion,biGraficos_limiteRegistros)
        VALUES (%Q,%N,%N,%N,%Q,%Q,%Q,%N)",
            $graf['biGraficos_nombre'] . " - " . $lang['copia'],
            $_SESSION[MID . 'userId'],
            $graf['biGraficos_cuboId'],
            0,
            $graf['biGraficos_config'],
            '',
            $graf['biGraficos_tipo'],
            $graf['biGraficos_configuracion'],
            100
        ));
        return $graficoCopiado;
    }
    public function nuevoGraficoHeredado($grafico, $configuracion)
    {
        global $lang;
        $db = new MYSQLDB();
        $graficoCopiado = $db->query($db->mkSQL(
            "INSERT INTO bigraficos
        (biGraficos_nombre,biGraficos_autorId,biGraficos_cuboId,biGraficos_esPublico,biGraficos_config,biGraficos_carpeta,biGraficos_tipo,biGraficos_configuracion)
        VALUES (%Q,%N,%N,%N,%Q,%Q,%Q,%Q)",
            $grafico['biGraficos_nombre'] . " - " . $grafico['biGraficos_id'] . "00" . random_int(10, 99),
            $_SESSION[MID . 'userId'],
            $grafico['biGraficos_cuboId'],
            0,
            $grafico['biGraficos_config'],
            $grafico['biGraficos_carpeta'],
            'HEREDADO',
            $configuracion
        ));
        return $graficoCopiado;
    }
    public function publicarGrafico($id)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET biGraficos_esPublico=%N
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            1,
            $id,
            $_SESSION[MID . 'userId']
        ));
    }
    public function despublicarGrafico($id)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET biGraficos_esPublico=%N
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            0,
            $id,
            $_SESSION[MID . 'userId']
        ));
    }
    public function borrarGrafico($id)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "DELETE FROM bigraficos
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            $id,
            $_SESSION[MID . 'userId']
        ));
    }
    public function guardaValor($id, $campo, $valor)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET " . $campo . "=%Q
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            $valor,
            $id,
            $_SESSION[MID . 'userId']
        ));
    }
    public function guardaCarpeta($carpetaAnterior, $carpetaNueva)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET biGraficos_carpeta=%Q
        WHERE biGraficos_carpeta=%Q AND biGraficos_autorId=%N",
            $carpetaNueva,
            $carpetaAnterior,
            $_SESSION[MID . 'userId']
        ));
    }
    public function cambiaFuenteDatos($graficoId, $cuboId)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET biGraficos_cuboId=%N
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            $cuboId,
            $graficoId,
            $_SESSION[MID . 'userId']
        ));
    }
    public function guardarConfiguracion($graficoId, $config_json, $limite)
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL(
            "UPDATE bigraficos SET biGraficos_config=%Q,biGraficos_limiteRegistros=%N
        WHERE biGraficos_id=%N AND biGraficos_autorId=%N",
            $config_json,
            $limite,
            $graficoId,
            $_SESSION[MID . 'userId']
        ));
    }
    //AUXILIARES
    public function utf8_to_latin1_recursive($data)
    {
        if (is_array($data)) {
            $converted = [];
            foreach ($data as $key => $value) {
                // Convertimos la key
                $key_latin1 = is_string($key) ? mb_convert_encoding($key, 'ISO-8859-1', 'UTF-8') : $key;
                // Convertimos el valor recursivamente
                $converted[$key_latin1] = $this->utf8_to_latin1_recursive($value);
            }
            return $converted;
        } elseif (is_string($data)) {
            return mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8');
        } else {
            return $data;
        }
    }
    public function crearVistaCubo($plug, $cub, $configuracion, $graficoHeredado) {
        $camposMapeo = $plug->getMapeoCampos();

        $camposMapeoFinales = [];
        $group = [];
        $project = ['_id' => 0];
        $datos = [[]];
        $camposConPorcentaje = [];

        /** ROWS **/
        foreach ($configuracion['rows'] as $value) {
            $camposMapeoFinales[$value] = $camposMapeo[$value];

            $mongoField = $camposMapeoFinales[$value]['mdb'];

            $datos[0][] = ['header' => $value, 'mongo' => $mongoField];
            $group['_id'][$mongoField] = "\${$mongoField}";
            $project[$mongoField] = "\$_id.{$mongoField}";
        }

        /** COLS **/
        foreach ($configuracion['cols'] as $campo => $operaciones) {
            $camposMapeoFinales[$campo] = $camposMapeo[$campo];
            $campoMongo = $camposMapeoFinales[$campo]['mdb'];

            foreach ($operaciones as $operacion) {

                $datos[0][] = ['header' => $campo, 'mongo' => $campoMongo];

                switch (strtolower($operacion)) {

                    case 'conteo_distinto':
                        $group["{$campoMongo}_set"] = ['$addToSet' => "\${$campoMongo}"];
                        $project["{$campoMongo}_{$operacion}"] = [
                            '$size' => "\${$campoMongo}_set"
                        ];
                        break;

                    case 'suma':
                    case 'maximo':
                    case 'minimo':
                    case 'promedio':

                        $mongoOp = [
                            'suma'     => '$sum',
                            'maximo'   => '$max',
                            'minimo'   => '$min',
                            'promedio' => '$avg'
                        ];

                        $group["{$campoMongo}_{$operacion}"] = [
                            $mongoOp[$operacion] => "\${$campoMongo}"
                        ];

                        $project["{$campoMongo}_{$operacion}"] = [
                            '$round' => ["\${$campoMongo}_{$operacion}", 2]
                        ];
                        break;

                    case 'suma_porcentaje':
                        $camposConPorcentaje[] = "{$campoMongo}_suma";
                        break;

                    case 'promedio_porcentaje':
                        $camposConPorcentaje[] = "{$campoMongo}_promedio";
                        break;

                    default:
                        $group['_id'][$campoMongo] = "\${$campoMongo}";
                        $project[$campoMongo] = "\$_id.{$campoMongo}";
                }
            }
        }

        /** TOTAL **/
        $datos[0][] = ['header' => 'total', 'mongo' => 'total'];
        $group['total_registros'] = ['$sum' => 1];
        $project['total_registros'] = 1;

        /** PIPELINE BASE **/
        $pipeline = [
            ['$group' => $group],
            ['$project' => $project]
        ];

        /** PORCENTAJES **/
        if (!empty($camposConPorcentaje)) {

            $output = [];
            foreach ($camposConPorcentaje as $campo) {
                $output["total_{$campo}"] = [
                    '$sum' => "\${$campo}",
                    'window' => ['documents' => ['unbounded', 'unbounded']]
                ];
            }

            $pipeline[] = [
                '$setWindowFields' => ['output' => $output]
            ];

            $addFields = [];
            foreach ($camposConPorcentaje as $campo) {
                $addFields["{$campo}_porcentaje"] = [
                    '$round' => [
                        [
                            '$multiply' => [
                                ['$divide' => ["\${$campo}", "\$total_{$campo}"]],
                                100
                            ]
                        ],
                        4
                    ]
                ];
            }

            $pipeline[] = ['$addFields' => $addFields];
        }

        /** CREAR VISTA **/
        $mdb = new MYMONGODB();
        $nombreVista = "vw_{$cub->get('plugin')}_{$graficoHeredado}";

        $mdb->borrarVista($nombreVista);
        return $mdb->crearVista(
            $nombreVista,
            $plug::COLLECTION_CUBO,
            $pipeline
        );
    }
    //MODULE DATABASE
    function checkStructure()
    {
        $db = new MYSQLDB();
        $db->mantieneBase(
            [
                "table" => "bigraficos",
                "prefix" => "biGraficos_",
                "fields" => [
                    [
                        "name" => "id",
                        "type" => "int",
                        "size" => "",
                        "default" => "",
                        "special" => "",
                        "index" => "primary",
                    ],
                    [
                        "name" => "nombre",
                        "type" => "varchar",
                        "size" => "200",
                        "default" => "",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "autorId",
                        "type" => "int",
                        "size" => "",
                        "default" => "",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "cuboId",
                        "type" => "int",
                        "size" => "",
                        "default" => "",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "esPublico",
                        "type" => "int",
                        "size" => "",
                        "default" => 0,
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "config",
                        "type" => "text",
                        "size" => "",
                        "default" => "",
                        "special" => "",
                        "index" => "",
                    ],
                    [
                        "name" => "carpeta",
                        "type" => "varchar",
                        "size" => "200",
                        "default" => "",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "limiteRegistros",
                        "type" => "int",
                        "size" => "",
                        "default" => "0",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "tipo",
                        "type" => "varchar",
                        "size" => "25",
                        "default" => "ORIGINAL",
                        "special" => "",
                        "index" => "normal",
                    ],
                    [
                        "name" => "configuracion",
                        "type" => "text",
                        "size" => "",
                        "default" => "{}",
                        "special" => "",
                        "index" => "normal",
                    ],
                ]
            ]
        );
    }
}
?><? //_FIN_DE_ARCHIVO ?>