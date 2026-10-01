/**
 * Dashboard de seguimiento de gestiones.
 *
 * Secciones:
 *  - Estado inicial
 *  - Filtros (mismos que dashboardGestionIndividual)
 *  - Totales y gráfico de tipificaciones
 *  - Pestañas y tablas
 *  - Detalle de audio, chat y correo
 *  - Actualización automática
 *  - Inicio
 */
app.controller("cmDashboardSeguimientoGestiones", ['$scope', 'avisos', 'pedido', 'myIntercom', '$timeout', '$sce', function ($scope, avisos, pedido, myIntercom, $timeout, $sce) {

    myIntercom.publica('ruta', menuSuperior);

    const END_POINT = '../canalesMasivos/cmDashboardSeguimientoGestionesCtrl.php';
    const RUTA_PANTALLA = 'dashboardSeguimientoGestiones';
    const CLAVE_TIEMPO_ACTUALIZA = 'tiempoActualizaSeguimientoGestiones';
    const TIEMPO_ACTUALIZA_DEFECTO = 60000;
    const REINTENTO_ACTUALIZA = 10000;
    const MAXIMO_RANGO_SEGUNDOS = 92 * 24 * 3600; // 3 meses
    const ID_GRAFICO = 'sgGraficoTipificaciones';
    const ALTO_BARRA_GRAFICO = 34;
    const ALTO_MINIMO_GRAFICO = 220;
    const PESTANIAS = [
        { key: 'llamadas', canal: 'llamada', label: 'Llamadas', icono: 'fa-phone', clase: 'sg-color-llamadas' },
        { key: 'whatsapp', canal: 'wp', label: 'WhatsApp', icono: 'fa-whatsapp', clase: 'sg-color-whatsapp' },
        { key: 'correo', canal: 'mail', label: 'Correo', icono: 'fa-envelope-o', clase: 'sg-color-correo' },
        { key: 'pendientes', canal: null, label: 'Pendientes', icono: 'fa-clock-o', clase: 'sg-color-pendientes' }
    ];
    const NOMBRES_CANAL = { llamada: 'Llamadas', wp: 'WhatsApp', mail: 'Correo electrónico' };

    //#region Estado inicial
    $scope.cargando = true;
    $scope.cargandoTotales = false;
    $scope.titulo = "";
    $scope.permisos = { soyDesarrollo: false, soySupervisor: false, soyObservador: true, soyGerente: false };
    $scope.listas = {
        carteras: [],
        campanias: [],
        filtroPeriodo: [],
        tiemposActualiza: [
            { nombre: "Cada 10 segs.", id: 10000 },
            { nombre: "Cada 30 segs.", id: 30000 },
            { nombre: "Cada minuto", id: 60000 },
            { nombre: "Cada 5 mins.", id: 300000 },
            { nombre: "Cada 10 mins.", id: 600000 }
        ]
    };
    $scope.periodo = [];
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "dia";
    $scope.filtroCartera = [];
    $scope.filtroCampania = [];
    $scope.filtroCanal = [];
    $scope.filtrosAplicados = [];
    $scope.tiempoActualizar = TIEMPO_ACTUALIZA_DEFECTO;

    $scope.cuadrosSuperiores = {
        intentos: { total: 0, tooltip: "" },
        whatsapp: { total: 0, tooltip: "" },
        correo: { total: 0, tooltip: "" },
        pendientes: { total: 0, tooltip: "" }
    };
    $scope.secciones = {
        llamadas: { cuadros: [], tipificaciones: [] },
        whatsapp: { cuadros: [] },
        correo: { cuadros: [] },
        pendientes: { cuadros: [] }
    };
    $scope.canalesActivos = ['llamada', 'wp', 'mail'];
    $scope.pestanias = PESTANIAS;
    $scope.pestaniaActiva = PESTANIAS[0];
    $scope.canalPendiente = 'llamada';
    $scope.busqueda = { texto: "" };
    $scope.recarga = { llamadas: 0, whatsapp: 0, correo: 0, pendientes: 0 };
    // Filtros con los que se cargaron los datos: las tablas usan estos y no los que se están editando
    $scope.parametrosTabla = "";
    //#endregion

    //#region Filtros
    /**
     * Arma el título del periodo elegido (igual que dashboardGestionIndividual).
     * @returns {void}
     */
    function armarTitulo() {
        const hoy = new Date();
        switch ($scope.filtroTiempo) {
            case "hora":
                $scope.titulo = "Última hora - " + dosDigitos(hoy.getHours()) + ":00/" + dosDigitos(hoy.getHours()) + ":59";
                break;
            case "dia":
                $scope.titulo = "Este día - " + formatoFecha(hoy);
                break;
            case "ayer": {
                const ayer = new Date(hoy);
                ayer.setDate(ayer.getDate() - 1);
                $scope.titulo = "Ayer - " + formatoFecha(ayer);
                break;
            }
            case "semana": {
                const lunes = obtenerLunes(hoy, 0);
                $scope.titulo = "Esta semana - del " + formatoFecha(lunes) + " al " + formatoFecha(hoy);
                break;
            }
            case "semanaanterior": {
                const lunes = obtenerLunes(hoy, -7);
                const domingo = new Date(lunes);
                domingo.setDate(domingo.getDate() + 6);
                $scope.titulo = "Semana anterior - del " + formatoFecha(lunes) + " al " + formatoFecha(domingo);
                break;
            }
            case "mes":
                $scope.titulo = "Este mes - " + dosDigitos(hoy.getMonth() + 1) + "/" + hoy.getFullYear();
                break;
            case "mesanterior": {
                const mes = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
                $scope.titulo = "Mes anterior - " + dosDigitos(mes.getMonth() + 1) + "/" + mes.getFullYear();
                break;
            }
            case "rango":
                // El título del rango lo arma aplicarRangoFechas()
                return;
        }
        $scope.fechaDesde = "";
        $scope.fechaHasta = "";
        $scope.filtroDesde = "";
        $scope.filtroHasta = "";
    }

    /**
     * Lunes de la semana de una fecha, desplazado en días.
     * @param {Date} fecha - Fecha de referencia.
     * @param {number} desplazamiento - Días a sumar al lunes (ej. -7 para la semana anterior).
     * @returns {Date}
     */
    function obtenerLunes(fecha, desplazamiento) {
        const lunes = new Date(fecha);
        const dia = lunes.getDay();
        lunes.setDate(lunes.getDate() - dia + (dia === 0 ? -6 : 1) + desplazamiento);
        return lunes;
    }

    /**
     * @param {number} n - Número a formatear.
     * @returns {string} El número con dos dígitos.
     */
    function dosDigitos(n) {
        return ("0" + n).slice(-2);
    }

    /**
     * @param {Date} fecha - Fecha a formatear.
     * @returns {string} Fecha en formato dd/mm/aaaa.
     */
    function formatoFecha(fecha) {
        return dosDigitos(fecha.getDate()) + "/" + dosDigitos(fecha.getMonth() + 1) + "/" + fecha.getFullYear();
    }

    /**
     * Filtros que se envían al backend.
     * @returns {Object}
     */
    function datosFiltros() {
        return {
            filtroTiempo: $scope.filtroTiempo,
            filtroDesde: $scope.filtroDesde,
            filtroHasta: $scope.filtroHasta,
            filtroCartera: $scope.filtroCartera,
            filtroCampania: $scope.filtroCampania,
            filtroPeriodo: $scope.listas.filtroPeriodo,
            filtroCanal: $scope.filtroCanal
        };
    }

    /**
     * Guarda los filtros vigentes como querystring para las tablas (multiselección separada por comas).
     * @returns {void}
     */
    function fijarParametrosTabla() {
        const datos = datosFiltros();
        $scope.parametrosTabla = Object.keys(datos).map(function (clave) {
            const valor = Array.isArray(datos[clave]) ? datos[clave].join(",") : datos[clave];
            return clave + "=" + encodeURIComponent(valor === undefined || valor === null ? "" : valor);
        }).join("&");
    }

    /**
     * Pide las listas de los filtros y, si corresponde, los datos del dashboard.
     * Al cambiar un filtro solo se refrescan las listas; los datos se cargan con "Filtrar".
     * @param {boolean} cargarDatos - true para recargar totales y tablas.
     * @returns {void}
     */
    $scope.traerParametrizacion = function (cargarDatos) {
        $scope.cargando = true;
        if ($scope.filtroCartera.length === 0) {
            $scope.listas.filtroPeriodo = [];
            $scope.periodo = [];
        }
        armarTitulo();
        pedido.async({
            method: 'POST',
            url: END_POINT + '?act=obtenerParametrizacion',
            data: datosFiltros()
        }).then(function (response) {
            if (response === undefined) {
                avisos.alerta("Error", "No hubo respuesta del servidor [parametrización]");
                $scope.cargando = false;
                return;
            }
            // Igual que en dashboardGestionIndividual: la lista solo se reemplaza mientras no haya nada elegido
            if (response.carteras !== undefined && $scope.filtroCartera.length === 0) {
                $scope.listas.carteras = response.carteras;
            }
            if (response.campanias !== undefined && $scope.filtroCampania.length === 0) {
                $scope.listas.campanias = response.campanias;
            }
            if (response.periodos !== undefined && $scope.listas.filtroPeriodo.length === 0) {
                $scope.periodo = $scope.filtroCartera.length > 0 ? response.periodos : [];
            }
            if (response.permisos !== undefined) {
                $scope.permisos = response.permisos;
            }
            if (cargarDatos) {
                cargarDatosDashboard();
            } else {
                $scope.cargando = false;
            }
        });
    };

    /**
     * Espera a que el usuario termine de marcar opciones antes de refrescar las listas.
     * @returns {void}
     */
    let esperaCambio = null;
    $scope.cambioCheck = function () {
        $timeout.cancel(esperaCambio);
        esperaCambio = $timeout(function () {
            $scope.traerParametrizacion(false);
        }, 800);
    };

    /**
     * Valida el rango Desde/Hasta y carga los datos.
     * @returns {void}
     */
    $scope.aplicarRangoFechas = function () {
        if (!($scope.fechaDesde instanceof Date) || !($scope.fechaHasta instanceof Date)) {
            return;
        }
        $scope.filtroDesde = Math.floor($scope.fechaDesde.getTime() / 1000);
        $scope.filtroHasta = Math.floor($scope.fechaHasta.getTime() / 1000);
        if ($scope.filtroHasta < $scope.filtroDesde) {
            avisos.alerta("Error", "Rango de fechas incorrecto");
            return;
        }
        if (($scope.filtroHasta - $scope.filtroDesde) > MAXIMO_RANGO_SEGUNDOS) {
            avisos.alerta("Error", "No se puede mostrar un periodo mayor a 3 meses");
            return;
        }
        $scope.titulo = "Del " + formatoFecha($scope.fechaDesde) + " al " + formatoFecha($scope.fechaHasta);
        $scope.traerParametrizacion(true);
    };

    /**
     * Nombres de las opciones elegidas de una lista.
     * @param {Array} seleccion - Ids elegidos.
     * @param {Array} lista - Opciones {id, nombre}.
     * @returns {string}
     */
    function nombresElegidos(seleccion, lista) {
        return seleccion.map(function (id) {
            const opcion = lista.find(function (o) { return o.id == id; });
            return opcion ? opcion.nombre : id;
        }).join(", ");
    }

    /**
     * Arma los badges de filtros aplicados con los filtros de la última carga.
     * @returns {void}
     */
    function actualizarFiltrosAplicados() {
        const aplicados = [];
        if ($scope.filtroTiempo !== "dia") {
            aplicados.push({ clave: 'tiempo', titulo: $scope.titulo });
        }
        if ($scope.filtroCartera.length > 0) {
            const ciclos = $scope.listas.filtroPeriodo.length > 0 ? nombresElegidos($scope.listas.filtroPeriodo, $scope.periodo) : "Todos los ciclos";
            aplicados.push({ clave: 'cartera', titulo: "Cartera: " + nombresElegidos($scope.filtroCartera, $scope.listas.carteras) + "   Ciclo: " + ciclos });
        }
        if ($scope.filtroCampania.length > 0) {
            aplicados.push({ clave: 'campania', titulo: "Campaña: " + nombresElegidos($scope.filtroCampania, $scope.listas.campanias) });
        }
        if ($scope.filtroCanal.length > 0) {
            aplicados.push({ clave: 'canal', titulo: "Canal: " + $scope.filtroCanal.map(function (c) { return NOMBRES_CANAL[c]; }).join(", ") });
        }
        $scope.filtrosAplicados = aplicados;
    }

    /**
     * Quita un filtro (o todos) y recarga.
     * @param {string} clave - tiempo | cartera | campania | canal | todo.
     * @returns {void}
     */
    $scope.quitarFiltro = function (clave) {
        if (clave === 'tiempo' || clave === 'todo') {
            $scope.filtroTiempo = "dia";
        }
        if (clave === 'cartera' || clave === 'todo') {
            $scope.filtroCartera = [];
            $scope.listas.filtroPeriodo = [];
        }
        if (clave === 'campania' || clave === 'todo') {
            $scope.filtroCampania = [];
        }
        if (clave === 'canal' || clave === 'todo') {
            $scope.filtroCanal = [];
        }
        $scope.traerParametrizacion(true);
    };
    //#endregion

    //#region Totales y gráfico de tipificaciones
    /**
     * Carga totales y recarga las tablas con los filtros vigentes.
     * @returns {void}
     */
    function cargarDatosDashboard() {
        fijarParametrosTabla();
        actualizarFiltrosAplicados();
        recargarTablas();
        traerTotales();
    }

    /**
     * Pide los cuadros superiores, los KPIs de cada pestaña y el gráfico.
     * @returns {void}
     */
    function traerTotales() {
        $scope.cargandoTotales = true;
        pedido.async({
            method: 'POST',
            url: END_POINT + '?act=obtenerTotales',
            data: datosFiltros()
        }).then(function (response) {
            $scope.cargando = false;
            $scope.cargandoTotales = false;
            if (response === undefined || response.cuadrosSuperiores === undefined) {
                avisos.alerta("Error", "No hubo respuesta del servidor [totales]");
                $scope.actualizarAutomaticamente();
                return;
            }
            $scope.cuadrosSuperiores = response.cuadrosSuperiores;
            $scope.secciones.llamadas = response.llamadas;
            $scope.secciones.whatsapp = response.whatsapp;
            $scope.secciones.correo = response.correo;
            $scope.secciones.pendientes = response.pendientes;
            $scope.canalesActivos = response.canales;
            ajustarPestaniaActiva();
            dibujarGrafico();
            $scope.actualizarAutomaticamente();
        });
    }

    /**
     * Escapa texto para el tooltip HTML de ECharts.
     * @param {string} texto - Texto a escapar.
     * @returns {string}
     */
    function escaparHtml(texto) {
        return String(texto === undefined || texto === null ? "" : texto)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    /**
     * Alto del gráfico según la cantidad de barras.
     * @returns {Object} Estilo para ng-style.
     */
    $scope.altoGrafico = function () {
        const barras = $scope.secciones.llamadas.tipificaciones || [];
        return { height: Math.max(ALTO_MINIMO_GRAFICO, barras.length * ALTO_BARRA_GRAFICO) + "px" };
    };

    /**
     * Dibuja el gráfico de tipificaciones (barras horizontales) cuando la pestaña Llamadas está visible.
     * @returns {void}
     */
    function dibujarGrafico() {
        $timeout(function () {
            const contenedor = document.getElementById(ID_GRAFICO);
            if (!contenedor || $scope.pestaniaActiva.key !== 'llamadas') {
                return;
            }
            const barras = $scope.secciones.llamadas.tipificaciones || [];
            const grafico = echarts.getInstanceByDom(contenedor) || echarts.init(contenedor, null, { renderer: 'svg' });
            grafico.setOption({
                grid: { left: 10, right: 40, top: 10, bottom: 10, containLabel: true },
                tooltip: {
                    trigger: 'item',
                    formatter: function (p) {
                        return escaparHtml(p.data.padre) + " - " + escaparHtml(p.name) + "<br><b>" + p.value + " usuarios</b> (" + escaparHtml(p.data.porcentaje) + ")";
                    }
                },
                xAxis: { type: 'value', splitLine: { lineStyle: { color: '#EDEFF2' } } },
                yAxis: {
                    type: 'category',
                    inverse: true,
                    data: barras.map(function (b) { return b.label; }),
                    axisTick: { show: false },
                    axisLabel: { fontSize: 11, color: '#374151' }
                },
                series: [{
                    type: 'bar',
                    barMaxWidth: 26,
                    label: { show: true, position: 'right', fontSize: 11 },
                    data: barras.map(function (b) {
                        return {
                            value: b.value,
                            name: b.label,
                            padre: b.padre,
                            porcentaje: b.porcentaje,
                            itemStyle: { color: b.color, borderRadius: [0, 4, 4, 0] }
                        };
                    })
                }]
            }, true);
            grafico.resize();
        });
    }
    //#endregion

    //#region Pestañas y tablas
    /**
     * Indica si una pestaña se ve con el filtro de canal vigente.
     * @param {Object} pestania - Elemento de PESTANIAS.
     * @returns {boolean}
     */
    $scope.pestaniaVisible = function (pestania) {
        return pestania.canal === null || $scope.canalesActivos.indexOf(pestania.canal) >= 0;
    };

    /**
     * Si la pestaña activa quedó oculta por el filtro de canal, pasa a la primera visible.
     * @returns {void}
     */
    function ajustarPestaniaActiva() {
        if (!$scope.pestaniaVisible($scope.pestaniaActiva)) {
            $scope.pestaniaActiva = PESTANIAS.find($scope.pestaniaVisible);
        }
        if ($scope.canalesActivos.indexOf($scope.canalPendiente) < 0) {
            $scope.canalPendiente = $scope.canalesActivos[0];
        }
    }

    /**
     * Cambia de pestaña; la tabla de la pestaña nueva se crea (ng-if) y carga con los filtros vigentes.
     * @param {Object} pestania - Elemento de PESTANIAS.
     * @returns {void}
     */
    $scope.cambiarPestania = function (pestania) {
        if ($scope.pestaniaActiva.key === 'llamadas') {
            const contenedor = document.getElementById(ID_GRAFICO);
            const grafico = contenedor ? echarts.getInstanceByDom(contenedor) : null;
            if (grafico) {
                grafico.dispose();
            }
        }
        $scope.pestaniaActiva = pestania;
        $scope.busqueda.texto = "";
        dibujarGrafico();
    };

    /**
     * Elige de qué canal se listan los pendientes.
     * @param {string} canal - llamada | wp | mail.
     * @returns {void}
     */
    $scope.elegirCanalPendiente = function (canal) {
        if ($scope.canalesActivos.indexOf(canal) < 0) {
            return;
        }
        $scope.canalPendiente = canal;
        $scope.recarga.pendientes++;
    };

    /**
     * URL de la tabla de una pestaña, con los filtros de la última carga y el texto buscado.
     * @param {string} act - Acción del backend.
     * @returns {string}
     */
    $scope.urlTabla = function (act) {
        let url = END_POINT + "?act=" + act + "&" + $scope.parametrosTabla + "&buscarCliente=" + encodeURIComponent($scope.busqueda.texto);
        if (act === 'listaPendientes') {
            url += "&canalPendiente=" + $scope.canalPendiente;
        }
        return url;
    };

    /**
     * Recarga la tabla de la pestaña activa al buscar un cliente.
     * @returns {void}
     */
    $scope.buscarCliente = function () {
        $scope.recarga[$scope.pestaniaActiva.key]++;
    };

    /**
     * Recarga todas las tablas (solo la de la pestaña activa existe en pantalla).
     * @returns {void}
     */
    function recargarTablas() {
        Object.keys($scope.recarga).forEach(function (clave) {
            $scope.recarga[clave]++;
        });
    }
    //#endregion

    //#region Detalle de audio, chat y correo
    $scope.verDetalleLlamada = { activo: false, nombreCliente: "", detalle: { audio: "", transcripcion: [] } };
    $scope.verDetalleWhatsapp = { activo: false, nombreCliente: "", detalle: { transcripcion: [] } };
    $scope.verDetalleCorreo = { activo: false, nombreCliente: "", detalle: { html: "", destinatario: "" } };

    /**
     * Pide el detalle de un registro y abre su modal.
     * @param {string} act - Acción del backend.
     * @param {Object} fila - Fila de la tabla.
     * @param {Object} modal - Objeto del modal a llenar.
     * @param {Function} preparar - Convierte la respuesta del backend en el detalle del modal.
     * @param {string} mensajeError - Mensaje si no se pudo obtener el detalle.
     * @returns {void}
     */
    function abrirDetalle(act, fila, modal, preparar, mensajeError) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: END_POINT + '?act=' + act,
            data: { avId: fila.avId }
        }).then(function (response) {
            $scope.cargando = false;
            const respuesta = response && response.respuesta;
            if (!respuesta || respuesta.error !== undefined) {
                avisos.alerta("Error", (respuesta && respuesta.error) || mensajeError);
                return;
            }
            modal.nombreCliente = fila.nombreCliente;
            modal.detalle = preparar(respuesta);
            modal.activo = true;
        });
    }

    /**
     * @param {Object} respuesta - Respuesta con la transcripción en base64.
     * @returns {Object} La respuesta con la transcripción ya decodificada.
     */
    function decodificarTranscripcion(respuesta) {
        respuesta.transcripcion = respuesta.transcripcion ? JSON.parse(window.atob(respuesta.transcripcion)) : [];
        return respuesta;
    }

    /**
     * @param {Object} fila - Fila de la tabla de llamadas.
     * @returns {void}
     */
    $scope.mostrarDetalleLlamada = function (fila) {
        abrirDetalle('obtenerAudioTranscripcionLlamada', fila, $scope.verDetalleLlamada, decodificarTranscripcion, "No se pudo obtener el detalle de la llamada");
    };

    /**
     * @param {Object} fila - Fila de la tabla de WhatsApp.
     * @returns {void}
     */
    $scope.mostrarDetalleWhatsapp = function (fila) {
        abrirDetalle('obtenerTranscripcionWhatsapp', fila, $scope.verDetalleWhatsapp, decodificarTranscripcion, "No se pudo obtener el detalle del chat");
    };

    /**
     * @param {Object} fila - Fila de la tabla de correo.
     * @returns {void}
     */
    $scope.mostrarDetalleCorreo = function (fila) {
        abrirDetalle('obtenerDetalleCorreo', fila, $scope.verDetalleCorreo, function (respuesta) {
            // Mismo tratamiento que dashboardGestionesCobranza: el html del correo se muestra tal cual
            respuesta.html = respuesta.html ? $sce.trustAsHtml(window.atob(respuesta.html)) : "";
            return respuesta;
        }, "No se pudo obtener el detalle del correo");
    };
    //#endregion

    //#region Actualización automática
    let esperaActualizacion = null;

    /**
     * Recarga los datos cada cierto tiempo, solo en "Última hora" o "Este día" (igual que dashboardGestionIndividual).
     * @returns {void}
     */
    $scope.actualizarAutomaticamente = function () {
        $timeout.cancel(esperaActualizacion);
        if (window.location.href.indexOf(RUTA_PANTALLA) < 0) {
            return;
        }
        if ($scope.cargando || $scope.cargandoTotales) {
            esperaActualizacion = $timeout($scope.actualizarAutomaticamente, REINTENTO_ACTUALIZA);
            return;
        }
        esperaActualizacion = $timeout(function () {
            if ($scope.filtroTiempo === "dia" || $scope.filtroTiempo === "hora") {
                $scope.traerParametrizacion(true);
            } else {
                $scope.actualizarAutomaticamente();
            }
        }, $scope.tiempoActualizar);
    };

    /**
     * Guarda el intervalo elegido y reprograma la actualización.
     * @returns {void}
     */
    $scope.setTiempoActualizar = function () {
        window.localStorage.setItem(CLAVE_TIEMPO_ACTUALIZA, String($scope.tiempoActualizar));
        $scope.actualizarAutomaticamente();
    };

    /**
     * Lee el intervalo guardado (por defecto cada minuto).
     * @returns {void}
     */
    function leerTiempoActualizar() {
        const guardado = parseInt(window.localStorage.getItem(CLAVE_TIEMPO_ACTUALIZA), 10);
        $scope.tiempoActualizar = isNaN(guardado) ? TIEMPO_ACTUALIZA_DEFECTO : guardado;
    }

    $scope.$on('$destroy', function () {
        $timeout.cancel(esperaActualizacion);
        $timeout.cancel(esperaCambio);
        const contenedor = document.getElementById(ID_GRAFICO);
        const grafico = contenedor ? echarts.getInstanceByDom(contenedor) : null;
        if (grafico) {
            grafico.dispose();
        }
    });
    //#endregion

    //#region Inicio
    leerTiempoActualizar();
    $scope.traerParametrizacion(true);
    //#endregion

}]);

//_FIN_DE_ARCHIVO
