 
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
    case "obtieneVariables":
        $coleccion = "analisisSentimientos";
    
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
                , ["_id" => 1]
        );
//        $ngTabula->permiteExportar("exporta");
        $json = $ngTabula->responde();
        
    
        
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;//$Central->conPermiso("Módulos de Sistema,Administrador");
        $json["extra"]["config"] = true;//$Central->conPermiso("Módulos de Sistema,Administrador") || $Central->conPermiso("Módulos de Sistema,Editor de configuraciones");
        $json["resultado"] = "";
        break;
    
    case "guardaDef":
        $d = jsonStart();
        $des = '';
        if (isset($d["asCartera"])) {
            $des = (expect_safe_html($d["asCartera"]));
        }
        if ($d["id"] == '-1') {
            $tr["asCartera"] = $des;
            $tr["asCarteraId"] = expect_safe_html($d["asCarteraId"]);
            $tr["asCampania"] = expect_safe_html($d["asCampania"]);
            $tr["asCanal"] = expect_safe_html($d["asCanal"]);
            //$date=  substr(expect_pure_alphanumeric($d["date"]), 0,10);  
            $tr["asFechaIni"] = expect_integer($d["asFechaIni"]);
            $tr["asFechaFin"] = expect_integer($d["asFechaFin"]);
            $tr["asDescripcion"] = expect_safe_html($d["asDescripcion"]);
            $tr["asEstado"] = (int) expect_integer($d["asEstado"]);
    
           
        } else {

            $tr["asCartera"] = $des;
            $tr["asCarteraId"] = expect_safe_html($d["asCarteraId"]);
            $tr["asCampania"] = expect_safe_html($d["asCampania"]);
            $tr["asCanal"] = expect_safe_html($d["asCanal"]);
            //$date=  substr(expect_pure_alphanumeric($d["date"]), 0,10);  
            $tr["asFechaIni"] = expect_integer($d["asFechaIni"]);
            $tr["asFechaFin"] = expect_integer($d["asFechaFin"]);
            $tr["asDescripcion"] = expect_safe_html($d["asDescripcion"]);
            $tr["asEstado"] = (int) expect_integer($d["asEstado"]);
           
        }
    
    $collecion = "analisisSentimientos";
              //inserto configuración
            $mongo = new MYMONGODB();
            $mongoID = $mongo->String2MongoId($d["id"]);
            $criterioAccion = array('_id' => $mongoID);
            $r = $mongo -> buscar($collecion, $criterioAccion);
            
            if ($r > 0) {
                $t = $mongo -> actualizar($collecion, $criterioAccion, $tr, true);
            }
            else{
                
                 $t = $mongo -> guardar($collecion, $tr);
            }
 
            $json["resultado"] = $d;
        break;
 
    case "borraDef":
   
        $d = jsonStart();
//        if (!$Central->conPermiso("Módulos de Sistema,Administrador")) {
//            exit;
//        }
        $id = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $collecion = "analisisSentimientos";
        $mongoID = $mongo->String2MongoId($id);
        
        $criterioAccion = array('_id' => $mongoID, '$isolated' => 1);
        $r = $mongo->borrar($collecion, $criterioAccion, true);
        $json["resultado"] = $r;
        break;

    case "traerCarteras":
        eval('$db=new ' . DB1 . 'DB();');
        $sentencia = "SELECT * FROM cobcartera";
        $carteras = [];
        if ($db->query($sentencia)) {            
            while ($rowdb = $db->fetchRow()){
                $carteras[]=["cobCartera_nombre"=>$rowdb["cobCartera_nombre"],"cobCartera_id"=>$rowdb["cobCartera_id"]];
            }

        }
        $json["resultado"] = $carteras;
        break;
    
}

function onlyChars($string) {
    $strlength = strlen($string);
    $retString = "";
    for ($i = 0; $i < $strlength; $i++) {
//            print_h(ord($string[$i]).'__');
        if (ord($string[$i]) == 209) {//supuesta Ñ la cambia por N
            $string[$i] = chr(78);
        }
        if ((ord($string[$i]) >= 48 && ord($string[$i]) <= 57) ||
                (ord($string[$i]) >= 65 && ord($string[$i]) <= 90) ||
                (ord($string[$i]) >= 97 && ord($string[$i]) <= 122) ||
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
                $textoLimpio.=$ch;
            } else {
//                    print_h("no entre ñ");
                if (preg_match('//u', $ch)) {
                    $textoLimpio.=$ch;
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

jsonEnd($json, $limpiar);
?><? //_FIN_DE_ARCHIVO                                           ?>
