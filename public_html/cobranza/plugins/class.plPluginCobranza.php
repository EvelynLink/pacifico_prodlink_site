<?php

// __PlantillaNombre__: Plantilla Cobranza
// __PlantillaModulo__: Cobranza
// __PlantillaPlugIn__: cobranza/plugins/class.plPluginCobranza.php
// __PlantillaDescripcion__: Plantilla que sirve para el envio de datos de cobranza

require_once("../plantillas/classes/class.plBase.php");

class plPluginCobranza extends plBase
{

    protected $plantillaId;

    function __construct($plantillaId = 0)
    {
        if ($plantillaId > 0) {
            $this->plantillaId = $plantillaId;
        }
    }

    function enviarParametros()
    {
        $valores = [
            array('value' => '::valor_cuota::', 'name' => '::valor_cuota::'),
            array('value' => '::fecha_mas_vencida::', 'name' => '::fecha_mas_vencida::'),
            array('value' => '::fecha_vencimiento::', 'name' => '::fecha_vencimiento::'),
            array('value' => '::dia_corte::', 'name' => '::dia_corte::'),
            array('value' => '::dia_largo_corte::', 'name' => '::dia_largo_corte::'),
            array('value' => '::nro_cuotas_vencidas::', 'name' => '::nro_cuotas_vencidas::'),
            array('value' => '::fecha_hora_local::', 'name' => '::fecha_hora_local::'),
            array('value' => '::ultimo_pago::', 'name' => '::ultimo_pago::'),
            array('value' => '::fecha_ultimo_pago::', 'name' => '::fecha_ultimo_pago::'),
            array('value' => '::monto_total::', 'name' => '::monto_total::'),
            array('value' => '::monto_total_vencido::', 'name' => '::monto_total_vencido::'),
            array('value' => '::monto_total_por_vencer::', 'name' => '::monto_total_por_vencer::'),
            array('value' => '::direccion::', 'name' => '::direccion::'),
            array('value' => '::cuotas_por_vencer::', 'name' => '::cuotas_por_vencer::'),
            array('value' => '::deuda_total::', 'name' => '::deuda_total::'),
            array('value' => '::valor_promocion::', 'name' => '::valor_promocion::'),
            array('value' => '::nombreCorto::', 'name' => '::nombreCorto::'),
            array('value' => '::soloNombre::', 'name' => '::solosNombre::'),
            array('value' => '::ultimoDiaMes::', 'name' => '::ultimoDiaMes::'),
            array('value' => '::perido::', 'name' => '::perido::'),
            array('value' => '::campania::', 'name' => 'Nombre de la campaña'),
            array('value' => '::salto_pagina::', 'name' => 'Salto de pagina'),
            array('value' => '::fechaPeriodo::', 'name' => '::fechaPeriodo::'),
            array('value' => '::ultimoDiaMesAnt::', 'name' => '::ultimoDiaMesAnt::'),
            array('value' => '::valor_cuotaTotal::', 'name' => '::valor_cuotaTotal::'),
            array('value' => '::ultimoDiaMesAnt2::', 'name' => '::ultimoDiaMesAnt2::'),
            array('value' => '::saludos::', 'name' => '::saludos::', 'label' => 'Saludos segun la hora, Buenos dias, Buenas tardes o Buenas noche'),
            array('value' => '::dias_mora::', 'name' => '::dias_mora::'),
            array('value' => '::urlCorta::', 'name' => '::urlCorta::'),
            array('value' => '::dsct50::', 'name' => '::dsct50::'),
            array('value' => '::marca::', 'name' => '::marca::'),
            array('value' => '::ultNumCI::', 'name' => '::ultNumCI::', 'label' => 'Ultimos 4 número de la cédula'),
            array('value' => '::meta::', 'name' => '::meta::'),
            array('value' => '::porcentaje::', 'name' => '::porcentaje::'),
            array('value' => '::valorDev::', 'name' => '::valorDev::'),
            array('value' => '::producto::', 'name' => '::producto::'),
            array('value' => '::correo::', 'name' => '::correo::'),
            array('value' => '::capitalVencido::', 'name' => '::capitalVencido::'),
            array('value' => '::carteraFirma::', 'name' => 'Cartera Firma', 'label' => 'Firma nombre de la cartera con Fullcredit'),
            array('value' => '::identificacion::', 'name' => 'Identificación del deudor', 'label' => 'Identificación del deudor'),
            array('value' => '::mes_actual::', 'name' => 'Mes actual', 'label' => 'Mes actual'),
            array('value' => '::soloCarteraFirma::', 'name' => 'Solo Cartera Firma', 'label' => 'Firma nombre de la cartera sin Fullcredit'),
            array('value' => '::nro_operacion::', 'name' => 'Solo nro_operacion Firma', 'label' => 'Numero de operacion'),
            array('value' => '::dia_laborablex36h::', 'name' => 'Día laborable 36h', 'label' => 'Retorna el día laborable x 36h'),
        ];
        // $cnf = getConf("Cobranza");
        // $variablesReemplazo = $cnf["Variables reemplazo mail sms"];
        // foreach ($variablesReemplazo as $value) {
        //     $partes = explode("|", $value);
        //     $valores[] = ['value' => $partes[0], 'name' => $partes[0]];
        // }
        return $valores;
    }

