app.controller("cmDetalleCobranzaHorarios", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter) {

    const END_POINT = '../canalesMasivos/cmDashboardGestionesCobranzaCtrl.php';
    // Valores de filtroTiempo que resuelven un período de asignación (control_carga_periodo)
    const FILTROS_PERIODO = ['asigActual', 'asigAnterior'];
    const CICLO_TODOS = -1;
    const NOMBRES_PERIODO = {
        asigActual: 'Asignación Período Actual',
        asigAnterior: 'Asignación Período Anterior'
    };

    myIntercom.publica('ruta', menuSuperior);

    $scope.periodo = [{ id: CICLO_TODOS, nombre: 'Todos los ciclos' }];

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
    $scope.filtroTiempo = "ini";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";
    $scope.filtroCanal = "todo";
    $scope.filtroFechaCarga = "todo";

    // Nombres a mostrar en el badge, con las MISMAS llaves y el mismo texto
    // que se guardan en cubAG_canal / se mandan al backend: AV, EMAIL, WHATSAPP.
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

        if (FILTROS_PERIODO.indexOf(qp.filtroTiempo) !== -1) { $scope.filtroTiempo = qp.filtroTiempo; }
        $scope.listas.filtroPeriodo = (qp.filtroPeriodo !== undefined && qp.filtroPeriodo !== "") ? parseInt(qp.filtroPeriodo, 10) : CICLO_TODOS;
    })();

    /**
     * Arma el título del período de asignación con las fechas que resolvió el backend.
     * @param {Object|null} periodoAsignacion - {desde, hasta, fechaInicio, fechaFin} de obtenerParametrizacion
     * @returns {void}
     */
    function actualizarTituloPeriodo(periodoAsignacion) {
        if (FILTROS_PERIODO.indexOf($scope.filtroTiempo) === -1 || $scope.filtroCartera === "todo") {
            $scope.titulo = "";
            $scope.filtrosAplicados.tiempo.activo = false;
            return;
        }
        if (!periodoAsignacion || !periodoAsignacion.desde) {
            $scope.titulo = NOMBRES_PERIODO[$scope.filtroTiempo] + " - Sin período registrado para la cartera";
        } else {
            $scope.titulo = NOMBRES_PERIODO[$scope.filtroTiempo] + " - Del " + periodoAsignacion.fechaInicio + " al " + periodoAsignacion.fechaFin;
        }
        $scope.filtrosAplicados.tiempo.titulo = $scope.titulo;
        $scope.filtrosAplicados.tiempo.activo = true;
    }

    // -----------------------------------------------------------------
    // Volver al dashboard principal, sin perder los filtros vigentes.
    // -----------------------------------------------------------------
    $scope.volver = function () {
        var params = "?filtroCartera=" + $scope.filtroCartera +
            "&filtroProducto=" + $scope.filtroProducto +
            "&filtroTiempo=" + $scope.filtroTiempo +
            "&filtroPeriodo=" + $scope.listas.filtroPeriodo +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        $window.location.href = "#/dashboardGestionesCobranza" + params;
    };

    $scope.validarFiltros = function () {
        // El período de asignación se resuelve por cartera en control_carga_periodo.
        // Se avisa sin quitar la opción elegida, igual que en los otros dashboards.
        if (FILTROS_PERIODO.indexOf($scope.filtroTiempo) !== -1 && $scope.filtroCartera === "todo") {
            avisos.alerta("Período de asignación", "Por favor seleccione una cartera");
        }
        $scope.traerParametrizacion();
    };


    // Cartera de la que viene la lista de ciclos vigente
    var carteraCiclos = $scope.filtroCartera;

    $scope.traerParametrizacion = function () {

        $scope.cargando = true;

        if ($scope.filtroTiempo === "ini") {
            $scope.titulo = "";
            $scope.filtrosAplicados.tiempo.activo = false;
        }
        // El ciclo depende de la cartera, no del período: al cambiarla, el
        // select arranca en "Todos los ciclos" hasta que lleguen los de la nueva
        if ($scope.filtroCartera !== carteraCiclos) {
            carteraCiclos = $scope.filtroCartera;
            $scope.periodo = [{ id: CICLO_TODOS, nombre: 'Todos los ciclos' }];
            $scope.listas.filtroPeriodo = CICLO_TODOS;
        }

        pedido.async({
            method: 'POST',
            url: END_POINT + '?act=obtenerParametrizacion',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroTiempo: $scope.filtroTiempo,
                filtroPeriodo: $scope.listas.filtroPeriodo
            }
        }).then(function (response) {

            if (response?.error === 1) {
                avisos.alerta("Error", response.mensaje);
            }

            if (response !== undefined) {
                // Ciclos del período elegido; si el ciclo vigente ya no está, vuelve a "todos"
                if (Array.isArray(response?.periodos) && response.periodos.length > 0) {
                    $scope.periodo = response.periodos;
                    var existeCiclo = $scope.periodo.some(function (p) {
                        return p.id == $scope.listas.filtroPeriodo;
                    });
                    if (!existeCiclo) {
                        $scope.listas.filtroPeriodo = CICLO_TODOS;
                    }
                }
                actualizarTituloPeriodo(response.periodoAsignacion);

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
            url: END_POINT + '?act=obtenerTotales',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroPeriodo: $scope.listas.filtroPeriodo,
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
            url: END_POINT + '?act=obtenerVariacionHorarios',
            data: {
                filtroCartera: $scope.filtroCartera,
                filtroProducto: $scope.filtroProducto,
                filtroPeriodo: $scope.listas.filtroPeriodo,
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
                $scope.listas.filtroPeriodo = CICLO_TODOS;
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
                $scope.listas.filtroPeriodo = CICLO_TODOS;
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
