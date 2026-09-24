<?php
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once("../cobranza/classes/class.cobCartera.php");
require_once("../cobranza/classes/class.cobCarteraRamas.php");
//if (($Central->conPermiso("Cobranza,Administrador"))||($Central->conPermiso("Cobranza,Aprobador"))
//                ||($Central->conPermiso("Cobranza,Cobranzas RED Supervisor"))) { 
//    exit;
//}
if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();


$mongo = new MYMONGODB();
$mongo1 = new MYMONGODB();



switch ($act) {

    case "obtenervaloreschatbot":
        //<editor-fold defaultstate="collapsed" desc="Carga valores de chatbot o registros principales">
        $d = jsonStart();

        $chats = [];
        $originalChatPreguntas = [];

        $chatbot = expect_safe_html($d["chatbot"]);
        $pregunta = expect_safe_html($d["pregunta"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbot["id"])));

        while ($resultado = $mongo->siguiente()) {
            $chats[] = $resultado;
        }
        $respPreg = 0;

        foreach ($chats as $value) {
            $mongo2 = new MYMONGODB();
            $originalChatPreguntas = $value["chatPreguntas"];

            $respPreg = $mongo2->actualizar('chatbot', array('_id' => $value["_id"]), ['chatPreguntas' => $originalChatPreguntas], true);
        }

//         print_h($originalChatPreguntas);
//            exit();
        $json["mensajeChatbot"] = $originalChatPreguntas;  //$nuevo
        //</editor-fold>
        break;
    case "obtieneVariables":
        //<editor-fold defaultstate="collapsed" desc="Carga todos los chatbot o registro principal"> 
        $coleccion = "chatbot";
        $d = jsonStart();
        $condition = array();
        $campos = array(
        );
        $limpiar = ["var_" => "var_"];
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos(
                $coleccion
                , $condition
                , $campos
                , ["chatEstado" => -1]
        );
        $json = $ngTabula->responde();
        for ($i = 0; $i < count($json['filas']); $i++) {
            $sortArray = array();
            $t = $json['filas'][$i]['chatPreguntas'];
            $orderby = "pregunta"; //change this to whatever key you want from the array 
            $t = orderMultiDimensionalArray($t, $orderby);
            $json['filas'][$i]['chatPreguntas'] = $t;
        }
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true; //$Central->conPermiso("Módulos de Sistema,Administrador");
        $json["extra"]["config"] = true; //$Central->conPermiso("Módulos de Sistema,Administrador") || $Central->conPermiso("Módulos de Sistema,Editor de configuraciones");
        $json["resultado"] = "";
        //</editor-fold>
        break;

    case "traerCarteras":
        //<editor-fold defaultstate="collapsed" desc="Carga todas las carteras"> 
        eval('$db=new ' . DB1 . 'DB();');
        $sentencia = "SELECT * FROM cobcartera";
        $carteras = [];
        if ($db->query($sentencia)) {
            while ($rowdb = $db->fetchRow()) {
                $carteras[] = ["cobCartera_nombre" => $rowdb["cobCartera_nombre"], "cobCartera_id" => $rowdb["cobCartera_id"]];
            }
        }
        $json["resultado"] = $carteras;
        //</editor-fold>
        break;
    case "getIDentrenarChatbot":
        //<editor-fold defaultstate="collapsed" desc="get chatbot registro principal"> 
        $d = jsonStart();
        $mongo->buscar('chatbot', ['_id' => $mongo->String2MongoId($d['id'])]);
        $row = $mongo->siguiente();
        $json[] = $row;
        //</editor-fold>
        break;
    case "guardaChatbot":
        //<editor-fold defaultstate="collapsed" desc="Guarda chatbot o registro principal"> 
        $d = jsonStart();
        $des = '';
        $tr["chatNombre"] = expect_safe_html($d["chatNombre"]);
        $tr["chatCartera"] = expect_safe_html($d["chatCartera"]);
