app.controller("cmDashboardGestiones", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

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
    
    $scope.tipoCartera = "";

    $scope.titulo = "";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "ini";
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
    $scope.cargandoExcel = {};
    $scope.debouncer = null;
    $scope.modalReportes = {
        evento: {},
        activo: false
    };

    $scope.modalGraficos = {
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
    //$scope.gaugeProgreso = loadLiquidFillGauge("gaugeProgreso", 0, configGaugeProgreso);
    //$scope.gaugeProgresoCorreo = loadLiquidFillGauge("gaugeProgresoCorreo", 0, configGaugeProgreso);
    //$scope.gaugeProgresoWhatsapp = loadLiquidFillGauge("gaugeProgresoWhatsapp", 0, configGaugeProgreso);

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


    $scope.opcionesLineaGestiones = {
        chart: {
            showControls: false,
            type: 'lineChart',
            noData: "Esperando datos",
            height: 170,
            margin: {
                top: 50,
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
            useInteractiveGuideline: false,
           
            
            xAxis: {
                axisLabel: 'Fecha',
                tickValues: function () {

                    if (!$scope.graficos || !$scope.graficos.lineaLlamadas) return [];

                    let fechas = {};

                    $scope.graficos.lineaLlamadas.forEach(function (serie) {
                        serie.values.forEach(function (p) {
                            fechas[p.x] = true;
                        });
                    });

                    let arr = Object.keys(fechas).map(Number).sort();

                    //let step = 1;

                  /*  if (arr.length > 15) step = 2;
                    if (arr.length > 25) step = 3;  //mostrar gráfica según días
                    if (arr.length > 40) step = 5;*/

                    let maxLabels = 10; // 

                    let step = Math.ceil(arr.length / maxLabels);

                    return arr.filter(function (v, i) {
                        return i % step === 0;
                    });
                },
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
              
                showMaxMin: true,
                staggerLabels: false,
                ticks: 12
            }
            ,

            yAxis: {
                axisLabel: 'Valores',
                tickFormat: function (d) {

                    // millones
                    if (d >= 1000000) {
                        let val = d / 1000000;
                        return val.toFixed(2).replace(/\.00$/, '') + 'M';
                    }

                    // miles
                    if (d >= 1000) {
                        let val = d / 1000;
                        return val.toFixed(2).replace(/\.00$/, '') + 'K';
                    }

                    //alores pequeños (decimales)
                    if (d < 1) {
                        return Number(d).toFixed(2);
                    }

                    // valores normales
                    return Number(d).toFixed(2).replace(/\.00$/, '');
                }
            }
        },
        title: {
            enable: false,
            text: "Gestiones realizadas este período"
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=cargarTablaDias',
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

    };

    $scope.cerrarDias = function () {
        $scope.verDias = false;
    };
    
    $scope.resincronizarCubo = function () {
        avisos.alerta("El proceso ocurrirá de manera asincrónica...","... y puede tomar algunos minutos");
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=resincronizarCubo',
            data: {
            }
        }).then(function (response) {
            avisos.alerta("Resincronización terminada");
        });
    };

    $scope.traerParametrizacion = function () {
        $scope.cargando = true;
        let dd = new Date();
        let fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
        if ($scope.filtroCartera === 'todo') {
            $scope.listas.filtroPeriodo = -1;
            $scope.periodo = [{ id: -1, nombre: 'Todos los ciclos' }];
        }
        switch ($scope.filtroTiempo) {
            case "ini":
                $scope.titulo = "";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                $scope.filtrosAplicados.tiempo.activo = false;
                break;
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
            
            case "asigActual":   

             
                $scope.titulo = "";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";

                if ($scope.filtroCartera === 'todo') {
                avisos.alerta("Advertencia","Por favor seleccione una cartera");
                }           

                break;
            case "asigAnterior":
            
                $scope.titulo = "";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";

                if ($scope.filtroCartera === 'todo') {
                avisos.alerta("Advertencia","Por favor seleccione una cartera");
                }   

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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroPeriodo: $scope.listas.filtroPeriodo
            }
        }).then(function (response) {
             if (response?.error === 1) {
                    avisos.alerta("Error", response.mensaje);
                    //return;
                }
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
                        if($scope.periodo[1]!==undefined){
                            let fechas=$scope.periodo[1];
                            if($scope.filtroTiempo=='asigActual'){
                                $scope.titulo = "Asignación Período Actual - " + fechas['fechaInicio'] +" al "+ fechas['fechaFin'];
                            }

                            if($scope.filtroTiempo=='asigAnterior'){
                                $scope.titulo = "Asignación Período Anterior - " + fechas['fechaInicio'] +" al "+ fechas['fechaFin'];
                            }
                        }else{
                            $scope.titulo = "Asignación Período Actual  ";
                        }
                    }
                }
                $scope.traerGraficoGestiones();
                $scope.traerTotales();
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
            }
            
            $scope.actualizarAutomaticamente();
        });

    };

    $scope.traerTotales = function () {
        $scope.cargandoGraficos = true;
        // $scope.cargando = true;
        $scope.graficos.barrasTotales = {};
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=obtenerTotales',
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
                //$scope.gaugeProgreso.update($scope.secciones.llamadasAV.porcentajeAvance);
            }
            if (response.correo !== undefined) {
                $scope.secciones.correo = response.correo;
                //$scope.gaugeProgresoCorreo.update($scope.secciones.correo.porcentajeAvance);
            }
            if (response.whatsapp !== undefined) {
                $scope.secciones.whatsapp = response.whatsapp;
                //$scope.gaugeProgresoWhatsapp.update($scope.secciones.whatsapp.porcentajeAvance);
            }
            if (response.totales !== undefined) {
                $scope.totales = response.totales;
                console.log(response.totales);
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
        //console.log($scope.totales);
    };

    $scope.traerGraficoGestiones = function () {
        $scope.graficos.lineaLlamadas = [];

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=graficoHistorialGestiones',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                tipo: $scope.tipoCartera,
                filtroPeriodo: $scope.listas.filtroPeriodo,
            }
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.graficos.lineaLlamadas = response.respuesta;

                console.log(response.respuesta);
                let data = response.respuesta;
                data.forEach(function (serie) {

                    let key = (serie.key || '').toUpperCase();
                    let tipo = ($scope.tipoCartera || '').toUpperCase();

                    if (key==('INTENSIDAD')) {
                        serie.key = 'INTENSIDAD GENERAL';
                    } 
                    else if (key.includes('TOTAL')) {
                        serie.key = 'N° Operaciones';
                    } 
                    else if (key.includes('MONTO')) {
                        serie.key = tipo.includes('PAGADA') ? 'Capital Pagado' : 'Capital Asignado';
                    } 
                    else if (key.includes('PORCENTAJE')) {
                        serie.key = 'Porcentaje (%)';
                    }

                    if (key.includes('INTENSIDAD')) {
                        serie.key = toCamelCase(serie.key);
                    }

                });

                 // Asignar data UNA sola vez
            $scope.graficos.lineaLlamadas = data;

            //Generar opciones por cada gráfica
            $scope.opcionesPorSerie = data.map(function (serie) {

                let opciones = angular.copy($scope.opcionesLineaGestiones);

                opciones.chart.yAxis.axisLabel = serie.key;

                return opciones;
            });
                
     
            }
           
        });
    };

    function toCamelCase(texto) {

        const excepciones = ['AV']; 

        return texto
            .toLowerCase()
            .replace(/[_-]/g, ' ')
            .split(' ')
            .map(function (word) {

                let upper = word.toUpperCase();

                //si está en excepciones, mantener mayúscula
                if (excepciones.includes(upper)) {
                    return upper;
                }

                // normal camel case
                return word.charAt(0).toUpperCase() + word.slice(1);
            })
            .join(' ');
    }


    

    $scope.traerGraficoCorreos = function () {
        $scope.graficos.lineaCorreos = [];
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=graficoLineaCorreos',
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=graficoLineaWhatsapp',
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=graficoCompromisos',
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
        if (u.includes("dashboardGestiones")) {
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
        window.localStorage.setItem("v", $scope.tiempoActualizar.toString());
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
                $scope.filtroTiempo = "ini";
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
                $scope.filtroTiempo = "ini";
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

    $scope.mostrarModalGraficos = function (tipo) {
        
        if ($scope.filtroTiempo!="ini" && $scope.filtroCartera !== "todo" ) {
        $scope.tipoCartera = tipo;        
        $scope.modalGraficos.activo = true;
        
            setTimeout(function(){
        $scope.traerGraficoGestiones();
        },200);
    }else{
        avisos.alerta("Error","No existen datos para mostrar la gráfica");
    }
       $scope.cerrarModalMenu();

    };

    $scope.cerrarModalMenu = function () {
        ShowObjectWithEffect('MenuCALayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCGLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCNGLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCGNCLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCGCLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCGPLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuGNGPLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCDLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCILayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuCPLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuIntensidadLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuIntensiCPLayer', 0, 'dropup', 400);
        ShowObjectWithEffect('MenuIntCNPLayer', 0, 'dropup', 400);
        
    };

    $scope.getFlex = function () {
        let n = $scope.graficos.lineaLlamadas.length;

        if (n === 2) return 34;
        if (n === 3) return 34; 
        if (n === 4) return 45; 

        return 33; // fallback
        
    };

    $scope.getWidthContenedor = function () {
    let lista = $scope.graficos?.lineaLlamadas || [];
    let n = lista.length;

    if (n === 4) return '108%'; 

        return '100%';
    };

    $scope.descargarReporteExcel = function (tipo) {
         $scope.tipoCartera = tipo;     
          $scope.cargandoExcel[tipo] = false;
        
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=descargarGestiones',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                tipo: $scope.tipoCartera,
                filtroPeriodo: $scope.listas.filtroPeriodo,
            }
        }).then(function (response) {

             
           if (response.ruta) {
                window.open(response.ruta, '_blank');
            } else {
                avisos.alerta("Error", "No se pudo generar el archivo");
            }
                $scope.cargandoExcel[tipo] = true;
        });
         //avisos.alerta("Error", "Descargar reporte excel");
               $scope.cerrarModalMenu();
    };

    $scope.cargarExcel = function (codigoCard) {
        return $scope.cargandoExcel[codigoCard] === false;
    };

    $scope.descargarReporteLlamadas = function () {
        $scope.espere.llamadas = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=descargarGestionLlamadas',
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
                    anchor.download = "Reporte_llamadas.csv";
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=descargarGestionCorreo',
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
                    anchor.download = "Reporte_correos.csv";
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=descargarGestionWhatsapp',
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
                    anchor.download = "Reporte_whatsapp.csv";
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
            url: '../canalesMasivos/cmDashboardGestionesCtrl.php?act=descargarGestionGeneral',
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
                    anchor.download = "Reporte_general.csv";
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
        if ($scope.filtroTiempo !== "ini" && $scope.filtroTiempo !== "rango") {
            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
            if($scope.titulo!==''){
            $scope.filtrosAplicados.tiempo.activo = true;
            }
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

