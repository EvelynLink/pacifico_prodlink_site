app.controller("cmDashboardGestionesVentas", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.recargarTabula = 0;
    $scope.tablaDias = [];
    $scope.listas = {
        "canales": [
            { "nombre": "Llamada telefónica (agente virtual)", "id": "llamadas_av", "checked": true },
            { "nombre": "Correo electrónico", "id": "correo", "checked": true },
            { "nombre": "WhatsApp", "id": "whatsapp", "checked": true }
        ],
        "carteras": [],
         "productos": [],
        "fechasCarga": [],
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
    $scope.filtroProducto = "todo";
    $scope.filtroCanal = 'todo';
    $scope.filtroFechaCarga = "todo";
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
        },
        "producto": {
            "titulo": "",
            "activo": false,
        },
        "canal": {
            "titulo": "",
            "activo": false,
        },
        "fechaCarga": {
            "titulo": "",
            "activo": false,
        },
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

    $scope.kpi = {
        intentosCall: 4,
        intentosWhatsapp: 2,
        intentosCorreo: 1,
        porcentajeContactabilidad:46,
        efectividadComercial:17
    };
    var gauge;
    var pie;
    var barras;
    let totalGeneralPie = 0;

    // -----------------------------------------------------------------
    // Al "Volver" recuperamos los filtros que tenía el usuario antes de navegar
    // -----------------------------------------------------------------
    (function restaurarFiltrosDeUrl() {
        var qp = $location.search();
        if (!qp || Object.keys(qp).length === 0) { return; }

        if (qp.filtroCartera) { $scope.filtroCartera = qp.filtroCartera; }
        if (qp.filtroProducto) { $scope.filtroProducto = qp.filtroProducto; }
        if (qp.filtroCanal) { $scope.filtroCanal = qp.filtroCanal; }
        if (qp.filtroFechaCarga) { $scope.filtroFechaCarga = qp.filtroFechaCarga; }

        if (qp.filtroTiempo === "rango" && qp.filtroDesde && qp.filtroHasta) {
            $scope.filtroTiempo = "rango";
            $scope.filtroDesde = parseInt(qp.filtroDesde);
            $scope.filtroHasta = parseInt(qp.filtroHasta);
            $scope.fechaDesde = new Date($scope.filtroDesde * 1000);
            $scope.fechaHasta = new Date($scope.filtroHasta * 1000);

            var dd = $scope.fechaDesde;
            var dh = $scope.fechaHasta;
            var fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
            var fh = ("0" + dh.getDate()).slice(-2) + "/" + ("0" + (dh.getMonth() + 1)).slice(-2) + "/" + dh.getFullYear();
            $scope.titulo = "Del " + fd + " al " + fh;
        }
    })();

    function formatoDDMMYYYY(d) {
        return ("0" + d.getDate()).slice(-2) + "/" + ("0" + (d.getMonth() + 1)).slice(-2) + "/" + d.getFullYear();
    }

    $scope.$watch('fechaDesde', function (val) {
        if (!(val instanceof Date) || isNaN(val.getTime())) { return; }
        $timeout(function () {
            var el = document.getElementById('fechaDesde');
            if (el) { el.value = formatoDDMMYYYY(val); }
        }, 0);
    });

    $scope.$watch('fechaHasta', function (val) {
        if (!(val instanceof Date) || isNaN(val.getTime())) { return; }
        $timeout(function () {
            var el = document.getElementById('fechaHasta');
            if (el) { el.value = formatoDDMMYYYY(val); }
        }, 0);
    });

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

                    // Valores pequeños (decimales)
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



    $scope.traerParametrizacion = function () {

        $scope.cargando = true;

        switch ($scope.filtroTiempo) {
            case "ini":
                $scope.titulo = "";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                $scope.filtrosAplicados.tiempo.activo = false;
                break;
            case "todo":
                $scope.titulo = "Todo el tiempo";
                $scope.fechaDesde = "";
                $scope.fechaHasta = "";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                break;
        }

        $scope.validarFiltros = function () {
            if (!$scope.fechaDesde && !$scope.fechaHasta) {
                 avisos.alerta("Error", "Por favor seleccione un rango de fechas.");
            }

            $scope.traerParametrizacion();
        };



        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroPeriodo: $scope.listas.filtroPeriodo,
                filtroFechaCarga: $scope.filtroFechaCarga
            }
        }).then(function (response) {
             if (response?.error === 1) {
                    avisos.alerta("Error", response.mensaje);
                    //return;
                }
            if (response !== undefined) {
                if (response?.carteras !== undefined) {
                    $scope.listas.carteras = response.carteras;
                    actualizarBadgeCartera();
                }

                if (response?.productos !== undefined) {
                    $scope.listas.productos = response.productos;

                    if ($scope.filtroProducto !== "todo") {
                        var existeProducto = $scope.listas.productos.some(function (p) {
                            return p.id == $scope.filtroProducto;
                        });
                        if (!existeProducto) {
                            $scope.filtroProducto = "todo";
                        }
                    }

                    actualizarBadgeProducto();
                }

                // Fechas de Carga: solo tienen sentido con una cartera (y,
                // idealmente, un rango) seleccionados; se recalculan cada vez
                // que cambian esos filtros porque dependen de ellos.
                if (response?.fechasCarga !== undefined) {
                    $scope.listas.fechasCarga = response.fechasCarga;

                    if ($scope.filtroFechaCarga !== "todo") {
                        var existeFechaCarga = $scope.listas.fechasCarga.some(function (p) {
                            return p.id == $scope.filtroFechaCarga;
                        });
                        if (!existeFechaCarga) {
                            $scope.filtroFechaCarga = "todo";
                        }
                    }

                    actualizarBadgeFechaCarga();
                }

                if (response?.permisos !== undefined) {
                    $scope.permisos = response.permisos;
                }

                $scope.traerTotales();
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
            }

            $scope.actualizarAutomaticamente();
        });

    };


    $scope.descargarReporteExcel = function (tipo) {
        $scope.cargandoGraficos = true;

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=descargarGestiones',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTiempo: $scope.filtroTiempo,
            }
        }).then(function (response) {


           if (response.ruta) {
                window.open(response.ruta, '_blank');
            } else {
                avisos.alerta("Error", "No se pudo generar el archivo");
            }
                 $scope.cargandoGraficos = false;
        });

    };

    $scope.traerTotales = function () {
         $scope.cargandoGraficos = true;

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerTotales',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTiempo: $scope.filtroTiempo,
                filtroCanal: $scope.filtroCanal,
                filtroFechaCarga: $scope.filtroFechaCarga,
            }
        }).then(function (response) {

            if (response.totales !== undefined) {
                $scope.totales = response.totales;
                $scope.efectividad = response.efectividad;
                $scope.porcentajeTotal = response.porcentajeTotal;

                //Actualizar gauge porcentaje
                var porcentaje = $scope.totales.gestion[1].porcentaje;

                actualizarGauge(porcentaje);
                actualizarPie($scope.totales);
                actualizarBarras($scope.totales)

            }

            $scope.cargando = false;
             $scope.cargandoGraficos = false;
        });
        //console.log($scope.totales);
    };



    // El backend manda los porcentajes como texto con coma decimal ("0,56"),
    // asi que parseFloat directo los trunca mal (da 0). Aqui normalizamos.
    function parseNumeroLocal(valor) {
        return parseFloat(String(valor).replace(',', '.'));
    }

    function actualizarGauge(valor) {
        valor = parseNumeroLocal(valor) || 0;
        gauge.setOption({
            series: [{
                data: [{
                    value: valor,
                    name: 'Base Trabajada'
                }]
            }],
        });
    }


    function actualizarPie(totales) {

        var data = [
            { value: parseNumeroLocal(totales.tipificacion[0].porcentaje) || 0, name: 'Contacto Directo' },
            { value: parseNumeroLocal(totales.tipificacion[1].porcentaje) || 0, name: 'Contacto Indirecto' },
            { value: parseNumeroLocal(totales.tipificacion[2].porcentaje) || 0, name: 'Sin Contacto' },
            { value: parseNumeroLocal(totales.gestion[2].porcentaje) || 0, name: 'No Contactado' }
        ];

        //Cálculo total general
        totalGeneralPie  = data.reduce((sum, item) => sum + item.value, 0);

        let contactabilidad = totales.gestion[3]?.porcentaje || 0;

            pie.setOption({

                title: {
                    text: contactabilidad + '%',
                    subtext: 'Contactabilidad',
                    left: 'center',
                    top: '46%',
                    textStyle: {
                        fontSize: 15,
                        fontWeight: 'bold',
                        color: '#333'
                    },
                    subtextStyle: {
                        fontSize: 10,
                        color: '#8a8f98'
                    },
                    itemGap: 6
                },

                series: [
                    {
                        type: 'pie',
                        radius: ['55%', '85%'],
                        data: data,

                        tooltip: {
                            trigger: 'item',
                            formatter: function (p) {

                                let totalItem = 0;

                                switch (p.dataIndex) {
                                    case 0:
                                        totalItem = totales.tipificacion[0]?.total || 0;
                                        break;
                                    case 1:
                                        totalItem = totales.tipificacion[1]?.total || 0;
                                        break;
                                    case 2:
                                        totalItem = totales.tipificacion[2]?.total || 0;
                                        break;
                                    case 3:
                                        totalItem = totales.gestion[2]?.total || 0;
                                        break;
                                }

                                let porcentaje = totalGeneralPie === 0
                                    ? 0
                                    : (p.value * 100) / totalGeneralPie;

                                let porcentajeTexto = Number.isInteger(porcentaje)
                                    ? porcentaje
                                    : porcentaje.toFixed(2).replace('.', ',');

                                return `
                                    ${p.name}<br/>
                                    Total: ${totalItem}<br/>
                                    Porcentaje: ${porcentajeTexto}%
                                `;
                            }
                        }
                    }
                ]
            });
        }

    function formatearMiles(valor) {
        return valor.toLocaleString('es-EC'); // usa puntos para miles
    }

    function actualizarBarras(totales) {

        if (!totales || !totales.horarios) return;

        let nombres = [
            '18:00 o Más',
            '16:00 - 18:00',
            '14:00 - 16:00',
            '12:00 - 14:00',
            '10:00 - 12:00',
            'Hasta 10:00'
        ];

        // Invertimos porque backend viene al revés
        let horariosReversos = [...totales.horarios].reverse();

        let dataBarras = nombres.map((nombre, index) => {
            let h = horariosReversos[index] || {};
            return {
                value: h.porcentaje || 0,
                total: h.total || 0,
                contactados: h.contactados || 0,
                name: nombre
            };
        });

        barras.setOption({
            tooltip: {
                formatter: function (p) {

                    let valor = p.value || 0;

                    if (!Number.isInteger(valor)) {
                        valor = valor.toFixed(2).replace('.', ',');
                    }

                    return `
                        ${p.name}<br/>
                        Total: ${formatearMiles(p.data.total || 0)}<br/>
                        Contactados: ${formatearMiles(p.data.contactados || 0)}<br/>
                        Porcentaje: ${valor}%
                    `;
                }
            },
            series: [{
                data: dataBarras,
                label: {
                    formatter: function(p) {
                        let val = p.value || 0;

                        if (!Number.isInteger(val)) {
                            val = val.toFixed(2).replace('.', ',');
                        }

                        return val + '%';
                    }
                }
            }]
        });
    }


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

            $scope.filtroTiempo = "rango";
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
            case "canal":
                $scope.filtroCanal = "todo";
                $scope.cuantosAplicados--;
                break;
            case "fechaCarga":
                $scope.filtroFechaCarga = "todo";
                $scope.cuantosAplicados--;
                break;
            case "todo":
                $scope.filtroTiempo = "ini";
                $scope.filtroCartera = "todo";
                $scope.filtroProducto = "todo";
                $scope.filtroCanal = "todo";
                $scope.filtroFechaCarga = "todo";
                $scope.cuantosAplicados = 0;
                break;
        }

        $scope.traerParametrizacion();
    };

    // Navega a la pantalla de detalle del KPI "Gestión de Base",
    // llevando los mismos filtros vigentes (incluido canal y fecha carga).
    $scope.irDetalleBase = function () {
        let params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/detalleGestionBase" + params;
    };

    // Navega a la pantalla de detalle del KPI "Contactabilidad"
    $scope.irDetalleContactabilidad = function () {
        let params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/detalleContactabilidad" + params;
    };

    // Navega a la pantalla de detalle del KPI "Efectividad Comercial"
    $scope.irDetalleEfectividad = function () {
        let params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/detalleEfectividad" + params;
    };


    // Navega a la pantalla de detalle de "Contactabilidad por Horarios"
    $scope.irDetalleHorarios = function () {
        let params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/detalleHorarios" + params;
    };


    // Navega a la pantalla de detalle de "Intensidad por Canales"
    $scope.irDetalleIntensidad = function () {
        let params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/detalleIntensidad" + params;
    };



    //NUEVOS METODOS GRÁFICAS

 $timeout(function () {

    // GAUGE
    gauge = echarts.init(document.getElementById('chartGauge'), null, {
        renderer: 'svg'
    });

gauge.setOption({

    series: [{
        type: 'gauge',

        // Anillo/circulo de progreso completo (en vez del semicirculo con aguja)
        startAngle: 90,
        endAngle: -270,

        min: 0,
        max: 100,

        center: ['50%', '50%'],
        radius: '90%',

        progress: {
            show: true,
            width: 10,
            itemStyle: {
                color: '#4FAF5A'
            }
        },

        axisLine: {
            lineStyle: {
                width: 10,
                color: [
                    [1, '#EDEFF2']
                ]
            }
        },

        pointer: {
            show: false
        },

        axisTick: {
            show: false
        },

        splitLine: {
            show: false
        },

        axisLabel: {
            show: false
        },

        title: {
            show: true,
            offsetCenter: [0, '38%'],
            fontSize: 13,
            color: '#8a8f98'
        },

        detail: {
            valueAnimation: true,
            fontSize: 24,
            fontWeight: 'bold',
            offsetCenter: [0, 0],
            color: '#4FAF5A',
            formatter: function (value) {
                if (Number.isInteger(value)) {
                    return value + '%';
                }
                return value.toFixed(2).replace('.', ',') + '%';
            }
        },

        data: [{
            value: 0,
            name: 'Base Trabajada'
        }]
    }]
});

    // PIE
    pie = echarts.init(document.getElementById('chartPie'), null, {
        renderer: 'svg'
    });



    var data = [
        { value: 0, name: 'Contacto Directo' },
        { value: 0, name: 'Contacto Indirecto' },
        { value: 0, name: 'Sin Contacto' },
        { value: 0, name: 'No Contactado' }
    ];

    pie.setOption({
        color: [
          '#4FAF5A',
          '#F2D36A',
          '#F5A04C',
          '#E05A5A',
        ],

        tooltip: {
            trigger: 'item',
            textStyle: {
                color: '#000',
            },
        },

        // La leyenda ahora se dibuja en HTML al costado de la dona por eso se apaga aquí.
        legend: {
            show: false
        },

        title: {
            text: '0%',
            subtext: 'Contactabilidad',
            left: 'center',
            top: '46%',
            textStyle: {
                fontSize: 15,
                fontWeight: 'bold',
                color: '#333'
            },
            subtextStyle: {
                fontSize: 10,
                color: '#8a8f98'
            },
            itemGap: 6
        },

        series: [
            {
                type: 'pie',
                radius: ['55%', '85%'],
                itemStyle: {
                    opacity: 0.9
                },
                label: {
                    show: false
                },
                labelLine: { show: false },
                data: data
            }
        ]
    });

        //BARRAS
       barras = echarts.init(document.getElementById('chartBarras'), null, {
        renderer: 'svg'
        });


        barras.setOption({
            tooltip: {
                trigger: 'item',
                textStyle: {
                    color: '#000'
                },
                formatter: function (p) {

                    let valor = p.value || 0;

                    if (!Number.isInteger(valor)) {
                        valor = valor.toFixed(2).replace('.', ',');
                    }

                    return `
                        ${p.name}<br/>
                        Valor: ${valor}%
                    `;
                }
            },

            grid: {
                left: 8,
                right: 16,
                top: 8,
                bottom: 8,
                containLabel: true
            },

            xAxis: {
                type: 'value',
                axisLabel: { show: false },
                splitLine: { show: false }
            },
            yAxis: {
                type: 'category',
                data: ['18:00 o Más','16:00 - 18:00', '14:00 - 16:00', '12:00 - 14:00', '10:00 - 12:00', 'Hasta 10:00'],
                axisLabel: {
                    fontSize: 13,
                    color: '#333',
                }
            },
            series: [{
                type: 'bar',
                barWidth: 12,
                data: [0, 0, 0, 0, 0, 0],
                showBackground: true,
                backgroundStyle: {
                    color: '#eef0f2',
                    borderRadius: 6
                },
                label: {
                    show: true,
                    position: 'right',
                    color: '#2E3A46',
                  ///  fontWeight: 'bold',
                    formatter: function(p) {
                        let val = p.value || 0;

                        if (!Number.isInteger(val)) {
                            val = val.toFixed(2).replace('.', ',');
                        }

                        return val + '%';
                    }
                },
                itemStyle: {
                    borderRadius: 6,
                    color: function(params) {
                        var val = params.value;

                        if (val <= 30) {
                            return '#E05A5A';
                        } else if (val <= 50) {
                            return '#F5A04C';
                        } else if (val <= 60) {
                            return '#F2D36A';
                        } else {
                            return '#4FAF5A'
                        }
                    }


                }
            }]
        });

        }, 100);



        $scope.mostrarModalGestiones = function () {


        $scope.modalGraficos.activo = true;

            setTimeout(function(){
        //$scope.traerGraficoGestiones();
        },200);


    };





    $scope.$watch('filtroTiempo', function () {
        if ($scope.filtroTiempo !== "ini" ) {
            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
            if($scope.titulo!==''){
            $scope.filtrosAplicados.tiempo.activo = true;
            }
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.tiempo.activo = false;
        }
    });


    function actualizarBadgeCartera() {
        if ($scope.filtroCartera === "todo") {
            $scope.filtrosAplicados.cartera.activo = false;
            return;
        }

        let cartera = $scope.listas.carteras.find(element => element.id == $scope.filtroCartera);
        if (!cartera) {
            return;
        }

        let msg = $scope.listas.filtroPeriodo;
        if ($scope.listas.filtroPeriodo === -1) {
            msg = 'Todos los ciclos';
        }

        $scope.filtrosAplicados.cartera.titulo = "Cartera: " + cartera.nombre + '   Ciclo: ' + msg;
        if (!$scope.filtrosAplicados.cartera.activo) {
            $scope.cuantosAplicados++;
        }
        $scope.filtrosAplicados.cartera.activo = true;
    }

    function actualizarBadgeProducto() {
        if ($scope.filtroProducto === "todo") {
            $scope.filtrosAplicados.producto.activo = false;
            return;
        }

        let producto = $scope.listas.productos.find(element => element.id == $scope.filtroProducto);
        if (!producto) {
            return;
        }

        $scope.filtrosAplicados.producto.titulo = "Producto: " + producto.nombre;
        if (!$scope.filtrosAplicados.producto.activo) {
            $scope.cuantosAplicados++;
        }
        $scope.filtrosAplicados.producto.activo = true;
    }

    // Nombres a mostrar en el badge, con las MISMAS llaves y el mismo texto
    // que se guardan en cubAV_canal / se mandan al backend: AV, EMAIL, WHATSAPP.
    $scope.nombresCanal = {
        "AV": "AV",
        "EMAIL": "EMAIL",
        "WHATSAPP": "WHATSAPP"
    };

    function actualizarBadgeCanal() {
        if ($scope.filtroCanal === "todo") {
            $scope.filtrosAplicados.canal.activo = false;
            return;
        }

        let nombreCanal = $scope.nombresCanal[$scope.filtroCanal];
        if (!nombreCanal) {
            return;
        }

        $scope.filtrosAplicados.canal.titulo = "Canal: " + nombreCanal;
        if (!$scope.filtrosAplicados.canal.activo) {
            $scope.cuantosAplicados++;
        }
        $scope.filtrosAplicados.canal.activo = true;
    }

    // El texto del badge sale de listas.fechasCarga (que llega de
    // obtenerParametrizacion, ya formateado dd/mm/aaaa por el backend).
    function actualizarBadgeFechaCarga() {
        if ($scope.filtroFechaCarga === "todo") {
            $scope.filtrosAplicados.fechaCarga.activo = false;
            return;
        }

        let fechaCarga = $scope.listas.fechasCarga.find(element => element.id == $scope.filtroFechaCarga);
        if (!fechaCarga) {
            return;
        }

        $scope.filtrosAplicados.fechaCarga.titulo = "Fecha de Carga: " + fechaCarga.nombre;
        if (!$scope.filtrosAplicados.fechaCarga.activo) {
            $scope.cuantosAplicados++;
        }
        $scope.filtrosAplicados.fechaCarga.activo = true;
    }

    $scope.$watch('filtroCartera', actualizarBadgeCartera);

    $scope.$watch('filtroProducto', actualizarBadgeProducto);

    $scope.$watch('filtroCanal', actualizarBadgeCanal);

    $scope.$watch('filtroFechaCarga', actualizarBadgeFechaCarga);

    $scope.$watch('listas.filtroPeriodo', function () {
        actualizarBadgeCartera();
    });

    $scope.traerParametrizacion();
    $scope.leerTiempoActualizar();




}]);

//_FIN_DE_ARCHIVO