//      $tr["chatCarteraId"] = expect_safe_html($d["chatCarteraId"]);
        $tr["chatCampania"] = expect_safe_html($d["chatCampania"]);
        $tr["chatCanal"] = expect_safe_html($d["chatCanal"]);
        $tr["chatVersion"] = expect_safe_html($d["chatVersion"]);
        $tr["chatFecha"] = (int) intval(time());
        $tr["chatDescripcion"] = expect_safe_html($d["chatDescripcion"]);
        $tr["chatEstado"] = (int) expect_integer($d["chatEstado"]);

        $collecion = "chatbot";
        //inserto configuración
        $mongo = new MYMONGODB();
        $mongoID = $mongo->String2MongoId($d["id"]);
        $criterioAccion = array('_id' => $mongoID);
        $r = $mongo->buscar($collecion, $criterioAccion);

        if ($r > 0) {
            $t = $mongo->actualizar($collecion, $criterioAccion, $tr, true);
        } else {
            $t = $mongo->guardar($collecion, $tr);
        }

        $json["resultado"] = $d;
        //</editor-fold>
        break;
    case "borraChatbot":
        //<editor-fold defaultstate="collapsed" desc="Borra chatbot o registro principal"> 
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $collecion = "chatbot";
        $mongoID = $mongo->String2MongoId($id);

        $criterioAccion = array('_id' => $mongoID, '$isolated' => 1);
        $r = $mongo->borrar($collecion, $criterioAccion, true);
        $json["resultado"] = $r;
        //</editor-fold>
        break;
    case "guardapreg":
        //<editor-fold defaultstate="collapsed" desc="Actualiza pregunta"> 
        $d = jsonStart();

        $chats = [];
        $originalChatPreguntas = [];

        $chatbot = expect_safe_html($d["chatbot"]);
        $pregunta = ucwords(strtolower(expect_safe_html($d["pregunta"])));

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbot["id"])));
        $chats = $mongo->siguiente();
        $respPreg = 0;

        $mongo2 = new MYMONGODB();
        $originalChatPreguntas = $chats["chatPreguntas"];
        $orderby = "pregunta";
        $originalChatPreguntas = orderMultiDimensionalArray($originalChatPreguntas, $orderby);
        $nuevo = [
            "id" => time(),
            "pregunta" => $pregunta,
            "sinonimos" => array(),
            "respuestas" => [],
            "respuestasJson" => []
        ];
        $originalChatPreguntas[] = $nuevo;
        $respPreg = $mongo2->actualizar('chatbot', ['_id' => $chats["_id"]], ['chatPreguntas' => $originalChatPreguntas]);

        if ($respPreg == 1) {
            $originalChatPreguntas = orderMultiDimensionalArray($originalChatPreguntas, $orderby);
            $json["mensajePreg"] = $originalChatPreguntas;  //$nuevo
        } else {
            $json["mensajePreg"] = array();
        }

        //</editor-fold>
        break;
    case "borrapreg":
        //<editor-fold defaultstate="collapsed" desc="Borra pregunta">
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbotId"]);
        $preguntaId = expect_safe_html($d["preguntaId"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        while ($resultado = $mongo->siguiente()) {
            $chats[] = $resultado;
        }
        $respPreg = 0;
        foreach ($chats as $value) {
            $mongo2 = new MYMONGODB();
            $originalChatPreguntas = $value["chatPreguntas"];
            foreach ($originalChatPreguntas as $k => $v) {
                if ($v["id"] == $preguntaId) {
                    unset($originalChatPreguntas[$k]);
                }
                $originalChatPreguntas = array_values($originalChatPreguntas);
            }

            $respPreg = $mongo2->actualizar('chatbot', array('_id' => $value["_id"]), array('chatPreguntas' => $originalChatPreguntas), true);
        }

        $json["mensajePreg"] = $respPreg;
        //</editor-fold>
        break;
    case "enviarEntrenar":
        //<editor-fold defaultstate="collapsed" desc="Envia a entrenar preguntas y respuestas al chatbot"> 
        $d = jsonStart();
        $mongo->buscar('chatbotConfig', []);
        $rowURL = $mongo->siguiente();
        $mongo->buscar('chatbot', ['_id' => $mongo->String2MongoId($d['id'])]);
        $row = $mongo->siguiente();
        if (!isset($row['coleccionEntrenar'])) {
            $resultado = 'No esta definida la url para entrenar';
        } else {
            $resp = [];
            $mongo->borrar($row['coleccionEntrenar'], []);
            foreach ($row['chatPreguntas'] as $val) {
                if (isset($val['respuestasJson']) && count($val['respuestasJson']) > 0) {
                    foreach ($val['respuestasJson'] as $respJson) {
                        $resp[] = ['pregunta' => $val['pregunta'], 'respuesta' => json_encode(clean_all($respJson))];
                        if (count($val['sinonimos']) > 0) {
                            foreach ($val['sinonimos'] as $val2) {
                                $resp[] = ['pregunta' => $val2, 'respuesta' => json_encode(clean_all($respJson))];
                            }
                        }
                    }
                }
            }

            array_unshift($resp, ['reg' => count($resp), "dataOrig" => $row]);
            foreach ($resp as $val) {
                $mongo->guardar($row['coleccionEntrenar'], $val);
            }

            $url = $rowURL['urlChatbot'] . $row['urlEntrenar'];

            $headers = array(
                "Content-Type: application/json",
                "Accept: application/json",
                "Content-length: 0"
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $resp = curl_exec($ch);
            curl_close($ch);
            $resultado = json_decode($resp, true);
//            $resultado = json_decode(file_get_contents($url));
            $res = 'error';
            if ($resultado['contenido']['texto'] == "Chatbot entrenando") {
//            if ($resultado->contenido->texto == "Chatbot entrenando") {
                $url = $rowURL['urlChatbot'] . $row['urlEntrenado'];

                $a = 0;
                while ($a != 60) {
                    sleep(10);
                    $a += 10;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $resp = curl_exec($ch);
                    curl_close($ch);
//                    $resultado = json_decode(file_get_contents($url));
                    trigger_error('entrenar ' . $resultado['contenido']['texto']);
//                    trigger_error('entrenar ' . $resultado->contenido->texto);
                    if (intval($resultado['contenido']['texto']) == 0) {
//                    if (intval($resultado->contenido->texto) == 0) {
                        $res = 'ok';
                        $a = 60;
                    }
                }
            }
        }

        $json['respuesta'] = $res;
        //</editor-fold>
        break;
    case "guardaresp":
        //<editor-fold defaultstate="collapsed" desc="Guarda respuesta">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbot"]);
        $respuestasId = expect_safe_html($d["respuestasId"]);
        $respuestaJson = expect_safe_html($d["respuestaJson"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $value = $mongo->siguiente();

        $originalChatPreguntas = $value["chatPreguntas"];

        foreach ($originalChatPreguntas as $k => $v) {
            if (intval($v["id"]) == intval($respuestasId)) {
                $nuevosValores = $v;
                $nuevosValores["respuestas"][] = $respuestaJson;
                $nuevosValores["respuestasJson"][] = $respuestaJson;
                $originalChatPreguntas[$k] = $nuevosValores;
            }
        }
        $respResp = $mongo->actualizar('chatbot', ['_id' => $value["_id"]], ['chatPreguntas' => $originalChatPreguntas]);

        $mongo->buscar("chatbot", ["_id" => $value["_id"]]);
        $chats = $mongo->siguiente();

        $json["mensajeResp"] = $respResp;
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "actpregunta":
        //<editor-fold defaultstate="collapsed" desc="Actualiza pregunta">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbot"]);
        $data = expect_safe_html($d["data"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $value = $mongo->siguiente();

        $originalChatPreguntas = $value["chatPreguntas"];
        $orderby = "pregunta";
        foreach ($originalChatPreguntas as $k => $v) {
            if (intval($v["id"]) == intval($data['id'])) {
                $nuevosValores = $v;
                $nuevosValores["pregunta"] = $data['input'];
                $originalChatPreguntas[$k] = $nuevosValores;
            }
        }
        $originalChatPreguntas = orderMultiDimensionalArray($originalChatPreguntas, $orderby);
        $respResp = $mongo->actualizar('chatbot', ['_id' => $value["_id"]], ['chatPreguntas' => $originalChatPreguntas]);

        $mongo->buscar("chatbot", ["_id" => $value["_id"]]);
        $chats = $mongo->siguiente();

        $json["mensajeResp"] = $respResp;
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "filtrarPreguntas":
        //<editor-fold defaultstate="collapsed" desc="filtrar preguntas en tabula">  
        $d = jsonStart();
        $chatbotId = $_REQUEST["id"];
        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));
        $chats = $mongo->siguiente();

        $new = [];
        $hayFiltro = 0;
        foreach ($d['filtro'] as $fil) {
            if ($fil['campo'] == 'pregunta') {
                $filtro = strtolower(trim($fil['filtro']));
                if ($filtro != '') {
                    $hayFiltro = 1;
                    foreach ($chats['chatPreguntas'] as $ch) {
                        if (strpos(strtolower($ch['pregunta']), $filtro) !== false) {
                            $new[] = $ch;
                        }
                    }
                }
            }
            if ($fil['campo'] == 'sinonimo') {
                $filtro = strtolower(trim($fil['filtro']));
                if ($filtro != '') {
                    $da = $chats['chatPreguntas'];
                    if ($hayFiltro == 1) {
                        $da = $new;
                        $new = [];
                    }
                    foreach ($da as $ch) {
                        foreach ($ch['sinonimos'] as $si) {
                            if (strpos(strtolower($si), $filtro) !== false) {
                                $new[] = $ch;
                            }
                        }
                    }
                    $hayFiltro = 1;
                }
            }
        }
        if ($hayFiltro == 0) {
            $new = $chats['chatPreguntas'];
        }
        $json['filas'] = $new;
        //</editor-fold>
        break;
    case "actsinonimo":
        //<editor-fold defaultstate="collapsed" desc="Actualiza sinonimos">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbot"]);
        $sinonimos = expect_safe_html($d["sinonimos"]);
        $respuestasId = expect_safe_html($d["respuestasId"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $value = $mongo->siguiente();
        $originalChatPreguntas = $value["chatPreguntas"];

        foreach ($originalChatPreguntas as $k => $v) {
            if (intval($v["id"]) == intval($respuestasId)) {
                $nuevosValores = $v;
                $nuevosValores["sinonimos"] = $sinonimos;
                $originalChatPreguntas[$k] = $nuevosValores;
            }
        }
        $respResp = $mongo->actualizar('chatbot', ['_id' => $value["_id"]], ['chatPreguntas' => $originalChatPreguntas]);
        $mongo->buscar("chatbot", ["_id" => $value["_id"]]);
        $chats = $mongo->siguiente();
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "actresp":
        //<editor-fold defaultstate="collapsed" desc="Actualiza respuesta">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbot"]);
        $respuestasId = expect_safe_html($d["respuestasId"]);
        $respuestaJson = expect_safe_html($d["respuestaJson"]);
        $item = expect_safe_html($d["item"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $value = $mongo->siguiente();

        $originalChatPreguntas = $value["chatPreguntas"];

        foreach ($originalChatPreguntas as $k => $v) {
            if (intval($v["id"]) == intval($respuestasId)) {
                $nuevosValores = $v;
                $nuevosValores["respuestas"][$item] = $respuestaJson;
                $nuevosValores["respuestasJson"][$item] = $respuestaJson;
                $originalChatPreguntas[$k] = $nuevosValores;
            }
        }
        $respResp = $mongo->actualizar('chatbot', ['_id' => $value["_id"]], ['chatPreguntas' => $originalChatPreguntas]);

        $mongo->buscar("chatbot", ["_id" => $value["_id"]]);
        $chats = $mongo->siguiente();

        $json["mensajeResp"] = $respResp;
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "borraresp":
        //<editor-fold defaultstate="collapsed" desc="Borra respuesta">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbotId"]);
        $respuestasId = expect_safe_html($d["respuestasId"]);
        $respuestasIndice = expect_safe_html($d["respuestasIndice"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $chats = $mongo->siguiente();

        $respResp = 0;
//        foreach ($chats as $value) {
        $mongo2 = new MYMONGODB();

        $originalChatPreguntas = $chats["chatPreguntas"];
        $dev = [];
        foreach ($originalChatPreguntas as $k => $v) {

            if ($v["id"] == $respuestasId) {
//                    $nuevosValores = $v;
//                    $originalChatPreguntas[$k] = $nuevosValores;
                unset($originalChatPreguntas[$k]["respuestas"][$respuestasIndice]);
                unset($originalChatPreguntas[$k]["respuestasJson"][$respuestasIndice]);
                $dev = $originalChatPreguntas[$k];
//                     $originalChatPreguntas[$k]["respuestas"];
//                     print_h($originalChatPreguntas[$k]["respuestas"][$respuestasIndice]);
//                      exit();
            }

            //Reinicia el id
            $originalChatPreguntas[$k]["respuestas"] = array_values($originalChatPreguntas[$k]["respuestas"]);
            $originalChatPreguntas[$k]["respuestasJson"] = array_values($originalChatPreguntas[$k]["respuestasJson"]);
        }

        $respResp = $mongo2->actualizar('chatbot', array('_id' => $chats["_id"]), array('chatPreguntas' => $originalChatPreguntas), true);

        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));
        $chats = $mongo->siguiente();
//            }

        $json["mensajeResp"] = $respResp;
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "reentrenarPregunta":
        //<editor-fold defaultstate="collapsed" desc="registra pregunta de reentrenamientocomo pregunta">  
        $d = jsonStart();
        $mongo->buscar('chatbot', ['_id' => $mongo->String2MongoId($d['id'])]);
        $row = $mongo->siguiente();
        $pre = $row['chatPreguntas'];
        $enco = 0;
        foreach ($pre as $val) {
            if ($val['pregunta'] == $d['data']['declaracion-user']) {
                $enco = 1;
            }
        }
        $time = time();
        if ($enco == 0) {
            $pre[] = [
                "id" => (int) $time,
                "pregunta" => $d['data']['declaracion-user'],
                "sinonimos" => [],
                "respuestas" => [],
                "respuestasJson" => [],
                'reentrenadoTime' => (int) $time,
                'reentrenadoUser' => (int) $_SESSION[MID . "userId"]
            ];
            $pre = orderMultiDimensionalArray($pre, 'pregunta');
            $mongo->actualizar('chatbot', ['_id' => $mongo->String2MongoId($d['id'])], ['chatPreguntas' => $pre]);
        }
        $mongo->actualizar($d['coleccion'], ['declaracion-user' => $d['data']['declaracion-user']], ['reentrenado' => 'reentrenado', 'reentrenadoTime' => (int) $time, 'reentrenadoUser' => (int) $_SESSION[MID . "userId"]]);
        //</editor-fold>
        break;
    case "borrarReentrenamiento":
        //<editor-fold defaultstate="collapsed" desc="Oculta las preguntas de reentrenamiento">  
        $d = jsonStart();
        $mongo->actualizar($d['coleccion'], ['declaracion-user' => $d['data']['declaracion-user']], ['reentrenado' => 'borrado', 'reentrenadoTime' => (int) time(), 'reentrenadoUser' => (int) $_SESSION[MID . "userId"]]);
        //</editor-fold>
        break;
    case "registrarSinonimoPregunta":
        //<editor-fold defaultstate="collapsed" desc="Registra preguntas como sinonimo en reentrenamiento">
        $d = jsonStart();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($d['id'])));
        $chats = $mongo->siguiente();
        $originalChatPreguntas = $chats["chatPreguntas"];

        foreach ($originalChatPreguntas as $k => $v) {
            if (intval($v["id"]) == intval($d['pregunta']['id'])) {
                $nuevosValores = $v;
                $nuevosValores["sinonimos"][] = $d['sinonimo'];
                $originalChatPreguntas[$k] = $nuevosValores;
            }
        }
        $respResp = $mongo->actualizar('chatbot', ['_id' => $chats["_id"]], ['chatPreguntas' => $originalChatPreguntas]);
        $mongo->actualizar($d['coleccion'], ['declaracion-user' => $d['sinonimo']], ['reentrenado' => 'reentrenado', 'reentrenadoTime' => (int) time(), 'reentrenadoUser' => (int) $_SESSION[MID . "userId"]]);
        //</editor-fold>
        break;
    case "getPreguntasSinonimos":
        //<editor-fold defaultstate="collapsed" desc="Carga preguntas para asignar un sinonimo en reentrenamiento"> 
        require_once "../comunes/classes/class.coCompleteMongo.php";
        $d = jsonStart();
        $limpiar = array();
        $autocom = new coCompleteMongo();
        $autocom->setInput($d);
        $autocom->setQueryDatos("chatbot", ['_id' => $mongo->String2MongoId($_REQUEST["id"])], ['chatPreguntas.pregunta'], []
        );
        $json = $autocom->responde();
        //</editor-fold>
        break;
    case "getReentrenar":
        //<editor-fold defaultstate="collapsed" desc="Carga datos para reentrenar o preguntas sin respuestas">  
        $d = jsonStart();
        $mongo->buscar($_REQUEST["coleccion"], ['reentrenado' => ['$exists' => false]]);
        while ($row = $mongo->siguiente()) {
            if (is_numeric($row['declaracion-user'])) {
                $mongo1->actualizar($_REQUEST["coleccion"], ['_id' => $row['_id']], ['reentrenado' => 'descartado', 'reentrenadoTime' => (int) time()]);
            } else {
                if (strlen($row['declaracion-user']) < 3) {
                    if (strtolower($row['declaracion-user']) != 'ok') {
                        $mongo1->actualizar($_REQUEST["coleccion"], ['_id' => $row['_id']], ['reentrenado' => 'descartado', 'reentrenadoTime' => (int) time()]);
                    }
                }
            }
        }
        $campos = [];
        $cond1['$or'] = [
            ["declaracion-bot" => ['$regex' => utf8_encode('Disculpa, no entiendo lo que dices. ¿Puedes repetirlo o reformularlo?')]],
            ["declaracion-bot" => ['$regex' => utf8_encode('Perdón, no te entendí. ¿Puedes reformular tu declaración?')]],
            ["declaracion-bot" => ['$regex' => utf8_encode('Perdón, no comprendí lo que dijiste. ¿Puedes reformularlo, por favor?')]],
            ["declaracion-bot" => ['$regex' => utf8_encode('Has escrito algo que no puedo comprender, ¿Puedes intentar de otra manera?')]],
        ];
        $cond2 = ["reentrenado" => ['$exists' => false]];
        $condi['$and'] = [$cond1, $cond2];
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos(
                $_REQUEST["coleccion"]
                , $condi
                , $campos
                , []
        );
        $json = $ngTabula->responde();
        //</editor-fold>
        break;
    case "guardasinonim":
        //<editor-fold defaultstate="collapsed" desc="Guarda sinonimos">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbot"]);
        $sinonimosId = expect_safe_html($d["sinonimosId"]);
        $sinonimos = expect_safe_html($d["sinonimos"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        while ($resultado = $mongo->siguiente()) {
            $chats[] = $resultado;
        }
        $respSinonim = 0;

        foreach ($chats as $value) {
            $mongo2 = new MYMONGODB();
            $originalChatPreguntas = $value["chatPreguntas"];
            foreach ($originalChatPreguntas as $k => $v) {
                if ($v["id"] == $sinonimosId) {
                    $nuevosValores = $v;
                    $nuevosValores["sinonimos"][] = $sinonimos;
                    $originalChatPreguntas[$k] = $nuevosValores;
                }
            }
        }

        $respSinonim = $mongo2->actualizar('chatbot', array('_id' => $value["_id"]), array('chatPreguntas' => $originalChatPreguntas), true);
        $mongo->buscar("chatbot", ["_id" => $value["_id"]]);
        $chats = $mongo->siguiente();
        $json["mensajeSinonim"] = $respSinonim;
        $json["data"] = $chats;
        //</editor-fold>
        break;
    case "borrasinonim":
        //<editor-fold defaultstate="collapsed" desc="Borra sinonimos">  
        $d = jsonStart();
        $chats = [];
        $chatbotId = expect_safe_html($d["chatbotId"]);
        $sinonimosId = expect_safe_html($d["sinonimosId"]);
        $sinonimosIndice = expect_safe_html($d["sinonimosIndice"]);

        $mongo = new MYMONGODB();
        $mongo->buscar("chatbot", array("_id" => $mongo->String2MongoId($chatbotId)));

        $chats = $mongo->siguiente();

        $originalChatPreguntas = $chats["chatPreguntas"];
        $respSinonim = 0;
        $mongo2 = new MYMONGODB();
        $dev = [];
        foreach ($originalChatPreguntas as $k => $v) {

            if ($v["id"] == $sinonimosId) {
                unset($originalChatPreguntas[$k]["sinonimos"][$sinonimosIndice]);
                $dev = $originalChatPreguntas[$k];
            }
            //Reinicia el id
            $originalChatPreguntas[$k]["sinonimos"] = array_values($originalChatPreguntas[$k]["sinonimos"]);
        }

        $respSinonim = $mongo2->actualizar('chatbot', array('_id' => $chats["_id"]), array('chatPreguntas' => $originalChatPreguntas), true);

        $mongo->buscar("chatbot", array("_id" => $chats["_id"]));
        $chats = $mongo->siguiente();

        $json["mensajeResp"] = $respResp;
        $json["data"] = $chats;
//        $json["data"] = $dev;
        //$json["mensajeSinonim"] = $originalChatPreguntas;
        //</editor-fold>
        break;
}
jsonEnd($json, $limpiar);

function clean_all($arr) {
    foreach ($arr as $key => $value) {
        if (is_array($value))
            $arr[$key] = clean_all($value);
        else {
            $arr[$key] = acento2html($value);
        }
    }
    return $arr;
}

function acento2html($string) {
    $string = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Ñ'], ['a', 'e', 'i', 'o', 'u', 'n', 'N'], $string);
//    $string = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Ñ'], ['&aacute;', '&eacute;', '&iacute;', '&oacute;', '&uacute;', '&ntilde;', '&Ntilde;'], $string);
    $string = onlyChars($string);
    return $string;
}

function onlyChars($string) {
    $strlength = strlen($string);
    $retString = "";
    for ($i = 0; $i < $strlength; $i++) {
//            print_h(ord($string[$i]).'__');
//        if (ord($string[$i]) == 209) {//supuesta Ñ la cambia por N
//            $string[$i] = chr(78);
//        }
        if ((ord($string[$i]) >= 48 && ord($string[$i]) <= 57) ||
                (ord($string[$i]) >= 65 && ord($string[$i]) <= 90) ||
                (ord($string[$i]) >= 97 && ord($string[$i]) <= 122) || ord($string[$i]) == 209 || ord($string[$i]) == 241 ||
                ord($string[$i]) == 164 || ord($string[$i]) == 165 || ord($string[$i]) == 47 || ord($string[$i]) == 64 || ord($string[$i]) == 32 ||
                ord($string[$i]) == 46 || ord($string[$i]) == 44 || ord($string[$i]) == 45 || ord($string[$i]) == 95 || ord($string[$i]) == 40 ||
                ord($string[$i]) == 41 || ord($string[$i]) == 35 || ord($string[$i]) == 34 || ord($string[$i]) == 39 || ord($string[$i]) == 33 ||
                ord($string[$i]) == 42 || ord($string[$i]) == 58 || ord($string[$i]) == 37 || ord($string[$i]) == 43 || ord($string[$i]) == 38) {
            $retString .= $string[$i];
        }
    }

    return $retString;
}

function encodeTemporal($text) {
    if ($text === null || $text == '') {
        return '';
    }
    // $text = str_replace("¦", "ñ", $text);
    $text = quickEncode($text, true);
    $isUTF8 = preg_match('//u', $text);
    if (!$isUTF8) {
        $textoLimpio = "";
        foreach (str_split($text) as $ch) {
//                print_h($this->strtolower_utf8($ch)."=="."ñ");
            if (strtolower_utf8($ch) == "ñ") {
//                    print_h("entre ñ");
                $textoLimpio .= $ch;
            } else {
//                    print_h("no entre ñ");
                if (preg_match('//u', $ch)) {
                    $textoLimpio .= $ch;
                }
            }
        }
        $text = $textoLimpio;
    }
    return $text;
}

function strtolower_utf8($cadena) {
    $convertir_a = array(
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u",
        "v", "w", "x", "y", "z", "à", "á", "â", "ã", "ä", "å", "æ", "ç", "è", "é", "ê", "ë", "?", "ì", "í", "î", "ï",
        "ð", "ñ", "ò", "ó", "ô", "õ", "ö", "ø", "ù", "ú", "û", "ü", "ý", "a", "e", "i", "o", "u", "?", "?", "?",
        "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?",
        "?", "?", "?", "?"
    );
    $convertir_de = array(
        "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U",
        "V", "W", "X", "Y", "Z", "À", "Á", "Â", "Ã", "Ä", "Å", "Æ", "Ç", "È", "É", "Ê", "Ë", "?", "Ì", "Í", "Î", "Ï",
        "Ð", "Ñ", "Ò", "Ó", "Ô", "Õ", "Ö", "Ø", "Ù", "Ú", "Û", "Ü", "Ý", "á", "é", "í", "ó", "ú", "?", "?", "?",
        "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?",
        "?", "?", "?", "?"
    );
    return str_replace($convertir_de, $convertir_a, $cadena);
}

function orderMultiDimensionalArray($toOrderArray, $field, $inverse = false) {
    $position = array();
    $newRow = array();
    foreach ($toOrderArray as $key => $row) {
        $position[$key] = $row[$field];
        $newRow[$key] = $row;
    }
    if ($inverse) {
        arsort($position);
    } else {
        asort($position);
    }
    $returnArray = array();
    foreach ($position as $key => $pos) {
        $returnArray[] = $newRow[$key];
    }
    return $returnArray;
}

?><? //_FIN_DE_ARCHIVO                                                                                                                                  ?>
