<?

require_once("../comunes/classes/class.clase.php");

class boCategoria extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bocategoria", "boCategoria_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite obtener el id de una categoría por nombre
    //__Inputs:__ $nombre:string nombre de categoría
    //__Outputs:__ $id:int id de bocategoria
    function getIdPorNombre($nombre) {
        $db = new MYSQLDB();
        $id = 0;
        $sql = $db->mkSQL("SELECT boCategoria_id FROM bocategoria WHERE boCategoria_nombre=%Q", $nombre);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $id = $row["boCategoria_id"];
        }
        return $id;
    }

    //__Descripción:__ Función que permite eliminar una categoría
    //__Inputs:__ $categoriaId:string id de tabla bocategoria
    //__Outputs:__ boolean:true/string
    function eliminarCategoria($categoriaId) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM bojerarquia WHERE boJerarquia_categoriaId=%N",$categoriaId);
        if($db->query($sql)){
            return "Se han encontrado jerarquías relacionadas a ésta categoría";
        }else{
            $sql = $db->mkSQL("DELETE FROM bocategoria WHERE boCategoria_id=%N", $categoriaId);
            $db->query($sql);
            return true;
        }
    }

    //__Descripción:__ Función que permite insertar un registro en la tabla bocategoria
    //__Inputs:__ $categoriaNombre:string nombre de la categoria
    //            $categoriaUltNivel:int nivel (no requerido/no usado)
    //__Outputs:__ $id:int id de tabla bocategoria
    function insertaBoCategoria($categoriaNombre, $categoriaUltNiv) {
        $db = new MYSQLDB();
        $id = $db->query($db->mkSQL("INSERT INTO bocategoria 
                (boCategoria_nombre,boCategoria_ultimoNivel) 
                VALUES (%Q,%N)", $categoriaNombre, $categoriaUltNiv));
        return $id;
    }

    //__Descripción:__ Función que permite actualizar los datos de una categoria
    //__Inputs:__ $categoriaNombre:string nombre de categoria
    //            $categoriaUltNiv:int novel (no requerido/no usado)
    //            $categoriaId:int id de categoria de bocategoria a modificar
    //__Outputs:__ boolean:true/false
    function actualizaBoCategoria($categoriaNombre, $categoriaUltNiv, $categoriaId) {
        $db = new MYSQLDB();
        return $db->query($db->mkSQL("UPDATE bocategoria 
			SET boCategoria_nombre=%Q, boCategoria_ultimoNivel=%N
			WHERE boCategoria_id=%N", $categoriaNombre, $categoriaUltNiv, $categoriaId));
    }

    //__Descripción:__ Función para obtener categorias de mapa del sitio generadas por editor antiguo
    //__Inputs:__ $abrir:int id a buscar para presentar jerarquía abierta
    //            $ubicacionBase:int id de padre inicial
    //__Outputs:__ $genealogia:array lista de padres de árbol de jerarquía
    function obtenerGenealogiaCategorias($abrir, $ubicacionBase) {
        $db = new MYSQLDB();
        $genealogia = array();
        if ($abrir > 0) {
            //construya los ancestros
            while (true) {
                $sql = $db->mkSQL("SELECT categorias_padreId FROM categorias WHERE categorias_id=%N AND categorias_publicar=%Q", $abrir, "Y");
                if (!$db->query($sql)) {
                    $json["errores"] = "Ubicación no existe";
                    break;
                } else {
                    $row = $db->fetchRow();
                    $abrir = $row["categorias_padreId"];
                    $genealogia[] = $abrir;
                    if ($abrir == 0) {
                        break;
                    }
                }
            }
        } else {
            $genealogia[] = $ubicacionBase;
        }
        return $genealogia;
    }

    //__Descripción:__ Función que permite obtener los datos de rama de categoría de mapa de sitio generados por editor antiguo
    //__Inputs:__ $genealogia:array lista de padres enviados por obtenerGenealogiaCategorias()
    //            $abrirOriginal:int id de busqueda que se abrirá en jerarquía
    //            $editable:boolean indica si las ramas se enviarán a interfaz como editables
    //__Outputs:__ $ramas:array lista de ramas de árbol de jerarquía
    function obtenerArbolCategorias($genealogia, $abrirOriginal, $editable = true) {
        $hijos = array();
        $anterior = -1;
        $db = new MYSQLDB();
        foreach ($genealogia as $padreId) {
            $sql = $db->mkSQL("SELECT * FROM categorias WHERE categorias_padreId=%N AND categorias_publicar=%Q", $padreId, "Y");
            $db->query($sql);
            $ramas = array();
            while ($row = $db->fetchRow()) {
                if ($row["categorias_id"] == $anterior) {
                    $estosHijos = $hijos;
                    $cerrada = false;
                } else {
                    $estosHijos = array();
                    $cerrada = true;
                }
                if ($abrirOriginal > 0 && $abrirOriginal == $row["categorias_id"]) {
                    $seleccionada = true;
                } else {
                    $seleccionada = false;
                }
                $ramas[] = array(
                    "id" => $row["categorias_id"],
                    "nombre" => $row["categorias_nombre"],
                    "codigo" => $row["categorias_nombre"],
                    "cerrada" => $cerrada,
                    "nueva" => $cerrada,
                    "seleccionada" => $seleccionada,
                    "padreId" => $row["categorias_padreId"],
                    "modelo" => $row["categorias_modelo"],
                    "hijos" => $estosHijos,
                    "editable" => $editable
                );
            }
            $anterior = $padreId;
            $hijos = $ramas;
        }
        return $ramas;
    }

    //__Descripción:__ Función para obtener el contenido HTML del editor antiguo (categorias)
    //__Inputs:__ $categoriaId:int id de tabla categorias
    //__Outputs:__ $result:string HTML generado por editar antiguo
    function obtenerHtmlEditor($categoriaId) {
        $db = new MYSQLDB();
        $result = array("html" => "", "url" => "");
        $html = "";
        $sql = $db->mkSQL("SELECT * FROM categorias WHERE categorias_id=%N", $categoriaId);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $modelo = $row["categorias_modelo"];
            $url = BASEURL . "web/" . $modelo . ".php?c=" . $categoriaId;
            require_once("../boletines/classes/class.boContenido.php");
            $conten = new boContenido();
            $html = $conten->generarContenidoHtml($url);
            $result["html"] = $html;
            $result["url"] = $url;
        }
        return $result;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bocategoria",
                        "prefix" => "boCategoria_",
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
                                "name" => "nombre",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "ultimoNivel",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
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
