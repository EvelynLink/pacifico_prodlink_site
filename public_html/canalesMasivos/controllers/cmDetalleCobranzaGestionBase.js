app.controller("cmDetalleCobranzaGestionBase", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$location', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $location, $timeout, $filter) {

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
    $scope.filtroTiempo = "ini";
    $scope.filtroCartera = "todo";
    $scope.filtroProducto = "todo";

    // Con canal elegido, "Gestionados"/"Sin Gestión" usan la MEJOR GESTIÓN
    // DE ESE CANAL (cubAG_mejorGestion), no la mejor gestión general.
    $scope.filtroCanal = "todo";

    // Mismas llaves que se mandan al backend: AV, EMAIL, WHATSAPP.
    $scope.nombresCanal = {
        "AV": "AV",
        "EMAIL": "EMAIL",
        "WHATSAPP": "WHATSAPP"
    };

    // A diferencia del canal, Fecha de Carga SÍ aplica aquí: cubAG_fechaCarga
    // es un dato nativo de cuAsignacionesGestionAP (la misma colección
    // que alimenta la Base), así que este KPI sí debe filtrarse por ella.
    $scope.filtroFechaCarga = "todo";

    // Al entrar, tomamos los filtros que venían activos en el dashboard principal . Si no vienen, se usan los valores por defecto.
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

    // Volver al dashboard principal, sin perder los filtros vigentes.
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
                filtroPeriodo: $scope.listas.filtroPeriodo,
                filtroCanal: $scope.filtroCanal,
                filtroFechaCarga: $scope.filtroFechaCarga
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
            "&filtroPeriodo=" + $scope.listas.filtroPeriodo +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga;

        pedido.async({
            method: 'GET',
            url: END_POINT + '?act=obtenerTotalesGestionBase' + params
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
            "&filtroPeriodo=" + $scope.listas.filtroPeriodo +
            "&filtroCanal=" + $scope.filtroCanal +
            "&filtroFechaCarga=" + $scope.filtroFechaCarga +
            "&buscarCliente=" + (busqueda || "");

        pedido.async({
            method: 'GET',
            url: END_POINT + '?act=exportarDetalle' + params
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