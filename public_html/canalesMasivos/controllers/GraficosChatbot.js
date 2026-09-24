app.controller("GraficosChatbot", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$timeout', '$interval','$sce', function ($scope, avisos, simple, pedido, myIntercom, $timeout, $interval,$sce) {
        window.Z_cbPanel = $scope;
        //informamos la ruta al encabezado
        myIntercom.publica('ruta', menuSuperior);
        $scope.procesando = false;
     
        $scope.cargarDatos = function(){
            $scope.procesando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/GraficosAnalisisSentimientosCtrl.php?act=graficarChatbot',
                data: {
                    
                },
                cache: true
            }).then(function (response) {
                $scope.procesando = false;
                if (angular.isDefined(response.data)){
                    $scope.dataPreguntasSinRespuesta = response.data.sinRespuesta;
                    $scope.dataPreguntasConRespuesta = response.data.conRespuesta;
                    $scope.dataFuenteDatos = response.data.dataFuenteDatos;
                    $scope.imagenMapa = $sce.trustAsHtml(response.data.dataUbicacion);
                    $scope.dataEdad = response.data.dataEdad;
                    $scope.dataHora = response.data.dataHora;
                }                
            });
        };
     
        $scope.imagenMapa="";
     
        //por hora
        $scope.dataHora = [];
        $scope.optionsHora = {
            chart: {
                showControls: false,
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
                                    label = "00:00-00:59";
                                    break;
                                case 1:
                                    label = "01:00-01:59";
                                    break;
                                case 2:
                                    label = "02:00-02:59";
                                    break;
                                case 3:
                                    label = "03:00-03:59";
                                    break;
                                case 4:
                                    label = "04:00-04:59";
                                    break;
                                case 5:
                                    label = "05:00-05:59";
                                    break;
                                case 6:
                                    label = "06:00-06:59";
                                    break;
                                case 7:
                                    label = "07:00-07:59";
                                    break;
                                case 8:
                                    label = "08:00-08:59";
                                    break;
                                case 9:
                                    label = "09:00-09:59";
                                    break;
                                case 10:
                                    label = "10:00-10:59";
                                    break;
                                case 11:
                                    label = "11:00-11:59";
                                    break;
                                case 12:
                                    label = "12:00-12:59";
                                    break;
                                case 13:
                                    label = "13:00-13:59";
                                    break;
                                case 14:
                                    label = "14:00-14:59";
                                    break;
                                case 15:
                                    label = "15:00-15:59";
                                    break;
                                case 16:
                                    label = "16:00-16:59";
                                    break;
                                case 17:
                                    label = "17:00-17:59";
                                    break;
                                case 18:
                                    label = "18:00-18:59";
                                    break;
                                case 19:
                                    label = "19:00-19:59";
                                    break;
                                case 20:
                                    label = "20:00-20:59";
                                    break;
                                case 21:
                                    label = "21:00-21:59";
                                    break;
                                case 22:
                                    label = "22:00-22:59";
                                    break;
                                case 23:
                                    label = "23:00-23:59";
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
     
        //Lineas edad
        $scope.dataEdad = [];
        $scope.optionsEdad = {
            chart: {
                showControls: false,
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
     
        //captados
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
                    rotateLabels: 45
                },
                yAxis: {
                    axisLabelDistance: -10
                },
                noData: "No hay datos"
            }
        };
        
        //preguntas
        $scope.dataPreguntasSinRespuesta = [];
        $scope.dataPreguntasConRespuesta = [];
        $scope.optionsPreguntasSinRespuesta = {
            chart: {
                type: 'multiBarHorizontalChart',
                height: 450,
                x: function(d){return d.label;},
                y: function(d){return d.value;},
                showControls: false,
                showValues: true,
                duration: 800,
                showXAxis : true,
                valueFormat: function(d){ return d3.format('')(d); },
                xAxis: {
                    showMaxMin: false
                },
                yAxis: {
                    axisLabel: 'Cantidad de preguntas',
                    tickFormat: function(d){
                        return d3.format(',.2f')(d);
                    }
                },
                margin: {
                    top: 30,
                    right: 20,
                    bottom: 50,
                    left: 320 //Valor por defecto 60
                },
                noData: "No hay datos"
            }
        };

    }]);
//_FIN_DE_ARCHIVO