    function obtenerPlantilla($plantillaId, $operacion, $carteraId, $data = '', $mongo2 = new MYMONGODB(), $mongo6 = new MYMONGODB())
    {

        if ($data == '') {
            $ramaMensaje = $this->obtenerPlantillaTextoSimpleporId($plantillaId);
        } else {
            $ramaMensaje = $data;
        }

        $cursorCbCrd = $mongo2->buscar('cbCreditos', ['cre_factura' => (string) $operacion, 'cre_inactivo' => (int) 0, 'cre_carteraId' => (string) $carteraId], [], ['cre_diaCorte' => -1], 1);
        $monto = 0;
        $diaCorte = 0;
        $valorCuota = 0;
        $fechaMasVencida = 0;
        $cuotasVencidas = 0;
        $deudaTotal = 0;
        $montoTotal = 0;
        $dtosFecha = getdate(time());
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $mes = $dtosFecha['mon'];
        $mes_actual = $meses[intval(date('m'))];
        $mensaje = '';
        $fechaPeriodo = '';
        $valores_reemplazo = [];
        $valoresPlantilla = [];
        $cursorCbCrd2 = $mongo6->buscar('cbCreditos', ['cre_factura' => (string) $operacion, 'cre_inactivo' => (int) 0, 'cre_carteraId' => (string) $carteraId], [], ['cre_diasMoraFactura' => -1]);
        if ($cursorCbCrd2 > 0) {
            while ($cur = $mongo6->siguiente()) {
                $deuda = $cur['cre_deudaNeta'];
                $deudaTotal += $deuda;
            }
        }
        if ($cursorCbCrd > 0) {
            $rowCrd = $mongo2->siguiente();
            $cartera = $rowCrd['cre_nombreCartera'];
            $carteraId = $rowCrd['cre_carteraId'];
            $marca = $rowCrd['cre_marca'];
            $producto = $rowCrd['cre_producto'];
            $periodo = $rowCrd['cre_periodo'];
            $fecha_hora_local = $this->fechaLarga();
            $valorDevolucion = $rowCrd['cre_valorDevolucion'];
            $metaConsumo = $rowCrd['cre_meta'];
            $porcentaje = $rowCrd['cre_porcentaje'];
            $fec36h = $this->fechaLarga($this->obtenerDiaLaborable(time(), 3));

            $fecha_vencimiento = '';
            if (isset($rowCrd['cre_fechaVencimiento']) && intval($rowCrd['cre_fechaVencimiento']) > 0) {
                $fec = explode("/", date('Y/m/d', $rowCrd['cre_fechaVencimiento']));
                $year = $fec[0];//dato del año
                $day = $fec[2];//dato del dia
                $month = $meses[intval($fec[1])];
                $fecha_vencimiento = $day . " de " . $month . " de " . $year;
            }

            if (isset($rowCrd['cre_fechaPeriodo']) && intval($rowCrd['cre_fechaPeriodo']) > 0) {
                $fec = explode("/", date('Y/m/d', $rowCrd['cre_fechaPeriodo']));
                $year = $fec[0];//dato del año
                $day = $fec[2];//dato del dia
                $month = $meses[intval($fec[1])];
                $fechaPeriodo = $day . " de " . $month . " de " . $year;
            }


            if ($data == '') {
                $c = $mongo2->buscar("plPlantillas", ["_id" => $mongo2->String2MongoId($plantillaId)]);
                if ($c > 0) {
                    $rpl = $mongo2->siguiente();
                    foreach ($rpl["pl_referencia"] as $plval) {
                        if (strpos($plval, 'firma:') !== false) {
                            $cartera = trim(explode(':', $plval)[1]);
                        }
                    }
                }
            }

            $correo = '';
            $c = $mongo2->buscar("cbEmail", ["mail_cedula" => (string) $rowCrd['cre_cedula']], [], ['_id' => -1]);
            if ($c > 0) {
                $corr = $mongo2->siguiente();
                $correo = $corr['mail_email'];
            }

            $monto = $rowCrd['cre_deudaNeta'];
            if (isset($rowCrd['cre_diaCorte'])) {
                $diaCorte = $rowCrd['cre_diaCorte'];
            }
            $dsct50 = number_format($monto / 2, 2, '.', '');
            $posCuota = strpos($rowCrd['cre_valorCuota'], "/");
            $valorCuota = trim(substr($rowCrd['cre_valorCuota'], $posCuota + 1, 5));
            $fechaMasVencida = $rowCrd['cre_fechaMasVencida'];
            $cuotasVencidas = $rowCrd['cre_cuotasAtrasadas'];
            $capitalVencido = $rowCrd['cre_capitalVencido'];
            $montoTotal = $rowCrd['cre_saldoCapital'];
            $ultNumCI = substr($rowCrd['cre_cedula'], -4);
            $cedula = $rowCrd['cre_cedula'];
            $monto_total_vencido = $rowCrd['cre_deudaNeta'];
            $diasMora = $rowCrd['cre_diasMoraFactura'];
            $monto_total_por_vencer = $rowCrd['cre_totalPorVencer'];
            $ultimoPago = 0;
            $ultimoPago = $this->getUltimoPago($rowCrd['cre_factura']);
            $ultFechaPago = $rowCrd['cre_fechaUltimoPago'];
            $fragmentos = [];
            $fragmentos = explode("::", $ramaMensaje);
            $valorDevolucion = $rowCrd['cre_valorDevolucion'];
            $metaConsumo = $rowCrd['cre_meta'];
            $porcentaje = $rowCrd['cre_porcentaje'];

            // $apellido1 = '';
            // $nombre1 = '';
            // $apellido1Gen = '';
            // $nombre1Gen = '';
            // $nombreCorto = '';
            // $apellidosArr = explode(" ", $rowCrd['cre_apellidos']);
            // $nombresArr = explode(" ", $rowCrd['cre_nombres']);
            // $apellido1Gen = $apellidosArr[0];
            // $nombre1Gen = $nombresArr[0];
            // if ($cobCartera_id == '39') {
            // $cnd = ['identificacion' => (string) $rowCrd['cre_cedula']];
            // $cursorClientes = $mongo1->buscar('portcollInfoClientes', $cnd);
            $direccion = '';
            // if ($cursorClientes > 0) {
            //     $rowInfo = $mongo1->siguiente();
            //     $apellido1 = $rowInfo['apellidoPaterno'];
            //     $nombre1 = $rowInfo['primerNombre'];
            //     $direccion = trim($rowInfo['callePrincipalDomi'] . " " . $rowInfo['calleSecundariaDomi']);
            //     if ($apellido1 == '') {
            //         $apellido1 = $apellido1Gen;
            //         $nombre1 = $nombre1Gen;
            //     }
            // } else {
            // $apellido1 = $apellido1Gen;
            // $nombre1 = $nombre1Gen;
            // }
            $lastDay = date("d/m/Y", time());
            $lastDayPrev = date('d-m-Y', strtotime('last day of previous month'));
            $lastDayPrev2M = date('d-m-Y', strtotime('last day of -2 month'));
            $hora = intval(date('H', time()));
            if ($hora < 12) {
                $saludos = 'Buenos dias';
            } elseif ($hora >= 12 && $hora < 18) {
                $saludos = 'Buenas tardes';
            } elseif ($hora >= 18) {
                $saludos = 'Buenas noches';
            }
            $nombreCorto = $rowCrd['cre_primerNombre'] . ' ' . $rowCrd['cre_apellidoPaterno'];
            $soloNombre = isset($rowCrd['cre_primerNombre']) && $rowCrd['cre_primerNombre'] != '' ? $rowCrd['cre_primerNombre'] : $rowCrd['cre_nombres'];
            $mensaje = $ramaMensaje;
            $valores_reemplazo = array(
                "::valor_cuota::" => $valorCuota,
                "::fecha_mas_vencida::" => date("d/m/Y", $fechaMasVencida),
                "::fecha_hora_local::" => $fecha_hora_local,
                "::dia_corte::" => $diaCorte,
                "::fecha_vencimiento::" => $fecha_vencimiento,
                "::nro_cuotas_vencidas::" => $cuotasVencidas,
                "::ultimo_pago::" => $ultimoPago,
                "::fecha_ultimo_pago::" => $ultFechaPago > 0 ? date("d/m/Y", $ultFechaPago) : '',
                "::monto_total::" => $montoTotal,
                "::periodo::" => $periodo,
                "::identificacion::" => $cedula,
                "::fechaPeriodo::" => $fechaPeriodo,
                "::deuda_total::" => $deudaTotal,
                "::capitalVencido::" => $capitalVencido,
                "::nombreCorto::" => $nombreCorto,
                "::soloNombre::" => $soloNombre,
                "::ultimoDiaMes::" => $lastDay,
                "::ultimoDiaMesAnt::" => $lastDayPrev,
                "::ultimoDiaMesAnt2::" => $lastDayPrev2M,
                "::dias_mora::" => $diasMora,
                "::nro_operacion::" => $operacion,
                "::cuotas_por_vencer::" => $cuotasVencidas,
                "::mes_actual::" => $mes_actual,
                "::direccion::" => $direccion,
                "::dia_laborablex36h::" => $fec36h,
                "::marca::" => $marca,
                "::ultNumCI::" => $ultNumCI,
                "::dsct50::" => $dsct50,
                "::correo::" => $correo,
                "::campania::" => '',
                "::producto::" => $producto,
                "::saludos::" => $saludos,
                "::monto_total_por_vencer::" => $monto_total_por_vencer,
                "::monto_total_vencido::" => $monto_total_vencido,
                "::carteraFirma::" => $cartera . '-Fullcredit',
                "::soloCarteraFirma::" => $cartera,
                "::valorDev::" => $valorDevolucion,
                "::meta::" => $metaConsumo,
                "::porcentaje::" => $porcentaje
            );
            // if (count($fragmentos) > 0) {
            //     foreach ($fragmentos as $value1) {
            //         if ($value1 == 'valor_cuota') {
            //             $mensaje = str_replace('::valor_cuota::', $valorCuota, $mensaje);
            //         } else if ($value1 == 'fecha_mas_vencida') {
            //             $mensaje = str_replace('::fecha_mas_vencida::', date("d-m-Y", $fechaMasVencida), $mensaje);
            //         } else if ($value1 == 'fecha_hora_local') {
            //             $mensaje = str_replace('::fecha_hora_local::', $fecha_hora_local, $mensaje);
            //         } else if ($value1 == 'periodo') {
            //             $mensaje = str_replace('::periodo::', $periodo, $mensaje);
            //         } else if ($value1 == 'dia_laborablex36h') {
            //             $mensaje = str_replace('::dia_laborablex36h::', $fec36h, $mensaje);
            //         } else if ($value1 == 'identificacion') {
            //             $mensaje = str_replace('::identificacion::', $cedula, $mensaje);
            //         } else if ($value1 == 'fechaPeriodo') {
            //             $mensaje = str_replace('::fechaPeriodo::', $fechaPeriodo, $mensaje);
            //         } else if ($value1 == 'dia_corte') {
            //             $mensaje = str_replace('::dia_corte::', $diaCorte, $mensaje);
            //         } else if ($value1 == 'correo') {
            //             $mensaje = str_replace('::correo::', $correo, $mensaje);
            //         } else if ($value1 == 'fecha_vencimiento') {
            //             $mensaje = str_replace('::fecha_vencimiento::', $fecha_vencimiento, $mensaje);
            //         } else if ($value1 == 'dia_corte_largo') {
            //             $mensaje = str_replace('::dia_largo_corte::', $diaCorte . ' de ' . $meses[$mes], $mensaje);
            //         } else if ($value1 == 'nro_cuotas_vencidas') {
            //             $mensaje = str_replace('::nro_cuotas_vencidas::', $cuotasVencidas, $mensaje);
            //         } else if ($value1 == 'ultimo_pago') {
            //             $mensaje = str_replace('::ultimo_pago::', $ultimoPago, $mensaje);
            //         } else if ($value1 == 'fecha_ultimo_pago') {
            //             $mensaje = str_replace('::fecha_ultimo_pago::', date("d-m-Y", $ultFechaPago), $mensaje);
            //         } else if ($value1 == 'monto_total') {
            //             $mensaje = str_replace('::monto_total::', $montoTotal, $mensaje);
            //         } else if ($value1 == 'nombreCorto') {
            //             $mensaje = str_replace('::nombreCorto::', $nombreCorto, $mensaje);
            //         } else if ($value1 == 'soloNombre') {
            //             $mensaje = str_replace('::soloNombre::', $soloNombre, $mensaje);
            //         } else if ($value1 == 'ultimoDiaMes') {
            //             $mensaje = str_replace('::ultimoDiaMes::', $lastDay, $mensaje);
            //         } else if ($value1 == 'ultimoDiaMesAnt') {
            //             $mensaje = str_replace('::ultimoDiaMesAnt::', $lastDayPrev, $mensaje);
            //         } else if ($value1 == 'ultimoDiaMesAnt2') {
            //             $mensaje = str_replace('::ultimoDiaMesAnt2::', $lastDayPrev2M, $mensaje);
            //         } else if ($value1 == 'dsct50') {
            //             $mensaje = str_replace('::dsct50::', $dsct50, $mensaje);
            //         } else if ($value1 == 'mes_actual') {
            //             $mensaje = str_replace('::mes_actual::', $mes_actual, $mensaje);
            //         } else if ($value1 == 'deuda_total') {
            //             $mensaje = str_replace('::deuda_total::', $deudaTotal, $mensaje);
            //         } else if ($value1 == 'capitalVencido') {
            //             $mensaje = str_replace('::capitalVencido::', $capitalVencido, $mensaje);
            //         } else if ($value1 == 'nro_operacion') {
            //             $mensaje = str_replace('::nro_operacion::', $operacion, $mensaje);
            //         } else if ($value1 == 'dias_mora') {
            //             $mensaje = str_replace('::dias_mora::', $diasMora, $mensaje);
            //         } else if ($value1 == 'cuotas_por_vencer') {
            //             $mensaje = str_replace('::cuotas_por_vencer::', $cuotasVencidas, $mensaje);
            //         } else if ($value1 == 'direccion') {
            //             $mensaje = str_replace('::direccion::', $direccion, $mensaje);
            //         } else if ($value1 == 'monto_total_por_vencer') {
            //             $mensaje = str_replace('::monto_total_por_vencer::', $monto_total_por_vencer, $mensaje);
            //         } else if ($value1 == 'monto_total_vencido') {
            //             $mensaje = str_replace('::monto_total_vencido::', $monto_total_vencido, $mensaje);
            //         } else if ($value1 == 'carteraFirma') {
            //             $mensaje = str_replace('::cartera_Firma::', $cartera . '-Fullcredit', $mensaje);
            //         } else if ($value1 == 'saludos') {
            //             $mensaje = str_replace('::saludos::', $saludos, $mensaje);
            //         } else if ($value1 == 'marca') {
            //             $mensaje = str_replace('::marca::', $marca, $mensaje);
            //         } else if ($value1 == 'ultNumCI') {
            //             $mensaje = str_replace('::ultNumCI::', $ultNumCI, $mensaje);
            //         } else if ($value1 == 'meta') {
            //             $mensaje = str_replace('::meta::', $metaConsumo, $mensaje);
            //         } else if ($value1 == 'maximo') {
            //             $mensaje = str_replace('::maximo::', $valorDevolucion, $mensaje);
            //         } else if ($value1 == 'valor') {
            //             $mensaje = str_replace('::valor::', $porcentaje, $mensaje);
            //         } else if ($value1 == 'producto') {
            //             $mensaje = str_replace('::producto::', $producto, $mensaje);
            //         } else if ($value1 == 'solo_cartera_Firma') {
            //             $mensaje = str_replace('::soloCarteraFirma::', $cartera, $mensaje);
            //         }
            //     }
            // }
            // $mensaje = str_replace('::salto_pagina::', '\n\n', $mensaje);

            $variables = [
                'valor_cuota'            => $valorCuota,
                'fecha_mas_vencida'      => date('d-m-Y', $fechaMasVencida),
                'fecha_hora_local'       => $fecha_hora_local,
                'periodo'                => $periodo,
                'dia_laborablex36h'      => $fec36h,
                'identificacion'         => $cedula,
                'fechaPeriodo'           => $fechaPeriodo,
                'dia_corte'              => $diaCorte,
                'correo'                 => $correo,
                'fecha_vencimiento'      => $fecha_vencimiento,
                'dia_corte_largo'        => $diaCorte . ' de ' . $meses[$mes],
                'nro_cuotas_vencidas'    => $cuotasVencidas,
                'ultimo_pago'            => $ultimoPago,
                'fecha_ultimo_pago'      => date('d-m-Y', $ultFechaPago),
                'monto_total'            => $montoTotal,
                'nombreCorto'            => $nombreCorto,
                'soloNombre'             => $soloNombre,
                'ultimoDiaMes'           => $lastDay,
                'ultimoDiaMesAnt'        => $lastDayPrev,
                'ultimoDiaMesAnt2'       => $lastDayPrev2M,
                'dsct50'                 => $dsct50,
                'mes_actual'             => $mes_actual,
                'deuda_total'            => $deudaTotal,
                'capitalVencido'         => $capitalVencido,
                'nro_operacion'          => $operacion,
                'dias_mora'              => $diasMora,
                'cuotas_por_vencer'      => $cuotasVencidas,
                'direccion'              => $direccion,
                'monto_total_por_vencer' => $monto_total_por_vencer,
                'monto_total_vencido'    => $monto_total_vencido,
                'carteraFirma'           => $cartera . '-Fullcredit',
                'saludos'                => $saludos,
                'marca'                  => $marca,
                'ultNumCI'               => $ultNumCI,
                'meta'                   => $metaConsumo,
                'maximo'                 => $valorDevolucion,
                'valor'                  => $porcentaje,
                'producto'               => $producto,
                'solo_cartera_Firma'     => $cartera,
            ];
            if (count($fragmentos) > 0) {
                preg_match_all('/::([^:]+)::/', $ramaMensaje, $matches);
                foreach (array_unique($matches[1]) as $key) {
                    if ($key === 'salto_pagina') {
                        continue;
                    }
                    $valor = $variables[$key] ?? 'VALOR_NO_ASIGNADO';
                    // Guardamos el valor para la plantilla
                    $valoresPlantilla[$key] = $valor;
                    // Reemplazamos en el mensaje
                    $mensaje = str_replace("::{$key}::", $valor, $mensaje);
                }
            }
            // Reemplazo especial
            $mensaje = str_replace('::salto_pagina::', "\n\n", $mensaje);
        }

        return [
            "mensaje" => $mensaje, 
            "valores_reemplazo" => $valores_reemplazo, 
            "mensaje_sin_reemplazos"=>$ramaMensaje, 
            'valores_reemplazo_plantilla' => $valoresPlantilla
        ];

    }

