app.controller("BoletinesRedesConf", ['$location', '$scope', 'avisos', 'simple', 'pedido', '$timeout', '$routeParams', 'myIntercom', '$debounce', '$mdSidenav', function ($location, $scope, avisos, simple, pedido, $timeout, $routeParams, myIntercom, $debounce, $mdSidenav) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? true : false;

    //Llamadas ----------------------------------------------------------------
    $scope.ubicacion = [{ub: '<lang>Facebook</lang>'}];
    myIntercom.publica('ubicacionBoletines',$scope.ubicacion);
    
    //VARIABLES
    $scope.mascara = false;
    $scope.muestra = true;
    $scope.fb = {url: "", msj: ""};
    $scope.config = {reload: 1};
    $scope.temp_form = "";
    $scope.form = {
        app: {},
        token: { op : false, data : {}, message : "Seleccione una aplicación para depurar su token" },
        business: {},
        adaccount: {},
        campaign: {},
        adset: {}
    };
    $scope.formData = {
        currencies : {}, timezones : {}
    };
    $scope.mask = {op: true, text: "Cargando configuración, por favor espere..."};
    $timeout(function () { $scope.mask.op = false }, 2000);
    //Declaracion de variable para popup de alert
    $scope.messageAlert = { act : "", show : false, title : "", message : "", custom : false, ops : false, noOps : true };
    //FUNCTIONS

    //FUNCIONES CONECTORES DE Create,Update,Delete que utilizan el servicio "simple"
    //función para inicializar un editor
    $scope.inicializaItem = function (item) {
        simple.inicializaItem(item);
    };
    //función para borrar y resetear un editor, sea de un registro existente o de uno nuevo
    $scope.reinicializaItem = function (item, lista) {
        simple.reinicializaItem(item, lista);
    };
    //función para ingresar una nueva App
    $scope.prepareNewApp = function (lista) {
        parametros = {
            vacio: {editame: true, id : "", secret : "", version : "", token : ""}
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    //función para ingresar una nueva Empresa
    $scope.prepareNewBusiness = function (lista) {
        parametros = {
            vacio: {editame: true, id : "", app_id : ""}
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    //función para ingresar una nueva Cuenta publicitaria
    $scope.prepareNewAdaccount = function (lista) {
        parametros = {
            vacio: {editame: true, id : "", business_id : ""}
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    //función para ingresar una nueva Campaña
    $scope.prepareNewCampaign = function (lista) {
        parametros = {
            vacio: {editame: true, id : "", adaccount_id : ""}
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    //función para ingresar un nuevo Conjunto de publicaciones
    $scope.prepareNewAdset = function (lista) {
        parametros = {
            vacio: {editame: true, id : "", campaign_id : ""}
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    //elimina una App
    $scope.deleteApp = function (item, lista, index) {
        parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=formCrud&op=deleteApp&index='+index,
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    //elimina una Empresa
    $scope.deleteBusiness = function (item, lista, index) {
        parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=formCrud&op=deleteBusiness&index='+index,
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    //elimina una cuenta publicitaria
    $scope.deleteAdaccount = function (item, lista, index) {
        parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=formCrud&op=deleteAdaccount&index='+index,
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    //elimina una campaña
    $scope.deleteCampaign = function (item, lista, index) {
        parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=formCrud&op=deleteCampaign&index='+index,
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    //elimina un conjunto de publicaciones
    $scope.deleteAdset = function (item, lista, index) {
        parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=formCrud&op=deleteAdset&index='+index,
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };

    $scope.verifyToken = function (save) {
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=verifyToken',
            data: { save : save },
            cache: false
        }).then(function (response) {
            if (response.answer.op || response.answer.op === true || response.answer.op == "true") {
                //CONECTADO
                if (save) {
                    angular.extend($scope.form[form], {msj: {class: "alert alert-warning fade in", text: response.answer.msj}, buttonText: "<i class='fa fa-floppy-o fa-fw'></i>Guardar", buttonDis: false});
                }
            } else {
                //SIN CONECCION
                angular.extend($scope.form[form], {msj: {class: "alert alert-warning fade in", text: response.answer.msj}, buttonText: "<i class='fa fa-floppy-o fa-fw'></i>Guardar", buttonDis: false});
            }
        });
    };

    $scope.structureApp = function (filas, extra) {
        $scope.form.app = filas;
        $scope.connectApp("all");
        return filas;
    };

    $scope.structureBusiness = function (filas, extra) {
        $scope.form.business = filas;
        $scope.connectBusiness("all");
        return filas;
    };

    $scope.structureAdaccount = function (filas, extra) {
        $scope.form.adaccount = filas;
        $scope.connectAdaccount("all");
        return filas;
    };

    $scope.structureCampaign = function (filas, extra) {
        $scope.form.campaign = filas;
        $scope.connectCampaign("all");
        return filas;
    };

    $scope.structureAdset = function (filas, extra) {
        $scope.form.adset = filas;
        $scope.connectAdset("all");
        return filas;
    };

    $scope.showApp = function (index) {
        angular.forEach($scope.form.app, function(value, key) {
            $scope.form.app[key].content.op = false;
        });
        $scope.form.app[index].content.op = true;
        $scope.tokenDebugger(index);
    };

    $scope.showBusiness = function (index) {
        angular.forEach($scope.form.business, function(value, key) {
            $scope.form.business[key].content.op = false;
        });
        $scope.form.business[index].content.op = true;
    };

    $scope.showAdaccount = function (index) {
        angular.forEach($scope.form.adaccount, function(value, key) {
            $scope.form.adaccount[key].content.op = false;
        });
        $scope.form.adaccount[index].content.op = true;
    };

    $scope.showCampaign = function (index) {
        angular.forEach($scope.form.campaign, function(value, key) {
            $scope.form.campaign[key].content.op = false;
        });
        $scope.form.campaign[index].content.op = true;
    };

    $scope.showAdset = function (index) {
        angular.forEach($scope.form.adset, function(value, key) {
            $scope.form.adset[key].content.op = false;
        });
        $scope.form.adset[index].content.op = true;
    };

    $scope.verifyInfo = function () {
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=verifyInfo',
            data: {},
            cache: false
        }).then(function (response) {
            //AJAX RESPONSE
            var hide_mask = false;
            $scope.form.app = response.answer.app;
            console.log('estructura con datos');
            console.log($scope.form.app);
            $scope.form.business.data = response.answer.business;
            $scope.form.adaccount.data = response.answer.adaccount;
            //Intenta conectar a Facebook
            $scope.connectApp();
            hide_mask = true;


            if (response.answer.connectBusiness || response.answer.connectBusiness == true || response.answer.connectBusiness == 'true') {
                //Intenta conectar a Facebook
                $scope.connectBusiness();
                hide_mask = true;
            }
            if (response.answer.connectAdaccount || response.answer.connectAdaccount == true || response.answer.connectAdaccount == 'true') {
                //Intenta conectar a Facebook
                $scope.connectAdaccount();
                hide_mask = true;
            }
            //Si entro en cualquiera de las acciones anteriores deshabilitara la mascara de espera
            if (hide_mask) {
                $scope.mask.op = false;
            }
        });
    };

    $scope.saveForm = function (form, item, index) {
        $scope.temp_form = form;
        var parametros = {
            url: '../boletines/BoletinesRedesConfCtrl.php?act=saveForm&form='+form+'&index='+index,
            onsuccess: function () {
                $scope.connect($scope.temp_form, index);
            }
        };
        simple.guardaItem(item, parametros);
    };

    $scope.connect = function (form, index) {
        switch (form) {
            case "app":
                $scope.connectApp(index);
                break;
            case "business":
                $scope.connectBusiness(index);
                break;
            case "adaccount":
                $scope.connectAdaccount(index);
                break;
            case "campaign":
                $scope.connectCampaign(index);
                break;
            case "adset":
                $scope.connectAdset(index);
                break;
            default:
              angular.extend($scope.form[form], {msj: {class: "alert alert-success fade in", text: "Información guardada satisfactoriamente"}, buttonText: "<i class='fa fa-floppy-o fa-fw'></i>Guardar", buttonDis: false});
        }
    };

    $scope.saveForm2 = function (form, data) {
//            var data = {};

        data = data.copy;

        $scope.form[form].buttonText = "<i class='fa fa-refresh fa-spin'></i>Guardando información...";
        $scope.form[form].buttonDis = true;
//            data = $scope.form[form];

        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=saveForm',
            data: { data : data, form : form },
            cache: false
        }).then(function (response) {
            //AJAX RESPONSE
            if (response.answer.op === true || response.answer.op == "true") {
                //PROCEDE
                console.log("Already saved Mongo");
                switch (form) {
                    case "app":
                        $scope.connectApp();
                        break;
                    case "business":
                        $scope.connectBusiness();
                        break;
                    case "adaccount":
                        $scope.connectAdaccount();
                        break;
                    default:
                      angular.extend($scope.form[form], {msj: {class: "alert alert-success fade in", text: "Información guardada satisfactoriamente"}, buttonText: "<i class='fa fa-floppy-o fa-fw'></i>Guardar", buttonDis: false});
                }
            } else {
                //SITUACION DETECTADA
                angular.extend($scope.form[form], {msj: {class: "alert alert-warning fade in", text: response.answer.msj}, buttonText: "<i class='fa fa-floppy-o fa-fw'></i>Guardar", buttonDis: false});
            }
        });
    };

    // <editor-fold defaultstate="collapsed" desc="Token Debugger">
    $scope.tokenDebugger = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=tokenDebugger',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            $scope.form.token = { op : false, data : {}, message : "Seleccione una aplicación para depurar su token" };
            if (response.answer.op == "true" || response.answer.op == true) {
                $scope.form.token = { op : true, data : response.answer.data, message : "" };
            } else {
                $scope.form.token = { op : false, data : [], message : response.answer.data };
            }
        });
    };
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="connectApp">
    $scope.connectApp = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=connectApp',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            var almost_one = false;
            angular.forEach(response.answer, function(value, key) {
                key = key.replace('#', '');
                if (who == 'all' || who == key) {
                    if (value['op'] === true || value['op'] == "true") {
                        //PROCEDE
                        angular.extend($scope.form.app[key], {light : { class : "fa fa-circle fa-mygreen status_dot", text : "Aplicación conectada" }, msj: {class: "alert alert-success fade in", text: "Aplicación conectada satisfactoriamente"}});
                        if (!almost_one) {
                            $scope.showApp(key);
                            almost_one = true;
                        }
                        $scope.form.app[key].content.data = value['msj'];
                        $timeout(function () { $scope.form.app[key].light.class = "fa fa-circle fa-mygreen"; }, 1000);
                    } else {
                        //SITUACION DETECTADA
                        $scope.form.app[key].content.op = false;
                        $scope.form.app[key].content.data = {};
                        angular.extend($scope.form.app[key], {light : { class : "fa fa-circle-o fa-mygreen status_dot", text : "Aplicación no conectada" }, msj: {class: "alert alert-warning fade in", text: value['msj']}});
                        $timeout(function () { $scope.form.app[key].light.class = "fa fa-circle-o fa-mygreen"; }, 1000);
                    }
                }
            });
            if (who !== "all") {
                $scope.tokenDebugger(who);
            }
            $scope.mask.op = false;
        });
    };
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="connectApp">
    $scope.connectBusiness = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=connectBusiness',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            var almost_one = false;
            angular.forEach(response.answer, function(value, key) {
                key = key.replace('#', '');
                if (who == 'all' || who == key) {
                    if (value['op'] === true || value['op'] == "true") {
                        //PROCEDE
                        angular.extend($scope.form.business[key], {light : { class : "fa fa-circle fa-mygreen status_dot", text : "Negocio conectado" }, msj: {class: "alert alert-success fade in", text: "Negocio conectado satisfactoriamente"}});
                        if (!almost_one) {
                            $scope.showBusiness(key);
                            almost_one = true;
                        }
                        $scope.form.business[key].content.data = value['msj'];
                        $timeout(function () { $scope.form.business[key].light.class = "fa fa-circle fa-mygreen"; }, 1000);
                    } else {
                        //SITUACION DETECTADA
                        $scope.form.business[key].content.op = false;
                        $scope.form.business[key].content.data = {};
                        angular.extend($scope.form.business[key], {light : { class : "fa fa-circle-o fa-mygreen status_dot", text : "Negocio no conectado" }, msj: {class: "alert alert-warning fade in", text: value['msj']}});
                        $timeout(function () { $scope.form.business[key].light.class = "fa fa-circle-o fa-mygreen"; }, 1000);
                    }
                }
            });
            $scope.mask.op = false;
        });
    };
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="connectAdaccount">
    $scope.connectAdaccount = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=connectAdaccount',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            var almost_one= false;
            angular.forEach(response.answer, function(value, key) {
                key = key.replace('#', '');
                if (who == 'all' || who == key) {
                    if (value['op'] === true || value['op'] == "true") {
                        //PROCEDE
                        angular.extend($scope.form.adaccount[key], {light : { class : "fa fa-circle fa-mygreen status_dot", text : "Cuenta publicitaria conectada" }, msj: {class: "alert alert-success fade in", text: "Cuenta publicitaria conectada satisfactoriamente"}});
                        if (!almost_one) {
                            $scope.showAdaccount(key);
                            almost_one = true;
                        }
                        $scope.form.adaccount[key].content.data = value['msj'];
                        $timeout(function () { $scope.form.adaccount[key].light.class = "fa fa-circle fa-mygreen"; }, 1000);
                    } else {
                        //SITUACION DETECTADA
                        $scope.form.adaccount[key].content.op = false;
                        $scope.form.adaccount[key].content.data = {};
                        angular.extend($scope.form.adaccount[key], {light : { class : "fa fa-circle-o fa-mygreen status_dot", text : "Cuenta publicitaria no conectada" }, msj: {class: "alert alert-warning fade in", text: value['msj']}});
                        $timeout(function () { $scope.form.adaccount[key].light.class = "fa fa-circle-o fa-mygreen"; }, 1000);
                    }
                }
            });
            $scope.mask.op = false;
        });
    };
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="connectCampaign">
    $scope.connectCampaign = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=connectCampaign',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            var almost_one= false;
            angular.forEach(response.answer, function(value, key) {
                key = key.replace('#', '');
                if (who == 'all' || who == key) {
                    if (value['op'] === true || value['op'] == "true") {
                        //PROCEDE
                        angular.extend($scope.form.campaign[key], {light : { class : "fa fa-circle fa-mygreen status_dot", text : "Campaña conectada" }, msj: {class: "alert alert-success fade in", text: "Campaña conectada satisfactoriamente"}});
                        if (!almost_one) {
                            $scope.showCampaign(key);
                            almost_one = true;
                        }
                        $scope.form.campaign[key].content.data = value['msj'];
                        $timeout(function () { $scope.form.campaign[key].light.class = "fa fa-circle fa-mygreen"; }, 1000);
                    } else {
                        //SITUACION DETECTADA
                        $scope.form.campaign[key].content.op = false;
                        $scope.form.campaign[key].content.data = {};
                        angular.extend($scope.form.campaign[key], {light : { class : "fa fa-circle-o fa-mygreen status_dot", text : "Campaña no conectada" }, msj: {class: "alert alert-warning fade in", text: value['msj']}});
                        $timeout(function () { $scope.form.campaign[key].light.class = "fa fa-circle-o fa-mygreen"; }, 1000);
                    }
                }
            });
            $scope.mask.op = false;
        });
    };
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="connectAdset">
    $scope.connectAdset = function (who) {
        who = who.toString();
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=connectAdset',
            data: { who : who }
        }).then(function (response) {
            //AJAX RESPONSE
            var almost_one= false;
            angular.forEach(response.answer, function(value, key) {
                key = key.replace('#', '');
                if (who == 'all' || who == key) {
                    if (value['op'] === true || value['op'] == "true") {
                        //PROCEDE
                        angular.extend($scope.form.adset[key], {light : { class : "fa fa-circle fa-mygreen status_dot", text : "Conjunto de anuncios conectado" }, msj: {class: "alert alert-success fade in", text: "Conjunto de anuncios conectado satisfactoriamente"}});
                        if (!almost_one) {
                            $scope.showAdset(key);
                            almost_one = true;
                        }
                        $scope.form.adset[key].content.data = value['msj'];
                        $timeout(function () { $scope.form.adset[key].light.class = "fa fa-circle fa-mygreen"; }, 1000);
                    } else {
                        //SITUACION DETECTADA
                        $scope.form.adset[key].content.op = false;
                        $scope.form.adset[key].content.data = {};
                        angular.extend($scope.form.adset[key], {light : { class : "fa fa-circle-o fa-mygreen status_dot", text : "Conjunto de anuncios no conectado" }, msj: {class: "alert alert-warning fade in", text: value['msj']}});
                        $timeout(function () { $scope.form.adset[key].light.class = "fa fa-circle-o fa-mygreen"; }, 1000);
                    }
                }
            });
            $scope.mask.op = false;
        });
    };
    // </editor-fold>

    $scope.buildAudience = function () {
        return pedido.async({
            method: 'POST',
            url: 'BoletinesRedesConfCtrl.php?act=formAudiencePre',
            data: {}
        }).then(function (response) {
            //AJAX RESPONSE
            $scope.formData.currencies = response.answer.currencies;
            $scope.formData.timezones = response.answer.timezones;

            $scope.launchAlert("", "NUEVA AUDIENCIA", "", true, true, true);
        });
    };

    /**
    * PERMITE MOSTRAR POPUP CON INFORMACION Y OPCIONES DINAMICAS
    * @param {string} act Accion que se utilizara al aceptar opciones en caso de que parametro ops este activo
    * @param {string} title Titulo o header que utilizara el popup
    * @param {message} message Texto que se monstrara en pop, pregunta o notificacion
    * @param {custom} custom Boolean indica si el mensaje viene en message o se arma en HTML
    * @param {boolean} ops Indica que el pop mostrara "aceptar" o "rechazar"
    */
    $scope.launchAlert = function (act, title, message, custom, ops, noOps) {
        $scope.messageAlert = { act : "", show : false, title : "", message : "", custom : false, ops : false, noOps : true };
        angular.extend($scope.messageAlert, {
            act : act,
            show : true,
            title : title,
            message : message,
            custom : custom,
            ops : ops,
            noOPs : noOps
        });
    };

    $scope.closeAlert = function () {
        angular.extend($scope.messageAlert, { act : "", show : false, title : "", message : "", custom: false, ops : false });
    };

    $scope.controlador = {nombre: "BoletinesRedesConf", barra: 0, buscadorCartera: 0, cargandoBarra: 0, recargaCartera: 0, create_pro: 0};
    myIntercom.publica('controlador', $scope.controlador);
    $timeout(function () {
        myIntercom.publica('controlador', $scope.controlador);
    }, 500);
}]);
app.filter('to_trusted', ['$sce', function ($sce) {
    return function (text) {
        return $sce.trustAsHtml(text);
    };
}]);
//_FIN_DE_ARCHIVO