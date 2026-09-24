<?

require_once ("../comunes/top.inc.php");
require_once ("../comunes/classes/class.coTabulaAngular.php");
require_once ("../comunes/classes/class.coCompleteAngular.php");
if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();
$conf = getConf("Email alerts");

switch ($act) {
    case "inicializar":
        require_once("../comunes/classes/sc_calendar.php");
        $diasConsulta = isset($conf["Días de consulta iniciales"][0]) ? $conf["Días de consulta iniciales"][0] : 3;
        $fechaInicio = sc_calendar::DateAdd("d", -$diasConsulta, time());
        $json["resultado"]["fechaInicio"] = $fechaInicio;
    break;
    case "buscarFamilias":
        $d = jsonStart();
        $ngComplete = new coCompleteAngular();
        $ngComplete->setInput($d);
        $limpiar = array("alEvents_" => "eve_");
        $db = new MYSQLDB();
        $sql = $db->mkSQL("SELECT * FROM alevents ORDER BY alEvents_familia,alEvents_nombre");
        $ngComplete->setQueryDatos($sql, "alEvents_familia alEvents_nombre");
        $ngComplete->setCamposConTabla(false);
        $json = $ngComplete->responde();
    break;
    case "getAll":
        $d = jsonStart();
        $fechaInicio = isset($_REQUEST["fechaInicio"]) ? expect_integer($_REQUEST["fechaInicio"]) : time();
        $fechaFin = isset($_REQUEST["fechaFin"]) ? expect_integer($_REQUEST["fechaFin"]) : time();
        $grupos = isset($_REQUEST["grupos"]) ? expect_safe_html($_REQUEST["grupos"]) : array();
        $fechaInicio = date("Y-m-d",$fechaInicio)." 00:00:00";
        $fechaInicio = strtotime($fechaInicio);
        $fechaFin = date("Y-m-d",$fechaFin)." 23:59:59";
        $fechaFin = strtotime($fechaFin);
        $sqlGrupos = "";
        $db = new MYSQLDB();
        if(!empty($grupos)){
            $eventosId = trim($grupos,',');
            $sqlGrupos = $db->mkSQL(" AND alEvents_id IN (".$eventosId.")");
        }
        $limpiar = array("alEnvelopes_" => "en_", "alEvents_" => "ev_");
        $ngTabula = new coTabulaAngular();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $sql = $db->mkSQL("SELECT alEvents_familia,
                                        alEvents_nombre,
                                        alEnvelopes_id,
                                        alEnvelopes_to,
                                        alEnvelopes_from,
                                        alEnvelopes_subject,
                                        alEnvelopes_attachments,
                                        alEnvelopes_when,
                                        alEnvelopes_text,
                                        alEnvelopes_html,
                                        alEnvelopes_success,
                                        alEnvelopes_proveedor,alEnvelopes_extra,alEnvelopes_estadoEnvio,alEnvelopes_leido,alEnvelopes_clic,alEnvelopes_cc,alEnvelopes_bcc
                                FROM alenvelopes LEFT JOIN alevents ON alEnvelopes_eventId=alEvents_id
                                WHERE alEnvelopes_when>=%N AND alEnvelopes_when<=%N".$sqlGrupos,
                $fechaInicio,$fechaFin);
        $ngTabula->setQueryDatos($sql);
        $ngTabula->setOrdenDefault("alEnvelopes_when DESC");
        $ngTabula->setCamposConTabla(false);
        $ngTabula->permiteExportar(true);
        $ngTabula->setPreparaDatos(function($fila) {
            $fila["alEnvelopes_proveedor"] = empty($fila["alEnvelopes_proveedor"])?'default':$fila["alEnvelopes_proveedor"];
            if ($fila["alEnvelopes_proveedor"]=="sendgrid"){
                $valor = "";
                switch ($fila["alEnvelopes_estadoEnvio"]) {
                    case "no enviado":
                        $valor="No enviado";
                        break;
                    case "enviado":
                        $valor="Enviado";
                        break;
                    case "delivered":
                        $valor="Entregado";
                        break;
                    case 'not_delivered':
                        $valor="Se envio pero no se pudo entregar";
                        break;
                    case 'processing':
                        $valor="Procesando";
                        break;
                    default:
                        break;
                }
                $fila["alEnvelopes_estadoEnvio"] = $valor;
            }
            return $fila;
        });
        $ngTabula->setPreparaDatosCabeceraExportar(function($cabecera) {
            switch ($cabecera) {
                case "alEvents_familia": $cabecera = "GRUPO";break;
                case "alEvents_nombre": $cabecera = "NOMBRE";break;
                case "alEnvelopes_from": $cabecera = "ORÍGEN";break;
                case "alEnvelopes_to": $cabecera = "DESTINATARIO";break;
                case "alEnvelopes_subject": $cabecera = "TITULO";break;
                case "alEnvelopes_attachments": $cabecera = "ADJUNTOS";break;
                case "alEnvelopes_when": $cabecera = "FECHA ENVÍO";break;
                case "alEnvelopes_success": $cabecera = "RESULTADO";break;
                case "alEnvelopes_proveedor": $cabecera = "PROVEEDOR";break;
                case "alEnvelopes_extra": $cabecera = "EXTRA (sendgrid)";break;
                case "alEnvelopes_estadoEnvio": $cabecera = "ESTADO ENVIO (sendgrid)";break;
                case "alEnvelopes_leido": $cabecera = "CUANDO LEYO (sendgrid)";break;
                case "alEnvelopes_clic": $cabecera = "CUANDO HIZO CLIC (sendgrid)";break;
                
            }
            return $cabecera;
        });
        $ngTabula->setPreparaDatosExportar(function($fila) {
            unset($fila["alEnvelopes_id"]);
            unset($fila["alEnvelopes_text"]);
            unset($fila["alEnvelopes_html"]);
            $fila["alEnvelopes_when"] = !empty($fila["alEnvelopes_when"]) ? date("Y-m-d H:i",$fila["alEnvelopes_when"]) : "";
            $fila["alEnvelopes_attachments"] = empty($fila["alEnvelopes_attachments"]) ? "'<Ninguno>" : $fila["alEnvelopes_attachments"];
            $fila["alEnvelopes_success"] = $fila["alEnvelopes_success"] ? "Ok" : "";
            $fila["alEnvelopes_leido"] = !empty($fila["alEnvelopes_leido"]) && $fila["alEnvelopes_leido"]>0 ? date("Y-m-d H:i",$fila["alEnvelopes_leido"]) : "";
            $fila["alEnvelopes_clic"] = !empty($fila["alEnvelopes_clic"]) && $fila["alEnvelopes_clic"]>0 ? date("Y-m-d H:i",$fila["alEnvelopes_clic"]) : "";
            $fila["alEnvelopes_proveedor"] = !empty($fila["alEnvelopes_proveedor"]) ? $fila["alEnvelopes_proveedor"] : "default";
            
            if ($fila["alEnvelopes_proveedor"]=="sendgrid"){
                $valor = "";
                switch ($fila["alEnvelopes_estadoEnvio"]) {
                    case "no enviado":
                        $valor="No enviado";
                        break;
                    case "enviado":
                        $valor="Enviado";
                        break;
                    case "delivered":
                        $valor="Entregado";
                        break;
                    case 'not_delivered':
                        $valor="Se envio pero no se pudo entregar";
                        break;
                    case 'processing':
                        $valor="Procesando";
                        break;
                    default:
                        break;
                }
                $fila["alEnvelopes_estadoEnvio"] = $valor;
            } 
            
            return $fila;
        });
        $json = $ngTabula->responde();
        break;
    case "getAllPanel":
        if ($Central->conPermiso("Email alerts,Administrator")) {
            $d = jsonStart();
            $limpiar = array("alEvents_" => "ev_");
            //primeramente aprovechemos para ejecutar una actualización de los alerts desde el código
            require_once("../alerts/classes/class.alEvent.php");
            $alEv = new alEvent();
            $alEv->parse_events();
            $ngTabula = new coTabulaAngular();
            $ngTabula->setInput($d);
            $ngTabula->setLimpiador($limpiar);
            $ngTabula->setQueryDatos("SELECT *
		FROM alevents");
            $ngTabula->setOrdenDefault("alEvents_familia");
            $ngTabula->setCamposConTabla(false);
            $ngTabula->permiteExportar(true);
            $json = $ngTabula->responde();
        }
    break;
    case "showEnvelope":
        if (isset($_REQUEST["env"])) {
            $env = expect_integer(limpiaInput("env"));
            require_once("../alerts/classes/class.alEnvelope.php");
            $envOb = new alEnvelope();
            $envOb->initFromDB($env);
            if ($envOb->get("id") == $env) {
                $retVal .= $envOb->muestra();
            }
        }
    break;
    case "resendMail":
        $d = jsonStart();
        if (isset($d["id"])) {
            $env = expect_integer($d["id"]);
            require_once("../alerts/classes/class.alEnvelope.php");
            $envOb = new alEnvelope();
            $envOb->initFromDB($env);
            $mailsS = array();
            if (expect_safe_html($d["email"])) {
                $mailsS = explode(",", expect_safe_html(trim($d["email"])));
            }
            $emails = array();
            if (count($mailsS) <> 0) {
                foreach ($mailsS as $key => $val) {
                    $emails[] = trim($val);
                }
            }
            $mto = $emails;
            if (strpos($envOb->get("subject"), "FW: ") === false) {
                $msubject = "FW: " . $envOb->get("subject");
            } else {
                $msubject = $envOb->get("subject");
            }

            require_once("../alerts/classes/class.alEvent.php");
            $alEv = new alEvent();
            $ok = ($alEv->send_alert(array(
                        "family" => $envOb->getEvent()->get("familia"),
                        "name" => $envOb->getEvent()->get("nombre"),
                        "explanation" => $envOb->getEvent()->get("descripcion"),
                        "subject" => $msubject,
                        "to" => $mto,
                        "from" => $envOb->get("from"),
                        "text" => $envOb->get("text"),
                        "html" => $envOb->get("html"),
                        "attachments" => $envOb->get("attachments"),
            )));
            if ($ok)
                $json["respuesta"] = "Mail enviado correctamente";
            else
                $json["respuesta"] = "Mail no pudo ser enviado correctamente";
        }
    break;
    case "toggleActiva":
        if ($Central->conPermiso("Email alerts,Administrator")) {
            $d = jsonStart();
            if (isset($d["id"])) {
                $ev = expect_integer($d["id"]);
                $val = expect_boolean($d["val"]);
                $tipo = expect_pure_alpha($d["tipo"]);
                require_once("../alerts/classes/class.alEvent.php");
                $evOb = new alEvent();
                $evOb->initFromDB($ev);
                if ($evOb->get("id") == $ev) {
                    switch ($tipo) {
                        case "normal":
                            if ($evOb->get("activoNormal") != $val) {
                                $evOb->toggleActivoNormal();
                                $json["respuesta"] = $evOb->get("activoNormal");
                            } else {
                                $json["errores"] = "No se modificaron los datos";
                            }
                            break;
                        case "adicional":
                            if ($evOb->get("activoAdicional") != $val) {
                                $evOb->toggleActivoAdicional();
                                $json["respuesta"] = $evOb->get("activoAdicional");
                            } else {
                                $json["errores"] = "No se modificaron los datos";
                            }
                            break;
                    }
                } else {
                    $json["errores"] = "No se encontró el evento";
                }
            }
        }
    break;
    case "guardaAdicionales":
        if ($Central->conPermiso("Email alerts,Administrator")) {
            $d = jsonStart();
            $nuevoValor = "";
            if (isset($d["id"])) {
                $ev = expect_integer($d["id"]);
                $adicionales = expect_safe_html($d["adic"]);
                require_once("../alerts/classes/class.alEvent.php");
                $evOb = new alEvent();
                $evOb->initFromDB($ev);
                if ($evOb->get("id") == $ev) {
                    //revise lo recibido
                    $valores = explode("\n", $adicionales);
                    $nuevoValor = array();
                    foreach ($valores as $val) {
                        if (expect_email($val)) {
                            $nuevoValor[] = expect_email($val);
                        }
                    }
                    $nuevoValor = implode("\n", $nuevoValor);
                    $evOb->setAdicionales($nuevoValor);
                    $json["valorGuardado"] = $nuevoValor;
                } else {
                    $json["errores"] = "No se encontró el evento";
                }
            } else {
                $json["errores"] = "No se encontró el evento";
            }
        }
    break;
    case "buscarDetalleSendgrid":
        $d = jsonStart();
        if (isset($d["id"])) {
            $id = expect_safe_html($d["id"]);
            $cnf = getConf("Email alerts");
            $key = isset($cnf["Sendgrid key"][0]) ? $cnf["Sendgrid key"][0] : "";
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.sendgrid.com/v3/messages/".$id,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "GET",
              CURLOPT_POSTFIELDS => "{}",
              CURLOPT_HTTPHEADER => array(
                "authorization: Bearer ".$key
              ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            
            if ($err) {
                $json["errores"] = "cURL Error #: ". $err;
            } else {
                $comoArray = json_decode($response, true);
                if (isset($comoArray['errors'])){
                    $json["errores"] = "Sendgrid says: ".$comoArray['errors'][0]['message'];
                } else {
                    $json["respuesta"] = $comoArray;
                    
                }
            }
            
        } else {
            $json["errores"] = "No se envio un id";
        }
        break;
}
jsonEnd($json, $limpiar);
?>
<? //_FIN_DE_ARCHIVO ?>