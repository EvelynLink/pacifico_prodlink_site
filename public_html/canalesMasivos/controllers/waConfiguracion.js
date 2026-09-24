app.controller("waConfiguracion", ['$scope', 'myIntercom', 'pedido', '$mdDialog', '$timeout', '$mdToast', function ($scope, myIntercom, pedido, $mdDialog, $timeout, $mdToast) {
    window.Z_Modules = $scope;
    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = false;
    $scope.carteras = [
        { cartera_id: 39, cartera_nombre: 'PACIFICO' },
    ];
    $scope.data = [];
    $scope.viewQR = false;
    $scope.viewQRpng = '';
    $scope.viewQRnum = '';
    $scope.viewQRhtml = '';
    $scope.waitTime = 16;

    $scope.estadoEditar = function (index) {
        $scope.data[index].editar = 1;
    };

    $scope.getConfiguraciones = function () {
        // Guardar nuevo o el editado
        $scope.cargando = 1;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/waConfiguracionCtrl.php?act=getConfiguraciones',
            data: {},
            cache: true
        }).then((data) => {
            $scope.cargando = 0;
            $scope.data = data.configuraciones;
        });
    };
    $scope.getConfiguraciones();
    $scope.guardar = function (index) {
        var validacion = Object.values($scope.data[index]).some((x) => x === '');
        if (validacion) {
            $mdToast.show(
                $mdToast.simple()
                    .textContent('Ingrese todos los datos!')
                    .hideDelay(3000)
            );
            return
        }
        // Guardar nuevo o el editado
        $scope.cargando = 1;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/waConfiguracionCtrl.php?act=saveOrUpdate',
            data: $scope.data[index],
            cache: true
        }).then((data) => {
            $scope.cargando = 0;
            $scope.data[index].id = data.configuracion;
            $scope.data[index].editar = 0;
        });
    };
    $scope.qr = function (index) {
        var validacion = Object.values($scope.data[index]).some((x) => x === '');
        if (validacion) {
            $mdToast.show(
                $mdToast.simple()
                    .textContent('Ingrese todos los datos!')
                    .hideDelay(3000)
            );
            return
        }
        // Guardar nuevo o el editado
        $scope.cargando = 1;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/waConfiguracionCtrl.php?act=getQR',
            data: $scope.data[index],
            cache: true
        }).then((data) => {
            console.log(data);
            $scope.cargando = 0;
            $scope.viewQR = true;
            $scope.waitTime = 16;
            $scope.viewQRnum = $scope.data[index].numero;
            $scope.viewQRpng = atob(data.datos)
            $scope.viewQRhtml = $scope.viewQRpng + "?v=" + Math.random();
            console.log('qr', $scope.viewQRpng);
            $scope.timeOutQR();
        });
    };

    $scope.timeOutQR = function () {
        $timeout(function () {
            if ($scope.waitTime == 0) {
                $scope.viewQR = false;
                return;
            } else {
                $timeout(function () {
                    $scope.viewQRhtml = $scope.viewQRpng + "?v=" + Math.random();
                    $scope.getConfiguraciones();
                    $timeout(function () {
                        angular.forEach($scope.data, function (item) {
                            if (item.numero === $scope.viewQRnum && item.estado === 'Activo') {
                                $scope.viewQR = false;
                                $scope.waitTime = 0;
                                return;
                            }
                        }, 3000);
                    });
                }, 100);
                if ($scope.waitTime > 0) {
                    $scope.waitTime--;
                }
            }
            $scope.timeOutQR();
        }, 5000);
    };

    $scope.getNombreCartera = function (index) {
        var i = $scope.carteras.find(x => x.cartera_id === $scope.data[index].cartera_id);
        if (i) $scope.data[index].cartera_nombre = i.cartera_nombre;
    }

    $scope.nuevo = function () {
        var i = $scope.data.findIndex(x => x.id == 'nuevo');
        if (i > -1) return;
        var nuevo = {
            id: 'nuevo',
            numero: '',
            cartera_id: '',
            cartera_nombre: '',
            descripcion: '',
            estado: 'Pendiente',
            editar: 1
        };
        $scope.data.unshift(nuevo);
    };

    $scope.borrar = function (index) {
        if ($scope.data[index].id === 'nuevo') {
            $scope.data.splice(index, 1);
            return
        }
        var confirm = $mdDialog.confirm()
            .title('Desea eliminar la configuración ?')
            .textContent('Si elimina no podra recuperar la configuración.')
            .ok('Eliminar')
            .cancel('Cancelar');

        $mdDialog.show(confirm).then(function () {
            $scope.cargando = 1;
            // Eliminar en el back
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/waConfiguracionCtrl.php?act=delete',
                data: $scope.data[index],
                cache: true
            }).then((data) => {
                $scope.data.splice(index, 1);
                $scope.cargando = 0;
            });
        }, function () {
            return;
        });
    };

}]);
//_FIN_DE_ARCHIVO