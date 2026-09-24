<?

require_once("../comunes/classes/class.clase.php");

class boBitacoraRedes extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bobitacoraredes", "boBitacoraRedes_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite actualizar el estado envío
    //__Inputs:__ $estado:string estados devueltos por red social aplicada
    //            $bitacoraId:int id de la tabla bobitaciraredes
    //__Outputs:__ $result:boolean true/false
    function actualizaEstadoEnvio($estado, $bitacoraId) {
        $db = new MYSQLDB();
        return $db->query($db->mkSQL("UPDATE bobitacoraredes SET boBitacoraRedes_estadoPublicacion = %Q WHERE boBitacoraRedes_id=%N", $estado, $bitacoraId));
    }

    //__Descripción:__ Función que permite insertar un registro en la tabla bobitacoraredes
    //__Inputs:__ $contenidoId:int id de tabla bocontenido
    //            $fechaPrg:int fecha de envío programado
    //            $horaPrg:string hora de envío programado
    //            $elementosTotales:int lista de correos a quienes se realiza la publicación
    //            $lista:string lista de valores marcados para envío desde interfaz
    //            $estado:string estado inicial de publicación (PENDIENTE)
    //            $contenido:string contenido de boletín
    //            $conexId:int id de tabla boredesconf
    //            $tipo:string red social
    //__Outputs:__ $idBitacora:int id de tabla bobitacoraredes
    function insertar($contenidoId, $fechaPgr, $horaPgr, $elementosTotales, $lista, $estado, $contenido = "", $conexId = "", $tipo = "") {
        $db = new MYSQLDB();
        $idBitacora = 0;
        $userId = isset($_SESSION[MID . "userId"]) ? $_SESSION[MID . "userId"] : 0;
        $sql = $db->mkSQL("INSERT INTO bobitacoraredes (
                               boBitacoraRedes_contenidoId,boBitacoraRedes_envioProgramado,
                               boBitacoraRedes_horaEnvio,boBitacoraRedes_elementosTotal,
                               boBitacoraRedes_marcados,boBitacoraRedes_estadoPublicacion,
                               boBitacoraRedes_contenido,boBitacoraRedes_conexId,
                               boBitacoraRedes_creado,boBitacoraRedes_userId,boBitacoraRedes_tipo)
                               VALUES (%N,%N,%Q,%N,%Q,%Q,%Q,%Q,%N,%N,%Q)", $contenidoId, $fechaPgr, $horaPgr, $elementosTotales, $lista, $estado, $contenido, $conexId, time(), $userId, $tipo);
        $idBitacora = $db->query($sql);
        return $idBitacora;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bobitacoraredes",
                        "prefix" => "boBitacoraRedes_",
                        "fields" => array(
                            array(
                                "name" => "id",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "primary",
                            ),
                            array(
                                "name" => "contenidoId",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "envioProgramado",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "horaEnvio",
                                "type" => "varchar",
                                "size" => "5",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "elementosTotal",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "marcados",
                                "type" => "varchar",
                                "size" => "1000",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "estadoPublicacion",
                                "type" => "varchar",
                                "size" => "100",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "contenido",
                                "type" => "varchar",
                                "size" => "700",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "conexId",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "creado",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "userId",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "tipo",
                                "type" => "varchar",
                                "size" => "50",
                                "default" => "facebook",
                                "special" => "",
                                "index" => "normal",
                            ),
                        )
                    )
            );
        }
    }

}
?>
<? //_FIN_DE_ARCHIVO  ?>
