app.controller("cmFechasPeriodo", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$timeout', '$mdToast', '$mdDialog', function ($scope, avisos, simple, pedido, myIntercom, $timeout, $mdToast, $mdDialog) {
        window.Z_cbPanel = $scope;
        myIntercom.publica('ruta', menuSuperior);
        $scope.waitWindow = false;

        const URL = "../canalesMasivos/cmFechasPeriodoCtrl.php?act=";


         //Guarda las carteras
        $scope.carteras = {};
     

        //Variables para manejar el guardado del nuevo registro
        $scope.campania = {    
            carteraId:0,
            periodo:-1,
            fechaInicio:formatearFecha(new Date()),
            fechaFin:formatearFecha(new Date())

        };


        //Para que puedan modificar las fechas directamente de la tabla, despues de ser creada
        $scope.cambioFecha = {fechaAux: "", bandera: false};
        $scope.indexEdit = -1;
        var tipoFecha = '';
        $scope.registroEditar= {};
        var control = 0;

        //Para presentar el modal para configurar campañas
        $scope.verNuevaCampania = false;

        $scope.guardando = false;

        $scope.tabuCampanias = 0;

        //Variables para inicializar los datepickers
        $scope.booFechaInicialDisponible = false;
        $scope.booFechaFinalDisponible = false;
        $scope.booFechaFinalDisponible1 = false;

        //Funciones que llama cuando clickea los datapickers
        $scope.initInicio = function () {
            $scope.booFechaInicialDisponible = !$scope.booFechaInicialDisponible;
        };

        $scope.initFin = function () {
            $scope.booFechaFinalDisponible = !$scope.booFechaFinalDisponible;
        };

        $scope.initFinOp = function () {
            $scope.booFechaFinalDisponible1 = !$scope.booFechaFinalDisponible1;
        };

        //Configuraciones iniciales
        $scope.cargarFuentes = function () {
            $scope.cargarCarteras();
            $scope.cargarPeriodos(); 
        };



        //Funcion para formatear las fechas tipo date en yyyy-mm-dd
        function formatearFecha(date) {
            return dateFormateado = date.getFullYear().toString() + '-' + (date.getMonth() + 1).toString() + '-' + date.getDate().toString();
        }

     

        //Dialog general para presentar avisos 
        $scope.showConfirm = function (title, subtitle = '') {
            $mdDialog.show(
                    $mdDialog.alert()
                    .clickOutsideToClose(true)
                    .title(title)
                    .textContent(subtitle)
                    .ariaLabel('Alert Dialog Error')
                    .ok('CONTINUAR')
                    );
        };

        //Presenta toast
        $scope.showSimpleToast = function (nombreDiv, textoToast, delay = 0, direction = 'right') {
            var element = document.getElementById(nombreDiv);
            $mdToast.show(
                    $mdToast.simple()
                    .textContent(textoToast)
                    .position('top ' + direction)
                    .parent(element)
                    .hideDelay(delay)
                    .capsule(true)
                    );
        };




        $scope.cambioCartera = function (carteraId) {


            if (carteraId == 2122) {
                // Solo período 0
                $scope.periodosFiltrados = $scope.periodos.filter(function(p){
                    return p == "0";
                });

            } else {
                // Todos los períodos
                $scope.periodosFiltrados = $scope.periodos;    

            }


        };



         //Trae las carteras para la presentancion en el select principal
        $scope.cargarCarteras = function () {

            $scope.waitWindow = true;

            pedido.async({
                method: 'POST',
                url: URL + 'getListaCarteras',
                data: {}
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    //Todas las carteras para tabla
                    $scope.carteras = datos.resultado;
                    $scope.carterasActivas = $scope.carteras.filter(c => c.estado == 1);           
              
                }
                $scope.waitWindow = false;
            });
        };


        $scope.cargarPeriodos = function () {
           
            pedido.async({
                method: 'POST',
                url: URL + 'getListaPeriodos',
                data: {}
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    $scope.periodos = datos.resultado;
              
                }
                $scope.waitWindow = false;
            });
        };

        //Muestra el nombre de la cartera segun el id
        $scope.getNombreCartera = function (id) {
            if (!id || !$scope.carteras) return '';

            let c = $scope.carteras.find(x => x.id == id);
            return c ? c.nombre : id;
        };


        $scope.isInteger = function (value) {
            return Number(value) % 1 === 0;
        };

        //Oculta el toast de advertencia para los campos de afectación
        $scope.reiniciarToastCondicion = function () {
            $mdToast.hide(false);
        };

 
       
        //Cierra la ventana principal de configuración
        $scope.cerrarConfiguracion = function () {
                var objDiv = document.getElementById("pantallaNuevoRegistro");
                objDiv.scrollTop = objDiv.scrollHeight = 0;
                resetearObjetos();
                $scope.verNuevaCampania = false;
                $mdToast.hide(false);
     
        };

        //Guarda registro control_carga_periodo
        $scope.guardar = function () {
            console.log($scope.campania);
            if ($scope.campania.carteraId == 0 || $scope.campania.carteraId == "") {
                $scope.showConfirm("Seleccione una cartera.");
                return;
            }

            if ($scope.campania.periodo < 0 || $scope.campania.periodo == "") {
                $scope.showConfirm("Seleccione un período.");
                return;
            }

            pedido.async({
                method: 'POST',
                url: URL + 'guardarRegistro',
                data: {
                    fechasPeriodo: $scope.campania
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.resultado)) {
                    $scope.guardando = false;
                    $scope.waitWindow = false;

                    // El PHP devuelve {error} si falló, o texto si guardó
                    if (angular.isObject(datos.resultado)) {
                        $scope.showConfirm(datos.resultado.error || 'Error al guardar el registro.');
                        return;
                    }

                    $timeout(function () {
                        $scope.tabuCampanias++;
                    }, 300);
                    $scope.cerrarConfiguracion();
                    $scope.showConfirm(datos.resultado);
                }
            });
        };


        
        //Guarda el indicativo de que fecha está modificando
        $scope.focusEditarFecha = function (fecha) {
            tipoFecha = fecha;
        };
        


        //Guarda la edición del registro
        $scope.guardarEdicionRegistro = function () {
            console.log($scope.registroEditar);
           var confirm = $mdDialog.confirm()
                    .title("¿Desea modificar el registro?")
                    .textContent('')
                    .ariaLabel('Cambiar registro')
                    .targetEvent()
                    .ok('CONTINUAR')
                    .cancel('CANCELAR');
            $mdDialog.show(confirm).then(function () {
                $scope.waitWindow = true;
                pedido.async({
                    method: 'POST',
                    url: URL + 'editarRegistro',
                    data: {
                        campania: $scope.registroEditar
                    }
                }).then(function (datos) {
                    if (angular.isDefined(datos.respuesta)) {
                        $timeout(function () {
                            $scope.tabuCampanias++;
                        }, 300);

                        $scope.waitWindow = false;
                        $scope.showConfirm(datos.respuesta);
                    }
                    $scope.cancelarCambioFecha();
                });
            }, function () {
                //Cancelar
                $scope.cancelarCambioFecha();
            });
        };


        $scope.cambiarEstadoRegistro = function (x, estado) {
            //console.log(x);
            var texto = estado == 1 ? "activar" : "inactivar";
           var confirm = $mdDialog.confirm()
                    .title("¿Desea " + texto + " el registro?")
                    .textContent('')
                    .ariaLabel(texto)
                    .targetEvent()
                    .ok('CONTINUAR')
                    .cancel('CANCELAR');
            $mdDialog.show(confirm).then(function () {
                $scope.waitWindow = true;
                pedido.async({
                    method: 'POST',
                    url: URL + 'cambiarEstadoRegistro',
                    data: {
                        campania: x,
                        estado: estado
                    }
                }).then(function (datos) {
                    if (angular.isDefined(datos.respuesta)) {
                        $timeout(function () {
                            $scope.tabuCampanias++;
                        }, 300);

                        $scope.waitWindow = false;
                        $scope.showConfirm(datos.respuesta);
                    }
                    $scope.cancelarCambioFecha();
                });
            }, function () {
                //Cancelar
                $scope.cancelarCambioFecha();
            });
        };
        
        $scope.cancelarCambioFecha = function () {
            $scope.indexEdit = -1;
            control = 1;
            $scope.registroEditar = {};
        };
 
    

        $scope.editarRegistro = function (index, x) {
            $scope.indexEdit = index;
            $scope.registroEditar = Object.assign({}, x);

             $scope.cambioCartera($scope.registroEditar.cartera);
            //console.log(x);

            if (x.fecha) {
                $scope.registroEditar.fecha = new Date(x.fecha * 1000);
            }

            if (x.fechaFin) {
                $scope.registroEditar.fechaFin = new Date(x.fechaFin * 1000);
            }  

        };

        //Bloquea el scroll de la pantalla principal al presentar las ventanas emergentes
        function congelarScrollBody(i) {
            var body = document.getElementById('bodyPrincipal');
            if (i) {
                body.style.overflow = 'visible';
            } else {
                body.scrollTop = body.scrollHeight = 0;
                body.style.overflow = 'hidden';
            }
        }

        

        //Para presentar la ventana emergente de nueva campaña 
        $scope.nuevaConfiguracion = function () {
            $scope.waitWindow = true;

            //congelarScrollBody(0);

            $timeout(function () {
                $scope.waitWindow = false;
                $scope.verNuevaCampania = true;
            }, 500);

           
        };

        //Reinicia todas las variables
        function resetearObjetos() {

           $scope.campania.carteraId=0;
           $scope.campania.periodo=-1;
           $scope.campania.fechaInicio=formatearFecha(new Date());
           $scope.campania.fechaFin=formatearFecha(new Date());

        }

}]);


//_FIN_DE_ARCHIVO