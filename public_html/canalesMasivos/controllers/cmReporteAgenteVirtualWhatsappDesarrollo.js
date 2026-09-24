app.controller("cmReporteAgenteVirtualWhatsappDesarrollo", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.recargarTabula = 0;
    $scope.ultimaEjecucion = "";

    $scope.permisos = {
        "soySupervisor": false,
        "soyObservador": true,
        "soyDesarrollo": false
    };

    $scope.cargoTabula = function (filas, extra) {
        $scope.cargando = false;
        return filas;
    };

    $scope.actualizarTabula = function () {
        $scope.cargando = true;
        $scope.recargarTabula++;
    };

    $scope.hearthBeatServicio = function () {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmReporteAgenteVirtualWhatsappCtrl.php?act=hearthBeatServicio',
            data: {}
        }).then(function (response) {
            if (response?.hearthBeat !== undefined) {
                $scope.ultimaEjecucion = response.hearthBeat;
            }
        });
        $timeout(() => {
            $scope.hearthBeatServicio();
        }, 10000);
    };

    $scope.hearthBeatServicio();

}]);

//_FIN_DE_ARCHIVO

