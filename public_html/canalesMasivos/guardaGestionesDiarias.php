<?
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "guardaGestionesDiarias no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/guardaGestionesDiarias.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}


$mongo = new MYMONGODB();
$mongoCiclo = new MYMONGODB();
$mdbAsig = new MYMONGODB();
$mdbGestion = new MYMONGODB();
$periodo = [];

$total = 0;
$totalMonto = 0;
$totalGestionado = 0;
$totalMontoGestion = 0;
$porcentajeGestion = 0;

$sinGestion = 0;
$totalMontoSinGestion = 0;
$porcentajeSinGestion = 0;

$totalMontoContactoDirecto = 0;
$totalMontoContactoIndirecto = 0;
$totalMontoSinContacto = 0;

$totalContactado = 0;
$totalMontoContactadaContacto = 0;
$porcentajeContactado = 0;

$contactoDirecto        = 0;
$porcentajeContactoDirecto  = 0;

$contactoIndirecto = 0;
$porcentajeContactoIndirecto  = 0;

$sinContactoGestionado = 0;
$porcentajeSinContactoGestionado = 0;

$pagaEnFecha = 0;
$porcentajepagaEnFecha = 0;
$totalGestionadoPagada = 0;
$totalMontoPagado = 0;
$porcentajeGestionadaPagada = 0;

$totalNoGestionadoPagada = 0;
$totalMontoNoGestionadoPagada = 0;
$porcentajeNoGestionadaPagada = 0;

$totalMontoContactoDirectoPagaFecha = 0;

//Intensidades
$totalGestionesInt  = 0;
$totalIntTelefonica = 0;
$totalIntWhatsapp   = 0;
$totalIntEmail      = 0;

//Intensidades Cartera Pagada
$totalPagadas = 0;
$totalGestionesPagadas = 0;
$totalTelefonicaPagadas = 0;
$totalWhatsappPagadas = 0;
$totalEmailPagadas = 0;


//Intensidades Cartera No Pagada
$totalNoPagadas = 0;
$totalGestionesNoPagadas = 0;
$totalTelefonicaNoPagadas = 0;
$totalWhatsappNoPagadas = 0;
$totalEmailNoPagadas = 0;

$carterasProcesadas = [];
$condicionCartera = ['activo' => (int) 1];
$mongo->buscar("control_carga_periodo", $condicionCartera);

