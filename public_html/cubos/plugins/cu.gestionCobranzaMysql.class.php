<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGgestionCobranzaMysql extends AbstractCuboPlugin
{
    public const COLLECTION_CUBO = 'cuGestionCobranzaMysql';

    protected function process($ids, $accion): void
    {
        //trigger_error("Factura ingresa cuGestionCobranza" . print_r($ids,true));
        //trigger_error("Factura accion cuGestionCobranza" . print_r($accion,true));
        if (!is_array($ids) || empty($ids)) return;
        $tablas = $this->organizeByTable($ids);
        $db     = new MYSQLDB();
        foreach ($tablas as $tabla => $regIds) {
            switch ($accion) {
                case 'ADD':

                    // Si existen cambios en avDetalleConversaciones* dispara reproceso de avProgramadas relacionado por idConversacion.
                    if (in_array($tabla, self::TABLAS_DETALLE_CONVERSACION, true)) {
                        foreach ($regIds as $idConversacion) {
                            $this->reprocesarAvProgramadasPorConversacion($idConversacion);
                        }
                        break;
                    }

                    // Cambios en scllamadas (MySQL): inserta la llamada si no tiene gestión en Mongo.
                    if ($tabla === self::TABLA_LLAMADAS) {
                        $this->insertarLlamadasSinGestion(0, 0, $regIds);
                        break;
                    }

                    $config = $this->getConfigByTabla($tabla);
                    if (!$config) break;

                    foreach ($regIds as $id) {


                        $mdbOrigen = new MYMONGODB();
                        $condOrigen = [
                            '_id' => new MongoDB\BSON\ObjectId($id)
                        ];
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


    // Colecciones de detalle de conversación
    protected const TABLAS_DETALLE_CONVERSACION = [
        'avDetalleConversaciones',
        'avDetalleConversacionesLink',
        'avDetalleConversacionesRetell',
    ];

    // Llamadas de MySQL que no tienen gestión en avProgramadas, avProgramadasWhatsApp ni cbEnvioMails.
    // El nombre de la tabla también es el valor de cubGC_origen de esas filas.
    protected const TABLA_LLAMADAS = 'scllamadas';
    protected const CANAL_LLAMADAS = 'TELEFONICA';
    protected const LOTE_LLAMADAS  = 1000;

    // Colección de gestión => campo que guarda el scLlamadas_id de la llamada.
    protected const CAMPOS_LLAMADA_GESTION = [
        'avProgramadas'         => ['av_evento', 'av_detalleReintentos.evento'],
        'avProgramadasWhatsApp' => ['ws_evento'],
        'cbEnvioMails'          => ['cem_susLlamadaId'],
    ];

    // Resultado de la última llamada enviada a createData() desde insertarLlamadasSinGestion().
    private string $estadoLlamada = '';
    // Si al guardar una gestión se borra la fila 'scllamadas' de la misma llamada (apagado durante recreate()).
    private bool $quitarLlamadasSinGestion = true;


    /**
     * Dado un idConversacion (proveniente de avDetalleConversaciones / Link / Retell),
     * ubica el(los) avProgramadas relacionado(s) por av_idConversacion y reprocesa
     * ese registro hacia el cubo, para refrescar cubGC_analisisCalidad (y el resto
     * de campos) sin depender de que avProgramadas haya cambiado.
     */
    protected function reprocesarAvProgramadasPorConversacion($idConversacion): void
    {
        if (empty($idConversacion)) return;

        $mdbAvProg = new MYMONGODB();
        $condAvProg = ['av_idConversacion' => $idConversacion];
        $mdbAvProg->buscar('avProgramadas', $condAvProg, ['_id', 'av_factura', 'av_carteraId'], [], 1);

        while ($doc = $mdbAvProg->siguiente()) {
            $factura   = $doc['av_factura'] ?? null;
            $cartera   = $doc['av_carteraId'] ?? null;
            $idGestion = $doc['_id'] ?? null;

            if (empty($factura) || $idGestion === null) continue;

            $mdbCred = new MYMONGODB();
            $condCred = [
                'cre_factura'   => (string)$factura,
                'cre_carteraId' => (string)$cartera
            ];
            $mdbCred->buscar('cbCreditos', $condCred, [], [], 1);
            while ($row = $mdbCred->siguiente()) {
                $this->createData([
                    'row'       => $row,
                    'idGestion' => $idGestion,
                    'tabla'     => 'avProgramadas',
                ]);
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

    // Devuelve la colección de detalle de conversación donde se guarda analisis_calidad, según el proveedor guardado en avProgramadas.av_proveedor
    protected function getColeccionDetalleConversacion(?string $proveedor): ?string
    {
        return match (strtoupper((string)$proveedor)) {
            'ELEVENLABS' => 'avDetalleConversaciones',
            'LINK'       => 'avDetalleConversacionesLink',
            'RETELL'     => 'avDetalleConversacionesRetell',
            default      => null,
        };
    }


     //Busca el campo analisis_calidad en la colección de detalle correspondiente,

    protected function obtenerAnalisisCalidad(?string $proveedor, $idConversacion)
    {
        if (empty($proveedor) || empty($idConversacion)) return null;

        $coleccion = $this->getColeccionDetalleConversacion($proveedor);
        if (!$coleccion) return null;

        $mdbConv = new MYMONGODB();
        $mdbConv->buscar($coleccion, ['idConversacion' => $idConversacion], ['analisis_calidad'], [], 1);

        if ($doc = $mdbConv->siguiente()) {
            return $doc['analisis_calidad'] ?? null;
        }
        return null;
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
            'fecha_programacion'        => ['mdb' => 'cubGC_fechaProgramacion',   'defaultValue' => '(Sin fecha programación)'],
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
            'compromiso'                => ['mdb' => 'cubGC_tipificacion_compromiso',        'defaultValue' => '(Sin compromiso)'],
            'monto_compromiso'          => ['mdb' => 'cubGC_tipificacion_montoCompromiso',        'defaultValue' => '(Sin monto compromiso)'],
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
            'analisis_calidad'          => ['mdb' => 'cubGC_analisisCalidad',        'defaultValue' => '(Sin análisis de calidad)'],
            'origen'                    => ['mdb' => 'cubGC_origen',        'defaultValue' => '(Sin origen)'],
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
        //__Descripcion__:  Lee desde el cubo las gestiones que no están marcadas como enviadas y las
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
            'cubGC_fechaProgramacion'                 => 0,
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
            'cubGC_tipificacion_compromiso'           => '',
            'cubGC_tipificacion_montoCompromiso'      => 0,
            'cubGC_resumen'                           => '',
            'cubGC_llamadaId'                         => 0,
            'cubGC_analisisCalidad'                   => null,
            // Solo aplica a correos (canal EMAIL), se pasan tal cual vienen de cbEnvioMails.
            'cubGC_abierto'                           => 0,
            'cubGC_cantidad_abierto'                  => 0,
            // Horario real (CDR) de la llamada, via scllamadas + sccdr. Solo aplica a TELEFONICA.
            'cubGC_horaInicio'                        => 0,
            'cubGC_horaFin'                           => 0,
            // Colección o tabla de la que sale la gestión; 'scllamadas' = llamada sin avProgramadas.
            'cubGC_origen'                            => $tabla,
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
        $carteraId = (int)($datos['cubGC_carteraId'] ?? 0);

        if ($tabla === 'avProgramadas') {

            $mdbAvProg = new MYMONGODB();

            $condAvProg = [
                'av_factura' => (string)$datos['cubGC_numFactura'],
                'av_carteraId' => (int)$datos['cubGC_carteraId'],
                'av_fecha' => ['$gte' => $datos['cubGC_fechaInicio']]
                // 'av_tipificacion.respuesta2' => ['$nin' => ['', null]] //ojo
            ];
  
            if (!empty($idGestion)) { 
                $condAvProg['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }

            $datAvProg = [
                '_id',
                'av_fecha',
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
                'av_proveedorSip',
                'av_evento',
                'av_detalleReintentos',
                'av_idConversacion',
                'av_fechaGeneraLlamada'
            ];

            $mdbAvProg->buscar('avProgramadas', $condAvProg, $datAvProg);

            while ($doc = $mdbAvProg->siguiente()) {

                // Se calcula una sola vez por documento: av_proveedor / av_idConversacion
                // pertenecen al avProgramadas, no a cada evento/reintento.
                $analisisCalidad = $this->obtenerAnalisisCalidad(
                    $doc['av_proveedor'] ?? null,
                    $doc['av_idConversacion'] ?? null
                );

                // Igual que av_proveedor/av_idConversacion, compromiso y montoCompromiso
                // pertenecen al avProgramadas, no a cada evento/reintento.
                $compromiso = $doc['av_tipificacion']['compromiso'] ?? null;
                $montoCompromiso = isset($doc['av_tipificacion']['montoCompromiso'])
                    ? (float)$doc['av_tipificacion']['montoCompromiso']
                    : null;

                $eventos = [];

                // Evento principal
                if (!empty($doc['av_evento'])) {
                    $eventos[] = [
                        'evento' => (int)$doc['av_evento'],
                        'principal' => true
                    ];
                }

                // Reintentos
                if (!empty($doc['av_detalleReintentos'])) {
                    foreach ($doc['av_detalleReintentos'] as $reintento) {

                        if (!empty($reintento['evento'])) {
                            $eventos[] = [
                                'evento' => (int)$reintento['evento'],
                                'principal' => false,
                                'fecha' => (int)($reintento['fecha'] ?? 0)
                            ];
                        }
                    }
                }

                foreach ($eventos as $ev) {

                    $idLlamada = $ev['evento'];

                    $fila = $datos;

                    $fila['cubGC_avId'] = $doc['_id'] ?? null;
                    $fila['cubGC_llamadaId'] = $idLlamada;
                    $fila['cubGC_canal'] = 'TELEFONICA';
                    $fila['cubGC_fechaProgramacion'] = (int)($doc['av_fecha'] ?? 0);

                    //$fechaGestion = !empty($doc['av_fechaFinLlamada']) ? (int)$doc['av_fechaFinLlamada'] : (int)$doc['av_eventoFecha'];

                    //$fila['cubGC_fechaGestion'] = $fechaGestion;
                    $fila['cubGC_telefono'] = $doc['av_telefono'] ?? null;
                    $fila['cubGC_campaniaId'] = (int)($doc['av_campaniaId'] ?? 0);
                    $fila['cubGC_campaniaNombre'] = $doc['av_campaniaNombre'] ?? null;
                    $fila['cubGC_duracionGestionSeg'] = $doc['av_duracionSegundos'] ?? null;
                    $fila['cubGC_proveedor'] = $doc['av_proveedor'] ?? null;
                    $fila['cubGC_proveedorSip'] = $doc['av_proveedorSip'] ?? null;
                    $fila['cubGC_analisisCalidad'] = $analisisCalidad;
                    $fila['cubGC_tipificacion_compromiso'] = $compromiso;
                    $fila['cubGC_tipificacion_montoCompromiso'] = $montoCompromiso;

                    //==================================================
                    // Horario real de la llamada (CDR), via scllamadas.scLlamadas_cdrId.
                    // Independiente de si el evento ya tiene tipificación o no.
                    //==================================================

                    $dbCdr = new MYSQLDB();
                    $sqlCdr = $dbCdr->mkSQL(
                        "SELECT sc1.scCDR_horaIni AS horaInicio, sc1.scCDR_horaFin AS horaFin
                         FROM scllamadas
                         LEFT JOIN sccdr sc1 ON sc1.scCDR_id = scllamadas.scLlamadas_cdrId
                         WHERE scllamadas.scLlamadas_id = %N",
                        $idLlamada
                    );
                    $dbCdr->query($sqlCdr);
                    $rowCdr = $dbCdr->fetchRow() ?: [];
                    $fila['cubGC_horaInicio'] = (int)($rowCdr['horaInicio'] ?? 0);
                    $fila['cubGC_horaFin'] = (int)($rowCdr['horaFin'] ?? 0);

                    //==================================================
                    // Obtener tipificación
                    //==================================================

                    $respuesta1 = null;
                    $respuesta2 = null;
                    $resumen = null;
                    $rowSql = [];

                    // Si es principal, tomar primero de avProgramadas
                    if ($ev['principal']) {

                        $respuesta1 = !empty($doc['av_tipificacion']['respuesta1']) ? $doc['av_tipificacion']['respuesta1'] : null;
                        $respuesta2 = !empty($doc['av_tipificacion']['respuesta2']) ? $doc['av_tipificacion']['respuesta2'] : null;
                        $resumen = !empty($doc['av_tipificacion']['resumen']) ? $doc['av_tipificacion']['resumen'] : null;
                    }

                    // Consultar scllamadas solamente si:
                    // 1. No es principal (reintento), o
                    // 2. Falta R1, o
                    // 3. Falta R2
                    if (!$ev['principal'] || empty($respuesta1) || empty($respuesta2)) {

                        $dbLlam = new MYSQLDB();

                        $sql = $dbLlam->mkSQL("SELECT scLlamadas_ruta1,scLlamadas_ruta2,scLlamadas_texto, scLlamadas_fechaCreacion FROM scllamadas WHERE scLlamadas_id=%N", $idLlamada);
                        $dbLlam->query($sql);
                        $rowSql = $dbLlam->fetchRow() ?: [];
                    }

                    //==================================================
                    // Completar datos
                    //==================================================

                    if ($ev['principal']) {

                        //$fila['cubGC_fechaGestion'] = !empty($doc['av_fechaFinLlamada']) ? (int)$doc['av_fechaFinLlamada'] : (int)$doc['av_eventoFecha'];
                        //Nueva validación  de fecha, para que tome siempre la fecha de gestión, no de tipificación

                        if (!empty($doc['av_fechaFinLlamada'])) {
                            $fila['cubGC_fechaGestion'] = (int)$doc['av_fechaFinLlamada'];
                        } elseif (!empty($doc['av_fechaGeneraLlamada'])) {
                            $fila['cubGC_fechaGestion'] = (int)$doc['av_fechaGeneraLlamada'];
                        } elseif (!empty($doc['av_eventoFecha'])) {
                            $fila['cubGC_fechaGestion'] = (int)$doc['av_eventoFecha'];
                        } else {
                            $fila['cubGC_fechaGestion'] = (int)($doc['av_fecha'] ?? 0);
                        }

                        if (empty($respuesta1)) {
                            $respuesta1 = !empty($rowSql['scLlamadas_ruta1'] ?? null) ? $rowSql['scLlamadas_ruta1'] : null;
                        }

                        if (empty($respuesta2)) {
                            $respuesta2 = !empty($rowSql['scLlamadas_ruta2'] ?? null) ? $rowSql['scLlamadas_ruta2'] : null;
                        }

                        if (empty($resumen)) {
                            $resumen = !empty($rowSql['scLlamadas_texto'] ?? null) ? $rowSql['scLlamadas_texto'] : null;
                        }
                    } else {

                        if (!empty($rowSql['scLlamadas_fechaCreacion'] ?? null)) {
                            $fila['cubGC_fechaGestion'] = (int)$rowSql['scLlamadas_fechaCreacion'];
                        } elseif (!empty($ev['fecha'] ?? null)) {
                            $fila['cubGC_fechaGestion'] = (int)$ev['fecha'];
                        } else {
                            $fila['cubGC_fechaGestion'] = (int)($doc['av_fecha'] ?? 0);
                        }

                        $respuesta1 = !empty($rowSql['scLlamadas_ruta1'] ?? null) ? $rowSql['scLlamadas_ruta1'] : null;
                        $respuesta2 = !empty($rowSql['scLlamadas_ruta2'] ?? null) ? $rowSql['scLlamadas_ruta2'] : null;
                        $resumen = !empty($rowSql['scLlamadas_texto'] ?? null) ? $rowSql['scLlamadas_texto'] : null;
                    }

                    // Si no existe R1 ni R2 en ninguna fuente, no insertar
                    if (empty($respuesta1) && empty($respuesta2)) {
                        continue;
                    }

                    $fila['cubGC_nivelContacto'] = $respuesta1;
                    $fila['cubGC_tipificacion_respuesta1'] = $respuesta1;
                    $fila['cubGC_tipificacion_respuesta2'] = $respuesta2;
                    $fila['cubGC_resumen'] = $resumen;
                    //==================================================
                    // Buscar ponderación
                    //==================================================

                    $dbMap = new MYSQLDB();

                    $sql = $dbMap->mkSQL(
                        "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion
                                            FROM cobmaparbolgst WHERE cobMapArbolGst_campaniaId=%N
                                            AND cobMapArbolGst_ramaOrigenNombre=%Q AND cobMapArbolGst_carteraId=%N",
                        (int)$fila['cubGC_campaniaId'],
                        (string)$fila['cubGC_tipificacion_respuesta2'],
                        (int)$carteraId
                    );

                    $dbMap->query($sql);

                    if ($rowMap = $dbMap->fetchRow()) {

                        $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
                        $fila['cubGC_ponderacion'] = (int)($rowMap['cobMapArbolGst_ponderacion'] ?? 0);
                    }

                    // Insertar / Actualizar cubo

                    $condCubo = [
                        'cubGC_numFactura' => (string)$fila['cubGC_numFactura'],
                        'cubGC_carteraId' => (string)$fila['cubGC_carteraId'],
                        'cubGC_avId'       => new MongoDB\BSON\ObjectId($fila['cubGC_avId']),
                        'cubGC_llamadaId' => (int)$fila['cubGC_llamadaId']
                    ];

                    if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)) {

                        $mdbCubo->guardar(self::COLLECTION_CUBO, $fila);

                        $insertado = true;
                    } else {
                        $mdbCubo->actualizar(self::COLLECTION_CUBO, $condCubo, $fila);
                    }
                    $this->auxQuitarLlamadaSinGestion($idLlamada, $mdbCubo);
                }
            }
        }

        if ($tabla === 'avProgramadasWhatsApp') {
            //Buscar registros en mongo avProgramadasWhatsapp y añadir al cubo
            $mdbAvProgWhats = new MYMONGODB();
            $condAvProgWhats = [
                'ws_factura'   => (string) $datos['cubGC_numFactura'],
                'ws_carteraId'   => (int) $datos['cubGC_carteraId'],
                'ws_fecha' => ['$gte' => $datos['cubGC_fechaInicio']]
            ];
            if (!empty($idGestion)) {
                $condAvProgWhats['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }
            $datAvProgWhats = [
                '_id',
                'ws_fecha',
                'ws_estadoEnvioFecha',
                'ws_numeroWP',
                'ws_campaniaId',
                'ws_campaniaNombre',
                'ws_duracionSegundos',
                'ws_tipificacion',
                'ws_fechaPeriodo',
                'ws_evento'
            ];
            $mdbAvProgWhats->buscar('avProgramadasWhatsApp', $condAvProgWhats, $datAvProgWhats);
            while ($doc = $mdbAvProgWhats->siguiente()) {
                $fila = $datos;
                $fila['cubGC_avId'] = $doc['_id'] ?? null;
                $fila['cubGC_canal'] = 'WHATSAPP';
                $fila['cubGC_fechaGestion'] = (int)($doc['ws_estadoEnvioFecha'] ?? 0);
                $fila['cubGC_fechaProgramacion'] = (int)($doc['ws_fecha'] ?? 0);
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
                $this->auxQuitarLlamadaSinGestion((int)($doc['ws_evento'] ?? 0), $mdbCubo);
            }
        }
        //Buscar registros en mongo cbEnvioMails y añadir al cubo
        if ($tabla === 'cbEnvioMails') {
            $mdbEnvMail = new MYMONGODB();
            $condEnvMail = [
                'cem_susFactura'   => (string) $datos['cubGC_numFactura'],
                'cem_susCarteraId'   => (int) $datos['cubGC_carteraId'],
                'cem_susFechaEnvio' => ['$gte' => $datos['cubGC_fechaInicio']]
            ];
            if (!empty($idGestion)) {
                $condEnvMail['_id'] = new MongoDB\BSON\ObjectId($idGestion);
            }
            $datEnvMail = [
                '_id',
                'cem_susFechaEnvio',
                'cem_susFechaAsignacion',
                'cem_susEmail',
                'cem_susCampaniaId',
                'cem_susCampaniaNombre',
                'cem_susLlamadaId',
                'cem_susFechaPeriodo',
                'abierto',
                'cantidad_abierto'

            ];
            $mdbEnvMail->buscar('cbEnvioMails', $condEnvMail, $datEnvMail);
            while ($doc = $mdbEnvMail->siguiente()) {
                $fila = $datos;
                $fila['cubGC_avId'] = $doc['_id'] ?? null;
                $fila['cubGC_canal'] = 'EMAIL';
                $fila['cubGC_fechaGestion'] =  (int)$doc['cem_susFechaEnvio'] ?? 0;
                $fila['cubGC_fechaProgramacion'] = (int)($doc['cem_susFechaAsignacion'] ?? 0);
                $fila['cubGC_email'] = $doc['cem_susEmail'] ?? null;
                $fila['cubGC_campaniaId'] = (int)$doc['cem_susCampaniaId'] ?? null;
                $fila['cubGC_campaniaNombre'] = $doc['cem_susCampaniaNombre'] ?? null;
                $fila['cubGC_duracionGestionSeg'] = 0;
                // Tal cual vienen en cbEnvioMails: abierto=0 si no se abrió o el unixtime de apertura.
                $fila['cubGC_abierto'] = $doc['abierto'] ?? 0;
                $fila['cubGC_cantidad_abierto'] = $doc['cantidad_abierto'] ?? 0;
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
                    $this->auxQuitarLlamadaSinGestion((int)($doc['cem_susLlamadaId'] ?? 0), $mdbCubo);
                }
            }
        }

        if ($tabla === self::TABLA_LLAMADAS) {
            $insertado = $this->auxGuardarLlamadaSinGestion($datos, $data['llamada'], (int)$data['campaniaId'], $mdbCubo) || $insertado;
        }
        if ($insertado) {
            return [];
        }
        return $datos;
    }

    public function recreate(): void
    {
        $limit = 20000;
        $mdb = new MYMONGODB();

        // Borra la colección completa antes de reconstruirla desde cero
        $mdb->borrarColeccion(self::COLLECTION_CUBO);
        // Con la colección vacía no hay filas 'scllamadas' que quitar, y sin índices cada borrado recorrería el cubo.
        $this->quitarLlamadasSinGestion = false;

        // Tablas de gestiones que alimentan el cubo (las mismas que atiende process())
        $tablas = ['avProgramadas', 'avProgramadasWhatsApp', 'cbEnvioMails'];

        foreach ($tablas as $tabla) {

            $config = $this->getConfigByTabla($tabla);
            if (!$config) continue;

           
            $condOrigen = [];
            // SOLO para emails
            if ($tabla === 'cbEnvioMails') {
                $condOrigen['cem_susErrorEnvio'] = 0;
            }

            $skip = 0;

            for ($i = 0; $i < 400; $i++) {

                $mdbOrigen = new MYMONGODB();
                $mdbOrigen->buscar($tabla, $condOrigen, $config['campos'], ['_id' => 1], $limit, $skip);

                $numRows = 0;

                while ($doc = $mdbOrigen->siguiente()) {

                    $factura   = $doc[$config['factura']] ?? null;
                    $cartera   = $doc[$config['cartera']] ?? null;
                    $idGestion = $doc['_id'] ?? null;

                    if ($factura !== null && $idGestion !== null) {

                        $mdbCred = new MYMONGODB();
                        $condCred = [
                            'cre_factura'   => (string)$factura,
                            'cre_carteraId' => (string)$cartera
                        ];
                        $mdbCred->buscar('cbCreditos', $condCred, [], [], 1);
                        while ($row = $mdbCred->siguiente()) {
                            $this->createData([
                                'row'       => $row,
                                'idGestion' => $idGestion,
                                'tabla'     => $tabla,
                            ]);
                        }
                    }

                    $numRows++;
                }

                if ($numRows < $limit) {
                    break;
                }

                $skip += $limit;
            }
        }

        // Índices antes de las llamadas sin gestión, para que la búsqueda de duplicados por fila no recorra el cubo.
        $this->createIndices($mdb);

        // Las llamadas sin gestión van al final: insertarLlamadasSinGestion() consulta las
        // colecciones de origen, así que no depende de lo que ya se haya reconstruido.
        $this->quitarLlamadasSinGestion = true;
        $this->insertarLlamadasSinGestion();
    }

    //==================================================
    // FUNCIONES PRINCIPALES: llamadas de scllamadas sin gestión
    //==================================================

    /**
     * Inserta en el cubo las llamadas de scllamadas que no tienen gestión en avProgramadas,
     * avProgramadasWhatsApp ni cbEnvioMails, marcadas con cubGC_origen = 'scllamadas'.
     * La cartera sale de la campaña (scLlamadas_ruta0 -> scramas -> cobcarteraramas) y se
     * confirma con el crédito del cliente (scLlamadas_usuarioId -> cbCreditos.usUsuarios_id).
     *
     * @param int   $desde Timestamp mínimo de scLlamadas_fechaCreacion (0 = sin límite).
     * @param int   $hasta Timestamp máximo, exclusivo (0 = sin límite).
     * @param array $ids   scLlamadas_id a procesar; si viene, se ignora el rango de fechas.
     * @return array{revisadas:int, conGestion:int, sinCampania:int, sinCredito:int, ambiguas:int, fueraDePeriodo:int, insertadas:int, actualizadas:int}
     */
    public function insertarLlamadasSinGestion(int $desde = 0, int $hasta = 0, array $ids = []): array
    {
        $resumen = [
            'revisadas' => 0, 'conGestion' => 0, 'sinCampania' => 0, 'sinCredito' => 0,
            'ambiguas' => 0, 'fueraDePeriodo' => 0, 'insertadas' => 0, 'actualizadas' => 0,
        ];
        $campanias = $this->auxCampaniasPorNombre();
        if (empty($campanias)) {
            return $resumen;
        }

        if (!empty($ids)) {
            $llamadas = [];
            foreach ($ids as $idLlamada) {
                $llamadas = array_merge($llamadas, $this->auxLeerLlamadas(0, 0, 0, 1, (int)$idLlamada));
            }
            $this->auxProcesarLoteLlamadas($llamadas, $campanias, $resumen);
            return $resumen;
        }

        $ultimoId = 0;
        do {
            $llamadas = $this->auxLeerLlamadas($desde, $hasta, $ultimoId, self::LOTE_LLAMADAS);
            if (empty($llamadas)) {
                break;
            }
            $ultimoId = (int)$llamadas[count($llamadas) - 1]['scLlamadas_id'];
            $this->auxProcesarLoteLlamadas($llamadas, $campanias, $resumen);
        } while (count($llamadas) === self::LOTE_LLAMADAS);

        return $resumen;
    }

    //==================================================
    // FUNCIONES AUXILIARES: llamadas de scllamadas sin gestión
    //==================================================

    /**
     * Campañas que tienen cartera asociada, agrupadas por nombre de rama.
     * Un mismo nombre puede tener varias parejas (rama repetida o rama en varias carteras).
     *
     * @return array<string, array<int, array{campaniaId:int, carteraId:string}>>
     */
    private function auxCampaniasPorNombre(): array
    {
        $db = new MYSQLDB();
        $db->query("SELECT scRamas_id, scRamas_nombre, cobCarteraRamas_carteraIdfk
                    FROM scramas
                    INNER JOIN cobcarteraramas ON cobCarteraRamas_ramaIdfk = scRamas_id");
        $campanias = [];
        while ($row = $db->fetchRow()) {
            $campanias[$this->auxClaveCampania($row['scRamas_nombre'] ?? '')][] = [
                'campaniaId' => (int)$row['scRamas_id'],
                'carteraId'  => (string)$row['cobCarteraRamas_carteraIdfk'],
            ];
        }
        return $campanias;
    }

    // Normaliza el nombre de campaña igual que lo compara MySQL (sin distinguir mayúsculas ni espacios extremos).
    private function auxClaveCampania(?string $nombre): string
    {
        return strtoupper(trim((string)$nombre));
    }

    /**
     * Lee llamadas tipificadas de scllamadas, con los mismos filtros de la consulta de seguimiento.
     *
     * @param int $desde       Timestamp mínimo de creación (0 = sin límite).
     * @param int $hasta       Timestamp máximo exclusivo (0 = sin límite).
     * @param int $despuesDeId Solo llamadas con id mayor a este (paginación por id).
     * @param int $limite      Cantidad máxima de filas.
     * @param int $soloId      Si es mayor a 0, solo esa llamada.
     * @return array<int, array<string, mixed>>
     */
    private function auxLeerLlamadas(int $desde, int $hasta, int $despuesDeId, int $limite, int $soloId = 0): array
    {
        $db = new MYSQLDB();
        $sql = $db->mkSQL(
            "SELECT l.scLlamadas_id, l.scLlamadas_usuarioId, l.scLlamadas_fechaCreacion, l.scLlamadas_ruta0,
                    l.scLlamadas_ruta1, l.scLlamadas_ruta2, l.scLlamadas_texto,
                    sc1.scCDR_callerId, sc1.scCDR_horaIni, sc1.scCDR_horaFin, sc1.scCDR_duration,
                    CONCAT(IFNULL(scColaProgramadas_area, ''), IFNULL(scColaProgramadas_numero, '')) AS numeroCola
             FROM scllamadas l
             LEFT JOIN sccdr sc1 ON sc1.scCDR_id = l.scLlamadas_cdrId
             LEFT JOIN sccolaprogramadas ON scColaProgramadas_id = l.scLlamadas_colaProgramadasId
             WHERE l.scLlamadas_id > %N
               AND (%N = 0 OR l.scLlamadas_id = %N)
               AND (%N = 0 OR l.scLlamadas_fechaCreacion >= %N)
               AND (%N = 0 OR l.scLlamadas_fechaCreacion < %N)
               AND IFNULL(l.scLlamadas_ruta1, '') NOT IN ('', 'Conexión exitosa')
               AND IFNULL(l.scLlamadas_ruta2, '') <> 'Mail No Enviado'
               AND IFNULL(l.scLlamadas_texto, '') NOT LIKE 'Llamada desprogramada%%'
               AND IFNULL(l.scLlamadas_texto, '') NOT IN ('WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR')
             ORDER BY l.scLlamadas_id
             LIMIT %N",
            $despuesDeId,
            $soloId, $soloId,
            $desde, $desde,
            $hasta, $hasta,
            $limite
        );
        $db->query($sql);
        $llamadas = [];
        while ($row = $db->fetchRow()) {
            $llamadas[] = $row;
        }
        return $llamadas;
    }

    /**
     * Procesa un lote de llamadas: descarta las que ya tienen gestión, resuelve
     * campaña/cartera/crédito y las envía a createData().
     *
     * @param array $llamadas  Filas de auxLeerLlamadas().
     * @param array $campanias Resultado de auxCampaniasPorNombre().
     * @param array $resumen   Contadores, se actualizan por referencia.
     */
    private function auxProcesarLoteLlamadas(array $llamadas, array $campanias, array &$resumen): void
    {
        if (empty($llamadas)) {
            return;
        }
        $resumen['revisadas'] += count($llamadas);
        $conGestion = $this->auxLlamadasConGestion(array_map(fn($l) => (int)$l['scLlamadas_id'], $llamadas));

        foreach ($llamadas as $llamada) {
            $idLlamada = (int)$llamada['scLlamadas_id'];
            if (isset($conGestion[$idLlamada])) {
                $resumen['conGestion']++;
                continue;
            }
            $candidatas = $campanias[$this->auxClaveCampania($llamada['scLlamadas_ruta0'] ?? '')] ?? [];
            if (empty($candidatas)) {
                $resumen['sinCampania']++;
                continue;
            }
            $eleccion = $this->auxElegirCredito($llamada, $candidatas);
            if ($eleccion['estado'] === 'sinCredito') {
                $resumen['sinCredito']++;
                continue;
            }
            if ($eleccion['estado'] === 'ambigua') {
                $resumen['ambiguas']++;
                $this->insertLog('LLAMADA_AMBIGUA;' . $idLlamada . ';' . implode(',', $eleccion['carteras']));
                continue;
            }

            $this->estadoLlamada = '';
            $this->createData([
                'row'        => $eleccion['credito'],
                'idGestion'  => null,
                'tabla'      => self::TABLA_LLAMADAS,
                'llamada'    => $llamada,
                'campaniaId' => $eleccion['campaniaId'],
            ]);
            if (isset($resumen[$this->estadoLlamada])) {
                $resumen[$this->estadoLlamada]++;
            }
        }
    }

    /**
     * Ids de llamada que ya tienen gestión en alguna colección de origen del cubo.
     *
     * @param int[] $ids scLlamadas_id del lote.
     * @return array<int, true>
     */
    private function auxLlamadasConGestion(array $ids): array
    {
        // El id puede estar guardado como número o como texto según la colección.
        $variantes = array_merge($ids, array_map('strval', $ids));
        $buscados = array_flip($ids);
        $conGestion = [];
        $mdb = new MYMONGODB();

        foreach (self::CAMPOS_LLAMADA_GESTION as $coleccion => $campos) {
            $condicion = ['$or' => array_map(fn($campo) => [$campo => ['$in' => $variantes]], $campos)];
            $proyeccion = array_map(fn($campo) => explode('.', $campo)[0], $campos);
            $mdb->buscar($coleccion, $condicion, $proyeccion);
            while ($doc = $mdb->siguiente()) {
                foreach ($this->auxIdsLlamadaDelDocumento($doc, $campos) as $idLlamada) {
                    if (isset($buscados[$idLlamada])) {
                        $conGestion[$idLlamada] = true;
                    }
                }
            }
        }
        return $conGestion;
    }

    /**
     * Extrae los ids de llamada de un documento de gestión, incluidos los de un arreglo
     * (campo con punto, ej. 'av_detalleReintentos.evento').
     *
     * @param array    $doc    Documento de la colección de gestión.
     * @param string[] $campos Campos de CAMPOS_LLAMADA_GESTION para esa colección.
     * @return int[]
     */
    private function auxIdsLlamadaDelDocumento(array $doc, array $campos): array
    {
        $ids = [];
        foreach ($campos as $campo) {
            $partes = explode('.', $campo);
            if (count($partes) === 1) {
                $ids[] = (int)($doc[$campo] ?? 0);
                continue;
            }
            foreach (($doc[$partes[0]] ?? []) as $elemento) {
                $ids[] = (int)($elemento[$partes[1]] ?? 0);
            }
        }
        return $ids;
    }

    /**
     * Elige el crédito (y con él cartera y campaña) al que corresponde la llamada.
     * Entre las parejas campaña-cartera candidatas solo quedan las carteras donde el cliente
     * tiene crédito; si queda más de una, desempata primero por ponderación configurada
     * para la tipificación y después por período vigente en la fecha de la llamada.
     *
     * @param array $llamada    Fila de auxLeerLlamadas().
     * @param array $candidatas Parejas ['campaniaId', 'carteraId'] de la campaña.
     * @return array{estado:string, credito?:array, campaniaId?:int, carteras?:string[]}
     *               estado: 'ok' | 'sinCredito' | 'ambigua'
     */
    private function auxElegirCredito(array $llamada, array $candidatas): array
    {
        $usuarioId = (int)($llamada['scLlamadas_usuarioId'] ?? 0);
        if ($usuarioId <= 0) {
            return ['estado' => 'sinCredito'];
        }
        $carteras = array_values(array_unique(array_column($candidatas, 'carteraId')));

        // Si el cliente tuviera varias facturas en la cartera, se queda con la del período más reciente.
        $creditos = [];
        $mdb = new MYMONGODB();
        $mdb->buscar('cbCreditos', [
            'usUsuarios_id' => ['$in' => [$usuarioId, (string)$usuarioId]],
            'cre_carteraId' => ['$in' => $carteras],
        ], [], ['cre_fechaPeriodo' => -1]);
        while ($doc = $mdb->siguiente()) {
            $carteraId = (string)($doc['cre_carteraId'] ?? '');
            if (!isset($creditos[$carteraId])) {
                $creditos[$carteraId] = $doc;
            }
        }

        $opciones = [];
        foreach ($candidatas as $candidata) {
            if (isset($creditos[$candidata['carteraId']])) {
                $opciones[] = $candidata + ['credito' => $creditos[$candidata['carteraId']]];
            }
        }
        if (empty($opciones)) {
            return ['estado' => 'sinCredito'];
        }

        $ruta2 = (string)($llamada['scLlamadas_ruta2'] ?? '');
        $fecha = (int)($llamada['scLlamadas_fechaCreacion'] ?? 0);
        $opciones = $this->auxFiltrarOpciones($opciones, fn($o) => $this->auxBuscarPonderacion($o['campaniaId'], $ruta2, (int)$o['carteraId']) !== null);
        $opciones = $this->auxFiltrarOpciones($opciones, fn($o) => $this->auxPeriodoVigente($o['credito'], $fecha));

        if (count($opciones) > 1) {
            return ['estado' => 'ambigua', 'carteras' => array_column($opciones, 'carteraId')];
        }
        return ['estado' => 'ok', 'credito' => $opciones[0]['credito'], 'campaniaId' => $opciones[0]['campaniaId']];
    }

    // Aplica un criterio de desempate solo si hay más de una opción y el criterio deja al menos una.
    private function auxFiltrarOpciones(array $opciones, callable $criterio): array
    {
        if (count($opciones) <= 1) {
            return $opciones;
        }
        $filtradas = array_values(array_filter($opciones, $criterio));
        return empty($filtradas) ? $opciones : $filtradas;
    }

    /**
     * Ponderación configurada en cobmaparbolgst para campaña + tipificación (ruta2) + cartera.
     *
     * @return array{cobMapArbolGst_id:mixed, cobMapArbolGst_ponderacion:mixed}|null
     */
    private function auxBuscarPonderacion(int $campaniaId, string $ruta2, int $carteraId): ?array
    {
        $db = new MYSQLDB();
        $sql = $db->mkSQL(
            "SELECT cobMapArbolGst_id, cobMapArbolGst_ponderacion FROM cobmaparbolgst
             WHERE cobMapArbolGst_campaniaId=%N AND cobMapArbolGst_ramaOrigenNombre=%Q AND cobMapArbolGst_carteraId=%N",
            $campaniaId,
            $ruta2,
            $carteraId
        );
        $db->query($sql);
        $row = $db->fetchRow();
        return $row ?: null;
    } 

    // Indica si el período activo de la cartera del crédito cubre la fecha dada (mismo criterio que createData()).
    private function auxPeriodoVigente(array $credito, int $fecha): bool
    {
        $mdb = new MYMONGODB();
        $mdb->buscar('control_carga_periodo', [
            'cartera' => (int)($credito['cre_carteraId'] ?? 0),
            'periodo' => (int)($credito['cre_periodo'] ?? 0),
            'fecha'   => (int)($credito['cre_fechaPeriodo'] ?? 0),
            'activo'  => 1,
        ], ['fecha', 'fechaFin'], ['_id' => -1], 1);
        $periodo = $mdb->siguiente();
        if (!$periodo) {
            return false;
        }
        return $fecha >= (int)($periodo['fecha'] ?? 0) && $fecha <= (int)($periodo['fechaFin'] ?? 0);
    }

    /**
     * Arma y guarda (o actualiza) en el cubo la fila de una llamada sin gestión.
     * Igual que en avProgramadas, solo entran llamadas desde el inicio del período del crédito.
     *
     * @param array      $datos      Datos base del crédito ya armados por createData().
     * @param array      $llamada    Fila de auxLeerLlamadas().
     * @param int        $campaniaId scRamas_id de la campaña elegida.
     * @param MYMONGODB  $mdbCubo    Conexión usada por createData() para el cubo.
     * @return bool true si se insertó una fila nueva.
     */
    private function auxGuardarLlamadaSinGestion(array $datos, array $llamada, int $campaniaId, MYMONGODB $mdbCubo): bool
    {
        $fechaGestion = (int)($llamada['scLlamadas_fechaCreacion'] ?? 0);
        if ($fechaGestion < (int)$datos['cubGC_fechaInicio']) {
            $this->estadoLlamada = 'fueraDePeriodo';
            return false;
        }

        $ruta2 = !empty($llamada['scLlamadas_ruta2']) ? $llamada['scLlamadas_ruta2'] : null;
        $fila = $datos;
        $fila['cubGC_avId'] = null;
        $fila['cubGC_llamadaId'] = (int)$llamada['scLlamadas_id'];
        $fila['cubGC_canal'] = self::CANAL_LLAMADAS;
        $fila['cubGC_fechaGestion'] = $fechaGestion;
        $fila['cubGC_fechaProgramacion'] = $fechaGestion;
        $fila['cubGC_telefono'] = !empty($llamada['numeroCola']) ? $llamada['numeroCola'] : ($llamada['scCDR_callerId'] ?? null);
        $fila['cubGC_campaniaId'] = $campaniaId;
        $fila['cubGC_campaniaNombre'] = $llamada['scLlamadas_ruta0'] ?? null;
        $fila['cubGC_duracionGestionSeg'] = (int)($llamada['scCDR_duration'] ?? 0);
        $fila['cubGC_horaInicio'] = (int)($llamada['scCDR_horaIni'] ?? 0);
        $fila['cubGC_horaFin'] = (int)($llamada['scCDR_horaFin'] ?? 0);
        $fila['cubGC_nivelContacto'] = $llamada['scLlamadas_ruta1'];
        $fila['cubGC_tipificacion_respuesta1'] = $llamada['scLlamadas_ruta1'];
        $fila['cubGC_tipificacion_respuesta2'] = $ruta2;
        $fila['cubGC_tipificacion_compromiso'] = null;
        $fila['cubGC_tipificacion_montoCompromiso'] = null;
        $fila['cubGC_resumen'] = !empty($llamada['scLlamadas_texto']) ? $llamada['scLlamadas_texto'] : null;

        $rowMap = $this->auxBuscarPonderacion($campaniaId, (string)$ruta2, (int)$datos['cubGC_carteraId']);
        if ($rowMap !== null) {
            $fila['cubGC_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
            $fila['cubGC_ponderacion'] = (int)($rowMap['cobMapArbolGst_ponderacion'] ?? 0);
        }

        $condCubo = [
            'cubGC_numFactura' => (string)$fila['cubGC_numFactura'],
            'cubGC_carteraId'  => (string)$fila['cubGC_carteraId'],
            'cubGC_origen'     => self::TABLA_LLAMADAS,
            'cubGC_llamadaId'  => $fila['cubGC_llamadaId'],
        ];
        if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)) {
            $mdbCubo->guardar(self::COLLECTION_CUBO, $fila);
            $this->estadoLlamada = 'insertadas';
            return true;
        }
        $mdbCubo->actualizar(self::COLLECTION_CUBO, $condCubo, $fila);
        $this->estadoLlamada = 'actualizadas';
        return false;
    }

    // Cuando llega la gestión real de una llamada, borra la fila 'scllamadas' que se hubiera insertado antes para ella.
    private function auxQuitarLlamadaSinGestion(int $idLlamada, MYMONGODB $mdbCubo): void
    {
        if ($idLlamada <= 0 || !$this->quitarLlamadasSinGestion) {
            return;
        }
        $mdbCubo->borrar(self::COLLECTION_CUBO, [
            'cubGC_origen'    => self::TABLA_LLAMADAS,
            'cubGC_llamadaId' => $idLlamada,
        ]);
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
            'cubGC_llamadaId',
        ];
        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}
?><? //_FIN_DE_ARCHIVO 
    ?>