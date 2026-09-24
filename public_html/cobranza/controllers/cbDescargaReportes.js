app.controller("cbDescargaReportes", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', '$routeParams', 'myIntercom', function ($scope, avisos, simple, pedido, $timeout, $routeParams, myIntercom, $debounce) {
    window.Z_cbPanel = $scope;
    myIntercom.publica('ruta', menuModulo);

    $scope.cargando = false;
    $scope.fechaDAbierta = false;
    $scope.fechaDAbierta1 = false;
    //$scope.fechaHAbierta = false;
    //    var tfd = new Date();
    //    tfd.setHours(0,0,0,0);
    //    $scope.fechaDesde = tfd.getTime();
    //    $scope.fechaHasta = new Date().getTime();
    //$scope.fechaHasta = new Date().getTime();
    $scope.tipoReporte = {};
    $scope.tiposReporte = [];
    $scope.tipoReporteSeleccionado = '';
    $scope.recargarTabula = 0;
    $scope.carteras = [];
    $scope.cartera = {};
    $scope.campanias = [];
    $scope.campania= {};
    $scope.carteraLabel='Cartera';
    $scope.filtroFechas=true;
    var tfd = new Date();
    tfd.setHours(0, 0, 0, 0);

    $scope.selCartera = function (tipo) {
        $scope.cartera = tipo;
        /*if($scope.campaniasSN==1){
            return pedido.async({
                method: 'POST',
                url: '../cobranza/cbDescargaReportesCtrl.php?act=listaCampanias',
                data: {
                    cartera:$scope.cartera
                },
                cache: true
            }).then(function (respuesta) {
                console.log('response');
                console.log(respuesta);
                if (respuesta.resp !== undefined) {
                    $scope.campanias = respuesta.resp.listaCamp;
                } else {
                    console.log("Error inesperado al traer campanias");
                }
                $scope.cargando = false;
            });
        }*/
        console.log(tipo);
        console.log($scope.tiposReporte);
        for (var i = 0; i < $scope.tiposReporte.length; i++) {
            //console.log($scope.tiposReporte[i].value);
            if ($scope.tiposReporte[i].value === $scope.tipoReporteSeleccionado) {
                /*console.log('estamos por acá');
                console.log($scope.tiposReporte[i].campanias);*/
                if($scope.tiposReporte[i].campanias[0]===9999){
                    return pedido.async({
                        method: 'POST',
                        url: '../cobranza/cbDescargaReportesCtrl.php?act=listaCampanias',
                        data: {
                            cartera:$scope.cartera
                        },
                        cache: true
                    }).then(function (respuesta) {
                        console.log('response');
                        console.log(respuesta);
                        if (respuesta.resp !== undefined) {
                            $scope.campanias = respuesta.resp.listaCamp;
                        } else {
                            console.log("Error inesperado al traer campanias");
                        }
                        $scope.cargando = false;
                    });
                }else{
                    $scope.campanias = $scope.tiposReporte[i].campanias;
                }
            }
        }

    };
    $scope.cargarTipoReporte = function (tipo) {
        $scope.fechaDesde = tfd.getTime();
        $scope.fechaHasta = new Date().getTime();
        $scope.tipoReporteSeleccionado = tipo;
        $scope.carteras = [];
        $scope.cartera = {};
        $scope.campanias = [];
        /*$scope.carteraLabel='Cartera';
        console.log($scope.tiposReporte);
        $scope.campaniasSN=0;
        if(tipo=='Reporte de Eventos'){
            if(tipo=='Reporte de Eventos'){
                $scope.carteraLabel='Grupo';
                $scope.campaniasSN=1;
            }
            return pedido.async({
                method: 'POST',
                url: '../cobranza/cbDescargaReportesCtrl.php?act=listaCarteras',
                data: {
                    tipoCartera:$scope.carteraLabel
                },
                cache: true
            }).then(function (respuesta) {
                console.log('response');
                console.log(respuesta);
                if (respuesta.resp !== undefined) {
                    $scope.carteras = respuesta.resp.listaCarteras;
                } else {
                    console.log("Error inesperado al traer campanias");
                }
                //$scope.cargando = false;
            });
        }else{
            
            for (var i = 0; i < $scope.tiposReporte.length; i++) {
                if ($scope.tiposReporte[i].value === tipo) {
                    $scope.carteras = $scope.tiposReporte[i].cartera;
                }
            }
        }*/

        for (var i = 0; i < $scope.tiposReporte.length; i++) {
            if ($scope.tiposReporte[i].value === tipo) {
                console.log($scope.tiposReporte[i]);
                if(angular.isDefined($scope.tiposReporte[i].filtroFechas)){
                    console.log('essiste');
                    if($scope.tiposReporte[i].filtroFechas=='0'){
                        $scope.filtroFechas=false;
                    }
                }
                if($scope.tiposReporte[i].cartera[0]===9999){
                    $scope.carteraLabel='Cartera';
                    return pedido.async({
                        method: 'POST',
                        url: '../cobranza/cbDescargaReportesCtrl.php?act=listaCarteras',
                        data: {
                        },
                        cache: true
                    }).then(function (respuesta) {
                        console.log('response');
                        console.log(respuesta);
                        if (respuesta.resp !== undefined) {
                            $scope.carteras = respuesta.resp.listaCarteras;
                        } else {
                            console.log("Error inesperado al traer carteras");
                        }
                    });
                }else{
                    $scope.carteras = $scope.tiposReporte[i].cartera;
                }
                
            }
        }
        console.log($scope.carteras);
    };

    $scope.actualizarDatos = function () {
        $scope.cargando = true;
        $scope.recargarTabula++;
    };

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.eliminarReporte = function (item) {
        avisos.setFuncion(function () {

            $scope.cargando = true;

            return pedido.async({
                method: 'POST',
                url: '../cobranza/cbDescargaReportesCtrl.php?act=eliminarReporte',
                data: {
                    id: item.rep_id
                },
                cache: true
            }).then(function (response) {
                if (response.respuesta !== undefined) {
                    avisos.alerta("Aviso", "Eliminado con éxito");
                    $scope.actualizarDatos();
                } else {
                    if (response.error !== undefined) {
                        avisos.alerta("Error", response.error);
                    } else {
                        avisos.alerta("Error", "Error inesperado al eliminar");
                    }
                }
                $scope.cargando = false;
            });
        });

        avisos.confirma("Confirme", "Seguro de eliminar?");
    };


    $scope.generarReporte = function () {
        if ($scope.tipoReporteSeleccionado === "Cartera" || $scope.tipoReporteSeleccionado === "Cancelaciones" || $scope.tipoReporteSeleccionado === "Recaudaciones" || $scope.tipoReporteSeleccionado === "Condonaciones") {
            if ($scope.carteras.length === 0 || $scope.cartera.name === undefined) {
                avisos.alerta("Error", "Seleccione una cartera");
                return;
            }
        }
        if ($scope.tipoReporteSeleccionado === "Cartera") {
            $scope.fechaHasta = $scope.fechaDesde;
        }

        if ($scope.tipoReporteSeleccionado === '') {
            avisos.alerta("Error", "Seleccione el tipo de reporte a generar");
            return;
        }
        if ($scope.fechaDesde <= 0) {
            avisos.alerta("Error", "Seleccione la fecha desde la cual generar el reporte");
            return;
        }

        if ($scope.fechaHasta <= 0) {
            avisos.alerta("Error", "Seleccione la fecha hasta la cual generar el reporte");
            return;
        }

        var f = $scope.fechaDesde;
        try {
            f = f.getTime();
        } catch (e) {
            f = tfd.getTime();
        }

        var g = $scope.fechaHasta;
        try {
            g = g.getTime();
        } catch (e) {
            g = new Date().getTime();
        }

        if (g < f) {
            avisos.alerta("Error", "Fecha hasta mayor a fecha desde");
            return;
        }

        /*if ($scope.fechaHasta<=0){
         avisos.alerta("Error", "Seleccione la fecha hasta la cual generar el reporte");
         return;
         }*/
        /*
         //fecha desde no puede ser mayor a la de hasta
         if ($scope.fechaDesde>$scope.fechaHasta){
         avisos.alerta("Error", "Fecha desde no puede ser posterior a fecha hasta");
         return;
         }*/

        avisos.setFuncion(function () {
            $scope.cargando = true;
            var f = $scope.fechaDesde;
            try {
                f = f.getTime();
            } catch (e) {
                f = new Date().getTime();
            }

            var g = $scope.fechaHasta;
            try {
                g = g.getTime();
            } catch (e) {
                g = new Date().getTime();
            }

            return pedido.async({
                method: 'POST',
                url: '../cobranza/cbDescargaReportesCtrl.php?act=generarReporte',
                data: {
                    tipo: $scope.tipoReporteSeleccionado,
                    fechaDesde: f,
                    fechaHasta: g,
                    cartera: $scope.cartera,
                    campania:$scope.campania
                    //fechaHasta:$scope.fechaHasta
                },
                cache: true
            }).then(function (response) {
                if (response.respuesta !== undefined) {
                    //avisos.alerta("Aviso", "Se inició el proceso de generación");
                    $scope.actualizarDatos();
                    $scope.cartera = {};
                    $scope.carteras = [];
                    $scope.campania = {};
                    $scope.campanias = [];
                    //encerar formulario
                    $scope.tipoReporte = {};
                    $scope.tipoReporteSeleccionado = '';
                    $scope.fechaDesde = new Date().getTime();
                    $scope.fechaHasta = new Date().getTime();
                } else {
                    if (response.error !== undefined) {
                        avisos.alerta("Error", response.error);
                    } else {
                        avisos.alerta("Error", "Error inesperado al generar las tramas");
                    }
                }
                $scope.cargando = false;
            });
        });

        avisos.confirma("Confirme", "Se generará el reporte, desea continuar?");

    };

    $scope.generaLinkLog = function (tipo, urlBase) {
        var url = "";
        switch (tipo) {
            case "Cartera":
                url = urlBase + "ETL/logs/ReporteCartera.log";
                break;
            case "Cancelaciones":
                url = urlBase + "ETL/logs/ReporteCancelaciones.log";
                break;
            case "Recaudaciones":
                url = urlBase + "ETL/logs/ReporteReacudaciones.log";
                break;
            case "Saldo de Cartera":
                url = urlBase + "ETL/logs/ReporteCierreGeneral.log";
                break;
            case "Cartera Vencida":
                url = urlBase + "ETL/logs/ReporteCarteraVencida.log";
                break;
            case "Condonaciones":
                url = urlBase + "ETL/logs/ReporteCondonaciones.log";
                break;
            case "Reversos":
                url = urlBase + "ETL/logs/ReportePagosReversos.log";
                break;
            case "Cartera por Cuotas":
                url = urlBase + "ETL/logs/ReporteCarteraCuota.log";
                break;
            case "Pagos Cartera Administrada":
                url = "https://portcoll-qa.zona-link.com//ETL/logs/jobReportePagosCA.log";
                break; 
            case "Reporte de Eventos":
                url = urlBase + "ETL/logs/Job_reporteEventos.log";
                break;
            case "Reporte de Rubros":
                url = urlBase + "ETL/logs/jobReporteRubros.log";
                break;    
        }
        return url;
    };

    $scope.traerCatalogos = function () {
        $scope.cargando = true;
        return pedido.async({
            method: 'POST',
            url: '../cobranza/cbDescargaReportesCtrl.php?act=traerCatalogos',
            data: {
            },
            cache: true
        }).then(function (response) {
            if (response.respuesta !== undefined) {
                $scope.tiposReporte = response.respuesta.reportes;
            } else {
                console.log("Error inesperado al traer catalogos");
            }
            $scope.cargando = false;
        });
    };
    $scope.selCampania = function (tipo){
        $scope.campania = tipo;
    }

}]);
//_FIN_DE_ARCHIVO
