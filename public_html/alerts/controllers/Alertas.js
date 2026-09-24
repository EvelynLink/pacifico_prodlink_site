app.controller("Alertas", ['$scope', 'myIntercom', '$sce', 'pedido', 'avisos', '$routeParams', '$location', '$timeout', function ($scope, myIntercom, $sce, pedido, avisos, $routeParams, $location, $timeout) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = permisoAdministrador === 'write' ? true : false;
    $scope.permisoObservador = permisoObservador === 'write' ? true : false;
    $scope.ruta = { tipo: "z" };
    var hoy = Math.floor(Date.now() / 1000);
    var itemGrupo = { eve_id: "0", eve_familia: "<lang>Familia</lang>", eve_nombre: "<lang>Todas</lang>" };
    $scope.alertas = { idRecarga: 0, familias: {}, familiasLimpias: "", item: {}, fechaInicio: 0, fechaFin: hoy };
    $scope.esteDetalle = false;
    $scope.waitWindow = true;
    $scope.detalleSendgrid = false;
    $scope.detalleEnvioSendgrid = {};

    //Llamadas ----------------------------------------------------------------
    //escuchamos cambios en la ruta
    $scope.suscripcion("ubicacionAlerts", function () {
        //carga ruta de masivos
        $scope.ubicacion = myIntercom.mensaje;
    });

    //Funciones ---------------------------------------------------------------
    $scope.irHistorial = function () {
        $scope.ruta.tipo = "h";
        $scope.ubicacion = [{ ub: '<lang>Historial de Envío</lang>' }];
        myIntercom.publica('ubicacionAlerts', $scope.ubicacion);
        $location.path("/PanelControl/h");
    };
    $scope.irFamilias = function () {
        $scope.ruta.tipo = "f";
        $scope.ubicacion = [{ ub: '<lang>Familias de Envío</lang>' }];
        myIntercom.publica('ubicacionAlerts', $scope.ubicacion);
        $location.path("/PanelControl/f");
    };

    //--------------- HISTORIAL --------------------
    $scope.inicializar = function () {
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: '../alerts/AlertasCtrl.php?act=inicializar',
            data: {}
        }).then(function (data) {
            //$scope.waitWindow = false;
            $scope.alertas.familias["f" + itemGrupo.eve_id] = itemGrupo;
            if (angular.isDefined(data.resultado.fechaInicio)) {
                $scope.alertas.fechaInicio = data.resultado.fechaInicio;
            } else {
                $scope.alertas.fechaInicio = hoy;
            }
        });
    };
    $scope.agregarFamilia = function (item) {
        delete $scope.alertas.familias["f0"];
        $scope.alertas.familias["f" + item.eve_id] = item;
        $scope.limpiarFamilia();
        $scope.buscarEnvios();
    };
    $scope.eliminarFamilia = function (id) {
        delete $scope.alertas.familias[id];
        var lenReg = Object.keys($scope.alertas.familias).length;
        if (lenReg === 0) {
            $scope.alertas.familias["f" + itemGrupo.eve_id] = itemGrupo;
        }
        $scope.limpiarFamilia();
        $scope.buscarEnvios();
    };
    $scope.limpiarFamilia = function () {
        $scope.alertas.familiasLimpias = "";
        angular.forEach($scope.alertas.familias, function (element, index) {
            if (index !== 'f0') {
                if (angular.isDefined(element.eve_id) && element.eve_id > 0) {
                    $scope.alertas.familiasLimpias += element.eve_id + ",";
                }
            }
        });
    };
    $scope.buscarEnvios = function () {
        $timeout(function () {
            $scope.waitWindow = true;
            $scope.alertas.idRecarga++;
        }, 200);
    };
    //ejecutamos al inicio un llamado al servidor para obtener los datos iniciales en formato JSON
    $scope.adecuaDatos = function (filas) {
        angular.forEach(filas, function (fila, key) {
            if (fila["en_success"] == "1") {
                fila["en_success"] = "Ok";
            } else {
                fila["en_success"] = "No se envió";
            }
            if (fila["en_attachments"] != "") {
                var howmany = fila["en_attachments"].split(",").length;
                if (howmany == 1) {
                    fila["en_attachments"] = howmany + " archivo";
                } else {
                    fila["en_attachments"] = howmany + " archivos";
                }
            }
        });
        $scope.waitWindow = false;
        return filas;
    };
    $scope.muestraDetalle = function (alerta) {
        $scope.esteDetalle = true;
        $scope.estaAlerta = alerta;
        $scope.cleanhtml = $sce.trustAsHtml(alerta["en_html"]);
        $scope.destinatarios = alerta["en_to"].split(",");
    };
    $scope.ocultaDetalle = function () {
        $scope.esteDetalle = false;
        $scope.detalleSendgrid = false;
    };
    $scope.reenviar = function (alerta, email) {
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: '../alerts/AlertasCtrl.php?act=resendMail',
            data: { id: alerta["en_id"], email: email }
        }).then(function (data) {
            $scope.waitWindow = false;
            if (angular.isDefined(data.respuesta)) {  //alerte en caso de errores
                avisos.alerta(data.respuesta);
            }
        });
    };

    //---------------PANEL DE CONTROL --------------------
    //ejecutamos al inicio un llamado al servidor para obtener los datos iniciales en formato JSON
    $scope.adecuaEventos = function (filas) {
        angular.forEach(filas, function (fila, key) {
            if (fila["ev_adicionales"] !== null) {
                var vv = fila["ev_adicionales"].split("\n");
                fila.adicionales = new Array();
                angular.forEach(vv, function (ii) {
                    fila.adicionales.push({ "valor": ii });
                });
            }
        });
        return filas;
    };
    $scope.toggleactiva = function (evento, tipo, valor) {
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: '../alerts/AlertasCtrl.php?act=toggleActiva',
            data: {
                id: evento.ev_id, tipo: tipo, val: valor
            }
        }).then(function (data) {
            if (angular.isDefined(data.errores)) {  //alerte en caso de errores
                $scope.waitWindow = false;
                avisos.alerta(data.errores);
            } else {
                $scope.waitWindow = false;
                if (tipo === "normal") {
                    evento.ev_activoNormal = data.respuesta.toString();
                } else {
                    evento.ev_activoAdicional = data.respuesta.toString();
                }
            }
        });
    };
    $scope.eliminaradic = function (item, evento) {
        var lista = evento.adicionales;
        if (lista.length > 1) {
            lista.splice(lista.indexOf(item), 1);
        } else {
            item.valor = '';
        }
        $scope.guardaadic(evento);
    };
    $scope.aniadiradic = function (lista) {
        if (lista.indexOf({ "valor": "" }) == -1) {
            lista.push({ "valor": "" });
        }
    };
    $scope.guardaadic = function (evento) {
        $scope.waitWindow = true;
        var valor = "";
        angular.forEach(evento.adicionales, function (ii) {
            valor += ii.valor + "\n";
        });
        //ejecute la peticion.  La url debe saber responder en formato tabula-JSON
        pedido.async({
            method: 'POST',
            url: '../alerts/AlertasCtrl.php?act=guardaAdicionales',
            data: {
                id: evento.ev_id, adic: valor
            }
        }).then(function (data) {
            if (angular.isDefined(data.errores)) {  //alerte en caso de errores
                $scope.waitWindow = false;
                avisos.alerta(data.errores);
            } else {
                $scope.waitWindow = false;
                evento.ev_adicionales = data.valorGuardado.toString();
                var vv = evento.ev_adicionales.split("\n");
                evento.adicionales = new Array();
                angular.forEach(vv, function (ii) {
                    evento.adicionales.push({ "valor": ii });
                });
            }
        });
    };

    $scope.buscarDetalleSendgrid = function (idCorreo) {
        //si no tiene un punto no se puede buscar
        if (idCorreo.includes('.')) {
            $scope.waitWindow = true;
            pedido.async({
                method: 'POST',
                url: '../alerts/AlertasCtrl.php?act=buscarDetalleSendgrid',
                data: {
                    id: idCorreo
                }
            }).then(function (data) {
                if (angular.isDefined(data.errores)) {  //alerte en caso de errores
                    $scope.waitWindow = false;
                    avisos.alerta(data.errores);
                } else {
                    $scope.waitWindow = false;
                    $scope.detalleSendgrid = true;
                    $scope.detalleEnvioSendgrid = data.respuesta;
                    $scope.estaAlerta = {
                        en_to: data.respuesta.to_email
                    };

                }
            });
        } else {
            avisos.alerta("No se puede realizar la búsqueda en sendgrid");
        }
    };

    //Parámetros --------------------------------------------------------------
    if (typeof $routeParams["tipo"] !== 'undefined') {
        //Envío cabecera de ubicación
        $scope.ruta.tipo = $routeParams["tipo"];
        if ($routeParams["tipo"] === "h") {
            $scope.ubicacion = [{ ub: '<lang>Historial de Envío</lang>' }];
        } else if ($routeParams["tipo"] === "f") {
            $scope.ubicacion = [{ ub: '<lang>Familias de Envío</lang>' }];
        } else {
            $scope.ubicacion = [{ ub: '<lang>Panel de Control</lang>' }];
        }
        myIntercom.publica('ubicacionAlerts', $scope.ubicacion);
    }
}]);
//templates virtuales asociados con HTML reales
angular.module("template/alerts/partials/Alertas.html", []).run(["$templateCache", function ($templateCache) {
    $templateCache.put("template/alerts/partials/Alertas.html",
        '<v-template-url>alerts/partials/Alertas.html</v-template-url>');
}]);
angular.module("template/alerts/partials/PanelControl.html", []).run(["$templateCache", function ($templateCache) {
    $templateCache.put("template/alerts/partials/PanelControl.html",
        '<v-template-url>alerts/partials/PanelControl.html</v-template-url>');
}]);
angular.module("template/alerts/partials/AlertasHistorial.html", []).run(["$templateCache", function ($templateCache) {
    $templateCache.put("template/alerts/partials/AlertasHistorial.html",
        '<v-template-url>alerts/partials/AlertasHistorial.html</v-template-url>');
}]);
angular.module("template/alerts/partials/AlertasPanel.html", []).run(["$templateCache", function ($templateCache) {
    $templateCache.put("template/alerts/partials/AlertasPanel.html",
        '<v-template-url>alerts/partials/AlertasPanel.html</v-template-url>');
}]);
//_FIN_DE_ARCHIVO
