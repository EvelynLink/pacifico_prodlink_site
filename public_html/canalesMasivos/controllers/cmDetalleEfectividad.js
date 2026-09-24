app.controller("cmDetalleEfectividad", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', '$sce', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter, $sce) {

    myIntercom.publica('ruta', menuSuperior);

    $scope.cargando = true;
    $scope.cargandoTotales = false;

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

    $scope.titulo = "";
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "ini";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";
    $scope.filtroCanal = "todo";
    $scope.filtroFechaCarga = "todo";
    $scope.filtroLeido = "todo";

    // Nombres a mostrar en el badge, con las MISMAS llaves y el mismo texto
    // que se guardan en cubAV_canal / se mandan al backend: AV, EMAIL, WHATSAPP.
    $scope.nombresCanal = {
        "AV": "AV",
        "EMAIL": "EMAIL",
        "WHATSAPP": "WHATSAPP"
    };

    $scope.totales = {
        "gestionados": 0,
        "contactados": 0,
        "interesados": 0,
        "efectivos": 0
    };

    $scope.busqueda = "";

    // Modal de audio/transcripcion de una llamada (subset de cmReporteAgenteVirtual).
    // El texto fijo del titulo va en el HTML (con &oacute;); aqui solo se manda el nombre.
    $scope.verDetalleLlamada = {
        activo: false,
        nombreCliente: "",
        detalle: { audio: "", transcripcion: [] }
    };

    $scope.mostrarDetalleLlamada = function (item) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerAudioTranscripcionLlamada',
            data: { avId: item.avId }
        }).then(function (response) {
            if (response && response.respuesta && response.respuesta.error === undefined) {
                $scope.verDetalleLlamada.nombreCliente = item.nombreCliente;
                $scope.verDetalleLlamada.detalle = response.respuesta;
                $scope.verDetalleLlamada.detalle.transcripcion = $scope.verDetalleLlamada.detalle.transcripcion
                    ? JSON.parse(window.atob($scope.verDetalleLlamada.detalle.transcripcion))
                    : [];
                $scope.verDetalleLlamada.activo = true;
            } else {
                avisos.alerta("Error", (response && response.respuesta && response.respuesta.error) || "No se pudo obtener el detalle de la llamada");
            }
            $scope.cargando = false;
        });
    };

    // Modal de transcripcion de un chat de WhatsApp (subset de cmReporteAgenteVirtualWhatsapp).
    $scope.verDetalleWhatsapp = {
        activo: false,
        nombreCliente: "",
        detalle: { transcripcion: [] }
    };

    $scope.mostrarDetalleWhatsapp = function (item) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerTranscripcionWhatsapp',
            data: { avId: item.avId }
        }).then(function (response) {
            if (response && response.respuesta && response.respuesta.error === undefined) {
                $scope.verDetalleWhatsapp.nombreCliente = item.nombreCliente;
                $scope.verDetalleWhatsapp.detalle = response.respuesta;
                $scope.verDetalleWhatsapp.detalle.transcripcion = $scope.verDetalleWhatsapp.detalle.transcripcion
                    ? JSON.parse(window.atob($scope.verDetalleWhatsapp.detalle.transcripcion))
                    : [];
                $scope.verDetalleWhatsapp.activo = true;
            } else {
                avisos.alerta("Error", (response && response.respuesta && response.respuesta.error) || "No se pudo obtener el detalle del chat");
            }
            $scope.cargando = false;
        });
    };

    // Modal de detalle de un correo (body html), a partir de cbEnvioMails.
    $scope.verDetalleCorreo = {
        activo: false,
        nombreCliente: "",
        detalle: { html: "", asunto: "", destinatario: "" }
    };

    $scope.mostrarDetalleCorreo = function (item) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerDetalleCorreo',
            data: { avId: item.avId }
        }).then(function (response) {
            if (response && response.respuesta && response.respuesta.error === undefined) {
                $scope.verDetalleCorreo.nombreCliente = item.nombreCliente;
                $scope.verDetalleCorreo.detalle = response.respuesta;
                $scope.verDetalleCorreo.detalle.html = $scope.verDetalleCorreo.detalle.html
                    ? $sce.trustAsHtml(window.atob($scope.verDetalleCorreo.detalle.html))
                    : "";
                $scope.verDetalleCorreo.activo = true;
            } else {
                avisos.alerta("Error", (response && response.respuesta && response.respuesta.error) || "No se pudo obtener el detalle del correo");
            }
            $scope.cargando = false;
        });
    };

    $scope.tabs = [
        { key: "gestionados", label: "Gestionados", act: "listaGestionados", caption: "gestionados", icono: "fa-check-square-o" },
        { key: "contactados", label: "Contactados", act: "listaContactados", caption: "contactados", icono: "fa-phone" },
        { key: "interesados", label: "Interesados", act: "listaInteresados", caption: "interesados", icono: "fa-star" },
        { key: "efectivos", label: "Efectivos", act: "listaEfectivos", caption: "efectivos", icono: "fa-check" }
    ];

    $scope.tabActivo = $scope.tabs[0];
    $scope.actActual = $scope.tabActivo.act;

    $scope.mostrarTabla = true;

    function recargarTabla() {
        $scope.mostrarTabla = false;
        $timeout(function () {
            $scope.mostrarTabla = true;
        }, 0);
    }

    $scope.cambiarTab = function (tab) {
        if ($scope.tabActivo.key === tab.key) { return; }
        $scope.tabActivo = tab;
        $scope.actActual = tab.act;
        recargarTabla();
    };

    $scope.buscarCliente = function () {
        recargarTabla();
    };

    // Filtro de "Leido" propio (el tabula real no soporta un select por columna).
    $scope.mostrarFiltroLeido = false;
    $scope.filtroLeidoCambio = function () {
        $scope.mostrarFiltroLeido = false;
        $scope.buscarCliente();
    };

    $scope.exportandoExcel = false;

    // Exporta a Excel TODOS los registros que cumplen los filtros vigentes
    // de la pestaña activa (no solo la página que se ve en la tabla).
    $scope.exportarExcel = function () {
        $scope.exportandoExcel = true;

        var params = "&tipo=" + $scope.actActual +
            "&filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroDesde=" + $scope.filtroDesde +
            "&filtroHasta=" + $scope.filtroHasta +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga +
            "&filtroLeido=" + $scope.filtroLeido +
            "&buscarCliente=" + $scope.busqueda;

        pedido.async({
            method: 'GET',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=exportarDetalle' + params
        }).then(function (response) {
            if (response && response.ruta) {
                window.open(response.ruta, '_blank');
            } else {
                avisos.alerta("Error", (response && response.mensaje) || "No se pudo generar el archivo");
            }
            $scope.exportandoExcel = false;
        });
    };

    (function leerFiltrosDeUrl() {
        var qp = $location.search();

        if (qp.filtroCartera) { $scope.filtroCartera = qp.filtroCartera; }
        if (qp.filtroProducto) { $scope.filtroProducto = qp.filtroProducto; }
        if (qp.filtroCanal) { $scope.filtroCanal = qp.filtroCanal; }
        if (qp.filtroFechaCarga) { $scope.filtroFechaCarga = qp.filtroFechaCarga; }
        if (qp.filtroLeido) { $scope.filtroLeido = qp.filtroLeido; }

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

    // -----------------------------------------------------------------
    // Volver al dashboard principal, sin perder los filtros vigentes.
    // -----------------------------------------------------------------
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
                filtroHasta: $scope.filtroHasta
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
                    var existeFechaCarga = $scope.listas.fechasCarga.some(function (p) {
                        return p.id == $scope.filtroFechaCarga;
                    });
                    if ($scope.filtroFechaCarga !== "todo" && !existeFechaCarga) {
                        $scope.filtroFechaCarga = "todo";
                    }
                    actualizarBadgeFechaCarga();
                }
                $scope.traerTotales();
                recargarTabla();
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
                $scope.cargando = false;
            }
        });
    };

    $scope.traerTotales = function () {

        $scope.cargandoTotales = true;

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerTotalesEfectividad',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroDesde: $scope.filtroDesde,
                filtroHasta: $scope.filtroHasta,
                filtroTiempo: $scope.filtroTiempo,
                filtroCanal: $scope.filtroCanal,
                filtroFechaCarga: $scope.filtroFechaCarga
            }
        }).then(function (response) {

            if (response.totales !== undefined) {
                $scope.totales = response.totales;
            }

            $scope.cargando = false;
            $scope.cargandoTotales = false;
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