while ($row = $mongo->siguiente()) {

    $cartera = $row["cartera"];

    if (in_array($cartera, $carterasProcesadas)) {
        continue;
    }
    $carterasProcesadas[] = $cartera;

    $fechaInicio = $row["fecha"];
    $fechaFin = $row["fechaFin"];

    $desde = strtotime(date('Y-m-d', $row['fecha']) . " 00:00:00");
    $hasta = strtotime(date("Y-m-d", $row['fechaFin']) . " 23:59:59");

    $condicionCubAsignacion = [
        "cubAG_carteraId" => (string)$cartera,
        "cubAG_fechaInicio" => (int)$fechaInicio
    ];

    $pipeline = [
        [
            '$match' => $condicionCubAsignacion
        ],
        [
            '$group' => [
                '_id' => null,
                // Total registros
                'totalRegistros' => ['$sum' => 1],
                // Total capital
                'totalCapital' => ['$sum' => '$cubAG_capitalActual'],
                // Total gestionados
                'totalGestionados' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAG_gestionada', 1]],
                            1,
                            0
                        ]
                    ]
                ],
                // Capital gestionado
                'capitalGestionado' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAG_gestionada', 1]],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                // Total sin gestión
                'totalSinGestion' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAG_gestionada', 0]],
                            1,
                            0
                        ]
                    ]
                ],
                // Capital sin gestión
                'capitalSinGestion' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubAG_gestionada', 0]],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                // Contacto Directo
                'totalContactoDirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Capital Contacto Directo
                'capitalContactoDirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
                                ]
                            ],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                // Contacto Indirecto
                'totalContactoIndirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Capital Contacto Indirecto
                'capitalContactoIndirecto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
                                ]
                            ],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                // Sin Contacto
                'totalSinContacto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Capital Sin Contacto
                'capitalSinContacto' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
                                ]
                            ],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                //Compromisos de pagos registrados
                'totalContactoDirectoPagaFecha' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                    ['$in' => ['$cubAG_tipificacion2', tipificacionesCompromisoPago()]]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                //Total monto paga en fecha
                'totalMontoContactoDirectoPagaFecha' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                    ['$in' => ['$cubAG_tipificacion2', tipificacionesCompromisoPago()]]
                                ]
                            ],
                            '$cubAG_capitalActual',
                            0
                        ]
                    ]
                ],
                // Total gestionadas pagadas
                'totalGestionadoPagada' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Total monto pagado
                'totalMontoPagado' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 1]],
                                    ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                ]
                            ],
                            '$cubAG_montoTotalPago',
                            0
                        ]
                    ]
                ],
                // Total no gestionadas pagadas
                'totalNoGestionadoPagada' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 0]],
                                    ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ],
                // Pagos no gestionadas pagadas
                'totalMontoNoGestionadoPagada' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$eq' => ['$cubAG_gestionada', 0]],
                                    ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                ]
                            ],
                            '$cubAG_montoTotalPago',
                            0
                        ]
                    ]
                ],
            ]
        ]
    ];


    $mdbAsig->aggregate("cuAsignacionesGestionAP", $pipeline);

    if ($row = $mdbAsig->siguiente()) {

        $total              = (int)$row['totalRegistros'];
        $totalMonto         = (float)round($row['totalCapital'], 2);

        $totalGestionado    = (int)$row['totalGestionados'];
        $totalMontoGestion  = (float)round($row['capitalGestionado'], 2);
        $porcentajeGestion  = $total > 0 ? round(($totalGestionado * 100) / $total, 2) : 0;

        $sinGestion  = (int) $row['totalSinGestion'];
        $totalMontoSinGestion = (float)round($row['capitalSinGestion'], 2);
        $porcentajeSinGestion  = $total > 0 ? round(($sinGestion * 100) / $total, 2) : 0;

        $contactoDirecto        = (int)$row['totalContactoDirecto'];
        $totalMontoContactoDirecto = (float)round($row['capitalContactoDirecto'], 2);

        $contactoIndirecto      = (int)$row['totalContactoIndirecto'];
        $totalMontoContactoIndirecto = (float)round($row['capitalContactoIndirecto'], 2);

        $sinContactoGestionado  = (int)$row['totalSinContacto'];
        $totalMontoSinContacto = (float)round($row['capitalSinContacto'], 2);
        $porcentajeSinContactoGestionado  = $totalGestionado > 0 ? round(($sinContactoGestionado * 100) / $totalGestionado, 2) : 0;

        $pagaEnFecha = (int)$row['totalContactoDirectoPagaFecha'];
        $totalMontoContactoDirectoPagaFecha = (float)round($row['totalMontoContactoDirectoPagaFecha'], 2);
        $porcentajepagaEnFecha  = $contactoDirecto > 0 ? round(($pagaEnFecha * 100) / $contactoDirecto, 2) : 0;

        $totalGestionadoPagada = (int)$row['totalGestionadoPagada'];
        $totalMontoPagado = (float)round($row['totalMontoPagado'], 2);
        $porcentajeGestionadaPagada = $totalGestionado > 0 ? round(($totalGestionadoPagada * 100) / $totalGestionado, 2) : 0;

        $totalNoGestionadoPagada = (int)$row['totalNoGestionadoPagada'];
        $totalMontoNoGestionadoPagada = (float)round($row['totalMontoNoGestionadoPagada'], 2);
        $porcentajeNoGestionadaPagada = $sinGestion > 0 ? round(($totalNoGestionadoPagada * 100) / $sinGestion, 2) : 0;
    }
    $totalContactado = $contactoDirecto + $contactoIndirecto;
    $totalMontoContactadaContacto = round(($totalMontoContactoDirecto + $totalMontoContactoIndirecto), 2);
    $porcentajeContactado = $totalGestionado > 0 ? round(($totalContactado * 100) / $totalGestionado, 2) : 0;

    $porcentajeContactoDirecto  = $totalContactado > 0 ? round(($contactoDirecto * 100) / $totalContactado, 2) : 0;
    $porcentajeContactoIndirecto  = $totalContactado > 0 ? round(($contactoIndirecto * 100) / $totalContactado, 2) : 0;



    //Se guarda -1 para los valores generales que solo son por cartera, no por ciclo
    //Guardar cartera asignada

    guardarIndicador($cartera, "CARTERA ASIGNADA", "TOTAL", $total,-1);
    guardarIndicador($cartera, "CARTERA ASIGNADA", "MONTO", $totalMonto,-1);

    //Guardar cartera gestionada

    guardarIndicador($cartera, "CARTERA GESTIONADA", "TOTAL", $totalGestionado,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA", "MONTO", $totalMontoGestion,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA", "PORCENTAJE", $porcentajeGestion,-1);

    //Guardar cartera gestionada no contactada

    guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "TOTAL", $sinContactoGestionado,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "MONTO", $totalMontoSinContacto,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "PORCENTAJE", $porcentajeSinContactoGestionado,-1);

    //Guardar  cartera gestionada contactada

    guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "TOTAL", $totalContactado,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "MONTO", $totalMontoContactadaContacto,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "PORCENTAJE", $porcentajeContactado,-1);

    //Guardar contacto directo

    guardarIndicador($cartera, "CONTACTO DIRECTO", "TOTAL", $contactoDirecto,-1);
    guardarIndicador($cartera, "CONTACTO DIRECTO", "MONTO", $totalMontoContactoDirecto,-1);
    guardarIndicador($cartera, "CONTACTO DIRECTO", "PORCENTAJE", $porcentajeContactoDirecto,-1);

    //Guardar compromiso de pago

    guardarIndicador($cartera, "COMPROMISO PAGO", "TOTAL", $pagaEnFecha,-1);
    guardarIndicador($cartera, "COMPROMISO PAGO", "MONTO", $totalMontoContactoDirectoPagaFecha,-1);
    guardarIndicador($cartera, "COMPROMISO PAGO", "PORCENTAJE", $porcentajepagaEnFecha,-1);

    //Guardar contacto indirecto
    guardarIndicador($cartera, "CONTACTO INDIRECTO", "TOTAL", $contactoIndirecto,-1);
    guardarIndicador($cartera, "CONTACTO INDIRECTO", "MONTO", $totalMontoContactoIndirecto,-1);
    guardarIndicador($cartera, "CONTACTO INDIRECTO", "PORCENTAJE", $porcentajeContactoIndirecto,-1);

    //Guardar total cartera gestionada pagada
    guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "TOTAL", $totalGestionadoPagada,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "MONTO", $totalMontoPagado,-1);
    guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "PORCENTAJE", $porcentajeGestionadaPagada,-1);

    //Guardar  cartera no gestionada

    guardarIndicador($cartera, "CARTERA NO GESTIONADA", "TOTAL", $sinGestion,-1);
    guardarIndicador($cartera, "CARTERA NO GESTIONADA", "MONTO", $totalMontoSinGestion,-1);
    guardarIndicador($cartera, "CARTERA NO GESTIONADA", "PORCENTAJE", $porcentajeSinGestion,-1);

    //Guardar cartera no gestionada pagada

    guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "TOTAL", $totalNoGestionadoPagada,-1);
    guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "MONTO", $totalMontoNoGestionadoPagada,-1);
    guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "PORCENTAJE", $porcentajeNoGestionadaPagada,-1);




    //INTENSIDADES

    $condicionCubGestion = [
        "cubGC_carteraId" => (string)$cartera,
        "cubGC_fechaGestion" => ['$gte' => $desde, '$lte' => $hasta]
    ];

    $pipelineGestion = [
        [
            '$match' => $condicionCubGestion
        ],
        [
            '$group' => [
                '_id' => null,

                // Total gestiones
                'totalGestiones' => [
                    '$sum' => 1
                ],

                // Telefónica
                'totalTelefonica' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubGC_canal', 'TELEFONICA']],
                            1,
                            0
                        ]
                    ]
                ],

                // WhatsApp
                'totalWhatsapp' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubGC_canal', 'WHATSAPP']],
                            1,
                            0
                        ]
                    ]
                ],

                // Email
                'totalEmail' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$cubGC_canal', 'EMAIL']],
                            1,
                            0
                        ]
                    ]
                ]
            ]
        ]
    ];


    $mdbGestion->aggregate("cuGestionCobranza", $pipelineGestion);

    if ($row = $mdbGestion->siguiente()) {

        $totalGestionesInt  = (int)$row['totalGestiones'];
        $totalIntTelefonica = (int)$row['totalTelefonica'];
        $totalIntWhatsapp   = (int)$row['totalWhatsapp'];
        $totalIntEmail      = (int)$row['totalEmail'];
    }

    //Guardar intensidades totales
    guardarIndicador($cartera, "INTENSIDAD", "TOTAL",   $total > 0 ? round(($totalGestionesInt / $total), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD AV", "TOTAL", $total > 0 ? round(($totalIntTelefonica / $total), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD WHATSAPP", "TOTAL", $total > 0 ? round(($totalIntWhatsapp / $total), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD EMAIL", "TOTAL", $total > 0 ? round(($totalIntEmail / $total), 2) : 0,-1);


    //Guardar intensidades cartera pagada

    $pagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$gte');

    $totalPagadas = $pagadas["totalPagos"];
    $totalGestionesPagadas = $pagadas["totalGestiones"];
    $totalTelefonicaPagadas = $pagadas["telefonica"];
    $totalWhatsappPagadas = $pagadas["whatsapp"];
    $totalEmailPagadas = $pagadas["email"];

    guardarIndicador($cartera, "INTENSIDAD PAGADA", "TOTAL",  $totalPagadas > 0 ? round(($totalGestionesPagadas / $totalPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD PAGADA AV", "TOTAL", $totalPagadas > 0 ? round(($totalTelefonicaPagadas / $totalPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD PAGADA WHATSAPP", "TOTAL", $totalPagadas > 0 ? round(($totalWhatsappPagadas / $totalPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD PAGADA EMAIL", "TOTAL", $totalPagadas > 0 ? round(($totalEmailPagadas / $totalPagadas), 2) : 0,-1);



    //Guardar intensidades cartera no pagada
    $noPagadas = obtenerGestionesPorPago($condicionCubAsignacion, '$lt');

    $totalNoPagadas = $noPagadas["totalPagos"];
    $totalGestionesNoPagadas = $noPagadas["totalGestiones"];
    $totalTelefonicaNoPagadas = $noPagadas["telefonica"];
    $totalWhatsappNoPagadas = $noPagadas["whatsapp"];
    $totalEmailNoPagadas = $noPagadas["email"];

    guardarIndicador($cartera, "INTENSIDAD NO PAGADA", "TOTAL",  $totalNoPagadas > 0 ? round(($totalGestionesNoPagadas / $totalNoPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD NO PAGADA AV", "TOTAL", $totalNoPagadas > 0 ? round(($totalTelefonicaNoPagadas / $totalNoPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD NO PAGADA WHATSAPP", "TOTAL", $totalNoPagadas > 0 ? round(($totalWhatsappNoPagadas / $totalNoPagadas), 2) : 0,-1);
    guardarIndicador($cartera, "INTENSIDAD NO PAGADA EMAIL", "TOTAL", $totalNoPagadas > 0 ? round(($totalEmailNoPagadas / $totalNoPagadas), 2) : 0,-1);

    // Busqueda por cada ciclo
    $mongoCiclo->buscar("control_carga_periodo", ["activo" => 1, "cartera" => $cartera]);
    $ciclos = [];
    while ($rowCiclo = $mongoCiclo->siguiente()) {
        $ciclos[] = $rowCiclo['periodo'];
    }

    foreach ($ciclos as $ciclo) {
        $condicionCubAsignacionCiclo = [
            "cubAG_carteraId" => (string)$cartera,
            "cubAG_fechaInicio" => (int)$fechaInicio,
            "cubAG_ciclo" => (int)$ciclo
        ];

        $pipeline = [
            [
                '$match' => $condicionCubAsignacionCiclo
            ],
            [
                '$group' => [
                    '_id' => null,
                    // Total registros
                    'totalRegistros' => ['$sum' => 1],
                    // Total capital
                    'totalCapital' => ['$sum' => '$cubAG_capitalActual'],
                    // Total gestionados
                    'totalGestionados' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 1]],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital gestionado
                    'capitalGestionado' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 1]],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Total sin gestión
                    'totalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 0]],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital sin gestión
                    'capitalSinGestion' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubAG_gestionada', 0]],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Contacto Directo
                    'totalContactoDirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Contacto Directo
                    'capitalContactoDirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Contacto Indirecto
                    'totalContactoIndirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Contacto Indirecto
                    'capitalContactoIndirecto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO INDIRECTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Sin Contacto
                    'totalSinContacto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Capital Sin Contacto
                    'capitalSinContacto' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'SIN CONTACTO']]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    //Compromisos de pagos registrados
                    'totalContactoDirectoPagaFecha' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubAG_tipificacion2', tipificacionesCompromisoPago()]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    //Total monto paga en fecha
                    'totalMontoContactoDirectoPagaFecha' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$eq' => ['$cubAG_tipificacion1', 'CONTACTO DIRECTO']],
                                        ['$in' => ['$cubAG_tipificacion2', tipificacionesCompromisoPago()]]
                                    ]
                                ],
                                '$cubAG_capitalActual',
                                0
                            ]
                        ]
                    ],
                    // Total gestionadas pagadas
                    'totalGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Total monto pagado
                    'totalMontoPagado' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 1]],
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                '$cubAG_montoTotalPago',
                                0
                            ]
                        ]
                    ],
                    // Total no gestionadas pagadas
                    'totalNoGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 0]],
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    // Pagos no gestionadas pagadas
                    'totalMontoNoGestionadoPagada' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$and' => [
                                        ['$eq' => ['$cubAG_gestionada', 0]],
                                        ['$gt' => ['$cubAG_montoTotalPago', 0]]
                                    ]
                                ],
                                '$cubAG_montoTotalPago',
                                0
                            ]
                        ]
                    ],
                ]
            ]
        ];


        $mdbAsig->aggregate("cuAsignacionesGestionAP", $pipeline);

        if ($row = $mdbAsig->siguiente()) {

            $total              = (int)$row['totalRegistros'];
            $totalMonto         = (float)round($row['totalCapital'], 2);

            $totalGestionado    = (int)$row['totalGestionados'];
            $totalMontoGestion  = (float)round($row['capitalGestionado'], 2);
            $porcentajeGestion  = $total > 0 ? round(($totalGestionado * 100) / $total, 2) : 0;

            $sinGestion  = (int) $row['totalSinGestion'];
            $totalMontoSinGestion = (float)round($row['capitalSinGestion'], 2);
            $porcentajeSinGestion  = $total > 0 ? round(($sinGestion * 100) / $total, 2) : 0;

            $contactoDirecto        = (int)$row['totalContactoDirecto'];
            $totalMontoContactoDirecto = (float)round($row['capitalContactoDirecto'], 2);

            $contactoIndirecto      = (int)$row['totalContactoIndirecto'];
            $totalMontoContactoIndirecto = (float)round($row['capitalContactoIndirecto'], 2);

            $sinContactoGestionado  = (int)$row['totalSinContacto'];
            $totalMontoSinContacto = (float)round($row['capitalSinContacto'], 2);
            $porcentajeSinContactoGestionado  = $totalGestionado > 0 ? round(($sinContactoGestionado * 100) / $totalGestionado, 2) : 0;

            $pagaEnFecha = (int)$row['totalContactoDirectoPagaFecha'];
            $totalMontoContactoDirectoPagaFecha = (float)round($row['totalMontoContactoDirectoPagaFecha'], 2);
            $porcentajepagaEnFecha  = $contactoDirecto > 0 ? round(($pagaEnFecha * 100) / $contactoDirecto, 2) : 0;

            $totalGestionadoPagada = (int)$row['totalGestionadoPagada'];
            $totalMontoPagado = (float)round($row['totalMontoPagado'], 2);
            $porcentajeGestionadaPagada = $totalGestionado > 0 ? round(($totalGestionadoPagada * 100) / $totalGestionado, 2) : 0;

            $totalNoGestionadoPagada = (int)$row['totalNoGestionadoPagada'];
            $totalMontoNoGestionadoPagada = (float)round($row['totalMontoNoGestionadoPagada'], 2);
            $porcentajeNoGestionadaPagada = $sinGestion > 0 ? round(($totalNoGestionadoPagada * 100) / $sinGestion, 2) : 0;
        }
        $totalContactado = $contactoDirecto + $contactoIndirecto;
        $totalMontoContactadaContacto = round(($totalMontoContactoDirecto + $totalMontoContactoIndirecto), 2);
        $porcentajeContactado = $totalGestionado > 0 ? round(($totalContactado * 100) / $totalGestionado, 2) : 0;

        $porcentajeContactoDirecto  = $totalContactado > 0 ? round(($contactoDirecto * 100) / $totalContactado, 2) : 0;
        $porcentajeContactoIndirecto  = $totalContactado > 0 ? round(($contactoIndirecto * 100) / $totalContactado, 2) : 0;



        //Guardar cartera asignada

        guardarIndicador($cartera, "CARTERA ASIGNADA", "TOTAL", $total, $ciclo);
        guardarIndicador($cartera, "CARTERA ASIGNADA", "MONTO", $totalMonto, $ciclo);

        //Guardar cartera gestionada

        guardarIndicador($cartera, "CARTERA GESTIONADA", "TOTAL", $totalGestionado, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA", "MONTO", $totalMontoGestion, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA", "PORCENTAJE", $porcentajeGestion, $ciclo);

        //Guardar cartera gestionada no contactada

        guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "TOTAL", $sinContactoGestionado, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "MONTO", $totalMontoSinContacto, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA NO CONTACTADA", "PORCENTAJE", $porcentajeSinContactoGestionado, $ciclo);

        //Guardar  cartera gestionada contactada

        guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "TOTAL", $totalContactado, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "MONTO", $totalMontoContactadaContacto, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA CONTACTADA", "PORCENTAJE", $porcentajeContactado, $ciclo);

        //Guardar contacto directo

        guardarIndicador($cartera, "CONTACTO DIRECTO", "TOTAL", $contactoDirecto, $ciclo);
        guardarIndicador($cartera, "CONTACTO DIRECTO", "MONTO", $totalMontoContactoDirecto, $ciclo);
        guardarIndicador($cartera, "CONTACTO DIRECTO", "PORCENTAJE", $porcentajeContactoDirecto, $ciclo);

        //Guardar compromiso de pago

        guardarIndicador($cartera, "COMPROMISO PAGO", "TOTAL", $pagaEnFecha, $ciclo);
        guardarIndicador($cartera, "COMPROMISO PAGO", "MONTO", $totalMontoContactoDirectoPagaFecha, $ciclo);
        guardarIndicador($cartera, "COMPROMISO PAGO", "PORCENTAJE", $porcentajepagaEnFecha, $ciclo);

        //Guardar contacto indirecto
        guardarIndicador($cartera, "CONTACTO INDIRECTO", "TOTAL", $contactoIndirecto, $ciclo);
        guardarIndicador($cartera, "CONTACTO INDIRECTO", "MONTO", $totalMontoContactoIndirecto, $ciclo);
        guardarIndicador($cartera, "CONTACTO INDIRECTO", "PORCENTAJE", $porcentajeContactoIndirecto, $ciclo);

        //Guardar total cartera gestionada pagada
        guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "TOTAL", $totalGestionadoPagada, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "MONTO", $totalMontoPagado, $ciclo);
        guardarIndicador($cartera, "CARTERA GESTIONADA PAGADA", "PORCENTAJE", $porcentajeGestionadaPagada, $ciclo);

        //Guardar  cartera no gestionada

        guardarIndicador($cartera, "CARTERA NO GESTIONADA", "TOTAL", $sinGestion, $ciclo);
        guardarIndicador($cartera, "CARTERA NO GESTIONADA", "MONTO", $totalMontoSinGestion, $ciclo);
        guardarIndicador($cartera, "CARTERA NO GESTIONADA", "PORCENTAJE", $porcentajeSinGestion, $ciclo);

        //Guardar cartera no gestionada pagada

        guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "TOTAL", $totalNoGestionadoPagada, $ciclo);
        guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "MONTO", $totalMontoNoGestionadoPagada, $ciclo);
        guardarIndicador($cartera, "CARTERA NO GESTIONADA PAGADA", "PORCENTAJE", $porcentajeNoGestionadaPagada, $ciclo);




        //INTENSIDADES

        $condicionCubGestionCiclo = [
            "cubGC_carteraId" => (string)$cartera,
            "cubGC_fechaGestion" => ['$gte' => $desde, '$lte' => $hasta],
            "cubGC_ciclo" => (int)$ciclo
        ];

        $pipelineGestion = [
            [
                '$match' => $condicionCubGestionCiclo
            ],
            [
                '$group' => [
                    '_id' => null,

                    // Total gestiones
                    'totalGestiones' => [
                        '$sum' => 1
                    ],

                    // Telefónica
                    'totalTelefonica' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'TELEFONICA']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // WhatsApp
                    'totalWhatsapp' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'WHATSAPP']],
                                1,
                                0
                            ]
                        ]
                    ],

                    // Email
                    'totalEmail' => [
                        '$sum' => [
                            '$cond' => [
                                ['$eq' => ['$cubGC_canal', 'EMAIL']],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $mdbGestion->aggregate("cuGestionCobranza", $pipelineGestion);

        if ($row = $mdbGestion->siguiente()) {

            $totalGestionesInt  = (int)$row['totalGestiones'];
            $totalIntTelefonica = (int)$row['totalTelefonica'];
            $totalIntWhatsapp   = (int)$row['totalWhatsapp'];
            $totalIntEmail      = (int)$row['totalEmail'];
        }

        //Guardar intensidades totales
        guardarIndicador($cartera, "INTENSIDAD", "TOTAL",   $total > 0 ? round(($totalGestionesInt / $total), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD AV", "TOTAL", $total > 0 ? round(($totalIntTelefonica / $total), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD WHATSAPP", "TOTAL", $total > 0 ? round(($totalIntWhatsapp / $total), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD EMAIL", "TOTAL", $total > 0 ? round(($totalIntEmail / $total), 2) : 0, $ciclo);


        //Guardar intensidades cartera pagada

        $pagadas = obtenerGestionesPorPago($condicionCubAsignacionCiclo, '$gte');

        $totalPagadas = $pagadas["totalPagos"];
        $totalGestionesPagadas = $pagadas["totalGestiones"];
        $totalTelefonicaPagadas = $pagadas["telefonica"];
        $totalWhatsappPagadas = $pagadas["whatsapp"];
        $totalEmailPagadas = $pagadas["email"];

        guardarIndicador($cartera, "INTENSIDAD PAGADA", "TOTAL",  $totalPagadas > 0 ? round(($totalGestionesPagadas / $totalPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD PAGADA AV", "TOTAL", $totalPagadas > 0 ? round(($totalTelefonicaPagadas / $totalPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD PAGADA WHATSAPP", "TOTAL", $totalPagadas > 0 ? round(($totalWhatsappPagadas / $totalPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD PAGADA EMAIL", "TOTAL", $totalPagadas > 0 ? round(($totalEmailPagadas / $totalPagadas), 2) : 0, $ciclo);



        //Guardar intensidades cartera no pagada
        $noPagadas = obtenerGestionesPorPago($condicionCubAsignacionCiclo, '$lt');

        $totalNoPagadas = $noPagadas["totalPagos"];
        $totalGestionesNoPagadas = $noPagadas["totalGestiones"];
        $totalTelefonicaNoPagadas = $noPagadas["telefonica"];
        $totalWhatsappNoPagadas = $noPagadas["whatsapp"];
        $totalEmailNoPagadas = $noPagadas["email"];

        guardarIndicador($cartera, "INTENSIDAD NO PAGADA", "TOTAL",  $totalNoPagadas > 0 ? round(($totalGestionesNoPagadas / $totalNoPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD NO PAGADA AV", "TOTAL", $totalNoPagadas > 0 ? round(($totalTelefonicaNoPagadas / $totalNoPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD NO PAGADA WHATSAPP", "TOTAL", $totalNoPagadas > 0 ? round(($totalWhatsappNoPagadas / $totalNoPagadas), 2) : 0, $ciclo);
        guardarIndicador($cartera, "INTENSIDAD NO PAGADA EMAIL", "TOTAL", $totalNoPagadas > 0 ? round(($totalEmailNoPagadas / $totalNoPagadas), 2) : 0, $ciclo);
    }
}






// Tipificaciones 2 que cuentan como compromiso de pago.
// Debe ser la misma lista que tipificacionesCompromisoPago() de cmDashboardGestionesCtrl.php.
function tipificacionesCompromisoPago(): array
{
    return ['PAGA EN FECHA', 'ABONO CUOTA', 'CONFIRMACION DE PAGO', 'NEGOCIACION EN CURSO ALIVIO', 'COMPROMISO DE PAGO'];
}

function guardarIndicador($cartera, $grupo, $indicador, $valor, $ciclo)
{
    $mdb = new MYMONGODB();
    static $fecha;

    if (!$fecha) {
        $fecha = time();
    }

    // Condición de búsqueda
    $condicion = [
        "avHist_cartera" => (int)$cartera,
        "avHist_grupo" => $grupo,
        "avHist_indicador" => $indicador,
        "avHist_fecha" => (int)$fecha,
        "avHist_ciclo" => (int)$ciclo
    ];

    // Verificar si ya existe
    $buscar = $mdb->buscar('avHistorialGestiones', $condicion, [], [], 1);

    if ($buscar > 0) {
        echo "Registro ya existe. Cartera: $cartera Grupo: $grupo Indicador: $indicador";
        if (!is_null($ciclo)) echo " Ciclo: $ciclo";
        echo " Fecha: $fecha<br>";
        return;
    }

    // Objeto a guardar
    $obj = [
        "avHist_cartera" => (int)$cartera,
        "avHist_grupo" => $grupo,
        "avHist_fecha" => (int)$fecha,
        "avHist_indicador" => $indicador,
        "avHist_valor" => $valor,
        "avHist_ciclo" => (int)$ciclo
    ];

    $resGuardado = $mdb->guardar('avHistorialGestiones', $obj);

    if ($resGuardado > 0) {
        echo "Valor guardado correctamente. Cartera: $cartera Grupo: $grupo Indicador: $indicador  Ciclo: $ciclo <br>";

    } else {
        echo "Error. Cartera: $cartera Grupo: $grupo Indicador: $indicador  Ciclo: $ciclo <br>";
      
    }
}

function obtenerGestionesPorPago($condicionCubAsignacion, $operadorExpr)
{
    $mdb = new MYMONGODB();

    $condicionCubAsignacion["cubAG_gestionada"] = (int)1;
    $condicionCubAsignacion['$expr'] = [
        $operadorExpr => ['$cubAG_montoTotalPago', '$cubAG_deudaNetaActual']
    ];

    $pipeline = [
        [
            '$match' => $condicionCubAsignacion
        ],
        [
            '$lookup' => [
                'from' => 'cuGestionCobranza',
                'let' => [
                    'factura' => '$cubAG_numFactura',
                    'cartera' => '$cubAG_carteraId',
                    'periodo' => '$cubAG_ciclo',
                    'fechaPeriodo' => '$cubAG_fechaPeriodo'
                ],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$eq' => ['$cubGC_numFactura', '$$factura']],
                                    ['$eq' => ['$cubGC_carteraId', '$$cartera']],
                                    ['$eq' => ['$cubGC_ciclo', '$$periodo']],
                                    ['$eq' => ['$cubGC_fechaPeriodo', '$$fechaPeriodo']]
                                ]
                            ]
                        ]
                    ]
                ],
                'as' => 'gestiones'
            ]
        ],
        [
            '$group' => [
                '_id' => null,

                'totalPagos' => ['$sum' => 1],

                'totalGestionesPagadas' => [
                    '$sum' => ['$size' => '$gestiones']
                ],

                'totalTelefonicaPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'TELEFONICA']]
                            ]
                        ]
                    ]
                ],

                'totalWhatsappPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'WHATSAPP']]
                            ]
                        ]
                    ]
                ],

                'totalEmailPagada' => [
                    '$sum' => [
                        '$size' => [
                            '$filter' => [
                                'input' => '$gestiones',
                                'as' => 'g',
                                'cond' => ['$eq' => ['$$g.cubGC_canal', 'EMAIL']]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    $mdb->aggregate("cuAsignacionesGestionAP", $pipeline);

    $resultado = [
        "totalPagos" => 0,
        "totalGestiones" => 0,
        "telefonica" => 0,
        "whatsapp" => 0,
        "email" => 0
    ];

    if ($row = $mdb->siguiente()) {
        $resultado["totalPagos"] = (int)$row['totalPagos'];
        $resultado["totalGestiones"] = (int)$row['totalGestionesPagadas'];
        $resultado["telefonica"] = (int)$row['totalTelefonicaPagada'];
        $resultado["whatsapp"] = (int)$row['totalWhatsappPagada'];
        $resultado["email"] = (int)$row['totalEmailPagada'];
    }

    return $resultado;
}





echo "EJECUCION_COMPLETA";


?><?

    //_FIN_DE_ARCHIVO 
    ?>