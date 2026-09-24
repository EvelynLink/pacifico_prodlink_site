<?

/** Interacción entre controlador de boletines y clase que opera API Marketing de Facebook
 *  @package Boletines
 *  @author Mikhael Portela ( mikhael.portela [arroba] espaciolink [punto] com)
 */

// <editor-fold defaultstate="collapsed" desc="REQUIRES TO SISBAK FRAMEWORK">
require_once("../comunes/classes/class.clase.php");
require_once("../comunes/classes/class.mymongodb.php");
//require_once("../networkAds/classes/class.ntfacebook.php");
require_once("../boletines/classes/class.boBitacoraRedes.php");
// </editor-fold>

class boRedesConf extends Clase {

    protected $audienceName = "sisbak audience";
    protected $audienceDescription = "Default audience for sisbak operations";
    protected $app_id;
    protected $secret;
    protected $version;
    protected $token;
    protected $facebook;
            
    function __construct($config = false) {
        parent::init("boredesConf", "boRedesConf_");
        if ($config) {
            $parameters = $this->deployConfig($config, 'adset');
            if (isset($parameters['adaccount']) && $parameters['adaccount'] != "") {
                $parameters['adaccount'] = "act_".$parameters['adaccount'];
                $this->app_id = $parameters['id'];
                $this->secret = $parameters['secret'];
                $this->version = $parameters['version'];
                $this->token = $parameters['token'];
                $this->facebook = new ntfacebook($this->app_id, $this->secret, $this->version, $this->token);
            }
        }
    }

