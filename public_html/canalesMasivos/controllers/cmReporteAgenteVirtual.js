app.controller("cmReporteAgenteVirtual", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.vueltas = 0;
    $scope.cargandoGraficos = true;
    $scope.cargandoTotales = true;
    $scope.recargarTabula = 0;
    $scope.verDetalle = {
        activo: false,
        titulo: "",
        evento: {},
        detalle: {
            audio: ""
        },
    };
    $scope.verDistribucion = {
        activo: false,
        evento: {},
    };
    $scope.promedioLlamadas = "";
    $scope.dataGraficos = [];
    $scope.dataGraficoTotalGeneral = [];
    $scope.dataGraficoLineaLlamada = [];
    $scope.dataTotalesGestion = [];
    $scope.dataGraficoDia = [];
    $scope.dataGraficoTop = [];
    $scope.dataGraficoProveedores = [];
    $scope.dataGraficoTelefonicas = [];
    $scope.dataGraficoAtendidaProveedores = [];
    $scope.dataGraficoAtendidaTelefonicas = [];
    $scope.cuadrosLlamadas = [];
    $scope.alertasJambonz = [];
    $scope.logGenera = [];
    $scope.logActualiza = [];
    $scope.logActualiza2 = [];
    $scope.logTipifica = [];
    $scope.logTipificaWs = [];
    $scope.logCalidad = [];
    $scope.marcadorElevenlabs = [];
    $scope.ultimasLlamadas = [];
    $scope.totalAlertasJambonz = 0;
    $scope.totalLLamadas = 0;
    $scope.totalError = 0;
    $scope.totalExito = 0;
    $scope.promedioPorMinuto = 0;
    $scope.totalReintentos = 0;
    $scope.verAlertasJambonz = false;
    $scope.verLogGenera = false;
    $scope.verLogActualiza = false;
    $scope.verLogActualiza2 = false;
    $scope.verLogTipifica = false;
    $scope.verLogTipificaWs = false;
    $scope.verLogCalidad = false;
    $scope.verDetalleErrores = false;
    $scope.agentesLinea = [];
    $scope.estadoServiciosLk = {};
    $scope.verLogLk = false;
    $scope.listas = {
        "lotes": [],
        "carteras": [],
        "campanias": [],
        "proveedores": [],
        "telefonicas": [],
        "productos": [],
        "tiemposActualiza": [
            { nombre: "Cada 10 segs.", id: 10000 },
            { nombre: "Cada 30 segs.", id: 30000 },
            { nombre: "Cada minuto", id: 60000 },
            { nombre: "Cada 5 mins.", id: 300000 },
            { nombre: "Cada 10 mins.", id: 600000 },
        ]
    };
    $scope.filtroTiempo = "dia";
    $scope.filtroCampania = [];
    $scope.filtroCartera = [];
    $scope.filtroProducto = [];
    $scope.filtroLote = [];
    $scope.filtroProveedor = [];
    $scope.filtroTelefonica = [];
    $scope.anchoBarra = { "width": "0%", "min-width": "2em" };
    $scope.permisos = {
        "soySupervisor": false,
        "soyObservador": true, //no utilizado
        "soyDesarrollo": false
    };
    $scope.titulo = "Este día";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.concurrencias = [];
    $scope.filtrosAplicados = {
        "todo": {
            "titulo": "Quita filtros",
            "activo": false,
        },
        "tiempo": {
            "titulo": "",
            "activo": false,
        },
        "lote": {
            "titulo": "",
            "activo": false,
        },
        "proveedor": {
            "titulo": "",
            "activo": false,
        },
        "telefonica": {
            "titulo": "",
            "activo": false,
        },
        "cartera": {
            "titulo": "",
            "activo": false,
        },
        "campania": {
            "titulo": "",
            "activo": false,
        },
        "producto": {
            "titulo": "",
            "activo": false,
        }
    };
    $scope.cuantosAplicados = 0;
    $scope.tiempoActualizar = 60000;
    $scope.duracionOperacion = "";
    $scope.mostrarDesarrollo = false;
    $scope.mostrarTabulaDetalle = false;
    $scope.mensajeProcesando = "";
    $scope.cargandoIndicadores = false;
    $scope.sinGestion = true;
    $scope.debouncer = null;
    $scope.distribucionLlamadas = [];

    //#region porcentaje
    let configGaugeProgreso = liquidFillGaugeDefaultSettings();
    configGaugeProgreso.circleColor = "#cbcbcb";
    configGaugeProgreso.textColor = "#686552";
    configGaugeProgreso.waveTextColor = "white";
    configGaugeProgreso.waveColor = "#686552";
    configGaugeProgreso.circleThickness = 0.2;
    configGaugeProgreso.textVertPosition = 0.2;
    configGaugeProgreso.waveAnimateTime = 3000;
    configGaugeProgreso.displayPercent = true;
    configGaugeProgreso.displayMoney = false;
    configGaugeProgreso.maxValue = 100;
    configGaugeProgreso.textSize = 0.75;
    $scope.gaugeProgreso = loadLiquidFillGauge("gaugeProgreso", 0, configGaugeProgreso);
    let configGaugeExito = liquidFillGaugeDefaultSettings();
    configGaugeExito.circleColor = "#cbcbcb";
    configGaugeExito.textColor = "#52be80";
    configGaugeExito.waveTextColor = "white";
    configGaugeExito.waveColor = "#52be80";
    configGaugeExito.circleThickness = 0.2;
    configGaugeExito.textVertPosition = 0.2;
    configGaugeExito.waveAnimateTime = 3000;
    configGaugeExito.displayPercent = true;
    configGaugeExito.displayMoney = false;
    configGaugeExito.maxValue = 100;
    configGaugeExito.textSize = 0.75;
    $scope.gaugeExito = loadLiquidFillGauge("gaugeExito", 0, configGaugeExito);
    //#endregion

    //#region opciones graficos
    $scope.opcionesPie = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            // legend: {
            //     margin: {
            //         top: 5,
            //         right: 120,
            //         bottom: 5,
            //         left: 0
            //     }
            // },
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    // var t = (d * 100) / $scope.dataGraficos.totalProgramadas;
                    // return t.toFixed(2) + "%";
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Programadas por estado"
        }
    };

    $scope.opcionesSentimiento = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            //legend: {
            //     margin: {
            //         top: 0,
            //         right: 0,
            //         bottom: 0,
            //         left: 0
            //     }
            //},
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    // var t = (d * 100) / $scope.dataGraficos.totalFinalizadas;
                    // return t.toFixed(2) + "%";
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación",
            // dispatch: {
            //     renderEnd: function (e) {
            //         //for each text
            //         d3.selectAll(".nv-legend text")[0].forEach(function (d) {

            //             var t = d3.select(d).data()[0];
            //             //hace un trim del nombre a 15 caracteres
            //             // var n = "";
            //             // if (t.key.length > 15) {
            //             //     n = t.key.substring(0, 12) + "...";
            //             // } else {
            //             //     n = t.key;
            //             // }
            //             // d3.select(d).html(n);
            //             /*
            //             //pone el total a un lado
            //             //set the new data in the innerhtml
            //             d3.select(d).html(t.key + " - " + t.y);
            //             */
            //             if (t.tooltip !== undefined && t.tooltip !== "") {
            //                 var h = '<span title="' + t.tooltip + '">' + t.key + '</span>';
            //                 console.log(d3.select(d));
            //                 d3.select(d).html(h);
            //             } else {
            //                 d3.select(d).html(t.key);
            //             }
            //         });
            //     }
            // }
        },
        title: {
            enable: true,
            text: "Análisis de sentimientos del cliente"
        }
    };

    $scope.opcionesFinalizacion = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            // legend: {
            //     margin: {
            //         top: 5,
            //         right: 120,
            //         bottom: 5,
            //         left: 0
            //     }
            // },
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    // var t = (d * 100) / $scope.dataGraficos.totalFinalizacion;
                    // return t.toFixed(2) + "%";
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Motivo de finalización"
        }
    };

    $scope.opcionesLinea = {
        chart: {
            showControls: false,
            type: 'cumulativeLineChart',
            noData: "Sin datos",
            height: 150,
            margin: {
                top: 20,
                right: 20,
                bottom: 40,
                left: 65
            },
            x: function (d) { return d.x; },
            y: function (d) { return d.y; },
            average: function (d) { return d.mean / 100; },
            color: [
                "#686552",
                "#495057",
                "#212529",
                "#ADB5BD",
            ],
            useInteractiveGuideline: true,
            dispatch: {
                stateChange: function (e) { /*console.log("stateChange");*/ },
                changeState: function (e) { /*console.log("changeState");*/ },
                tooltipShow: function (e) { /*console.log("tooltipShow");*/ },
                tooltipHide: function (e) { /*console.log("tooltipHide");*/ }
            },
            xAxis: {
                //axisLabel: 'Hoy día',
                // tickFormat: function (d) {
                //     return d3.time.format('%H:%M')(new Date(d * 1000));
                //     //return d;
                // },
                tickFormat: function (d) {
                    if ($scope.filtroTiempo === "dia" || $scope.filtroTiempo === "hora" || $scope.filtroTiempo === "ayer") {
                        return d3.time.format('%H:%M')(new Date(d * 1000));
                    } else if ($scope.filtroTiempo === "rango") {
                        let dif = $scope.filtroHasta - $scope.filtroDesde;
                        if (dif < 86400) {
                            return d3.time.format('%H:%M')(new Date(d * 1000));
                        } else {
                            return d3.time.format('%d/%m')(new Date(d * 1000));
                        }
                    } else {
                        return d3.time.format('%d/%m')(new Date(d * 1000));
                    }
                },
                //axisLabelDistance: -10,
                showMaxMin: true,
                staggerLabels: true,
                ticks: 12//$scope.dataGraficoLlamadas[0].values.length
            }
        },
        title: {
            enable: true,
            text: "Llamadas realizadas este día"
        }
    };

    $scope.opcionesLineaLlamadas = {
        chart: {
            showControls: false,
            type: 'cumulativeLineChart',
            noData: "Esperando asignación",
            height: 250,
            margin: {
                top: 20,
                right: 20,
                bottom: 40,
                left: 65
            },
            x: function (d) { return d.x; },
            y: function (d) { return d.y; },
            average: function (d) { return d.mean / 100; },
            color: [
                "#686552",
                "#495057",
                "#212529",
                "#ADB5BD",
            ],
            useInteractiveGuideline: true,
            dispatch: {
                stateChange: function (e) { /*console.log("stateChange");*/ },
                changeState: function (e) { /*console.log("changeState");*/ },
                tooltipShow: function (e) { /*console.log("tooltipShow");*/ },
                tooltipHide: function (e) { /*console.log("tooltipHide");*/ }
            },
            xAxis: {
                //axisLabel: 'Hoy día',
                tickFormat: function (d) {
                    if ($scope.filtroTiempo === "dia" || $scope.filtroTiempo === "hora" || $scope.filtroTiempo === "ayer") {
                        return d3.time.format('%H:%M')(new Date(d * 1000));
                    } else if ($scope.filtroTiempo === "rango") {
                        let dif = $scope.filtroHasta - $scope.filtroDesde;
                        if (dif < 86400) {
                            return d3.time.format('%H:%M')(new Date(d * 1000));
                        } else {
                            return d3.time.format('%d/%m')(new Date(d * 1000));
                        }
                    } else {
                        return d3.time.format('%d/%m')(new Date(d * 1000));
                    }
                },
                //axisLabelDistance: -10,
                showMaxMin: true,
                staggerLabels: true,
                ticks: 12//$scope.dataGraficoLlamadas[0].values.length
            },
            yAxis: {
                //axisLabel: 'Llamadas realizadas este día'
            }

        },
        title: {
            enable: true,
            text: "Llamadas realizadas"
        }
    };

    $scope.opcionesTotalGeneral = {
        chart: {
            type: 'discreteBarChart',
            height: 200,
            margin: {
                left: 5
            },
            x: function (d) { return d.label; },
            y: function (d) { return d.value; },
            showValues: true,
            valueFormat: function (d) {
                //return d3.format(',.4f')(d);
                return d;
            },
            duration: 500,
            xAxis: {
                //axisLabel: 'Estado'
            },
            yAxis: {
                //axisLabel: 'Total',
                axisLabelDistance: -10
            },
            showYAxis: false,
            tooltip: {
                valueFormatter: function (d) {
                    let v = d3.format(' .0f')(d);
                    let f = v + " de " + $scope.totalLLamadas + " llamadas";
                    return f;
                }
            },
        },
        title: {
            enable: true,
            text: "Llamadas realizadas"
        }
    };

    $scope.opcionesTipificacion = {
        chart: {
            type: 'discreteBarChart',
            height: 250,
            margin: {
                top: 5,
                right: 40,
                bottom: 70,
                left: 55
            },
            x: function (d) { return d.label; },
            y: function (d) { return d.value; },
            showValues: true,
            valueFormat: function (d) {
                return d;
            },
            duration: 500,
            xAxis: {
                //axisLabel: 'X Axis'
                rotateLabels: 15,
                fontSize: 10
            },
            yAxis: {
                //axisLabel: 'Y Axis',
                axisLabelDistance: -10,
                tickFormat: function (d) { return d3.format(',f')(d); },
            },
            noData: "Esperando asignación",
            tooltip: {
                contentGenerator: function (d) {
                    //let h = '<table><tbody><tr><td class="legend-color-guide"><div style="background-color: ' + d.color + ';"></div></td><td class="key">' + d.data.padre + ' - ' + d.data.label + '<br><span class="value"><b>' + d.data.value + ' deudas</b> (' + d.data.monto + ')</span></td></tr></tbody></table>';
                    let h = '<table><tbody><tr><td class="legend-color-guide"><div style="background-color: ' + d.color + ';"></div></td><td class="key">' + d.data.padre + ' - ' + d.data.label + '<br><span class="value"><b>' + d.data.value + ' usuarios</b></span></td></tr></tbody></table>';
                    return h;
                }
            },
        }
    };

    $scope.opcionesBarras = {
        chart: {
            type: 'multiBarHorizontalChart',
            height: 300,
            margin: {
                top: 20,
                right: 20,
                bottom: 65,
                left: 105
            },
            x: function (d) { return d.label; },
            y: function (d) { return d.value; },
            showValues: true,
            valueFormat: function (d) {
                return d;
            },
            duration: 500,
            xAxis: {
                //axisLabel: 'Fecha/hora',
                rotateLabels: 15,
                fontSize: 10
            },
            yAxis: {
                axisLabel: '# de llamadas',
                axisLabelDistance: -10,
                tickFormat: function (d) {
                    return d;
                },
            },
            noData: "Sin datos",
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    return d + " llamadas";
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            title: {
                enable: true,
                text: "Top de llamadas"
            }
        }
    };

    $scope.opcionesProveedores = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Llamadas por proveedor"
        }
    };

    $scope.opcionesTelefonicas = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Llamadas por proveedor telefonía"
        }
    };

    $scope.opcionesAtendidoProveedores = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Atendidas por proveedor"
        }
    };

    $scope.opcionesAtendidoTelefonicas = {
        chart: {
            type: 'pieChart',
            height: 300,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            showLegend: true,
            showLabels: true,
            labelSunbeamLayout: false,
            tooltip: {
                enabled: true,
                valueFormatter: function (d, i) {
                    return d;
                },
                headerFormatter: function (d) {
                    return d;
                },
                keyFormatter: function (d) {
                    return d;
                }
            },
            noData: "Esperando asignación"
        },
        title: {
            enable: true,
            text: "Atendidas por proveedor telefonía"
        }
    };

    //#endregion

    $scope.mostrar = function () {
        if ($scope.mostrarTabulaDetalle) {
            $scope.mostrarTabulaDetalle = false;
        } else {
            $scope.mostrarTabulaDetalle = true;
            window.setTimeout(() => {
                document.getElementById('tabulaDetalle').scrollIntoView({ behavior: 'smooth' });
            }, 300);
        }
    };

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = function () {
        $scope.cargando = true;
        $scope.traerDetalleGrafico();
        $scope.traerDetalleGraficoDia();
        $scope.traerDetalleGraficoLlamadas();
        $scope.traerTotalesGestion();
        if ($scope.permisos.soyDesarrollo) {
            $scope.obtenerAlertasJambonz();
        }
        $scope.recargarTabula++;
    };

    $scope.traerParametrizacion = function (cargarDatos) {
        //#region: filtros
        if (cargarDatos) {
            let dd = new Date();
            let fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
            switch ($scope.filtroTiempo) {
                case "hora":
                    fd = ("0" + dd.getHours()).slice(-2) + ":00/" + ("0" + dd.getHours()).slice(-2) + ":59";
                    $scope.titulo = "Última hora - " + fd;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "dia":
                    $scope.titulo = "Este día - " + fd;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "ayer":
                    dd.setDate(dd.getDate() - 1);
                    fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                    $scope.titulo = "Ayer - " + fd;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "semana":
                    let lunes = $scope.getMonday(dd);
                    fd = ("0" + lunes.getDate()).slice(-2) + "/" + ("0" + (lunes.getMonth() + 1)).slice(-2) + "/" + lunes.getFullYear();
                    let fh = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                    $scope.titulo = fd === fh ? "Esta semana - " + fd : "Esta semana - del " + fd + " al " + fh;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "semanaanterior":
                    let lunesanterior = $scope.getPreviousMonday(dd);
                    fd = ("0" + lunesanterior.getDate()).slice(-2) + "/" + ("0" + (lunesanterior.getMonth() + 1)).slice(-2) + "/" + lunesanterior.getFullYear();
                    let domingoanterior = $scope.getPreviousSunday(dd);
                    let fha = ("0" + domingoanterior.getDate()).slice(-2) + "/" + ("0" + (domingoanterior.getMonth() + 1)).slice(-2) + "/" + domingoanterior.getFullYear();
                    $scope.titulo = fd === fha ? "Semana anterior - " + fd : "Semana anterior - del " + fd + " al " + fha;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "mes":
                    fd = ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                    $scope.titulo = "Este mes - " + fd;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "mesanterior":
                    fd = ("0" + (dd.getMonth())).slice(-2) + "/" + dd.getFullYear();
                    $scope.titulo = "Mes anterior - " + fd;
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
                case "rango":
                    if (typeof $scope.fechaDesde === "object" && typeof $scope.fechaHasta === "object") {
                        $scope.filtroDesde = Math.floor($scope.fechaDesde.getTime() / 1000);
                        $scope.filtroHasta = Math.floor($scope.fechaHasta.getTime() / 1000);
                        if ($scope.filtroHasta < $scope.filtroDesde) {
                            avisos.alerta("Error", "Rango de fechas incorrecto");
                            return;
                        }
                        if (($scope.filtroHasta - $scope.filtroDesde) > 7889238000) {
                            avisos.alerta("Error", "No se puede mostrar un periodo mayor a 3 meses");
                            return;
                        }
                        let dd = new Date($scope.fechaDesde.getTime());
                        let dh = new Date($scope.fechaHasta.getTime());
                        let fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                        let fh = ("0" + dh.getDate()).slice(-2) + "/" + ("0" + (dh.getMonth() + 1)).slice(-2) + "/" + dh.getFullYear();
                        $scope.titulo = "Del " + fd + " al " + fh;
                        $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
                        $scope.filtrosAplicados.tiempo.activo = true;
                        $scope.cuantosAplicados++;
                    } else {
                        avisos.alerta("Error", "Seleccione un rango de fechas");
                        return;
                    }
                    break;
                case "todo":
                    $scope.titulo = "Todo el tiempo";
                    $scope.fechaDesde = "";
                    $scope.fechaHasta = "";
                    $scope.filtroDesde = "";
                    $scope.filtroHasta = "";
                    break;
            }


            if ($scope.filtroLote.length > 0) {
                let mensajes = [];
                $scope.filtroLote.forEach(element => {
                    mensajes.push($filter('fecha')(element, 'd/m/Y H:i'));
                });
                $scope.filtrosAplicados.lote.titulo = "Fecha programación: " + (mensajes.join(", "));
                $scope.filtrosAplicados.lote.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.lote.activo = false;
            }

            if ($scope.filtroCampania.length > 0) {
                let mensajes = [];
                $scope.filtroCampania.forEach(element => {
                    mensajes.push($scope.listas.campanias.find(ele => ele.id == element).nombre);
                });
                $scope.filtrosAplicados.campania.titulo = "Campaña: " + (mensajes.join(", "));
                $scope.filtrosAplicados.campania.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.campania.activo = false;
            }

            if ($scope.filtroCartera.length > 0) {
                let mensajes = [];
                $scope.filtroCartera.forEach(element => {
                    mensajes.push($scope.listas.carteras.find(ele => ele.id == element).nombre);
                });
                $scope.filtrosAplicados.cartera.titulo = "Cartera: " + (mensajes.join(", "));
                $scope.filtrosAplicados.cartera.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.cartera.activo = false;
            }

            if ($scope.filtroProducto.length > 0) {
                let mensajes = [];
                $scope.filtroProducto.forEach(element => {
                    mensajes.push($scope.listas.productos.find(ele => ele.id == element).nombre);
                });
                $scope.filtrosAplicados.producto.titulo = "Producto: " + (mensajes.join(", "));
                $scope.filtrosAplicados.producto.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.producto.activo = false;
            }

            if ($scope.filtroProveedor.length > 0) {
                let mensajes = [];
                $scope.filtroProveedor.forEach(element => {
                    mensajes.push($scope.listas.proveedores.find(ele => ele.id == element).nombre);
                });
                $scope.filtrosAplicados.proveedor.titulo = "Proveedor: " + (mensajes.join(", "));
                $scope.filtrosAplicados.proveedor.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.proveedor.activo = false;
            }
            if ($scope.filtroTelefonica.length > 0) {
                let mensajes = [];
                $scope.filtroTelefonica.forEach(element => {
                    mensajes.push($scope.listas.telefonicas.find(ele => ele.id == element).nombre);
                });
                $scope.filtrosAplicados.telefonica.titulo = "Telefonía: " + (mensajes.join(", "));
                $scope.filtrosAplicados.telefonica.activo = true;
                $scope.cuantosAplicados++;
            } else {
                $scope.filtrosAplicados.telefonica.activo = false;
            }
        }
        //#endregion

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroProducto: $scope.filtroProducto,
                filtroTelefonica: $scope.filtroTelefonica
            }
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.lotes !== undefined && $scope.filtroLote.length === 0) {
                    $scope.listas.lotes = response.lotes;
                }
                if (response?.campanias !== undefined && $scope.filtroCampania.length === 0) {
                    $scope.listas.campanias = response.campanias;
                }
                if (response?.carteras !== undefined && $scope.filtroCartera.length === 0) {
                    $scope.listas.carteras = response.carteras;
                }
                if (response?.proveedores !== undefined && $scope.filtroProveedor.length === 0) {
                    $scope.listas.proveedores = response.proveedores;
                }
                if (response?.telefonicas !== undefined && $scope.filtroTelefonica.length === 0) {
                    $scope.listas.telefonicas = response.telefonicas;
                }
                if (response?.productos !== undefined && $scope.filtroProducto.length === 0) {
                    $scope.listas.productos = response.productos;
                }
                if (response?.permisos !== undefined) {
                    $scope.permisos = response.permisos;
                }
                if (cargarDatos && ($scope.filtroTiempo !== "" || $scope.filtroLote !== "" || $scope.filtroCampania !== "" || $scope.filtroCartera !== "" || $scope.filtroProducto !== "" || $scope.filtroProveedor !== "" || $scope.filtroTelefonica !== "")) {
                    $scope.actualizarTabula();
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.cargando = false;
            $scope.actualizarAutomaticamente();
        });

    };

    $scope.getMonday = function (d) {
        d = new Date(d);
        var day = d.getDay(),
            diff = d.getDate() - day + (day == 0 ? -6 : 1); // adjust when day is sunday
        return new Date(d.setDate(diff));
    };

    $scope.getPreviousMonday = function (d) {
        d = new Date(d);
        var day = d.getDay(),
            diff = d.getDate() - day + (day == 0 ? -6 : 1); // adjust when day is sunday
        return new Date(d.setDate(diff - 7));
    };

    $scope.getPreviousSunday = function (d) {
        var t = new Date(d);
        t.setDate((t.getDate() - t.getDay()));
        return t;
    };

    $scope.mostrarDetalle = function (pos, item) {

        //if (!$scope.permisos.soyDesarrollo) return;

        $scope.cargando = true;
        $scope.verDetalle.audioCargado = "";
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=obtenerDetalleConversacion',
            data: {
                conv: item.av_idConversacion
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                //avisos.alerta("Atención", ["Audio encontrado", response.respuesta]);
                //window.open(response.respuesta, '_blank');
                $scope.verDetalle.titulo = "Detalle de llamada a " + item.av_nombre + " - " + item.av_cedula;
                $scope.verDetalle.detalle = response.respuesta;

                $scope.verDetalle.detalle["resumenOriginal"] = $scope.verDetalle.detalle.resumen.toString();
                //traduce el resumen
                if ($scope.verDetalle.detalle.resumen !== "") {
                    if (typeof Translator === "function") {
                        Translator.create({
                            sourceLanguage: "en",
                            targetLanguage: "es",
                        }).then((translator) => {
                            const totalInputQuota = translator.inputQuota;
                            console.log(totalInputQuota, "totalInputQuota");
                            translator.measureInputUsage($scope.verDetalle.detalle.resumen).then((inputUsage) => {
                                console.log(inputUsage, "inputUsage");
                                if (inputUsage < totalInputQuota) {
                                    translator.translate($scope.verDetalle.detalle.resumen).then((translation) => {
                                        $scope.verDetalle.detalle.resumen = translation;
                                        $scope.verDetalle.detalle.tengoTraduccion = true;
                                        translator.destroy();
                                    });
                                } else {
                                    console.log("translation quota exceeded");
                                }
                            });
                        });
                    }
                }

                if ($scope.verDetalle.detalle.transcripcion !== "") {
                    $scope.verDetalle.detalle.transcripcion = JSON.parse(window.atob($scope.verDetalle.detalle.transcripcion));
                }
                $scope.verDetalle.detalle.mostrarDinamicos = false;
                $scope.verDetalle.detalle.mostrarTranscripcion = true;
                $scope.verDetalle.evento = pos;
                $scope.verDetalle.activo = true;
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
            }
            $scope.cargando = false;
        });
    };

    $scope.traerDetalleGrafico = function () {
        $scope.cargandoGraficos = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=detalleGraficos',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroProducto: $scope.filtroProducto,
                filtroTelefonica: $scope.filtroTelefonica
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.dataGraficos = response.respuesta;
                $scope.dataGraficoProveedores = response.proveedores;
                $scope.dataGraficoTelefonicas = response.telefonicas;
                $scope.dataGraficoAtendidaProveedores = response.atendidoProveedores;
                $scope.dataGraficoAtendidaTelefonicas = response.atendidoTelefonicas;
                if ($scope.dataGraficos.totalNuevas > 0 && $scope.dataGraficos.totalReintento > 0) {
                    $scope.mensajeProcesando = "Procesando: llamadas nuevas y reintentos";
                }
                else if ($scope.dataGraficos.totalNuevas > 0 && $scope.dataGraficos.totalReintento <= 0) {
                    $scope.mensajeProcesando = "Procesando: llamadas nuevas";
                }
                else if ($scope.dataGraficos.totalNuevas <= 0 && $scope.dataGraficos.totalReintento > 0) {
                    $scope.mensajeProcesando = "Procesando: llamadas de reintento";
                } else {
                    $scope.mensajeProcesando = "";
                }

                $scope.anchoBarra = { "width": $scope.dataGraficos.porcentajeAvance + "%", "min-width": "2em" };
                $scope.gaugeProgreso.update($scope.dataGraficos.porcentajeAvance);
                $scope.gaugeExito.update($scope.dataGraficos.porcentajeExito);

                //$scope.opcionesFinalizacion.chart.showLegend = $scope.dataGraficos.dataGraficoFinalizacion.length > 10 ? false : true;
                $scope.opcionesFinalizacion.chart.showLabels = $scope.dataGraficos.dataGraficoFinalizacion.length >= 10 ? false : true;
                $scope.opcionesPie.chart.showLegend = $scope.dataGraficos.dataGraficoProgramadasEstado.length >= 10 ? false : true;

                if (response.respuesta.totalProgramadas > 0) {
                    $scope.sinGestion = false;
                } else {
                    $scope.sinGestion = true;
                }


            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
            }
            $scope.cargandoGraficos = false;
        });
    };

    $scope.traerDetalleGraficoDia = function () {
        $scope.cargandoGraficos = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=graficoDia',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroProducto: $scope.filtroProducto,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTelefonica: $scope.filtroTelefonica
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.dataGraficoDia = response.respuesta;
                $scope.concurrencias = response.concurrencias;
                $scope.dataGraficoTop = response.top;
                $scope.promedioLlamadas = response.promedio;
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
            }
            $scope.cargandoGraficos = false;
        });
    };

    $scope.traerDetalleGraficoLlamadas = function () {
        $scope.cargandoGraficos = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=detalleGraficosLlamadas',
            data: {
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTiempo: $scope.filtroTiempo,
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroProducto: $scope.filtroProducto,
                filtroTelefonica: $scope.filtroTelefonica
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                if (response?.respuesta?.graficoGeneral !== undefined) {
                    $scope.dataGraficoTotalGeneral = response.respuesta.graficoGeneral;
                }
                if (response?.respuesta?.dataGraficoLineaLlamada !== undefined) {
                    $scope.dataGraficoLineaLlamada = response.respuesta.dataGraficoLineaLlamada;
                }
                if (response?.respuesta?.total !== undefined) {
                    $scope.totalLLamadas = response.respuesta.total;

                }
                if (response?.respuesta?.totalErrorGeneral !== undefined) {
                    $scope.totalError = response.respuesta.totalErrorGeneral;
                }
                if (response?.respuesta?.totalExito !== undefined) {
                    $scope.totalExito = response.respuesta.totalExito;
                }
                if (response?.respuesta?.promedioPorMinuto !== undefined) {
                    $scope.promedioPorMinuto = response.respuesta.promedioPorMinuto;
                }
                if (response?.respuesta?.totalReintentos !== undefined) {
                    $scope.totalReintentos = response.respuesta.totalReintentos;
                }
                if (response?.respuesta?.cuadros !== undefined) {
                    $scope.cuadrosLlamadas = response.respuesta.cuadros;
                }
                if (response?.respuesta?.inicioGestion !== undefined && response?.respuesta?.inicioGestion !== "") {
                    $scope.duracionOperacion = "Primera llamada: " + response.respuesta.inicioGestion + " - Última llamada: " + response.respuesta.finGestion;
                } else {
                    $scope.duracionOperacion = "";
                }
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
            }
            $scope.cargandoGraficos = false;
        });
    };

    $scope.traerTotalesGestion = function () {
        $scope.cargandoTotales = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=totalesGestion',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroProducto: $scope.filtroProducto,
                filtroTelefonica: $scope.filtroTelefonica
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.dataTotalesGestion = response.respuesta;
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
            }
            $scope.cargandoTotales = false;
        });
    };

    $scope.obtenerAlertasJambonz = function () {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=ultimasAlertasJambonz',
            data: {}
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                //$scope.alertasJambonz = response.respuesta;
                //$scope.totalAlertasJambonz = response.total;
                console.log("22");
                $scope.logGenera = response.logGenera;
                $scope.logActualiza = response.logActualiza;
                $scope.logActualiza2 = response.logActualiza2;
                $scope.logTipifica = response.logTipifica;
                $scope.logTipificaWs = response.logTipificaWs;
                $scope.logCalidad = response.logCalidad;
                $scope.agentesLinea = response.agentes;
                $scope.estadoServiciosLk = response.estado;
                $scope.ultimasLlamadas = response.ultimasLlamadas;
                $scope.marcadorElevenlabs = response.marcadorElevenlabs;
            }
        });
    };

    $scope.aplicarRangoFechas = function () {
        if (typeof $scope.fechaDesde === "object" && typeof $scope.fechaHasta === "object") {
            $scope.filtroDesde = Math.floor($scope.fechaDesde.getTime() / 1000);
            $scope.filtroHasta = Math.floor($scope.fechaHasta.getTime() / 1000);
            if ($scope.filtroHasta < $scope.filtroDesde) {
                avisos.alerta("Error", "Rango de fechas incorrecto");
                return;
            }
            if (($scope.filtroHasta - $scope.filtroDesde) > 7889238000) {
                avisos.alerta("Error", "No se puede mostrar un periodo mayor a 3 meses");
                return;
            }
            let dd = new Date($scope.fechaDesde.getTime());
            let dh = new Date($scope.fechaHasta.getTime());
            let fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
            let fh = ("0" + dh.getDate()).slice(-2) + "/" + ("0" + (dh.getMonth() + 1)).slice(-2) + "/" + dh.getFullYear();
            $scope.titulo = "Del " + fd + " al " + fh;
            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
            $scope.filtrosAplicados.tiempo.activo = true;
            $scope.cuantosAplicados++;
            $scope.traerParametrizacion(true);
        }
    };

    let toa = null;
    $scope.actualizarAutomaticamente = function () {
        let u = window.location.href;
        if (u.includes("reporteAgenteVirtual")) {
            window.clearTimeout(toa);
            if (!$scope.verDetalle.activo && !$scope.cargando && !$scope.sinGestion) {
                //en x segundos actualice
                //y cada x veces actualice toda la pagina
                toa = window.setTimeout(() => {
                    if ($scope.filtroTiempo === "dia" || $scope.filtroTiempo === "hora") {
                        if ($scope.vueltas === 50) {
                            window.location.reload();
                        }
                        console.log("actualizo: " + new Date());
                        $scope.traerParametrizacion(true);
                        $scope.vueltas++;
                    } else {
                        console.log("no actualizo: " + new Date());
                        $scope.actualizarAutomaticamente();
                    }
                }, $scope.tiempoActualizar);
            } else {
                //espere 10 segundo e intente nuevamente
                toa = window.setTimeout(() => {
                    console.log("actualizo 1: " + new Date());
                    $scope.actualizarAutomaticamente();
                }, 10000);
            }
        }
    };

    $scope.generarLlamada = function (item) {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=generarLlamada',
                data: {
                    id: item.id
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    $scope.actualizarTabula();
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        });
        avisos.confirma("Confirme", ["Se iniciará la llamada, desea continuar?"]);
    };

    // $scope.$watch('filtroTiempo', function () {
    //     if ($scope.filtroTiempo !== "dia" && $scope.filtroTiempo !== "rango") {
    //         $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
    //         $scope.filtrosAplicados.tiempo.activo = true;
    //         $scope.cuantosAplicados++;
    //     } else {
    //         $scope.filtrosAplicados.tiempo.activo = false;
    //     }
    // });

    $scope.quitarFiltro = function (filtro) {
        switch (filtro) {
            case "tiempo":
                $scope.filtroTiempo = "dia";
                $scope.cuantosAplicados--;
                break;
            case "lote":
                $scope.filtroLote = [];
                $scope.cuantosAplicados--;
                break;
            case "proveedor":
                $scope.filtroProveedor = [];
                $scope.cuantosAplicados--;
                break;
            case "telefonica":
                $scope.filtroTelefonica = [];
                $scope.cuantosAplicados--;
                break;
            case "cartera":
                $scope.filtroCartera = [];
                $scope.cuantosAplicados--;
                break;
            case "campania":
                $scope.filtroCampania = [];
                $scope.cuantosAplicados--;
                break;
            case "producto":
                $scope.filtroProducto = [];
                $scope.cuantosAplicados--;
                break;
            case "todo":
                $scope.filtroTiempo = "dia";
                $scope.filtroLote = [];
                $scope.filtroProveedor = [];
                $scope.filtroTelefonica = [];
                $scope.filtroCartera = [];
                $scope.filtroCampania = [];
                $scope.filtroProducto = [];
                $scope.cuantosAplicados = 0;
                break;
        }
        $scope.traerParametrizacion(true);

    };

    $scope.leerTiempoActualizar = function () {
        let a = window.localStorage.getItem("tiempoActualiza");
        if (a !== null && a !== undefined) {
            $scope.tiempoActualizar = parseInt(a);
            console.log($scope.tiempoActualizar);
        } else {
            window.localStorage.setItem("tiempoActualiza", "60000");
            $scope.tiempoActualizar = 60000;
        }
    };

    $scope.setTiempoActualizar = function () {
        window.localStorage.setItem("tiempoActualiza", $scope.tiempoActualizar.toString());
        window.clearTimeout(toa);
        $scope.actualizarAutomaticamente();
    };

    // $scope.probrarFiltro = function () {
    //     console.log($scope.tabulas.llamadasProgramadas);
    //     let a = $scope.tabulas.llamadasProgramadas.filtro.find((element) => element.campo === "av_estadoEnvio").filtro = "FINALIZADA";
    //     $scope.actualizarTabula();

    // };

    $scope.cambioFiltro = function () {
        clearTimeout($scope.debouncer);
        $scope.debouncer = $timeout(() => {
            $scope.traerParametrizacion(false);
        }, 1000);
    };

    $scope.devolverTotalesLlamadasDia = () => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualCtrl.php?act=devolverTotalesLlamadasDia',
            data: {
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.distribucionLlamadas = response.respuesta;
                $scope.verDistribucion.activo = true;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.traerParametrizacion(true);
    $scope.leerTiempoActualizar();

}]);

//_FIN_DE_ARCHIVO

