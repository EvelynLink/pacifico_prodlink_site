<?php
require_once '../cubos/classes/abstract.class.cuCuboPlugin.php';
class cuPGgestionVentas extends AbstractCuboPlugin
{
    public const COLLECTION_CUBO = 'cuGestionVentas';

    // Colecciones de detalle de conversación
    protected const TABLAS_DETALLE_CONVERSACION = [
        'avDetalleConversaciones',
        'avDetalleConversacionesLink',
        'avDetalleConversacionesRetell',
    ];

    // Llamadas de MySQL que no tienen gestión en avProgramadas, avProgramadasWhatsApp ni cbEnvioMails.
    // El nombre de la tabla también es el valor de cubGV_origen de esas filas.
    protected const TABLA_LLAMADAS = 'scllamadas';
    protected const CANAL_LLAMADAS = 'TELEFONICA';
    protected const LOTE_LLAMADAS  = 1000;
    protected const TIPO_CARTERA   = 'VENTAS';

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

    protected function process($ids, $accion): void
    {
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

    /**
     * Dado un idConversacion (proveniente de avDetalleConversaciones / Link / Retell),
     * ubica el(los) avProgramadas relacionado(s) por av_idConversacion (restringido a
     * carteras de VENTAS) y reprocesa ese registro hacia el cubo, para refrescar
     * cubGV_analisisCalidad (y el resto de campos) sin depender de que avProgramadas
     * haya cambiado.
     */
    protected function reprocesarAvProgramadasPorConversacion($idConversacion): void
    {
        if (empty($idConversacion)) return;

        //Busco carteras de ventas
        $db = new MYSQLDB();
        $sql = 'SELECT cobCartera_id FROM cobcartera WHERE cobCartera_tipo="VENTAS"';
        $db->query($sql);

        $carterasVentas = [];
        while ($row = $db->fetchRow()) {
            $carterasVentas[] = (int)$row['cobCartera_id'];
        }

        $mdbAvProg = new MYMONGODB();
        $condAvProg = [
            'av_idConversacion' => $idConversacion,
            'av_carteraId'      => ['$in' => $carterasVentas],
        ];
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

    protected function getConfigByTabla(string $tabla): ?array
    {
        switch ($tabla) {
            case 'avProgramadas':
                return [
                    'factura'      => 'av_factura',
                    'cartera'      => 'av_carteraId',
                    // No se filtra por tipificacion aqui: con eventos/reintentos la
                    // respuesta puede resolverse via scllamadas por evento (ver
                    // createData), y no siempre esta en el documento principal de
                    // avProgramadas. Si se dejara este filtro, un ADD que solo agrega
                    // un reintento (sin tocar av_tipificacion.respuesta2 del doc raiz)
                    // quedaria descartado antes de llegar a createData.
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
            'fecha_programacion'        => ['mdb' => 'cubGV_fechaProgramacion',   'defaultValue' => '(Sin fecha programación)'],
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
            'fecha_carga'               => ['mdb' => 'cubGV_fechaCarga',        'defaultValue' => '(Sin fecha carga)'],
            'analisis_calidad'          => ['mdb' => 'cubGV_analisisCalidad',        'defaultValue' => '(Sin análisis de calidad)'],
            'origen'                    => ['mdb' => 'cubGV_origen',        'defaultValue' => '(Sin origen)'],
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
            'cubGV_fechaProgramacion'                 => 0,
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
            'cubGV_resumen'                           => '',
            'cubGV_llamadaId'                         => 0,
            'cubGV_analisisCalidad'                   => null,
            // Solo aplica a correos (canal EMAIL), se pasan tal cual vienen de cbEnvioMails.
            'cubGV_abierto'                           => 0,
            'cubGV_cantidad_abierto'                  => 0,
            // Horario real (CDR) de la llamada, via scllamadas + sccdr. Solo aplica a TELEFONICA.
            'cubGV_horaInicio'                        => 0,
            'cubGV_horaFin'                           => 0,
            // Colección o tabla de la que sale la gestión; 'scllamadas' = llamada sin avProgramadas.
            'cubGV_origen'                            => $tabla,
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

            // Nota: ya no se filtra por 'av_tipificacion.respuesta2' aquí, porque con
            // eventos/reintentos la tipificación puede resolverse por cada evento
            // consultando scllamadas (ver abajo), y no siempre está en el documento
            // principal de avProgramadas.
            $condAvProg = [
                'av_factura'   => (string) $datos['cubGV_numFactura'],
                'av_carteraId' => (int) $datos['cubGV_carteraId'],
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
            /*trigger_error(
                "FACTURA {$datos['cubGV_numFactura']} --> avProgramadas encontradas: {$mdbAvProg->buscar('avProgramadas',$condAvProg,$datAvProg)}"
            );*/
            while ($doc = $mdbAvProg->siguiente()) {

                // Se calcula una sola vez por documento: av_proveedor / av_idConversacion
                // pertenecen al avProgramadas, no a cada evento/reintento.
                $analisisCalidad = $this->obtenerAnalisisCalidad(
                    $doc['av_proveedor'] ?? null,
                    $doc['av_idConversacion'] ?? null
                );

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
                                'principal' => false
                            ];
                        }
                    }
                }

                foreach ($eventos as $ev) {

                    $idLlamada = $ev['evento'];

                    $fila = $datos;

                    $fila['cubGV_avId'] = $doc['_id'] ?? null;
                    $fila['cubGV_llamadaId'] = $idLlamada;
                    $fila['cubGV_canal'] = 'TELEFONICA';
                    $fila['cubGV_fechaProgramacion'] = (int)($doc['av_fecha'] ?? 0);
                    $fila['cubGV_telefono'] = $doc['av_telefono'] ?? null;
                    $fila['cubGV_campaniaId'] = (int)($doc['av_campaniaId'] ?? 0);
                    $fila['cubGV_campaniaNombre'] = $doc['av_campaniaNombre'] ?? null;
                    $fila['cubGV_duracionGestionSeg'] = $doc['av_duracionSegundos'] ?? null;
                    $fila['cubGV_proveedor'] = $doc['av_proveedor'] ?? null;
                    $fila['cubGV_proveedorSip'] = $doc['av_proveedorSip'] ?? null;
                    $fila['cubGV_analisisCalidad'] = $analisisCalidad;
                    // $fila['cubGV_fechaPeriodo'] = isset($doc['av_fechaPeriodo']) ? (int)$doc['av_fechaPeriodo'] : 0;

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
                    $fila['cubGV_horaInicio'] = (int)($rowCdr['horaInicio'] ?? 0);
                    $fila['cubGV_horaFin'] = (int)($rowCdr['horaFin'] ?? 0);

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

                        // Se toma siempre la fecha de gestión, no de tipificación
                        if (!empty($doc['av_fechaFinLlamada'])) {
                            $fila['cubGV_fechaGestion'] = (int)$doc['av_fechaFinLlamada'];
                        } elseif (!empty($doc['av_fechaGeneraLlamada'])) {
                            $fila['cubGV_fechaGestion'] = (int)$doc['av_fechaGeneraLlamada'];
                        } else {
                            $fila['cubGV_fechaGestion'] = (int)$doc['av_eventoFecha'];
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

                        $fila['cubGV_fechaGestion'] = !empty($rowSql['scLlamadas_fechaCreacion'] ?? null) ? (int)$rowSql['scLlamadas_fechaCreacion'] : null;

                        $respuesta1 = !empty($rowSql['scLlamadas_ruta1'] ?? null) ? $rowSql['scLlamadas_ruta1'] : null;
                        $respuesta2 = !empty($rowSql['scLlamadas_ruta2'] ?? null) ? $rowSql['scLlamadas_ruta2'] : null;
                        $resumen = !empty($rowSql['scLlamadas_texto'] ?? null) ? $rowSql['scLlamadas_texto'] : null;
                    }

                    // Si no existe R1 ni R2 en ninguna fuente, no insertar
                    if (empty($respuesta1) && empty($respuesta2)) {
                        continue;
                    }

                    $fila['cubGV_nivelContacto'] = $respuesta1;
                    $fila['cubGV_tipificacion_respuesta1'] = $respuesta1;
                    $fila['cubGV_tipificacion_respuesta2'] = $respuesta2;
                    $fila['cubGV_resumen'] = $resumen;

                    //==================================================
                    // Buscar ponderación
                    //==================================================

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
                        'cubGV_llamadaId' => (int)$fila['cubGV_llamadaId']
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
                'ws_factura'   => (string) $datos['cubGV_numFactura'],
                'ws_carteraId'   => (int) $datos['cubGV_carteraId'],
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
                $fila['cubGV_avId'] = $doc['_id'] ?? null;
                $fila['cubGV_canal'] = 'WHATSAPP';
                $fila['cubGV_fechaGestion'] = (int)($doc['ws_estadoEnvioFecha'] ?? 0);
                $fila['cubGV_fechaProgramacion'] = (int)($doc['ws_fecha'] ?? 0);
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
                $this->auxQuitarLlamadaSinGestion((int)($doc['ws_evento'] ?? 0), $mdbCubo);
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
                $fila['cubGV_avId'] = $doc['_id'] ?? null;
                $fila['cubGV_canal'] = 'EMAIL';
                $fila['cubGV_fechaGestion'] =  (int)$doc['cem_susFechaEnvio'] ?? 0;
                $fila['cubGV_fechaProgramacion'] = (int)($doc['cem_susFechaAsignacion'] ?? 0);
                $fila['cubGV_email'] = $doc['cem_susEmail'] ?? null;
                $fila['cubGV_campaniaId'] = (int)$doc['cem_susCampaniaId'] ?? null;
                $fila['cubGV_campaniaNombre'] = $doc['cem_susCampaniaNombre'] ?? null;
                $fila['cubGV_duracionGestionSeg'] = 0;
                // Tal cual vienen en cbEnvioMails: abierto=0 si no se abrió o el unixtime de apertura.
                $fila['cubGV_abierto'] = $doc['abierto'] ?? 0;
                $fila['cubGV_cantidad_abierto'] = $doc['cantidad_abierto'] ?? 0;
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
        $skip  = 0;
        $condiciones = [];
        $mdb = new MYMONGODB();


        $encontreControl = false;

        if (!$encontreControl) {  //solo borre la coleccion completa cuando no sea reconstruccion parcial
            $mdb->borrarColeccion(self::COLLECTION_CUBO);
            // Con la colección vacía no hay filas 'scllamadas' que quitar, y sin índices cada borrado recorrería el cubo.
            $this->quitarLlamadasSinGestion = false;
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
     * avProgramadasWhatsApp ni cbEnvioMails, marcadas con cubGV_origen = 'scllamadas'.
     * La cartera sale de la campaña (scLlamadas_ruta0 -> scramas -> cobcarteraramas) y se
     * confirma con el crédito del cliente (scLlamadas_usuarioId -> cbCreditos.usUsuarios_id).
     *
     * @param int   $desde Timestamp mínimo de scLlamadas_fechaCreacion (0 = sin límite).
     * @param int   $hasta Timestamp máximo, exclusivo (0 = sin límite).
     * @param array $ids   scLlamadas_id a procesar; si viene, se ignora el rango de fechas.
     * @return array{revisadas:int, conGestion:int, sinCampania:int, sinCredito:int, ambiguas:int, insertadas:int, actualizadas:int}
     */
    public function insertarLlamadasSinGestion(int $desde = 0, int $hasta = 0, array $ids = []): array
    {
        $resumen = [
            'revisadas' => 0, 'conGestion' => 0, 'sinCampania' => 0, 'sinCredito' => 0,
            'ambiguas' => 0, 'insertadas' => 0, 'actualizadas' => 0,
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
     * Campañas que tienen cartera de VENTAS asociada, agrupadas por nombre de rama.
     * Un mismo nombre puede tener varias parejas (rama repetida o rama en varias carteras).
     *
     * @return array<string, array<int, array{campaniaId:int, carteraId:string}>>
     */
    private function auxCampaniasPorNombre(): array
    {
        $db = new MYSQLDB();
        $sql = $db->mkSQL(
            "SELECT scRamas_id, scRamas_nombre, cobCarteraRamas_carteraIdfk
             FROM scramas
             INNER JOIN cobcarteraramas ON cobCarteraRamas_ramaIdfk = scRamas_id
             INNER JOIN cobcartera ON cobCartera_id = cobCarteraRamas_carteraIdfk
             WHERE cobCartera_tipo = %Q",
            self::TIPO_CARTERA
        );
        $db->query($sql);
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

        $ruta2 = !empty($llamada['scLlamadas_ruta2']) ? $llamada['scLlamadas_ruta2'] : null;
        $fila = $datos;
        $fila['cubGV_avId'] = null;
        $fila['cubGV_llamadaId'] = (int)$llamada['scLlamadas_id'];
        $fila['cubGV_canal'] = self::CANAL_LLAMADAS;
        $fila['cubGV_fechaGestion'] = $fechaGestion;
        $fila['cubGV_fechaProgramacion'] = $fechaGestion;
        $fila['cubGV_telefono'] = !empty($llamada['numeroCola']) ? $llamada['numeroCola'] : ($llamada['scCDR_callerId'] ?? null);
        $fila['cubGV_campaniaId'] = $campaniaId;
        $fila['cubGV_campaniaNombre'] = $llamada['scLlamadas_ruta0'] ?? null;
        $fila['cubGV_duracionGestionSeg'] = (int)($llamada['scCDR_duration'] ?? 0);
        $fila['cubGV_horaInicio'] = (int)($llamada['scCDR_horaIni'] ?? 0);
        $fila['cubGV_horaFin'] = (int)($llamada['scCDR_horaFin'] ?? 0);
        $fila['cubGV_nivelContacto'] = $llamada['scLlamadas_ruta1'];
        $fila['cubGV_tipificacion_respuesta1'] = $llamada['scLlamadas_ruta1'];
        $fila['cubGV_tipificacion_respuesta2'] = $ruta2;
        $fila['cubGV_resumen'] = !empty($llamada['scLlamadas_texto']) ? $llamada['scLlamadas_texto'] : null;

        $rowMap = $this->auxBuscarPonderacion($campaniaId, (string)$ruta2, (int)$datos['cubGV_carteraId']);
        if ($rowMap !== null) {
            $fila['cubGV_cobmaparbolgstId'] = $rowMap['cobMapArbolGst_id'] ?? null;
            $fila['cubGV_ponderacion'] = (int)($rowMap['cobMapArbolGst_ponderacion'] ?? 0);
        }

        $condCubo = [
            'cubGV_numFactura' => (string)$fila['cubGV_numFactura'],
            'cubGV_carteraId'  => (string)$fila['cubGV_carteraId'],
            'cubGV_origen'     => self::TABLA_LLAMADAS,
            'cubGV_llamadaId'  => $fila['cubGV_llamadaId'],
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
            'cubGV_origen'    => self::TABLA_LLAMADAS,
            'cubGV_llamadaId' => $idLlamada,
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
            'cubGV_numFactura',
            'cubGV_carteraId',
            'cubGV_cedula',
            'cubGV_llamadaId',
        ];
        foreach ($indices as $campo) {
            $mdb->crearIndice(self::COLLECTION_CUBO, [$campo => 1]);
        }
    }
}
?><? //_FIN_DE_ARCHIVO 
    ?>