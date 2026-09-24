app.controller("cmDetalleHorarios", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);

    $scope.cargando = true;

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

    // Nombres a mostrar en el badge, con las MISMAS llaves y el mismo texto
    // que se guardan en cubAV_canal / se mandan al backend: AV, EMAIL, WHATSAPP.
    $scope.nombresCanal = {
        "AV": "AV",
        "EMAIL": "EMAIL",
        "WHATSAPP": "WHATSAPP"
    };

    $scope.filas = [];

    $scope.resumen = {
        "intentos": 0,
        "contactos": 0,
        "mejorFranja": "-",
        "contactabilidadPromedio": "0"
    };

    // "Por Dia y Hora" todavia no esta definida (pendiente de diseno)
    $scope.subTabActiva = "horario";

    $scope.cambiarSubTab = function (tab) {
        if (tab === "diaHora") {
            avisos.alerta("Informacion", "La vista \"Por Dia y Hora\" todavia no esta definida. Proximamente.");
            return;
        }
        $scope.subTabActiva = tab;
    };

    // NOTA: la letra con tilde de "Mas" se arma con String.fromCharCode(225) en vez de escribirla directo en el archivo
    var LETRA_A_CON_TILDE = String.fromCharCode(225);

    var NOMBRES_FRANJAS = [
        "Hasta 10:00",
        "10:00 - 12:00",
        "12:00 - 14:00",
        "14:00 - 16:00",
        "16:00 - 18:00",
        "18:00 o M" + LETRA_A_CON_TILDE + "s"
    ];

    function formatearMiles(valor) {
        return (valor || 0).toLocaleString('es-EC'); // usa puntos para miles
    }

    function colorPorPorcentaje(valor) {
        if (valor <= 30) {
            return '#E05A5A';
        } else if (valor <= 50) {
            return '#F5A04C';
        } else if (valor <= 60) {
            return '#F2D36A';
        }
        return '#4FAF5A';
    }

    function armarDesdeHorarios(horarios) {

        horarios = horarios || [];

        var sumaIntentos = 0;
        var sumaContactos = 0;
        var mejorFranja = "-";
        var mejorPorcentaje = -1;

        $scope.filas = NOMBRES_FRANJAS.map(function (nombre, index) {
            var h = horarios[index] || {};
            var total = h.total || 0;
            var contactados = h.contactados || 0;
            var porcentaje = parseFloat(h.porcentaje) || 0;

            sumaIntentos += total;
            sumaContactos += contactados;

            if (porcentaje > mejorPorcentaje) {
                mejorPorcentaje = porcentaje;
                mejorFranja = nombre;
            }

            return {
                horario: nombre,
                intentos: formatearMiles(total),
                contactos: formatearMiles(contactados),
                porcentaje: porcentaje, // numero crudo: lo usa la barra (ng-style width)
                porcentajeTexto: porcentaje.toFixed(2).replace('.', ','), // para mostrar (coma decimal)
                color: colorPorPorcentaje(porcentaje)
            };
        });

        var contactabilidadPromedio = sumaIntentos > 0 ? (sumaContactos * 100) / sumaIntentos : 0;

        $scope.resumen = {
            intentos: formatearMiles(sumaIntentos),
            contactos: formatearMiles(sumaContactos),
            mejorFranja: mejorFranja,
            contactabilidadPromedio: contactabilidadPromedio.toFixed(2).replace('.', ',')
        };
    }

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
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrizacion]");
                $scope.cargando = false;
            }
        });
    };

    $scope.traerTotales = function () {

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
                filtroFechaCarga: $scope.filtroFechaCarga
            }
        }).then(function (response) {


            armarDesdeHorarios(response && response.totales ? response.totales.horarios : []);

            $scope.traerVariacion();

            $scope.cargando = false;
        });
    };

    // "Variacion vs periodo anterior": compara, franja por franja, el % de contactabilidad actual contra el mismo dato del ciclo anterior

    $scope.traerVariacion = function () {

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmDashboardGestionesVentasCtrl.php?act=obtenerVariacionHorarios',
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
            armarVariacion(response && response.variacion ? response.variacion : []);
        });
    };

    function armarVariacion(variacion) {

        variacion = variacion || [];

        $scope.filas.forEach(function (fila, index) {

            var v = variacion[index];

            if (!v || !v.hayDato) {
                fila.variacion = { hayDato: false };
                return;
            }

            var puntos = fila.porcentaje - (parseFloat(v.porcentajeAnterior) || 0);
            puntos = Math.round(puntos * 100) / 100;

            var signo = puntos > 0 ? '+' : '';
            var direccion = puntos > 0 ? 'up' : (puntos < 0 ? 'down' : 'igual');

            fila.variacion = {
                hayDato: true,
                puntos: puntos,
                texto: signo + puntos.toFixed(2).replace('.', ',') + ' pts',
                direccion: direccion
            };
        });
    }

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
