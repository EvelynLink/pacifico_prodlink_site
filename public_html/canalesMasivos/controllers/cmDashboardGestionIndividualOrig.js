app.controller("cmDashboardGestionIndividual", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.recargarTabula = 0;
    $scope.tablaDias = [];
    $scope.listas = {
        "canales": [
            { "nombre": "LLamada telefónica (agente virtual)", "id": "llamadas_av", "checked": true },
            { "nombre": "Correo electrónico", "id": "correo", "checked": true },
            { "nombre": "WhatsApp", "id": "whatsapp", "checked": true }
        ],
        "carteras": [],
        "campanias": [],
        "tiemposActualiza": [
            { nombre: "Cada 10 segs.", id: 10000 },
            { nombre: "Cada 30 segs.", id: 30000 },
            { nombre: "Cada minuto", id: 60000 },
            { nombre: "Cada 5 mins.", id: 300000 },
            { nombre: "Cada 10 mins.", id: 600000 },
        ]
    };
    $scope.periodo = [];
    $scope.periodo = [{ id: -1, nombre: 'Todos los ciclos' }];
    $scope.permisos = {
        "soySupervisor": false,
        "soyObservador": true,
        "soyDesarrollo": false
    };
    $scope.secciones = {
        "llamadasAV": {
            "totales": [],
            "porcentajeAvance": 0,
            "campanias": []
        },
        "whatsapp": {
            "totales": [],
            "porcentajeAvance": 0,
            "campanias": []
        },
        "correo": {
            "totales": [],
            "porcentajeAvance": 0,
            "campanias": []
        }
    };
    $scope.totales = {
        "gestion": [],
        "tipificacion": [],
        "cumplimiento": []
    };
    $scope.verDias = false;
    $scope.efectividad = {};
    $scope.totalesCumplimiento = [];
    $scope.porcentajeTotal = 0;
    $scope.porcentajes = {
        "porcentajeAvanceLlamadasAv": "0,0"
    };
    $scope.graficos = {
        "pie": [],
        "barrasTotales": [],
        "lineaLlamadas": [],
        "lineaCorreos": [],
        "lineaWhatsapp": [],
        "compromisos": []
    };
    $scope.promedios = {
        "llamadas": "N/D",
        "correos": "N/D",
        "whatsapp": "N/D"
    }
    $scope.titulo = "";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "dia";
    $scope.filtroCampania = "todo";
    $scope.filtroCartera = "todo";
    $scope.filtroCanal = 'todo';
    $scope.listas.filtroPeriodo = -1;
    $scope.filtrosAplicados = {
        "todo": {
            "titulo": "Quita filtros",
            "activo": false,
        },
        "tiempo": {
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
        }
    };
    $scope.cuantosAplicados = 0;
    $scope.tiempoActualizar = 60000;
    $scope.cargandoGraficos = false;
    $scope.debouncer = null;
    $scope.modalReportes = {
        evento: {},
        activo: false
    };
    $scope.espere = {
        "general": false,
        "llamadas": false,
        "correos": false,
        "whatsapp": false
    };
    $scope.resumenCompromisos = [];

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
    $scope.gaugeProgresoCorreo = loadLiquidFillGauge("gaugeProgresoCorreo", 0, configGaugeProgreso);
    $scope.gaugeProgresoWhatsapp = loadLiquidFillGauge("gaugeProgresoWhatsapp", 0, configGaugeProgreso);
    //$scope.gaugeProgresoTotal = loadLiquidFillGauge("gaugeProgresoTotal", 0, configGaugeProgreso);

    $scope.opcionesBarraTotales = {
        chart: {
            type: 'discreteBarChart',
            height: 200,
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
                axisLabel: 'Contactos directos',
                axisLabelDistance: -4,
                tickFormat: function (d) { return d3.format(',f')(d); },
            },
            noData: "Esperando datos",
            tooltip: {
                contentGenerator: function (d) {
                    //let h = '<table><tbody><tr><td class="legend-color-guide"><div style="background-color: ' + d.color + ';"></div></td><td class="key">' + d.data.padre + ' - ' + d.data.label + '<br><span class="value"><b>' + d.data.value + ' deudas</b> (' + d.data.monto + ')</span></td></tr></tbody></table>';
                    let h = '<table><tbody><tr><td class="legend-color-guide"><div style="background-color: ' + d.color + ';"></div></td><td class="key">' + d.data.label + '<br><span class="value"><b>' + d.data.value + ' contactos directos (' + d.data.porcentaje + '% )</b></span></td></tr></tbody></table>';
                    return h;
                }
            },
        }
    };

    $scope.opcionesLineaLlamadas = {
        chart: {
            showControls: false,
            type: 'cumulativeLineChart',
            noData: "Esperando datos",
            height: 150,
            margin: {
                top: 20,
                right: 20,
                bottom: 40,
                left: 65
            },
            x: function (d) { return d.x; },
            y: function (d) { return d.y; },
            //average: function (d) { return d.mean / 100; },
            color: [
                "#686552",
                "#495057",
                "#212529",
                "#ADB5BD",
            ],
            useInteractiveGuideline: true,
            xAxis: {
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
            enable: false,
            text: "Gestiones realizadas este día"
        }
    };

    $scope.opcionesCompromisos = {
        chart: {
            type: 'multiBarChart',
            height: 200,
            margin: {
                top: 5,
                right: 40,
                bottom: 20,
                left: 55
            },
            noData: "Esperando datos",
            clipEdge: true,
            duration: 500,
            stacked: false,
            xAxis: {
                axisLabel: '',
                showMaxMin: true,
                tickFormat: function (d) {
                    //return d3.format(',f')(d);
                    return d3.time.format('%d/%m')(new Date(d));
                },
                staggerLabels: false,
                ticks: 12,
            },
            yAxis: {
                axisLabel: '',
                axisLabelDistance: -20,
                tickFormat: function (d) {
                    //return d3.format(',.1f')(d);
                    return d;
                }
            },
            tooltip: {
                contentGenerator: function (d) {
                    let r = $scope.resumenCompromisos["_" + d.value];
                    let h = '<table><tbody>' +
                        '<tr><td colspan="2" class="key"><b>' + d3.time.format('%d/%m/%Y')(new Date(d.value)) + '</b></td></tr>' +
                        '<tr><td class="legend-color-guide">' +
                        '<div style="background-color: ' + r["Compromisos proyectados"][1] + ';"></div></td>' +
                        '<td class="key">Compromisos proyectados: <span class="value"><b>' + r["Compromisos proyectados"][0] + '</b></span></td></tr>' +
                        '<tr><td class="legend-color-guide">' +
                        '<div style="background-color: ' + r["Cumplimiento compromisos del día"][1] + ';"></div></td>' +
                        '<td class="key">Cumplimiento compromisos del d&iacute;a: <span class="value"><b>' + r["Cumplimiento compromisos del día"][0] + '</b></span></td></tr>' +
                        '<tr><td class="legend-color-guide">' +
                        '<div style="background-color: ' + r["Seguimiento promesa caída"][1] + ';"></div></td>' +
                        '<td class="key">Seguimiento promesa ca&iacute;da: <span class="value"><b>' + r["Seguimiento promesa caída"][0] + '</b></span></td></tr>' +
                        '<tr><td></td><td>' +
                        '<small>' + r["Seguimiento promesa caída"][2] + '</small></td></tr>' +
                        '</tbody></table>';
                    return h;
                }
            }
        }
    };

    $scope.opcionesPie = {
        chart: {
            type: 'pieChart',
            height: 200,
            x: function (d) { return d.key; },
            y: function (d) { return d.y; },
            showLabels: true,
            duration: 5,
            labelThreshold: 0.01,
            labelSunbeamLayout: false,
            legend: {
                margin: {
                    top: 5,
                    right: 0,
                    bottom: 5,
                    left: 0
                }
            }
        }
    };

    $scope.viewdias = function () {
        $scope.cargando = true;
        $scope.tablaDias = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=cargarTablaDias',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroPeriodo: $scope.listas.filtroPeriodo
            }
        }).then(function (response) {
            $scope.tablaDias = response;
            $scope.verDias = true;
            $scope.cargando = false;
        });

    }

    $scope.cerrarDias = function () {
        $scope.verDias = false;
    }

    $scope.traerParametrizacion = function () {
        $scope.cargando = true;
        let dd = new Date();
        let fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
        if ($scope.filtroCartera === 'todo') {
            $scope.listas.filtroPeriodo = -1;
            $scope.periodo = [{ id: -1, nombre: 'Todos los ciclos' }];
        }
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
                fd = ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                $scope.titulo = "Mes anterior - " + fd;
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                break;
            case "asigActual":
                fd = ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                $scope.titulo = "Asignación contable [mes actual] - " + fd;
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                break;
            case "asigAnterior":
                dd.setMonth(dd.getMonth() - 1);
                fd = ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                $scope.titulo = "Asignación contable [mes anterior] - " + fd;
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                break;
            case "rango":
                // $scope.titulo = "Rango";
                // $scope.fechaDesde = "";
                // $scope.fechaHasta = "";
                // $scope.filtroDesde = "";
                // $scope.filtroHasta = "";
                break;
            case "todo":
                $scope.titulo = "Todo el tiempo";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                break;
        }

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroPeriodo: $scope.listas.filtroPeriodo
            }
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.carteras !== undefined) {
                    if ($scope.filtroCartera === 'todo') {
                        $scope.listas.carteras = response.carteras;
                    }
                }
                if (response?.campanias !== undefined) {
                    $scope.listas.campanias = response.campanias;
                }
                if (response?.permisos !== undefined) {
                    $scope.permisos = response.permisos;
                }
                if (response?.periodos !== undefined && response.periodos.length > 0) {
                    if ($scope.filtroCartera !== 'todo') {
                        $scope.periodo = response.periodos;
                    }
                }

                $scope.traerTotales();
                $scope.traerGraficoLlamadas();
                $scope.traerGraficoCorreos();
                $scope.traerGraficoWp();
                //$scope.traerGraficoCompromisos();
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
            }
            // $scope.cargando = false;
            $scope.actualizarAutomaticamente();
        });

    };

    $scope.traerTotales = function () {
        $scope.cargandoGraficos = true;
        // $scope.cargando = true;
        $scope.graficos.barrasTotales = {};
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=obtenerTotales',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroCanales: $scope.listas.canales,
                filtroPeriodo: $scope.listas.filtroPeriodo,
                filtroCanal: $scope.filtroCanal
            }
        }).then(function (response) {
            if (response.llamadasAV !== undefined) {
                $scope.secciones.llamadasAV = response.llamadasAV;
                $scope.gaugeProgreso.update($scope.secciones.llamadasAV.porcentajeAvance);
            }
            if (response.correo !== undefined) {
                $scope.secciones.correo = response.correo;
                $scope.gaugeProgresoCorreo.update($scope.secciones.correo.porcentajeAvance);
                console.log("correo");
                console.log($scope.secciones.correo);
            }
            if (response.whatsapp !== undefined) {
                $scope.secciones.whatsapp = response.whatsapp;
                $scope.gaugeProgresoWhatsapp.update($scope.secciones.whatsapp.porcentajeAvance);
            }
            if (response.totales !== undefined) {
                $scope.totales = response.totales;
                $scope.efectividad = response.efectividad;
                $scope.porcentajeTotal = response.porcentajeTotal;
                //$scope.gaugeProgresoTotal.update($scope.porcentajeTotal);
            }
            if (response.graficos !== undefined) {
                $scope.graficos = response.graficos;
            }
            $scope.cargando = false;
            $scope.cargandoGraficos = false;
        });
    };

    $scope.traerGraficoLlamadas = function () {
        $scope.graficos.lineaLlamadas = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=graficoLineaLlamadas',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.graficos.lineaLlamadas = response.respuesta;
            }
            if (response.promedio !== undefined) {
                $scope.promedios.llamadas = response.promedio;
            } else {
                $scope.promedios.llamadas = "N/D";
            }
        });
    };

    $scope.traerGraficoCorreos = function () {
        $scope.graficos.lineaCorreos = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=graficoLineaCorreos',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.graficos.lineaCorreos = response.respuesta;
            }
            if (response.promedio !== undefined) {
                $scope.promedios.correos = response.promedio;
            } else {
                $scope.promedios.correos = "N/D";
            }
        });
    };

    $scope.traerGraficoWp = function () {
        $scope.graficos.lineaCorreos = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=graficoLineaWhatsapp',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.graficos.lineaWhatsapp = response.respuesta;
            }
            if (response.promedio !== undefined) {
                $scope.promedios.whatsapp = response.promedio;
            } else {
                $scope.promedios.whatsapp = "N/D";
            }
        });
    };

    $scope.traerGraficoCompromisos = function () {
        $scope.graficos.compromisos = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=graficoCompromisos',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.graficos.compromisos = response.respuesta;
            }
            if (response.resumen !== undefined) {
                $scope.resumenCompromisos = response.resumen;
            }
        });
    };

    $scope.cambioCheck = function () {
        clearTimeout($scope.debouncer);
        $scope.debouncer = setTimeout(() => {
            $scope.traerParametrizacion();
        }, 800);
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
            $scope.traerParametrizacion();
            console.log("Aplico filtro fecha");
        }
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

    let toa = null;
    $scope.actualizarAutomaticamente = function () {
        let u = window.location.href;
        if (u.includes("dashboardMultiCanal")) {
            window.clearTimeout(toa);
            if (!$scope.cargando && !$scope.cargandoGraficos) {
                //en x segundos actualice
                toa = window.setTimeout(() => {
                    if ($scope.filtroTiempo === "dia" || $scope.filtroTiempo === "hora") {
                        console.log("actualizo: " + new Date());
                        $scope.traerParametrizacion();
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

    $scope.setTiempoActualizar = function () {
        window.localStorage.setItem("tiempoActualizaDashboardGeneral", $scope.tiempoActualizar.toString());
        window.clearTimeout(toa);
        $scope.actualizarAutomaticamente();
    };

    $scope.leerTiempoActualizar = function () {
        let a = window.localStorage.getItem("tiempoActualizaDashboardGeneral");
        if (a !== null && a !== undefined) {
            $scope.tiempoActualizar = parseInt(a);
        } else {
            window.localStorage.setItem("tiempoActualizaDashboardGeneral", "60000");
            $scope.tiempoActualizar = 60000;
        }
    };

    $scope.quitarFiltro = function (filtro) {
        switch (filtro) {
            case "tiempo":
                $scope.filtroTiempo = "dia";
                $scope.cuantosAplicados--;
                break;
            case "lote":
                $scope.filtroLote = "todo";
                $scope.cuantosAplicados--;
                break;
            case "proveedor":
                $scope.filtroProveedor = "todo";
                $scope.cuantosAplicados--;
                break;
            case "cartera":
                $scope.filtroCartera = "todo";
                $scope.cuantosAplicados--;
                break;
            case "campania":
                $scope.filtroCampania = "todo";
                $scope.cuantosAplicados--;
                break;
            case "producto":
                $scope.filtroProducto = "todo";
                $scope.cuantosAplicados--;
                break;
            case "todo":
                $scope.filtroTiempo = "dia";
                $scope.filtroCartera = "todo";
                $scope.filtroCampania = "todo";
                $scope.cuantosAplicados = 0;
                break;
        }
        $scope.traerParametrizacion();
    };

    $scope.mostrarModalReportes = function () {
        $scope.modalReportes.activo = true;
    };

    $scope.descargarReporteLlamadas = function () {
        $scope.espere.llamadas = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=descargarGestionLlamadas',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                if (response.respuesta.archivo !== undefined) {
                    var anchor = document.createElement('a');
                    anchor.href = response.respuesta.archivo;
                    anchor.target = '_blank';
                    //anchor.download = "Reporte_llamadas.csv";
                    anchor.download = "Reporte_llamadas_" + $scope.obtenerFechaHoraArchivo() + ".csv";
                    anchor.click();
                }
                if (response.respuesta.error !== undefined) {
                    avisos.alerta("Error", response.respuesta.error);
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.espere.llamadas = false;
        });
    };

    $scope.descargarReporteCorreos = function () {
        $scope.espere.correos = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=descargarGestionCorreo',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                if (response.respuesta.archivo !== undefined) {
                    var anchor = document.createElement('a');
                    anchor.href = response.respuesta.archivo;
                    anchor.target = '_blank';
                    //anchor.download = "Reporte_correos.csv";
                    anchor.download = "Reporte_correos_" + $scope.obtenerFechaHoraArchivo() + ".csv";
                    anchor.click();
                }
                if (response.respuesta.error !== undefined) {
                    avisos.alerta("Error", response.respuesta.error);
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.espere.correos = false;
        });
    };

    $scope.descargarReporteWhatsapp = function () {
        $scope.espere.whatsapp = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=descargarGestionWhatsapp',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                if (response.respuesta.archivo !== undefined) {
                    var anchor = document.createElement('a');
                    anchor.href = response.respuesta.archivo;
                    anchor.target = '_blank';
                    //anchor.download = "Reporte_whatsapp.csv";
                    anchor.download = "Reporte_whatsapp_" + $scope.obtenerFechaHoraArchivo() + ".csv";
                    anchor.click();
                }
                if (response.respuesta.error !== undefined) {
                    avisos.alerta("Error", response.respuesta.error);
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.espere.whatsapp = false;
        });
    };

    $scope.descargarReporteGeneral = function () {
        $scope.espere.general = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionIndividualCtrl.php?act=descargarGestionGeneral',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                if (response.respuesta.archivo !== undefined) {
                    var anchor = document.createElement('a');
                    anchor.href = response.respuesta.archivo;
                    anchor.target = '_blank';
                    //anchor.download = "Reporte_general.csv";
                    anchor.download = "Reporte_general_" + $scope.obtenerFechaHoraArchivo() + ".csv";
                    anchor.click();
                } 
                if (response.respuesta.error !== undefined) {
                    avisos.alerta("Error", response.respuesta.error);
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.espere.general = false;
        });
    };

    $scope.$watch('filtroTiempo', function () {
        if ($scope.filtroTiempo !== "dia" && $scope.filtroTiempo !== "rango") {
            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
            $scope.filtrosAplicados.tiempo.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.tiempo.activo = false;
        }
    });

    $scope.$watch('filtroCampania', function () {
        if ($scope.filtroCampania !== "todo") {
            $scope.filtrosAplicados.campania.titulo = "Campaña: " + $scope.listas.campanias.find(element => element.id == $scope.filtroCampania).nombre;
            $scope.filtrosAplicados.campania.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.campania.activo = false;
        }
    });


    // NUEVO: función auxiliar para armar el sufijo de fecha/hora, ej: 09072026_143025
    $scope.obtenerFechaHoraArchivo = function () {
        var ahora = new Date();
        var pad = function (n) { return n < 10 ? "0" + n : n; };

        var dia = pad(ahora.getDate());
        var mes = pad(ahora.getMonth() + 1);
        var anio = ahora.getFullYear();
        var horas = pad(ahora.getHours());
        var minutos = pad(ahora.getMinutes());
        var segundos = pad(ahora.getSeconds());

        return dia + mes + anio + "_" + horas + minutos + segundos;
    };

    $scope.$watch('filtroCartera', function () {
        if ($scope.filtroCartera !== "todo") {
            let msg = $scope.listas.filtroPeriodo;
            if ($scope.listas.filtroPeriodo === -1) {
                msg = 'Todos los ciclos';
            }
            $scope.filtrosAplicados.cartera.titulo = "Cartera: " + $scope.listas.carteras.find(element => element.id == $scope.filtroCartera).nombre + '   Ciclo: ' + msg;
            $scope.filtrosAplicados.cartera.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.cartera.activo = false;
        }
    });
    $scope.$watch('listas.filtroPeriodo', function () {
        let msg = $scope.listas.filtroPeriodo;
        if ($scope.listas.filtroPeriodo === -1) {
            msg = 'Todos los ciclos';
        }
        $scope.filtrosAplicados.cartera.titulo = "Cartera: " + $scope.listas.carteras.find(element => element.id == $scope.filtroCartera).nombre + '   Ciclo: ' + msg;
    });

    $scope.traerParametrizacion();
    $scope.leerTiempoActualizar();

}]);

//_FIN_DE_ARCHIVO

