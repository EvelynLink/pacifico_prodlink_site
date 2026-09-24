app.controller("agenteVirtualParam", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', '$parse', '$sce', function ($scope, avisos, simple, pedido, $timeout, myIntercom, $parse, $sce) {
    window.Z_cbPanel = $scope;
    myIntercom.publica('ruta', menuSuperior);
    $scope.tabuVariable = 0;
    $scope.editar = false;
    $scope.cargando = true;
    $scope.servidorGpu = false;
    $scope.modalNuevo = {
        titulo: "",
        evento: {},
        activo: false,
        tipo: ""
    };

    $scope.verModal = {
        titulo: "",
        evento: {},
        activo: false,
        tipo: ""
    };
    $scope.verModalPrompt = {
        evento: {},
        activo: false,
        datos: {
            prompt: "",
            saludo: "",
            agenteId: "",
            proveedor: ""
        }
    };
    $scope.verModalLlamada = {
        evento: {},
        activo: false,
        agente: "",
        numero: "",
        valoresReemplazo: [],
        nuevoDatos: [],
        idLlamada: "",
        detalleLlamada: {}
    };
    $scope.listas = {
        "modelos": [],
        "proveedores": [],
        "proveedoresInternos": [],
        "agentes": [
            { nombre: "Seleccione un proveedor", id: 0 }
        ],
        "telefonos": [
            { nombre: "Seleccione un proveedor", id: 0 }
        ],
        "voces": [
            { nombre: "Seleccione un proveedor", id: 0 }
        ],
        "tipos": [
            { nombre: "Seleccione un tipo", id: 0 }
        ]
    };

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = function () {
        $scope.cargando = true;
        $scope.tabuVariable++;
    };

    $scope.datosAV = {
        "agenteId": '',
        "agenteNombre": '',
        "apikey": '',
        "proveedor": '',
        "numero": '',
        "numeroId": '',
        "valores_reemplazo": [],
        "concurrencia": 1,
        "activo": 1,
        "temperatura": 0.5
    };

    // $scope.guarda = function () {
    //     avisos.setFuncion(function () {
    //         $scope.cargando = true;
    //         pedido.async({
    //             method: 'POST',
    //             url: '../canalesMasivos/agenteVirtualParam.php?act=guardar',
    //             data: $scope.datosAV
    //         }).then(function (response) {
    //             $scope.editar = false;
    //             $scope.actualizarTabula();
    //         });
    //     });
    //     avisos.confirma("Confirme", "Esta seguro de guardar el agente virtual?");
    // };

    $scope.guardarAgente = function () {

        if ($scope.datosAV.proveedor === "RETELL") {
            if ($scope.datosAV.subtipo === "whatsapp") {
                $scope.datosAV.numero = $scope.datosAV.numeroId;
            }

            if ($scope.datosAV.subtipo === "telefono") {
                $scope.datosAV.agenteNombre = $scope.listas.agentes.find(element => element.id == $scope.datosAV.agenteId).nombre;
                $scope.datosAV.numero = $scope.listas.telefonos.find(element => element.id == $scope.datosAV.numeroId).nombre;
            }
        }

        if ($scope.datosAV.proveedor === "LINK") {
            //if ($scope.datosAV.subtipo === "whatsapp") {
            $scope.datosAV.agenteNombre = $scope.listas.agentes.find(element => element.id == $scope.datosAV.agenteId).nombre;
            $scope.datosAV.numeroId = "+593999999999";
            //}
        }

        if ($scope.datosAV.proveedor == "") {
            avisos.alerta("Error", "Seleccione un proveedor");
            return;
        }
        if ($scope.datosAV.agenteId == "") {
            avisos.alerta("Error", "Seleccione un agente");
            return;
        }
        if ($scope.datosAV.agenteNombre == "") {
            avisos.alerta("Error", "Escriba el nombre del agente");
            return;
        }
        if ($scope.datosAV.numeroId == "") {
            avisos.alerta("Error", "Seleccione un teléfono");
            return;
        }
        if ($scope.datosAV.proveedor === "ELEVENLABS" && $scope.datosAV.vozId == "") {
            avisos.alerta("Error", "Seleccione una voz");
            return;
        }

        if ($scope.datosAV.proveedor === "ELEVENLABS") {
            $scope.datosAV.voz = $scope.listas.voces.find(element => element.id == $scope.datosAV.vozId).nombre;
        }

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=guardar',
            data: $scope.datosAV
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.actualizarTabula();
                $scope.verModal.activo = false;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
                $scope.cargando = false;
            }
        });
    };

    $scope.guardarAgenteInterno = function () {

        if ($scope.datosAV.proveedor == "") {
            avisos.alerta("Error", "Seleccione un proveedor");
            return;
        }
        if ($scope.datosAV.agenteId == "") {
            avisos.alerta("Error", "Escriba el id del agente");
            return;
        }
        if ($scope.datosAV.agenteNombre == "") {
            avisos.alerta("Error", "Escriba el nombre del agente");
            return;
        }
        if ($scope.datosAV.numeroId == "") {
            avisos.alerta("Error", "Escriba el número de teléfono");
            return;
        }
        if ($scope.datosAV.temperatura < 0 || $scope.datosAV.temperatura > 1) {
            avisos.alerta("Error", "La temperatura debe ser un valor entre 0 y 1");
            return;
        }
        if ($scope.datosAV.modelo == "") {
            avisos.alerta("Error", "Seleccione un modelo base");
            return;
        }
        if ($scope.datosAV.prompt == "") {
            avisos.alerta("Error", "Escriba el prompt para el agente");
            return;
        }

        $scope.datosAV.promptCodificado = $scope.toBase64($scope.datosAV.prompt);
        $scope.datosAV.saludosCodificado = window.btoa(JSON.stringify($scope.datosAV.saludos));

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=guardarAgenteInterno',
            data: { ...$scope.datosAV, gpu: $scope.servidorGpu }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.actualizarTabula();
                $scope.modalNuevo.activo = false;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
                $scope.cargando = false;
            }
        });
    };

    $scope.actualizarAgente = function () {
        if ($scope.datosAV.numeroId == "") {
            avisos.alerta("Error", "Seleccione un teléfono");
            return;
        }
        if ($scope.datosAV.proveedor === "ELEVENLABS" && $scope.datosAV.vozId == "") {
            avisos.alerta("Error", "Seleccione una voz");
            return;
        }

        if ($scope.datosAV.subtipo === "telefono") {
            $scope.datosAV.numero = $scope.listas.telefonos.find(element => element.id == $scope.datosAV.numeroId).nombre;
        }
        if ($scope.datosAV.proveedor === "ELEVENLABS") {
            $scope.datosAV.voz = $scope.listas.voces.find(element => element.id == $scope.datosAV.vozId).nombre;
        }
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=actualizar',
            data: { ...$scope.datosAV, gpu: $scope.servidorGpu }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.actualizarTabula();
                $scope.verModal.activo = false;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
                $scope.cargando = false;
            }
        });
    };

    // $scope.configurar = function (item) {
    //     $scope.editar = true;
    //     $scope.datosAV = {
    //         "agenteId": '',
    //         "agenteNombre": '',
    //         "apikey": '',
    //         "proveedor": '',
    //         "numero": '',
    //         "numeroId": '',
    //         "valores_reemplazo": [],
    //         "concurrencia": 1,
    //     };
    //     $timeout(function () {
    //         $scope.datosAV = item;
    //     }, 100);

    // };

    $scope.configurarAgente = function (item) {
        $scope.datosAV = item;
        if (item.subtipo === "telefono") {
            $scope.traerDetalleProveedor(true);
        } else if (item.subtipo === "whatsapp" && item.proveedor === "LINK") {
            $scope.traerDetalleProveedorInterno(true);
        } else {
            $scope.verModal.titulo = "Modificar " + $scope.datosAV.agenteNombre;
            $scope.verModal.activo = true;
            $scope.verModal.tipo = "modificar";
        }
    };

    $scope.configurarPrompt = function (item) {
        if (item.agentePrompt === undefined) {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/agenteVirtualParam.php?act=traerDetalleAgente',
                data: {
                    id: item.agenteId,
                    proveedor: item.proveedor
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    $scope.verModalPrompt.datos = {
                        prompt: window.atob(response.respuesta.prompt),
                        saludo: response.respuesta.saludo,
                        agenteId: item.agenteId,
                        proveedor: item.proveedor
                    };
                    $scope.verModalPrompt.activo = true;
                    $scope.cargando = false;
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        } else {
            $scope.verModalPrompt.datos = {
                prompt: $scope.fromBase64(item.agentePrompt),
                saludo: JSON.parse(window.atob(item.agenteSaludo)),
                agenteId: item.agenteId,
                proveedor: item.proveedor,
                temperatura: item.temperatura
            };
            $scope.verModalPrompt.activo = true;
        }
    };

    $scope.toBase64 = function (txt) {
        //return window.btoa(txt);
        return window.btoa(unescape(encodeURIComponent(txt)));
    }

    $scope.fromBase64 = function (txt) {
        //return window.atob(txt);
        return decodeURIComponent(escape(window.atob(txt)));
    }

    $scope.guardarPrompt = function () {
        if ($scope.verModalPrompt.datos.prompt == "") {
            avisos.alerta("Error", "No puede guardar un prompt vacío");
            return;
        }
        if ($scope.verModalPrompt.datos.temperatura < 0 || $scope.verModalPrompt.datos.temperatura > 1) {
            avisos.alerta("Error", "La temperatura debe ser un valor entre 0 y 1");
            return;
        }

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=actualizarPrompt',
            data: {
                proveedor: $scope.verModalPrompt.datos.proveedor,
                id: $scope.verModalPrompt.datos.agenteId,
                prompt: $scope.toBase64($scope.verModalPrompt.datos.prompt),
                saludo: window.btoa(JSON.stringify($scope.verModalPrompt.datos.saludo)),
                temperatura: $scope.verModalPrompt.datos.temperatura,
                gpu: $scope.servidorGpu
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.verModalPrompt.activo = false;
                $scope.actualizarTabula();
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
                $scope.cargando = false;
            }
        });
    };

    // $scope.nuevo = function () {
    //     $scope.editar = true;
    //     $scope.datosAV = {
    //         "agenteId": '',
    //         "agenteNombre": '',
    //         "apikey": '',
    //         "proveedor": '',
    //         "numero": '',
    //         "numeroId": '',
    //         "valores_reemplazo": [],
    //         "concurrencia": 1,
    //     };
    // };

    $scope.nuevoAgente = function () {
        $scope.datosAV = {
            "agenteId": '',
            "agenteNombre": '',
            "apikey": '',
            "proveedor": '',
            "numero": '',
            "numeroId": '',
            "valores_reemplazo": [],
            "concurrencia": 1,
        };
        $scope.verModal.titulo = "Nuevo agente externo";
        $scope.verModal.activo = true;
        $scope.verModal.tipo = "nuevo";
    };

    $scope.nuevoAgenteInterno = function () {
        $scope.datosAV = {
            "agenteId": '',
            "agenteNombre": '',
            "apikey": '',
            "proveedor": 'LINK',
            "numero": '',
            "numeroId": '',
            "valores_reemplazo": [],
            "concurrencia": 1,
            "prompt": "",
            "saludos": [],
            "subtipo": "",
            "modelo": "",
            "temperatura": 0.5
        };
        $scope.traerDetalleProveedorInterno(false);
        $scope.modalNuevo.titulo = "Nuevo agente Link";
        $scope.modalNuevo.activo = true;
        $scope.modalNuevo.tipo = "nuevo";
    };

    $scope.traerDetalleProveedor = function (modificar) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=traerDetalleProveedor',
            data: {
                id: $scope.datosAV.proveedor,
                gpu: $scope.servidorGpu
            }
        }).then(function (response) {
            if (response !== undefined) {

                $scope.listas.tipos = $scope.listas.proveedores.find(element => element.id == $scope.datosAV.proveedor).tipos;

                if (response.agentes !== undefined) {
                    $scope.listas.agentes = response.agentes;
                }
                if (response.telefonos !== undefined) {
                    $scope.listas.telefonos = response.telefonos;
                }
                if (response.voces !== undefined) {
                    $scope.listas.voces = response.voces;
                }
                if (response.concurrencia !== undefined) {
                    $scope.datosAV.concurrencia = response.concurrencia;
                }
                if (!modificar) {
                    $scope.datosAV.agenteId = "";
                    $scope.datosAV.numeroId = "";
                    $scope.datosAV.vozId = "";
                } else {
                    $scope.verModal.titulo = "Modificar " + $scope.datosAV.agenteNombre;
                    $scope.verModal.activo = true;
                    $scope.verModal.tipo = "modificar";
                }
                //$scope.datosAV.concurrencia = 0;
            }
            $scope.cargando = false;
        });
    };

    $scope.traerDetalleProveedorInterno = function (modificar) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=traerDetalleProveedor',
            data: {
                id: $scope.datosAV.proveedor,
                gpu: $scope.servidorGpu
            }
        }).then(function (response) {
            if (response !== undefined) {

                $scope.listas.tipos = $scope.listas.proveedoresInternos.find(element => element.id == $scope.datosAV.proveedor).tipos;
                if (response.agentes !== undefined) {
                    $scope.listas.agentes = response.agentes;
                }
                if (response.modelos !== undefined) {
                    $scope.listas.modelos = response.modelos;
                }
                if (response.telefonos !== undefined) {
                    $scope.listas.telefonos = response.telefonos;
                }
                if (response.voces !== undefined) {
                    $scope.listas.voces = response.voces;
                }
                if (response.concurrencia !== undefined) {
                    $scope.datosAV.concurrencia = response.concurrencia;
                }
                if (!modificar) {
                    $scope.datosAV.agenteId = "";
                    $scope.datosAV.numeroId = "";
                    $scope.datosAV.vozId = "";
                } else {
                    // $scope.modalNuevo.titulo = "Modificar " + $scope.datosAV.agenteNombre;
                    // $scope.modalNuevo.activo = true;
                    // $scope.modalNuevo.tipo = "modificar";
                    $scope.verModal.titulo = "Modificar " + $scope.datosAV.agenteNombre;
                    $scope.verModal.activo = true;
                    $scope.verModal.tipo = "modificar";
                }
                //$scope.datosAV.concurrencia = 0;
            }
            $scope.cargando = false;
        });
    };

    $scope.activarAgente = function (agente) {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=cambiarActivo',
            data: {
                id: agente.id,
                activo: agente.activo
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
    };

    $scope.eliminarAgente = function (agente) {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/agenteVirtualParam.php?act=eliminarAgente',
                data: {
                    id: agente.id,
                    gpu: $scope.servidorGpu
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
        avisos.confirma("Confirme", ["Esta seguro de eliminar el agente virtual?", "Cualquier asignación activa con este agente no se procesará."]);
    }

    $scope.generarLlamada = function (av) {

        $scope.verModalLlamada.agente = av.id;
        $scope.verModalLlamada.numero = "";
        $scope.verModalLlamada.valoresReemplazo = av.valores_reemplazo;
        $scope.verModalLlamada.nuevoDatos = [];
        $scope.verModalLlamada.idLlamada = "";
        $scope.verModalLlamada.detalleLlamada = {};
        av.valores_reemplazo.forEach(element => {
            $scope.verModalLlamada.nuevoDatos.push({ "nombre": element, valor: "" });
        });
        $scope.verModalLlamada.activo = true;
    };

    $scope.iniciarLlamada = function () {
        if ($scope.verModalLlamada.numero == "" || $scope.verModalLlamada.numero.substring(0, 5) !== "+5939") {
            avisos.alerta("Error", "Número de teléfono incorrecto");
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
                        url: '../canalesMasivos/agenteVirtualParam.php?act=iniciarLlamada',
                        data: {
                            agente: $scope.verModalLlamada.agente,
                            telefono: $scope.verModalLlamada.numero,
                            valores_reemplazo: $scope.verModalLlamada.nuevoDatos
                        }
                    }).then(function (response) {
                        if (response?.respuesta !== undefined) {
                            avisos.alerta("Atención", response.respuesta);
                            if (response.idLlamada !== undefined && response.idLlamada !== "") {
                                $scope.verModalLlamada.idLlamada = response.idLlamada;
                            }
                        } else {
                            avisos.alerta("Atención", "Sin respuesta del servidor");
                        }
                        $scope.cargando = false;
                    });
                }
            }
        });

    };

    $scope.verDetalleLlamada = function () {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=detalleLlamada',
            data: {
                agente: $scope.verModalLlamada.agente,
                telefono: $scope.verModalLlamada.numero,
                idLlamada: $scope.verModalLlamada.idLlamada
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                $scope.verModalLlamada.detalleLlamada = response.respuesta;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.obtenerParametrizacion = function () {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=devolverParametrizacion',
            data: {}
        }).then(function (response) {
            if (response !== undefined) {
                if (response.proveedores !== undefined) {
                    $scope.listas.proveedores = response.proveedores;
                }
                if (response.proveedoresInternos !== undefined) {
                    $scope.listas.proveedoresInternos = response.proveedoresInternos;
                }

                // if (response.tipos !== undefined) {
                //     $scope.listas.tipos = response.tipos;
                // }
            }
        });
    };

    $scope.traduceTipo = function (tipo) {
        if (tipo === "whatsapp") {
            return "WhatsApp";
        }
        if (tipo === "telefono") {
            return "Teléfono";
        }
    };

    $scope.agregarSaludo = function () {
        if ($scope.datosAV.nuevoSaludo !== '') {
            $scope.datosAV.nuevoSaludo = $scope.datosAV.nuevoSaludo.replaceAll("¿", "");
            $scope.datosAV.saludos.push($scope.datosAV.nuevoSaludo);
            $scope.datosAV.nuevoSaludo = "";
        }
    };

    $scope.eliminarSaludo = function (index) {
        $scope.datosAV.saludos.splice(index, 1);
    };

    $scope.agregarSaludoModifica = function () {
        if ($scope.datosAV.nuevoSaludo !== '') {
            $scope.datosAV.nuevoSaludo = $scope.datosAV.nuevoSaludo.replaceAll("¿", "");
            $scope.verModalPrompt.datos.saludo.push($scope.datosAV.nuevoSaludo);
            $scope.datosAV.nuevoSaludo = "";
        }
    };

    $scope.eliminarSaludoModifica = function (index) {
        $scope.verModalPrompt.datos.saludo.splice(index, 1);
    };

    $scope.generarId = function () {
        $scope.datosAV.agenteId = $scope.datosAV.agenteNombre.replaceAll(" ", "_");
    }

    $scope.agregarChip = (chip) => {
        let nueva = chip.replace(/[^a-zA-Z0-9]/g, '_');
        var idchip = $scope.datosAV.valores_reemplazo.indexOf(chip, 0);
        $scope.datosAV.valores_reemplazo[idchip] = nueva;
    }

    $scope.obtenerParametrizacion();

}]);
//_FIN_DE_ARCHIVO