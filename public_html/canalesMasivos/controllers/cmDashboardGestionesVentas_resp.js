app.controller("cmDashboardGestionesVentas", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

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
         "productos": [],
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
    var funnel;
    var barras;
    let totalGeneralPie = 0;

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

                if (response?.productos !== undefined) {
                    if ($scope.filtroProducto === 'todo') {
                        $scope.listas.productos = response.productos;
                       
                    }
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
                actualizarFunnel($scope.totales);
                actualizarBarras($scope.totales)
 
            } 
         
            $scope.cargando = false;
             $scope.cargandoGraficos = false;
        });
        //console.log($scope.totales);
    }; 

  

    function actualizarGauge(valor) {
        valor = parseFloat(valor) || 0;
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
            { value: parseFloat(totales.tipificacion[0].porcentaje) || 0, name: 'Contacto Directo' },
            { value: parseFloat(totales.tipificacion[1].porcentaje) || 0, name: 'Contacto Indirecto' },
            { value: parseFloat(totales.tipificacion[2].porcentaje) || 0, name: 'Sin Contacto' },
            { value: parseFloat(totales.gestion[2].porcentaje) || 0, name: 'No Contactado' }
        ];

        //Cálculo total general
        totalGeneralPie  = data.reduce((sum, item) => sum + item.value, 0);

            pie.setOption({

                series: [
                    {
                        // ?? ESTA será la principal (la de adentro)
                        type: 'pie',
                        radius: '80%',
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

                                return `
                                    ${p.name}<br/>
                                    Total: ${totalItem}<br/>
                                    Porcentaje: ${porcentaje.toFixed(2)}%
                                `;
                            }
                        }
                    },

                    {
                        type: 'pie',
                        radius: '80%',
                        data: data,

                        tooltip: { show: false },   
                        silent: true               
                    }
                ]
            });
        }

    function actualizarFunnel(totales) {
        console.log(totales);

        if (!totales) return;

        var data = [
            { 
                value: parseNumero(totales.gestion[1]?.total) || 0, 
                name: 'Clientes Gestionados',
                itemStyle: { color: '#E05A5A' }
            },
            { 
                value: parseNumero(totales.gestion[3]?.total) || 0, 
                name: 'Clientes Contactados',
                itemStyle: { color: '#F5A04C' }
            },
            { 
                value: parseNumero(totales.gestion[4]?.total) || 0,
                name: 'Clientes Interesados',
                itemStyle: { color: '#F2D36A' }
            },
            { 
                value: 0, // ?? por ahora fijo
                name: 'Clientes Efectivos',
                itemStyle: { color: '#4FAF5A' }
            }


            

        ];

        funnel.setOption({

            tooltip: {
                trigger: 'item',
                formatter: function (p) {
                    return `
                        ${p.name}<br/>
                        Total: ${p.value}
                    `;
                }
            },

            series: [{
                data: data
            }]
        });
    }

    function parseNumero(valor) {
        if (!valor) return 0;
        return Number(valor.toString().replace(/\./g, ''));
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
        let dataBarras = [...totales.horarios].reverse().map((h, index) => {
            return {
                value: h.porcentaje || 0,
                total: h.total || 0,
                contactados: h.contactados || 0,
                name: nombres[index]
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
                        Total: ${p.data.total}<br/>
                        Contactados: ${p.data.contactados}
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
            case "todo":
                $scope.filtroTiempo = "ini";
                $scope.filtroCartera = "todo";
                $scope.filtroProducto = "todo";
                $scope.cuantosAplicados = 0;
                break;
        }

        $scope.traerParametrizacion();
    };

     

    //NUEVOS METODOS GRÁFICAS

 $timeout(function () {

    // GAUGE
    gauge = echarts.init(document.getElementById('chartGauge'), null, {
        renderer: 'svg'
    });

gauge.setOption({
    graphic: [
        {
            type: 'text',
            left: '77%',
            top: '68%',
            
        }
    ],

    series: [{
        type: 'gauge',

        startAngle: 180,
        endAngle: 0,

        min: 0,
        max: 100,
 
        splitNumber: 8,

        center: ['50%', '85%'], 
        radius: '150%',

        axisLine: {
            lineStyle: {
                width: 6,
                color: [  
                    [0.25, '#E05A5A'],
                    [0.5, '#F5A04C'],
                    [0.75, '#F2D36A'],
                    [1, '#4FAF5A']
                ]


             
            }
        },

        pointer: {
            icon: 'path://M12.8,0.7l12,40.1H0.7L12.8,0.7z',
            length: '12%',
            width: 20,
            offsetCenter: [0, '-60%'],
            itemStyle: {
                color: 'auto'
            }
        },

        axisTick: {
            length: 12,
            lineStyle: {
                color: 'auto',
                width: 2
            }
        },

        splitLine: {
            length: 20,
            lineStyle: {
                color: 'auto',
                width: 5
            }
        },

        /*axisLabel: {
            color: '#464646',
            fontSize: 12,
            distance: -50,
            rotate: 'tangential',
            formatter: function (value) {
                if (value === 100) return '';
                return value + '%';
            }
        },*/
        axisLabel: {
            show: false
        },

        title: {
            offsetCenter: [0, 0],
            fontSize: 16,
            color: '#000'
        }, 

        detail: {
            valueAnimation: true,
            fontSize: 26,
            offsetCenter: [0, '-20%'],
            color: 'inherit',
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
        legend: {
            orient: 'horizontal',
            top: -5,
            left: 'center',
            width: 300,    
            //itemGap: 20
        }, 

        series: [

            // CAPA INTERNA %
            {
                type: 'pie',
                radius: '70%',
                 top: 60, 
                  itemStyle: {
            opacity: 0.9   // ?? AQUÍ
        },
                label: {
                    position: 'inside',
                   // formatter: '{d}%',
                   formatter: function(params) {
                        if (totalGeneralPie === 0) return '0%';

                        let porcentaje = (params.value * 100) / totalGeneralPie;

                        return porcentaje.toFixed(2) + '%';
                    },
                    fontSize: 14,  
                   // fontWeight: 'bold',
                    color: '#fff',      
                  ///  fontWeight: 'bold',  
                   // textShadowColor: 'rgba(0, 0, 0, 0.7)',
                    //textShadowBlur: 6,
                    //textShadowOffsetX: 1,
                    //textShadowOffsetY: 1
                }, 
                labelLine: { show: false },
                data: data
            },
 
            // CAPA EXTERNA: nombres y líneas
            {
                type: 'pie', 
                radius: '70%',
                 top: 60, 
                label: {
                    position: 'outside',
                    formatter: '{b}',
                    fontSize: 14,  
                    //fontWeight: 'bold', 
                    //overflow: 'break',   
                    width: 200         
                },  
                labelLine: { 
                    show: true,
                    length: 12, 
                    length2: 10
                },
                 avoidLabelOverlap: true,

                labelLayout: {
                    moveOverlap: 'shiftY'
                },
                data: data
            }

        ]
    });

    // FUNNEL
    funnel = echarts.init(document.getElementById('chartFunnel'), null, {
        renderer: 'svg'
    });


    funnel.setOption({
       
        tooltip: {
            trigger: 'item',
            formatter: function (p) {
                return `
                    ${p.name}<br/>
                    Total: ${formatearMiles(p.value)}
                `;
            }
        },  
        series: [
            { 
            name: 'Total',
            type: 'funnel',  
            left: '13%', 
            top: 10,
            bottom: 60,
            width: '75%', 
            /*  min: 0,  
            max: 20, */ 
            minSize: '70%',  
            maxSize: '100%',
            sort: 'descending',  
            gap: 2,
            label: {
                show: true,
                position: 'inside',
                color: '#fff',
                /*textShadowColor: 'rgba(0, 0, 0, 0.7)',
                textShadowBlur: 6,
                textShadowOffsetX: 1,
                textShadowOffsetY: 1,*/
                fontSize: 15,
                formatter: function (params) {
                    return '{bold|' + formatearMiles(params.value) + '} ' + params.name;
                }, 
                rich: {
                    bold: {
                        fontWeight: 'bold'
                    }
                } 
            },
            labelLine: { 
                length: 10,
                lineStyle: {
                width: 1,
                type: 'solid'
                }
            },
            itemStyle: {
                borderColor: '#fff',
                borderWidth: 1
            },
            emphasis: {
                label: { 
                fontSize: 15 
                } 
            },
            data: [
                { value: 0, name: 'Clientes Gestionados',  itemStyle: { color: '#C56C6C' } },
                { value: 0, name: 'Clientes Contactados', itemStyle: { color: '#D88A4B' }  },
                { value: 0, name: 'Clientes Interesados',itemStyle: { color: '#D9B75F' } },
                { value: 0, name: 'Clientes Efectivos', itemStyle: { color: '#5E9C76' }  } 
                   
            ]


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
                data: [0, 0, 0, 0, 0, 0],
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

    $scope.$watch('filtroProducto', function () {

        if ($scope.filtroProducto !== "todo") {
            
            
            $scope.filtrosAplicados.producto.titulo = "Producto: " + $scope.listas.productos.find(element => element.id == $scope.filtroProducto).nombre;
            $scope.filtrosAplicados.producto.activo = true;
            $scope.cuantosAplicados++;
     
           

        } else {
            $scope.filtrosAplicados.producto.activo = false;
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

