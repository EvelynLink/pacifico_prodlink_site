<?

require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once("../comunes/classes/class.mysqldb.php");

class waConfiguracionCtrl
{

    private $mongo;

    public function __construct($act)
    {
        $this->mongo = new MYMONGODB();
        $d = jsonStart();
        $json = [];
        $limpiar = array();
        // Variable $act tiene el nombre de la funcion que apunta el servicio
        $json = $this->$act($d);
        jsonEnd($json, $limpiar);
    }

    public function getConfiguraciones($d)
    {
        $coleccion = 'cbConfig';
        $condition = [
            'cbConf_tipo' => 'whatsappConf',
            'cbConf_nombre' => 'whatsappConf',
            'cbConf_estado' => (int) 1
        ];
        $campos = [
            'cbConf_valor',
            'cbConf_cartera_id',
            'cbConf_cartera_nombre',
            'cbConf_descripcion',
            'cbConf_status',
            'cbConf_fecha_creacion',
            'cbConf_fecha_actualizacion'
        ];
        $cursor = $this->mongo->buscar($coleccion, $condition, $campos, array("_id" => -1));
        $data = [];
        while ($r = $this->mongo->siguiente()) {
            $data[] = [
                'id' => $r['id'],
                'numero' => $r['cbConf_valor'],
                'cartera_id' => $r['cbConf_cartera_id'],
                'cartera_nombre' => $r['cbConf_cartera_nombre'],
                'descripcion' => $r['cbConf_descripcion'],
                'estado' => $r['cbConf_status'],
                'creacion' => $r['cbConf_fecha_creacion'],
                'actualizacion' => $r['cbConf_fecha_actualizacion'],
                'editar' => 0
            ];
        }
        return ['configuraciones' => $data];
    }

    public function saveOrUpdate($d)
    {
        $data = [
            'cbConf_tipo' => 'whatsappConf',
            'cbConf_nombre' => 'whatsappConf',
            'cbConf_valor' => (string) $d['numero'],
            'cbConf_cartera_id' => $d['cartera_id'],
            'cbConf_cartera_nombre' => $d['cartera_nombre'],
            'cbConf_descripcion' => strtoupper($d['descripcion']),
            'cbConf_status' => $d['estado'],
            'cbConf_fecha_creacion' => (int) time(),
            'cbConf_fecha_actualizacion' => 0,
            'cbConf_estado' => (int) 1,
        ];
        $coleccion = 'cbConfig';
        if ($d['id'] === 'nuevo') {
            $response = $this->mongo->guardar($coleccion, $data);
        } else {
            $condition = [
                '_id' => $this->mongo->String2MongoId($d['id'])
            ];
            unset($data['cbConf_fecha_creacion']);
            $data['cbConf_fecha_actualizacion'] = (int) time();
            $r = $this->mongo->actualizar($coleccion, $condition, $data);
            $response = $d['id'];
        }
        return ['configuracion' => $response];
    }

    public function delete($d)
    {
        $coleccion = 'cbConfig';
        $condition = [
            '_id' => $this->mongo->String2MongoId($d['id'])
        ];
        return $this->mongo->actualizar($coleccion, $condition, ['cbConf_estado' => 0]); //Borrado logico
    }

    public function getQR($d)
    {
        require_once "../canalesMasivos/apis/class.whatsappAPI.php";
        $wh = new whatsappAPI();
        return $wh->getQR($d['numero']);
    }

}

$act = $_REQUEST["act"];
$metodos_clase = get_class_methods('waConfiguracionCtrl');
if (!isset($act) && !in_array($act, $metodos_clase))
    exit;
new waConfiguracionCtrl(expect_pure_alphanumeric($act));
?>
<?//_FIN_DE_ARCHIVO ?>