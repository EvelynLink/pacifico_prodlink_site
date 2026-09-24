<?

require_once("../comunes/classes/class.clase.php");

class boJerarquia extends Clase {

    //funcion que crea el objeto
    function __construct() {
        parent::init("bojerarquia", "boJerarquia_");
        $this->checkStructure();
    }

    //__Descripción:__ Función que permite obtener el id de la tabla bojerarquia por el nombre y la categoría padre enviada
    //__Inputs:__ $nombre:string nombre de categoria
    //            $categoriaId:int id de tabla bocategoria
    //__Outputs:__ $id:int id de tabla bojerarquia
    function getIdPorNombreYCategoria($nombre, $categoriaId) {
        $db = new MYSQLDB();
        $id = 0;
        $sql = $db->mkSQL("SELECT boJerarquia_id FROM bojerarquia 
                             WHERE boJerarquia_nombre=%Q AND boJerarquia_categoriaId=%N", $nombre, $categoriaId);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $id = $row["boJerarquia_id"];
        }
        return $id;
    }

    //__Descripción:__ Función que permite insertar un registro en la tabla bojerarquia
    //__Inputs:__ $nombre:string nombre de jerarquia
    //            $categoriaId:int id de tabla bocategoria
    //            $ubicacionBase:int padreId de tabla bojerarquía
    //__Outputs:__ $id:int id de la tabla bojerarquia
    function insertaBoJerarquia($nombre, $categoriaId, $ubicacionBase) {
        $db = new MYSQLDB();
        $id = 0;
        $sql = $db->mkSQL("INSERT INTO bojerarquia 
			(boJerarquia_nombre,boJerarquia_categoriaId, 
			boJerarquia_padreId) 
			VALUES (%Q,%N,%N)", $nombre, $categoriaId, $ubicacionBase);
        $id = $db->query($sql);
        return $id;
    }

    //__Descripción:__ Función que permite eliminar un registro de la tabla bojerarquia
    //__Inputs:__ $jerarquiaId:int id de tabla bojerarquia
    //__Outputs:__ $result:boolean true/false
    function eliminaJerarquia($jerarquiaId) {
        $db = new MYSQLDB();
        $sql = $db->mkSQL("DELETE FROM bojerarquia WHERE boJerarquia_id = %N", $jerarquiaId);
        return $db->query($sql);
    }

    //__Descripción:__ Función que permite cambiar el padre de un registro de la tabla bojerarquia
    //__Inputs:__ $destinoPadre:int nuevo id de padre
    //            $ubicacionBase:int id de registro de tabla bojerarquia
    //__Outputs:__ $result:int 1:ok,0:false
    function moverRama($destinoPadre, $ubicacionBase) {
        //__Descripcion__: atualiza las relaciones entre el padreId y el hijo en la tabla jerarquia
        //__Input__:$destinoPadre: key del emSucursalesCO_padreId,
        //          $empresaId= key de la emSucursalesCO_empresaId
        //          $ubicacionBase=id de la tabla emSucursalCo,
        //__Output__: 1 en caso de que paso y 0 en caso de que no
        $db = new MYSQLDB();
        $sql = $db->mkSQL("UPDATE bojerarquia 
                                SET boJerarquia_padreId=%N
                                WHERE boJerarquia_id=%N", $destinoPadre, $ubicacionBase);
        if ($db->query($sql)) {
            return 1;
        } else {
            return 0;
        }
    }

    //__Descripción:__ Función que permite obtener los datos de la tabla bojerarquia relacionada al registro solicitado con id
    //__Inputs:__ $id:int id de tabla bojerarquia
    //__Outputs:__ $datos:array lista de datos registrados en la tabla bojerarquia
    function obtieneInfoJerarquia($id) {
        $datos = array();
        $db = new MYSQLDB();
        $db->query($db->mkSQL("SELECT * FROM bojerarquia WHERE boJerarquia_id=%N", $id));
        $datos = $db->fetchRow();
        return $datos;
    }

    //__Descripción:__ Función que permite obtener los datos de la tabla bojerarquia como un array con nombres limpios en base a una lista de ids
    //__Inputs:__ $strLista:array lista de ids de la tabla bojerarquia separados por comas
    //__Outputs:__ $datos:array lista de array que contiene datos de tabla bojerarquia
    function obtieneInfoJerarquiaLista($strLista) {
        //__Descripcion__: obtiene lista de datos de jerarquia por id
        //__Input__:id jerarquia
        //__Output__: datos de jerarquia
        $db = new MYSQLDB();
        $datos = array();
        $db->query($db->mkSQL("SELECT * FROM bojerarquia WHERE boJerarquia_id IN (" . $strLista . ")"));
        while ($row = $db->fetchRow()) {
            $camposLimpios = array();
            foreach ($row as $campo=>$valor){
                $campo = str_replace("boJerarquia_", "", $campo);
                $camposLimpios[$campo] = $valor;
            }
            $datos[] = $camposLimpios;
        }
        return $datos;
    }

    //__Descripción:__ Función que permite actualizar los datos de un registro de la tabla bojerarquia por id
    //__Inputs:__ $nombreJerarquia:string nombre de la jerarquía
    //            $descripcionJerarquia:string descripción de la jerarquía
    //            $activo:int 1:activo,0:inactivo
    //__Outputs:__ $result:boolean true/false
    function actualizaJerarquiaXId($nombreJerarquia, $descripcionJerarquia, $activo, $id) {
        $db = new MYSQLDB();
        return $db->query($db->mkSQL("UPDATE bojerarquia 
                SET boJerarquia_nombre=%Q, 
                boJerarquia_descripcion=%Q,
                boJerarquia_activo=%N
                WHERE boJerarquia_id=%N", $nombreJerarquia, $descripcionJerarquia, $activo, $id));
    }

    //__Descripción:__ Función que permite obtener el id de la tabla bojerarquia asociado al nombre de la jerarquia
    //__Inputs:__ $nombreJerarquia:string nombre de jerarquia a buscar
    //__Outputs:__ $jerarquiaId:int id de tabla bojerarquia
    function obtenerIdJerarquiaXNombre($nombreJerarquia) {
        $db = new MYSQLDB();
        $jerarquiaId = 0;
        $sql = $db->mkSQL("SELECT boJerarquia_id
                               FROM bojerarquia
                               WHERE boJerarquia_nombre LIKE %Q", $nombreJerarquia);
        if ($db->query($sql)) {
            $row = $db->fetchRow();
            $jerarquiaId = $row["boJerarquia_id"];
        }
        return $jerarquiaId;
    }

    //__Descripción:__ Función que permite obtener los registros padre de la tabla bojerarquia para formar un árbol con la directiva jerarquía
    //__Inputs:__ $categoriaId:int id de tabla bocategoria
    //            $abir:int id de tabla bojerarquia que tendrá la característica de estar abierto
    //            $ubicacionBase:int id de tabla bojerarquia de donde parte la jerarquía
    //__Outputs:__ $genealogia:array lista de padres de la jerarquía
    function obtenerGenealogia($categoriaId, $abrir, $ubicacionBase) {
        $db = new MYSQLDB();
        $genealogia = array();
        if ($abrir > 0) {
            //construya los ancestros
            while (true) {
                $sql = $db->mkSQL("SELECT boJerarquia_padreId
                                                    FROM bojerarquia
                                                    WHERE boJerarquia_categoriaId=%N
                                                    AND boJerarquia_id=%N", $categoriaId, $abrir);
                if (!$db->query($sql)) {
                    $json["errores"] = "Ubicación no existe";
                    break;
                } else {
                    $row = $db->fetchRow();
                    $abrir = $row["boJerarquia_padreId"];
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

    //__Descripción:__ Función que permite obtener las ramas de la jerarquia
    //__Inputs:__ $categoriaId:int id de la tabla bocategoria
    //            $genealogia:array lista de id padres de la jerarquia
    //            $abrirOriginal:int id de tabla bojerarquia que tendrá la característica de abierto
    //__Outputs:__ $ramas:array lista de datos de bojerarquia
    function obtenerArbolBojerarquia($categoriaId, $genealogia, $abrirOriginal) {
        $hijos = array();
        $anterior = -1;
        $db = new MYSQLDB();
        foreach ($genealogia as $padreId) {
            $sql = $db->mkSQL("SELECT * FROM bojerarquia
                                            WHERE boJerarquia_categoriaId=%N
                                            AND boJerarquia_padreId=%N", $categoriaId, $padreId);

            $db->query($sql);
            $ramas = array();
            while ($row = $db->fetchRow()) {
                if ($row["boJerarquia_id"] == $anterior) {
                    $estosHijos = $hijos;
                    $cerrada = false;
                } else {
                    $estosHijos = array();
                    $cerrada = true;
                }
                if ($abrirOriginal > 0 && $abrirOriginal == $row["boJerarquia_id"]) {
                    $seleccionada = true;
                } else {
                    $seleccionada = false;
                }
                $ramas[] = array(
                    "id" => $row["boJerarquia_id"],
                    "nombre" => $row["boJerarquia_nombre"],
                    "descripcion" => $row["boJerarquia_descripcion"],
                    "activo" => $row["boJerarquia_activo"],
                    "categoria" => $row["boJerarquia_categoriaId"],
                    "cerrada" => $cerrada,
                    "nueva" => $cerrada,
                    "seleccionada" => $seleccionada,
                    "padreId" => $row["boJerarquia_padreId"],
                    "hijos" => $estosHijos,
                );
            }
            $anterior = $padreId;
            $hijos = $ramas;
        }
        return $ramas;
    }

    //__Descripción:__ Función que permite obtener la descripción de una rama de jerarquía seleccionada
    //__Inputs:__ $padreId:int padreId de la tabla bojerarquia
    //            $categoriaId:int id de la tabla bocategoria
    //__Outputs:__ $cadenaResult:string descripción recursiva de la relación padre jerarquía y categoría id
    function obtieneDetalleOrigen($padreId, $categoriaId) {
        //__Descripcion__: obtiene descripción de selección dentro de arbol
        global $cadenaJerarquia;
        if ($padreId > 0) {
            $db = new MYSQLDB();
            $sql = $db->mkSQL("SELECT * 
                                   FROM bojerarquia
                                   WHERE boJerarquia_id=%N", $padreId);
            $db->query($sql);
            $row = $db->fetchRow();
            $cadenaJerarquia .= $row["boJerarquia_nombre"] . " | ";
            //print_h($cadenaJerarquia);
            $cadenaJerarquia .= $this->obtieneDetalleOrigen($row["boJerarquia_padreId"], $categoriaId);
        } else {
            $db1 = new MYSQLDB();
            $sql1 = $db1->mkSQL("SELECT * 
                           FROM bocategoria
                           WHERE boCategoria_id=%N", $categoriaId);
            $db1->query($sql1);
            $rowCat = $db1->fetchRow();
            $cadenaJerarquia .= $rowCat["boCategoria_nombre"];
            $arrCadena = explode('|>', $cadenaJerarquia);
            $cadenaResult = "";
            if (count($arrCadena) > 1) {
                $cadenaResult = $arrCadena[0];
            }
            //print_h($cadenaJerarquia);
            return $cadenaResult;
        }
    }

    //__Descripción:__ Función que permite crear la jerarquía del árbol
    //__Inputs:__ $chain_parts:array Estructrura de categoria y jerarquias con la forma: GRUPO|CATEGORIA1|CATEGORIA2|BOLETIN (CNT|2017|Julio|Tramo30-60)
    //            $categoriaId:int id de tabla bocategoria
    //            $estricto:boolean TRUE: Si se desea que la estructura $estructura deba existir previamente (omite su creacion en caso de inexistencia) | FALSE: Si desea que se creen categoria y jerarquias indicadas en $estructura en caso de no existir
    //__Outputs:__ $ans:int id de tabla bojerarquia o 0 en caso de no haber creado
    function gestionaArbol($chain_parts, $categoriaId, $estricto) {
        $ans = 0;
        $parent = 0;
        //Obtengo todos los grupos de esta categoria
        $renovar_arbol = false;
        $arbol = $this->obtieneArborJerarquias($categoriaId);
        //Buscar en este arbol si existe la secuencia que deseo
        for ($level = 0; $level < count($chain_parts); $level++) {
            //Renueva el arbol si es que hubo una modificacion
            if ($renovar_arbol) {
                $arbol = $this->obtieneArborJerarquias($categoriaId);
                $renovar_arbol = false;
            }
            $fountIt = false;
            if ($level == 0) {
                foreach ($arbol as $key => $values) {
                    //Busca el grupo especifico
                    if ($this->comparacionEspecial($values['nombre'], $chain_parts[$level])) {
                        $fountIt = true;
                        break;
                    }
                }
            } else {
                //Grupo inmediato anterior
                $parent = $this->getIdPorNombreYCategoria($chain_parts[$level - 1], $categoriaId);
                $tmp_arbol = $this->gestionaArbolInterno($arbol, $parent);

                if ($tmp_arbol['gold']) {
                    foreach ($tmp_arbol['data'] as $key => $values) {
                        //Busca el grupo especifico
                        if ($this->comparacionEspecial($values['nombre'], $chain_parts[$level])) {
                            $fountIt = true;
                            if ($level == count($chain_parts) - 1) {
                                //Ultima jerarquia
                                $ans = $values['id'];
                            }
                            break;
                        }
                    }
                }
            }
            if (!$fountIt) {
                if ($level > 0 && $parent == 0) {
                    trigger_error("Error: creacion dinamica de grupos de boletines");
                } else {
                    if (!$estricto) {
                        //No es estricto. Se crearan grupos si no existen
                        $ans = $this->insertaBoJerarquia($chain_parts[$level], $categoriaId, $parent);
                        if ($ans == 0) {
                            trigger_error("Error: imposibilidad de crear jerarquia de boletines");
                        } else {
                            $renovar_arbol = true;
                        }
                    } else {
                        //Es estricto. Si no existe grupo no se creara y devolvera 0
                    }
                }
            }
        }
        return $ans;
    }

    //__Descripción:__ Función que permite comparar dos cadenas limpiando los caracteres especiales y tildes
    //__Inputs:__ $a:string valor a comparar 1
    //            $b:string valor a comparar 2
    //__Outputs:__ $result:boolean true/false
    private function comparacionEspecial($a, $b) {
        $convertir_de = array(
            "á", "é", "í", "ó", "ú", "À", "Á", "Â", "Ã", "Ä", "Å", "Æ", "Ç", "È", "É", "Ê", "Ë", "Ì", "Í", "Î", "Ï",
            "Ð", "Ò", "Ó", "Ô", "Õ", "Ö", "Ø", "Ù", "Ú", "Û", "Ü", "Ý", "°", "¤", "¦", "º", "?", "?", "½", "¡", "¯", "¿", "\t", "\s", "¼", "Ñ"
        );
        $convertir_a = array(
            "a", "e", "i", "o", "u", "A", "A", "A", "A", "A", "A", "A", "", "E", "E", "E", "E", "I", "I", "I", "I",
            "", "O", "O", "O", "O", "O", "O", "U", "U", "U", "U", "Y", " ", "", "", " ", " ", " ", "1/2", "", "", "", "", " ", " ", "1/4", "#"
        );
        $a_clean = strtolower(str_replace($convertir_de, $convertir_a, $a));
        $b_clean = strtolower(str_replace($convertir_de, $convertir_a, $b));
        if ($a_clean == $b_clean) {
            return true;
        } else {
            return false;
        }
    }

    //__Descripción:__ Función que permite obtener recursivamente una coincidencia con el parámetro $parent
    //__Inputs:__ $arbol:array lista de elementos en donde se buscará $parent
    //            $parent:int valor a buscar dentro de $arbol
    //__Outputs:__ $sons:array lista de datos antecesores a $parent
    private function gestionaArbolInterno($arbol, $parent) {
        $sons = ['gold' => false, 'data' => []];
        $fountIt = false;
        foreach ($arbol as $key => $value) {
            if ($value['id'] == $parent) {
                $fountIt = true;
                //Unica salida satisfactoria
                $sons = ['gold' => true, 'data' => $value['hijos']];
                break;
            } else {
                if (count($value['hijos']) > 0) {
                    $sons = $this->gestionaArbolInterno($value['hijos'], $parent);
                    break;
                }
            }
        }
        return $sons;
    }

    //__Descripción:__ Función que permite obtener descripción de selección dentro de arbol
    //__Inputs:__ $categoriaId:int id de tabla bocategoria
    //__Outputs:__ $ans:array lista de descripciones de jerarquía
    function obtieneArborJerarquias($categoriaId) {
        $ans = [];
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * 
                               FROM bojerarquia
                               WHERE boJerarquia_categoriaId=%N", $categoriaId);
        $db->query($sql);
        $parents = [0];
        $jerarquiasObj = [];
        while ($row = $db->fetchRow()) {
            $jerarquiasObj[] = $row;
        }
        //Padres por referencia
        foreach ($parents as &$parent) {
            //Objetos configurados en DB
            foreach ($jerarquiasObj as $values) {
                if ($values['boJerarquia_padreId'] == $parent) {
                    array_push($parents, $values['boJerarquia_id']);
                    $temp_values = [
                        'id' => $values['boJerarquia_id'],
                        'nombre' => $values['boJerarquia_nombre'],
                        'descripcion' => $values['boJerarquia_descripcion'],
                        'hijos' => []
                    ];
                    if ($parent != 0) {
                        //Debe ser hijo
                        $ans = $this->buscarInterno($ans, $parent, $temp_values);
                    } else {
                        //Es raiz
                        $ans[] = $temp_values;
                    }
                }
            }
        }
        return $ans;
    }

    //__Descripción:__ Función que permite obtener dentro de un array los ancestros de $parent
    //__Inputs:__ $ans:array lista de registros
    //            $parent:int valor buscado
    //            $temp_values:array que contiene los ancestros
    //__Outputs:__ $ans:array listado de ancestros de $parent
    private function buscarInterno($ans, $parent, $temp_values) {
        foreach ($ans as &$values) {
            if ($values['id'] == $parent) {
                array_push($values['hijos'], $temp_values);
                break;
            } else {
                if (count($values['hijos']) > 0) {
                    $values['hijos'] = $this->buscarInterno($values['hijos'], $parent, $temp_values);
                }
            }
        }
        return $ans;
    }

    //funcion que crea la tabla en al base de datos
    function checkStructure() {
        if (DEVELOPMENT) {
            $db = new MYSQLDB();
            $db->mantieneBase(
                    array(
                        "table" => "bojerarquia",
                        "prefix" => "boJerarquia_",
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
                                "name" => "descripcion",
                                "type" => "varchar",
                                "size" => "200",
                                "default" => "",
                                "special" => "",
                                "index" => "",
                            ),
                            array(
                                "name" => "activo",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "categoriaId",
                                "type" => "int",
                                "size" => "",
                                "default" => "",
                                "special" => "",
                                "index" => "normal",
                            ),
                            array(
                                "name" => "padreId",
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
