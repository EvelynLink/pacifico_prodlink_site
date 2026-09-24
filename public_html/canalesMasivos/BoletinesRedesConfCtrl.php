<?php
ini_set("max_execution_time", "29000439");
ini_set('memory_limit', '2000M');
date_default_timezone_set('UTC');

// <editor-fold defaultstate="collapsed" desc="REQUIRES TO SISBAK FRAMEWORK">
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once "../comunes/classes/class.coCompleteAngular.php";
require_once("../comunes/classes/class.coTabulaAngular.php");
require_once("../boletines/classes/class.boRedesConf.php");
require_once("../networkAds/classes/class.ntfacebook.php");
// </editor-fold>

if (!isset($_REQUEST["act"])) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

switch ($act) {
    case "verifyInfo" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB">
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $apps = [];
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Aplicación no conectada" ];
        while ($result = $mongo->siguiente()) {
            //Informacion de Aplicaciones
            $temp_apps = $result['variables']['app'];
            foreach ($temp_apps as $key => $value) {
                $apps[$key] = array_merge($value, $light);
            }
            //MULTI APPS FACEBOOK
            $app_id = $secret = $version = "";
            $token = $result['token'];
            //Informacion de Empresa
            $temp_business = $result['variables']['business'];
            $business_id = $temp_business['id'];
            //Informacion de Cuenta publicitaria
            $temp_adaccount = $result['variables']['adaccount'];
            $adaccount_id = $temp_adaccount['id'];
        }
        //Carga informacion en variable de respuesta que precargara informacion en formularios
        $app = array_merge($app, $light);
        $config = [
            'app' => $apps,
            'business' => [
                'id' => $business_id
            ],
            'adaccount' => [
                'id' => $adaccount_id
            ],
            'connectBusiness' => (($business_id != "") ? true : false),
            'connectAdaccount' => (($adaccount_id != "") ? true : false)
        ];
        $json['answer'] = $config;
        // </editor-fold>
        break;
    case "verifyApps" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Apps">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $app_id = $adaccount_id = $secret = $version = $token = "";
        $app_connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Aplicación no conectada" ];
        $content = [ 'op' => false, 'data' => [] ];
        while ($result = $mongo->siguiente()) {
            $apps = $result['variables']['app'];
            //MULTI APPS FACEBOOK
            foreach ($apps as $key => $values) {
                //Informacion de Aplicaciones
                $app_id = $values['id'];
                $app_connected = $values['connected'];
                $secret_full = $secret = $values['app_secret'];
                $version = $values['default_graph_version'];
                $token_full = $token = $values['token'];
                if ($secret != "") {
                    $secret = shortener($secret_full, 10);
                }
                if ($token != "") {
                    $token = shortener($token_full, 17);
                }
                $ans[] = [
                    'id' => $app_id,
                    'secret' => $secret,
                    'secret_full' => $secret_full,
                    'version' => $version,
                    'token' => $token,
                    'token_full' => $token_full,
                    'connected' => $app_connected,
                    'light' => $light,
                    'content' => $content
                ];
            }
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;
        $json["extra"]["config"] = true;
        // </editor-fold>
        break;
    case "verifyBusiness" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Business">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $id = $app_id = "";
        $connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Negocio no conectado" ];
        $content = [ 'op' => false, 'data' => [] ];
        while ($result = $mongo->siguiente()) {
            $business = $result['variables']['business'];
            //MULTI BUSINESS FACEBOOK
            foreach ($business as $key => $values) {
                //Informacion de Negocios
                $id = $values['id'];
                $app_id = $values['app_id'];
                $parent_name = $values['parent_name'];
                $connected = $values['connected'];
                $ans[] = [
                    'id' => $id,
                    'app_id' => $app_id,
                    'parent_name' => $parent_name,
                    'connected' => $connected,
                    'light' => $light,
                    'content' => $content
                ];
            }
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;
        $json["extra"]["config"] = true;
        // </editor-fold>
        break;
    case "verifyAdaccount" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Adaccount">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $id = $business_id = "";
        $connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Cuenta publicitaria no conectada" ];
        $content = [ 'op' => false, 'data' => [] ];
        while ($result = $mongo->siguiente()) {
            $adaccount = $result['variables']['adaccount'];
            //MULTI BUSINESS FACEBOOK
            foreach ($adaccount as $key => $values) {
                //Informacion de Cuentas publicitarias
                $id = $values['id'];
                $business_id = $values['business_id'];
                $parent_name = $values['parent_name'];
                $connected = $values['connected'];
                $ans[] = [
                    'id' => $id,
                    'business_id' => $business_id,
                    'parent_name' => $parent_name,
                    'connected' => $connected,
                    'light' => $light,
                    'content' => $content
                ];
            }
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;
        $json["extra"]["config"] = true;
        // </editor-fold>
        break;
    case "verifyCampaign" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Campaign">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $id = $adaccount_id = "";
        $connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Campaña no conectada" ];
        $content = [ 'op' => false, 'data' => [] ];
        while ($result = $mongo->siguiente()) {
            $campaign = $result['variables']['campaign'];
            //MULTI CAMPAIGN FACEBOOK
            foreach ($campaign as $key => $values) {
                //Informacion de Campañas
                $id = $values['id'];
                $adaccount_id = $values['adaccount_id'];
                $parent_name = $values['parent_name'];
                $connected = $values['connected'];
                $ans[] = [
                    'id' => $id,
                    'adaccount_id' => $adaccount_id,
                    'parent_name' => $parent_name,
                    'connected' => $connected,
                    'light' => $light,
                    'content' => $content
                ];
            }
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;
        $json["extra"]["config"] = true;
        // </editor-fold>
        break;
    case "verifyAdset" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Adset">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $id = $adaccount_id = "";
        $connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Conjunto de anuncios no conectado" ];
        $content = [ 'op' => false, 'data' => [] ];
        while ($result = $mongo->siguiente()) {
            $campaign = $result['variables']['adset'];
            //MULTI CAMPAIGN FACEBOOK
            foreach ($campaign as $key => $values) {
                //Informacion de Campañas
                $id = $values['id'];
                $adaccount_id = $values['adaccount_id'];
                $parent_name = $values['parent_name'];
                $connected = $values['connected'];
                $ans[] = [
                    'id' => $id,
                    'adaccount_id' => $adaccount_id,
                    'parent_name' => $parent_name,
                    'connected' => $connected,
                    'light' => $light,
                    'content' => $content
                ];
            }
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        $json["extra"]["noEditable"] = true;
        $json["extra"]["read"] = true;
        $json["extra"]["write"] = true;
        $json["extra"]["config"] = true;
        // </editor-fold>
        break;
    case "verifyAudiences" :
        // <editor-fold defaultstate="collapsed" desc="Verify configuration information from MongoDB about Audiences">
        $d = jsonStart();
        $ans = [];
        $msj = "";
        $app_id = $adaccount_id = $secret = $version = $token = "";
        $app_connected = false;
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            //Informacion de Aplicaciones
            $app_id = $result['variables']['app']['id'];
            $app_connected = $result['variables']['app']['connected'];
            $secret = $result['variables']['app']['app_secret'];
            $version = $result['variables']['app']['default_graph_version'];
            $adaccount_id = $result['variables']['adaccount']['id'];
            $token = $result['token'];
        }
        if ($app_id != "" && $adaccount_id != "" && $app_connected) {
            //Debe haber un APP ID valido
            $facebook = new ntfacebook($app_id, $secret, $version);
            $res = $facebook->getAudience($token, $adaccount_id);
            $ans =  $res['data'];
        } else {
            //No hay coneccion a Aplicaciones, no intentara buscar audiencias
            $msj = "No pudo conectarse cuenta publicitaria";
        }
        $obj = [
            'cuantos' => count($ans),
            'extra' => [ 'msj' => $msj ],
            'filas' => $ans
        ];
        $json = $obj;
        // </editor-fold>
        break;
    case "formCrud" :
        // <editor-fold defaultstate="collapsed" desc="CRUD for forms depends of parameter">
        $d = jsonStart();
        $op = expect_safe_html($_REQUEST["op"]);
        $index = expect_safe_html($_REQUEST["index"]);
        $ans = false;
        switch ($op) {
            case "deleteApp" :
                $ans = deleteApp($index);
                break;
            case "deleteBusiness" :
                $ans = deleteBusiness($index);
                break;
            case "deleteAdaccount" :
                $ans = deleteAdaccount($index);
                break;
            case "deleteCampaign" :
                $ans = deleteCampaign($index);
                break;
            case "deleteAdset" :
                $ans = deleteAdset($index);
                break;
            default:
                break;
        }
        $json['resultado'] = $ans;
        // </editor-fold>
        break;
    case "saveForm" :
        // <editor-fold defaultstate="collapsed" desc="Save forms depends of parameter">
        $d = jsonStart();
        //DATOS OBLIGATORIOS
        $form = expect_safe_html($_REQUEST["form"]);
        $index = expect_safe_html($_REQUEST["index"]);
        $data = $d;
        switch ($form) {
            case "app":
                $ans = saveFromApp($data, $index);
                break;
            case "business":
                $ans = saveFromBusiness($data, $index);
                break;
            case "adaccount":
                $ans = saveFromAdaccount($data, $index);
                break;
            case "campaign":
                $ans = saveFromCampaign($data, $index);
                break;
            case "adset":
                $ans = saveFromAdset($data, $index);
                break;
            default:
                $ans = [ 'op' => false, 'msj' => "Disculpe, ha ocurrido un error inesperado, intente mas tarde" ];
        }
        $json = $ans;
        // </editor-fold>
        break;
    case "tokenDebugger" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to debugg token">
        $ans = [ 'op' => 'false', 'data' => "Disculpe, no fue posible depurar el token" ];
        $id = $secret = $version = $token = "";
        $d = jsonStart();
        if ($d['who'] != "all") {
            $who = $d['who'];
            
            $mongo = new MYMONGODB();
            $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
            while ($result = $mongo->siguiente()) {
                $apps = $result['variables']['app'];
                //Multiple Apps
                foreach ($apps as $key => $values) {
                    $key = (string) $key;
                    $id = $values['id'];
                    $secret = $values['app_secret'];
                    $version = $values['default_graph_version'];
                    $token = $values['token'];
                    //Validacion para realizar llamada al API Graph
                    if ($key == $who) {
                        if ($token != "") {
                            //INSTANCIA DE OBJETO NTFACEBOOK
                            $facebook = new ntfacebook($id, $secret, $version, $token);
                            $res = $facebook->tokenDebugg($token);
                            if ($res['op']) {
                                $temp = $res['data'];
                                if (is_array($temp)) {
                                    $expire_at = "";
                                    if (isset($temp['expires_at'])) {
                                        if ($temp['expires_at'] > 0) {
                                            $expire_at = date('Y-m-d h:i:s a', $temp['expires_at']);
                                        } else {
                                            $expire_at = 'Nunca';
                                        }
                                    }
                                    $valid = "No";
                                    if (isset($temp['is_valid'])) {
                                        if ($temp['is_valid']) {
                                            $valid = "Sí";
                                        }
                                    }
                                    $scopes = "";
                                    foreach($temp['scopes'] as $value) {
                                        $scopes.= $value.', ';
                                    }
                                    $formated_data = [
                                        'Aplicación' => isset($temp['application']) ? $temp['app_id'].": ".$temp['application'] : "",
                                        'Identificador de usuario' => isset($temp['user_id']) ? $temp['user_id'] : "",
                                        'Emitido' => isset($temp['issued_at']) ? date('Y-m-d h:i:s a', $temp['issued_at']) : "",
                                        'Caducidad' => $expire_at,
                                        'Valido' => $valid,
                                        'Ambitos' => ($scopes != "") ? substr($scopes, 0, -2) : $scopes
                                    ];
                                    $ans = [ 'op' => 'true', 'data' => $formated_data ];
                                } else {
                                    $ans = [ 'op' => 'false', 'data' => $temp ];
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "connectApp" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to connect Facebook App">
        $ans = [];
        $id = $secret = $version = $token = "";
        $d = jsonStart();
        $who = (isset($d['who'])) ? (string) $d['who'] : 'all';

        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            $apps = $result['variables']['app'];
            //Multiple Apps
            foreach ($apps as $key => $values) {
                $key = (string) $key;
                $id = $values['id'];
                $secret = $values['app_secret'];
                $version = $values['default_graph_version'];
                $token = $values['token'];
                //Validacion para realizar llamada al API Graph
                if ($who == 'all' || $key == $who) {
                    if ($id != "" && $secret != "") {
                        if ($token != "") {
                            //INSTANCIA DE OBJETO NTFACEBOOK
                            $facebook = new ntfacebook($id, $secret, $version, $token);
                            $res = $facebook->getApp($token);
                            if ($res['op']) {
                                $ans["#$key"] = [ 'op' => true, 'msj' => $res['data'] ];
                            } else {
                                //NO FUE POSIBLE CONECTAR LA APLICACION
                                $ans["#$key"] = [ 'op' => false, 'msj' => "No fue posible conectar la aplicación, verifique la información proporcionada" ];
                            }
                        } else {
                            $ans["#$key"] = [ 'op' => false, 'msj' => "Se requiere un token de acceso para un usuario con privilegios" ];
                        }
                    } else {
                        $ans["#$key"] = [ 'op' => false, 'msj' => "No hay información suficiente para conectar aplicación" ];
                    }
                }
                //Actualiza conexion
                if (isset($ans["#$key"])) {
                    updateConectionStatus('app', $key, $ans["#$key"]["op"], $ans["#$key"]["msj"]);
                }
            }
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "connectBusiness" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to connect Business">
        $ans = [];
        $id = $app_id = "";
        $d = jsonStart();
        $who = (isset($d['who'])) ? (string) $d['who'] : 'all';

        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            $apps = $result['variables']['app'];
            $business = $result['variables']['business'];
            //Multiple Business
            foreach ($business as $key => $values) {
                $key = (string) $key;
                $id = $values['id'];
                $app_id = $values['app_id'];
                $temp_token = $temp_id = $temp_secret = $temp_version = "";

                foreach ($apps as $key2 => $values2) {
                    //Seleleccion de app especifica
                    if ($values2['id'] == $app_id) {
                        $temp_token = $values2['token'];
                        $temp_id = $values2['id'];
                        $temp_secret = $values2['app_secret'];
                        $temp_version = $values2['default_graph_version'];
                        break;
                    }
                }

                if ($temp_id !== "" && $temp_secret != "") {
                    //Validacion para realizar llamada al API Graph
                    if ($who == 'all' || $key == $who) {
                        if ($id != "" && $app_id != "") {
                            if ($temp_token != "") {
                                //INSTANCIA DE OBJETO NTFACEBOOK
                                $facebook = new ntfacebook($temp_id, $temp_secret, $temp_version);
                                $res = $facebook->getBusiness($temp_token, $id);
                                if ($res['op']) {
                                    $ans["#$key"] = [ 'op' => true, 'msj' => $res['data'] ];
                                } else {
                                    //NO FUE POSIBLE CONECTAR LA APLICACION
                                    $ans["#$key"] = [ 'op' => false, 'msj' => "No fue posible conectar la empresa, verifique la información proporcionada" ];
                                }
                            } else {
                                $ans["#$key"] = [ 'op' => false, 'msj' => "Se requiere un token de acceso para un usuario con privilegios" ];
                            }
                        } else {
                            $ans["#$key"] = [ 'op' => false, 'msj' => "No hay información suficiente para conectar empresa" ];
                        }
                    }
                }
                //Actualiza conexion
                if (isset($ans["#$key"])) {
                    updateConectionStatus('business', $key, $ans["#$key"]["op"], $ans["#$key"]["msj"]);
                }
            }
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "connectAdaccount" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to connect Ad Account">
        $ans = [];
        $id = $app_id = "";
        $d = jsonStart();
        $who = (isset($d['who'])) ? (string) $d['who'] : 'all';

        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            $apps = $result['variables']['app'];
            $business = $result['variables']['business'];
            $adaccount = $result['variables']['adaccount'];
            //Multiple Adaccount
            foreach ($adaccount as $key => $values) {
                $key = (string) $key;
                $id = $values['id'];
                $business_id = $values['business_id'];
                $temp_token = $temp_id = $temp_secret = $temp_version = "";

                foreach ($business as $key2 => $values2) {
                    //Seleleccion de negocio especifico
                    if ($values2['id'] == $business_id) {
                        $app_id = $values2['app_id'];
                        foreach ($apps as $key3 => $value3) {
                            if ($value3['id'] == $app_id) {
                                $temp_token = $value3['token'];
                                $temp_id = $value3['id'];
                                $temp_secret = $value3['app_secret'];
                                $temp_version = $value3['default_graph_version'];

                                if ($temp_id !== "" && $temp_secret != "") {
                                    //Validacion para realizar llamada al API Graph
                                    if ($who == 'all' || $key == $who) {
                                        if ($id != "" && $business_id != "") {
                                            if ($temp_token != "") {
                                                //INSTANCIA DE OBJETO NTFACEBOOK
                                                $facebook = new ntfacebook($temp_id, $temp_secret, $temp_version);
                                                $res = $facebook->getAdaccount($temp_token, $id);
                                                if ($res['op']) {
                                                    $ans["#$key"] = [ 'op' => true, 'msj' => $res['data'] ];
                                                } else {
                                                    //NO FUE POSIBLE CONECTAR LA APLICACION
                                                    $ans["#$key"] = [ 'op' => false, 'msj' => "No fue posible conectar la empresa, verifique la información proporcionada" ];
                                                }
                                            } else {
                                                $ans["#$key"] = [ 'op' => false, 'msj' => "Se requiere un token de acceso para un usuario con privilegios" ];
                                            }
                                        } else {
                                            $ans["#$key"] = [ 'op' => false, 'msj' => "No hay información suficiente para conectar empresa" ];
                                        }
                                    }
                                }
                                break;
                            }
                        }
                        break;
                    }
                }
                //Actualiza conexion
                if (isset($ans["#$key"])) {
                    updateConectionStatus('adaccount', $key, $ans["#$key"]["op"], $ans["#$key"]["msj"]);
                }
            }
            break;
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "connectCampaign" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to connect Campaign">
        $ans = [];
        $id = $app_id = "";
        $d = jsonStart();
        $who = (isset($d['who'])) ? (string) $d['who'] : 'all';

        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            $campaign = $result['variables']['campaign'];
            //Multiple Campaigns
            foreach ($campaign as $campaign_key => $campaign_values) {
                $campaign_key = (string) $campaign_key;
                $id = $campaign_values['id'];
                $adaccount_id = $campaign_values['adaccount_id'];
                
                $myClass = new boRedesConf();
                $conf = $myClass->deployConfig($id, 'campaign');
                if ($conf['id'] != "" && $conf['secret'] != "") {
                    $temp_token = $conf['token'];
                    $temp_id = $conf['id'];
                    $temp_secret = $conf['secret'];
                    $temp_version = $conf['version'];
                    //Validacion para realizar llamada al API Graph
                    if ($who == 'all' || $campaign_key == $who) {
                        if ($id != "" && $adaccount_id != "") {
                            if ($temp_token != "") {
                                //INSTANCIA DE OBJETO NTFACEBOOK
                                $facebook = new ntfacebook($temp_id, $temp_secret, $temp_version);
                                $res = $facebook->getCampaign($temp_token, $id);
                                if ($res['op']) {
                                    $ans["#$campaign_key"] = [ 'op' => true, 'msj' => $res['data'] ];
                                } else {
                                    //NO FUE POSIBLE CONECTAR LA APLICACION
                                    $ans["#$campaign_key"] = [ 'op' => false, 'msj' => "No fue posible conectar la campaña, verifique la información proporcionada" ];
                                }
                            } else {
                                $ans["#$campaign_key"] = [ 'op' => false, 'msj' => "Se requiere un token de acceso para un usuario con privilegios" ];
                            }
                        } else {
                            $ans["#$campaign_key"] = [ 'op' => false, 'msj' => "No hay información suficiente para conectar campañas" ];
                        }
                    }
                } else {
                    $ans["#$campaign_key"] = [ 'op' => false, 'msj' => "No hay información de aplicación para contectar Campaña" ];
                }
                //Actualiza conexion
                if (isset($ans["#$campaign_key"])) {
                    updateConectionStatus('campaign', $campaign_key, $ans["#$campaign_key"]["op"], $ans["#$campaign_key"]["msj"]);
                }
            }
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "connectAdset" :
        // <editor-fold defaultstate="collapsed" desc="Attempt to connect Adset">
        $ans = [];
        $id = $app_id = "";
        $d = jsonStart();
        $who = (isset($d['who'])) ? (string) $d['who'] : 'all';

        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        while ($result = $mongo->siguiente()) {
            $adset = $result['variables']['adset'];
            //Multiple Adsets
            foreach ($adset as $adset_key => $adset_values) {
                $adset_key = (string) $adset_key;
                $id = $adset_values['id'];
                $adaccount_id = $adset_values['adaccount_id'];
                $myClass = new boRedesConf();
                $conf = $myClass->deployConfig($id, 'adset');
                if ($conf['id'] != "" && $conf['secret'] != "") {
                    $temp_token = $conf['token'];
                    $temp_id = $conf['id'];
                    $temp_secret = $conf['secret'];
                    $temp_version = $conf['version'];

                    //Validacion para realizar llamada al API Graph
                    if ($who == 'all' || $adset_key == $who) {
                        if ($id != "" && $adaccount_id != "") {
                            if ($temp_token != "") {
                                //INSTANCIA DE OBJETO NTFACEBOOK
                                $facebook = new ntfacebook($temp_id, $temp_secret, $temp_version);
                                $res = $facebook->getAdset($temp_token, $id);
                                if ($res['op']) {
                                    $ans["#$adset_key"] = [ 'op' => true, 'msj' => $res['data'] ];
                                } else {
                                    //NO FUE POSIBLE CONECTAR LA APLICACION
                                    $ans["#$adset_key"] = [ 'op' => false, 'msj' => "No fue posible conectar el conjunto de anuncios, verifique la información proporcionada" ];
                                }
                            } else {
                                $ans["#$adset_key"] = [ 'op' => false, 'msj' => "Se requiere un token de acceso para un usuario con privilegios" ];
                            }
                        } else {
                            $ans["#$adset_key"] = [ 'op' => false, 'msj' => "No hay información suficiente para conectar conjunto de anuncios" ];
                        }
                    }
                } else {
                    $ans["#$adset_key"] = [ 'op' => false, 'msj' => "No hay información de aplicación para contectar Adset" ];
                }
                //Actualiza conexion
                if (isset($ans["#$adset_key"])) {
                    updateConectionStatus('adset', $adset_key, $ans["#$adset_key"]["op"], $ans["#$adset_key"]["msj"]);
                }
            }
        }
        $json['answer'] = $ans;
        // </editor-fold>
        break;
    case "formAudiencePre" :
        // <editor-fold defaultstate="collapsed" desc="Get pre information to audience form">
        $app_id = $secret = $version = $token = "";
        $d = jsonStart();
        $facebook = new ntfacebook();
        $ans = [
            'currencies' => $facebook->getCurrencies(),
            'timezones' => $facebook->getTimezones()
        ];
        $json['answer'] = $ans;
        // </editor-fold>
        break;
}
jsonEnd($json, $limpiar);

/**
 * Permite actualizar el parametro connection de cada item en la configuracion facebook
 * @param string $from Nombre de item
 * @param string $index Posicion de configuracion
 * @param boolean $connection Conexion establecida en facebook o no
 * @param array $data Datos del objetos obtenidos de Facebook
 */
function updateConectionStatus($from, $index, $connection = false, $data = []) {
    $whitelist = [ 'id', 'name', 'status', 'campaign_id', 'adset_id', 'app_id', 'business_id' ];
    if (is_array($data) && count($data) > 0) {
        $data = array_intersect_key( $data, array_flip( $whitelist ) );
    }
    $mongo = new MYMONGODB();
    $new = [
        "variables.$from.$index.connected" => $connection,
        "variables.$from.$index.data" => $data
    ];
    $u = $mongo->actualizar("boRedesConf", ['type' => 'facebook'], $new);
    if ($u > 0) {
        //Success!
    } else {
        //Not update because error or the data remains the same 
       // trigger_error('There is an issue updating connection status configuration facebook ('.$from.')');
    }
}

/**
 * Obtine el nombre del objeto en configuracion Facebook
 * @param array $compound Array con 2 elementos (id, element)
 * @return string Nombre de elemento padre
 */
function getObjectName($compound) {
    $ans = "";
    $element = $compound['element'];
    $id = $compound['id'];
    $mongo = new MYMONGODB();
    $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
    while ($result = $mongo->siguiente()) {
        foreach ($result['variables'][$element] as $key => $values) {
            if ($values['id'] == $id) {
                $ans = (isset($values['data']['name'])) ? $values['data']['name'] : "";
            }
        }
    }
    return $ans;
}

/**
 * Permite guardar informacion de aplicacion en coleccion de configuracion MongoDB
 * @param array $data Arreglo con campos a guardar
 * @return array Arreglo con 2 posiciones: op (boolean: Procede o no) | msj (string: Mensaje de error)
 */
function saveFromApp($data, $index) {
    //RECOLECCION DE DATOS
    $id = expect_pure_alphanumeric($data['id']);
    $secret_full = $secret = expect_pure_alphanumeric($data['secret_full']);
    $version = $data['version'];
    $token_full = $token = expect_pure_alphanumeric($data['token_full']);
    if ($secret != "") {
        $secret = shortener($secret_full, 10);
    }
    if ($token != "") {
        $token = shortener($token_full, 17);
    }

    $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Aplicación no conectada" ];
    if (isset($data['light'])) {
        $light = $data['light'];
    }
    $content = [ 'op' => false, 'data' => [] ];
    if (isset($data['content'])) {
        $content = $data['content'];
    }

    //Validaciones de campos
    $temp_errors = [];
    if (!preg_match('/^[0-9]{15}/', $data['id'])) {
        $temp_errors['id'] = "Utilice solo 15 números";
    }
    if (!preg_match('/^([a-zA-Z0-9])*/', $data['secret'])) {
        $temp_errors['secret'] = "Utilice solo números y letras";
    }
    if (!preg_match('/^v[1-9]\.[0-9]/', $data['version'])) {
        $temp_errors['version'] = "Invalido. Ej.: v2.5";
    }
    $resume = [ 'id' => $id, 'secret' => $secret, 'secret_full' => $secret_full, 'version' => $version, 'token' => $token, 'token_full' => $token_full, 'light' => $light, 'content' => $content, 'errores' => $temp_errors ];
    $ans = [ 'resultado' => $resume ];

    if (count($ans['resultado']['errores']) == 0) {
        $new = [
            "variables.app.$index.id" => (string) $id,
            "variables.app.$index.app_secret" => (string) $secret_full,
            "variables.app.$index.default_graph_version" => (string) $version,
            "variables.app.$index.token" => (string) $token_full,
            "variables.app.$index.connected" => false,
            "variables.app.$index.data" => ""
        ];
        $mongo = new MYMONGODB();
        $u = $mongo->actualizar("boRedesConf", [ "type" => "facebook" ], $new);
        if ($u > 0) {
            //PROCEDE
        } else {
            $ans['resultado']['errores']['id'] = "Disculpe, ha ocurrido un error al almacenar la información, intente mas tarde";
        }
    }
    return $ans;
}

/**
 * Permite guardar informacion de negocio en coleccion de configuracion MongoDB
 * @param array $data Arreglo con campos a guardar
 * @return array Arreglo con 2 posiciones: op (boolean: Procede o no) | msj (string: Mensaje de error)
 */
function saveFromBusiness($data, $index) {
    //RECOLECCION DE DATOS
    $id = expect_pure_alphanumeric($data['id']);
    $app_id = $secret = expect_pure_alphanumeric($data['app_id']);

    $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Aplicación no conectada" ];
    if (isset($data['light'])) {
        $light = $data['light'];
    }
    $content = [ 'op' => false, 'data' => [] ];
    if (isset($data['content'])) {
        $content = $data['content'];
    }

    //Validaciones de campos
    $temp_errors = [];
    if (!preg_match('/^[0-9]{15}/', $data['id'])) {
        $temp_errors['id'] = "Utilice solo 15 números";
    }
    $resume = [ 'id' => $id, 'app_id' => $app_id, 'light' => $light, 'content' => $content, 'errores' => $temp_errors ];
    $ans = [ 'resultado' => $resume ];
    //Consulta el nombre del elemento padre
    $parent_name = getObjectName(['element' => 'app', 'id' => $app_id]);
    if (count($ans['resultado']['errores']) == 0) {
        $new = [
            "variables.business.$index.id" => (string) $id,
            "variables.business.$index.app_id" => (string) $app_id,
            "variables.business.$index.parent_name" => $parent_name,
            "variables.business.$index.data" => ""
        ];
        $mongo = new MYMONGODB();
        $u = $mongo->actualizar("boRedesConf", [ "type" => "facebook" ], $new);
        if ($u > 0) {
            //PROCEDE
        } else {
            $ans['resultado']['errores']['id'] = "Disculpe, ha ocurrido un error al almacenar la información, intente mas tarde";
        }
    }
    return $ans;
}

/**
 * Permite guardar informacion de cuenta de publicacion en coleccion de configuracion MongoDB
 * @param array $data Arreglo con campos a guardar
 * @return array Arreglo con 2 posiciones: op (boolean: Procede o no) | msj (string: Mensaje de error)
 */
function saveFromAdaccount($data, $index) {
    //RECOLECCION DE DATOS
    $id = expect_pure_alphanumeric($data['id']);
    $business_id = $secret = expect_pure_alphanumeric($data['business_id']);

    $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Cuenta publicitaria no conectada" ];
    if (isset($data['light'])) {
        $light = $data['light'];
    }
    $content = [ 'op' => false, 'data' => [] ];
    if (isset($data['content'])) {
        $content = $data['content'];
    }

    //Validaciones de campos
    $temp_errors = [];
    if (!preg_match('/^[0-9]{15}/', $data['id'])) {
        $temp_errors['id'] = "Utilice solo 15 números";
    }
    $resume = [ 'id' => $id, 'business_id' => $business_id, 'light' => $light, 'content' => $content, 'errores' => $temp_errors ];
    $ans = [ 'resultado' => $resume ];
    //Consulta el nombre del elemento padre
    $parent_name = getObjectName(['element' => 'business', 'id' => $business_id]);
    if (count($ans['resultado']['errores']) == 0) {
        $new = [
            "variables.adaccount.$index.id" => (string) $id,
            "variables.adaccount.$index.business_id" => (string) $business_id,
            "variables.adaccount.$index.parent_name" => $parent_name,
            "variables.adaccount.$index.data" => ""
        ];
        $mongo = new MYMONGODB();
        $u = $mongo->actualizar("boRedesConf", [ "type" => "facebook" ], $new);
        if ($u > 0) {
            //PROCEDE
        } else {
            $ans['resultado']['errores']['id'] = "Disculpe, ha ocurrido un error al almacenar la información, intente mas tarde";
        }
    }
    return $ans;
}

/**
 * Permite guardar informacion de campaña en coleccion de configuracion MongoDB
 * @param array $data Arreglo con campos a guardar
 * @return array Arreglo con 2 posiciones: op (boolean: Procede o no) | msj (string: Mensaje de error)
 */
function saveFromCampaign($data, $index) {
    //RECOLECCION DE DATOS
    $id = expect_pure_alphanumeric($data['id']);
    $adaccount_id = $secret = expect_pure_alphanumeric($data['adaccount_id']);

    $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Campaña no conectada" ];
    if (isset($data['light'])) {
        $light = $data['light'];
    }
    $content = [ 'op' => false, 'data' => [] ];
    if (isset($data['content'])) {
        $content = $data['content'];
    }

    //Validaciones de campos
    $temp_errors = [];
    if (!preg_match('/^[0-9]{5,}/', $data['id'])) {
        $temp_errors['id'] = "Utilice mas de 5 números";
    }
    $resume = [ 'id' => $id, 'adaccount_id' => $adaccount_id, 'light' => $light, 'content' => $content, 'errores' => $temp_errors ];
    $ans = [ 'resultado' => $resume ];
    //Consulta el nombre del elemento padre
    $parent_name = getObjectName(['element' => 'adaccount', 'id' => $adaccount_id]);
    if (count($ans['resultado']['errores']) == 0) {
        $new = [
            "variables.campaign.$index.id" => (string) $id,
            "variables.campaign.$index.adaccount_id" => (string) $adaccount_id,
            "variables.campaign.$index.parent_name" => $parent_name,
            "variables.campaign.$index.data" => ""
        ];
        $mongo = new MYMONGODB();
        $u = $mongo->actualizar("boRedesConf", [ "type" => "facebook" ], $new);
        if ($u > 0) {
            //PROCEDE
        } else {
            $ans['resultado']['errores']['id'] = "Disculpe, ha ocurrido un error al almacenar la información, intente mas tarde";
        }
    }
    return $ans;
}

/**
 * Permite guardar informacion de conjunto de anuncios en coleccion de configuracion MongoDB
 * @param array $data Arreglo con campos a guardar
 * @return array Arreglo con 2 posiciones: op (boolean: Procede o no) | msj (string: Mensaje de error)
 */
function saveFromAdset($data, $index) {
    //RECOLECCION DE DATOS
    $id = expect_pure_alphanumeric($data['id']);
    $adaccount_id = $secret = expect_pure_alphanumeric($data['adaccount_id']);

    $light = [ 'class' => "fa fa-circle-o fa-mygreen", 'text' => "Conjunto de anuncios no conectado" ];
    if (isset($data['light'])) {
        $light = $data['light'];
    }
    $content = [ 'op' => false, 'data' => [] ];
    if (isset($data['content'])) {
        $content = $data['content'];
    }

    //Validaciones de campos
    $temp_errors = [];
    if (!preg_match('/^[0-9]{5,}/', $data['id'])) {
        $temp_errors['id'] = "Utilice mas de 5 números";
    }
    $resume = [ 'id' => $id, 'adaccount_id' => $adaccount_id, 'light' => $light, 'content' => $content, 'errores' => $temp_errors ];
    $ans = [ 'resultado' => $resume ];
    //Consulta el nombre del elemento padre
    $parent_name = getObjectName(['element' => 'adaccount', 'id' => $adaccount_id]);
    if (count($ans['resultado']['errores']) == 0) {
        $new = [
            "variables.adset.$index.id" => (string) $id,
            "variables.adset.$index.adaccount_id" => (string) $adaccount_id,
            "variables.adset.$index.parent_name" => $parent_name,
            "variables.adset.$index.data" => ""
        ];
        $mongo = new MYMONGODB();
        $u = $mongo->actualizar("boRedesConf", [ "type" => "facebook" ], $new);
        if ($u > 0) {
            //PROCEDE
        } else {
            $ans['resultado']['errores']['id'] = "Disculpe, ha ocurrido un error al almacenar la información, intente mas tarde";
        }
    }
    return $ans;
}

/**
 * Permite acortar un string si es que este es muy largo
 * @param string $str Cadena de caracteres que sera truncada
 * @param int $max maximo de caracteres
 * @return string Cadena truncada
 */
function shortener($str, $max) {
    if (strlen($str) >= $max) {
        $ans = substr($str, 0, $max).'...';
    } else {
        $ans = $str;
    }
    return $ans;
}

/**
 * Permite eliminar un registro de app
 * @param string $index indice de app en configuracion
 */
function deleteApp($index) {
    $mongo = new MYMONGODB();
    $mongo->buscar('boRedesConf', ['type' => 'facebook']);
    $ans = false;
    while ($result = $mongo->siguiente()) {
        $apps = $result['variables']['app'];
        if (isset($apps[$index])) {
            unset($apps[$index]);
            $apps = array_values($apps);
            $new_data = [ 'variables.app' => $apps ];
            $u = $mongo->actualizar('boRedesConf', ['type' => 'facebook'], $new_data);
            if ($u) {
                $ans = 1;
            } else {
                $ans = false;
            }
        }
    }
    return $ans;
}

/**
 * Permite eliminar un registro de empresa
 * @param string $index indice de app en configuracion
 */
function deleteBusiness($index) {
    $mongo = new MYMONGODB();
    $mongo->buscar('boRedesConf', ['type' => 'facebook']);
    $ans = false;
    while ($result = $mongo->siguiente()) {
        $business = $result['variables']['business'];
        if (isset($business[$index])) {
            unset($business[$index]);
            $business = array_values($business);
            $new_data = [ 'variables.business' => $business ];
            $u = $mongo->actualizar('boRedesConf', ['type' => 'facebook'], $new_data);
            if ($u) {
                $ans = 1;
            } else {
                $ans = false;
            }
        }
    }
    return $ans;
}

/**
 * Permite eliminar un registro de cuenta publicitaria
 * @param string $index indice de cuenta publicitaria en configuracion
 */
function deleteAdaccount($index) {
    $mongo = new MYMONGODB();
    $mongo->buscar('boRedesConf', ['type' => 'facebook']);
    $ans = false;
    while ($result = $mongo->siguiente()) {
        $adaccount = $result['variables']['adaccount'];
        if (isset($adaccount[$index])) {
            unset($adaccount[$index]);
            $adaccount = array_values($adaccount);
            $new_data = [ 'variables.adaccount' => $adaccount ];
            $u = $mongo->actualizar('boRedesConf', ['type' => 'facebook'], $new_data);
            if ($u) {
                $ans = 1;
            } else {
                $ans = false;
            }
        }
    }
    return $ans;
}

/**
 * Permite eliminar un registro de campaña
 * @param string $index indice de campaña en configuracion
 */
function deleteCampaign($index) {
    $mongo = new MYMONGODB();
    $mongo->buscar('boRedesConf', ['type' => 'facebook']);
    $ans = false;
    while ($result = $mongo->siguiente()) {
        $adaccount = $result['variables']['campaign'];
        if (isset($adaccount[$index])) {
            unset($adaccount[$index]);
            $adaccount = array_values($adaccount);
            $new_data = [ 'variables.campaign' => $adaccount ];
            $u = $mongo->actualizar('boRedesConf', ['type' => 'facebook'], $new_data);
            if ($u) {
                $ans = 1;
            } else {
                $ans = false;
            }
        }
    }
    return $ans;
}

/**
 * Permite eliminar un registro de conjunto de anuncios
 * @param string $index indice de conjunto de anuncios en configuracion
 */
function deleteAdset($index) {
    $mongo = new MYMONGODB();
    $mongo->buscar('boRedesConf', ['type' => 'facebook']);
    $ans = false;
    while ($result = $mongo->siguiente()) {
        $adaccount = $result['variables']['adset'];
        if (isset($adaccount[$index])) {
            unset($adaccount[$index]);
            $adaccount = array_values($adaccount);
            $new_data = [ 'variables.adset' => $adaccount ];
            $u = $mongo->actualizar('boRedesConf', ['type' => 'facebook'], $new_data);
            if ($u) {
                $ans = 1;
            } else {
                $ans = false;
            }
        }
    }
    return $ans;
}

//_FIN_DE_ARCHIVO