app.controller("cmDetalleGestionBase", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);

    $scope.cargando = true;
    $scope.cargandoListas = false;

    $scope.listas = {
        "carteras": [],
        "productos": [],
        "fechasCarga": [],
        "filtroPeriodo": -1
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
        "cartera": {
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

    $scope.totales = {
        "gestion": [],
        "tipificacion": [],
        "horarios": [],
        "intensidad": []
    };

    $scope.busqueda = {
        "base": "",
        "gestionados": "",
        "sinGestion": ""
    };

    $scope.mostrarTabla = {
        "base": true,
        "gestionados": true,
        "sinGestion": true
    };

    function recargarTabla(tipo) {
        $scope.mostrarTabla[tipo] = false;
        $timeout(function () {
            $scope.mostrarTabla[tipo] = true;
        }, 0);
    }

    function recargarTodasLasTablas() {
        recargarTabla('base');
        recargarTabla('gestionados');
        recargarTabla('sinGestion');
    }

    $scope.titulo = "";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "ini";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";

    // Con canal elegido, "Gestionados"/"Sin Gestión" usan la MEJOR GESTIÓN
    // DE ESE CANAL (cubAV_mejorGestion), no la mejor gestión general.
    $scope.filtroCanal = "todo";

    // Mismas llaves que se mandan al backend: AV, EMAIL, WHATSAPP.
    $scope.nombresCanal = {
        "AV": "AV",
        "EMAIL": "EMAIL",
        "WHATSAPP": "WHATSAPP"
    };

    // A diferencia del canal, Fecha de Carga SÍ aplica aquí: cubAV_fechaCarga
    // es un dato nativo de cuAsignacionesGestionVentas (la misma colección
    // que alimenta la Base), así que este KPI sí debe filtrarse por ella.
    $scope.filtroFechaCarga = "todo";

    // Al entrar, tomamos los filtros que venían activos en el dashboard principal . Si no vienen, se usan los valores por defecto.
    (function leerFiltrosDeUrl() {
        var qp = $location.search();

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

    // Volver al dashboard principal, sin perder los filtros vigentes.
    $scope.volver = function () {
        var params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/dashboardGestionesVentas" + params;
    };

    $scope.validarFiltros = function () {
        $scope.traerParametrizacion();
    };

    $scope.aplicarRangoFechas = function () {

        if (typeof $scope.fechaDesde === "object" && typeof $scope.fechaHasta === "object") {

            $scope.filtroDesde = Math.floor($scope.fechaDesde.getTime() / 1000);
            $scope.filtroHasta = Math.floor($scope.fechaHasta.getTime() / 1000);

            if ($scope.filtroHasta < $scope.filtroDesde) {
                avisos.alerta("Error", "Rango de fechas incorrecto");
                return;
            }

            $scope.filtroTiempo = "rango";

            var dd = new Date($scope.fechaDesde.getTime());
            var dh = new Date($scope.fechaHasta.getTime());
            var fd = ("0" + dd.getDate()).slice(-2) + "/" + ("0" + (dd.getMonth() + 1)).slice(-2) + "/" + dd.getFullYear();
            var fh = ("0" + dh.getDate()).slice(-2) + "/" + ("0" + (dh.getMonth() + 1)).slice(-2) + "/" + dh.getFullYear();
            $scope.titulo = "Del " + fd + " al " + fh;

            $scope.traerParametrizacion();
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

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerParametrizacion',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroTiempo: $scope.filtroTiempo,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroCanal: $scope.filtroCanal,
                filtroFechaCarga: $scope.filtroFechaCarga
            }
        }).then(function (response) {

            if (response?.error === 1) {
                avisos.alerta("Error", response.mensaje);
            }

            if (response !== undefined) {
                if (response?.carteras !== undefined) {
                    $scope.listas.carteras = response.carteras;
                    actualizarBadgeCartera();
                }
                if (response?.productos !== undefined) {
                    $scope.listas.productos = response.productos;
                    actualizarBadgeProducto();
                }
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
                $scope.traerTotales();
                recargarTodasLasTablas();
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
                $scope.cargando = false;
            }
        });
    };

    // Endpoint propio de esta pantalla (no el "obtenerTotales" compartido
    // con el dashboard principal), para que el canal SOLO afecte aqui.
    $scope.traerTotales = function () {

        $scope.cargandoListas = true;

        var params = "&filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        pedido.async({
            method: 'GET',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerTotalesGestionBase' + params
        }).then(function (response) {

            if (response.totales !== undefined) {
                $scope.totales = response.totales;
            }

            $scope.cargando = false;
            $scope.cargandoListas = false;
        });
    };

    $scope.buscarEnLista = function (tipo) {
        recargarTabla(tipo);
    };

    $scope.exportandoExcel = {};

    // Exporta a Excel TODOS los registros que cumplen los filtros vigentes
    // de la lista indicada (no solo la página que se ve en la tabla).
    // "tipo" es el act del backend (listaBaseAsignada/ClientesGestionados/
    // ClientesSinGestion) y "busqueda" es el texto buscado en esa lista.
    $scope.exportarExcel = function (tipo, busqueda) {
        $scope.exportandoExcel[tipo] = true;

        var params = "&tipo=" + tipo +
            "&filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga +
            "&buscarCliente=" + (busqueda || "");

        pedido.async({
            method: 'GET',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=exportarDetalle' + params
        }).then(function (response) {
            if (response && response.ruta) {
                window.open(response.ruta, '_blank');
            } else {
                avisos.alerta("Error", (response && response.mensaje) || "No se pudo generar el archivo");
            }
            $scope.exportandoExcel[tipo] = false;
        });
    };


    $scope.quitarFiltro = function (filtro) {

        switch (filtro) {
            case "tiempo":
                $scope.filtroTiempo = "ini";
                $scope.cuantosAplicados--;
                break;
            case "cartera":
                $scope.filtroCartera = "todo";
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

    $scope.$watch('filtroTiempo', function () {
        if ($scope.filtroTiempo !== "ini") {
            $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
            if ($scope.titulo !== '' && !$scope.filtrosAplicados.tiempo.activo) {
                $scope.cuantosAplicados++;
            }
            if ($scope.titulo !== '') {
                $scope.filtrosAplicados.tiempo.activo = true;
            }
        } else {
            $scope.filtrosAplicados.tiempo.activo = false;
        }
    });

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

}]);

//_FIN_DE_ARCHIVO