app.controller("chatbotConf", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', function ($scope, avisos, simple, pedido, $timeout, myIntercom) {
        window.Z_cbPanel = $scope;
        //informamos la ruta al encabezado
        myIntercom.publica('ruta', menuSuperior);
//            $scope.activaFiltroPeriodo = 1;
//            $scope.controlador = {"nombre": "modelosPredictivos", "barra": 1, "buscadorCartera": 1, "cargandoBarra": 0, "recargaCartera": 1, "buscadorEstadoGestion": 1, "buscadorEstadoGestionNE": 1, "buscadorPeriodos": $scope.activaFiltroPeriodo, "buscadorGestionEfectiva": 1};
//            myIntercom.publica('controlador', $scope.controlador);
 

    $scope.controlador = {"nombre": "chatbotConf", "barra": 1, "buscadorCartera": 1, "cargandoBarra": 0, "recargaCartera": 1, "buscadorEstadoGestion": 0};
        myIntercom.publica('controlador', $scope.controlador);
    }]);

//_FIN_DE_ARCHIVO