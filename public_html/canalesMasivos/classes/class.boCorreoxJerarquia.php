<?

require_once("../comunes/classes/class.clase.php");

class boCorreoxJerarquia extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bocorreoxjerarquia", "boCorreoxJerarquia_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite obtener Id de correo por medio del id de tabla bocorreoxjerarquia
    //__Inputs:__ $correoxjerarquiaId:int id de tabla bocorreoxjerarquia
    //__Outputs:__ $dtCorreo:array lista datos de coreo de tipo ["correoId"=>$correoId,"correo"=>$correo,"jerarquiaId"=>$jerarquiaId]
    function obtieneDetalleCorreoJerarquia($correoxjerarquiaId) {
        $db = new MYSQLDB();
        $dtCorreo = array("correo" => "", "correoId" => 0, "jerarquiaId" => 0);
        $sql = $db->mkSQL("SELECT * 
                               FROM bocorreoxjerarquia
                               LEFT JOIN bocorreo ON boCorreoxJerarquia_correoId = boCorreo_id
                               WHERE boCorreoxJerarquia_id=%N", $correoxjerarquiaId);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $dtCorreo["correoId"] = $row["boCorreoxJerarquia_correoId"];
            $dtCorreo["correo"] = $row["boCorreo_email"];
            $dtCorreo["jerarquiaId"] = $row["boCorreoxJerarquia_jerarquiaId"];
        }
        return $dtCorreo;
    }

    //__Descripción:__ Función que permite eliminar una relación correo-jerarquía por medio de id de correo y jerarquia
    //__Inputs:__ $correoId:int id de tabla bocorreo
    //            $jerarquiaId:int id de tabla bojerarquia
    //__Outputs:__ $resulta:boolean true/false
    function eliminaSuscripcion($correoId, $jerarquiaId) {
        $db = new MYSQLDB();
        //Elimina suscripción
        $sql = $db->mkSQL("DELETE FROM bocorreoxjerarquia
            WHERE boCorreoxJerarquia_correoId=%N AND boCorreoxJerarquia_jerarquiaId=%N", $correoId, $jerarquiaId);
        $resulta = $db->query($sql);
        return $resulta;
    }

    //__Descripción:__ Función que permite eliminar relación correo-jerarquía por id de tabla bocorreoxjerarquia
    //__Inputs:__ $correoxjerarquiaId:int id de tabla bocorreoxjerarquia
    //__Outputs:__ $resulta:boolean true/false
    function eliminaSuscripcionXId($correoxjerarquiaId) {
        $db = new MYSQLDB();
        $resulta = $db->query($db->mkSQL("DELETE FROM bocorreoxjerarquia 
	    WHERE boCorreoxJerarquia_id=%N", $correoxjerarquiaId));
        return $resulta;
    }

    //__Descripción:__ Función que permite eliminar un grupo de ids de relación correo-jerarquía
    //__Inputs:__ $ids:array lista de ids de tabla bocorreoxjerarquia
    //__Outputs:__ $resulta:booolean true/false
    function eliminaSuscripcionXlistaIds($ids) {
        $clausulaIn = implode(',', $ids);
        $db = new MYSQLDB();
        $resulta = $db->query($db->mkSQL("DELETE FROM bocorreoxjerarquia 
	    WHERE boCorreoxJerarquia_id IN (" . $clausulaIn . ")"));
        return $resulta;
    }

    //__Descripción:__ Función que permite eliminar relación correo-jerarquia por medio de un arreglo de datos que incluyen el campo "id" correspondiente al id de la tabla bocorreoxjerarquia
    //__Inputs:__ $listaJerarquias:array lista de array de datos que contiene id de tabla bocorreoxjerarquia
    //__Outputs:__ $resulta:boolean true/false
    function eliminaSuscripciones($listaJerarquias) {
        $clausulaIn = "";
        foreach ($listaJerarquias as $item) {
            $clausulaIn .= $item["id"] . ",";
        }
        $clausulaIn = rtrim($clausulaIn, ",");
        //Elimina todas las suscripciones
        $db = new MYSQLDB();
        $sql = "DELETE FROM bocorreoxjerarquia 
	    WHERE boCorreoxJerarquia_jerarquiaId IN (" . $clausulaIn . ")";
        //Elimina suscripción
        $resulta = $db->query($db->mkSQL($sql));
        return $resulta;
    }

    //__Descripción:__ Función que permite eliminar las relaciones correo-jerarquia existentes en toda la base de boletines
    //__Inputs:__ 
    //__Outputs:__ 
    function eliminarDesuscripcionesEncontradas() {
        $db = new MYSQLDB();
        $db1 = new MYSQLDB();
        $sql = $db->mkSQL("SELECT 
                                    boCorreoxJerarquia_id,
                                    boRegistro_correoId,
                                    boRegistro_correo,
                                    boRegistro_desuscritos,
                                    boJerarquia_id,
                                    boJerarquia_nombre
                                FROM boregistro
                                INNER JOIN bojerarquia ON FIND_IN_SET(boJerarquia_id,boRegistro_desuscritos)
                                INNER JOIN bocorreoxjerarquia ON boCorreoxJerarquia_correoId = boRegistro_correoId AND boJerarquia_id=boCorreoxJerarquia_jerarquiaId
                                WHERE boRegistro_tipo LIKE 'DeSuscripcion'
                                ORDER BY boRegistro_correo,boRegistro_correoId,boRegistro_desuscritos,boJerarquia_id,boJerarquia_nombre DESC");
        if ($db->query($sql)) {
            while ($row = $db->fetchRow()) {
                $id = $row["boCorreoxJerarquia_id"];
                if ($id > 0) {
                    $db1->query($db1->mkSQL("DELETE FROM bocorreoxjerarquia 
                                                WHERE boCorreoxJerarquia_id=%N", $id));
                }
            }
        }
    }

    //__Descripción:__ Función que permite guardar una suscripción
    //__Inputs:__ $correoId:int id de tabla bocorreo
    //            $jerarquiaId:int id de tabla bojerarquia
    //__Outputs:__ $idCorreoJerarquia:int id de tabla bocorreoxjerarquia
    function guardaSuscripcion($correoId, $jerarquiaId, $fechaCreacion=0) {
        $db = new MYSQLDB();
        $idCorreoJeraquia = 0;
        if($fechaCreacion == 0){
            $fechaCreacion = time();
        }
        $countSuscrip = $db->query($db->mkSQL("SELECT boCorreoxJerarquia_id FROM bocorreoxjerarquia WHERE boCorreoxJerarquia_correoId = %N AND boCorreoxJerarquia_jerarquiaId = %N", $correoId, $jerarquiaId));
        if ($countSuscrip == 0) {
            $idCorreoJeraquia = $db->query($db->mkSQL("INSERT INTO bocorreoxjerarquia (boCorreoxJerarquia_correoId,boCorreoxJerarquia_jerarquiaId,boCorreoxJerarquia_fechaCreacion) VALUES (%N,%N,%N)", $correoId, $jerarquiaId, $fechaCreacion));
        }
        return $idCorreoJeraquia;
    }

    //__Descripción:__ Función que permite insertar una suscripción de correo bajo una lista de categorías marcadas en interfaz
    //__Inputs:__ $idCorreo:int id de tabla bocorreo
    //            $lstCategoriasMarcadas: lista de categorias en las que se insertará el correo
    //__Outputs:__ $idCorreoJerarquia:int id de tabla bocorreoxjerarquia
    function insertaSuscripcion($idCorreo, $lstCategoriasMarcadas, $fechaCreacion=0) {
        $idCorreoJeraquia = 1;
        if ($idCorreo != 0) {
            $db = new MYSQLDB();
            if($fechaCreacion == 0){
                $fechaCreacion = time();
            }
            foreach ($lstCategoriasMarcadas as $item) {
                if ($item > 0) {
                    $countSuscrip = $db->query($db->mkSQL("SELECT boCorreoxJerarquia_id FROM bocorreoxjerarquia WHERE boCorreoxJerarquia_correoId = %N AND boCorreoxJerarquia_jerarquiaId = %N", $idCorreo, $item));
                    if ($countSuscrip == 0) {
                        $db->query($db->mkSQL("INSERT INTO bocorreoxjerarquia (boCorreoxJerarquia_correoId,boCorreoxJerarquia_jerarquiaId,boCorreoxJerarquia_fechaCreacion) VALUES (%N,%N,%N)", $idCorreo, $item, $fechaCreacion));
                        $idCorreoJeraquia++;
                    }
                }
            }
        }
        return $idCorreoJeraquia;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bocorreoxjerarquia",
                        "prefix" => "boCorreoxJerarquia_",
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
                                "name" => "correoId",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "jerarquiaId",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "fechaCreacion",
                                "type" => "int",
                                "size" => "",
                                "default" => "0",
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
