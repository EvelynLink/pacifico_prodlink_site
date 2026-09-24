app.controller("agenteVirtual", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', function ($scope, avisos, simple, pedido, $timeout, myIntercom) {
    window.Z_cbPanel = $scope;
    //informamos la ruta al encabezado
    myIntercom.publica('ruta', menuSuperior);

    $scope.cargando = true;
    $scope.llamada = {};
    $scope.recargarTabula = 0;

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.opciones = {
        agentes: [
            { codigo: "CaJslL1xziwefCeTNzHv", nombre: "Cristina Campos" },
            { codigo: "YExhVa4bZONzeingloMX", nombre: "Juan Carlos" },
            { codigo: "tomkxGQGz4b1kE0EM722", nombre: "Mario" },
            { codigo: "a0MaQpDjx7p7bZmqzFp1", nombre: "Gaby" },
            { codigo: "qyrGJ30fywarSl4a3pNp", nombre: "Milli" },
            { codigo: "rEVYTKPqwSMhytFPayIb", nombre: "Sandra" },
            { codigo: "qHkrJuifPpn95wK3rm2A", nombre: "Andrea" },
            { codigo: "zl1Ut8dvwcVSuQSB9XkG", nombre: "Ninoska" },
            { codigo: "crQgCQuWgUucmYHEPsrB", nombre: "Fran" },

        ],
        tipos: [
            { codigo: "V9lUDX9CrYC3kdDCdf7r", nombre: "Agente virtual" },
            { codigo: "ptjd6I7lQo5XeSHV4wo7", nombre: "Agente emapar" },
            { codigo: "agent_01jvmgj7kpffmtpnj2thmbsynd", nombre: "Agente Banco" },
            { codigo: "agent_01jw6kwxyzfywvvnaqws086mns", nombre: "Agente Banco Premora" },
            { codigo: "agent_01jw6p8xv5fwkv1tfg3p6mbn2t", nombre: "Agente Banco Premora Reestructura" },
        ],
        telefonos: [
            { codigo: "OZydsR1ZaxbRNLKcagey", nombre: "+593 2 400 4667" }
        ]
    };

    $scope.inicializarLlamada = function () {
        $scope.llamada = {
            telefono: "",
            nombre: "",
            nombre_deudor: "",
            nombre_cartera: "Empresa municipal de Agua Potable de Riobamba",
            nro_cuotas_vencidas: 0,
            dias_mora: 0,
            monto_total_vencido: 0,
            nro_operacion: "",
            ruc_empresa: "1715462896001",
            nombre_empresa: "Empresa Municipal de Agua Potable de Riobamba",
            generoAgente: $scope.opciones.agentes[0].codigo,
            tipoAgente: $scope.opciones.tipos[0].codigo,
            dia_corte: 5,
            direccion: "10 de agosto 12-22 y García Moreno",
            saludo: "",
            conversacion: "",
            numero_telefono_agente: $scope.opciones.telefonos[0].codigo,
        };
    };

    $scope.hacerLlamada = function () {
        if ($scope.llamada.telefono === "" || $scope.llamada.telefono.length !== 9) {
            avisos.alerta("Error", "Teléfono incorrecto");
            return;
        } else {
            if ($scope.llamada.telefono.substring(0, 1) !== "9") {
                avisos.alerta("Error", "Teléfono incorrecto");
                return;
            }
        }
        if ($scope.llamada.nombre === "") {
            avisos.alerta("Error", "Llene nombre");
            return;
        }
        if ($scope.llamada.nombre_cartera === "") {
            avisos.alerta("Error", "Llene cartera");
            return;
        }
        if ($scope.llamada.nro_cuotas_vencidas === "" || $scope.llamada.nro_cuotas_vencidas < 1) {
            avisos.alerta("Error", "Indique número de cuotas vencidas");
            return;
        }
        if ($scope.llamada.dias_mora === "" || $scope.llamada.dias_mora < 1) {
            avisos.alerta("Error", "Indique días de mora");
            return;
        }
        if ($scope.llamada.monto_total_vencido === "" || $scope.llamada.monto_total_vencido < 1) {
            avisos.alerta("Error", "Indique monto vencido");
            return;
        }
        if ($scope.llamada.dia_corte === "" || $scope.llamada.dia_corte < 1) {
            avisos.alerta("Error", "Indique el día corte");
            return;
        }
        if ($scope.llamada.direccion === "") {
            avisos.alerta("Error", "Indique la dirección");
            return;
        }
        if ($scope.llamada.ruc_empresa === "") {
            avisos.alerta("Error", "Llene RUC empresa");
            return;
        }
        if ($scope.llamada.nombre_empresa === "") {
            avisos.alerta("Error", "Llene nombre empresa");
            return;
        }

        $scope.waitWindows = true;
        $scope.llamada.nombre_deudor = $scope.llamada.nombre;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualCtrl.php?act=generarLlamada',
            data: $scope.llamada
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.estado !== undefined && response.estado === "OK") {
                    avisos.alerta("Atención", ["Llamada iniciada con éxito", response.datos.callSid]);
                    $scope.inicializarLlamada();
                } else {
                    if (response?.mensaje !== undefined) {
                        avisos.alerta("Error", response.mensaje);
                    } else {
                        avisos.alerta("Error", "No hubo respuesta del servidor");
                    }
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.waitWindows = false;
        });
    };

    $scope.obtenerDetalle = function (item) {

        $scope.waitWindows = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualCtrl.php?act=obtenerDetalle',
            data: {
                id: item.id,
                conv: item.resultado.datos.conversation_id
            }
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.respuesta !== undefined && response?.respuesta !== "") {
                    //avisos.alerta("Atención", response.respuesta.status);
                    item.detalle = response.respuesta;
                    item.mostrarDetalle = true;
                } else {
                    if (response?.error !== undefined) {
                        avisos.alerta("Error", response.error);
                    } else {
                        avisos.alerta("Error", "No hubo respuesta del servidor");
                    }
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.waitWindows = false;
        });
    };

    $scope.traerAudio = function (item) {

        $scope.waitWindows = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualCtrl.php?act=obtenerAudio',
            data: {
                id: item.id,
                conv: item.resultado.conversation_id
            }
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", ["Audio encontrado", response.respuesta]);
                    window.open(response.respuesta, '_blank');
                } else {
                    if (response?.error !== undefined) {
                        avisos.alerta("Error", response.error);
                    } else {
                        avisos.alerta("Error", "No hubo respuesta del servidor");
                    }
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.waitWindows = false;
        });
    };

    $scope.eliminarLlamada = function (item) {
        avisos.setFuncion(function () {
            $scope.waitWindows = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/agenteVirtualCtrl.php?act=eliminarLlamada',
                data: {
                    id: item.id,
                    conv: item.resultado.conversation_id
                }
            }).then(function (response) {
                if (response !== undefined) {
                    if (response?.respuesta !== undefined && response?.respuesta !== "") {
                        avisos.alerta("Atención", response.respuesta.mensaje);
                        $scope.recargarTabula++;
                    } else {
                        if (response?.error !== undefined) {
                            avisos.alerta("Error", response.error);
                        } else {
                            avisos.alerta("Error", "No hubo respuesta del servidor");
                        }
                        $scope.waitWindows = false;
                    }
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }

            });
        });
        avisos.confirma("Confirme", "Desea eliminar?");
    };

    $scope.inicializarLlamada();

}]);

//_FIN_DE_ARCHIVO