    public function getConfig() {
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook" ]);
        $ans = [];
        
        while ($result = $mongo->siguiente()) {
            //Informacion de Aplicaciones
            $adsets = $result['variables']['adset'];
            $campaigns = $result['variables']['campaign'];
            foreach ($campaigns as $campaign_key => $campaign_values) {
                if ($campaign_values['connected'] == true || $campaign_values['connected'] == "true") {
                    foreach($adsets as $adset_key => $adset_values) {
                        if ($adset_values['connected'] == true || $adset_values['connected'] == "true") {
                            if (isset($adset_values['data']['campaign_id']) && isset($adset_values['data']['name']) && isset($campaign_values['data']['name'])) {
                                if ($adset_values['data']['campaign_id'] == $campaign_values['id']) {
                                    //Debe estar conectada y tener una relacion con el campaign en curso
                                    $ans[] = [
                                        'id' => $adset_values['id'],
                                        'descripcion' => $adset_values['data']['name'],
                                        'grupo' => $campaign_values['data']['name']
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        return $ans;
    }
    
    /**
     * Permite crear una publicacion convencional en facebook
     * @param string $name Nombre del AD
     * @param string $image URL de imagen en servidor
     * @param array $people Listado con multiples campos
     * @param string|int $config ID de AdSet en configuracion facebook
     * @param array $ad_datos [titulo, descripcion, enlace]
     * @return array
     */
    public function publicarEnFacebook($image, $people, $config, $ad_datos) {
        //Default answer
        $ans = [ 'op' => false, 'message' => '' ];
        $error_to_trigger = "";
        $parameters = $this->deployConfig($config, 'adset');
        
        if (isset($parameters['adaccount']) && $parameters['adaccount'] != "") {
            $parameters['adaccount'] = "act_".$parameters['adaccount'];
            $facebook = new ntfacebook($parameters['id'], $parameters['secret'], $parameters['version'], $parameters['token']);
            //Creacion de audiencia en blanco
            $new_audience = $facebook->createAudience($this->audienceName, $this->audienceDescription, $parameters['adaccount']);
            if ($new_audience) {
                //trigger_error('Track: blank audience created: '.$new_audience);
                //Agregacion de usuarios a la audiencia
                $add_result = $facebook->addPeopleAudience($new_audience, $people);
                if (isset($add_result['num_received']) && isset($add_result['num_invalid_entries'])) {
                    if ($add_result['num_received'] > $add_result['num_invalid_entries']) {
                        //trigger_error('Track: adding people to audience: recived('.$add_result['num_received'].') invalids('.$add_result['num_invalid_entries'].')');
                        //Creacion de imagen para obtencion del HASH
                        $new_image = $facebook->uploadImage($image, $parameters['adaccount']);
                        if ($new_image) {
                            //trigger_error('Track: image created: '.$new_image);
                            $temp_name = 'Default sisbak creative';
//                            $temp_title = 'Sisbak Cobranzas Inteligentes 2';
//                            $temp_body = 'Descuentos y oportunidades para liquidacion de deudas en entidades financieras 2';
//                            $temp_object_url = 'http://cnthistorico.espaciolink.com';
//                            $temp_link_url = 'http://cnthistorico.espaciolink.com';
                            $temp_title = $ad_datos['titulo'];
                            $temp_body = $ad_datos['descripcion'];
                            $temp_object_url = $ad_datos['enlace'];
                            $temp_link_url = $ad_datos['enlace'];
                            //Creacion del creative
                            $new_creative = $facebook->createCreative($temp_name, $temp_title, $temp_body, $temp_object_url, $temp_link_url, $new_image, $parameters['adaccount']);
                            if ($new_creative) {
                                //trigger_error('Track: creative created: '.$new_creative);
                                //Edicion de Adset
                                $adset_up = $facebook->setAudienceToAdset($config, $new_audience);
                                if ($adset_up) {
                                    //trigger_error('Track: updated adset with audience');
                                    //Creacion de AD
                                    $new_ad = $facebook->createAd($temp_title, $parameters['adaccount'], $config, $new_creative);
                                    if ($new_ad) {
                                        //trigger_error('Track: ad created: '.$new_ad);
                                        $ans = [ 'op' => true, 'message' => $new_ad ];
                                        //Actualiza en bitacora el ID de AD
                                    } else {
                                        //Error al crear AD
                                        $error_to_trigger = "Error: can't create final AD";
                                        $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                                    }
                                } else {
                                    //Error al editar Adset
                                    $error_to_trigger = "Error: can't update preset AdSet";
                                    $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                                }
                            } else {
                                //Error en creacion de creative
                                $error_to_trigger = "Error: can't create image creative";
                                $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                            }
                        } else {
                            //Error al crear imagen en facebook (obtencion de Hash)
                            $error_to_trigger = "Error: can't upload image to get hash";
                            $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                        }
                    } else {
                        //Error al agregar personas a la audiencia
                        $error_to_trigger = "Error: can't add people to blank audience1 [recived(".$add_result['num_received'].")  invalids(".$add_result['num_invalid_entries'].")]";
                        $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                    }
                } else {
                    //Error al agregar personas a la audiencia
                    $error_to_trigger = "Error: can't add people to blank audience2";
                    $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
                }
            } else {
                //Error creando la audiencia en blanco
                $error_to_trigger = "Error: can't create blank audience";
                $ans['message'] = "Disculpe, en este momento no podemos procesar su solicitud, intente mas tarde";
            }
        } else {
            //No hay cuenta publicitaria conectada
            $error_to_trigger = "Error: can't connect to Ad Account with this credentials";
            $ans['message'] = "Disculpe, no hay cuenta publicitaria conectada";
        }
        if (!$ans['op']) {
            trigger_error($error_to_trigger);
        }
        return $ans;
    }
    
    /**
     * Permite obtener los datos "data" en una configuracion especifica desde MongoDB
     * @param string $id Identificador de objeto
     * @param string $who Nombre de objeto app|business|adaccount|campaign|adset
     */
    public function getDataConfig($id, $who) {
        //Default answer
        $ans = [ 'op' => false, 'data' => [] ];
        //Unique consult to facebook configuration mongo
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook"]);
        while ($result = $mongo->siguiente()) {
            $compound = $result['variables'];
            foreach ($compound as $key => $value) {
                if ($key == $who) {
                    foreach($value as $key2 => $value2) {
                        if ($id == $value2['id']) {
                            $ans = [ 'op' => true, 'data' => $value2['data'] ];
                            break 3;
                        }
                    }
                }
            }
        }
        return $ans;
    }
    
    /**
     * Permite navegar entre la configuracion para ubicar parametros de conexion sin importar desde que nivel se solicite
     * @param string $id Identificador de objeto
     * @param string $who Nombre de objeto app|business|adaccount|campaign|adset
     * @return array Estructura de respuesta preestablecida
     */
    public function deployConfig($id, $who) {
        //Default answer
        $ans = [ 'id' => '', 'secret' => '', 'version' => '', 'token' => '', 'business' => '', 'adaccount' => '', 'campaign' => '', 'adset' => ''];
        if (isset($ans[$who])) {
            $ans[$who] = $id;
        }
        $old_who = $who;
        $gotit = false;
        //Unique consult to facebook configuration mongo
        $mongo = new MYMONGODB();
        $mongo->buscar("boRedesConf", [ "type" => "facebook"]);
        $compound = [];
        while ($result = $mongo->siguiente()) {
            $compound = $result['variables'];
        }
        if (count($compound) > 0) {
            $error_control = 0;
            do {
                $get_one = self::findOneConfig($id, $who, $compound);
                //ERROR CONTROL FOR INFINITY BUCLE
                if ($error_control > 0 && $old_who == $who) {
                    //trigger_error('There is an issue with Facebook configuration1');
                    break;
                }
                $old_who = $who;
                if (isset($get_one['token'])) {
                    $gotit = true;
                    $ans['id'] = $get_one['id'];
                    $ans['secret'] = $get_one['app_secret'];
                    $ans['version'] = $get_one['default_graph_version'];
                    $ans['token'] = $get_one['token'];
                } else {
                    $keys = array_keys($get_one);
                    foreach ($keys as $val) {
                        if (strpos($val, "_id") || strpos($val, "_id") > 0) {
                            $key_parts = explode("_", $val);
                            $id = $get_one[$val];
                            //Update the ID of repective object
                            if (isset($ans[$key_parts[0]])) {
                                $ans[$key_parts[0]] = $get_one[$val];
                            }
                            if ($key_parts[0] != $who) {
                                $who = $key_parts[0];
                            } else {
                                //trigger_error('There is an issue with Facebook configuration2');
                                break;
                            }
                        }
                    }
                }
                $error_control++;
            } while (!$gotit);
        } else {
            //trigger_error('There is not Facebook configuration');
        }
        return $ans;
    }
    
    private function findOneConfig($id, $who, $compound) {
        $ar = [];
        foreach ($compound as $key => $value) {
            if ($key == $who) {
                foreach($value as $parameters) {
                    if ($parameters['id'] == $id) {
                        $ar = $parameters;
                    }
                }
            }
        }
        return $ar;
    }
    
    /**
     * Permite obtener datos estadisticos de un AD
     * @param string $ad_id ID Facebook del AD publicado
     * @param string $config ID Facebook de AdSet con configuracion (ID Config)
     * @param string $filter Filtro para insights
     * @return array Estructura de respuesta preestablecida (op : procede, data : datos)
     */
    public function getInsights($ad_id, $config, $filter) {
        //Default answer
        $ans = [ 'op' => false, 'message' => '' ];
        $error_to_trigger = "";
        $parameters = $this->deployConfig($config, 'adset');
        
        if (isset($parameters['adaccount']) && $parameters['adaccount'] != "") {
            $parameters['adaccount'] = "act_".$parameters['adaccount'];
            $facebook = new ntfacebook($parameters['id'], $parameters['secret'], $parameters['version'], $parameters['token']);
            $insight = $facebook->adInsights($ad_id, $filter);
            if ($insight['op']) {
                //Busca nombres de objetos
                $formated = $insight['data'];
                if (isset($formated['ad_info']['adaccount'])) {
                    $temp_objet = $this->getDataConfig($formated['ad_info']['adaccount'], "adaccount");
                    $formated['ad_info']['adaccount'] = (isset($temp_objet['data']['name'])) ? $temp_objet['data']['name'] : $formated['ad_info']['adaccount'];
                }
                if (isset($formated['ad_info']['campaign'])) {
                    $temp_objet = $this->getDataConfig($formated['ad_info']['campaign'], "campaign");
                    $formated['ad_info']['campaign'] = (isset($temp_objet['data']['name'])) ? $temp_objet['data']['name'] : $formated['ad_info']['campaign'];
                }
                if (isset($formated['ad_info']['adset'])) {
                    $temp_objet = $this->getDataConfig($formated['ad_info']['adset'], "adset");
                    $formated['ad_info']['adset'] = (isset($temp_objet['data']['name'])) ? $temp_objet['data']['name'] : $formated['ad_info']['adset'];
                }
                $ans = [ 'op' => true, 'message' => $formated ];
            }
        }
        return $ans;
    }
    
    /**
     * Permite obtener preview de un AD segun formato de AD
     * @param string $ad_id ID Facebook del AD publicado
     * @param string $filter Formato de AD para preview
     * @return array Estructura de respuesta preestablecida (op : procede, data : datos)
     */
    public function getPreview($ad_id, $filter) {
        //Default answer
        $ans = [ 'op' => false, 'data' => '' ];
        $error = false;
        try {
            if (method_exists($this->facebook,'adPreview')) {
                $preview = $this->facebook->adPreview($ad_id, $filter);
            } else {
                throw new Exception('Error en clase local(getPreview): Metodo inexistente');
            }
        } catch (Exception $e) {
            trigger_error('Error en clase local(getPreview): ' . $e->getMessage());
            $error = true;
        }
        if (!$error) {
            if ($preview['op']) {
                $ans = [ 'op' => true, 'data' => $preview['data'] ];
            }
        }
        return $ans;
    }
    
    /**
     * Permite obtener informacion de un AD particular
     * @param string $ad_id ID Facebook del AD publicado
     * @param string $config ID Facebook de AdSet con configuracion (ID Config)
     * @return array Estructura de respuesta preestablecida (op : procede, data : datos)
     */
    public function getAd($ad_id, $config, $onlyStatus = false) {
        //Default answer
        $ans = [ 'op' => false, 'data' => '' ];
        $parameters = $this->deployConfig($config, 'adset');
        if (isset($parameters['adaccount']) && $parameters['adaccount'] != "") {
            $parameters['adaccount'] = "act_".$parameters['adaccount'];
            $facebook = new ntfacebook($parameters['id'], $parameters['secret'], $parameters['version'], $parameters['token']);
            $ad = $facebook->ad($ad_id, $onlyStatus);
            if ($ad['op']) {
                $ans = [ 'op' => true, 'data' => $ad['data'] ];
            }
        }
        return $ans;
    }
    
    /**
     * Permite cambiar el status de un AD publicado en Facebook
     * @param string $ad_id ID Facebook del AD publicado
     * @param string $status Status en Ingles para actualizacion en Facebook
     * @return array Estructura de respuesta preestablecida (op : procede, data : string con estado traducido)
     */
    public function changeAdStatus($ad_id, $status, $bitacoraId) {
        //Default answer
        $translated_status = "";
        $ans = [ 'op' => false, 'data' => '' ];
        $par = [
            'ACTIVE' => 'ACTIVO',
            'PAUSED' => 'PAUSADO',
            'DELETED' => 'ELIMINADO',
            'PENDING_REVIEW' => 'PENDIENTE POR REVISION',
            'DISAPPROVED' => 'DESAPROVADO',
            'PREAPPROVED' => 'PREAPROVADO',
            'PENDING_BILLING_INFO' => 'PENDIENTE POR INFO. FACTURACIÓN',
            'CAMPAIGN_PAUSED' => 'CAMPAÑA EN PAUSA',
            'ARCHIVED' => 'ARCHIVADO',
            'ADSET_PAUSED' => 'CONJUNTO EN PAUSA'
        ];
        //Spanish to English
        $par = array_flip($par);
        if (array_key_exists($status, $par)) {
            $translated_status = $par[$status];
            $ad = $this->facebook->changeAdStatus($ad_id, $translated_status);
            if ($ad['op']) {
                //English to Spanish
                $par = array_flip($par);
                if (array_key_exists($ad['data'], $par)) {
                    $translated_status = $par[$ad['data']];
                    $ans = [ 'op' => true, 'data' => $translated_status ];
                    //Actualizacion en Bitacora de Boletines
                    $boBitacoraRedes = new boBitacoraRedes();
                    $boBitacoraRedes->actualizaEstadoEnvio($translated_status, $bitacoraId);
                } else {
                    trigger_error('Error en clase local, traduccion de estados de AD(1)');
                }
            }
        } else {
            trigger_error('Error en clase local, traduccion de estados de AD(2)');
        }
        return $ans;
    }
}
?><? //_FIN_DE_ARCHIVO  ?>