    function obtenerDiaLaborable(int $fechaInicialTimestamp, $dias): int
    {
        $mongo = new MYMONGODB();
        $feriados = [];
        $mongo->buscar('Feriados', ['anio' => ['$gte' => intval(date('Y', time()))]]);
        while ($cur = $mongo->siguiente()) {
            $feriados[] = intval(strtotime(date('Y-m-d 00:00:00', $cur['fecha'])));
        }

        $fecha = strtotime(date('Y-m-d', $fechaInicialTimestamp));

        $feriadosByYmd = [];
        foreach ($feriados as $f) {
            $feriadosByYmd[date('Y-m-d', $f)] = true;
        }

        $contador = 0;

        while (true) {
            $ymd = date('Y-m-d', $fecha);
            $diaSemana = (int) date('N', $fecha); // 1 lun - 7 dom

            $esFeriado = isset($feriadosByYmd[$ymd]);
            $esFinDeSemana = ($diaSemana >= 6);

            if (!$esFinDeSemana && !$esFeriado) {
                $contador++;
            }

            if ($contador >= $dias) {
                return $fecha;
            }

            $fecha = strtotime("+1 day", $fecha);
        }
    }

    function fechaLarga($laFecha = '')
    {
        $datetime = new DateTime();
        if ($laFecha != '') {
            $fecha = $datetime->createFromFormat('d/m/Y', date('d/m/Y', $laFecha));
        } else {
            $fecha = $datetime;
        }
        $dias = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];

