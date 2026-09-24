app.controller("cmReporteAgenteVirtualWhatsapp", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.recargarTabula = 0;
    $scope.mostrarTabulaDetalle = false;
    $scope.dataGraficos = [];

    $scope.listas = {
        "lotes": [],
        "carteras": [],
        "campanias": [],
        "proveedores": [],
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
    $scope.filtroCampania = "todo";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";
    $scope.filtroLote = "todo";
    $scope.filtroProveedor = "todo";

    $scope.permisos = {
        "soySupervisor": false,
        "soyObservador": true,
        "soyDesarrollo": false
    };

    $scope.titulo = "Este día";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.verDetalle = {
        activo: false,
        titulo: "",
        evento: {},
        detalle: {
        },
    };

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

    $scope.opcionesPie = {
        chart: {
            type: 'pieChart',
            height: 200,
            donut: true,
            x: function (d) {
                return d.key + " " + d.porc + "%";;
            },
            y: function (d) {
                return d.y;
            },
            duration: 500,
            interactive: true,
            showLegend: false,
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
            enable: false,
            text: "Programadas"
        }
    };

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
        $scope.recargarTabula++;
    };

    $scope.getMonday = function (d) {
        d = new Date(d);
        var day = d.getDay(),
            diff = d.getDate() - day + (day == 0 ? -6 : 1); // adjust when day is sunday
        return new Date(d.setDate(diff));
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
        }
    };

    $scope.traerParametrizacion = function () {
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
            case "mes":
                fd = ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
                $scope.titulo = "Este mes - " + fd;
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
            url: '../canalesMasivos/cmReporteAgenteVirtualWhatsappCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroProducto: $scope.filtroProducto,
            }
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.lotes !== undefined) {
                    $scope.listas.lotes = response.lotes;
                }
                if (response?.campanias !== undefined) {
                    $scope.listas.campanias = response.campanias;
                }
                if (response?.carteras !== undefined) {
                    $scope.listas.carteras = response.carteras;
                }
                if (response?.proveedores !== undefined) {
                    $scope.listas.proveedores = response.proveedores;
                }
                if (response?.productos !== undefined) {
                    $scope.listas.productos = response.productos;
                }
                if (response?.permisos !== undefined) {
                    $scope.permisos = response.permisos;
                }
                if ($scope.filtroTiempo !== "" || $scope.filtroLote !== "" || $scope.filtroCampania !== "" || $scope.filtroCartera !== "" || $scope.filtroProducto !== "" || $scope.filtroProveedor !== "") {
                    $scope.actualizarTabula();
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.cargando = false;
            $scope.actualizarAutomaticamente();
        });

    };

    let toa = null;
    $scope.actualizarAutomaticamente = function () {
        window.clearTimeout(toa);
        if (!$scope.cargando) {
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
    };

    $scope.traerDetalleGrafico = function () {
        $scope.cargandoGraficos = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualWhatsappCtrl.php?act=detalleGraficos',
            data: {
                filtroCampania: $scope.filtroCampania,
                filtroCartera: $scope.filtroCartera,
                filtroLote: $scope.filtroLote,
                filtroProveedor: $scope.filtroProveedor,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroProducto: $scope.filtroProducto,
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.dataGraficos = response.respuesta;
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

    $scope.setTiempoActualizar = function () {
        window.localStorage.setItem("tiempoActualiza", $scope.tiempoActualizar.toString());
        window.clearTimeout(toa);
        $scope.actualizarAutomaticamente();
    };

    $scope.mostrarDetalle = function (pos, item) {
        console.log(item);
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualWhatsappCtrl.php?act=obtenerDetalleChat',
            data: {
                idChat: item.ws_idConversacion,
                id: item.id
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.verDetalle.titulo = "Detalle del chat con " + item.ws_nombre + " - " + item.ws_cedula;
                let nuevo = Object.assign(item, response.respuesta);
                $scope.verDetalle.detalle = nuevo;
                $scope.verDetalle.evento = pos;
                $scope.verDetalle.activo = true;
                $scope.verDetalle.detalle.mostrarTranscripcion = true;
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

    $scope.traerParametrizacion();
    $scope.traerDetalleGrafico();

}]);

//_FIN_DE_ARCHIVO

