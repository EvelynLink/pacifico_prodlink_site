<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGgestionVentas extends AbstractCuboPlugin
{
    public const COLLECTION_CUBO = 'cuGestionVentas';
    protected function process($ids, $accion): void
    {
        if (!is_array($ids) || empty($ids)) return;
        $tablas = $this->organizeByTable($ids);
        $db     = new MYSQLDB();
        foreach ($tablas as $tabla => $regIds) {
            $config = $this->getConfigByTabla($tabla);
            if (!$config) continue;
            switch ($accion) {
                case 'ADD':
                    foreach ($regIds as $id) {
                        //Busco carteras de ventas
                        $sql = 'SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo="VENTAS"';
                        $result = $db->query($sql);

                        $carterasVentas = [];
                        while ($row = $db->fetchRow($result)) {
                            $carterasVentas[] = (int)$row['cobCartera_id'];
                        }

                        $mdbOrigen = new MYMONGODB();
                        $condOrigen = [
                            '_id' => new MongoDB\BSON\ObjectId($id),
                            $config['cartera'] => ['$in' => $carterasVentas]
                        ];
                        if ($config['tipificacion']) {
                            $condOrigen[$config['tipificacion']] = ['$nin' => [null, '']];
                        }
                        // SOLO para emails
                        if ($tabla === 'cbEnvioMails') {
                            $condOrigen['cem_susErrorEnvio'] = 0;
                        }

                        $mdbOrigen->buscar($tabla, $condOrigen, $config['campos'], [], 1);
                        while ($doc = $mdbOrigen->siguiente()) {
                            $factura = $doc[$config['factura']] ?? null;
                            $cartera = $doc[$config['cartera']] ?? null;
                            $idGestion = $doc['_id'] ?? null;
                            $mdbCred = new MYMONGODB();
                            $condCred = [
                                'cre_factura' => (string)$factura,
                                'cre_carteraId' => (string)$cartera
                            ];
                            $mdbCred->buscar('cbCreditos', $condCred, [], [], 1);
                            while ($row = $mdbCred->siguiente()) {
                                $this->createData([
                                    'row'       => $row,
                                    'idGestion' => $idGestion,
                                    'tabla' => $tabla,
                                ]);
                            }
                        }
                    }
                    break;
                default:
                    trigger_error("Acción no reconocida {$accion}");
                    break;
            }
        }
    }

    protected function getConfigByTabla(string $tabla): ?array
    {
        switch ($tabla) {
            case 'avProgramadas':
                return [
                    'factura'      => 'av_factura',
                    'cartera'      => 'av_carteraId',
                    'tipificacion' => 'av_tipificacion.respuesta2',
                    'campos'       => [
                        '_id',
                        'av_factura',
                        'av_carteraId'
                    ]
                ];
            case 'avProgramadasWhatsApp':
                return [
                    'factura'      => 'ws_factura',
                    'cartera'      => 'ws_carteraId',
                    'tipificacion' => 'ws_tipificacion.respuesta2',
                    'campos'       => [
                        '_id',
                        'ws_factura',
                        'ws_carteraId'
                    ]
                ];
            case 'cbEnvioMails':
                return [
                    'factura'      => 'cem_susFactura',
                    'cartera'      => 'cem_susCarteraId',
                    'tipificacion' => null,
                    'campos'       => [
                        '_id',
                        'cem_susFactura',
                        'cem_susCarteraId',
                        'cem_susLlamadaId',
                        'cem_susErrorEnvio'
                    ]
                ];
            default:
                return null;
        }
    }

    protected function buildCondition($tabla, $regIds): array
    {
        $idsStr = "'" . implode("','", $regIds) . "'";
        return match ($tabla) {
            'avProgramadas'         => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGV_numFactura' => ['$in' => $regIds]]],
            'avProgramadasWhatsapp' => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGV_numFactura' => ['$in' => $regIds]]],
            'cbEnvioMails'          => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGV_numFactura' => ['$in' => $regIds]]],
            default                 => ['db' => '', 'mongo' => []],
        };
    }

    /**
     * Devuelve el mapeo de campos utilizado para transformar los datos provenientes
     * de la colección original en MongoDB hacia un formato homogéneo para reportes
     * o gráficos BI.
     *
     * Cada entrada del arreglo define:
     *  - La clave con la que se representará el campo en el resultado final.
     *  - El nombre real del campo en la base de datos mongo (`mdb`).
     *  - El valor por defecto a usar cuando el campo no exista o llegue vacío
     *    (`defaultValue`).
     *
     * Ejemplo de retorno:
     * [
     *     'fecha_pago' => [ 'mdb' => 'fechaPago', 'defaultValue' => '(Sin Fecha Pago)' ]
     * ]
     *
     * @return array Arreglo asociativo con la definición de campos y sus reglas de mapeo.
     */
    public function getMapeoCampos(): array
    {
        $campos = [
            'fecha_gestion'             => ['mdb' => 'cubGV_fechaGestion',        'defaultValue' => '(Sin fecha gestión)'],
            'sponsor'                   => ['mdb' => 'cubGV_sponsor',        'defaultValue' => '(Sin sponsor)'],
            'nombre_cliente'            => ['mdb' => 'cubGV_nombres',        'defaultValue' => '(Sin nombre cliente)'],
            'apellido_cliente'          => ['mdb' => 'cubGV_apellidos',        'defaultValue' => '(Sin apellido cliente)'],
            'cedula_cliente'            => ['mdb' => 'cubGV_cedula',        'defaultValue' => '(Sin apellido cliente)'],
            'edad'                      => ['mdb' => 'cubGV_edad',        'defaultValue' => '(Sin edad)'],
            'sexo'                      => ['mdb' => 'cubGV_sexo',        'defaultValue' => '(Sin sexo)'],
            'estado_civil'              => ['mdb' => 'cubGV_estadoCivil',        'defaultValue' => '(Sin estado civil)'],
            'dirección'                 => ['mdb' => 'cubGV_direccion',        'defaultValue' => '(Sin dirección)'],
            'cartera'                   => ['mdb' => 'cubGV_carteraNombre',        'defaultValue' => '(Sin cartera)'],
            'campaña'                   => ['mdb' => 'cubGV_campaniaNombre',        'defaultValue' => '(Sin campaña)'],
            'canal'                     => ['mdb' => 'cubGV_canal',        'defaultValue' => '(Sin canal)'],
            'nivel_contacto'            => ['mdb' => 'cubGV_nivelContacto',        'defaultValue' => '(Sin nivel de contacto)'],
            'duración'                  => ['mdb' => 'cubGV_duracionGestionSeg',        'defaultValue' => '(Sin duración)'],
            'tipificación'              => ['mdb' => 'cubGV_tipificacion',        'defaultValue' => '(Sin tipificación)'],
            'resumen'                   => ['mdb' => 'cubGV_resumen',        'defaultValue' => '(Sin resumen)'],
            'ponderación'               => ['mdb' => 'cubGV_ponderacion',        'defaultValue' => '(Sin ponderación)'],
            'registro_operacion'        => ['mdb' => 'cubGV_numFactura',        'defaultValue' => '(Sin registro operación)'],
            'ciudad'                    => ['mdb' => 'cubGV_ciudad',        'defaultValue' => '(Sin ciudad)'],
            'producto'                  => ['mdb' => 'cubGV_producto',        'defaultValue' => '(Sin producto)'],
            'fecha_carga'               => ['mdb' => 'cubGV_fechaCarga',        'defaultValue' => '(Sin fecha carga)']
        ];
        return $campos;
    }


    protected function createData($data): array
    {
        $row = $data['row'];
        $idGestion = $data['idGestion'];
        $tabla = $data['tabla'];
        $datos = [
            //mongo cbCreditos
            'cubGV_cbcreId'                           => $row['_id'] ?? null,
            'cubGV_sponsor'                           => 'BANCO DEL PACÍFICO',
            'cubGV_usuariosId'                        => $row['usUsuarios_id'] ?? '',
            'cubGV_nombres'                           => $row['cre_nombres'] ?? '',
            'cubGV_apellidos'                         => $row['cre_apellidos'] ?? '',
            'cubGV_cedula'                            => $row['cre_cedula'] ?? '',
            'cubGV_producto'                          => $row['cre_producto'] ?? '',
            'cubGV_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
            'cubGV_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
            'cubGV_numFactura'                        => $row['cre_factura'] ?? '',
            'cubGV_ciudad'                            => $row['cre_ciudad'] ?? '',
            'cubGV_carteraId'                         => $row['cre_carteraId'] ?? '',
            'cubGV_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
            'cubGV_fechaCarga   '                     => $row['cre_fechaCarga'] ?? '',

            //Mongo CRM
            'cubGV_crmId'                             => '',
            'cubGV_edad'                              => '',
            'cubGV_sexo'                              => '',
            'cubGV_estadoCivil'                       => '',
            //Mongo cbDirecciones    
            'cubGV_dirId'                             => '',
            'cubGV_direccion'                         => '',

            //SQL cobmaparbolgst  
            'cubGV_cobmaparbolgstId'                  => '',
            'cubGV_ponderacion'                       => 0,
            //Mongo avProgramadas, avProgramadasWhatsapp, cbEnvioMails         
            'cubGV_avId'                              => '',
            'cubGV_fechaGestion'                      => 0,
            'cubGV_telefono'                          => '',
            'cubGV_email'                             => '',
            'cubGV_campaniaId'                        => '',
            'cubGV_campaniaNombre'                    => '',
            'cubGV_canal'                             => '',
            'cubGV_proveedor'                         => '',
            'cubGV_proveedorSip'                      => '',
            'cubGV_nivelContacto'                     => '', 
            'cubGV_duracionGestionSeg'                => '',
            'cubGV_tipificacion_respuesta1'           => '',
            'cubGV_tipificacion_respuesta2'           => '',
            'cubGV_resumen'                           => ''
        ];

        //Buscar registros en mongo CRM y añadir al cubo
        $mdbCrm = new MYMONGODB();
        $condCrm = ['crm_cedula' => (string) $datos['cubGV_cedula']];
        $datCrm = [
            '_id',
            'crm_edad',
            'crm_sexo',
            'crm_estadoCivil'
        ];
        $mdbCrm->buscar('CRM', $condCrm, $datCrm, [], 1);
        while ($doc = $mdbCrm->siguiente()) {
            $datos['cubGV_crmId'] = $doc['_id'] ?? null;
            $datos['cubGV_edad'] = $doc['crm_edad'] ?? null;
            $datos['cubGV_sexo'] = $doc['crm_sexo'] ?? null;
            $datos['cubGV_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;

            break;
        }
        //Buscar registros en mongo cbDirecciones y añadir al cubo
        $mdbDir = new MYMONGODB();
        $condDir = ['dir_cedula' => (string) $datos['cubGV_cedula']];
        $datDir = [
            '_id',
            'dir_direccion'
        ];
        $mdbDir->buscar('cbDirecciones', $condDir, $datDir, [], 1);

        while ($doc = $mdbDir->siguiente()) {
            $datos['cubGV_dirId'] = $doc['_id'] ?? null;
            $datos['cubGV_direccion'] = $doc['dir_direccion'] ?? null;
            break;
        }


        //Buscar registros en mongo avProgramadas y añadir al cubo
        $insertado = false;
        $mdbCubo = new MYMONGODB();
        $carteraId    = (int) ($datos['cubGV_carteraId'] ?? 0);

        if ($tabla === 'avProgramadas') {
            $mdbAvProg = new MYMONGODB();
            $condAvProg = [
                'av_factura'   => (string) $datos['cubGV_numFactura'],
                'av_carteraId'   => (int) $datos['cubGV_carteraId'],
                'av_tipificacion.respuesta2' => ['$nin' => ['', null]]
            ];
            if (!empty($idGestion)) {
                $condAvProg['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }
            $datAvProg = [
                '_id',
                'av_fechaFinLlamada',
                'av_eventoFecha',
                'av_telefono',
                'av_campaniaId',
                'av_campaniaNombre',
                'av_duracionSegundos',
                'av_tipificacion',
                'av_fechaActualizaLlamada',
                'av_fechaPeriodo',
                'av_proveedor',
                'av_proveedorSip'

            ];
            $mdbAvProg->buscar('avProgramadas', $condAvProg, $datAvProg);
            /*trigger_error(
                "FACTURA {$datos['cubGV_numFactura']} --> avProgramadas encontradas: {$mdbAvProg->buscar('avProgramadas',$condAvProg,$datAvProg)}"
            );*/
            while ($doc = $mdbAvProg->siguiente()) {
                $fila = $datos;
                $fila['cubGV_avId'] = $doc['_id'] ?? null;
                $fila['cubGV_canal'] = 'TELEFONICA';
                $fechaGestion = 0;
                /*$fechaFin = isset($doc['av_fechaFinLlamada']) ? (int)$doc['av_fechaFinLlamada'] : 0;
                $fechaAct = isset($doc['av_eventoFecha']) ? (int)$doc['av_eventoFecha'] : 0;
                if ($fechaFin === 0) {
                    $fechaGestion = $fechaAct;
                } else {
                    $fechaGestion = $fechaFin;
                }*/
                $fechaGestion = !empty($doc['av_fechaFinLlamada'])
                    ? (int)$doc['av_fechaFinLlamada']
                    : (int)$doc['av_eventoFecha'];

                $fila['cubGV_fechaGestion'] = $fechaGestion ?? 0;
                $fila['cubGV_telefono'] = $doc['av_telefono'] ?? null;
                $fila['cubGV_campaniaId'] = (int)$doc['av_campaniaId'] ?? null;
                $fila['cubGV_campaniaNombre'] = $doc['av_campaniaNombre'] ?? null;
                $fila['cubGV_nivelContacto'] = $doc['av_tipificacion']['respuesta1'] ?? null;
                $fila['cubGV_duracionGestionSeg'] = $doc['av_duracionSegundos'] ?? null;
                $fila['cubGV_tipificacion_respuesta1'] = $doc['av_tipificacion']['respuesta1'] ?? null;
                $fila['cubGV_tipificacion_respuesta2'] = $doc['av_tipificacion']['respuesta2'] ?? null;
                $fila['cubGV_resumen'] = $doc['av_tipificacion']['resumen'] ?? null;
                $fila['cubGV_proveedor'] = $doc['av_proveedor'] ?? null;
                $fila['cubGV_proveedorSip'] = $doc['av_proveedorSip'] ?? null;
                // $fila['cubGV_fechaPeriodo'] = isset($doc['av_fechaPeriodo']) ? (int)$doc['av_fechaPeriodo'] : 0;
                $dbMap = new MYSQLDB();
                $sql = $dbMap->mkSQL(
                    "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                    (int)$fila['cubGV_campaniaId'],
                    (string)$fila['cubGV_tipificacion_respuesta2'],
                    (int)$carteraId
                );
                $dbMap->query($sql);
                if ($rowMap = $dbMap->fetchRow()) {
                    $fila['cubGV_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                    $fila['cubGV_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                }
                //Buscar si existe el registro en el cubo:
                // trigger_error("ID " . $fila['cubGV_avId']);
                $condCubo = [
                    'cubGV_numFactura' => (string)$fila['cubGV_numFactura'],
                    'cubGV_carteraId' => (string)$fila['cubGV_carteraId'],
                    'cubGV_avId'       => new MongoDB\BSON\ObjectId($fila['cubGV_avId']),
                ];
                /*trigger_error(
                    "COND_CUBO:" . print_r($condCubo, true)
                );
                trigger_error(
                    "busq" . $mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)
                );*/
                if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)) {
                    $mdbCubo->guardar(self::COLLECTION_CUBO, $fila);
                    $insertado = true;
                } else {
                    $mdbCubo->actualizar(self::COLLECTION_CUBO, $condCubo, $fila);
                }
            }
        }

        if ($tabla === 'avProgramadasWhatsApp') {
            //Buscar registros en mongo avProgramadasWhatsapp y añadir al cubo
            $mdbAvProgWhats = new MYMONGODB();
            $condAvProgWhats = [
                'ws_factura'   => (string) $datos['cubGV_numFactura'],
                'ws_carteraId'   => (int) $datos['cubGV_carteraId'],
                'ws_tipificacion.respuesta2' => ['$nin' => ['', null]]
            ];
            if (!empty($idGestion)) {
                $condAvProgWhats['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }
            $datAvProgWhats = [
                '_id',
                'ws_fecha',
                'ws_numeroWP',
                'ws_campaniaId',
                'ws_campaniaNombre',
                'ws_duracionSegundos',
                'ws_tipificacion',
                'ws_fechaPeriodo'
            ];
            $mdbAvProgWhats->buscar('avProgramadasWhatsApp', $condAvProgWhats, $datAvProgWhats);
            while ($doc = $mdbAvProgWhats->siguiente()) {
                $fila = $datos;
                $fila['cubGV_avId'] = $doc['_id'] ?? null;
                $fila['cubGV_canal'] = 'WHATSAPP';
                $fila['cubGV_fechaGestion'] =  (int)$doc['ws_fecha'] ?? 0;
                $fila['cubGV_telefono'] = $doc['ws_numeroWP'] ?? null;
                $fila['cubGV_campaniaId'] = (int)$doc['ws_campaniaId'] ?? null;
                $fila['cubGV_campaniaNombre'] = $doc['ws_campaniaNombre'] ?? null;
                $fila['cubGV_nivelContacto'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
                $fila['cubGV_duracionGestionSeg'] = $doc['ws_duracionSegundos'] ?? null;
                $fila['cubGV_tipificacion_respuesta1'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
                $fila['cubGV_tipificacion_respuesta2'] = $doc['ws_tipificacion']['respuesta2'] ?? null;
                $fila['cubGV_resumen'] = $doc['ws_tipificacion']['resumen'] ?? null;
                // $fila['cubGV_fechaPeriodo'] = isset($doc['ws_fechaPeriodo']) ? (int)$doc['ws_fechaPeriodo'] : 0;
                $dbMap = new MYSQLDB();
                $sql = $dbMap->mkSQL(
                    "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                    (int)$fila['cubGV_campaniaId'],
                    (string)$fila['cubGV_tipificacion_respuesta2'],
                    (int)$carteraId
                );
                $dbMap->query($sql);
                if ($rowMap = $dbMap->fetchRow()) {
                    $fila['cubGV_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                    $fila['cubGV_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                }
                //Buscar si existe el registro en el cubo:
                $condCubo = [
                    'cubGV_numFactura' => (string)$fila['cubGV_numFactura'],
                    'cubGV_carteraId' => (string)$fila['cubGV_carteraId'],
                    'cubGV_avId'       => new MongoDB\BSON\ObjectId($fila['cubGV_avId']),
                ];
                if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)) {
                    $mdbCubo->guardar(self::COLLECTION_CUBO, $fila);
                    $insertado = true;
                } else {
                    $mdbCubo->actualizar(self::COLLECTION_CUBO, $condCubo, $fila);
                }
            }
        }
        //Buscar registros en mongo cbEnvioMails y añadir al cubo
        if ($tabla === 'cbEnvioMails') {
            $mdbEnvMail = new MYMONGODB();
            $condEnvMail = [
                'cem_susFactura'   => (string) $datos['cubGV_numFactura'],
                'cem_susCarteraId'   => (int) $datos['cubGV_carteraId']
            ];
            if (!empty($idGestion)) {
                $condEnvMail['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }
            $datEnvMail = [
                '_id',
                'cem_susFechaEnvio',
                'cem_susEmail',
                'cem_susCampaniaId',
                'cem_susCampaniaNombre',
                'cem_susLlamadaId',
                'cem_susFechaPeriodo'

            ];
            $mdbEnvMail->buscar('cbEnvioMails', $condEnvMail, $datEnvMail);
            while ($doc = $mdbEnvMail->siguiente()) {
                $fila = $datos;
                $fila['cubGV_avId'] = $doc['_id'] ?? null;
                $fila['cubGV_canal'] = 'EMAIL';
                $fila['cubGV_fechaGestion'] =  (int)$doc['cem_susFechaEnvio'] ?? 0;
                $fila['cubGV_email'] = $doc['cem_susEmail'] ?? null;
                $fila['cubGV_campaniaId'] = (int)$doc['cem_susCampaniaId'] ?? null;
                $fila['cubGV_campaniaNombre'] = $doc['cem_susCampaniaNombre'] ?? null;
                $fila['cubGV_duracionGestionSeg'] = 0;
                //$fila['cubGV_fechaPeriodo'] = isset($doc['cem_susFechaPeriodo']) ? (int)$doc['cem_susFechaPeriodo'] : 0;
                $dbLlam = new MYSQLDB();
                $sql = $dbLlam->mkSQL("SELECT scLlamadas_ruta1, scLlamadas_ruta2, scLlamadas_texto 
                                        FROM scllamadas where scLlamadas_id=%N  AND scLlamadas_ruta1 IS NOT NULL 
                                        AND scLlamadas_ruta1 != ''", $doc['cem_susLlamadaId']);
                $dbLlam->query($sql);
                if ($rowSql = $dbLlam->fetchRow()) {
                    $fila['cubGV_nivelContacto'] = $rowSql['scLlamadas_ruta1'] ?? null;
                    $fila['cubGV_tipificacion_respuesta1']  = $rowSql['scLlamadas_ruta1'] ?? null;
                    $fila['cubGV_tipificacion_respuesta2']  = $rowSql['scLlamadas_ruta2'] ?? null;
                    $fila['cubGV_resumen']       = $rowSql['scLlamadas_texto'] ?? null;
                    $dbMap = new MYSQLDB();
                    $sql = $dbMap->mkSQL(
                        "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                        (int)$fila['cubGV_campaniaId'],
                        (string)$fila['cubGV_tipificacion_respuesta2'],
                        (int)$carteraId
                    );
                    $dbMap->query($sql);
                    if ($rowMap = $dbMap->fetchRow()) {
                        $fila['cubGV_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                        $fila['cubGV_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                    }
                    //Buscar si existe el registro en el cubo:
                    $condCubo = [
                        'cubGV_numFactura' => (string)$fila['cubGV_numFactura'],
                        'cubGV_carteraId' => (string)$fila['cubGV_carteraId'],
                        'cubGV_avId'       => new MongoDB\BSON\ObjectId($fila['cubGV_avId']),
                    ];
                    if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)) {
                        $mdbCubo->guardar(self::COLLECTION_CUBO, $fila);
                        $insertado = true;
                    } else {
                        $mdbCubo->actualizar(self::COLLECTION_CUBO, $condCubo, $fila);
                    }
                }
            }
        }
        if ($insertado) {
            return [];
        }
        return $datos;
    }

    public function recreate(): void
    {
        $limit = 20000;
        $skip  = 0;
        $condiciones = [];
        $mdb = new MYMONGODB();


        $encontreControl = false;

        if (!$encontreControl) {  //solo borre la coleccion completa cuando no sea reconstruccion parcial
            $mdb->borrarColeccion(self::COLLECTION_CUBO);
        } else {
            //no borre automaticamente. Hagalo manualmente.
        }

        $db = new MYSQLDB();
        $sql = 'SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo="VENTAS"';
        $db->query($sql);

        $carterasVentas = [];
        while ($row = $db->fetchRow()) {
            $carterasVentas[] = (string)$row['cobCartera_id'];
        }

        $condiciones = [
            'cre_carteraId' => ['$in' => $carterasVentas]
        ];
        for ($i = 0; $i < 400; $i++) {
            $mdb->buscar('cbCreditos', $condiciones, [], ['_id' => 1], $limit, $skip);
            $numRows = 0;
            while ($doc = $mdb->siguiente()) {
                foreach (['avProgramadas', 'avProgramadasWhatsApp', 'cbEnvioMails'] as $tabla) {
                    $this->createData([
                        'row' => $doc,
                        'idGestion' => null,
                        'tabla' => $tabla
                    ]);
                }
                $numRows++;
            }
            if ($numRows < $limit) break;
            $skip += $limit;
        }
        $this->createIndices($mdb);
    }

    protected function baseQuery(int $limit = 0): string
    {
        return "SELECT 
                cr.cre_id,
                'BANCO DEL PACÍFICO' AS sponsor,
                cr.cre_cedula,
                cr.cre_nombres,
                cr.cre_apellidos,
                cr.cre_calificacion,
                cr.cre_deudaNeta, 
                cr.cre_diasMoraFactura, 
                cr.cre_producto, 
                cr.cre_periodo, 
                cr.cre_factura
                FROM cbcreditos cr " . ($limit > 0 ? 'LIMIT ' . $limit : '');
    }

    protected function createIndices($mdb): void
    {
        $indices = [
            'cubGV_numFactura',
            'cubGV_carteraId',
            'cubGV_cedula',
        ];
        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}
?><? //_FIN_DE_ARCHIVO 
    ?>