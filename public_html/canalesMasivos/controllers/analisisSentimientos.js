app.controller("analisisSentimientos", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', function ($scope, avisos, simple, pedido, $timeout, myIntercom) {
        window.Z_cbPanel = $scope;
        //informamos la ruta al encabezado
        myIntercom.publica('ruta', menuSuperior);
        $scope.write = true;
        $scope.tabu = 0;
        $scope.tabuVariable = 0;
        $scope.tab3n = 0;
        $scope.detM3 = 0;
        $scope.nuevaCar = false;
        $scope.verBtnCartera = true;
        $scope.verCheckGuardar = true;
        $scope.edita = true;
        $scope.verBtnVar = true;
        $scope.obligatorio = false;
        $scope.tdatocanal = [{nombre: 'Facebook'}, {nombre: 'Twitter'}, {nombre: 'Chatbot'}];
        //$scope.tdatocartera=[{nombre:'CNT'},{nombre:'BANCO EC'},{nombre:'CNT COORPORATIVO'}]; 
        $scope.tdatocartera = [];

        $scope.inicializaItemVar = function (item) {
            simple.inicializaItem(item);
        };

        $scope.reinicializaItem = function (item, lista) {
            $scope.verBtnVar = true;
            $scope.edita = false;
            simple.reinicializaItem(item, lista);
            ///
            $scope.deslistaConfig3n();
        };

        $scope.traerCarteras = function () {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/analisisSentimientosCtrl.php?act=traerCarteras',
                data: {
                },
                cache: true
            }).then(function (response) {
                if (angular.isDefined(response.resultado)) {
                    $scope.tdatocartera = response.resultado;
                    console.log($scope.tdatocartera);
                }
            });
        };

        $scope.listaConfiguraciones = function (item, lista) {
            $scope.listaConf = true;
            $scope.tabu++;
            if (angular.isUndefined($scope.detM) || $scope.detM._id != item._id) {
                $scope.tabulas['cf'] = {"cuantos": "0", "filas": []};
                $scope.detM = item;
            }
        };

        $scope.listaConfiguraciones3n = function (item, lista) {
            $scope.listaConf = true;
            $scope.analisisSentimientos = item;
            $timeout(function () {
                $scope.tab3n++;
            }, 100);
        };

        $scope.listaConfiguraciones4n = function (item, lista) {
            $scope.listaConf4 = true;
            $scope.detM3 = item;
            $timeout(function () {
                $scope.tab3n++;
            }, 100);
        };

        $scope.deslistaConfig3n = function () {
            $scope.listaConf = false;
        };

        $scope.deslistaConfig4n = function () {
            $scope.listaConf4 = false;
        };

        $scope.guardaDef = function (item) {
              //comvierte objeto
            item.copy.asCartera = JSON.parse(item.copy.asCartera);
//            console.log(item.copy.modCartera.cobCartera_id);
//            return;
            item.copy.asCarteraId=item.copy.asCartera.cobCartera_id;
            item.copy.asCartera=item.copy.asCartera.cobCartera_nombre;
            
            if (item.copy.asCampania.trim() === '') {
                avisos.alerta('Debe ingresar un nombre de variable');
                return;
            }
            $scope.verCheckGuardar = false;
            parametros = {
                url: '../canalesMasivos/analisisSentimientosCtrl.php?act=guardaDef'
            };
            simple.guardaItem(item, parametros);
            $scope.verCheckGuardar = true;
            ///
            $scope.deslistaConfig3n();
            $timeout(function () {
                $scope.tabuVariable++;
            }, 100);
        };

        $scope.eliminaDef = function (item, lista) {
            parametros = {
                url: '../canalesMasivos/analisisSentimientosCtrl.php?act=borraDef',
                confirma: true
            };
            simple.eliminaItem(item, lista, parametros);
        };

        $scope.traducirEstadoVariable = function (filas, extra) {
            angular.forEach(filas, function (item) {
                if (item.asEstado == "1") {
                    item.asEstado = true;
                } else {
                    item.asEstado = false;
                }
            });
            return filas;
        };

        $scope.controlador = {"nombre": "analisisSentimientos", "barra": 1, "buscadorCartera": 1, "cargandoBarra": 0, "recargaCartera": 1, "buscadorEstadoGestion": 0};
        $timeout(function () {
            myIntercom.publica('controlador', $scope.controlador);
        }, 1000);


    }]);

//_FIN_DE_ARCHIVO