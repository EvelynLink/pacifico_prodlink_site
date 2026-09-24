<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGasignacionesGestionVentas extends AbstractCuboPlugin
{
    public const COLLECTION_CUBO = 'cuAsignacionesGestionVentas';
    protected function process($ids, $accion): void
    {
        if (!is_array($ids) || empty($ids)) return;
        $tablas = $this->organizeByTable($ids);
        $mdb    = new MYMONGODB();
        $mdbCubo    = new MYMONGODB();
        foreach ($tablas as $tabla => $regIds) {
            $condicion = $this->buildCondition($tabla, $regIds);
            if (!($condicion['mongo'])) continue;
            switch ($accion) {
                case 'ADD':

                    $db = new MYSQLDB();
                    $sql = 'SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo="VENTAS"';
                    $db->query($sql);

                    $carterasVentas = [];
                    while ($row = $db->fetchRow()) {
                        $carterasVentas[] = (string)$row['cobCartera_id'];
                    }

                    $condMongo = [
                        '$and' => [
                            $condicion['mongo'],
                            ['cre_carteraId' => ['$in' => $carterasVentas]]
                        ]
                    ];

                    $mdb->buscar('cbCreditos', $condMongo);
                    while ($doc = $mdb->siguiente()) {

                        $criteria = [
                            'cubAV_numFactura'    => (string)$doc['cre_factura'],
                            'cubAV_carteraId'     => (string)$doc['cre_carteraId'],
                            'cubAV_fechaPeriodo'  => (int)$doc['cre_fechaPeriodo'],
                        ];
                        $newRow = $this->createData($doc);
                        if ($mdbCubo->buscar(self::COLLECTION_CUBO, $criteria, ['_id'], [], 1)) {
                            $mdbCubo->actualizar(self::COLLECTION_CUBO, $criteria, $newRow);
                        } else {
                            if ($tabla == 'cbCreditos') {
                                $mdbCubo->guardar(self::COLLECTION_CUBO, $newRow);
                            } else {
                                //trigger_error("Registro no existe: Tabla: " . $tabla . " Información: " .  print_r($criteria, true));
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

    protected function buildCondition($tabla, $regIds): array
    {
        $idsStr = "'" . implode("','", $regIds) . "'";
        return match ($tabla) {
            'cbCreditos'            => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cre_factura' => ['$in' => $regIds]]],
            'cuGestionCobranza'     => ['db' => "cr.cre_factura IN ({$idsStr})",       'mongo' => ['cre_factura' => ['$in' => $regIds]]],
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
            'sponsor'                   => ['mdb' => 'cubAV_sponsor',        'defaultValue' => '(Sin sponsor)'],
            'nombre_cliente'            => ['mdb' => 'cubAV_nombres',        'defaultValue' => '(Sin nombre cliente)'],
            'apellido_cliente'          => ['mdb' => 'cubAV_apellidos',        'defaultValue' => '(Sin apellido cliente)'],
            'cedula_cliente'            => ['mdb' => 'cubAV_cedula',        'defaultValue' => '(Sin apellido cliente)'],
            'edad'                      => ['mdb' => 'cubAV_edad',        'defaultValue' => '(Sin edad)'],
            'sexo'                      => ['mdb' => 'cubAV_sexo',        'defaultValue' => '(Sin sexo)'],
            'estado_civil'              => ['mdb' => 'cubAV_estadoCivil',        'defaultValue' => '(Sin estado civil)'],
            'dirección'                 => ['mdb' => 'cubAV_direccion',        'defaultValue' => '(Sin dirección)'],
            'cartera'                   => ['mdb' => 'cubAV_carteraNombre',        'defaultValue' => '(Sin cartera)'],
            'registro_operacion'        => ['mdb' => 'cubAV_numFactura',        'defaultValue' => '(Sin registro operación)'],
            'ciudad'                    => ['mdb' => 'cubAV_ciudad',        'defaultValue' => '(Sin ciudad)'],
            'riesgo'                    => ['mdb' => 'cubAV_riesgo',        'defaultValue' => '(Sin riesgo)'],
            'producto'                  => ['mdb' => 'cubAV_producto',        'defaultValue' => '(Sin producto)'],
            'capital_actual'            => ['mdb' => 'cubAV_capitalActual',        'defaultValue' => '(Sin capital actual)'],
            'deuda_neta_actual'         => ['mdb' => 'cubAV_deudaNetaActual',        'defaultValue' => '(Sin deuda neta actaul)'],
            'dias_mora'                 => ['mdb' => 'cubAV_diasMora',        'defaultValue' => '(Sin días mora)'],
            'cartera_gestionada'        => ['mdb' => 'cubAV_gestionada',        'defaultValue' => '(Sin cartera gestionada)'],

        ];
        return $campos;
    }

    protected function createData($row): array
    {
        $datos = [
            //mongo cbCreditos
            'cubAV_cbcreId'                           => $row['_id'] ?? null,
            'cubAV_numFactura'                        => $row['cre_factura'] ?? '',
            'cubAV_carteraId'                         => $row['cre_carteraId'] ?? '',
            'cubAV_carteraNombre'                     => $row['cre_nombreCartera'] ?? '',
            'cubAV_ciclo'                             => (int)($row['cre_periodo'] ?? 0),
            'cubAV_fechaPeriodo'                      => (int)($row['cre_fechaPeriodo'] ?? 0),
            'cubAV_sponsor'                           => 'BANCO DEL PACÍFICO',
            'cubAV_usuariosId'                        => $row['usUsuarios_id'] ?? '',
            'cubAV_nombres'                           => $row['cre_nombres'] ?? '',
            'cubAV_apellidos'                         => $row['cre_apellidos'] ?? '',
            'cubAV_cedula'                            => $row['cre_cedula'] ?? '',
            'cubAV_producto'                          => $row['cre_producto'] ?? '',
            'cubAV_ciudad'                            => $row['cre_ciudad'] ?? '',
            'cubAV_fechaCarga'                        => $row['cre_fechaCarga'] ?? '',
            'cubAV_mejorBandaHoraria'                 => $row['cre_mejorBandaHoraria'] ?? '',


            //Mongo CRM
            'cubAV_crmId'                             => '',
            'cubAV_edad'                              => '',
            'cubAV_sexo'                              => '',
            'cubAV_estadoCivil'                       => '',
            //Mongo cbDirecciones    
            'cubAV_dirId'                             => '',
            'cubAV_direccion'                         => '',
            //Mongo control_carga_periodo
            'cubAV_ctrId'                             => '',
            'cubAV_fechaInicio'                       => 0,
            'cubAV_fechaFin'                          => 0,

            //cubo gestiones   
            'cubAV_cuGestionId'                       => '',
            'cubAV_gestionada'                        => 0,
            'cubAV_ponderacion'                       => 0,
            'cubAV_tipificacion1'                     => '',
            'cubAV_tipificacion2'                     => '',
            'cubAV_fechaGestion'                      => 0,

        ];
        //Buscar registros en mongo CRM y añadir al cubo
        $mdbCRM = new MYMONGODB();
        $condCRM = ['crm_cedula' => (string) $datos['cubAV_cedula']];
        $datCRM = [
            '_id',
            'crm_edad',
            'crm_sexo',
            'crm_estadoCivil'
        ];
        $mdbCRM->buscar('CRM', $condCRM, $datCRM, [], 1);
        while ($doc = $mdbCRM->siguiente()) {
            $datos['cubAV_crmId'] = $doc['_id'] ?? null;
            $datos['cubAV_edad'] = $doc['crm_edad'] ?? null;
            $datos['cubAV_sexo'] = $doc['crm_sexo'] ?? null;
            $datos['cubAV_estadoCivil'] = $doc['crm_estadoCivil'] ?? null;

            break;
        }
        //Buscar registros en mongo cbDirecciones y añadir al cubo
        $mdbDir = new MYMONGODB();
        $condDir = ['dir_cedula' => (string) $datos['cubAV_cedula']];
        $datDir = [
            '_id',
            'dir_direccion'
        ];
        $mdbDir->buscar('cbDirecciones', $condDir, $datDir, [], 1);

        while ($doc = $mdbDir->siguiente()) {
            $datos['cubAV_dirId'] = $doc['_id'] ?? null;
            $datos['cubAV_direccion'] = $doc['dir_direccion'] ?? null;
            break;
        }

        //Buscar registros en mongo control_carga_periodo y añadir al cubo
        $mdbCargaPer = new MYMONGODB();
        $condCargaPer = [
            'cartera'   => (int) $datos['cubAV_carteraId'],
            'periodo'   => (int) $datos['cubAV_ciclo'],
            'fecha'     => (int) $datos['cubAV_fechaPeriodo'],
            //'activo'    => (int) 1

        ];
        $datCargaPer = [
            '_id',
            'fecha',
            'fechaFin'
        ];
        $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer, $datCargaPer, ['_id' => -1], 1); //3 VECES AL MES
        while ($doc = $mdbCargaPer->siguiente()) {
            $datos['cubAV_ctrId'] = $doc['_id'] ?? null;
            $datos['cubAV_fechaInicio'] = (int) $doc['fecha'] ?? 0;
            $datos['cubAV_fechaFin'] = (int) $doc['fechaFin'] ?? 0;
            break;
        }


        //Buscar registros en mongo cuGestionVentas y añadir al cubo
        $mdbCuGV = new MYMONGODB();
        $condCuGV = [
            'cubGV_numFactura'     => (string)$datos['cubAV_numFactura'],
            'cubGV_carteraId'      => (string)$datos['cubAV_carteraId'],
            'cubGV_fechaGestion'   => ['$gte' => (int)$datos['cubAV_fechaInicio']],
            'cubGV_tipificacion_respuesta2' => ['$nin' => ['WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR', 'Mail No Enviado']]
        ];
        $datCuGV = [
            '_id',
            'cubGV_avId',
            'cubGV_ponderacion',
            'cubGV_tipificacion_respuesta1',
            'cubGV_tipificacion_respuesta2',
            'cubGV_fechaGestion'
        ];

        $mdbCuGV->buscar('cuGestionVentas', $condCuGV, $datCuGV, ['cubGV_ponderacion' => -1], 1);
        while ($doc = $mdbCuGV->siguiente()) {
            $datos['cubAV_cuGestionId'] = $doc['_id'];
            $datos['cubAV_gestionada'] = isset($doc['cubGV_avId']) ? 1 : 0;
            $datos['cubAV_ponderacion'] = (int)$doc['cubGV_ponderacion'];
            $datos['cubAV_tipificacion1'] = (string)$doc['cubGV_tipificacion_respuesta1'];
            $datos['cubAV_tipificacion2'] = (string)$doc['cubGV_tipificacion_respuesta2'];
            $datos['cubAV_fechaGestion'] = (int)$doc['cubGV_fechaGestion'];

            break;
        }

        return $datos;
    }

    public function recreate(): void
    {
        $limit = 20000;
        $skip  = 0;
        $condiciones = [];
        $mdb = new MYMONGODB();
        $mdb->borrarColeccion(self::COLLECTION_CUBO);

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
            'cubAV_numFactura',
            'cubAV_cedula',
            'cubAV_carteraId'

        ];

        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}

?><? //_FIN_DE_ARCHIVO 