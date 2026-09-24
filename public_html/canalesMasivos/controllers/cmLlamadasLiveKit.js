app.controller("cmLlamadasLiveKit", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', '$parse', '$sce', function ($scope, avisos, simple, pedido, $timeout, myIntercom, $parse, $sce) {
    window.Z_cbPanel = $scope;
    myIntercom.publica('ruta', menuSuperior);
    $scope.tabuVariable = 0;
    $scope.cargando = true;
    $scope.listas = {
        "agentes": [],
        "estados": []
    };
    $scope.equivalencias = {
        "agentes": {},
        "estados": {},
        "fechas": {
            "dia": "Este día",
            "ayer": "Ayer",
            "semana": "Esta semana",
            "semanaanterior": "Semana anterior",
            "mes": "Este mes",
            "mesanterior": "Mes anterior",
            "rango": "Rango de fechas",
        }
    }
    $scope.estadosSeleccionados = [];
    $scope.agentesSeleccionados = [];
    $scope.fechaDesde = "";
    $scope.fechaHasta = "";
    $scope.filtroDesde = "";
    $scope.filtroHasta = "";
    $scope.filtroTiempo = "dia";
    $scope.filtrosAplicados = {
        "todo": {
            "titulo": "Quita filtros",
            "activo": false,
        },
        "tiempo": {
            "titulo": "",
            "activo": false,
        },
        "estados": {
            "titulo": "",
            "activo": false,
        },
        "agentes": {
            "titulo": "",
            "activo": false,
        }
    };
    $scope.cuantosAplicados = 0;

    $scope.debouncer = null;
    $scope.verModalLlamada = {
        evento: {},
        activo: false,
        agente: "",
        numero: "",
        valoresReemplazo: [],
        nuevoDatos: []
    };
    $scope.verDetalleLlamada = {
        evento: {},
        activo: false,
        datos: []
    };

    $scope.cargoTabula = (filas, extra) => {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = () => {
        $scope.cargando = true;
        $scope.tabuVariable++;
    };

    $scope.traerParametrizacion = () => {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=obtenerParametrizacion',
            data: {}
        }).then((response) => {
            if (response !== undefined) {
                if (response?.agentes !== undefined) {
                    $scope.listas.agentes = response.agentes;

                    $scope.listas.agentes.forEach(element => {
                        $scope.equivalencias.agentes[element.id] = element.nombre;
                    });
                }
                if (response?.estados !== undefined) {
                    $scope.listas.estados = response.estados;

                    $scope.listas.estados.forEach(element => {
                        $scope.equivalencias.estados[element.id] = element.nombre;
                    });
                }

            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            //$scope.cargando = false;
        });

    };

    $scope.debounceAplicarFiltros = () => {
        if ($scope.filtroTiempo == "rango") {
            if ($scope.fechaDesde != "" && $scope.fechaHasta != "") {
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
            } else {
                return;
            }
        }
        clearTimeout($scope.debouncer);
        $scope.debouncer = $timeout(() => {
            $scope.aplicarFiltros();
        }, 1000);
    };

    $scope.aplicarFiltros = () => {
        $scope.actualizarTabula();
    };

    $scope.nuevaLlamada = () => {
        $scope.verModalLlamada = {
            evento: {},
            activo: true,
            agente: "",
            numero: "",
            valoresReemplazo: [],
            nuevoDatos: []
        };
    };

    $scope.traerCamposAgente = () => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=traerCamposAgente',
            data: {
                agente: $scope.verModalLlamada.agente
            }
        }).then((response) => {
            if (response !== undefined) {
                if (response?.campos !== undefined) {
                    $scope.verModalLlamada.nuevoDatos = response.campos;
                }
                $scope.cargando = false;
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            //$scope.cargando = false;
        });
    };

    $scope.iniciarLlamada = () => {
        if ($scope.verModalLlamada.numero == "" || $scope.verModalLlamada.numero.substring(0, 5) !== "+5939") {
            avisos.alerta("Error", "Número de teléfono incorrecto");
            return;
        }
        if ($scope.verModalLlamada.agente == "") {
            avisos.alerta("Error", "Seleccione el agente");
            return;
        }

        let errore = [];
        $scope.verModalLlamada.nuevoDatos.forEach((element, index) => {
            if (element.valor === "") {
                errore.push(element.nombre + " no tiene datos");
            }
            if (index === $scope.verModalLlamada.nuevoDatos.length - 1) {
                if (errore.length > 0) {
                    avisos.alerta("Error", errore);
                    return;
                } else {
                    $scope.cargando = true;
                    pedido.async({
                        method: 'POST',
                        url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=iniciarLlamada',
                        data: {
                            agente: $scope.verModalLlamada.agente,
                            telefono: $scope.verModalLlamada.numero,
                            valores_reemplazo: $scope.verModalLlamada.nuevoDatos
                        }
                    }).then(function (response) {
                        if (response?.respuesta !== undefined) {
                            avisos.alerta("Atención", response.respuesta);
                            $scope.actualizarTabula();
                            $scope.verModalLlamada.activo = false;
                        } else {
                            avisos.alerta("Atención", "Sin respuesta del servidor");
                        }
                        $scope.cargando = false;
                    });
                }
            }
        });
    };

    $scope.quitarFiltro = function (filtro) {
        switch (filtro) {
            case "tiempo":
                $scope.filtroTiempo = "dia";
                $scope.cuantosAplicados--;
                break;
            case "estados":
                $scope.estadosSeleccionados = [];
                $scope.cuantosAplicados--;
                break;
            case "agentes":
                $scope.agentesSeleccionados = [];
                $scope.cuantosAplicados--;
                break;
            case "todo":
                $scope.filtroTiempo = "dia";
                $scope.estadosSeleccionados = [];
                $scope.agentesSeleccionados = [];
                $scope.cuantosAplicados = 0;
                break;
        }
        $timeout(() => {
            $scope.actualizarTabula();
        }, 1000);
    };

    $scope.abrirLlamada = (llamada) => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=detalleLlamada',
            data: {
                id: llamada.idLlamada
            }
        }).then(function (response) {
            if (response?.llamada !== undefined) {
                $scope.verDetalleLlamada.mostrarTranscripcion = true;
                $scope.verDetalleLlamada.mostrarDetalle = false;
                $scope.verDetalleLlamada.mostrarDinamicos = false;
                $scope.verDetalleLlamada.datos = response.llamada;
                let nueva_transcripcion = [];
                let fecha_primera = 0;
                if ($scope.verDetalleLlamada.datos.transcripcion !== undefined) {
                    if ($scope.verDetalleLlamada.datos.transcripcion.items !== undefined && $scope.verDetalleLlamada.datos.transcripcion.items.length > 0) {
                        $scope.verDetalleLlamada.datos.transcripcion.items.forEach(element => {
                            if (element.type === "message") {
                                nueva_transcripcion.push({
                                    direccion: element.role === "assistant" ? "agente" : "cliente",
                                    mensaje: element.content[0],
                                    interlocutorInterrumpe: element.interrupted,
                                    fechaHora: parseInt(element.metrics.started_speaking_at)
                                });
                                $scope.verDetalleLlamada.datos.transcripcion_simple = nueva_transcripcion;
                                if (fecha_primera === 0) {
                                    fecha_primera = parseInt(element.metrics.started_speaking_at)
                                }
                                $scope.verDetalleLlamada.datos.fecha_inicia_conversacion = fecha_primera;
                            }
                        });
                    }
                }

                $scope.verDetalleLlamada.activo = true;

                pedido.async({
                    method: 'POST',
                    url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=audioLlamada',
                    data: {
                        id: llamada.idLlamada
                    }
                }).then(function (response) {
                    if (response?.audio !== undefined) {
                        $scope.verDetalleLlamada.datos.audioLocal = response.audio;
                    } else if (response?.error) {
                        avisos.alerta("Atención", response.error);
                    } else {
                        avisos.alerta("Atención", "Sin respuesta del servidor");
                    }
                    $scope.cargando = false;
                });

            } else if (response?.error) {
                avisos.alerta("Atención", response.error);
                $scope.cargando = false;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
                $scope.cargando = false;
            }
        });
    }

    $scope.eliminarLlamada = function (llamada) {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=eliminarLlamada',
                data: {
                    id: llamada.idLlamada
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    $scope.actualizarTabula();
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        });
        avisos.confirma("Confirme", ["Esta seguro de eliminar la llamada?"]);
    };

    $scope.desprogramarLlamada = (llamada) => {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmLlamadasLivekitCtrl.php?act=desprogramarLlamada',
                data: {
                    id: llamada.idLlamada
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    $scope.actualizarTabula();
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        });
        avisos.confirma("Confirme", ["Esta seguro de desprogramar la llamada?"]);
    };

    $scope.$watch('filtroTiempo', function () {
        if ($scope.filtroTiempo !== "dia" && $scope.filtroTiempo !== "rango") {
            $scope.filtrosAplicados.tiempo.titulo = $scope.equivalencias.fechas[$scope.filtroTiempo];
            $scope.filtrosAplicados.tiempo.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.tiempo.activo = false;
        }
    });
    $scope.$watch('agentesSeleccionados', function () {
        if ($scope.agentesSeleccionados.length > 0) {
            let nuevos = [];
            $scope.agentesSeleccionados.forEach(element => {
                nuevos.push($scope.equivalencias.agentes[element]);
            });
            $scope.filtrosAplicados.agentes.titulo = "Agentes: " + nuevos.join(", ");
            $scope.filtrosAplicados.agentes.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.agentes.activo = false;
        }
    });
    $scope.$watch('estadosSeleccionados', function () {
        if ($scope.estadosSeleccionados.length > 0) {
            let nuevos = [];
            $scope.estadosSeleccionados.forEach(element => {
                nuevos.push($scope.equivalencias.estados[element]);
            });
            $scope.filtrosAplicados.estados.titulo = "Estados: " + nuevos.join(", ");
            $scope.filtrosAplicados.estados.activo = true;
            $scope.cuantosAplicados++;
        } else {
            $scope.filtrosAplicados.estados.activo = false;
        }
    });

    $scope.traerParametrizacion();

}]);
//_FIN_DE_ARCHIVO