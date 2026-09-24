<?

require_once("../comunes/classes/class.clase.php");

class boRegistro extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("boregistro", "boRegistro_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite insertar registro en tabla boregistro
    //__Inputs:__ $idBitacora:int id de tabla boregistro
    //            $correo:string correo del cual se llevará seguimiento de desuscritos y visitas
    //            $idCorreo:int id de tabla bocorreo
    //            $tipoRegistro:string tipo de movimiento (Lectura/DeSuscripcion)
    //            $fechaRegistro:int fecha de ejecución de movimiento
    //            $jerarquia:string lista de jeraquías de las cuales se desuscribe
    //__Outputs:__ $ans:array listado de ancestros de $parent
    function insertaRegistro($idBitacora, $correo, $idCorreo, $tipoRegistro, $fechaRegistro, $jerarquia = "") {
        $db = new MYSQLDB();
        $idRegistro = 0;
        $sql = $db->mkSQL("SELECT * FROM boregistro WHERE boRegistro_bitacoraId=%N AND boRegistro_correoId=%N AND boRegistro_tipo=%Q", $idBitacora, $idCorreo, $tipoRegistro);
        $db->query($sql);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $idRegistro = $row["boRegistro_id"];
            $visitasActuales = 1; //$row["boRegistro_visitas"];
            //$visitasActuales++;
            $jerarquiasActuales = "";
            if ($jerarquia != "") {
                $jerarquiasActuales = $row["boRegistro_desuscritos"];
                $jerarquiasActuales .= "," . $jerarquia;
                $jerarquiasActuales = rtrim($jerarquiasActuales, ',');
                $jerarquiasActuales = ltrim($jerarquiasActuales, ',');
            }
            $sql1 = $db->mkSQL("UPDATE boregistro SET boRegistro_visitas=%N,boRegistro_desuscritos=%Q WHERE boRegistro_id=%N", $visitasActuales, $jerarquiasActuales, $idRegistro);
            $db->query($sql1);
        } else {
            $sql1 = $db->mkSQL("INSERT INTO boregistro (boRegistro_bitacoraId,boRegistro_correo,boRegistro_correoId,boRegistro_tipo,boRegistro_fechaRegistro,boRegistro_visitas,boRegistro_desuscritos)
                               VALUES (%N,%Q,%N,%Q,%N,%N,%Q)", $idBitacora, $correo, $idCorreo, $tipoRegistro, $fechaRegistro, 1, $jerarquia);
            $idRegistro = $db->query($sql1);
        }
        return $idRegistro;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "boregistro",
                        "prefix" => "boRegistro_",
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
                                "name" => "bitacoraId",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "correo",
                                "type" => "varchar",
                                "size" => "300",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "correoId",
                                "type" => "varchar",
                                "size" => "300",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "tipo",
                                "type" => "varchar",
                                "size" => "20",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaRegistro",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "visitas",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "desuscritos",
                                "type" => "text",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                        )
                    )
            );
        }
    }

}
?>
<? //_FIN_DE_ARCHIVO  ?>
