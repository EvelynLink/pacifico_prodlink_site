//añadimos un controlador a nuestra aplicación
app.controller("SuscripcionWeb",["$scope", "avisos", "simple", "$sce", "pedido", "myIntercom", "$routeParams", "$timeout", "$rootScope",function($scope, avisos, simple, $sce, pedido, myIntercom,$routeParams, $timeout,$rootScope){
    //informamos la ruta al encabezado
    myIntercom.publica('ruta', menuSuperior);
    $scope.correoId = 0;
    $scope.usrreg = "";
    $scope.bitacoraId = 0;
    $scope.autorizado = "1";
    $scope.sus = {nombre:"",identificacion:"",email:""};
    //Cargo valores de url
    if (typeof $routeParams["correoId"] !== 'undefined') {
        if ($routeParams["correoId"] && $routeParams["correoId"] > 0) {
            $scope.correoId = $routeParams["correoId"]; 
        }
    }
    if (typeof $routeParams["usrreg"] !== 'undefined') {
        if ($routeParams["usrreg"] && $routeParams["usrreg"] !== "") {
                $scope.usrreg = $routeParams["usrreg"]; 
        }
    }
    if (typeof $routeParams["bitacoraId"] !== 'undefined') {
        if ($routeParams["bitacoraId"] && $routeParams["bitacoraId"] !== "") {
                $scope.bitacoraId = $routeParams["bitacoraId"]; 
        }
    }
    
    //Evaluación de acceso
     pedido.async({
        method: 'POST',
        url: "../boletines/SuscripcionesWebCtrl.php?act=validaAcceso",
        data: {
            "correo": $scope.correoId,
            "usrreg": $scope.usrreg
        }
     }).then(function(data) {
            $scope.autorizado = data.resultado;
     });
     //Obtiene datos básicos de suscriptor
     pedido.async({
        method: 'POST',
        url: "../boletines/SuscripcionesWebCtrl.php?act=obtieneDatos",
        data: {
            "correo": $scope.correoId
        }
     }).then(function(data) {
            $scope.sus.nombre = data.resultado.boCorreo_nombre;
            $scope.sus.identificacion = data.resultado.boCorreo_identificacion; 
            $scope.sus.email= data.resultado.boCorreo_email; 
     });
     //Elimina suscripcion
     $scope.eliminarSuscripcion=function(item,lista){
         parametros = {
            url: '../boletines/SuscripcionesWebCtrl.php?act=eliminaSuscripcion&bitacoraId='+$scope.bitacoraId,
            confirma:true
        };
        simple.eliminaItem(item, lista, parametros);
     };
}]);
//_FIN_DE_ARCHIVO