<?php
if (PHP_VERSION_ID >= 80000) {
    //Mongo para PHP 8
    require_once BASEFOLDER . 'functions/vendor_mongodb_82/autoload.php';
}elseif (PHP_VERSION_ID >= 70000) {
    //Mongo para PHP 7
    require_once BASEFOLDER . 'functions/vendor/autoload.php';
}
if (PHP_VERSION_ID >= 70000) {

    //Mongo para PHP 7 y 8
    class MYMONGODB {

        //atributos de la clase
        private $postfix;
        private $prefix;
        private $targetHost;
        private $targetPort;
        private $my;
        private $myDB;
        private $cursor = false;
        private $seleccion = []; //conexion seleccionada
        private $mdbinterno;

        public function getCursor() {
            return $this->cursor;
        }

        public function __construct($postfix = "") {
            //__Descripcion__: create object
            //__Input__: string $postfix opcionalmente se de entrega un identificador de la base de datos a utilizar
            //__Output__: null
            global $propias, $prefijoDBGlobal;
            //revisemos si existe un $prefijoDBGlobal a utilizar
            $this->prefix = '';
            if (isset($prefijoDBGlobal)) {
                $this->prefix = $prefijoDBGlobal;
            }
            $this->postfix = $this->prefix . $postfix;
            //selecciona la conexion apropiada segun disponibilidad
            if (isset($propias["MONGODB" . $this->postfix . "_CONN"])) {
                //hay una sola conexión?
                if (count($propias["MONGODB" . $this->postfix . "_CONN"]) == 1) {
                    $tmp = array_keys($propias["MONGODB" . $this->postfix . "_CONN"]);
                    $primerKey = array_shift($tmp);
                    $sel = $propias["MONGODB" . $this->postfix . "_CONN"][$primerKey];
                } else {
                    $hayIndice = false;
                    $redis = new MYREDISDB();
                    try {
                        $indice = $redis->query("hGet", "selectorBases:MONGODB:" . $this->postfix, "indice");
                    } catch (Exception $e) {
                        //continue como cuando no hay indice
                        $indice = false;
                    }
                    if ($indice !== false) {
                        if (isset($propias["MONGODB" . $this->postfix . "_CONN"][$indice])) {
                            $sel = $propias["MONGODB" . $this->postfix . "_CONN"][$indice];
                            $hayIndice = true;
                        }
                    }
                    if (!$hayIndice) {
                        //no hay un registro en redis aun o tiene un indice inexistente
                        //cree uno con el primer elemento
                        $tmp = array_keys($propias["MONGODB" . $this->postfix . "_CONN"]);
                        $primerKey = array_shift($tmp);
                        $redis->query(
                                "hMSet", "selectorBases:MONGODB:" . $this->postfix, ["indice" => $primerKey, "descripcion" => "Registro creado automáticamente"]
                        );
                        $sel = $propias["MONGODB" . $this->postfix . "_CONN"][$primerKey];
                    }
                }
                $this->seleccion = [
                    "HOST" => $sel["HOST"],
                    "USERNAME" => $sel["USERNAME"],
                    "PWD" => $sel["PWD"],
                    "DATABASE" => $sel["DATABASE"],
                ];
            } else {
                $this->seleccion = [
                    "HOST" => $propias["MONGODB" . $this->postfix . "_HOST"],
                    "USERNAME" => $propias["MONGODB" . $this->postfix . "_USERNAME"],
                    "PWD" => $propias["MONGODB" . $this->postfix . "_PWD"],
                    "DATABASE" => $propias["MONGODB" . $this->postfix . "_DATABASE"],
                ];
            }
            $this->targetHost = strtolower($this->seleccion["HOST"]);
            $this->targetPort = ini_get("mongo.default_port");
            if ($this->targetPort == "") {
                $this->targetPort = "27017";
            }
            if (strpos($this->targetHost, ":") !== false) {
                //different port
                $this->targetHost = substr($this->targetHost, 0, strpos($this->targetHost, ":"));
                $this->targetPort = substr(strtolower($this->seleccion["HOST"]), strpos(strtolower($this->seleccion["HOST"]), ":") + 1);
            }
            $laClave = $this->seleccion["PWD"];
            if (ctype_xdigit(substr($laClave, 2))) {
                $pass = strrev("MONGODB" . $this->postfix . "_HOST") . strrev("MONGODB" . $this->postfix . "_USERNAME") . strrev("MONGODB" . $this->postfix . "_DATABASE");
                $pass = str_pad(substr($pass . strtoupper($pass) . $pass . strtolower($pass) . $pass, 0, 32), 32, ".");
                $laClave = decriptCompress(hexToString($laClave), goodKey($pass));
            }
            //antes de instanciar la clase, lea cuantas veces se han levantado conexiones a mongodb desde el mismo script y linea
//            $caller=debug_backtrace();
//            $ultimoCaller=array_pop($caller);
//            if(isset($ultimoCaller['file'])){
//                $periodoScript=5; //expira en n segundos
//                $redis=new MYREDISDB();
//                $usuarioScript=isset($_SESSION[MID.'userId'])?$_SESSION[MID.'userId']:0;
//                if(!$cuentaScript=$redis->query('get','mongo:scriptlog:'.$usuarioScript.$ultimoCaller['file'].'|'.$ultimoCaller['line'])){
//                    $cuentaScript=0;
//                }
//                if($cuentaScript >= 50){
//                    //usleep(5000); //ralentice este script
//                    if($cuentaScript % 50==0){
//                        foreach($caller as $cc){
//                            if(isset($cc['file'])){
//                                trigger_error('Rutinas previas: ' . $cc['file'].' | '.$cc['line']);
//                            }
//                        }
//                        trigger_error('Uso intensivo de '.$cuentaScript.' conexiones MongoDB en '.$periodoScript.'s - '.$usuarioScript .' - '. $ultimoCaller['file'].' | '.$ultimoCaller['line']);
//                    }
//                }
//            }
            $this->my = new MongoDB\Client(
                    "mongodb://" . $this->seleccion["USERNAME"] . ":" . $laClave . "@" . $this->targetHost . ":" . $this->targetPort . "/" . $this->seleccion["DATABASE"], array("socketTimeoutMS" => 1200000));
            $this->myDB = $this->my->selectDatabase($this->seleccion["DATABASE"]);
//            if(isset($ultimoCaller['file'])){
//                //guarde el registro del script que abrio esta conexion en REDIS, en un registro expirable
//                $redis->query('set','mongo:scriptlog:'.$usuarioScript.$ultimoCaller['file'].'|'.$ultimoCaller['line'],$cuentaScript+1);
//                $redis->query('expire','mongo:scriptlog:'.$usuarioScript.$ultimoCaller['file'].'|'.$ultimoCaller['line'],$periodoScript);
//        }
            }

        public function traerColecciones() {
            //__Descripcion__: Obtiene todas las colecciones de esta base mongo
            //__Input__: null
            //__Output__: array con las colecciones
            $cols = $this->myDB->listCollections();
            $colsArray = [];
            foreach ($cols as $col) {
                $colsArray[] = $col;
            }
            return $colsArray;
        }

        public function String2MongoId($id) {
            //__Descripcion__: Convierte string a id de mongo
            //__Input__: string $id
            //__Output__: objeto id mongo

            return new MongoDB\BSON\ObjectId($id);
        }
        
        public function renombrar($coleccion,$coleccionNew) {
            //__Descripcion__: Renombra una colección
            //__Input__: string $coleccion a renombrar, string $coleccionNew nuevo nombre
            //__Output__: boolean éxito o fracaso
            if (!expect_scalar($coleccion) || !expect_scalar($coleccionNew)) {
                return false;
            }
            try {
                $col = $this->myDB->selectCollection($coleccion);
                if ($col) {
                    $result = $col->rename($coleccionNew);
                    return true;
                }else{
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }

        public function crearColeccion($nombre, $opciones = array("capped" => false, "size" => 0, "max" => 0)) {
            //__Descripcion__: crea una nueva coleccion en la base de datos, esta funcion solo debe llamarse cuando se crear colecciones con opciones especiales, pues caso contrario la coleccion se crea automaticamente. NOTA_IMPORTANTE: una coleccion con la opcion capped NO permite operaciones de borrado!
            //__Input__: string $nombre de la coleccion, array $opciones a utilizar
            //__Output__: boolean exito o fracaso
            if (expect_scalar($nombre)) {
                try {
                    $col = $this->myDB->createCollection($nombre, $opciones);
                    return $col['ok'];
                } catch (Exception $e) {
                    //ya existia la coleccion, no haga nada
                    return true;
                }
            }
        }

        public function borrarColeccion($nombre) {
            //__Descripcion__: borra una coleccion en la base de datos
            //__Input__: string $nombre de la coleccion
            //__Output__: boolean exito o fracaso
            try {
                $col = $this->myDB->dropCollection($nombre);
                return $col['ok'];
            } catch (Exception $e) {
                //ya existia la coleccion, no haga nada
                return true;
            }
        }

        public function crearVista(string $nombreVista, string $coleccionBase, array $pipeline = []) : bool
        {
            //__Descripcion__: crea una nueva vista en la base de datos basada en una colección existente
            //__Input__: 
            //   string $nombreVista: nombre de la vista a crear
            //   string $coleccionBase: colección sobre la que se aplicará el pipeline
            //   array $pipeline: pipeline de agregación para la vista
            //__Output__: boolean exito o fracaso

            if (expect_scalar($nombreVista) && expect_scalar($coleccionBase) && is_array($pipeline)) {
                try {
                    $opciones = [
                        'viewOn' => $coleccionBase,
                        'pipeline' => $pipeline
                    ];
                    $col = $this->myDB->createCollection($nombreVista, $opciones);
                    return isset($col['ok']) ? $col['ok'] : true;
                } catch (Exception $e) {
                    // si la vista ya existía, no hacer nada
                    return true;
                }
            }
            return false;
        }

        public function borrarVista(string $nombreVista) : bool
        {
            //__Descripcion__: borra una vista en la base de datos
            //__Input__: string $nombreVista: nombre de la vista a borrar
            //__Output__: boolean exito o fracaso
            try {
                $col = $this->myDB->dropCollection($nombreVista);
                return isset($col['ok']) ? $col['ok'] : true;
            } catch (Exception $e) {
                // si no existía, no hacer nada
                return true;
            }
        }


        public function crearIndiceDeTexto($coleccion, $opciones) {
            //__Descripcion__: crea un full text index de la $coleccion si no existe ya previamente
            //__Input__: string $coleccion nombre de la tabla, array $opciones un array de opciones a enviar a mongo para la creacion del full text index
            //__Output__: boolean exito o fracaso
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            if ($col) {
                $resultado = $col->createIndex($opciones);
                return $resultado;
            }
            return false;
        }

        public function guardarbatch($coleccion, $documentos) {
            //__Descripcion__: insertar o guardar un nuevo documento en la coleccion
            //__Input__: string $coleccion nombre de la colección para insertar, $documentos array de todos los ducmento que se desea guardar;
            //__Output__: id del documento insertado, o 0 en caso de error
            //debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //pase a utf-8
            $documentos = array_encode_any($documentos);
            //si nos enviaron un id o _id, entonces se hace update, caso contrario se hace insert
            if (isset($documentos["id"]) && !isset($documentos["_id"])) {
                $documentos["_id"] = new MongoId($documentos["id"]);
            }
            $r = $col->batchInsert($documentos, array('continueOnError' => true));
            if ($r["ok"] == 1) {
                return true;
            } else {
                return false;
            }
        }

        public function crearIndice($coleccion, $columnas, $opciones = array("unique" => false, "sparse" => false)) {
            //__Descripcion__: crea un indice de la $coleccion si no existe ya previamente
            //__Input__: string $coleccion nombre de la tabla, array $columnas con una lista simple de nombres de campo o con una lista de nombres de campo como keys y 1 o -1 como valor para indicar un índice ascendente o descendente, array $opciones (opcional) un array de opciones a enviar a mongo para la creacion del indice
            //__Output__: boolean exito o fracaso
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            if ($col && is_array($columnas) && count($columnas) > 0) {
                //enviaron una lista de nombres simple? aniada orden ascendente
                if (array_keys($columnas) === range(0, count($columnas) - 1)) {
                    $newColumnas = [];
                    foreach ($columnas as $ob) {
                        $newColumnas[$ob] = 1; //indice ascendente
                    }
                } else {
                    //caso contrario utilice el array que nos enviaron
                    $newColumnas = $columnas;
                }
                if (!is_array($newColumnas)) {
                    return false;
                }
                return $col->createIndex($newColumnas, $opciones);
            }
            return false;
        }

        private function registrosParaCubos($coleccion, $criterio = []) {
            //__Descripcion__: devuelve un array con datos de el o los cubos en los que está involucrada esta coleccion de Mongo
            //__Input__: string $coleccion nombre de la coleccion, array $criterio
            //__Output__: array datos de cubos involucradas
            
            //CUBOS
            global $propias;
            $cubosInvolucrados = [];
            if(count($criterio)==0){
                return $cubosInvolucrados;
            }
            if ($coleccion == 'usSesiones' || $coleccion == 'audRegistro') {
                return $cubosInvolucrados;
            }
            //hay colecciones de mymongodb involucradas en cubos?
            if (isset($propias["datos_cubos"]) && count($propias["datos_cubos"]) > 0) {
                //veamos si el cambio que haga este query debe loguearse
                foreach ($propias["datos_cubos"] as $dc) {
                    foreach ($dc["cuCubos_colecciones"] as $cc) {
                        if ($coleccion == $cc["nombre"]) {
                            $datosParaCubos = [];
                            if (count($criterio) > 0) {
                                $this->mdbinterno = new MYMONGODB();
                                $this->mdbinterno->buscar($coleccion, $criterio, [$cc["identificador"]]);
                                while ($doc = $this->mdbinterno->siguiente()) {
                                    $datosParaCubos[] = $coleccion . ':' . (string) $doc[$cc["identificador"]];
                                }
                            }
                            if(count($datosParaCubos) > 0){
                                $cubosInvolucrados[] = [
                                    'plugin' => $dc["cuCubos_plugin"],
                                    'coleccion' => $coleccion,
                                    'identificador' => $cc["identificador"],
                                    'datosParaCubos' => $datosParaCubos,
                                ];
                            }
                            break;
                        }
                    }
                }
            }
            return $cubosInvolucrados;
        }

        public function guardar($coleccion, $data, $path = "", $size = 0) {
            //__Descripcion__: insertar o guardar un nuevo documento en la coleccion
            //__Input__: string $coleccion nombre de la colección para insertar, $data array de datos para insertar
            //__Output__: id del documento insertado, o 0 en caso de error
            //debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //pase a utf-8
            if (isset($data["_id"])) {
                $elId = $data["_id"];
                unset($data["_id"]);
            }
            //limpio los datos
            $data = utf8_converter($data);

            if (isset($elId) && is_object($elId) && (get_class($elId) == 'MongoDB\BSON\ObjectId' || get_class($elId) == 'MongoDB\BSON\ObjectID')) {
                $data["_id"] = $elId;
                $data["id"] = $data["_id"]->__toString();
            } elseif (isset($data["id"])) {
                //si nos enviaron un id o _id, entonces se hace update, caso contrario se hace insert
                $data["_id"] = new MongoDB\BSON\ObjectId($data["id"]);
            }

            if ($path != "") {
                //creamos un nombre a partir del $path
                $pathInfo = pathinfo($path);
                $nombre = $pathInfo["filename"];
                $source = fopen($path, "r");
                if ($source) {
                    // GridFS
                    $gridFS = $this->myDB->selectGridFSBucket();
                    // Note metadata field & filename field
                    $storedfile = $gridFS->uploadFromStream($nombre, $source, array("metadata" => $data));
                    fclose($source);
                    return (string) $storedfile;
                } else {
                    $caller = debug_backtrace();
                    trigger_error("Mongo GridFS error: no se pudo leer el archivo temporal" . FT . $caller[0]["file"] . FT . $caller[0]["line"], E_USER_ERROR);
                }
            }

            if (isset($data['_id'])) {
                try {
                    $result = $col->updateOne(['_id' => $data['_id']], ['$set' => $data], ["upsert" => true]);
                    $resultId = $result->getUpsertedId();
                } catch (Exception $e) {
                    $resultId = 0;
                }
            } else {
                try {
                    $result = $col->insertOne($data);
                    $resultId = $result->getInsertedId();
                } catch (Exception $e) {
                    $resultId = 0;
                }
            }
            if ($resultId !== 0) {
                $cubosInvolucrados = $this->registrosParaCubos($coleccion);
                if(count($cubosInvolucrados) > 0){
                    require_once BASEFOLDER . 'comunes/classes/class.myredisdb.php';
                    $redisCubo = new MYREDISDB();
                    foreach ($cubosInvolucrados as $cI) {
                        $params = array_merge(['SADD', 'CUMONGO:' . $cI["plugin"] . ':add'], [$coleccion . ':' . (string) $resultId]);
                        call_user_func_array(array($redisCubo, "query"), $params);
                        $redisCubo->query('SET', 'CUMONGO:' . $cI["plugin"] . ':last', time());
                    }
                }
            }
            return (string) $resultId;
        }

        public function actualizar($coleccion, $criterio, $nuevaData, $soloUnRegistro = false) {
            //__Descripcion__: actualizar uno o mas documentos ya existentes en una coleccion
            //__Input__: string $coleccion nombre de la colección para actualizar, array $criterio condiciones para aplicar la actualizacion, $nuevaData array de datos para actualizar, $soloUnRegistro opcional, si quiero que se actualice sólo un registro de los que cumplen el criterio
            //__Output__: número de registros actualizados
            // --!-- debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $cubosInvolucrados = $this->registrosParaCubos($coleccion, $criterio);
            $col = $this->myDB->selectCollection($coleccion);
            $opciones = array("multiple" => !$soloUnRegistro);
            $nuevaData = utf8_converter($nuevaData);
            if ($soloUnRegistro) {
                try {
                    $res = $col->updateOne($criterio, ['$set' => $nuevaData]);
                    $cuenta = $res->getModifiedCount();
                } catch (Exception $e) {
                    trigger_error($e);
                    $cuenta = 0;
                }
            } else {
                try {
                    $res = $col->updateMany($criterio, ['$set' => $nuevaData]);
                    $cuenta = $res->getModifiedCount();
                } catch (Exception $e) {
                    trigger_error($e);
                    $cuenta = 0;
                }
            }
            if ($cuenta !== 0) {
                if(count($cubosInvolucrados) > 0){
                    require_once BASEFOLDER . 'comunes/classes/class.myredisdb.php';
                    $redisCubo = new MYREDISDB();
                    foreach ($cubosInvolucrados as $cI) {
                        $params = array_merge(['SADD', 'CUMONGO:' . $cI["plugin"] . ':add'], $cI["datosParaCubos"]);
                        call_user_func_array(array($redisCubo, "query"), $params);
                        $redisCubo->query('SET', 'CUMONGO:' . $cI["plugin"] . ':last', time());
                        //trigger_log($c." en tabla ".$tablaEnCubo." con datos ".print_r($cI["datosParaCubos"],true)." en el cubo ".$cI["plugin"]);
                    }
                }
            }
            return $cuenta;
        }

        public function actualizarPuro($coleccion, $criterio, $nuevaData){
            //__Descripcion__: actualizar uno o mas documentos ya existentes en una coleccion
            //__Input__: string $coleccion nombre de la colección para actualizar, array $criterio condiciones para aplicar la actualizacion, $nuevaData array de datos para actualizar, $soloUnRegistro opcional, si quiero que se actualice sólo un registro de los que cumplen el criterio
            //__Output__: número de registros actualizados
            // --!-- debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $cubosInvolucrados = $this->registrosParaCubos($coleccion, $criterio);
            $col = $this->myDB->selectCollection($coleccion);
            $nuevaData = utf8_converter($nuevaData);

            try {
                $res = $col->updateOne($criterio, $nuevaData);
                $cuenta = $res->getModifiedCount();
            } catch (Exception $e) {
                trigger_error($e);
                $cuenta = 0;
            }

            if ($cuenta !== 0) {
                if (count($cubosInvolucrados) > 0) {
                    require_once BASEFOLDER . 'comunes/classes/class.myredisdb.php';
                    $redisCubo = new MYREDISDB();
                    foreach ($cubosInvolucrados as $cI) {
                        $params = array_merge(['SADD', 'CUMONGO:' . $cI["plugin"] . ':add'], $cI["datosParaCubos"]);
                        call_user_func_array(array($redisCubo, "query"), $params);
                        $redisCubo->query('SET', 'CUMONGO:' . $cI["plugin"] . ':last', time());
                    }
                }
            }
            return $cuenta;
        }
        
        public function eliminarCampo($coleccion, $criterio, $camposABorrar, $soloUnRegistro = false) {
            //__Descripcion__: actualizar uno o mas documentos ya existentes en una coleccion, eliminando uno o varios campos
            //__Input__: string $coleccion nombre de la colección para actualizar, array $criterio condiciones para aplicar la actualizacion, $camposABorrar array de datos para actualizar, $soloUnRegistro opcional, si quiero que se actualice sólo un registro de los que cumplen el criterio
            //__Output__: número de registros actualizados
            // --!-- debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $cubosInvolucrados = $this->registrosParaCubos($coleccion, $criterio);
            $col = $this->myDB->selectCollection($coleccion);
            $opciones = array("multiple" => !$soloUnRegistro);
            $camposABorrar = array_encode_any($camposABorrar);
            if ($soloUnRegistro) {
                $res = $col->updateOne($criterio, ['$unset' => $camposABorrar]);
            } else {
                $res = $col->updateMany($criterio, ['$unset' => $camposABorrar]);
            }
            $cuenta = $res->getModifiedCount();
            if(count($cubosInvolucrados) > 0){
                require_once BASEFOLDER . 'comunes/classes/class.myredisdb.php';
                $redisCubo = new MYREDISDB();
                foreach ($cubosInvolucrados as $cI) {
                    $params = array_merge(['SADD', 'CUMONGO:' . $cI["plugin"] . ':add'], $cI["datosParaCubos"]);
                    call_user_func_array(array($redisCubo, "query"), $params);
                    $redisCubo->query('SET', 'CUMONGO:' . $cI["plugin"] . ':last', time());
                    //trigger_log($c." en tabla ".$tablaEnCubo." con datos ".print_r($cI["datosParaCubos"],true)." en el cubo ".$cI["plugin"]);
                }
            }
            return $cuenta;
        }

        public function borrar($coleccion, $criterio, $soloUnRegistro = false, $gridFS = false) {
            //__Descripcion__: borrar un documento de una colección
            //__Input__: string $coleccion nombre de la colección, $criterio condición para la eliminación, $soloUnRegistro opcional indica si se eliminan todos los registros que cumplen el criterio o sólo uno, por defecto se borrarán todos.
            //__Output__: true si se ejecutó con éxito, false caso contrario.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $res = [];
            switch ($gridFS) {
                case true:
                    // GridFS
                    $gridFS = $this->myDB->selectGridFSBucket();
                    if (is_object($criterio)) {
                        $res = $gridFS->delete(['_id' => $criterio]);
                    } elseif (is_string($criterio)) {
                        $res = $gridFS->delete(new MongoDB\BSON\ObjectId($criterio));
                    }
                    return 1;
                    break;
                default:
                    $criterio = array_encode_any($criterio);
                    $cubosInvolucrados = $this->registrosParaCubos($coleccion, $criterio);
                    $col = $this->myDB->selectCollection($coleccion);
                    if ($soloUnRegistro) {
                        $res = $this->myDB->$coleccion->deleteOne($criterio);
                    } else {
                        $res = $this->myDB->$coleccion->deleteMany($criterio);
                    }
                    if ($res->isAcknowledged()) {
                        $cuenta = $res->getDeletedCount();
                        if(count($cubosInvolucrados) > 0){
                            require_once BASEFOLDER . 'comunes/classes/class.myredisdb.php';
                            $redisCubo = new MYREDISDB();
                            foreach ($cubosInvolucrados as $cI) {
                                //quite los adds si existieran
                                $params = array_merge(['SREM', 'CUMONGO:' . $cI["plugin"] . ':add'], $cI["datosParaCubos"]);
                                call_user_func_array(array($redisCubo, "query"), $params);
                                $params = array_merge(['SADD', 'CUMONGO:' . $cI["plugin"] . ':del'], $cI["datosParaCubos"]);
                                call_user_func_array(array($redisCubo, "query"), $params);
                                $redisCubo->query('SET', 'CUMONGO:' . $cI["plugin"] . ':last', time());
                            }
                        }
                        return $cuenta;
                    } else {
                        trigger_error("MYMONGODB->borrar: " . $res["errmsg"], E_USER_NOTICE);
                        return false;
                    }
                    break;
            }
        }

        public function siguiente() {
            //__Descripcion__: permite avanzar en el cursor del objeto instanciado
            //__Input__: null
            //__Output__: documento siguiente o false

            if ($this->cursor == false)
                return false;
            $doc = $this->cursor->current();

            if ($doc) {
                //ahora añadamos el id como string si no hubiera
                if (isset($doc["_id"]) && is_object($doc["_id"]) && (get_class($doc["_id"]) == 'MongoDB\BSON\ObjectID' || get_class($doc["_id"]) == 'MongoDB\BSON\ObjectId')) {
                    $doc["id"] = $doc["_id"]->__toString();
                }
                $doc = array_encode_any($doc, $reverse = true);
                $this->cursor->next();
                return $doc;
            } else {
                return false;
            }
        }

        public function siguientex() {
            //__Descripcion__: permite avanzar en el cursor del objeto instanciado
            //__Input__: null
            //__Output__: documento siguiente o false

            if ($this->cursor == false)
                return false;
            $doc = $this->cursor->current();

            if ($doc) {
                $this->cursor->next();
                return $doc;
            } else {
                return false;
            }
        }

        public function buscarGroupBy($coleccion, $keys, $initial, $reduce, $condition = array()) {
            //__Descripcion__: buscar los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $coleccion nombre de la colección, string $criterios condiciones para la busqueda
            trigger_error("buscarGroupBy no está implementado en mymogodb para PHP 7");
            return false;

            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            if (!is_array($condition) || count($condition) == 0) {
                $respuesta = $col->group($keys, $initial, $reduce);
            } else {
                $respuesta = $col->group($keys, $initial, $reduce, $condition);
            }
            return $respuesta;
        }

        public function agregar($coleccion, $pipelines, $criterios = [], $orderBy = [], $limit = 0, $skip = 0) {
            //__Descripcion__: Realiza una acumulación usando el framework de acumulación
            //__Input__: string $coleccion la coleccion, array $options array de operadores para el aggregate 
            //__Output__: CommandCursor el CommandCursor resultante
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //si no envian $criterios, devuelve todo
            //$pipelines = array_encode_any($pipelines);
            if (is_array($criterios) && count($criterios) > 0) {
                //$criterios = array_encode_any($criterios);
                $pipelines[] = ['$match' => $criterios];
            }
            if (is_array($orderBy) && count($orderBy) > 0) {
                //$criterios = array_encode_any($orderBy);
                $pipelines[] = ['$sort' => $orderBy];
            }
            if ($skip > 0) {
                $pipelines[] = ['$skip' => (int) $skip];
            }
            if ($limit > 0) {
                $pipelines[] = ['$limit' => (int) $limit];
                $opcionesAdicionales = [
                    "cursor" => ["batchSize" => (int) $limit - 1],
                    "allowDiskUse" => true,
                ];
            } else {
                $opcionesAdicionales = [
                    "allowDiskUse" => true,
                ];
            }
            $cursor = $col->aggregate($pipelines, $opcionesAdicionales);
            $this->cursor = new IteratorIterator($cursor);
            $this->cursor->rewind();
            return $this->cursor;
        }

        public function aggregate($coleccion, $pipelines) {
            //__Descripcion__: Realiza una acumulación usando el framework de acumulación
            //__Input__: string $coleccion tabla a operar, array $pipelines array de operadores de pipeline. 
            //__Output__: array de respuesta
            $this->agregar($coleccion, $pipelines);
        }

        public function buscar($coleccion, $criterios = array(), $campos = array(), $orderBy = array(), $limit = 0, $skip = 0) {
            //__Descripcion__: buscar los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $coleccion nombre de la colección, array $criterios condiciones para la busqueda, array $campos campos a devolver, array $orderBy campos por los que se quiere ordenar los resultados, int $limit cuantos registros devolver, int $skip en que registro empezar 
            //__Output__: cursor de MongoDB
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //si no envian $criterios, devuelve todo
            //        $criterios = array_encode_any($criterios);
            $criterios = $criterios;
            //        print_h("valor dentro de la clase");
            //        print_h($criterios);

            $opciones = [];
            if ($skip > 0) {
                $opciones["skip"] = (int) $skip;
            }
            if ($limit > 0) {
                $opciones["limit"] = (int) $limit;
            }
            $cuantos = $col->count($criterios, $opciones);
            //si no envian $campos devuelve todos
            if (is_array($campos) && count($campos) > 0) {
                if (array_keys($campos) === range(0, count($campos) - 1)) {
                    //enviaron una lista de nombres simple
                    $newCampos = array();
                    foreach ($campos as $ob) {
                        $newCampos[$ob] = true;
                    }
                } else {
                    //enviaron una lista que ya tiene el formato Mongo
                    $newCampos = $campos;
                }
                $opciones["projection"] = $newCampos;
            }
            if (is_array($orderBy) && count($orderBy) > 0) {
                if (array_keys($orderBy) === range(0, count($orderBy) - 1)) {
                    //enviaron una lista de nombres simple
                    $newOrderBy = array();
                    foreach ($orderBy as $ob) {
                        $newOrderBy[$ob] = 1;
                    }
                } else {
                    //enviaron una lista que ya tiene el formato Mongo
                    $newOrderBy = $orderBy;
                }
                $opciones["sort"] = $newOrderBy;
            }
            $cursor = $col->find($criterios, $opciones);
            $this->cursor = new IteratorIterator($cursor);
            $this->cursor->rewind();
            return $cuantos;
        }

        public function buscarDistinct($field, $coleccion, $criterios = array()) {
            //__Descripcion__: distinct de los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $field campo que se le aplica distinct, string $coleccion nombre de la colección, array $criterios condiciones para la busqueda 
            //__Output__: cursor de MongoDB
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            $criterios = $criterios;
            $listaResultados = $col->distinct($field, $criterios);
            return $listaResultados;
        }

        public function obtieneBytes($coleccion, $id) {
            //__Descripcion__: trae el contenido de un gridFS
            //__Input__: string $coleccion nombre de la colección, string $id identificador único del documeto en la colección
            //__Output__: devuelve el contenido binario del archivo

            $gridFS = $this->myDB->selectGridFSBucket();
            if (is_object($id)) {
                $fp = $gridFS->openDownloadStream($id);
            } elseif (is_string($id)) {
                $fp = $gridFS->openDownloadStream(new MongoDB\BSON\ObjectId($id));
            }
            $base64string = "";
            while ($contenido = fread($fp, 10000)) {
                $base64string .= $contenido;
            }
            return $base64string;
        }

        public function buscarPorId($coleccion, $id, $useGridFS = false) {
            //__Descripcion__: trae un documento de una coleccion, dado un id
            //__Input__: string $coleccion nombre de la colección, string $id identificador único del documeto en la colección
            //__Output__: array con la información del documento
            switch ($useGridFS) {
                case true:
                    // GridFS
                    $gridFS = $this->myDB->selectGridFSBucket();
                    if (is_object($id)) {
                        $doc = $gridFS->findOne(array("_id" => $id));
                    } elseif (is_string($id)) {
                        $doc = $gridFS->findOne(array("_id" => new MongoDB\BSON\ObjectId($id)));
                    }
                    break;
                default:
                    if (!expect_scalar($coleccion)) {
                        return false;
                    }
                    $col = $this->myDB->selectCollection($coleccion);
                    if (is_object($id)) {
                        $doc = $col->findOne(["_id" => $id]);
                    } elseif (is_string($id)) {
                        $doc = $col->findOne(["_id" => new MongoDB\BSON\ObjectId($id)]);
                    }
                    break;
            }
            return $doc;
        }

    }

} else {

    //Mongo para PHP 5
    class MYMONGODB {

        //atributos de la clase
        private $postfix;
        private $prefix;
        private $targetHost;
        private $targetPort;
        private $my;
        private $myDB;
        private $cursor = false;
        private $seleccion = []; //conexion seleccionada

        public function getCursor() {
            return $this->cursor;
        }

        public function __construct($postfix = "") {
            //__Descripcion__: create object
            //__Input__: string $postfix opcionalmente se de entrega un identificador de la base de datos a utilizar
            //__Output__: null
            global $propias, $prefijoDBGlobal;
            //revisemos si existe un $prefijoDBGlobal a utilizar
            $this->prefix = '';
            if (isset($prefijoDBGlobal)) {
                $this->prefix = $prefijoDBGlobal;
            }
            $this->postfix = $this->prefix . $postfix;
            //selecciona la conexion apropiada segun disponibilidad
            if (isset($propias["MONGODB" . $this->postfix . "_CONN"])) {
                $hayIndice = false;
                $redis = new MYREDISDB();
                $indice = $redis->query("hGet", "selectorBases:MONGODB:" . $this->postfix, "indice");
                if ($indice !== false) {
                    if (isset($propias["MONGODB" . $this->postfix . "_CONN"][$indice])) {
                        $sel = $propias["MONGODB" . $this->postfix . "_CONN"][$indice];
                        $hayIndice = true;
                    }
                }
                if (!$hayIndice) {
                    //no hay un registro en redis aun o tiene un indice inexistente
                    //cree uno con el primer elemento
                    $tmp = array_keys($propias["MONGODB" . $this->postfix . "_CONN"]);
                    $primerKey = array_shift($tmp);
                    $redis->query(
                            "hMSet", "selectorBases:MONGODB:" . $this->postfix, ["indice" => $primerKey, "descripcion" => "Registro creado automáticamente"]
                    );
                    $sel = $propias["MONGODB" . $this->postfix . "_CONN"][$primerKey];
                }
                $this->seleccion = [
                    "HOST" => $sel["HOST"],
                    "USERNAME" => $sel["USERNAME"],
                    "PWD" => $sel["PWD"],
                    "DATABASE" => $sel["DATABASE"],
                ];
            } else {
                $this->seleccion = [
                    "HOST" => $propias["MONGODB" . $this->postfix . "_HOST"],
                    "USERNAME" => $propias["MONGODB" . $this->postfix . "_USERNAME"],
                    "PWD" => $propias["MONGODB" . $this->postfix . "_PWD"],
                    "DATABASE" => $propias["MONGODB" . $this->postfix . "_DATABASE"],
                ];
            }
            $this->targetHost = strtolower($this->seleccion["HOST"]);
            $this->targetPort = ini_get("mongo.default_port");
            if (strpos($this->targetHost, ":") !== false) {
                //different port
                $this->targetHost = substr($this->targetHost, 0, strpos($this->targetHost, ":"));
                $this->targetPort = substr(strtolower($this->seleccion["HOST"]), strpos(strtolower($this->seleccion["HOST"]), ":") + 1);
            }
            $laClave = $this->seleccion["PWD"];
            if (ctype_xdigit(substr($laClave, 2))) {
                $pass = strrev("MONGODB" . $this->postfix . "_HOST") . strrev("MONGODB" . $this->postfix . "_USERNAME") . strrev("MONGODB" . $this->postfix . "_DATABASE");
                $pass = str_pad(substr($pass . strtoupper($pass) . $pass . strtolower($pass) . $pass, 0, 32), 32, ".");
                $laClave = decriptCompress(hexToString($laClave), goodKey($pass));
            }
            $this->my = new MongoClient(
                    "mongodb://" . $this->seleccion["USERNAME"] . ":" . $laClave . "@" . $this->targetHost . ":" . $this->targetPort . "/" . $this->seleccion["DATABASE"], array("socketTimeoutMS" => "1200000"));
            $this->myDB = $this->my->selectDB($this->seleccion["DATABASE"]);
        }

        public function traerColecciones() {
            //__Descripcion__: Obtiene todas las colecciones de esta base mongo
            //__Input__: null
            //__Output__: array con las colecciones
            return $this->myDB->listCollections();
        }

        public function String2MongoId($id) {
            //__Descripcion__: Convierte string a id de mongo
            //__Input__: string $id
            //__Output__: objeto id mongo

            return new MongoId($id);
        }

        public function crearColeccion($nombre, $opciones = array("capped" => false, "size" => 0, "max" => 0, "autoIndexId" => true)) {
            //__Descripcion__: crea una nueva coleccion en la base de datos, esta funcion solo debe llamarse cuando se crear colecciones con opciones especiales, pues caso contrario la coleccion se crea automaticamente. NOTA_IMPORTANTE: una coleccion con la opcion capped NO permite operaciones de borrado!
            //__Input__: string $nombre de la coleccion, array $opciones a utilizar
            //__Output__: boolean exito o fracaso
            if (expect_scalar($nombre)) {
                $col = $this->myDB->createCollection($nombre, $opciones);
            }
            return $col;
        }

        public function guardarbatch($coleccion, $documentos) {
            //__Descripcion__: insertar o guardar un nuevo documento en la coleccion
            //__Input__: string $coleccion nombre de la colección para insertar, $documentos array de todos los ducmento que se desea guardar;
            //__Output__: id del documento insertado, o 0 en caso de error
            //debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //pase a utf-8
            $documentos = array_encode_any($documentos);
            //si nos enviaron un id o _id, entonces se hace update, caso contrario se hace insert
            if (isset($documentos["id"]) && !isset($documentos["_id"])) {
                $documentos["_id"] = new MongoId($documentos["id"]);
            }
            $r = $col->batchInsert($documentos, array('continueOnError' => true));
            if ($r["ok"] == 1) {
                return true;
            } else {
                return false;
            }
        }

        public function crearIndice($coleccion, $columnas, $opciones = array("unique" => false, "sparse" => false)) {
            //__Descripcion__: crea un indice de la $coleccion si no existe ya previamente
            //__Input__: string $coleccion nombre de la tabla, array $columnas con una lista simple de nombres de campo o con una lista de nombres de campo como keys y 1 o -1 como valor para indicar un índice ascendente o descendente, array $opciones (opcional) un array de opciones a enviar a mongo para la creacion del indice
            //__Output__: boolean exito o fracaso
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            if ($col && is_array($columnas) && count($columnas) > 0) {
                //enviaron una lista de nombres simple? aniada orden ascendente
                if (array_keys($columnas) === range(0, count($columnas) - 1)) {
                    $newColumnas = array();
                    foreach ($columnas as $ob) {
                        $newColumnas[$ob] = 1; //indice ascendente
                    }
                } else {
                    //caso contrario utilice el array que nos enviaron
                    $newColumnas = $columnas;
                }
                if (!is_array($newColumnas)) {
                    return false;
                }
                return $col->createIndex($newColumnas, $opciones);
            }
            return false;
        }

        public function guardar($coleccion, $data, $path = array(), $size = 0) {
            //__Descripcion__: insertar o guardar un nuevo documento en la coleccion
            //__Input__: string $coleccion nombre de la colección para insertar, $data array de datos para insertar
            //__Output__: id del documento insertado, o 0 en caso de error
            //debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //pase a utf-8
            $data = array_encode_any($data);
            //si nos enviaron un id o _id, entonces se hace update, caso contrario se hace insert
            if (isset($data["id"]) && !isset($data["_id"])) {
                $data["_id"] = new MongoId($data["id"]);
            }

            if (count($path) > 0) {
                //            if ($size <= 16777216) {
                ////                print_h("tengo un tamaño menor de 16 megas");
                ////                exit;
                //                $file =array("file" => new MongoBinData(file_get_contents($path)));
                //                $data = array_merge($data,$file);   
                //            }else{
                //                print_h("tengo un tamaño mayor de 16 megas");
                //                exit;
                // GridFS
                $gridFS = $this->myDB->getGridFS();
                // Note metadata field & filename field
                $storedfile = $gridFS->storeFile($path, array("metadata" => $data));
                return (string) $storedfile;
                //                   }
            }
            $col->save($data);
            if (isset($data["_id"])) {
                return (string) $data["_id"];
            } else {
                return false;
            }
        }

        public function actualizar($coleccion, $criterio, $nuevaData, $soloUnRegistro = false) {
            //__Descripcion__: actualizar uno o mas documentos ya existentes en una coleccion
            //__Input__: string $coleccion nombre de la colección para actualizar, array $criterio condiciones para aplicar la actualizacion, $nuevaData array de datos para actualizar, $soloUnRegistro opcional, si quiero que se actualice sólo un registro de los que cumplen el criterio
            //__Output__: número de registros actualizados
            // --!-- debería verificar la estructura de la colección, o al menos que la colección exista.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            $opciones = array("multiple" => !$soloUnRegistro);
            $nuevaData = array_encode_any($nuevaData);

            if (!array_key_exists('$set', $nuevaData)) {
                $nuevaData = ['$set' => $nuevaData];
            }

            $res = $col->update($criterio, $nuevaData, $opciones);
            return $res["nModified"];
        }

        public function borrar($coleccion, $criterio, $soloUnRegistro = false, $gridFS = false) {
            //__Descripcion__: borrar un documento de una colección
            //__Input__: string $coleccion nombre de la colección, $criterio condición para la eliminación, $soloUnRegistro opcional indica si se eliminan todos los registros que cumplen el criterio o sólo uno, por defecto se borrarán todos.
            //__Output__: true si se ejecutó con éxito, false caso contrario.
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $opciones = array("justOne" => $soloUnRegistro);
            $res = array();
            switch ($gridFS) {
                case true:
                    // GridFS
                    $gridFS = $this->myDB->getGridFS();
                    // Find image to stream

                    $res = $gridFS->remove(array('_id' => $criterio), $opciones);

                    break;

                default:


                    $col = $this->myDB->selectCollection($coleccion);
                    $criterio = array_encode_any($criterio);
                    $res = $this->myDB->$coleccion->remove($criterio, $opciones);

                    break;
            }
            if ($res["ok"]) {
                return $res["n"];
            } else {
                trigger_error("MYMONGODB->borrar: " . $res["errmsg"], E_USER_NOTICE);
                return false;
            }
        }

        public function siguiente() {
            //__Descripcion__: permite avanzar en el cursor del objeto instanciado
            //__Input__: null
            //__Output__: documento siguiente o false

            if ($this->cursor == false)
                return false;
            //es un cursor normal o un commandCursor?
            $tipoDeCursor = get_class($this->cursor);

            switch ($tipoDeCursor) {
                case "MongoCursor":
                    $doc = $this->cursor->getNext();
                    break;
                case "MongoCommandCursor":
                    $doc = $this->cursor->current();
                    $this->cursor->next();
                    break;
            }

            if ($doc) {
                //ahora añadamos el id como string
                if (isset($doc["_id"]) && !is_array($doc["_id"])) {
                    $doc["id"] = (string) $doc["_id"];
                }
                $doc = array_encode_any($doc, $reverse = true);
                return $doc;
            } else {
                return false;
            }
        }

        public function siguientex() {
            //__Descripcion__: permite avanzar en el cursor del objeto instanciado
            //__Input__: null
            //__Output__: documento siguiente o false

            if ($this->cursor == false)
                return false;
            //es un cursor normal o un commandCursor?
            $tipoDeCursor = get_class($this->cursor);

            switch ($tipoDeCursor) {
                case "MongoCursor":
                    $doc = $this->cursor->getNext();
                    break;
                case "MongoCommandCursor":
                    $doc = $this->cursor->current();
                    $this->cursor->next();
                    break;
            }

            if ($doc) {
                return $doc;
            } else {
                return false;
            }
        }

        public function buscarGroupBy($coleccion, $keys, $initial, $reduce, $condition = array()) {
            //__Descripcion__: buscar los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $coleccion nombre de la colección, string $criterios condiciones para la busqueda
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            if (!is_array($condition) || count($condition) == 0) {
                $respuesta = $col->group($keys, $initial, $reduce);
            } else {
                $respuesta = $col->group($keys, $initial, $reduce, $condition);
            }
            return $respuesta;
        }

        public function aggregate($coleccion, $pipelines) {
            //__Descripcion__: Realiza una acumulación usando el framework de acumulación
            //__Input__: string $coleccion tabla a operar, array $pipelines array de operadores de pipeline. 
            //__Output__: array de respuesta
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //si no envian $pipelines se trata de un error
            $pipelines = array_encode_any($pipelines);
            if (is_array($pipelines) && count($pipelines) > 0) {
                $respuesta = $col->aggregate($pipelines);
                return $respuesta;
            } else {
                trigger_error("aggregate() no puede ejecutarse sin pipelines", E_USER_ERROR);
            }
        }

        public function agregar($coleccion, $pipelines, $criterios = [], $orderBy = [], $limit = 0, $skip = 0) {
            //__Descripcion__: Realiza una acumulación usando el framework de acumulación
            //__Input__: string $coleccion la coleccion, array $options array de operadores para el aggregate 
            //__Output__: CommandCursor el CommandCursor resultante
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //si no envian $criterios, devuelve todo
            //$pipelines = array_encode_any($pipelines);
            if (is_array($criterios) && count($criterios) > 0) {
                //$criterios = array_encode_any($criterios);
                $pipelines[] = ['$match' => $criterios];
            }
            if (is_array($orderBy) && count($orderBy) > 0) {
                //$criterios = array_encode_any($orderBy);
                $pipelines[] = ['$sort' => $orderBy];
            }
            if ($skip > 0) {
                $pipelines[] = ['$skip' => $skip];
            }
            if ($limit > 0) {
                $pipelines[] = ['$limit' => $limit];
                $opcionesAdicionales = [
                    "cursor" => ["batchSize" => $limit - 1],
                    "allowDiskUse" => true,
                ];
            } else {
                $opcionesAdicionales = [
                    "allowDiskUse" => true,
                ];
            }
            $cursor = $col->aggregateCursor($pipelines, $opcionesAdicionales);

            $this->cursor = $cursor;
            $this->cursor->rewind();
            return $this->cursor;
        }

        public function buscar($coleccion, $criterios = array(), $campos = array(), $orderBy = array(), $limit = 0, $skip = 0) {
            //__Descripcion__: buscar los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $coleccion nombre de la colección, array $criterios condiciones para la busqueda, array $campos campos a devolver, array $orderBy campos por los que se quiere ordenar los resultados, int $limit cuantos registros devolver, int $skip en que registro empezar 
            //__Output__: cursor de MongoDB
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            //si no envian $criterios, devuelve todo
            //        $criterios = array_encode_any($criterios);
            $criterios = $criterios;
            //        print_h("valor dentro de la clase");
            //        print_h($criterios);
            $cursor = $col->find($criterios);

            //si no envian $campos devuelve todos
            if (is_array($campos) && count($campos) > 0) {
                if (array_keys($campos) === range(0, count($campos) - 1)) {
                    //enviaron una lista de nombres simple
                    $newCampos = array();
                    foreach ($campos as $ob) {
                        $newCampos[$ob] = true;
                    }
                    $cursor->fields($newCampos);
                } else {
                    //enviaron una lista que ya tiene el formato Mongo
                    $cursor->fields($campos);
                }
            }

            if (is_array($orderBy) && count($orderBy) > 0) {
                if (array_keys($orderBy) === range(0, count($orderBy) - 1)) {
                    //enviaron una lista de nombres simple
                    $newOrderBy = array();
                    foreach ($orderBy as $ob) {
                        $newOrderBy[$ob] = 1;
                    }
                    $cursor->sort($newOrderBy);
                } else {
                    //enviaron una lista que ya tiene el formato Mongo
                    $cursor->sort($orderBy);
                }
            }
            if ($skip > 0) {
                $cursor->skip($skip);
            }
            if ($limit > 0) {
                $cursor->limit($limit);
            }
            $this->cursor = $cursor;
            return $this->cursor->count($foundOnly = true);
        }

        public function buscarDistinct($field, $coleccion, $criterios = array()) {
            //__Descripcion__: distinct de los documentos de una coleccion que satisfacen los criterios de busqueda
            //__Input__: string $field campo que se le aplica distinct, string $coleccion nombre de la colección, array $criterios condiciones para la busqueda 
            //__Output__: cursor de MongoDB
            if (!expect_scalar($coleccion)) {
                return false;
            }
            $col = $this->myDB->selectCollection($coleccion);
            $criterios = $criterios;
            $cursor = $col->distinct($field, $criterios);
            $this->cursor = $cursor;
            return $this->cursor;
        }

        public function obtieneBytes($coleccion, $id) {
            //__Descripcion__: trae el contenido de un gridFS
            //__Input__: string $coleccion nombre de la colección, string $id identificador único del documeto en la colección
            //__Output__: devuelve el contenido binario del archivo
            $resultado = $this->buscarPorId($coleccion, $id, true);
            $base64string = $resultado->getBytes();
            return $base64string;
        }

        public function buscarPorId($coleccion, $id, $useGridFS = false) {
            //__Descripcion__: trae un documento de una coleccion, dado un id
            //__Input__: string $coleccion nombre de la colección, string $id identificador único del documeto en la colección
            //__Output__: array con la información del documento
            switch ($useGridFS) {
                case true:
                    // GridFS
                    $gridFS = $this->myDB->getGridFS();
                    // Find image to stream 
                    $doc = $gridFS->findOne(array("_id" => new MongoId($id)));
                    break;

                default:
                    if (!expect_scalar($coleccion)) {
                        return false;
                    }
                    $col = $this->myDB->selectCollection($coleccion);
                    $doc = $col->findOne(array("_id" => new MongoId($id)));
                    if ($doc) {
                        $doc["id"] = (string) $doc["_id"];
                    }
                    break;
            }
            return $doc;
        }

    }

}
?><? //_FIN_DE_ARCHIVO ?>