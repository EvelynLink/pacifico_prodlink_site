app.controller("cmLocalLLM", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', '$parse', '$sce', function ($scope, avisos, simple, pedido, $timeout, myIntercom, $parse, $sce) {
    window.Z_cbPanel = $scope;
    myIntercom.publica('ruta', menuSuperior);
    $scope.tabuVariable = 0;
    $scope.cargando = true;
    $scope.mostrarDinamicos = false;
    $scope.mostrarTranscripcion = true;
    $scope.nuevoMensaje = "";
    $scope.esperandoRespuesta = false;
    $scope.chatSeleccionado = {};
    $scope.dataNuevoChat = {};

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = function () {
        $scope.cargando = true;
        $scope.tabuVariable++;
    };

    $scope.abrirChat = function (chat) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLocalLLMCtrl.php?act=detalleChat',
            data: { id: chat.idChat }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.tabulas['chats'].filas.forEach(element => {
                    element.abierto = false;
                });
                chat.abierto = true;
                $scope.chatSeleccionado = response.respuesta;
                $scope.dataNuevoChat = {};
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Atención", response.error);
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                }
            }
            $scope.cargando = false;
        });
    };

    $scope.cerrarChat = function () {
        $scope.tabulas['chats'].filas.forEach(element => {
            element.abierto = false;
        });
        $scope.chatSeleccionado = {};
    };

    $scope.finalizarChat = function (chat) {
        if (chat.estado === "en_curso") {
            avisos.setFuncion(function () {
                $scope.cargando = true;
                pedido.async({
                    method: 'POST',
                    url: '../canalesMasivos/cmLocalLLMCtrl.php?act=finalizarChat',
                    data: {
                        id: chat.idChat
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
            avisos.confirma("Confirme", ["Esta seguro de finalizar el chat?", "Ya no se podrá interactuar en el chat."]);
        }
    };

    $scope.eliminarChat = function (chat) {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmLocalLLMCtrl.php?act=eliminarChat',
                data: {
                    id: chat.idChat
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
        avisos.confirma("Confirme", ["Esta seguro de eliminar el chat?", "Esta acción no se puede reversar."]);
    };

    $scope.analizarChat = function (chat) {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmLocalLLMCtrl.php?act=analizarChat',
                data: {
                    id: chat.idChat
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    //$scope.actualizarTabula();
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        });
        avisos.confirma("Confirme", "Esta seguro de analizar el chat?");
    };

    $scope.enviarMensaje = function () {
        if ($scope.nuevoMensaje.trim() !== "") {
            $scope.esperandoRespuesta = true;
            $scope.chatSeleccionado.mensajes.push({ rol: "usuario", "mensaje": $scope.nuevoMensaje, "fecha": "ahora" });
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmLocalLLMCtrl.php?act=obtenerRespuesta',
                data: { id: $scope.chatSeleccionado._id, mensaje: $scope.nuevoMensaje }
            }).then(function (response) {
                $scope.nuevoMensaje = "";
                if (response?.respuesta !== undefined && response.respuesta === "OK") {
                    pedido.async({
                        method: 'POST',
                        url: '../canalesMasivos/cmLocalLLMCtrl.php?act=detalleChat',
                        data: { id: $scope.chatSeleccionado._id }
                    }).then(function (response) {
                        if (response?.respuesta !== undefined) {
                            $scope.chatSeleccionado = response.respuesta;
                        } else {
                            if (response?.error !== undefined) {
                                avisos.alerta("Atención", response.error);
                            } else {
                                avisos.alerta("Atención", "Sin respuesta del servidor");
                            }
                        }
                        $scope.esperandoRespuesta = false;
                    });
                } else {
                    if (response?.error !== undefined) {
                        avisos.alerta("Atención", response.error);
                    } else {
                        avisos.alerta("Atención", "Sin respuesta del servidor");
                    }
                    $scope.esperandoRespuesta = false;
                }
            });
        }
    };

    $scope.nuevoChat = function () {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLocalLLMCtrl.php?act=inicializaNuevoChat',
            data: {}
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.chatSeleccionado = {};
                $scope.dataNuevoChat = {
                    agente: "",
                    listaAgentes: response.respuesta,
                    variablesDinamicas: []
                };
            }
            $scope.cargando = false;
        });
    };

    $scope.traerVariablesDinamicas = function () {
        let temp = $scope.dataNuevoChat.listaAgentes.find((element) => element.id === $scope.dataNuevoChat.agente)["valores_reemplazo"];
        temp.forEach(element => {
            let item = { "nombre": element, valor: "" };
            $scope.dataNuevoChat.variablesDinamicas.push(item);
        });

    };

    $scope.cerrarNuevoChat = function () {
        $scope.dataNuevoChat = {};
    };

    $scope.iniciarChat = function () {
        if ($scope.dataNuevoChat.agente === "") {
            avisos.alerta("Atención", "Seleccione el agente con el cual chatear");
            return;
        }
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmLocalLLMCtrl.php?act=crearNuevoChat',
            data: {
                agente: $scope.dataNuevoChat.agente,
                variablesDinamicas: $scope.dataNuevoChat.variablesDinamicas
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", "Chat creado con éxito, id: " + response.respuesta);
                $scope.actualizarTabula();
                $scope.cerrarNuevoChat();
            } else {
                if (response?.error !== undefined) {
                    avisos.alerta("Atención", response.error);
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                }
                $scope.cargando = false;
            }
        });
    };

}]);
//_FIN_DE_ARCHIVO