<?php

require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
require_once '../smasivo/classes/class.smSiniestro.php';

class cuPGasignacionesGestion extends AbstractCuboPlugin
{
    public const COLLECTION_CUBO = 'cuAsignacionesGestion';

    protected function process($ids, $accion): void
    {


        if (!is_array($ids) || empty($ids)) return;

        $tablas = $this->organizeByTable($ids);


        $db     = new MYSQLDB();
        $mdb    = new MYMONGODB();

        foreach ($tablas as $tabla => $regIds) {
            $condicion = $this->buildCondition($tabla, $regIds);



            if (!$condicion['db']) continue;

            switch ($accion) {
                case 'ADD':
                    $sql = $db->mkSQL($this->baseQuery() . " WHERE {$condicion['db']}");
                    $db->query($sql);


                    while ($row   = $db->fetchRow('EXTENDED')) {



                        $criteria = [
                            'cubAG_numFactura'          => (string)$row['cr.cre_factura'],
                            'cubAG_carteraId'           => (string)$row['cr.cre_carteraId'],
                            'cubAG_ciclo'               => (string)$row['cr.cre_periodo'],
                            'cubAG_fechaPeriodo'        => (int)$row['cr.cre_fechaPeriodo']
                        ];



                        $newRow = $this->createData($row);

                        if (count($newRow) > 0) {

                            if ($mdb->buscar(self::COLLECTION_CUBO, $criteria)) {
                                //trigger_error("entro a actualizar" . print_r($newRow, true));
                                $mdb->actualizar(self::COLLECTION_CUBO, $criteria, $newRow);
                            } else {
                                //trigger_error("entro a guardar" . print_r($criteria, true));
                                $mdb->guardar(self::COLLECTION_CUBO, $newRow);
                            }
                        }
                    }
                    break;
                /*case 'DELETE':
                    $criteria = $condicion['mongo'];
                    $mdb->borrar(self::COLLECTION_CUBO, $criteria);
                    break;
                */
                default:
                    trigger_error("Acción no reconocida {$accion}");
                    break;
            }
        }
    }



