<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGasignacionesGestion extends AbstractCuboPlugin {
    public const COLLECTION_CUBO = 'cuAsignacionesGestionAP';

    // Mapeo del canal tal como viene en cuGestionCobranzaMysql (cubGC_canal)
    // hacia el codigo con el que se guarda en el cubo.
    protected const MAPA_CANALES = [
        'TELEFONICA' => 'AV',
        'EMAIL'      => 'EMAIL',
        'WHATSAPP'   => 'WHATSAPP',
    ];

    // Cache de tramos de mora (misma fuente que el ETL de cbCreditos)
    private ?array $tramosMora = null;

    protected function process($ids, $accion): void
    {
        //trigger_error("Factura ingresa cuAsignacionesGestionAP" . print_r($ids,true));
        //trigger_error("Factura accion cuAsignacionesGestionAP" . print_r($accion,true));
        if (!is_array($ids) || empty($ids)) return;
        $this->tramosMora = null;
        $tablas = $this->organizeByTable($ids);
        $mdb    = new MYMONGODB();
        $mdbCubo    = new MYMONGODB();
        foreach ($tablas as $tabla => $regIds) {
            $condicion = $this->buildCondition($tabla, $regIds);
            if (!($condicion['mongo'])) continue;
            switch ($accion) {
                case 'ADD':

                    //Se agrega condición para que solo ingresen registros activos(cre_inactivo:0)

                    //trigger_error("Ingresa condicion " . print_r($condicion, true));

                    $condCbCred = [
                        '$and' => [
                            $condicion['mongo'],
                            ['cre_inactivo' => (int)0]
                        ]
                    ];

                    //trigger_error("condCbCred " . print_r($condCbCred, true));



                    $mdb->buscar('cbCreditos', $condCbCred);
                    while ($doc = $mdb->siguiente()) {
                        // if ($doc['cre_factura'] && $doc['cre_carteraId'] && isset($doc['cre_periodo']) && isset($doc['cre_fechaPeriodo'])) {
                        $criteria = [
                            'cubAG_numFactura'    => (string)$doc['cre_factura'],
                            'cubAG_carteraId'     => (string)$doc['cre_carteraId'],
                            'cubAG_fechaPeriodo' => (int)$doc['cre_fechaPeriodo'],
                        ];
                        $newRow = $this->createData($doc);
                        if ($mdbCubo->buscar(self::COLLECTION_CUBO, $criteria, ['_id'], [], 1)) {
                            //trigger_error("Ingreso a buscar" . (string)$doc['cre_factura']);
                            $mdbCubo->actualizar(self::COLLECTION_CUBO, $criteria, $newRow);
                        } else {
                            //trigger_error("Ingreso a caso contrario" . (string)$doc['cre_factura']);
                            if ($tabla == 'cbCreditos') {
                                //capital al primer ingreso a este periodo, solo se guarda aqui
                                $newRow['cubAG_capitalInicialPeriodo'] = (float)($doc['cre_saldoCapital'] ?? 0);
                                //calificacion acelerada: tramo segun la mora proyectada al fin del periodo, solo se guarda aqui
                                $acel = $this->calificacionAcelerada($doc, (int)$newRow['cubAG_fechaInicio'], (int)$newRow['cubAG_fechaFin']);
                                $newRow['cubAG_calificacionAcelerada'] = $acel['calificacion'];
                                $newRow['cubAG_diasMoraProyectados'] = $acel['dias'];
                                //fecha de asignacion: solo se guarda en el primer ingreso al periodo
                                $newRow['cubAG_fechaAsignacion'] = (int)($doc['cre_fechaCarga'] ?? 0);
                                $mdbCubo->guardar(self::COLLECTION_CUBO, $newRow);
                            } else {
                                //trigger_error("Registro no existe: Tabla: ".$tabla." Información: ".  print_r($criteria, true) );
                            }
                        }
                        //}
                    }
                    break;

                default:
                    //trigger_error("Acción no reconocida {$accion}");
                    break;
            }
        }
    }

    protected function buildCondition($tabla, $regIds): array
    {
        $idsStr = "'" . implode("','", $regIds) . "'";
        return match ($tabla) {
            'cbCreditos'            => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cre_factura' => ['$in' => $regIds]]],
            'cuGestionCobranzaMysql'     => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cre_factura' => ['$in' => $regIds]]],
            'cbPagos'               => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cre_factura' => ['$in' => $regIds]]],
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
            'sponsor'                   => ['mdb' => 'cubAG_sponsor',        'defaultValue' => '(Sin sponsor)'],
            'nombre_cliente'            => ['mdb' => 'cubAG_nombres',        'defaultValue' => '(Sin nombre cliente)'],
            'apellido_cliente'          => ['mdb' => 'cubAG_apellidos',        'defaultValue' => '(Sin apellido cliente)'],
            'cedula_cliente'            => ['mdb' => 'cubAG_cedula',        'defaultValue' => '(Sin apellido cliente)'],
            'edad'                      => ['mdb' => 'cubAG_edad',        'defaultValue' => '(Sin edad)'],
            'sexo'                      => ['mdb' => 'cubAG_sexo',        'defaultValue' => '(Sin sexo)'],
            'estado_civil'              => ['mdb' => 'cubAG_estadoCivil',        'defaultValue' => '(Sin estado civil)'],
            'dirección'                 => ['mdb' => 'cubAG_direccion',        'defaultValue' => '(Sin dirección)'],
            'cartera'                   => ['mdb' => 'cubAG_carteraNombre',        'defaultValue' => '(Sin cartera)'],
            'registro_operacion'        => ['mdb' => 'cubAG_numFactura',        'defaultValue' => '(Sin registro operación)'],
            'ciudad'                    => ['mdb' => 'cubAG_ciudad',        'defaultValue' => '(Sin ciudad)'],
            'riesgo'                    => ['mdb' => 'cubAG_riesgo',        'defaultValue' => '(Sin riesgo)'],
            'calificacion_acelerada'    => ['mdb' => 'cubAG_calificacionAcelerada', 'defaultValue' => '(Sin calificación acelerada)'],
            'dias_mora_proyectados'     => ['mdb' => 'cubAG_diasMoraProyectados', 'defaultValue' => '(Sin días mora proyectados)'],
            'producto'                  => ['mdb' => 'cubAG_producto',        'defaultValue' => '(Sin producto)'],
            'capital_actual'            => ['mdb' => 'cubAG_capitalActual',        'defaultValue' => '(Sin capital actual)'],
            'capital_inicial_periodo'   => ['mdb' => 'cubAG_capitalInicialPeriodo', 'defaultValue' => '(Sin capital inicial periodo)'],
            'deuda_neta_actual'         => ['mdb' => 'cubAG_deudaNetaActual',        'defaultValue' => '(Sin deuda neta actaul)'],
            'dias_mora'                 => ['mdb' => 'cubAG_diasMora',        'defaultValue' => '(Sin días mora)'],
            'ciclo'                     => ['mdb' => 'cubAG_ciclo',        'defaultValue' => '(Sin ciclo)'],
            'saldo_capital_inicial'     => ['mdb' => 'cubAG_capitalInicial',        'defaultValue' => '(Sin saldo capital inicial)'],
            'deuda_neta_inicial'        => ['mdb' => 'cubAG_deudaNetaInicial',        'defaultValue' => '(Sin deuda neta inicial)'],
            'fecha_inicio'              => ['mdb' => 'cubAG_fechaInicio',        'defaultValue' => '(Sin fecha inicio)'],
            'fecha_fin'                 => ['mdb' => 'cubAG_fechaFin',        'defaultValue' => '(Sin fecha fin)'],
            'fecha_periodo'             => ['mdb' => 'cubAG_fechaPeriodo',        'defaultValue' => '(Sin fecha periodo)'],
            'fecha_asignacion'          => ['mdb' => 'cubAG_fechaAsignacion',   'defaultValue' => '(Sin fecha asignación)'],
            'cartera_gestionada'        => ['mdb' => 'cubAG_gestionada',        'defaultValue' => '(Sin cartera gestionada)'],
            'monto_pago'                => ['mdb' => 'cubAG_montoTotalPago',        'defaultValue' => '(Sin monto pago)'],
            'compromiso'                => ['mdb' => 'cubAG_compromiso',        'defaultValue' => '(Sin compromiso)'],
            'monto_compromiso'          => ['mdb' => 'cubAG_montoCompromiso',        'defaultValue' => '(Sin monto compromiso)'],
            'telefono'                   => ['mdb' => 'cubAG_telefono',           'defaultValue' => '(Sin telefono)'],
            'email'                      => ['mdb' => 'cubAG_email',              'defaultValue' => '(Sin email)'],
            'ultima_gestion_canal'      => ['mdb' => 'cubAG_ultimaGestion_canal',            'defaultValue' => '(Sin canal última gestión)'],
            'ultima_gestion_tipificacion1' => ['mdb' => 'cubAG_ultimaGestion_tipificacion1',    'defaultValue' => '(Sin tipificación 1 última gestión)'],
            'ultima_gestion_tipificacion2' => ['mdb' => 'cubAG_ultimaGestion_tipificacion2',    'defaultValue' => '(Sin tipificación 2 última gestión)'],
            'ultima_gestion_fecha'      => ['mdb' => 'cubAG_ultimaGestion_fechaGestion',     'defaultValue' => '(Sin fecha última gestión)'],
            'ultima_gestion_compromiso' => ['mdb' => 'cubAG_ultimaGestion_compromiso',       'defaultValue' => '(Sin compromiso última gestión)'],
            'ultima_gestion_monto_compromiso' => ['mdb' => 'cubAG_ultimaGestion_montoCompromiso',  'defaultValue' => '(Sin monto compromiso última gestión)'],
            'intensidad_total'          => ['mdb' => 'cubAG_intensidad_total',               'defaultValue' => '(Sin intensidad)'],

        ];
        return $campos;
    }

    protected function createData($row): array
    {
        $datos = [
            //mongo cbCreditos
            'cubAG_numFactura'                        => $row['cre_factura'] ?? '',
            'cubAG_carteraId'                         => $row['cre_carteraId'] ?? '',
            'cubAG_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
            'cubAG_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
            'cubAG_cbcreId'                           => $row['_id'] ?? null,
            'cubAG_sponsor'                           => 'BANCO DEL PACÍFICO',
            'cubAG_nombres'                           => $row['cre_nombres'] ?? '',
            'cubAG_apellidos'                         => $row['cre_apellidos'] ?? '',
            'cubAG_cedula'                            => $row['cre_cedula'] ?? '',
            'cubAG_riesgo'                            => $row['cre_calificacion'] ?? '',
            'cubAG_deudaNetaActual'                   => (float) ($row['cre_deudaNeta'] ?? 0),
            'cubAG_diasMora'                          => (int) ($row['cre_diasMoraFactura'] ?? 0),
            'cubAG_producto'                          => $row['cre_producto'] ?? '',
            'cubAG_diaProbable'                       => $row['cre_diaProbable'] ?? '',
            'cubAG_mejorBandaHoraria'                 => $row['cre_mejorBandaHoraria'] ?? '',
            'cubAG_regularidad'                       => $row['cre_regularidad'] ?? '',
            'cubAG_tramoSaldo'                        => $row['cre_tramoSaldo'] ?? '',
            'cubAG_tramoMora'                         => $row['cre_tramoMora'] ?? '',
            'cubAG_marca'                             => $row['cre_marca'] ?? '',
            'cubAG_tipoCredito'                       => $row['cre_tipoCredito'] ?? '',
            'cubAG_canton'                            => $row['cre_canton'] ?? '',

            'cubAG_ciudad'                            => $row['cre_ciudad'] ?? '',
            'cubAG_capitalActual'                     => (float)($row['cre_saldoCapital'] ?? 0),
            'cubAG_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
            'cubAG_fechaCarga'                        => (int)($row['cre_fechaCarga'] ?? 0),
            'cubAG_usuariosId'                        => $row['usUsuarios_id'] ?? '',

            //Mongo CRM 
            'cubAG_crmId'                             => '',
            'cubAG_edad'                              => '',
            'cubAG_sexo'                              => '',
            'cubAG_estadoCivil'                       => '',
            //Mongo cbDirecciones    
            'cubAG_dirId'                             => '',
            'cubAG_direccion'                         => '',

            //Mongo cbCargaDetallePacifico
            'cubAG_cdetId'                            => '',
            'cubAG_capitalInicial'                    => 0,
            'cubAG_deudaNetaInicial'                  => 0,
            //Mongo control_carga_periodo
            'cubAG_ctrId'                             => '',
            'cubAG_fechaInicio'                       => 0,
            'cubAG_fechaFin'                          => 0,
            //cubo gestiones: mejor gestion general, aplanada (se llena mas abajo)
            'cubAG_gestionada'                        => 0,
            'cubAG_cuGestionId'                       => '',
            'cubAG_canal'                              => '',
            'cubAG_ponderacion'                       => 0,
            'cubAG_tipificacion1'                     => '',
            'cubAG_tipificacion2'                     => '',
            'cubAG_fechaGestion'                      => 0,
            'cubAG_compromiso'                        => '',
            'cubAG_montoCompromiso'                   => 0,
            'cubAG_telefono'                           => '',
            'cubAG_email'                              => '',

            //cubo gestiones por canal (AV/EMAIL/WHATSAPP), se llena mas abajo
            'cubAG_mejorGestion'                      => [],

            //ultima gestion general (la de mayor fecha de gestion), aplanada (se llena mas abajo)
            'cubAG_ultimaGestion_cuGestionId'         => '',
            'cubAG_ultimaGestion_canal'               => '',
            'cubAG_ultimaGestion_ponderacion'         => 0,
            'cubAG_ultimaGestion_tipificacion1'       => '',
            'cubAG_ultimaGestion_tipificacion2'       => '',
            'cubAG_ultimaGestion_fechaGestion'        => 0,
            'cubAG_ultimaGestion_compromiso'          => '',
            'cubAG_ultimaGestion_montoCompromiso'     => 0,
            'cubAG_ultimaGestion_telefono'            => '',
            'cubAG_ultimaGestion_email'               => '',

            //ultima gestion por canal (AV/EMAIL/WHATSAPP), se llena mas abajo
            'cubAG_ultimaGestion'                     => [],

            //intensidad: numero de gestiones del periodo por canal y total de los 3
            'cubAG_intensidad'                        => [],
            'cubAG_intensidad_total'                  => 0,

            //PAGOS
            'cubAG_montoTotalPago'                    => 0,
        ];
        //Buscar registros en mongo CRM y añadir al cubo
        $mdbCRM = new MYMONGODB();
        $condCRM = ['crm_cedula' => (string) $datos['cubAG_cedula']];
        $datCRM = [
            '_id',
            'crm_edad',
            'crm_sexo',
            'crm_estadoCivil'
        ];
        $mdbCRM->buscar('CRM', $condCRM, $datCRM, [], 1);
        while ($doc = $mdbCRM->siguiente()) {
            $datos['cubAG_crmId'] = $doc['_id'] ?? null;
            $datos['cubAG_edad'] = $doc['crm_edad'] ?? null;
            $datos['cubAG_sexo'] = $doc['crm_sexo'] ?? null;
            $datos['cubAG_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;

            break;
        }
        //Buscar registros en mongo cbDirecciones y añadir al cubo
        $mdbDir = new MYMONGODB();
        $condDir = ['dir_cedula' => (string) $datos['cubAG_cedula']];
        $datDir = [
            '_id',
            'dir_direccion'
        ];
        $mdbDir->buscar('cbDirecciones', $condDir, $datDir, [], 1);

        while ($doc = $mdbDir->siguiente()) {
            $datos['cubAG_dirId'] = $doc['_id'] ?? null;
            $datos['cubAG_direccion'] = $doc['dir_direccion'] ?? null;
            break;
        }

        //Buscar registros en mongo cbCargaDetallePacifico y añadir al cubo
        $mdbDet = new MYMONGODB();
        $condDet = [
            'cedula'    => (string) $datos['cubAG_cedula'],
            'carteraId' => (int) $datos['cubAG_carteraId'],
            'inicial'   => (int) 1,
            'periodo'          => $datos['cubAG_ciclo']

        ];
        $datDet = [
            '_id',
            'saldoCapital',
            'deudaNeta'
        ];
        $mdbDet->buscar('cbCargaDetallePacifico', $condDet, $datDet, [], 1);
        while ($doc = $mdbDet->siguiente()) {
            $datos['cubAG_cdetId'] = $doc['_id'] ?? null;
            $datos['cubAG_capitalInicial'] = (float) ($doc['saldoCapital'] ?? 0);
            $datos['cubAG_deudaNetaInicial'] = (float) ($doc['deudaNeta'] ?? 0);
            break;
        }
        //Buscar registros en mongo c y añadir al cubo
        $mdbCargaPer = new MYMONGODB();
        $condCargaPer = [
            'cartera'   => (int) $datos['cubAG_carteraId'],
            'periodo'   => (int) $datos['cubAG_ciclo'],
            'fecha'     => (int) $datos['cubAG_fechaPeriodo'],
            //'activo'    => (int) 1

        ];
        $datCargaPer = [
            '_id',
            'fecha',
            'fechaFin'
        ];
        $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, $datCargaPer, ['_id' => -1], 1); //3 VECES AL MES
        while ($doc = $mdbCargaPer->siguiente()) {
            $datos['cubAG_ctrId'] = $doc['_id'] ?? null;
            $datos['cubAG_fechaInicio'] = (int) $doc['fecha'] ?? 0;
            $datos['cubAG_fechaFin'] = (int) $doc['fechaFin'] ?? 0;
            break;
        }

        // Gestion vacia de un canal: la comparten mejorGestion y ultimaGestion
        $gestionVacia = [
            'cuGestionId'     => '',
            'ponderacion'     => 0,
            'tipificacion1'   => '',
            'tipificacion2'   => '',
            'fechaGestion'    => 0,
            'compromiso'      => '',
            'montoCompromiso' => 0,
            'telefono'        => '',
            'email'           => '',
        ];
        foreach (self::MAPA_CANALES as $codigoCanal) {
            $datos['cubAG_mejorGestion'][$codigoCanal]  = $gestionVacia;
            $datos['cubAG_ultimaGestion'][$codigoCanal] = $gestionVacia;
            $datos['cubAG_intensidad'][$codigoCanal]    = 0;
        }

        $mdbCuGC = new MYMONGODB();
        $condCuGC = [
            'cubGC_numFactura'     => (string)$datos['cubAG_numFactura'],
            'cubGC_carteraId'      => (string)$datos['cubAG_carteraId'],
            'cubGC_ciclo'          => (int)$datos['cubAG_ciclo'],
            //'cubGC_fechaPeriodo'   => (int)$datos['cubAG_fechaPeriodo'],
            'cubGC_fechaGestion'   => ['$gte' => (int)$datos['cubAG_fechaInicio'], /*, '$lte' => strtotime(date("Y-m-d", $datos['cubAG_fechaFin']) . " 23:59:59")*/],
            'cubGC_tipificacion_respuesta2' => ['$nin' => ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado']]
        ];
        $datCuGC = [
            '_id',
            'cubGC_avId',
            'cubGC_canal',
            'cubGC_ponderacion',
            'cubGC_tipificacion_respuesta1',
            'cubGC_tipificacion_respuesta2',
            'cubGC_fechaGestion',
            'cubGC_tipificacion_compromiso',
            'cubGC_tipificacion_montoCompromiso',
            'cubGC_telefono',
            'cubGC_email'
        ];

        $mdbCuGC->buscar('cuGestionCobranzaMysql', $condCuGC, $datCuGC, ['cubGC_ponderacion' => -1]);

        $mejorGlobal      = null;
        $mejoresPorCanal  = [];
        $ultimaGlobal     = null;
        $ultimasPorCanal  = [];
        $gestionadaGlobal = 0;

        while ($doc = $mdbCuGC->siguiente()) {
            // 'gestionada' es un indicador global: si CUALQUIER gestion (de
            // cualquier canal) tiene cubGC_avId, se marca 1 y ya no cambia.
            if (isset($doc['cubGC_avId'])) {
                $gestionadaGlobal = 1;
            }

            $codigoCanal = self::MAPA_CANALES[$doc['cubGC_canal'] ?? null] ?? null;

            // Telefono solo para AV/WHATSAPP, email solo para EMAIL.
            $telefono = '';
            $email    = '';
            if ($codigoCanal === 'AV' || $codigoCanal === 'WHATSAPP') {
                $telefono = (string)($doc['cubGC_telefono'] ?? '');
            } elseif ($codigoCanal === 'EMAIL') {
                $email = (string)($doc['cubGC_email'] ?? '');
            }

            $candidato = [
                'cuGestionId'     => $doc['_id'],
                'canal'           => $codigoCanal ?? '',
                'ponderacion'     => (int)$doc['cubGC_ponderacion'],
                'tipificacion1'   => (string)$doc['cubGC_tipificacion_respuesta1'],
                'tipificacion2'   => (string)$doc['cubGC_tipificacion_respuesta2'],
                'fechaGestion'    => (int)$doc['cubGC_fechaGestion'],
                'compromiso'      => (string)($doc['cubGC_tipificacion_compromiso'] ?? ''),
                'montoCompromiso' => (float)($doc['cubGC_tipificacion_montoCompromiso'] ?? 0),
                'telefono'        => $telefono,
                'email'           => $email,
            ];

            if ($mejorGlobal === null) {
                $mejorGlobal = $candidato;
            }

            if ($codigoCanal !== null && !isset($mejoresPorCanal[$codigoCanal])) {
                $mejoresPorCanal[$codigoCanal] = $candidato;
            }

            // Ultima gestion: por fecha de gestion, no por ponderacion
            if ($this->auxEsMasReciente($candidato, $ultimaGlobal)) {
                $ultimaGlobal = $candidato;
            }

            if ($codigoCanal === null) continue;
            $datos['cubAG_intensidad'][$codigoCanal]++;
            if ($this->auxEsMasReciente($candidato, $ultimasPorCanal[$codigoCanal] ?? null)) {
                $ultimasPorCanal[$codigoCanal] = $candidato;
            }
        }

        $datos['cubAG_gestionada'] = $gestionadaGlobal;

        if ($mejorGlobal !== null) {
            $datos['cubAG_cuGestionId']   = $mejorGlobal['cuGestionId'];
            $datos['cubAG_canal']         = $mejorGlobal['canal'];
            $datos['cubAG_ponderacion']   = $mejorGlobal['ponderacion'];
            $datos['cubAG_tipificacion1'] = $mejorGlobal['tipificacion1'];
            $datos['cubAG_tipificacion2'] = $mejorGlobal['tipificacion2'];
            $datos['cubAG_fechaGestion']  = $mejorGlobal['fechaGestion'];
            $datos['cubAG_compromiso']       = $mejorGlobal['compromiso'];
            $datos['cubAG_montoCompromiso']  = $mejorGlobal['montoCompromiso'];
            $datos['cubAG_telefono']         = $mejorGlobal['telefono'];
            $datos['cubAG_email']            = $mejorGlobal['email'];
        }

        foreach ($mejoresPorCanal as $codigoCanal => $mejor) {
            $datos['cubAG_mejorGestion'][$codigoCanal] = $mejor;
        }

        // Las claves del candidato coinciden con el sufijo de los campos planos cubAG_ultimaGestion_*
        foreach ($ultimaGlobal ?? [] as $campo => $valor) {
            $datos['cubAG_ultimaGestion_' . $campo] = $valor;
        }

        foreach ($ultimasPorCanal as $codigoCanal => $ultima) {
            $datos['cubAG_ultimaGestion'][$codigoCanal] = $ultima;
        }

        $datos['cubAG_intensidad_total'] = array_sum($datos['cubAG_intensidad']);

        //Buscar registros en mongo cbPagos y añadir al cubo
        $mdbPag = new MYMONGODB();
        $condPag = [
            'pagos_numFactura'     => $datos['cubAG_numFactura'],
            'pagos_carteraId'      => $datos['cubAG_carteraId'],
            'pagos_periodo'        => (int)$datos['cubAG_ciclo'],
            'pagos_proceso'        => ['$gte' => (int)$datos['cubAG_fechaInicio']],
        ];
        $datPag = [
            'pagos_monto'
        ];

        $mdbPag->buscar('cbPagos', $condPag, $datPag);
        $totalMonto = 0;
        while ($doc = $mdbPag->siguiente()) {
            $totalMonto += isset($doc['pagos_monto']) ? (float)$doc['pagos_monto'] : 0;
        }
        $datos['cubAG_montoTotalPago'] = round($totalMonto, 2);

        return $datos;
    }

    public function recreate(): void
    {
        $limit = 20000;
        $skip  = 0;
        $mdb = new MYMONGODB();
        $mdbCubo = new MYMONGODB();
        $mdb->borrarColeccion(self::COLLECTION_CUBO);

        // Misma condición que aplica process() en el caso 'ADD': solo créditos activos
        $condiciones = ['cre_inactivo' => (int)0];

        for ($i = 0; $i < 400; $i++) {
            $mdb->buscar('cbCreditos', $condiciones, [], ['_id' => 1], $limit, $skip);
            $numRows = 0;
            while ($doc = $mdb->siguiente()) {

                $criteria = [
                    'cubAG_numFactura'   => (string)$doc['cre_factura'],
                    'cubAG_carteraId'    => (string)$doc['cre_carteraId'],
                    'cubAG_fechaPeriodo' => (int)$doc['cre_fechaPeriodo'],
                ];
                $newRow = $this->createData($doc);

                // Mismo insertar-o-actualizar que hace process() cuando la tabla es cbCreditos
                if ($mdbCubo->buscar(self::COLLECTION_CUBO, $criteria, ['_id'], [], 1)) {
                    $mdbCubo->actualizar(self::COLLECTION_CUBO, $criteria, $newRow);
                } else {
                    //capital al primer ingreso a este periodo, solo se guarda aqui
                    $newRow['cubAG_capitalInicialPeriodo'] = (float)($doc['cre_saldoCapital'] ?? 0);
                    //fecha de asignacion: solo se guarda en el primer ingreso al periodo
                    $newRow['cubAG_fechaAsignacion'] = (int)($doc['cre_fechaCarga'] ?? 0);
                    $mdbCubo->guardar(self::COLLECTION_CUBO, $newRow);
                }

                $numRows++;
            }
            if ($numRows < $limit) break;
            $skip += $limit;
        }
        $this->createIndices($mdb);
    }

    // Proyecta la mora desde fechaInicio hasta fechaFin del periodo y le asigna el tramo de mora.
    // Devuelve ['dias' => mora proyectada, 'calificacion' => tramo].
    protected function calificacionAcelerada(array $doc, int $fechaInicio, int $fechaFin): array
    {
        $diasMora = (int)($doc['cre_diasMoraFactura'] ?? 0);

        // Sin fechas del periodo no se puede proyectar: se deja la calificación actual
        if ($fechaInicio <= 0 || $fechaFin <= 0) {
            return ['dias' => $diasMora, 'calificacion' => (string)($doc['cre_calificacion'] ?? '')];
        }

        // Se toman también los clientes con mora 0
        $diasPeriodo = (int)round((strtotime(date('Y-m-d', $fechaFin)) - strtotime(date('Y-m-d', $fechaInicio))) / 86400);
        $diasProyectados = $diasMora + max(0, $diasPeriodo);

        return [
            'dias'         => $diasProyectados,
            'calificacion' => $this->buscarTramoMora($diasProyectados, (string)($doc['cre_producto'] ?? ''), (string)($doc['cre_carteraId'] ?? '')),
        ];
    }

    protected function buscarTramoMora(int $mora, string $producto, string $carteraId): string
    {
        $this->cargarTramosMora();
        $tramos = [];
        if ($producto !== '' && isset($this->tramosMora['producto'][$producto])) {
            $tramos = $this->tramosMora['producto'][$producto];
        } elseif ($carteraId !== '' && isset($this->tramosMora['cartera'][$carteraId])) {
            $tramos = $this->tramosMora['cartera'][$carteraId];
        }

        foreach ($tramos as $tr) {
            $inicio = $tr['tr_tramoInicio'];
            $fin    = $tr['tr_tramoFin'];
            if (($inicio != 9999999 && $fin != 9999999 && $mora >= $inicio && $mora <= $fin)
                || ($fin == 9999999 && $mora >= $inicio)
                || ($inicio == 9999999 && $mora <= $fin)) {
                return (string)$tr['tr_tramo'];
            }
        }
        return '';
    }

    // Carga una sola vez los tramos MORA con el mismo aggregate que usa el ETL de carga para cbCreditos.
    protected function cargarTramosMora(): void
    {
        if ($this->tramosMora !== null) return;
        $this->tramosMora = ['producto' => [], 'cartera' => []];

        $condition = [
            ['$match' => ['tr_tipoTramo' => 'MORA']],
            ['$lookup' => [
                'from'         => 'cbConfig',
                'localField'   => '_id',
                'foreignField' => 'cbConf_cbTramosId',
                'as'           => 'data',
            ]],
            ['$unwind' => '$data'],
            ['$match' => ['data.cbConf_tipo' => 'tr_MORA_CARTERA']],
            ['$lookup' => [
                'from'         => 'cbConfig',
                'localField'   => 'data.cbConf_cbTramosId',
                'foreignField' => 'cbConf_cbTramosId',
                'as'           => 'data1',
            ]],
            ['$unwind' => '$data1'],
            ['$match' => ['data1.cbConf_tipo' => 'tr_MORA_PRODUCTO']],
        ];

        $mdbTr = new MYMONGODB();
        $mdbTr->agregar('cbTramos', $condition);
        while ($tr = $mdbTr->siguiente()) {
            $tramo = [
                'tr_tramo'       => $tr['tr_tramo'] ?? '',
                'tr_tramoInicio' => $tr['tr_tramoInicio'] ?? null,
                'tr_tramoFin'    => $tr['tr_tramoFin'] ?? null,
            ];
            if (isset($tr['data']['cbConf_id'])) {
                $this->tramosMora['cartera'][$tr['data']['cbConf_id']][] = $tramo;
            }
            if (isset($tr['data1']['cbConf_carteraNombre'])) {
                $this->tramosMora['producto'][$tr['data1']['cbConf_carteraNombre']][] = $tramo;
            }
        }
    }

    /**
     * Indica si la gestión candidata es más reciente que la actual. Gana la de mayor
     * fecha de gestión y, si empatan, la que entró después al cubo de gestiones
     * (_id mayor: el ObjectId crece con la inserción). La ponderación no interviene.
     *
     * @param array      $candidato Gestión leída del cursor.
     * @param array|null $actual    Última gestión encontrada hasta ahora, o null si no hay.
     * @return bool
     */
    private function auxEsMasReciente(array $candidato, ?array $actual): bool
    {
        if ($actual === null) return true;
        if ($candidato['fechaGestion'] !== $actual['fechaGestion']) {
            return $candidato['fechaGestion'] > $actual['fechaGestion'];
        }
        return strcmp((string)$candidato['cuGestionId'], (string)$actual['cuGestionId']) > 0;
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
                cr.cre_factura, 
                cr.cre_carteraId,
                cr.cre_fechaPeriodo

                FROM cbcreditos cr " . ($limit > 0 ? 'LIMIT ' . $limit : '');
    }

    protected function createIndices($mdb): void
    {
        $indices = [
            'cubAG_numFactura',
            'cubAG_cedula',
            'cubAG_carteraId',
            'cubAG_ciclo',
            'cubAG_fechaPeriodo'

        ];

        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}

?><? //_FIN_DE_ARCHIVO 