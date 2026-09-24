app.controller("BoletinesReportes",['$scope', 'avisos', 'simple', '$sce', 'pedido', 'myIntercom', '$routeParams', '$rootScope','$timeout','$window',function($scope, avisos, simple, $sce, pedido, myIntercom, $routeParams, $rootScope, $timeout, $window) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? true : false;
    $scope.waitWindow = false;
    
    //Variables
    $scope.recargaBitacora = 0;
    $scope.Bitacora = {selecId:0,ver:false,evento:{},estadisticas:{archivo:"",programado:0,hora:0,leidos:0,abiertos:0,marcados:{}}};
    $scope.reporte = { suscripciones:0, contacto:{ tipos:{},tipo:'',recargaId:0 }};
    
    //Llamadas ----------------------------------------------------------------
    $scope.ubicacion = [{ub: '<lang>Reportes</lang>'}];
    myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
    
    //---------------- Bitácora ----------------------------------------------//
    $scope.cambiaEstadoEnvio=function(bitacoraId,estado){
        $scope.waitWindow = true;
        pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpBitacora&acc=cambiarEstadoEnvio",
                data: {
                    "bitacoraId": bitacoraId,
                    "estado": estado
                }
            }).then(function(data) {
                if(data.resultado.errores && data.resultado.errores !== ''){
                    avisos.alerta(data.resultado.errores);
                }else{
                    $scope.recargaBitacora++;
                }
                $scope.waitWindow = false;
            });
    };
    $scope.verDetalle = function(pos,bitacora,e){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpBitacora&acc=estadisticasEnvio",
            data: {
                "bitacoraId": bitacora.bbi_id
            }
        }).then(function(data) {
                $scope.Bitacora.estadisticas.archivo = bitacora.bbi_archivoEnvio;
                $scope.Bitacora.estadisticas.programado = bitacora.bbi_envioProgramado;
                $scope.Bitacora.estadisticas.hora = bitacora.bbi_horaEnvio;
                $scope.Bitacora.estadisticas.marcados = data.resultado.marcados;
                $scope.Bitacora.ver = true;
                $scope.Bitacora.evento = pos;
                $scope.Bitacora.selecId = bitacora.bbi_id;
                $scope.waitWindow = false;
        });
    };
    $scope.verDetalleDesuscripciones = function(bitacora,des){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpBitacora&acc=detalleDesuscritos",
            data: { bitacoraId: bitacora }
        }).then(function(data) {
                des.brg_jerarquias = data.resultado.jerarquias;
                if(des.brg_activo === '0'){des.brg_activo = 1;}else{des.brg_activo = 0;}
                $scope.Bitacora.selecId = bitacora.bbi_id;
                $scope.waitWindow = false;
        });
    };
    $scope.recargarBitacora = function(){
        $scope.recargaBitacora++;
    };
    //--------------- Suscripciones ------------------------------------------//
    $scope.eliminarCorreoYasociaciones = function(id){
        avisos.setFuncion(function(){
            if(id > 0){
                $scope.waitWindow = true;
                pedido.async({
                    method : 'POST',
                    url : '../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpSuscripciones&acc=eliminarCorreoYasociaciones',
                    data:{  id:id }
                }).then(function(data) {
                    if(angular.isDefined(data.resultado.error)){
                        $scope.waitWindow = false;
                        avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                    }else{
                        $scope.reporte.suscripciones++;
                        $scope.waitWindow = false;
                    }
                });   
            }
        });
        avisos.confirma("<lang>Seguro desea eliminar el correo y todo su historial de suscripción?</lang>",null);
    };
    //--------------- Contactos ----------------------------------------------//
    $scope.inicializarReporteContactos = function(){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpContactos&acc=getConfiguraciones",
            data: { }
        }).then(function(data) {
            $scope.reporte.contacto.tipos = data.resultado.tipos;
            $scope.waitWindow = false;
        });
    };
    $scope.recargarContactos = function(){
        $scope.reporte.contacto.recargaId++;
    };
}]);
//Controlador: Reporte  Bitácora Redes
app.controller("BoletinesReportesRedes",['$scope', 'avisos', 'simple', '$sce', 'pedido', 'myIntercom', '$routeParams', '$rootScope','$timeout','$window',function($scope, avisos, simple, $sce, pedido, myIntercom, $routeParams, $rootScope, $timeout, $window) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? false : true;
    $scope.waitWindow = false;
    
    //Variables
    $scope.Bitacora = {selecId:0,ver:false,evento:{},estadisticas:{archivo:"",programado:0,hora:0,leidos:0,abiertos:0,marcados:{}}};
    $scope.insightDetatil = { connect : false, almost_one : false, account_name : '', adset_name : '', campaign_name : '', name : '', recommendations : {}, effective_status : '', created_time : '', preview : '', filter : "last_30d", filterPreview : 'RIGHT_COLUMN_STANDARD', message : "Cargando datos, por favor espere..." };
    $scope.reloadDetail = 1;
    $scope.messageAlert = { act : "", show : false, title : "", message : "", ops : false, anyOptions : false, passthrough : {} };
    $scope.reloadMainTable = 1;
    $scope.filterOptions = [
        { key : 'today', value : 'Hóy' },
        { key : 'yesterday', value : 'Ayer' },
        { key : 'this_month', value : 'Este mes' },
        { key : 'last_3d', value : 'Últimos 3 días' },
        { key : 'last_7d', value : 'Últimos 7 días' },
        { key : 'last_14d', value : 'Últimos 14 días' },
        { key : 'last_30d', value : 'Últimos 30 días' },
        { key : 'lifetime', value : 'Desde el inicio' }
    ];
    $scope.previewOptions = [
        { key : 'RIGHT_COLUMN_STANDARD', value : 'Columna derecha estándar' },
        { key : 'DESKTOP_FEED_STANDARD', value : 'Escritorio estándar' },
        { key : 'MOBILE_FEED_STANDARD', value : 'Movil estándar' },
        { key : 'INSTAGRAM_STANDARD', value : 'Instagram estándar' }
    ];
    $scope.adStatus = [
        { key : 'ACTIVO', value : 'ACTIVO' },
        { key : 'PAUSADO', value : 'PAUSADO' }
    ];
    $scope.estadoTest = "ACTIVO";
    
    //---------------- Bitácora Redes ----------------------------------------//
    $scope.verDetalleRedes = function(pos,bitacora){
        $scope.waitWindow = true;
        $scope.Bitacora.selecId = bitacora.bbi_id;
        $scope.Bitacora.ver = true;
        $scope.Bitacora.evento = pos;
        $scope.insightDetatil = { connect : false, almost_one : false, account_name : '', adset_name : '', campaign_name : '', name : '', recommendations : {}, effective_status : '', created_time : '', preview : '', filter : "last_30d", filterPreview : 'RIGHT_COLUMN_STANDARD', message : "Cargando datos, por favor espere..." };
    };
    $scope.actualizarFiltroDetalle = function() {
        $scope.insightDetatil.message = "Cargando datos, por favor espere...";
        $timeout(function () {
            $scope.reloadDetail++;
        }, 500);
        $scope.insightDetatil.connect = true;
        $scope.insightDetatil.almost_one = false;
    };
    $scope.actualizarFiltroDetallePreview = function() {
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpBitacoraRedes&acc=actualizarPreview",
            data: {
                bitacoraId : $scope.Bitacora.selecId,
                filter : $scope.insightDetatil.filterPreview
            }
        }).then(function(data) {
            if (data.respuesta !== '') {
                $scope.insightDetatil.preview = window.atob(data.respuesta);
            } else {
                $scope.insightDetatil.preview = data;
            }
        });
    };
    $scope.preActualizarEstadoAd = function(bitacora_id, estado) {
        var passthrough = { id : bitacora_id, state : estado };
        $scope.launchAlert("actualizarEstadoAd", "MODIFICACIÓN DE ANUNCIO", "Esta acción podría implicar costos, esta seguro de continuar?", true, passthrough);
    };
    $scope.actualizarEstadoAd = function(passthrough) {
        var bitacora = passthrough.id;
        var estado = passthrough.state;
        console.log('bitacora id: '+bitacora+'    estado: '+estado);
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=procesarReporte&reporte=rpBitacoraRedes&acc=actualizarEstado",
            data: {
                bitacoraId : bitacora,
                estado : estado
            }
        }).then(function(data) {
            if (data.respuesta.op === 'true' || data.respuesta.op === true) {
                //Exito, procede a asignar valores
                $scope.messageAlert.message = 'El estado del anuncio ha sido modificado satisfactoriamente';
                $scope.messageAlert.anyOptions = false;
                $scope.messageAlert.ops = false;
                $scope.reloadMainTable++;
            } else {
                //No fue posible actualizar el status de Ad en Facebook
                $scope.messageAlert.message = 'Disculpe, no fue posible procesar su solicitud, intente mas tarde';
                $scope.messageAlert.anyOptions = false;
                $scope.messageAlert.ops = false;
                $scope.reloadMainTable++;
            }
        });
    };
    $scope.detallePostCargaRedes = function(filas, extra) {
        $scope.waitWindow = false;
        if (extra['connect'] === 'si') {
            $scope.insightDetatil.message = "Disculpe no hay datos estadísticos para este filtro";
            $scope.insightDetatil.connect = true;
            $scope.insightDetatil.name = extra['ad_info']['name'];
            $scope.insightDetatil.account_name = extra['ad_info']['adaccount'];
            $scope.insightDetatil.adset_name = extra['ad_info']['adset'];
            $scope.insightDetatil.campaign_name = extra['ad_info']['campaign'];
            $scope.insightDetatil.preview = extra['ad_info']['preview'];
            $scope.insightDetatil.created_time = extra['ad_info']['created_time'];
            $scope.insightDetatil.recommendations = extra['ad_info']['recommendations'];
            var almost_one = false;
            angular.forEach(filas, function(value, key) {
                if (angular.isDefined(value['account_name']) && angular.isDefined(value['adset_name']) && angular.isDefined(value['campaign_name'])) {
                    almost_one = true;
                }
            });
            $scope.insightDetatil.almost_one = (almost_one) ? true : false;
        } else {
            $scope.insightDetatil.message = "Disculpe, no ha sido posible conectar a Facebook";
            $scope.insightDetatil.connect = false;
            $scope.insightDetatil.almost_one = false;
        }
        return filas;
    };
    // <editor-fold defaultstate="collapsed" desc="Dynamic alert">
    /**
    * PERMITE MOSTRAR POPUP CON INFORMACION Y OPCIONES DINAMICAS
    * @param {string} act Accion que se utilizara al aceptar opciones en caso de que parametro ops este activo
    * @param {string} title Titulo o header que utilizara el popup
    * @param {message} message Texto que se monstrara en pop, pregunta o notificacion
    * @param {boolean} ops Indica que el pop mostrara "aceptar" o "rechazar" 
    */
    $scope.launchAlert = function (act, title, message, ops, passthrough) {
        $scope.messageAlert = { act : "", show : false, title : "", message : "", ops : false, anyOptions : false, passthrough : {} };
        angular.extend($scope.messageAlert, {
            act : act,
            show : true,
            title : title,
            message : message,
            ops : ops,
            passthrough : passthrough
        });
    };

    //RECIBE LAS OPCIONES ENVIADAS DESDE EL ALERT
    $scope.reciveOpAlert = function (passthrough) {
        switch ($scope.messageAlert.act) {
            case "actualizarEstadoAd":
                $scope.messageAlert.message = '<i class="fa fa-spinner fa-spin fa-lg fa-2x"></i>&nbsp;Conectando a Facebook, por favor espere...';
                $scope.messageAlert.anyOptions = true;
                $scope.actualizarEstadoAd(passthrough);
                break;
            default:
                $scope.closeAlert();
        };
//        $scope.closeAlert();
    };

    $scope.closeAlert = function () {
        angular.extend($scope.messageAlert, { act : "", show : false, title : "", message : "", ops : false, anyOptions : false, passthrough : {} });
//        $scope.mask.op = false;
    };
    // </editor-fold>
}]);
app.filter('to_trusted', ['$sce', function ($sce) {
    return function (text) {
        return $sce.trustAsHtml(text);
    };
}]);
//_FIN_DE_ARCHIVO