    protected function buildCondition($tabla, $regIds): array
    {
        $idsStr = "'" . implode("','", $regIds) . "'";

        return match ($tabla) {

            'cbcreditos'            => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubAG_numFactura' => ['$in' => $regIds]]],
            'cbCreditos'            => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubAG_numFactura' => ['$in' => $regIds]]],
            'cuGestionCobranza'     => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubAG_numFactura' => ['$in' => $regIds]]],
            'cbPagos'               => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cubAG_numFactura' => ['$in' => $regIds]]],
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
            //mysql tabla cbCreditos
            'cubAG_cbcreId'                           => (int) $row['cr.cre_id'],
            'cubAG_sponsor'                           => $row['sponsor'],
            'cubAG_nombres'                           => $row['cr.cre_nombres'],
            'cubAG_apellidos'                         => $row['cr.cre_apellidos'],
            'cubAG_cedula'                            => $row['cr.cre_cedula'],
            'cubAG_riesgo'                            => $row['cr.cre_calificacion'],
            'cubAG_deudaNetaActual'                   => (float) $row['cr.cre_deudaNeta'],
            'cubAG_diasMora'                          => (int) $row['cr.cre_diasMoraFactura'],
            'cubAG_producto'                          => $row['cr.cre_producto'],
            'cubAG_ciclo'                             => $row['cr.cre_periodo'],
            'cubAG_numFactura'                        => $row['cr.cre_factura'],
            'cubAG_carteraId'                         => $row['cr.cre_carteraId'],
            'cubAG_fechaPeriodo'                      => (int)$row['cr.cre_fechaPeriodo'],
            //Mongo CRM
            'cubAG_crmId'                             => '',
            'cubAG_edad'                              => '',
            'cubAG_sexo'                              => '',
            'cubAG_estadoCivil'                       => '',
            //Mongo cbDirecciones    
            'cubAG_dirId'                             => '',
            'cubAG_direccion'                         => '',
            //Mongo cbCreditos
            'cubAG_creId'                             => '',
            'cubAG_ciudad'                            => '',
            'cubAG_capitalActual'                     => 0,
            'cubAG_carteraNombre'                     => '',
            'cubAG_fechaCarga'                        => 0,

            //Mongo cbCargaDetallePacifico
            'cubAG_cdetId'                            => '',
            'cubAG_capitalInicial'                    => 0,
            'cubAG_deudaNetaInicial'                  => 0,
            //Mongo control_carga_periodo
            'cubAG_ctrId'                             => '',
            'cubAG_fechaInicio'                       => 0,
            'cubAG_fechaFin'                          => 0,
            //Control         
            'cubAG_gestionada'                        => 0,
            'cubAG_montoTotalPago'                    => 0,


        ];



        //Buscar registros en mongo cbCreditos y añadir al cubo
        $mdbCbCred = new MYMONGODB();
        $condCbCred = [
            'cre_factura'               => (string) $datos['cubAG_numFactura'],
            'cre_carteraId'             => (string)$datos['cubAG_carteraId']
        ];


        $datCbCred = [
            '_id',
            'cre_ciudad',
            'cre_saldoCapital',
            'cre_carteraId',
            'cre_fechaPeriodo',
            'cre_nombreCartera',
            'cre_periodo',
            'cre_fechaCargaInicial'

        ];

        $mdbCbCred->buscar('cbCreditos', $condCbCred, $datCbCred, [], 1);

        /*  if ($mdbCbCred->buscar('cbCreditos', $condCbCred, $datCbCred, [], 1)) {*/


        while ($doc = $mdbCbCred->siguiente()) {
            $datos['cubAG_creId'] = $doc['_id'] ?? null;;
            $datos['cubAG_ciudad'] = $doc['cre_ciudad'] ?? null;
            $datos['cubAG_capitalActual'] = (float) $doc['cre_saldoCapital'] ?? 0;
            $datos['cubAG_carteraNombre'] = $doc['cre_nombreCartera'] ?? null;
            $datos['cubAG_fechaCarga'] = isset($doc['cre_fechaCargaInicial']) ? (int)$doc['cre_fechaCargaInicial'] : 0;


            break;
        }

        //trigger_error("FACTURA ".$datos['cubAG_numFactura']." - FECHA CARGA".$datos['cubAG_fechaCarga']);



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
            'periodo'          => $datos['cubAG_ciclo'],
            'fechaPeriodo'   => (int)$datos['cubAG_fechaPeriodo'],

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


        //Buscar registros en mongo control_carga_periodo y añadir al cubo
        $mdbCargaPer = new MYMONGODB();
        $condCargaPer = [
            'cartera'   => (int) $datos['cubAG_carteraId']
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

        //Buscar registros en mongo cuGestionCobranza y añadir al cubo
        $mdbCuGC = new MYMONGODB();
        $condCuGC = [
            'cubGC_numFactura'     => $datos['cubAG_numFactura'],
            'cubGC_carteraId'      => $datos['cubAG_carteraId'],
            'cubGC_ciclo'          => $datos['cubAG_ciclo'],
            'cubGC_fechaPeriodo'   => (int)$datos['cubAG_fechaPeriodo'],
        ];
        $datCuGC = [
            'cubGC_avId',
            'cubGC_fechaGestion'
        ];

        $mdbCuGC->buscar('cuGestionCobranza', $condCuGC, $datCuGC, [], 1);


        while ($doc = $mdbCuGC->siguiente()) {
            $datos['cubAG_gestionada'] = isset($doc['cubGC_avId']) ? 1 : 0;
            break;
        }

        //Buscar registros en mongo cbPagos y añadir al cubo
        $mdbPag = new MYMONGODB();
        $condPag = [
            'pagos_numFactura'     => $datos['cubAG_numFactura'],
            'pagos_carteraId'      => $datos['cubAG_carteraId'],
            'pagos_periodo'        => (int)$datos['cubAG_ciclo'],
            'pagos_fechaPeriodo'   => (int)$datos['cubAG_fechaPeriodo'],
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



        //Verifico si existe en el cubo y si 

        $mdbCubo = new MYMONGODB();

        $condCubo = [
            'cubAG_numFactura'       => (string)$datos['cubAG_numFactura'],
            'cubAG_carteraId'        => (string)$datos['cubAG_carteraId'],
            'cubAG_ciclo'            => (string)$datos['cubAG_ciclo'],
            'cubAG_fechaPeriodo'     => (int)$datos['cubAG_fechaPeriodo'],
        ];



        if (!$mdbCubo->buscar(self::COLLECTION_CUBO, $condCubo, ['_id'], [], 1)  && $datos['cubAG_creId'] != '') {
            return $datos;
        } else {
            return [];
        }
    }

    public function recreate(): void
    {
        $limit = 20000;
        $offset = 0;
        $mdb = new MYMONGODB();
        $db = new MYSQLDB();
        $mdb->borrarColeccion(self::COLLECTION_CUBO);
        for ($i = 0; $i < 400; $i++) {
            $sql = $db->mkSQL($this->baseQuery()) . " LIMIT {$limit} OFFSET {$offset}";
            $db->query($sql);
            $numRows = 0;
            while ($row = $db->fetchRow('EXTENDED')) {

                $dato = $this->createData($row);
                if (count($dato) > 0) {
                    $mdb->guardar(self::COLLECTION_CUBO, $dato);
                }
                $numRows++;
            }
            if ($numRows < $limit)
                break;
            $offset += $limit;
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
    ?>