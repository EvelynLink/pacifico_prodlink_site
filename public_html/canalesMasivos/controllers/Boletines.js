app.controller("Boletines",["$scope", "avisos", "simple", "pedido", "myIntercom", "$timeout",'$routeParams','$location',function($scope, avisos, simple, pedido, myIntercom, $timeout, $routeParams,$location) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? true : false;
    
    $scope.ruta = { tipo:"z" };
    $scope.waitWindow = false;
    $scope.ubicacion = {};
    
    //Llamadas -----------------------------------------------------------------
    //escuchamos cambios en la ruta
    $scope.suscripcion("ubicacionBoletines",function(){
        //carga ruta de masivos
        $scope.ubicacion = myIntercom.mensaje;
    });
    
    //Funciones ---------------------------------------------------------------
    $scope.irEnviar = function(){
        $location.path("/PanelControl/e");
    };
    $scope.irCategorias = function(){
        $location.path("/PanelControl/c");
    };
    $scope.irSuscripcion = function(){
        $location.path("/PanelControl/s");
    };
    $scope.irLimpieza = function(){
        $location.path("/PanelControl/l");
    };
    $scope.irConfiguracion = function(){
        $location.path("/PanelControl/f");
    };
    
    //Parámetros --------------------------------------------------------------
    if (typeof $routeParams["tipo"] !== 'undefined') {
        //Envío cabecera de ubicación
        $scope.ruta.tipo = $routeParams["tipo"];
        if ($routeParams["tipo"] === "e") {
            $scope.ruta.tipo = "e";
            $scope.ubicacion = [{ub: '<lang>Envío de boletines</lang>'}];
            myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }else if($routeParams["tipo"] === "c"){
            $scope.ruta.tipo = "c";
            $scope.ubicacion = [{ub: '<lang>Categorías y grupos de suscripción</lang>'}];
             myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }else if($routeParams["tipo"] === "s"){
            $scope.ruta.tipo = "s";
            $scope.ubicacion = [{ub: '<lang>Suscripción de Boletines</lang>'}];
             myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }else if($routeParams["tipo"] === "l"){
            $scope.ruta.tipo = "l";
            $scope.ubicacion = [{ub: '<lang>Limpieza de Suscripciones</lang>'}];
             myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }else if($routeParams["tipo"] === "f"){
            $scope.ruta.tipo = "f";
            $scope.ubicacion = [{ub: '<lang>Configuración</lang>'}];
             myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }else{
            $scope.ruta.tipo = "z";
            $scope.ubicacion = [{ub: '<lang>Panel de Control</lang>'}];
             myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
        }
    }
}]);
//templates virtuales asociados con HTML reales
angular.module("template/boletines/partials/Boletines.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/Boletines.html",
    '<v-template-url>boletines/partials/Boletines.html</v-template-url>');
}]);
angular.module("template/boletines/partials/BoletinesSeleccion.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/BoletinesSeleccion.html",
    '<v-template-url>boletines/partials/BoletinesSeleccion.html</v-template-url>');
}]);
angular.module("template/boletines/partials/BoletinesCategorias.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/BoletinesCategorias.html",
    '<v-template-url>boletines/partials/BoletinesCategorias.html</v-template-url>');
}]);
angular.module("template/boletines/partials/BoletinesAsociacion.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/BoletinesAsociacion.html",
    '<v-template-url>boletines/partials/BoletinesAsociacion.html</v-template-url>');
}]);
angular.module("template/boletines/partials/BoletinesSuscripcionLimpieza.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/BoletinesSuscripcionLimpieza.html",
    '<v-template-url>boletines/partials/BoletinesSuscripcionLimpieza.html</v-template-url>');
}]);
angular.module("template/boletines/partials/BoletinesConfiguracion.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/BoletinesConfiguracion.html",
    '<v-template-url>boletines/partials/BoletinesConfiguracion.html</v-template-url>');
}]);
//_FIN_DE_ARCHIVO