        // Array con los nombres de los meses en español
        $meses = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre'
        ];

        // Obtener partes de la fecha
        $nombreDiaIngles = $fecha->format('l');
        $nombreMesIngles = $fecha->format('F');
        $nombreDiaEspanol = $dias[$nombreDiaIngles] ?? $nombreDiaIngles;
        $nombreMesEspanol = $meses[$nombreMesIngles] ?? $nombreMesIngles;

        // Obtener diferencia horaria GMT
        $gmtOffset = $fecha->format('P'); // Formato: +05:00 o -05:00
        $gmtString = 'ECT (GMT' . $gmtOffset . ")";

        // Obtener hora actual en formato HH:MM:SS
        $horaActual = $fecha->format('H:i:s');
        // Formatear la fecha final
        if ($laFecha != '') {
            $fechaFormateada = sprintf(
                "%s, %s %d, %d %s %s",
                $nombreDiaEspanol,
                $nombreMesEspanol,
                $fecha->format('d'),
                $fecha->format('Y'),
                $gmtString,
                $horaActual
            );
        } else {
            $fechaFormateada = sprintf(
                "%s, %s %d, %d %s %s (Hoy)",
                $nombreDiaEspanol,
                $nombreMesEspanol,
                $fecha->format('d'),
                $fecha->format('Y'),
                $gmtString,
                $horaActual
            );
        }
        return $fechaFormateada;
    }
    function getUltimoPago($fact)
    {
        $mongo = new MYMONGODB();
        $coleccion = "cbPagos";
        $pago = 0;
        $r = $mongo->buscar($coleccion, ['pagos_numFactura' => (string) $fact, 'pagos_monto' => ['$gt' => 0], 'pagos_inactivo' => (int) 0, 'pagos_reverso' => ['$exists' => false]], [], ['_id' => -1], 1);
        //print_h(count($r));
        if ($r > 0) {
            while ($row = $mongo->siguiente()) {
                $pago = $row['pagos_monto'];
            }
        }
        return $pago;
    }

    function crearPlantilla($documento)
    {
        return $this->insertar($documento);
    }

    function guardar($documento)
    {
        return $this->insertar($documento);
    }

    function procesar($lsPlantillaId, $lsProcesoId)
    {

        $respuesta = array('status' => false, 'data' => '');
        return $respuesta;
    }

}

?>
<?

//_FIN_DE_ARCHIVO      ?>