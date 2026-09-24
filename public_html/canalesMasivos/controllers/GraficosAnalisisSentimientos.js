app.controller("GraficosAnalisisSentimientos", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$timeout', '$interval','$sce', function ($scope, avisos, simple, pedido, myIntercom, $timeout, $interval,$sce) {
        window.Z_cbPanel = $scope;
        //informamos la ruta al encabezado
        myIntercom.publica('ruta', menuSuperior);
        $scope.procesando = false;
        
        $scope.totalRegistros = 0;
        $scope.totalHombres = 0;
        $scope.totalMujeres = 0;
        $scope.totalPositivos = 0;
        $scope.totalNegativos = 0;
        $scope.totalNeutral = 0;

         $scope.imagenMapa="";

        $scope.cargarDatos = function(){
            $scope.procesando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/GraficosAnalisisSentimientosCtrl.php?act=graficar',
                data: {
                    
                },
                cache: true
            }).then(function (response) {
                $scope.procesando = false;
                if (angular.isDefined(response.data)){
                    $scope.totalRegistros= response.data.totalRegistros;                    
                    $scope.dataPieGeneral = response.data.dataPieGeneral;
                    $scope.dataPieGeneralHombres = response.data.dataPieGeneralHombres;
                    $scope.dataPieGeneralMujeres = response.data.dataPieGeneralMujeres;
                    $scope.totalHombres = response.data.totalHombres;
                    $scope.totalMujeres = response.data.totalMujeres;
                    $scope.optionsPieGeneralMujeres.title.text = "Femenino \r\n(" + response.data.totalMujeres + " de " + $scope.totalRegistros + ")";
                    $scope.optionsPieGeneralHombres.title.text = "Masculino \r\n(" + response.data.totalHombres + " de " + $scope.totalRegistros + ")";
                    $scope.dataFuenteDatos = response.data.dataFuenteDatos;
                    $scope.dataEdad = response.data.dataEdad;
                    $scope.dataFecha = response.data.dataFecha;
                    
                    $scope.totalNeutral = response.data.porcentajePolaridad["NEUTRAL"]["valor"];
                    $scope.totalNegativos = response.data.porcentajePolaridad["NEGATIVO"]["valor"];
                    $scope.totalPositivos = response.data.porcentajePolaridad["POSITIVO"]["valor"];
                    
                    var configPositivo = liquidFillGaugeDefaultSettings();
                    configPositivo.circleColor = "green";
                    configPositivo.textColor = "green";
                    configPositivo.waveTextColor = "black";
                    configPositivo.waveColor = "green";
                    configPositivo.circleThickness = 0.2;
                    configPositivo.textVertPosition = 0.2;
                    configPositivo.waveAnimateTime = 1000;
                    configPositivo.displayPercent = true;
                    configPositivo.displayMoney = false;
                    configPositivo.maxValue = 100;
                    configPositivo.textSize =0.75;
                    $scope.gaugeMeta = loadLiquidFillGauge("gaugePositivo", response.data.porcentajePolaridad["POSITIVO"]["porcentaje"], configPositivo);    
                    var configNegativo = liquidFillGaugeDefaultSettings();
                    configNegativo.circleColor = "red";
                    configNegativo.textColor = "red";
                    configNegativo.waveTextColor = "black";
                    configNegativo.waveColor = "red";
                    configNegativo.circleThickness = 0.2;
                    configNegativo.textVertPosition = 0.2;
                    configNegativo.waveAnimateTime = 1000;
                    configNegativo.displayPercent = true;
                    configNegativo.displayMoney = false;
                    configNegativo.maxValue = 100;
                    configNegativo.textSize =0.75;
                    $scope.gaugeMeta = loadLiquidFillGauge("gaugeNegativo", response.data.porcentajePolaridad["NEGATIVO"]["porcentaje"], configNegativo);    
                    var configNeutral = liquidFillGaugeDefaultSettings();
                    configNeutral.circleColor = "gray";
                    configNeutral.textColor = "gray";
                    configNeutral.waveTextColor = "black";
                    configNeutral.waveColor = "grey";
                    configNeutral.circleThickness = 0.2;
                    configNeutral.textVertPosition = 0.2;
                    configNeutral.waveAnimateTime = 1000;
                    configNeutral.displayPercent = true;
                    configNeutral.displayMoney = false;
                    configNeutral.maxValue = 100;
                    configNeutral.textSize =0.75;
                    $scope.gaugeMeta = loadLiquidFillGauge("gaugeNeutral", response.data.porcentajePolaridad["NEUTRAL"]["porcentaje"], configNeutral); 
                    
                    console.log(response.data.dataUbicacion);
                    $scope.imagenMapa = $sce.trustAsHtml(response.data.dataUbicacion);
                }
                if (angular.isDefined((response.error))){
                    $scope.porMesError = response.error;
                }
            });
        };

        //fecha
        $scope.dataFecha = [];
        $scope.optionsFecha = {
            chart: {
                type: 'cumulativeLineChart',
                height: 450,
                margin : {
                    top: 20,
                    right: 20,
                    bottom: 60,
                    left: 65
                },
                x: function(d){ return d[0]; },
                y: function(d){ return d[1]; },
                average: function(d) { return d.mean/100; },

                color: d3.scale.category10().range(),
                duration: 300,
                useInteractiveGuideline: true,
                clipVoronoi: false,

                xAxis: {
                    tickFormat: function(d) {
                        return d3.time.format('%d/%m/%Y')(new Date(d));
                    },
                    showMaxMin: false,
                    staggerLabels: true
                },

                yAxis: {
                    staggerLabels: true,
                    tickFormat: function(d){
                        return d;
                    }
                }
            }
        };

        //Lineas edad
        $scope.dataEdad = [];
        $scope.optionsEdad = {
            chart: {
                type: 'cumulativeLineChart',
                height: 300,
                margin : {
                    top: 20,
                    right: 20,
                    bottom: 60,
                    left: 65
                },
                x: function(d){ return d[0]; },
                y: function(d){ return d[1]; },
                average: function(d) { return d.mean/100; },

                color: d3.scale.category10().range(),
                duration: 300,
                useInteractiveGuideline: false,
                clipVoronoi: false,

                xAxis: {
                    showMaxMin: true,
                        staggerLabels: false,
                        rotateLabels: -30,
                        tickFormat: function(d) {
                            var label="";
                            switch (d){
                                case 0:
                                    label = "0-10 años";
                                    break;
                                case 1:
                                    label = "11-20 años";
                                    break;
                                case 2:
                                    label = "21-30 años";
                                    break;
                                case 3:
                                    label = "31-40 años";
                                    break;
                                case 4:
                                    label = "41-50 años";
                                    break;
                                case 5:
                                    label = "51-60 años";
                                    break;
                                case 6:
                                    label = "61-70 años";
                                    break;
                                case 7:
                                    label = "71-80 años";
                                    break;
                                case 8:
                                    label = "81-90 años";
                                    break;
                                case 9:
                                    label = "91+ años";
                                    break;
                            };
                            return label;
                        }
                },

                yAxis: {
                    staggerLabels: true,
                    tickFormat: function(d){
                        return d;
                    }
                }
            }
        };
        //Barra fuente datos
        $scope.dataFuenteDatos=[];
        $scope.optionsFuenteDatos = {
            chart: {
                type: 'discreteBarChart',
                height: 300,
                margin : {
                    top: 20,
                    right: 40,
                    bottom: 80,
                    left: 45
                },
                x: function(d){
                    return d.label;
                },
                y: function(d){
                    return d.value + (1e-10);
                },
                showValues: true,
                valueFormat: function(d){
                    return d3.format('.0f')(d);
                    //d.toFixed(0);
                },
                duration: 500,
                xAxis: {
                    axisLabel: 'Origen',
                    rotateLabels: 45
                },
                yAxis: {
                    axisLabel: 'Cantidad',
                    axisLabelDistance: -10
                },
                noData: "No hay datos"
            },
            title: {
                enable: true,
                text: "Origen"
            }
        };

        //PIE por sexo
        $scope.dataPieGeneralHombres = [];
        $scope.dataPieGeneralMujeres = [];
        $scope.optionsPieGeneralHombres = {
            chart: {
                type: 'pieChart',
                height: 300,
                x: function (d) {
                    return d.key;
                },
                y: function (d) {
                    //return d3.format('.2%')(d.y);
                    return d.y;
                },
                duration: 500,
                legend: {
                    margin: {
                        top: 5,
                        right: 35,
                        bottom: 5,
                        left: 0
                    }
                },
                showLegend: false,
                tooltip: {
                    enabled: true,
                    valueFormatter: function(d, i) {
                        var t = (d * 100) / $scope.totalHombres;
                        return t.toFixed(2) + "%";
                                    },
                    headerFormatter:function(d) {
                                        return d;
                                    },
                    keyFormatter:   function(d) {
                                        return d;
                                    }
                },
                noData: "No hay datos"
            },
            title: {
                enable: true,
                text: "Hombres"
            }
        };
        $scope.optionsPieGeneralMujeres = {
            chart: {
                type: 'pieChart',
                height: 300,
                x: function (d) {
                    return d.key;
                },
                y: function (d) {
                    //return d3.format('.2%')(d.y);
                    return d.y;
                },
                duration: 500,
                legend: {
                    margin: {
                        top: 5,
                        right: 35,
                        bottom: 5,
                        left: 0
                    }
                },
                showLegend: false,
                tooltip: {
                    enabled: true,
                    valueFormatter: function(d, i) {
                        var t = (d * 100) / $scope.totalMujeres;
                        return t.toFixed(2) + "%";
                                    },
                    headerFormatter:function(d) {
                                        return d;
                                    },
                    keyFormatter:   function(d) {
                                        return d;
                                    }
                },
                noData: "No hay datos"
            },
            title: {
                enable: true,
                text: "Mujeres"
            }
        };

        //PIE General
        $scope.dataPieGeneral = [];
        $scope.configPieGeneral = {
            visible: true, // default: true
            extended: false, // default: false
            disabled: false, // default: false
            autorefresh: true, // default: true
            refreshDataOnly: false, // default: true
            deepWatchOptions: true, // default: true
            deepWatchData: false, // default: false
            deepWatchConfig: true, // default: true
            debounce: 10 // default: 10
        };
        $scope.optionsPieGeneral = {
            chart: {
                type: 'pieChart',
                height: 300,
                x: function (d) {
                    return d.key;
                },
                y: function (d) {
                    //return d3.format('.2%')(d.y);
                    return d.y;
                },
                duration: 500,
                legend: {
                    margin: {
                        top: 5,
                        right: 35,
                        bottom: 5,
                        left: 0
                    }
                },
                showLegend: false,
                tooltip: {
                    enabled: true,
                    valueFormatter: function(d, i) {
                        var t = (d * 100) / $scope.totalRegistros;
                        return t.toFixed(2) + "%";
                                    },
                    headerFormatter:function(d) {
                                        return d;
                                    },
                    keyFormatter:   function(d) {
                                        return d;
                                    }
                },
                noData: "No hay datos"
            }
        };
        

    }]);
//_FIN_DE_ARCHIVO