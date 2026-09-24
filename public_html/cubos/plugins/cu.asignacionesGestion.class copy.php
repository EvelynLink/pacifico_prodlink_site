<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGasignacionesGestion extends AbstractCuboPlugin {
    public const COLLECTION_CUBO = 'cuAsignacionesGestionAP';
    protected function process($ids, $accion): void
    {
        //trigger_error("Factura ingresa cuAsignacionesGestionAP" . print_r($ids,true));
        //trigger_error("Factura accion cuAsignacionesGestionAP" . print_r($accion,true));
        if (!is_array($ids) || empty($ids)) return;
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
            'producto'                  => ['mdb' => 'cubAG_producto',        'defaultValue' => '(Sin producto)'],
            'capital_actual'            => ['mdb' => 'cubAG_capitalActual',        'defaultValue' => '(Sin capital actual)'],
            'deuda_neta_actual'         => ['mdb' => 'cubAG_deudaNetaActual',        'defaultValue' => '(Sin deuda neta actaul)'],
            'dias_mora'                 => ['mdb' => 'cubAG_diasMora',        'defaultValue' => '(Sin días mora)'],
            'ciclo'                     => ['mdb' => 'cubAG_ciclo',        'defaultValue' => '(Sin ciclo)'],
            'saldo_capital_inicial'     => ['mdb' => 'cubAG_capitalInicial',        'defaultValue' => '(Sin saldo capital inicial)'],
            'deuda_neta_inicial'        => ['mdb' => 'cubAG_deudaNetaInicial',        'defaultValue' => '(Sin deuda neta inicial)'],
            'fecha_inicio'              => ['mdb' => 'cubAG_fechaInicio',        'defaultValue' => '(Sin fecha inicio)'],
            'fecha_fin'                 => ['mdb' => 'cubAG_fechaFin',        'defaultValue' => '(Sin fecha fin)'],
            'fecha_periodo'             => ['mdb' => 'cubAG_fechaPeriodo',        'defaultValue' => '(Sin fecha periodo)'],
            'cartera_gestionada'        => ['mdb' => 'cubAG_gestionada',        'defaultValue' => '(Sin cartera gestionada)'],
            'monto_pago'                => ['mdb' => 'cubAG_montoTotalPago',        'defaultValue' => '(Sin monto pago)'],

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
            //cubo gestiones         
            'cubAG_cuGestionId'                       => '',      
            'cubAG_gestionada'                        => 0,
            'cubAG_ponderacion'                       => 0,
            'cubAG_tipificacion1'                     => '',
            'cubAG_tipificacion2'                     => '',
            'cubAG_fechaGestion'                      => 0,  

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
        //Buscar registros en mongo cuGestionCobranzaMysql y añadir al cubo
        $mdbCuGC = new MYMONGODB();
        $condCuGC = [
            'cubGC_numFactura'     => (string)$datos['cubAG_numFactura'],
            'cubGC_carteraId'      => (string)$datos['cubAG_carteraId'],
            'cubGC_ciclo'          => (int)$datos['cubAG_ciclo'],
            'cubGC_fechaPeriodo'   => (int)$datos['cubAG_fechaPeriodo'],
            'cubGC_fechaGestion'   => ['$gte' => (int)$datos['cubAG_fechaInicio'], /*, '$lte' => strtotime(date("Y-m-d", $datos['cubAG_fechaFin']) . " 23:59:59")*/],
            'cubGC_tipificacion_respuesta2' => ['$nin' => ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado']]
        ];
        $datCuGC = [
            '_id',
            'cubGC_avId',
            'cubGC_ponderacion',
            'cubGC_tipificacion_respuesta1',
            'cubGC_tipificacion_respuesta2',
            'cubGC_fechaGestion'
        ];

        $mdbCuGC->buscar('cuGestionCobranzaMysql', $condCuGC, $datCuGC, ['cubGC_ponderacion' => -1], 1);
        while ($doc = $mdbCuGC->siguiente()) {
            $datos['cubAG_cuGestionId'] = $doc['_id'];
            $datos['cubAG_gestionada'] = isset($doc['cubGC_avId']) ? 1 : 0;
            $datos['cubAG_ponderacion'] = (int)$doc['cubGC_ponderacion'];
            $datos['cubAG_tipificacion1'] = (string)$doc['cubGC_tipificacion_respuesta1'];
            $datos['cubAG_tipificacion2'] = (string)$doc['cubGC_tipificacion_respuesta2'];
            $datos['cubAG_fechaGestion'] = (int)$doc['cubGC_fechaGestion'];

            break;
        }

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
        exit;
        $limit = 20000;
        $skip  = 0;
        $mdb = new MYMONGODB();
        $mdb->borrarColeccion(self::COLLECTION_CUBO);
        for ($i = 0; $i < 400; $i++) {
            $mdb->buscar('cbCreditos', [], [], ['_id' => 1], $limit, $skip);
            $numRows = 0;
            while ($doc = $mdb->siguiente()) {
                $dato = $this->createData($doc);
                if (count($dato) > 0) {
                    $mdb->guardar(self::COLLECTION_CUBO, $dato);
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