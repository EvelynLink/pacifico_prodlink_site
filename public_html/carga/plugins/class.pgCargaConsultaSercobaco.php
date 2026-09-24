<?php

// __PluginDescripcion__: Carga Masiva de Asignacion Call Center
require_once("../comunes/classes/class.clase.php");
require_once("../usuarios/classes/class.usuario.php");
require_once("../carga/classes/class.caCargaArchivos.php");
// require_once("../functions/sha256.inc.php");
require_once("../comunes/classes/class.mymongodb.php");

class pgCargaConsultaSercobaco extends Clase
{

    protected $tipo;

    function __construct()
    {
        global $lang, $cnf;
        $this->tipo = "ConsultaSercobaco";
        parent::__construct();
    }

    function procesaArchivo($filename)
    {

        require_once("../cobranza/apis/class.cbAPI.php");

        $miapi = new cbAPI();

        $mongo = new MYMONGODB();

        global $lang, $db;

        $retVal = "";

        $retVal .= 'Archivo procesado' . ":<br>" . $filename;
        $retVal .= "<br><br>";

        $definitive_dir = BASEFOLDER . "carga/files/";

        $datos = file($definitive_dir . $filename);

        $numReg = 0;
        $numGuardados = 0;
        $numExistentes = 0;
        $numProcesados = 0;




        foreach ($datos as $reg) {

            $clienteProcesado = false;
            $clienteCRM = false;

            $dat = explode(";", $reg);

            // evitar cabecera
            if (strpos(strtoupper(trim($dat[0])), 'CEDULA') !== false || strpos(strtoupper(trim($dat[0])), 'IDENTIFICACION') !== false) {
                continue;
            }

            $numReg++;

            $cedula = trim($dat[0]);

            // completar cédula si viene incompleta
            if (strlen($cedula) == 9 || strlen($cedula) == 12) {
                $cedula = '0' . $cedula;
            }

            //trigger_error("Consultando cédula: " . $cedula);

            // VALIDAR SI YA EXISTE EN MONGO
            $existeConsultaSercobaco = $mongo->buscar('cbConsultaSercobaco', array('cedula' => (string)$cedula));


            if ($existeConsultaSercobaco > 0) {

                //trigger_error("Ya existe en colección cbConsultaSercobaco -> " . $cedula);
                $clienteProcesado = false;
                $clienteCRM = true;
                $numExistentes++;
            } else {

                $existeCrm = $mongo->buscar('CRM', array('crm_cedula' => (string)$cedula));

                if ($existeCrm > 0) {
                    $clienteCRM = true;
                    //trigger_error("si existe en crm");

                    // CONSUMIR API

                     $intentos = 0;
                    $maxIntentos = 2;

                    $bd_datos = null;
                    $objDecodificado = null;

                    while ($intentos < $maxIntentos) {

                        $bd_datos = $miapi->api_consultaSercobaco(
                            'coreSisbak',
                            $cedula,
                            'WEB'
                        );

                        if (is_string($bd_datos) && $bd_datos !== '') {

                            // formato clasico: string en base64
                            $decoded = base64_decode($bd_datos, true);

                            if ($decoded !== false) {
                                $tmp = json_decode($decoded);
                                if ($tmp !== null && json_last_error() === JSON_ERROR_NONE) {
                                    $objDecodificado = $tmp;
                                    break;
                                }
                            }
                        } elseif (is_object($bd_datos) || is_array($bd_datos)) {

                            // el API a veces ya devuelve el objeto/array decodificado directamente
                            $objDecodificado = is_array($bd_datos) ? json_decode(json_encode($bd_datos)) : $bd_datos;
                            break;
                        }

                        $intentos++;

                        sleep(1);
                    }

                    if (!$objDecodificado || !isset($objDecodificado->d_datos)) {

                        $detalle = is_string($bd_datos) ? $bd_datos : json_encode($bd_datos);

                        trigger_error("API SIN RESPUESTA VALIDA -> " . $cedula . " | Respuesta: " . $detalle);

                        continue;
                    }

                    $array = [$objDecodificado];

                    $Familiar = $dd = $TelVarios = array();

                    //mapeo de datos personales

                    $dd["CEDULA"] = $array[0]->d_datos->d_cedula;
                    $dd["NOMBRES"] = mb_convert_encoding($array[0]->d_datos->d_nombre, 'ISO-8859-1', 'UTF-8');
                    $dd["SEXO"] = $array[0]->d_datos->d_sexo;
                    $dd["CIUDADANIA"] = $array[0]->d_datos->d_tipo;
                    $dd["FECHA_NACI"] = $array[0]->d_datos->d_fecnac;
                    $dd["NACIONALID"] = $array[0]->d_datos->d_nacionalidad;
                    $dd["ESTADO_CIV"] = $array[0]->d_datos->d_estadocivil;
                    $dd["NOMBRE_CON"] = mb_convert_encoding($array[0]->d_datos->d_conyuge, 'ISO-8859-1', 'UTF-8');
                    $dd["CEDULA_CON"] = $array[0]->d_datos->d_cedconyuge;
                    $dd["NOMBRE_MAD"] = mb_convert_encoding($array[0]->d_datos->d_nombremadre, 'ISO-8859-1', 'UTF-8');
                    $dd["CEDULA_MAD"] = $array[0]->d_datos->d_cedmadre;
                    $dd["NOMBRE_PAD"] = mb_convert_encoding($array[0]->d_datos->d_nombrepadre, 'ISO-8859-1', 'UTF-8');
                    $dd["CEDULA_PAD"] = $array[0]->d_datos->d_cedpadre;

                    $dd["DIRECCIONES"] = [];
                    if (isset($array[0]->d_direcciones) && is_array($array[0]->d_direcciones)) {
                        foreach ($array[0]->d_direcciones as $val) {
                            $dd["DIRECCIONES"][] = strtoupper(str_replace('||', ', ', $val->direccion));
                        }


                        $direccionArr = $direccion = [];
                        foreach (explode('||', $array[0]->d_direcciones[0]->direccion) as $n) {
                            if ($n != '') {
                                if (!in_array($n, $direccionArr)) {
                                    $direccion[] = $n;
                                }
                                $direccionArr[] = $n;
                            }
                        }
                    }

                    $dd["CORREO"] = explode(' ', $array[0]->d_emails[0]->email)[0];
                    $emails = [];
                    $j = 0;

                    foreach (explode('||', $array[0]->d_emails[0]->email) as $n) {

                        $correo = explode(' ', trim($n))[0];

                        if ($correo != '') {

                            $existe = 0;

                            $coleccion = "cbEmail";
                            $campos = ['mail_cedula', 'mail_email'];

                            if (!mb_check_encoding($correo, 'UTF-8')) {
                                $correo = mb_convert_encoding($correo, 'UTF-8', 'ISO-8859-1');
                            }
                            $condition = array('mail_cedula' => (string)$dd["CEDULA"], 'mail_email' => (string)$correo);
                            $buscar = $mongo->buscar($coleccion, $condition, $campos);

                            if ($buscar > 0) {
                                $existe = 1;
                            }

                            $emails[$j]["EMAIL"] = $correo;
                            $emails[$j]["REGISTRADO"] = $existe;

                            $j++;
                        }
                    }

                    if (isset($array[0]->d_empleos) && is_array($array[0]->d_empleos) && isset($array[0]->d_empleos[0])) {

                        $dd["OCUPACION"] = $array[0]->d_empleos[0]->d_emp_ocupacion;
                        $dd["TELVARIOS"] = $array[0]->d_empleos[0]->d_emp_telefono;
                        $dd["NOMBREEMP"] = mb_convert_encoding($array[0]->d_empleos[0]->d_emp_nombre, 'ISO-8859-1', 'UTF-8');
                        $dd["RUCEMP"] = $array[0]->d_empleos[0]->d_emp_ruc;
                        $dd["SALARIO"] = $array[0]->d_empleos[0]->d_emp_sueldo;
                        $dd["DIRAFI"] = mb_convert_encoding($array[0]->d_empleos[0]->d_emp_direccion, 'ISO-8859-1', 'UTF-8');
                        $dd["FECINGAFI"] = $array[0]->d_empleos[0]->d_emp_fecingreso;
                    } else {
                        $dd["OCUPACION"] = "";
                        $dd["TELVARIOS"] = "";
                        $dd["NOMBREEMP"] = "";
                        $dd["RUCEMP"] = "";
                        $dd["SALARIO"] = "";
                        $dd["DIRAFI"] = "";
                        $dd["FECINGAFI"] = "";
                    }

                    $i = 0;
                    if (isset($array[0]->d_hijos) && is_array($array[0]->d_hijos)) {
                        foreach ($array[0]->d_hijos as $n) {
                            $Familiar[$i]["CEDULA"] = $n->h_cedula;
                            $Familiar[$i]["NOMBRES"] = $n->h_nombre;
                            $Familiar[$i]["PARENTESCO"] = "HIJO(A)";
                            $i++;
                        }
                    }

                    /****Se aumenta BUSQUEDA TELEFONOS, para que muestre si el teléfono ya está registrado o no******/
                    $coleccion = "CRM";
                    $campos = ['crm_cedula', 'usUsuarios_id', 'crm_telefonos'];
                    $condition = array('crm_cedula' => (string)$dd["CEDULA"]);
                    $mongo->buscar($coleccion, $condition, $campos);
                    $documento = $mongo->siguiente();



                    if (
                        isset($array[0]->d_telefonos) && is_array($array[0]->d_telefonos) && isset($array[0]->d_telefonos[0]) &&
                        isset($array[0]->d_telefonos[0]->telefono)
                    ) {
                        $temp = explode("||", $array[0]->d_telefonos[0]->telefono);
                        $i = 0;
                        foreach ($temp as $n) {
                            if ($n != '') {
                                $TelVarios[$i]["PERTENECE"] = "TITULAR";
                                $TelVarios[$i]["ORIGEN"] = "FICHA";
                                $TelVarios[$i]["TELEFONO"] = explode(' ', $n)[0];
                                $existe = 0;

                                $numero = expect_phone_EC($TelVarios[$i]["TELEFONO"]);

                                if ($numero && count($numero) > 0) {


                                    $sql = $db->mkSQL("
                                    SELECT * 
                                    FROM ustelfs
                                    WHERE usTelfs_area=%Q
                                    AND usTelfs_telefono=%Q
                                    AND usTelfs_relId=%N
                                ", $numero[0], $numero[1], $documento['usUsuarios_id']);

                                    if ($db->query($sql)) {
                                        $existe = 1;
                                    }
                                }

                                //Se aumenta para que se muestre la opción registrar teléfonos

                                $TelVarios[$i]["CALLCENTER"] = $existe;



                                $i++;
                            }
                        }
                    }

                    if (
                        isset($array[0]->d_celulares) && is_array($array[0]->d_celulares) && isset($array[0]->d_celulares[0]) &&
                        isset($array[0]->d_celulares[0]->celular)
                    ) {

                        $temp2 = explode("||", $array[0]->d_celulares[0]->celular);
                        foreach ($temp2 as $n) {
                            if ($n != '') {
                                $TelVarios[$i]["PERTENECE"] = "TITULAR";
                                $TelVarios[$i]["ORIGEN"] = "FICHA";
                                $TelVarios[$i]["TELEFONO"] = explode(' ', $n)[0];
                                $existe = 0;

                                $numero = expect_phone_EC($TelVarios[$i]["TELEFONO"]);

                                if ($numero && count($numero) > 0) {

                                    $sql = $db->mkSQL("
                                    SELECT * 
                                    FROM ustelfs
                                    WHERE usTelfs_area=%Q
                                    AND usTelfs_telefono=%Q
                                    AND usTelfs_relId=%N
                                ", $numero[0], $numero[1], $documento['usUsuarios_id']);

                                    if ($db->query($sql)) {
                                        $existe = 1;
                                    }
                                }

                                //Se aumenta para que se muestre la opción registrar teléfonos

                                $TelVarios[$i]["CALLCENTER"] = $existe;
                                $i++;
                            }
                        }
                    }

                    $veh = [];
                    if (isset($array[0]->d_vehiculos) && is_array($array[0]->d_vehiculos)) {
                        foreach ($array[0]->d_vehiculos as $val) {
                            if (trim($val->d_vehiculo) != '') {
                                $veh[] = strtoupper(str_replace('Anio', 'Año', $val->d_vehiculo));
                            }
                        }
                    }

                    $lic = [];
                    if (isset($array[0]->d_licencias)) {
                        $lic[] = [
                            'tiposangre' => $array[0]->d_licencias->d_tiposangre,
                            'tipo' => $array[0]->d_licencias->d_licencia,
                            'fechaEmision' => explode(' ', $array[0]->d_licencias->d_fechaemision)[0],
                            'fechaExpiracion' => '',
                        ];
                    }

                    $inmueble = [];
                    if (isset($array[0]->d_predios) && is_array($array[0]->d_predios)) {
                        foreach ($array[0]->d_predios as $val) {
                            if (trim($val->d_predio) != '') {
                                $inmueble[] = $val->d_predio;
                            }
                        }
                    }

                    // GUARDAR EN MONGO

                    $objSercobaco = [
                        "cedula" => (string)$cedula,
                        "datos_personales" => $dd,
                        "direcciones" => $direccion,
                        "familiares" => $Familiar,
                        "telefonos" => $TelVarios,
                        "vehiculos" => $veh,
                        "licencias" => $lic,
                        "inmuebles" => $inmueble,
                        "emails" => $emails,
                        "activo" => (int)1,
                        "fechaCreacion" => time(),
                        "origen" => "Sercobaco",
                        "fechaConsulta" => time()
                    ];

                    $mongo->guardar("cbConsultaSercobaco", $objSercobaco);
                    $clienteProcesado = true;


                    $numGuardados++;
                } else {
                    $clienteCRM = false;
                    print_h((string)$cedula . ' -> Cliente no existe en CRM ');
                }
            }

            // Guardar teléfonos

            $mongo->buscar('cbConsultaSercobaco', array('cedula' => (string)$cedula));
            $sercobaco = $mongo->siguiente();
            //Guardar teléfonos
            if (isset($sercobaco['telefonos']) && is_array($sercobaco['telefonos'])) {
                foreach ($sercobaco['telefonos'] as $telefono) {
                    if (isset($telefono['CALLCENTER']) && intval($telefono['CALLCENTER']) == 0) {
                        $this->registrarTel($cedula, $telefono['TELEFONO'], $telefono['PERTENECE']);
                        $clienteProcesado = true;
                    }
                }
            }

            //Guardar emails
            if (isset($sercobaco['emails']) && is_array($sercobaco['emails'])) {

                foreach ($sercobaco['emails'] as $email) {

                    if (isset($email['REGISTRADO']) && intval($email['REGISTRADO']) == 0) {
                        $this->registrarEmail($cedula, $email['EMAIL']);
                        $clienteProcesado = true;
                    }
                }
            }

            if ($clienteProcesado) {
                $numProcesados++;
                print_h($cedula . ' -> Cliente procesado correctamente ');
            } else {
                if ($clienteCRM) {
                    print_h($cedula . ' -> Cliente ya registrado ');
                }
            }
        }

        print_h('Total Consultas API Sercobaco: ' . $numGuardados);
        print_h('Total Existentes en cbConsultaSercobaco: ' . $numExistentes);

        print_h('Total Registros: ' . $numReg);
        print_h('Total Procesadas: ' . $numProcesados);

        $archivoErrores = strtr(str_replace(" ", "_", $filename), "áéíóúüñÁÉÍOÚÜÑ-.,'\":;\\<>?/`~!@#$%^&*()+=[]", "aeiouunAEIOUUN__________________________________");
        $archivoErrores = time() . "." . $archivoErrores . ".txt";

        $fp = fopen(BASEFOLDER . "carga/files/" . $archivoErrores, "w+");

        $respuestaVal = str_replace("<br>", "\n", $retVal);

        fwrite($fp, $respuestaVal);

        fclose($fp);

        $arrDatos = array();

        $arrDatos['nombre'] = $filename;
        $arrDatos['tipo'] = $this->tipo;
        $arrDatos['ruta'] = "../carga/files/";
        $arrDatos['bitacError'] = $archivoErrores;
        $arrDatos['registros'] = $numReg;

        $archivoCarga = new caCargaArchivos();

        $archivoCarga->insertar($arrDatos);

        return encodedEnd($retVal);
    }


    function registrarTel($ci, $telefono, $obs = '')
    {
        $mongo = new MYMONGODB();

        $json = array();

        $lasNotas = trim(substr(strtoupper($obs), 0, 200));

        $numero = expect_phone_EC($telefono);

        if (!$numero || count($numero) == 0) {

            trigger_error("Telefono invalido -> " . $telefono . ", CI -> " . $ci);

            return false;
        }

        $collecion = 'CRM';
        $criterioAccion = array("crm_cedula" => (string)$ci);
        $cursor = $mongo->buscar($collecion, $criterioAccion);
        $documento = $mongo->siguiente();

        if (!$documento) {
            trigger_error("CRM NO EXISTE -> " . $ci);
            return false;
        }

        eval('$db=new ' . DB1 . 'DB();');
        if (
            !$db->query(
                $db->mkSQL(
                    "SELECT * FROM ustelfs  WHERE usTelfs_area=%Q AND usTelfs_telefono=%Q AND usTelfs_relId=%N",
                    $numero[0],
                    $numero[1],
                    $documento['usUsuarios_id']
                )
            )
        ) {
            // inserta
            $idTel = $db->query(
                $db->mkSQL(
                    "INSERT INTO ustelfs
                    (usTelfs_relId, usTelfs_relTable, usTelfs_area, usTelfs_telefono, usTelfs_comentario) VALUES
                    (%N, %Q, %Q, %Q, %Q)",
                    $documento['usUsuarios_id'],
                    "ususuarios",
                    $numero[0],
                    $numero[1],
                    trim(substr($lasNotas, 0, 199))
                )
            );
        } else {

            $row = $db->fetchRow();
            $idTel = $row['usTelfs_id'];
        }

        $tipo = 'Fijo';

        if (intval($numero[0]) == 9) {
            $tipo = 'Movil';
        }

        if (is_numeric($idTel)) {
            $telefonosaGuardarUsuarios = array(
                'tel_id' => (int)intval($idTel),
                'tel_numero' => $numero[0] . $numero[1],
                "tel_tipo" => $tipo,
                "tel_observacion" => $lasNotas,
                "tel_titular" => (int)1,
                "tel_equivocado" => (int)0,
                "tel_eliminado" => (int)0,
                "tel_orden" => time() . substr(microtime(), 2, 8),
                "tel_usUsuario_id_bdds" => (int)intval($_SESSION[MID . "userId"]),
                "tel_fecha_bdds" => time()
            );

            $misTelefonos = (isset($documento["crm_telefonos"]) && is_array($documento["crm_telefonos"]))
                ? $documento["crm_telefonos"] : [];
            array_push($misTelefonos, $telefonosaGuardarUsuarios);
            $nuevoTelefonos = array("crm_telefonos" => $misTelefonos);
            $json['resultado'] = $mongo->actualizar($collecion, $criterioAccion, $nuevoTelefonos, true);

            $a = array('tel_cedula' => (string)$ci);
            $b = array_merge($a, $telefonosaGuardarUsuarios);

            //Guardar en cbTelefonos

            $existeTelefono = $mongo->buscar(
                'cbTelefonos',
                array('tel_cedula' => (string)$ci, 'tel_numero' => $numero[0] . $numero[1])
            );

            if ($existeTelefono <= 0) {

                $a = array('tel_cedula' => (string)$ci);
                $b = array_merge($a, $telefonosaGuardarUsuarios);

                $mongo->guardar('cbTelefonos', $b);
            }

            //trigger_error("TELEFONO REGISTRADO -> " . $ci . " - " . $telefono);

            return true;
        }

        return false;
    }

    function registrarEmail($ci, $email)
    {
        $mongo = new MYMONGODB();

        $email = trim(strtolower($email));

        // VALIDAR SI YA EXISTE
        $collecion = 'cbEmail';
        //Se convierte email antes de la búsqueda a UTF-8
        $email = trim(strtolower($email));

        if (!mb_check_encoding($email, 'UTF-8')) {
            $email = mb_convert_encoding($email, 'UTF-8', 'ISO-8859-1');
        }
        $criterioAccion = array("mail_cedula" => (string)$ci, "mail_email" => (string)$email);

        $cursor = $mongo->buscar($collecion, $criterioAccion);

        if ($cursor <= 0) {

            // OBJETO EMAIL
            $objEmail = [
                "mail_cedula" => (string)$ci,
                "mail_email" => (string)$email,
                "mail_observacion" => "",
                "mail_usUsuario_id" => (int)intval($_SESSION[MID . "userId"]),
                "mail_fecha" => time(),
                "mail_cedulaReferido" => "",
                "mail_tipoCarga" => "",
                "mail_tipoReferenciaPersona" => "",
            ];

            // GUARDAR
            $mongo->guardar("cbEmail", $objEmail);

            //trigger_error("EMAIL REGISTRADO -> " . $ci . " - " . $email);
        }

        return true;
    }
}

?>
<?

//_FIN_DE_ARCHIVO 
?>