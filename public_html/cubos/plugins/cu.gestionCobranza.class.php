<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGgestionCobranza extends AbstractCuboPlugin {
    public const COLLECTION_CUBO = 'cuGestionCobranza';
    protected function process($ids, $accion): void
    {
        //trigger_error("Factura ingresa cuGestionCobranza" . print_r($ids,true));
        //trigger_error("Factura accion cuGestionCobranza" . print_r($accion,true));
        if (!is_array($ids) || empty($ids)) return;
        $tablas = $this->organizeByTable($ids);
        $db     = new MYSQLDB();
        foreach ($tablas as $tabla => $regIds) {
            $config = $this->getConfigByTabla($tabla);
            if (!$config) continue;
            switch ($accion) {
                case 'ADD':
                    foreach ($regIds as $id) {
                        $mdbOrigen = new MYMONGODB();
                        $condOrigen = [
                            '_id' => new MongoDB\BSON\ObjectId($id),
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
            'avProgramadas'         => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGC_numFactura' => ['$in' => $regIds]]],
            'avProgramadasWhatsapp' => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGC_numFactura' => ['$in' => $regIds]]],
            'cbEnvioMails'          => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubGC_numFactura' => ['$in' => $regIds]]],
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
            'fecha_gestion'             => ['mdb' => 'cubGC_fechaGestion',        'defaultValue' => '(Sin fecha gestión)'],
            'sponsor'                   => ['mdb' => 'cubGC_sponsor',        'defaultValue' => '(Sin sponsor)'],
            'nombre_cliente'            => ['mdb' => 'cubGC_nombres',        'defaultValue' => '(Sin nombre cliente)'],
            'apellido_cliente'          => ['mdb' => 'cubGC_apellidos',        'defaultValue' => '(Sin apellido cliente)'],
            'cedula_cliente'            => ['mdb' => 'cubGC_cedula',        'defaultValue' => '(Sin apellido cliente)'],
            'edad'                      => ['mdb' => 'cubGC_edad',        'defaultValue' => '(Sin edad)'],
            'sexo'                      => ['mdb' => 'cubGC_sexo',        'defaultValue' => '(Sin sexo)'],
            'estado_civil'              => ['mdb' => 'cubGC_estadoCivil',        'defaultValue' => '(Sin estado civil)'],
            'dirección'                 => ['mdb' => 'cubGC_direccion',        'defaultValue' => '(Sin dirección)'],
            'cartera'                   => ['mdb' => 'cubGC_carteraNombre',        'defaultValue' => '(Sin cartera)'],
            'campaña'                   => ['mdb' => 'cubGC_campaniaNombre',        'defaultValue' => '(Sin campaña)'],
            'canal'                     => ['mdb' => 'cubGC_canal',        'defaultValue' => '(Sin canal)'],
            'nivel_contacto'            => ['mdb' => 'cubGC_nivelContacto',        'defaultValue' => '(Sin nivel de contacto)'],
            'duración'                  => ['mdb' => 'cubGC_duracionGestionSeg',        'defaultValue' => '(Sin duración)'],
            'tipificación'              => ['mdb' => 'cubGC_tipificacion',        'defaultValue' => '(Sin tipificación)'],
            'resumen'                   => ['mdb' => 'cubGC_resumen',        'defaultValue' => '(Sin resumen)'],
            'ponderación'               => ['mdb' => 'cubGC_ponderacion',        'defaultValue' => '(Sin ponderación)'],
            'registro_operacion'        => ['mdb' => 'cubGC_numFactura',        'defaultValue' => '(Sin registro operación)'],
            'ciudad'                    => ['mdb' => 'cubGC_ciudad',        'defaultValue' => '(Sin ciudad)'],
            'riesgo'                    => ['mdb' => 'cubGC_riesgo',        'defaultValue' => '(Sin riesgo)'],
            'producto'                  => ['mdb' => 'cubGC_producto',        'defaultValue' => '(Sin producto)'],
            'capital_actual'            => ['mdb' => 'cubGC_capitalActual',        'defaultValue' => '(Sin capital actual)'],
            'deuda_neta_actual'         => ['mdb' => 'cubGC_deudaNetaActual',        'defaultValue' => '(Sin deuda neta actaul)'],
            'dias_mora'                 => ['mdb' => 'cubGC_diasMora',        'defaultValue' => '(Sin días mora)'],
            'ciclo'                     => ['mdb' => 'cubGC_ciclo',        'defaultValue' => '(Sin ciclo)'],
            'saldo_capital_inicial'     => ['mdb' => 'cubGC_capitalInicial',        'defaultValue' => '(Sin saldo capital inicial)'],
            'deuda_neta_inicial'        => ['mdb' => 'cubGC_deudaNetaInicial',        'defaultValue' => '(Sin deuda neta inicial)'],
            'fecha_inicio'              => ['mdb' => 'cubGC_fechaInicio',        'defaultValue' => '(Sin fecha inicio)'],
            'fecha_fin'                 => ['mdb' => 'cubGC_fechaFin',        'defaultValue' => '(Sin fecha fin)'],
            'fecha_periodo'             => ['mdb' => 'cubGC_fechaPeriodo',        'defaultValue' => '(Sin fecha periodo)'],
        ];
        return $campos;
    }

    private function obtenerCodigoGestion($doc)
    {
        $respuesta = '02'; //por defecto
        $mapaRuta2 = [
            'SOCIO FUERA PAIS' => '01',
            'ILOCALIZABLE' => '02',
            'MENSAJE A TERCERO' => '03',
            'GESTION A TERCEROS' => '03',
            'TELEFONO EQUIVOCADO NO CORRESPONDE' => '04',
            'PAGA EN FECHA' => '05',
            'RECLAMO PENDIENTE' => '06',
            'VALORES A PAGAR ERRONEOS' => '06',
            'DEBITOS DUPLICADOS' => '06',
            'DFP NO ACEPTADO' => '06',
            'NO LLEGO ESTADO CUENTA' => '07',
            'NO QUIERE PAGAR' => '08',
            'DESEMPLEADO' => '09',
            'CALAMIDAD DOMESTICA' => '10',
            'CHEQUE' => '11',
            'VOLVER A LLAMAR' => '16',
            'MENSAJE VOZ GRABADORA' => '16',
            'SEGUROS NO CANCELADOS' => '16',
            'FALLECIDO' => '20',
            'WHATSAPP LEIDO' => '24',
            'WHATSAPP NO LEIDO' => '24',
            'Mail Enviado' => '24',
            'WHATSAPP RESPONDIDO' => '35',
            'GESTION DE COBRANZAS CARGADA' => '18'
        ];
        if (
            ($doc['cubGC_campaniaNombre'] == 'BDP Whatsapp Preventiva' && $doc['cubGC_tipificacion_respuesta1'] == 'CONTACTO DIRECTO')
            || ($doc['cubGC_campaniaNombre'] == 'BDP Whatsapp Premora')
            || ($doc['cubGC_tipificacion_respuesta1'] == 'CONTACTO DIRECTO' && $doc['cubGC_resumen'] == 'WHATSAPP ENVIADO')
        ) {
            $respuesta = '24';
        } elseif (
            ($doc['cubGC_tipificacion_respuesta1'] == 'CONTACTO DIRECTO' && $doc['cubGC_resumen'] == 'WHATSAPP RESPONDIDO')
            || ($doc['cubGC_tipificacion_respuesta1'] == 'CONTACTO DIRECTO' && $doc['cubGC_resumen'] == 'WHATSAPP LEIDO')
        ) {
            $respuesta = '35';
            // 3? SIN CONTACTO
        } elseif (
            $doc['cubGC_tipificacion_respuesta1'] == 'SIN CONTACTO'
            && (
                $doc['cubGC_resumen'] == 'ILOCALIZABLE'
                || $doc['cubGC_resumen'] == 'CLIENTE NO CONTESTA LA LLAMADA'
                || $doc['cubGC_resumen'] == 'WHATSAPP NO LEIDO'
                || $doc['cubGC_tipificacion_respuesta2'] == null
                || $doc['cubGC_tipificacion_respuesta2'] == ''
            )
        ) {
            $respuesta = '02';
            // 4? REINTENTOS
        } elseif (
            ($doc['cubGC_resumen'] != null && strpos($doc['cubGC_resumen'], 'REINTENTOS') === 0)
            || $doc['cubGC_resumen'] == 'ILOCALIZABLE'
        ) {
            $respuesta = '02';
            // 6? Buscar en mapa
        } elseif (isset($mapaRuta2[$doc['cubGC_tipificacion_respuesta2']])) {
            $respuesta = $mapaRuta2[$doc['cubGC_tipificacion_respuesta2']];
        }
        return $respuesta;
    }

    public function obtenerGestionesNoEnviadas(): array
    {
        //__Descripcion__:  Lee desde el cubo las gestiones que no estén marcadas como enviadas y las
        //                  entrega en un array
        //__Input__: null
        //__Output__: array de gestiones
        $mdb = new MYMONGODB();
        $cuantas = $mdb->buscar(self::COLLECTION_CUBO, [
            '$or' => [
                ['cubGC_enviada' => ['$exists' => false]],
                ['cubGC_enviada' => ['$ne' => 1]]
            ]
        ], [
            'cubGC_cedula', //16
            'cubGC_numFactura', //6
            //fechaAsignacion //8   XXXXXXX
            //secuencial    //2    CALCULAR POR CLIENTE
            'cubGC_fechaGestion',  //8
            //tipo de contacto   //1: T
            //codigo respuesta   //2
            //observaciones,      //210
            //seguimiento en tres dias   //8
            //respuesta          //3
            'cubGC_canal',
            'cubGC_campaniaNombre', //scLlamadas_ruta0
            'cubGC_tipificacion_respuesta1', //scLlamadas_ruta1
            'cubGC_tipificacion_respuesta2', //scLlamadas_ruta2
            'cubGC_nivelContacto', // p.ej. SIN CONTACTO
            'cubGC_resumen'  //que guarda el scLlamadas_texto
        ]);
        //print_h('cuantas: '.$cuantas);
        $gestiones = [];
        $secuencialPorCliente = [];
        $enTresDias = date('Ymd', strtotime('+3 days'));
        if ($doc = $mdb->siguiente()) {
            //print_h($doc);
            $fechaAsignacion = "YYYYMMDD"; //falta obtener desde archivo de asignacion
            $secuencialPorCliente['C_' . $doc['cubGC_cedula']] = isset($secuencialPorCliente['C_' . $doc['cubGC_cedula']]) ? $secuencialPorCliente['C_' . $doc['cubGC_cedula']]++ : 1;
            $linea = '';
            $linea .= str_pad($doc['cubGC_cedula'], 16, " ");
            $linea .= str_pad($doc['cubGC_numFactura'], 6, " ");
            $linea .= str_pad($fechaAsignacion, 8, " ");
            $linea .= str_pad($secuencialPorCliente['C_' . $doc['cubGC_cedula']], 2, "0", STR_PAD_LEFT);
            $linea .= str_pad(date('Ymd', $doc['cubGC_fechaGestion']), 8, " ");
            $linea .= 'T';
            $linea .= str_pad($this->obtenerCodigoGestion($doc), 2, " ");
            $linea .= str_pad("", 210, " ");
            $linea .= str_pad($enTresDias, 6, " ");
            $linea .= $linea .= str_pad("", 3, " ");
            $linea .= "\r\n";
            /*
            [cubGC_sponsor] => BANCO DEL PACÍFICO
            [cubGC_nombres] => CECILIA ROSALBA
            [cubGC_apellidos] => HEREDIA REYES
            [cubGC_cedula] => 0701148504
            [cubGC_riesgo] => A2
            [cubGC_deudaNetaActual] => 430.42
            [cubGC_diasMora] => 1
            [cubGC_producto] => MASTERCARD-DFP
            [cubGC_ciclo] => 0
            [cubGC_fechaPeriodo] => 1770181200
            [cubGC_numFactura] => 022635
            [cubGC_ciudad] => 
            [cubGC_capitalActual] => 14032.85
            [cubGC_carteraId] => 2121
            [cubGC_carteraNombre] => BANCO DEL PACIFICO PREMORA
            [cubGC_usuariosId] => 911132521
            [cubGC_crmId] => MongoDB\BSON\ObjectId Object
                (
                    [oid] => 693ab964f44233a15e0edf20
                )

            [cubGC_edad] => 
            [cubGC_sexo] => null
            [cubGC_estadoCivil] => null
            [cubGC_dirId] => 
            [cubGC_direccion] => 
            [cubGC_cdetId] => MongoDB\BSON\ObjectId Object
                (
                    [oid] => 69a9a58bd039e804d606d453
                )

            [cubGC_capitalInicial] => 13931.2
            [cubGC_deudaNetaInicial] => 430.42
            [cubGC_ctrId] => MongoDB\BSON\ObjectId Object
                (
                    [oid] => 69a0c2cf65d5d4ac59110e5f
                )

            [cubGC_fechaInicio] => 1770181200
            [cubGC_fechaFin] => 1772168400
            [cubGC_cobmaparbolgstId] => 12653
            [cubGC_ponderacion] => 1
            [cubGC_avId] => MongoDB\BSON\ObjectId Object
                (
                    [oid] => 693acc5cf314a2ad7d0ab070
                )

            [cubGC_fechaGestion] => 1765485294
            [cubGC_campaniaId] => 14824
            [cubGC_campaniaNombre] => BDP Agente Virtual Premora
            [cubGC_canal] => TELEFONICA
            [cubGC_nivelContacto] => SIN CONTACTO
            [cubGC_duracionGestionSeg] => 0
            [cubGC_tipificacion_respuesta1] => SIN CONTACTO
            [cubGC_tipificacion_respuesta2] => ILOCALIZABLE
            [cubGC_resumen] => REINTENTOS [1]
            */
            $gestiones[] = $linea;
        }
        return $gestiones;
    }
    public function obtenerLineaTotales($cuantas)
    {
        $linea = '';
        $linea .= str_pad("T", 16, " ");
        $linea .= str_pad("", 6, "9");
        $linea .= str_pad($cuantas, 8, "0", STR_PAD_LEFT);
        $linea .= str_pad("", 231, " ");
        $linea .= str_pad("", 3, " ");
        $linea .= "\r\n";
        return $linea;
    }
    protected function createData($data): array
    {
        $row = $data['row'];
        $idGestion = $data['idGestion'];
        $tabla = $data['tabla'];
        $datos = [
            //mongo cbCreditos
            'cubGC_cbcreId'                           => $row['_id'] ?? null,
            'cubGC_sponsor'                           => 'BANCO DEL PACÍFICO',
            'cubGC_nombres'                           => $row['cre_nombres'] ?? '',
            'cubGC_apellidos'                         => $row['cre_apellidos'] ?? '',
            'cubGC_cedula'                            => $row['cre_cedula'] ?? '',
            'cubGC_riesgo'                            => $row['cre_calificacion'] ?? '',
            'cubGC_deudaNetaActual'                   => (float) ($row['cre_deudaNeta'] ?? 0),
            'cubGC_diasMora'                          => (int) ($row['cre_diasMoraFactura'] ?? 0),
            'cubGC_producto'                          => $row['cre_producto'] ?? '',
            'cubGC_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
            'cubGC_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
            'cubGC_numFactura'                        => $row['cre_factura'] ?? '',

            'cubGC_ciudad'                            => $row['cre_ciudad'] ?? '',
            'cubGC_capitalActual'                     => (float)($row['cre_saldoCapital'] ?? 0),
            'cubGC_carteraId'                         => $row['cre_carteraId'] ?? '',
            'cubGC_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
            'cubGC_usuariosId'                         => $row['usUsuarios_id'] ?? '',

            //Mongo CRM
            'cubGC_crmId'                             => '',
            'cubGC_edad'                              => '',
            'cubGC_sexo'                              => '',
            'cubGC_estadoCivil'                       => '',
            //Mongo cbDirecciones    
            'cubGC_dirId'                             => '',
            'cubGC_direccion'                         => '',

            //Mongo cbCargaDetallePacifico
            'cubGC_cdetId'                            => '',
            'cubGC_capitalInicial'                    => 0,
            'cubGC_deudaNetaInicial'                  => 0,
            //Mongo control_carga_periodo
            'cubGC_ctrId'                             => '',
            'cubGC_fechaInicio'                       => 0,
            'cubGC_fechaFin'                          => 0,
            //Mongo control_carga_periodo       
            'cubGC_cobmaparbolgstId'                  => '',
            'cubGC_ponderacion'                       => 0,
            //Mongo avProgramadas, avProgramadasWhatsapp, cbEnvioMails         
            'cubGC_avId'                              => '',
            'cubGC_fechaGestion'                      => 0,
            'cubGC_telefono'                          => '',
            'cubGC_email'                             => '',
            'cubGC_campaniaId'                        => '',
            'cubGC_campaniaNombre'                    => '',
            'cubGC_canal'                             => '',
            'cubGC_proveedor'                         => '',
            'cubGC_proveedorSip'                      => '',
            'cubGC_nivelContacto'                     => '',
            'cubGC_duracionGestionSeg'                => '',
            'cubGC_tipificacion_respuesta1'           => '',
            'cubGC_tipificacion_respuesta2'           => '',
            'cubGC_resumen'                           => '',
        ];

        //Buscar registros en mongo CRM y añadir al cubo
        $mdbCrm = new MYMONGODB();
        $condCrm = ['crm_cedula' => (string) $datos['cubGC_cedula']];
        $datCrm = [
            '_id',
            'crm_edad',
            'crm_sexo',
            'crm_estadoCivil'
        ];
        $mdbCrm->buscar('CRM', $condCrm, $datCrm, [], 1);
        while ($doc = $mdbCrm->siguiente()) {
            $datos['cubGC_crmId'] = $doc['_id'] ?? null;
            $datos['cubGC_edad'] = $doc['crm_edad'] ?? null;
            $datos['cubGC_sexo'] = $doc['crm_sexo'] ?? null;
            $datos['cubGC_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;

            break;
        }
        //Buscar registros en mongo cbDirecciones y añadir al cubo
        $mdbDir = new MYMONGODB();
        $condDir = ['dir_cedula' => (string) $datos['cubGC_cedula']];
        $datDir = [
            '_id',
            'dir_direccion'
        ];
        $mdbDir->buscar('cbDirecciones', $condDir, $datDir, [], 1);

        while ($doc = $mdbDir->siguiente()) {
            $datos['cubGC_dirId'] = $doc['_id'] ?? null;
            $datos['cubGC_direccion'] = $doc['dir_direccion'] ?? null;
            break;
        }
        //Buscar registros en mongo cbCargaDetallePacifico y añadir al cubo
        $mdbDet = new MYMONGODB();
        $condDet = [
            'cedula'   => (string) $datos['cubGC_cedula'],
            'carteraId' => (int) $datos['cubGC_carteraId'],
            'periodo' => (int) $datos['cubGC_ciclo'],
            'inicial'   => (int) 1,
        ];
        $datDet = [
            '_id',
            'saldoCapital',
            'deudaNeta'
        ];
        $mdbDet->buscar('cbCargaDetallePacifico', $condDet, $datDet, ['_id' => -1], 1);
        while ($doc = $mdbDet->siguiente()) {
            $datos['cubGC_cdetId'] = $doc['_id'] ?? null;
            $datos['cubGC_capitalInicial'] = (float) ($doc['saldoCapital'] ?? 0);
            $datos['cubGC_deudaNetaInicial'] = (float) ($doc['deudaNeta'] ?? 0);
            break;
        }
        //Buscar registros en mongo control_carga_periodo y añadir al cubo
        $mdbCargaPer = new MYMONGODB();
        $condCargaPer = [
            'cartera'   => (int) $datos['cubGC_carteraId'],
            'periodo'   => (int) $datos['cubGC_ciclo'],
            'fecha'     => (int) $datos['cubGC_fechaPeriodo'],
            'activo'    => (int) 1
        ];
        $datCargaPer = [
            '_id',
            'fecha',
            'fechaFin'
        ];
        $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, $datCargaPer, ['_id' => -1], 1); //3 VECES AL MES
        while ($doc = $mdbCargaPer->siguiente()) {
            $datos['cubGC_ctrId'] = $doc['_id'] ?? null;
            $datos['cubGC_fechaInicio'] = (int) $doc['fecha'] ?? 0;
            $datos['cubGC_fechaFin'] = (int) $doc['fechaFin'] ?? 0;
            break;
        }
        //Buscar registros en mongo avProgramadas y añadir al cubo
        $insertado = false;
        $mdbCubo = new MYMONGODB();
        $carteraId    = (int) ($datos['cubGC_carteraId'] ?? 0);

        if ($tabla === 'avProgramadas') {
            $mdbAvProg = new MYMONGODB();
            $condAvProg = [
                'av_factura'   => (string) $datos['cubGC_numFactura'],
                'av_carteraId'   => (int) $datos['cubGC_carteraId'],
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
                "FACTURA {$datos['cubGC_numFactura']} --> avProgramadas encontradas: {$mdbAvProg->buscar('avProgramadas',$condAvProg,$datAvProg)}"
            );*/
            while ($doc = $mdbAvProg->siguiente()) {
                $fila = $datos;
                $fila['cubGC_avId'] = $doc['_id'] ?? null;
                $fila['cubGC_canal'] = 'TELEFONICA';
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
                $fila['cubGC_fechaGestion'] = $fechaGestion ?? 0;
                $fila['cubGC_telefono'] = $doc['av_telefono'] ?? null;
                $fila['cubGC_campaniaId'] = (int)$doc['av_campaniaId'] ?? null;
                $fila['cubGC_campaniaNombre'] = $doc['av_campaniaNombre'] ?? null;
                $fila['cubGC_nivelContacto'] = $doc['av_tipificacion']['respuesta1'] ?? null;
                $fila['cubGC_duracionGestionSeg'] = $doc['av_duracionSegundos'] ?? null;
                $fila['cubGC_tipificacion_respuesta1'] = $doc['av_tipificacion']['respuesta1'] ?? null;
                $fila['cubGC_tipificacion_respuesta2'] = $doc['av_tipificacion']['respuesta2'] ?? null;
                $fila['cubGC_resumen'] = $doc['av_tipificacion']['resumen'] ?? null;
                $fila['cubGC_proveedor'] = $doc['av_proveedor'] ?? null;
                $fila['cubGC_proveedorSip'] = $doc['av_proveedorSip'] ?? null;
                // $fila['cubGC_fechaPeriodo'] = isset($doc['av_fechaPeriodo']) ? (int)$doc['av_fechaPeriodo'] : 0;
                $dbMap = new MYSQLDB();
                $sql = $dbMap->mkSQL(
                    "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                    (int)$fila['cubGC_campaniaId'],
                    (string)$fila['cubGC_tipificacion_respuesta2'],
                    (int)$carteraId
                );
                $dbMap->query($sql);
                if ($rowMap = $dbMap->fetchRow()) {
                    $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                    $fila['cubGC_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                }
                //Buscar si existe el registro en el cubo:
                // trigger_error("ID " . $fila['cubGC_avId']);
                $condCubo = [
                    'cubGC_numFactura' => (string)$fila['cubGC_numFactura'],
                    'cubGC_carteraId' => (string)$fila['cubGC_carteraId'],
                    'cubGC_avId'       => new MongoDB\BSON\ObjectId($fila['cubGC_avId']),
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
                'ws_factura'   => (string) $datos['cubGC_numFactura'],
                'ws_carteraId'   => (int) $datos['cubGC_carteraId'],
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
                $fila['cubGC_avId'] = $doc['_id'] ?? null;
                $fila['cubGC_canal'] = 'WHATSAPP';
                $fila['cubGC_fechaGestion'] =  (int)$doc['ws_fecha'] ?? 0;
                $fila['cubGC_telefono'] = $doc['ws_numeroWP'] ?? null;
                $fila['cubGC_campaniaId'] = (int)$doc['ws_campaniaId'] ?? null;
                $fila['cubGC_campaniaNombre'] = $doc['ws_campaniaNombre'] ?? null;
                $fila['cubGC_nivelContacto'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
                $fila['cubGC_duracionGestionSeg'] = $doc['ws_duracionSegundos'] ?? null;
                $fila['cubGC_tipificacion_respuesta1'] = $doc['ws_tipificacion']['respuesta1'] ?? null;
                $fila['cubGC_tipificacion_respuesta2'] = $doc['ws_tipificacion']['respuesta2'] ?? null;
                $fila['cubGC_resumen'] = $doc['ws_tipificacion']['resumen'] ?? null;
                // $fila['cubGC_fechaPeriodo'] = isset($doc['ws_fechaPeriodo']) ? (int)$doc['ws_fechaPeriodo'] : 0;
                $dbMap = new MYSQLDB();
                $sql = $dbMap->mkSQL(
                    "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                    (int)$fila['cubGC_campaniaId'],
                    (string)$fila['cubGC_tipificacion_respuesta2'],
                    (int)$carteraId
                );
                $dbMap->query($sql);
                if ($rowMap = $dbMap->fetchRow()) {
                    $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                    $fila['cubGC_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                }
                //Buscar si existe el registro en el cubo:
                $condCubo = [
                    'cubGC_numFactura' => (string)$fila['cubGC_numFactura'],
                    'cubGC_carteraId' => (string)$fila['cubGC_carteraId'],
                    'cubGC_avId'       => new MongoDB\BSON\ObjectId($fila['cubGC_avId']),
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
                'cem_susFactura'   => (string) $datos['cubGC_numFactura'],
                'cem_susCarteraId'   => (int) $datos['cubGC_carteraId']
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
                $fila['cubGC_avId'] = $doc['_id'] ?? null;
                $fila['cubGC_canal'] = 'EMAIL';
                $fila['cubGC_fechaGestion'] =  (int)$doc['cem_susFechaEnvio'] ?? 0;
                $fila['cubGC_email'] = $doc['cem_susEmail'] ?? null;
                $fila['cubGC_campaniaId'] = (int)$doc['cem_susCampaniaId'] ?? null;
                $fila['cubGC_campaniaNombre'] = $doc['cem_susCampaniaNombre'] ?? null;
                $fila['cubGC_duracionGestionSeg'] = 0;
                //$fila['cubGC_fechaPeriodo'] = isset($doc['cem_susFechaPeriodo']) ? (int)$doc['cem_susFechaPeriodo'] : 0;
                $dbLlam = new MYSQLDB();
                $sql = $dbLlam->mkSQL("SELECT scLlamadas_ruta1, scLlamadas_ruta2, scLlamadas_texto 
                                        FROM scllamadas where scLlamadas_id=%N  AND scLlamadas_ruta1 IS NOT NULL 
                                        AND scLlamadas_ruta1 != ''", $doc['cem_susLlamadaId']);
                $dbLlam->query($sql);
                if ($rowSql = $dbLlam->fetchRow()) {
                    $fila['cubGC_nivelContacto'] = $rowSql['scLlamadas_ruta1'] ?? null;
                    $fila['cubGC_tipificacion_respuesta1']  = $rowSql['scLlamadas_ruta1'] ?? null;
                    $fila['cubGC_tipificacion_respuesta2']  = $rowSql['scLlamadas_ruta2'] ?? null;
                    $fila['cubGC_resumen']       = $rowSql['scLlamadas_texto'] ?? null;
                    $dbMap = new MYSQLDB();
                    $sql = $dbMap->mkSQL(
                        "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst 
                                       WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND
                                       cobMapArbolGst_carteraId=%N",
                        (int)$fila['cubGC_campaniaId'],
                        (string)$fila['cubGC_tipificacion_respuesta2'],
                        (int)$carteraId
                    );
                    $dbMap->query($sql);
                    if ($rowMap = $dbMap->fetchRow()) {
                        $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                        $fila['cubGC_ponderacion'] = (int)$rowMap['cobMapArbolGst_ponderacion'] ?? 0;
                    }
                    //Buscar si existe el registro en el cubo:
                    $condCubo = [
                        'cubGC_numFactura' => (string)$fila['cubGC_numFactura'],
                        'cubGC_carteraId' => (string)$fila['cubGC_carteraId'],
                        'cubGC_avId'       => new MongoDB\BSON\ObjectId($fila['cubGC_avId']),
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
        /*
        //LIMITAR EL recreate A UNA SOLA CARTERA DE UN SOLO PERIODO
        */
        $encontreControl = false;
        /*
        $limitar=true;
        $carteraId=2122;
        $ciclo=0;
        $fechaInicio=1772514000;
        $limiteSuperior=0;
        if($limitar===true){
            $mdb->buscar('control_carga_periodo',['cartera'=>$carteraId,'periodo'=>$ciclo,'fecha'=>$fechaInicio]);
            if($doc=$mdb->siguiente()){
                $limiteSuperior=$doc['fechaFin'];
                $encontreControl=true;
                $condiciones=[
                    'cre_carteraId'=>strval($carteraId),
                    'cre_periodo'=>$ciclo,
                    'cre_fechaPeriodo'=>$fechaInicio,
                ];
            }
        }
        */
        /* FIN DE LA SECCION DE LIMITAR*/

        if (!$encontreControl) {  //solo borre la coleccion completa cuando no sea reconstruccion parcial
            $mdb->borrarColeccion(self::COLLECTION_CUBO);
        } else {
            //no borre automaticamente. Hagalo manualmente.
        }
        for ($i = 0; $i < 400; $i++) {
            $mdb->buscar('cbCreditos', $condiciones, [], ['_id' => 1], $limit, $skip);
            $numRows = 0;
            while ($doc = $mdb->siguiente()) {
                $this->createData([
                    'row' => $doc,
                    'idGestion' => null
                ]);
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
            'cubGC_numFactura',
            'cubGC_carteraId',
            'cubGC_cedula',
            /* ['cubGC_carteraId','cubGC_numFactura','cubGC_cedula'],
            ['cubGC_carteraId','cubGC_numFactura','cubGC_avId'],
            ['cubGC_carteraId','cubGC_numFactura','cubGC_ciclo','cubGC_fechaPeriodo','cubGC_fechaGestion'],
            ['cubGC_carteraId','cubGC_fechaPeriodo'],
            ['cubGC_carteraId','cubGC_fechaGestion']*/
        ];
        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}
?><? //_FIN_DE_ARCHIVO 
    ?>