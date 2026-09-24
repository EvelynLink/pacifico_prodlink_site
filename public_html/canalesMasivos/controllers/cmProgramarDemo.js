app.controller("cmProgramarDemo", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = false;
    $scope.programar = {
        telefono: "",
        nombre: "",
        llamada: true,
        whatsapp: true
    };

    $scope.programarDemo = function () {
        if ($scope.programar.telefono === "" || $scope.programar.telefono.length < 9 || $scope.programar.telefono.length > 10) {
            avisos.alerta("Error", "Teléfono incorrecto 001");
            return;
        } else {
            if ($scope.programar.telefono.substring(0, 2) !== "09" && $scope.programar.telefono.substring(0, 1) !== "9") {
                avisos.alerta("Error", "Teléfono incorrecto 002");
                return;
            }
        }

        if (!$scope.programar.llamada && !$scope.programar.whatsapp) {
            avisos.alerta("Error", "Seleccione si quiere llamar y/o mandar whatsapp");
            return;
        }

        if ($scope.programar.telefono.substring(0, 2) === "09") {
            $scope.programar.telefono = $scope.programar.telefono.substring(1);
        }
        if ($scope.programar.nombre === "") {
            $scope.programar.nombre = "Juan Perez";
        }

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/agenteVirtualParam.php?act=programarLlamada',
            data: $scope.programar
        }).then(function (response) {
            if (response !== undefined) {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                    //response.idLlamada
                    $scope.inicializarLlamada();
                } else {
                    if (response?.mensaje !== undefined) {
                        avisos.alerta("Error", response.mensaje);
                    } else {
                        avisos.alerta("Error", "No se pudo programar. Error[001]");
                    }
                    $scope.cargando = false;
                }
            } else {
                avisos.alerta("Error", "No se pudo programar. Error[002]");
            }
            $scope.cargando = false;
        });

    };

    $scope.inicializarLlamada = function () {
        $scope.programar = {
            telefono: "",
            nombre: "",
            llamada: true,
            whatsapp: true
        };
    };

}]);

//_FIN_DE_ARCHIVO

