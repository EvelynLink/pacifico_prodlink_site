app.controller("cmDashboardGestionesCarteraPorMora", ['backendController','$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function (backendController,$scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);

    const END_POINT = '../canalesMasivos/cmDashboardGestionesCarteraPorMoraCtrl.php';
    const API = backendController(END_POINT);

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
    $scope.filtroAsignacion = "ini";
    $scope.ultimaAsignacion = "";
    $scope.ultimaCartera = "";
    $scope.rangoMinimo="";
    $scope.rangoMaximo="";
    $scope.filtroCampania = "todo";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";
    $scope.filtroMarca = "todo";
    $scope.filtroRegion = "todo";
    $scope.filtroCanton = "todo";
    $scope.filtroFechaCarga = "todo";
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
        "marca": {
            "titulo": "",
            "activo": false,
        },
         "region": {
            "titulo": "",
            "activo": false,
        },
          "canton": {
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
    var funnel;
    var barras;
    let totalGeneralPie = 0;
    var totalGlobal;

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
       
        switch ($scope.filtroAsignacion) {
            case "ini":
                $scope.fechaDesde = null;
                $scope.fechaHasta = null;

                $scope.filtroDesde = "";
                $scope.filtroHasta = "";

                $scope.rangoMinimo = "";
                $scope.rangoMaximo = "";

                $scope.filtroTiempo = "ini";

                $scope.filtroCartera = "todo";
                $scope.listas.filtroPeriodo = -1;
                $scope.filtroFechaCarga = "todo";
                $scope.filtroProducto = "todo";
                $scope.filtroMarca = "todo";
                $scope.filtroRegion = "todo";
                $scope.filtroCanton = "todo";
 
               
                $scope.titulo = "";
                $scope.filtrosAplicados.tiempo.activo = false;
                

                break;
            case "asigActual":
                if($scope.filtroTiempo !== 'rango'){               
                $scope.titulo = "";
                }
                if ($scope.filtroCartera === 'todo') {
                avisos.alerta("Error","Por favor seleccione una cartera");
                }           
                break;
            case "asigAnterior": 
                if($scope.filtroTiempo !== 'rango'){            
                    $scope.titulo = "";  
                }         
                if ($scope.filtroCartera === 'todo') {
                avisos.alerta("Error","Por favor seleccione una cartera");
                }   
                break;
            case "todo":
                $scope.titulo = "Todo el tiempo";              
                break;
        }
      
        API.post('obtenerParametrizacion', {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroMarca: $scope.filtroMarca,
                filtroRegion: $scope.filtroRegion,
                filtroCanton: $scope.filtroCanton,
                filtroFechaCarga: $scope.filtroFechaCarga,
                filtroTiempo: $scope.filtroTiempo,
                filtroAsignacion: $scope.filtroAsignacion,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroPeriodo: $scope.listas.filtroPeriodo
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

                if (response?.periodos !== undefined && response.periodos.length > 0) {
                    if ($scope.filtroCartera !== 'todo') {
                        $scope.periodo = response.periodos;
                        if ($scope.periodo[1] !== undefined &&
                            ($scope.filtroTiempo !== 'rango' || $scope.ultimaAsignacion !== $scope.filtroAsignacion || $scope.ultimaCartera !== $scope.filtroCartera)
                        ) {
                            let fechas=$scope.periodo[1];
                
                            //Fechas en string
                            $scope.fechaDesde = fechas['fechaInicio'];
                            $scope.fechaHasta = fechas['fechaFin'];


                            //fechas en unixtime
                            $scope.filtroDesde = fechas['fechaInicioConv'];
                            $scope.filtroHasta = fechas['fechaFinConv'];
                            $scope.filtroTiempo="rango";

                            // límites del período para validación
                            $scope.rangoMinimo = convertirFecha(fechas['fechaInicio']);
                            $scope.rangoMaximo = convertirFecha(fechas['fechaFin']);

                          
                             // GUARDAR ASIGNACION ACTUAL
                            $scope.ultimaAsignacion = $scope.filtroAsignacion;
                            $scope.ultimaCartera = $scope.filtroCartera;

                           if ($scope.filtroAsignacion === 'asigActual') {
                            $scope.titulo = "Asignación Período Actual - Del " + $scope.fechaDesde + " al " + $scope.fechaHasta;
                        }

                        if ($scope.filtroAsignacion === 'asigAnterior') {
                            $scope.titulo = "Asignación Período Anterior - Del " + $scope.fechaDesde + " al " + $scope.fechaHasta;
                        }
                            
                            
                            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
                            $scope.filtrosAplicados.tiempo.activo = true;

                            $scope.cuantosAplicados++;


                            
                        }
                    }
                }

                if (response?.productos !== undefined) {
                    if ($scope.filtroProducto === 'todo') {
                        $scope.listas.productos = response.productos;
                       
                    }
                }

                 if (response?.marcas !== undefined) {
                    if ($scope.filtroMarca === 'todo') {
                        $scope.listas.marcas = response.marcas;
                       
                    }
                }

                 if (response?.regiones !== undefined) {
                    if ($scope.filtroRegion === 'todo') {
                        $scope.listas.regiones = response.regiones;
                       
                    }
                }

                if (response?.cantones !== undefined) {
                    if ($scope.filtroCanton === 'todo') {
                        $scope.listas.cantones = response.cantones;
                       
                    }
                }

                if (response?.fechasCarga !== undefined) {
                    if ($scope.filtroFechaCarga === 'todo') {
                        $scope.listas.fechasCarga = response.fechasCarga;
                       
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

     $scope.validarFiltros = function () {
            if (!$scope.fechaDesde && !$scope.fechaHasta) {
                 avisos.alerta("Error", "Por favor seleccione un rango de fechas.");
            }

            $scope.traerParametrizacion();
        };




    $scope.traerTotales = function () {
         $scope.cargandoGraficos = true;


          API.post('obtenerTotales', {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTiempo: $scope.filtroTiempo,
                filtroMarca: $scope.filtroMarca,
                filtroRegion: $scope.filtroRegion,
                filtroCanton: $scope.filtroCanton,
                filtroFechaCarga: $scope.filtroFechaCarga,
                filtroAsignacion: $scope.filtroAsignacion,
                filtroPeriodo: $scope.listas.filtroPeriodo 
            }).then(function (response) {
           console.log(response.totales);
            if (response.totales !== undefined) {
                $scope.totales = response.totales;

                actualizarGraficaTramosMora($scope.totales.tramosMora.grafica);
                actualizarDonutMora($scope.totales.graficaSaldosMora);
                actualizarEvolucionVencida($scope.totales.evolucionVencida);
               
                
                console.log($scope.totales);
 
            } 
         
            $scope.cargando = false;
             $scope.cargandoGraficos = false;
        });
        //console.log($scope.totales);
    }; 

    function actualizarGraficaTramosMora(datos) {
        
        var categorias = [];
        var valores = [];

        var colores = [
            '#4CAF50',
            '#7CB342',
            '#C0CA33',
            '#FDD835',
            '#F9A825',
            '#FB8C00',
            '#F4511E',
            '#E53935',
            '#B71C1C'
        ];

        // Si no hay datos mostrar gráfico vacío
        if (!datos || datos.length === 0) {

            categorias.push('Sin datos');

            valores.push({
                value: 0,
                itemStyle: {
                    color: '#D0D5DD'
                }
            });

        } else {

            clientesMora.setOption({

                title: {
                    show: false
                },

                xAxis: {
                    data: categorias
                },

                series: [{
                    data: valores
                }]

            });

        
            angular.forEach(datos, function (item, index) {

                categorias.push(item.nombre);

                valores.push({
                    value: item.total,
                    itemStyle: {
                        color: colores[index % colores.length]
                    }
                });

            });

        }

        clientesMora.setOption({

            xAxis: {
                data: categorias
            },

            series: [{
                data: valores
            }]

        });

    }



function actualizarDonutMora(datos) {

    var colores = [
        '#4CAF50',
        '#7CB342',
        '#C0CA33',
        '#FDD835',
        '#F9A825',
        '#FB8C00',
        '#F4511E',
        '#E53935',
        '#B71C1C'
    ];

    var dataPie = [];
    var totalSaldo = 0;

    angular.forEach(datos, function(item, index){
        totalSaldo += Number(item.saldo);

        dataPie.push({
            value: item.saldo,
            name: item.nombre,
            itemStyle: {
                color: colores[index % colores.length]
            }
        });
    });

    totalGlobal = totalSaldo;

    donutMora.setOption({
        series: [{
            type: 'pie',
            radius: ['40%', '78%'],
            center: ['35%', '50%'],

            data: dataPie,

            label: {
                show: true,
                position: 'inside',

                formatter: function (params) {

                    var porcentaje = (params.value / totalGlobal) * 100;
                    return $scope.formatearMiles(porcentaje.toFixed(2),2) + '%';
                },

                color: '#fff',
                fontSize: 12,
                fontWeight: 600
            },

            labelLine: { show: false },

            itemStyle: {
                borderColor: '#fff',
                borderWidth: 3
            }
        }],

        legend: {
            orient: 'vertical',
            right: 20,
            top: 'center',
            icon: 'circle'
        }
    });


    donutMora.off('finished');

    donutMora.on('finished', function () {

        donutMora.setOption({
            title: {
                text:
                    '$' + $scope.formatearMiles(totalGlobal.toFixed(2),2) +
                    '\nSaldo Total',

                left: '34%',
                top: '50%',

                textAlign: 'center',
                textVerticalAlign: 'middle',

                textStyle: {
                    fontSize: 16,
                    fontWeight: 700,
                    color: '#344054',
                    lineHeight: 22
                }
            }
        });
    });

    donutMora.resize();
}


function actualizarEvolucionVencida(datos) {

    var maximo = Math.max.apply(null, datos.porcentajes);

    lineaMora.setOption({

        tooltip: {

            trigger: 'axis',

            formatter: function(params){

                var i = params[0].dataIndex;

                return `
                    <b>${datos.meses[i]}</b><br/>
                    Total Cartera: ${$scope.formatearMiles(datos.totalClientes[i],0)}<br/>
                    Cartera Vencida: ${$scope.formatearMiles(datos.clientesMora[i],0)}
                `;
            }
        },

        xAxis: {
            data: datos.meses
        },

        yAxis: {

            min: 0,

            max: Math.ceil(maximo / 10) * 10
        },

        series: [{
            data: datos.porcentajes
        }]
    });

}




$scope.formatearMiles = function(valor, decimales) {

    return Number(valor).toLocaleString(
        'es-EC',
        {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }
    );

};

$scope.obtenerColorTramo = function(index){

    var clases = [
        'badge-verde',
        'badge-verde-claro',
        'badge-limon',
        'badge-amarillo',
        'badge-amarillo-oscuro',
        'badge-naranja',
        'badge-naranja-oscuro',
        'badge-rojo',
        'badge-vino'
    ];

    return clases[index % clases.length];
};

$scope.obtenerClaseTramo = function(indice){

    var clases = [
        'hm-green',
        'hm-lightgreen',
        'hm-yellow',
        'hm-yellow-dark',
        'hm-orange',
        'hm-orange-dark',
        'hm-red-light',
        'hm-red',
        'hm-red-dark'
    ];

    return clases[indice] || 'hm-red-dark';
};


// CHART CLIENTES POR MORA


$timeout(function () {

    // CHART CLIENTES POR MORA (barra)

    clientesMora = echarts.init(
        document.getElementById('chartClientesMora'),
        null,
        {
            renderer: 'svg'
        }
    );

    clientesMora.setOption({
         title: {
        text: 'Seleccione tipo de asignación y cartera ',
        left: 'center',
        top: 'middle',
        textStyle: {
            fontSize: 14,
            color: '#98A2B3'
        }
    },

        animationDuration: 1200,

        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            },
            formatter: function(params){

                var p = params[0];

                return p.name + '<br/>' +
                    'Clientes: ' +
                    $scope.formatearMiles(p.value, 0);
            }
        },

        grid: {
            left: '4%',
            right: '4%',
            bottom: '5%',
            top: '10%',
            containLabel: true
        },

        xAxis: {
            type: 'category',

            data: [],

            axisLine: {
                lineStyle: {
                    color: '#D0D5DD'
                }
            },

            axisTick: {
                show: false
            },

            axisLabel: {
                color: '#667085',
            fontSize: 12,
            interval: 0,
            rotate: 20
            }
        },

        yAxis: {
            type: 'value',

            splitLine: {
                lineStyle: {
                    color: '#F0F2F5'
                }
            },

            axisLabel: {
                color: '#98A2B3',
                fontSize: 11,
                formatter: function(value){
                    return $scope.formatearMiles(value, 0);
                }
            }
        },

        series: [{

            type: 'bar',

            barWidth: '45%',

            label: {
                show: true,
                position: 'top',
                color: '#344054',
                fontSize: 11,
                 formatter: function(params){
                    return $scope.formatearMiles(params.value,0);
                }
            },

            itemStyle: {
                borderRadius: [6, 6, 0, 0]
            },

            data: [ ]

        }]
    });



// DONUT MORA

donutMora = echarts.init(
    document.getElementById('chartDonutMora'),
    null,
    {
        renderer: 'svg'
    }
);

donutMora.setOption({

    animationDuration: 1200,

     tooltip: {
        trigger: 'item',
        formatter: function (p) {

            var porcentaje = (Number(p.value) / totalGlobal) * 100;

            return `
                ${p.name}<br/>
                Saldo: $${$scope.formatearMiles(p.value,2)}<br/>
                Porcentaje: ${$scope.formatearMiles(porcentaje.toFixed(2),2)}%
            `;
        }
    },
    graphic: [

        {
            type: 'text',

            left: '32%',

            top: '43%',

            style: {

                text: '',

                textAlign: 'center',

                fill: '#344054',

                fontSize: 16,

                fontWeight: 700,

                lineHeight: 24
            }
        }

    ],



    series: [{

        type: 'pie',

        radius: ['40%', '78%'],

        center: ['35%', '50%'],

        avoidLabelOverlap: false,

        label: {

            show: true,

            position: 'inside',

            color: '#fff',

            fontSize: 12,

            formatter: function(params){

                return $scope.formatearMiles(params.percent, 2)  + '%';
            }

        },

        labelLine: {
            show: false
        },

        itemStyle: {
            borderColor: '#fff',
            borderWidth: 3
        },

        data: [{
            value: 1,
            name: 'Sin información',
            itemStyle: {
                color: '#E5E7EB'
            }
        }]
    }]
});




// ======================================
// CHART LÍNEA - EVOLUCIÓN MORA
// ======================================

lineaMora = echarts.init(
    document.getElementById('chartLineaMora'),
    null,
    {
        renderer: 'svg'
    }
);

lineaMora.setOption({

    animationDuration: 1200,

    grid: {
        left: '5%',
        right: '6%',
        top: '10%',
        bottom: '12%',
        containLabel: true
    },

    tooltip: {
        trigger: 'axis'
    },

    xAxis: {

        type: 'category',

        boundaryGap: false,

        data: [],

        axisLine: {
            lineStyle: {
                color: '#D0D5DD'
            }
        },

        axisLabel: {
            color: '#667085',
            fontSize: 11
        }
    },

    yAxis: {

        type: 'value',
        min: 0,

        axisLabel: {
            formatter: '{value}%',
            color: '#667085'
        },

        splitLine: {
            lineStyle: {
                color: '#EAECF0'
            }
        }
    },

    series: [{

        name: '% Mora',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 8,
        data: [],

        lineStyle: {
            width: 3,
            color: '#EB5757'
        },

        itemStyle: {
            color: '#EB5757',
            borderColor: '#fff',
            borderWidth: 2
        },

        areaStyle: {
            opacity: 0.08,
            color: '#EB5757'
        },

        label: {

            show: true,
            position: 'top',
            offset: [15, 0],
            color: '#344054',
            fontSize: 11,
            formatter: function(p){

                //return p.value + '%';
                return $scope.formatearMiles(p.value,2) + '%';


                
            }
        }
    }]
});


}, 100); 

    


    $scope.aplicarRangoFechas = function () {

    if (typeof $scope.fechaDesde === "object" &&
        typeof $scope.fechaHasta === "object") {

        $scope.filtroDesde = Math.floor($scope.fechaDesde.getTime() / 1000);
        $scope.filtroHasta = Math.floor($scope.fechaHasta.getTime() / 1000);

        // VALIDAR RANGO NORMAL
        if ($scope.filtroHasta < $scope.filtroDesde) {
            avisos.alerta("Error", "Rango de fechas incorrecto");
            return;
        }

        // VALIDAR MAXIMO 3 MESES
        if (($scope.filtroHasta - $scope.filtroDesde) > 7889238000) {
            avisos.alerta("Error", "No se puede mostrar un periodo mayor a 3 meses");
            return;
        }

        // VALIDAR LIMITE DEL PERIODO ASIGNADO
      
        if (
            ($scope.rangoMinimo && $scope.filtroDesde < $scope.rangoMinimo) ||
            ($scope.rangoMaximo && $scope.filtroHasta > $scope.rangoMaximo)
        ) {

            let fechaMin = new Date($scope.rangoMinimo * 1000);
            let fechaMax = new Date($scope.rangoMaximo * 1000);

            let fmin =
                ("0" + fechaMin.getDate()).slice(-2) + "/" +
                ("0" + (fechaMin.getMonth() + 1)).slice(-2) + "/" +
                fechaMin.getFullYear();

            let fmax =
                ("0" + fechaMax.getDate()).slice(-2) + "/" +
                ("0" + (fechaMax.getMonth() + 1)).slice(-2) + "/" +
                fechaMax.getFullYear();

            avisos.alerta(
                "Error",
                "Las fechas deben estar entre " + fmin + " y " + fmax
            );

            return;
        }

        $scope.filtroTiempo = "rango";

        let dd = new Date($scope.fechaDesde.getTime());
        let dh = new Date($scope.fechaHasta.getTime());

        let fd =
            ("0" + dd.getDate()).slice(-2) + "/" +
            ("0" + (dd.getMonth() + 1)).slice(-2) + "/" +
            dd.getFullYear();

        let fh =
            ("0" + dh.getDate()).slice(-2) + "/" +
            ("0" + (dh.getMonth() + 1)).slice(-2) + "/" +
            dh.getFullYear();

        if ($scope.filtroAsignacion === 'asigActual') {

            $scope.titulo = "Asignación Período Actual - Del " + fd + " al " + fh;

        } else if ($scope.filtroAsignacion === 'asigAnterior') {

            $scope.titulo = "Asignación Período Anterior - Del " + fd + " al " + fh;

        } else {

            $scope.titulo = "Del " + fd + " al " + fh;
        }

        $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
        $scope.filtrosAplicados.tiempo.activo = true;

        $scope.cuantosAplicados++;

        $scope.traerParametrizacion();
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
                $scope.filtroAsignacion = "ini";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                 $scope.fechaDesde = null;
                $scope.fechaHasta = null;
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
            case "marca":
                $scope.filtroMarca = "todo";
                $scope.cuantosAplicados--;
                break;
            case "region":
                $scope.filtroRegion = "todo";
                $scope.cuantosAplicados--;
                break;
            case "canton":
                $scope.filtroCanton = "todo";
                $scope.cuantosAplicados--;
                break;
            case "fechaCarga":
                console.log("entro a quitar fecha carga");
                $scope.filtroFechaCarga = "todo";
                $scope.cuantosAplicados--;
                break;
            case "todo":
                $scope.filtroTiempo = "ini";
                $scope.filtroAsignacion = "ini";
                $scope.filtroDesde = "";
                $scope.filtroHasta = "";
                $scope.fechaDesde = null;
                $scope.fechaHasta = null;
                $scope.filtroCartera = "todo";
                $scope.filtroProducto = "todo";
                $scope.filtroMarca = "todo";
                $scope.filtroRegion = "todo";
                $scope.filtroCanton = "todo";
                $scope.filtroFechaCarga = "todo";
                $scope.listas.filtroPeriodo = -1;
                $scope.cuantosAplicados = 0;
                break;
        }

        $scope.traerParametrizacion();
    };



    function convertirFecha(fechaStr){
        let partes = fechaStr.split('/');
        return Math.floor(
            new Date(
                partes[2],      // año
                partes[1] - 1,  // mes
                partes[0]       // día
            ).getTime() / 1000
        );
    }




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

    $scope.$watch('filtroMarca', function () {

        if ($scope.filtroMarca !== "todo") {
            
            
            $scope.filtrosAplicados.marca.titulo = "Marca: " + $scope.listas.marcas.find(element => element.id == $scope.filtroMarca).nombre;
            $scope.filtrosAplicados.marca.activo = true;
            $scope.cuantosAplicados++;
     
           

        } else {
            $scope.filtrosAplicados.marca.activo = false;
        }
    });

    $scope.$watch('filtroRegion', function () {

        if ($scope.filtroRegion !== "todo") {
            
            
            $scope.filtrosAplicados.region.titulo = "Región: " + $scope.listas.regiones.find(element => element.id == $scope.filtroRegion).nombre;
            $scope.filtrosAplicados.region.activo = true;
            $scope.cuantosAplicados++;
     
           

        } else {
            $scope.filtrosAplicados.region.activo = false;
        }
    });

    $scope.$watch('filtroCanton', function () {

        if ($scope.filtroCanton !== "todo") {
            
            
            $scope.filtrosAplicados.canton.titulo = "Cantón: " + $scope.listas.cantones.find(element => element.id == $scope.filtroCanton).nombre;
            $scope.filtrosAplicados.canton.activo = true;
            $scope.cuantosAplicados++;
     
           

        } else {
            $scope.filtrosAplicados.canton.activo = false;
        }
    });

     $scope.$watch('filtroFechaCarga', function () {

        if ($scope.filtroFechaCarga !== "todo") {
            
            
            $scope.filtrosAplicados.fechaCarga.titulo = "Fecha de Carga: " + $scope.listas.fechasCarga.find(element => element.id == $scope.filtroFechaCarga).nombre;
            $scope.filtrosAplicados.fechaCarga.activo = true;
            $scope.cuantosAplicados++;
     
           

        } else {
            $scope.filtrosAplicados.fechaCarga.activo = false;
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

