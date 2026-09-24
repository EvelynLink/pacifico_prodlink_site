app.controller("cmConfiguracionTelefonos", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.tabuVariable = 0;
    $scope.filtros = {
        campania: "",
        cartera: ""
    };
    $scope.debouncer = null;
    $scope.carteraSeleccionada = {};
    $scope.campaniaSeleccionada = {};
    $scope.seleccionado = {
        "carteraSeleccionada": {},
        "campaniaSeleccionada": {}
    };
    $scope.listas = {
        "proveedores": [],
        "carteras": [],
        "campanias": []
    };

    $scope.verModalCartera = {
        evento: {},
        activo: false,
        seleccionado: {},
        desactiva: false,
        "exclusivo": false
    };

    $scope.verModalNuevo = {
        evento: {},
        activo: false,
        datos: {
            "numero": "",
            "proveedor": ""
        }
    };

    $scope.traerParametrizacion = () => {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=devolverParametrizacion',
            data: {}
        }).then((response) => {
            if (response?.proveedores !== undefined) {
                $scope.listas.proveedores = response.proveedores;
            }
            if (response?.carteras !== undefined) {
                $scope.listas.carteras = response.carteras;
            }
            if (response?.campanias !== undefined) {
                $scope.listas.campanias = response.campanias;
            }
        });
    };

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = function () {
        $scope.cargando = true;
        $scope.tabuVariable++;
    };

    $scope.configurarCampania = (tel) => {
        $scope.verModalCartera.activo = true;
        $scope.seleccionado = {
            "carteraSeleccionada": {},
            "campaniaSeleccionada": {}
        };

        let excarteras = tel.carteras.findIndex((el) => el.exclusivo);
        let excampanias = tel.campanias.findIndex((el) => el.exclusivo);

        if (excarteras >= 0 || excampanias >= 0) {
            $scope.verModalCartera.desactiva = true;
        } else {
            $scope.verModalCartera.desactiva = false;
        }
        $scope.verModalCartera.seleccionado = tel;
    };

    $scope.buscarFila = (id) => {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=buscarRelacionPorId',
            data: {
                "id": id
            }
        }).then((response) => {
            if (response?.fila !== undefined) {
                let excarteras = response.fila.carteras.findIndex((el) => el.exclusivo);
                let excampanias = response.fila.campanias.findIndex((el) => el.exclusivo);

                if (excarteras >= 0 || excampanias >= 0) {
                    $scope.verModalCartera.desactiva = true;
                } else {
                    $scope.verModalCartera.desactiva = false;
                }
                $scope.verModalCartera.seleccionado = response.fila;
            }
            $scope.cargando = false;
        });
    };

    $scope.relacionar = () => {
        let numero = $scope.verModalCartera.seleccionado["id"];
        let cartera = $scope.seleccionado.carteraSeleccionada["cobCartera_id"] !== undefined ? $scope.seleccionado.carteraSeleccionada["cobCartera_id"] : 0;
        let campania = $scope.seleccionado.campaniaSeleccionada["cobCarteraRamas_ramaIdfk"] !== undefined ? $scope.seleccionado.campaniaSeleccionada["cobCarteraRamas_ramaIdfk"] : 0;
        if (cartera === 0) {
            avisos.alerta("Error", "Seleccione una cartera");
            return;
        }
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=relacionar',
            data: {
                "numero": numero,
                "cartera": cartera,
                "campania": campania,
                "exclusivo": $scope.verModalCartera.exclusivo ? 1 : 0
            }
        }).then((response) => {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.tabuVariable++;
                $scope.seleccionado = {
                    "carteraSeleccionada": {},
                    "campaniaSeleccionada": {}
                };
                $scope.buscarFila($scope.verModalCartera.seleccionado.id);
            } else if (response?.error !== undefined) {
                avisos.alerta("Error", response.error);
                $scope.tabuVariable++;
                $scope.cargando = false;
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
                $scope.cargando = false;
            }
        });
    };

    $scope.eliminarRelacion = (fila, tipo) => {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=eliminarRelacion',
                data: {
                    idRelacionado: fila.idRelacionado,
                    tipo: tipo,
                    numero: $scope.verModalCartera.seleccionado.id
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    $scope.tabuVariable++;
                    $scope.buscarFila($scope.verModalCartera.seleccionado.id);
                } else if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                    $scope.tabuVariable++;
                    $scope.cargando = false;
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                    $scope.cargando = false;
                }
            });
        });
        avisos.confirma("Confirme", "Desea eliminar la relación?");
    };

    $scope.eliminarTelefono = (tel) => {
        avisos.setFuncion(function () {
            $scope.cargando = true;
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=eliminarTelefono',
                data: {
                    numero: tel.id
                }
            }).then(function (response) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    $scope.actualizarTabula();
                } else if (response?.error !== undefined) {
                    avisos.alerta("Error", response.error);
                } else {
                    avisos.alerta("Atención", "Sin respuesta del servidor");
                }
                $scope.cargando = false;
            });
        });
        avisos.confirma("Confirme", "Desea eliminar el número telefónico?");
    };

    $scope.nuevoNumero = () => {
        $scope.verModalNuevo.activo = true;
        $scope.verModalNuevo.datos = {
            "numero": "",
            "proveedor": ""
        };
    };

    $scope.guardarNuevoNumero = () => {
        if ($scope.verModalNuevo.datos.proveedor === "") {
            avisos.alerta("Error", "Seleccione un proveedor");
            return;
        }
        if ($scope.verModalNuevo.datos.numero === "") {
            avisos.alerta("Error", "Escriba el número");
            return;
        }
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=guardarNuevoTelefono',
            data: {
                proveedor: $scope.verModalNuevo.datos.proveedor,
                numero: $scope.verModalNuevo.datos.numero
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.verModalNuevo.activo = false;
                $scope.actualizarTabula();
            } else if (response?.error !== undefined) {
                avisos.alerta("Error", response.error);
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.aplicarFiltros = () => {
        clearTimeout($scope.debouncer);
        $scope.debouncer = $timeout(() => {
            $scope.actualizarTabula();
        }, 800);
    };

    $scope.quitarFiltros = () => {
        $scope.filtros = {
            cartera: "",
            campania: ""
        };
        $scope.debouncer = $timeout(() => {
            $scope.actualizarTabula();
        }, 500);
    };

    $scope.activarTelefono = (tel) => {
        console.log(tel);
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionTelefonosCtrl.php?act=activarTelefono',
            data: {
                activo: tel.activo ? 1 : 0,
                id: tel.id
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
                $scope.actualizarTabula();
            } else if (response?.error !== undefined) {
                avisos.alerta("Error", response.error);
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.traerParametrizacion();

}]);

//_FIN_DE_ARCHIVO

