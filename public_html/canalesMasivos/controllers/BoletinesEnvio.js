app.controller("BoletinesEnvio",['$scope', 'avisos', 'simple', '$sce', 'pedido', 'myIntercom', '$routeParams', '$rootScope','$timeout','$window',function($scope, avisos, simple, $sce, pedido, myIntercom, $routeParams, $rootScope, $timeout, $window) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? true : false;
    
    //Variables
    $scope.tipoPlantilla = [{id:'url',nombre:'<lang>Especificar Dirección</lang>'},{id:'editor',nombre:'<lang>Editor de Contenidos</lang>'}];
    $scope.boletin = { envioManual:false, actual:{},listaMarcados:[],listaMarcadosVista:[],serieActual:'',
                       suscritos:{ envios:{},enviosTotal:0,paginaEnvio:0,totalPaginas:0 },
                       listaEnvios:[],activaListaEnvio:false,recargaActivos:0,
                       archivo:{ seleccionado:{ id:''} },
                       correos:""
                     };
    $scope.editorAntiguo = { activo:false };
    $scope.tipoEnvio = [{id:'correo',nombre:'<lang>Correo Electrónico</lang>'},
                        {id:'facebook',nombre:'<lang>Facebook</lang>'},
                        {id:'sms',nombre:'<lang>SMS</lang>'}
                       ];
    $scope.tipoOrigen = [{id:'modulo',nombre:'<lang>Módulo Boletines</lang>'},
                        {id:'archivo',nombre:'<lang>Archivo</lang>'}
                        ];
    $scope.catalogos = { pluginEnvio:{},archivos:{} };
    $scope.conexionesRedes = {};
    //grupos y categorías
    $scope.cat = {
        verCategoriaMenu: 0,
        verCategorias: 1,
        recargar: 0,
        cualAbrir: 0,
        soyAdministrador: $rootScope.datosWrite
    };
    $scope.enEdicion = {};
    
    //------------------------ Funciones --------------------------------------
    $scope.inicializarEnvio = function(){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=inicializarEnvio",
            data: {}
        }).then(function(data) {
            if (angular.isDefined(data.resultado.errores)) {
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>",data.errores);
            } else {
                $scope.conexionesRedes = data.resultado.conexiones;
                $scope.catalogos.pluginEnvio = data.resultado.plugins;
                $scope.waitWindow = false;
            }
        });
    };
    $scope.nuevoBoletin = function(){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=nuevoBoletin",
            data: {}
        }).then(function(data) {
            if (angular.isDefined(data.resultado.errores)) {
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>",data.errores);
            } else {
                $scope.boletin.recargaActivos++;
                $scope.waitWindow = false;
            }
        });
    };
    $scope.actualizarBoletin = function(item) {
        item.categoria_id = $scope.editorAntiguo.idCategoria;
        item.copy.categoria_id = $scope.editorAntiguo.idCategoria;
        $scope.waitWindow = true;
        parametros = {
            url: '../boletines/BoletinesCtrl.php?act=actualizarBoletin',
            onsuccess: function(item, data) {
                if(angular.isDefined(data.resultado.errores)){
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                }else{
                    //Nombre de boletín actual
                    item = data.resultado;
                    //$scope.boletin.actual.nombreVistaPrevia = data.resultado.boc_nombre;
                    $scope.boletin.actual = {};
                    $scope.boletin.recargaActivos=$scope.boletin.recargaActivos+1;
                    $scope.waitWindow = false;
                }
            }
        };
        simple.guardaItem(item, parametros);
    };
    $scope.eliminarBoletin = function(id){
        avisos.setFuncion(function(){
            $scope.waitWindow = true;
            pedido.async({
                method : 'POST',
                url : '../boletines/BoletinesCtrl.php?act=eliminarBoletin',
                data:{ id:id }
            }).then(function(data) {
                if(angular.isDefined(data.resultado.error)){
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                }else{
                    $scope.boletin.recargaActivos++;
                    $scope.waitWindow = false;
                }
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar el boletín?, se eliminarán las bitácoras relacionadas</lang>",null);
    };
    $scope.cambiarEstadoBoletin = function(id){
        avisos.setFuncion(function(){
            $scope.waitWindow = true;
            pedido.async({
                method : 'POST',
                url : '../boletines/BoletinesCtrl.php?act=cambiarEstadoBoletin',
                data:{ id:id }
            }).then(function(data) {
                if(angular.isDefined(data.resultado.error)){
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                }else{
                    $scope.boletin.recargaActivos++;
                    $scope.waitWindow = false;
                }
            });
        });
        avisos.confirma("<lang>Seguro desea ocultar boletín?</lang>",null);
    };
    $scope.abrirEditorBoletin = function() {
        $window.open("../web/mapaEditor.php?c=945",'_blank');
    };
    $scope.editarBoletin = function(item) {
        $window.open("../web/cmsControler.php?act=toggleCaptureYes&categoriaId="+ item.id,'_blank');
    };
    $scope.actualizarHtml = function(pr){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=actualizarContenidoHTML",
            data: { id:pr.boc_id }
        }).then(function(data) {
            if (angular.isDefined(data.resultado.errores)){
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>",data.errores);
            } else {
                pr.boc_contenido=data.resultado.html;
                pr.seleccionada="fa fa-square-o fa-green fa-fw";
                $scope.boletin.actual = {};
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>","<lang>El contenido HTML fue actualizado correctamente</lang>");
            }
        });
    };
    $scope.seleccionaBoletin = function(item){
        if(item.seleccionada === "fa fa-check-square-o fa-green fa-fw"){
            $scope.boletin.actual = {};
            $scope.boletin.recargaActivos++;
        }else{
            $scope.boletin.actual.boletinEnVistaPrevia = item.boc_id;
            $scope.boletin.actual.nombreVistaPrevia = item.boc_nombre;
            $scope.boletin.actual.tipo = item.boc_tipo;
            $scope.boletin.actual.origen = item.boc_origen;
            $scope.boletin.recargaActivos++;
            if (item.boc_tipo === 'correo' && angular.isDefined(item.boc_contenido) && item.boc_contenido !== null && item.boc_contenido !== "") {
                $scope.boletin.actual.contenido = $sce.trustAsHtml(item.boc_contenido);
            }else if(item.boc_tipo === 'facebook' && angular.isDefined(item.boc_url) && item.boc_url !== null && item.boc_url !== ""){
                var html = '<img ng-src="'+item.boc_url+'">';
                $scope.boletin.actual.contenido = $sce.trustAsHtml(html);
            }else if(item.boc_tipo === 'sms' && angular.isDefined(item.boc_mensaje) && item.boc_mensaje !== null && item.boc_mensaje !== ""){
                $scope.boletin.actual.contenido = $sce.trustAsHtml(item.boc_mensaje);
            } else {
                $scope.boletin.actual = {};
                avisos.alerta("<lang>Advertencia</lang>","<lang>No se pudo traer información</lang>");
            }
        }
    };
    $scope.obtenerBoletin = function(boletinId){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=obtenerBoletin",
            data: { boletinId:boletinId}
        }).then(function(data) {
            $scope.waitWindow = false;
            if (angular.isDefined(data.resultado.errores)) {
                avisos.alerta("<lang>Advertencia</lang>",data.errores);
            } else {
                $scope.boletin.selecciondo = data.resultado.boletin;
                $scope.seleccionaBoletin($scope.boletin.selecciondo);
            }
        });
    };
    $scope.seleccionarUrlEditorAntiguo = function(pos,pr){
        $scope.editorAntiguo.activo = true;
        $scope.editorAntiguo.evento = pos;
        $scope.editorAntiguo.idCategoria = 0;
        $scope.editorAntiguo.nombreCategoria = '';
        pr.copy.boc_url = '';
    };
    $scope.seleccionarEditorAntiguo = function(item,seleccionadas){
        $scope.editorAntiguo.idCategoria = 0;
        $scope.editorAntiguo.nombreCategoria = '';
        if(item.seleccionada){
            $scope.editorAntiguo.idCategoria = item.id;
            $scope.editorAntiguo.nombreCategoria = item.nombre;
        }
    };
    $scope.limpiarSeleccionEditorAntiguo = function(pr){
        $scope.editorAntiguo = {};
        pr.copy.boc_url = '';
    };
    $scope.asignarDireccionArchivoBoletin = function(file,pr){
        if(angular.isDefined(file) && angular.isDefined(file[0]) && angular.isDefined(file[0].fileId) && file[0].fileId > 0){
            $scope.waitWindow = true;
            pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=obtenerUrlUploadBoletin",
                data: { fileId:file[0].fileId}
            }).then(function(data) {
                if (angular.isDefined(data.resultado.errores)) {
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.errores);
                } else {
                    pr.copy.boc_url = data.resultado;
                    $scope.waitWindow = false;
                }
            });
        }
    };
    $scope.seleccionaItem = function(item, seleccionadas) {
        if (item.seleccionada) {
            if (item.id.split('C').length === 2) {
                item.seleccionada = false;
                $scope.waitWindow = false;
                avisos.alerta("<lang>No puede seleccionar una categoría</lang>");
            }
        }
        if (item.seleccionada) {
            $scope.seleccionHijos(item, false);
            angular.forEach(seleccionadas, function(s) {
                if (s.id !== item.id && s.hijos.length > 0) {
                    $scope.seleccionPadres(s.hijos, item, s);
                }
            });
        }

        $scope.boletin.listaMarcados = [];
        angular.forEach(seleccionadas, function(s) {
            if (s.seleccionada) {
                $scope.boletin.listaMarcados.push(s);
            }
        });
        if ($scope.boletin.listaMarcados.length > 0) {
            $scope.waitWindow = true;
            pedido.async({
                    method: 'POST',
                    url: "../boletines/BoletinesCtrl.php?act=traerInfoJerarquia",
                    data: { lista: $scope.boletin.listaMarcados }
            }).then(function(data) {
                $scope.boletin.listaMarcadosVista = data;
                $scope.recarga++;
                var serieMarcados = "";
                angular.forEach(seleccionadas, function(s) {
                    serieMarcados += "" + s.id + "|" + s.padreId + "|" + s.categoriaId + ";";
                });
                $scope.boletin.serieActual = serieMarcados; 
                $scope.waitWindow = false;
            });
        }else{
            $scope.boletin.listaMarcadosVista = [];
        }
    };
    $scope.seleccionHijos = function(item, seleccion) {
        if (item.hijos.length > 0) {
            angular.forEach(item.hijos, function(h) {
                h.seleccionada = seleccion;
                $scope.seleccionHijos(h, seleccion);
            });
        }
    };
    $scope.preparaBoletinesActivos = function(items){
        angular.forEach(items,function(it){
            if(it.boc_id===$scope.boletin.actual.boletinEnVistaPrevia){
                it.seleccionada="fa fa-check-square-o fa-green fa-fw";
            }else{
                it.seleccionada ="fa fa-square-o fa-green fa-fw";
            }
        });
        return items;
    };
    $scope.seleccionPadres = function(hijos, item, s) {
        $scope.existe = false;
        angular.forEach(hijos, function(h) {
            if (h.id === item.id) {
                $scope.existe = true;
            } else {
                if (h.hijos.length > 0) {
                    $scope.seleccionPadres(h.hijos, item, s);
                }
            }
        });
        if ($scope.existe) {
            s.seleccionada = false;
        }
    };
    $scope.inicializaItem = function(item) {
        $scope.editorAntiguo.idCategoria = 0;
        $scope.editorAntiguo.activo = false;
        item.boc_tipoPlantilla = '';
        if(angular.isDefined(item.boc_url) && item.boc_url !== null && item.boc_url !== ''){
            var find1 = item.boc_url.indexOf("web/");
            var find2 = item.boc_url.indexOf(".php?c=");
            if(find1 > 0 && find2 > 0){
                item.boc_tipoPlantilla = 'editor';
            }else{
                item.boc_tipoPlantilla = 'url';
            }
        }
        simple.inicializaItem(item);
    };
    $scope.inicializaItemCategorias = function(item) {
        simple.inicializaItem(item);
    };
    $scope.reinicializaItem = function(item, lista) {
        simple.reinicializaItem(item, lista);
    };
    $scope.activarEnviosPorTipo = function(tipo){
        $scope.cargarSuscripciones();
        if(tipo==='manual'){
            $scope.boletin.envioManual=true;
            $scope.boletin.bitacora=false;
            $scope.boletin.envioManualLista=false;
        }else if(tipo==='lista'){
            $scope.boletin.envioManual=false;
            $scope.boletin.bitacora=false;
            $scope.boletin.envioManualLista=true;
        }else if(tipo==='bitacora'){
            $scope.boletin.envioManual=false;
            $scope.boletin.bitacora=true;
            $scope.boletin.envioManualLista=false;
        }
    };
    $scope.cargarSuscripciones = function(){
        if($scope.boletin.serieActual !== ""){
            pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=reporteSuscripcionesEnvio",
                data: {
                    lista: $scope.boletin.serieActual,
                    pagina:$scope.boletin.suscritos.paginaEnvio,
                    filtro:''
                }
            }).then(function(data) {
                $scope.boletin.suscritos.envios = data.lista;
                $scope.boletin.suscritos.enviosTotal = data.Total;
                $scope.boletin.suscritos.totalPaginas = data.Paginas;
                $scope.waitWindow = false;
            });
        }   
    };
    
    //Sección: Envío Manual, Envia listado de correos ingresados en el input.
    $scope.enviarManual = function(correos) {
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            var correosLimpios = correos.split("\n"); 
            pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=envioManualBoletin",
                data: {
                    correos: correosLimpios,
                    boletinId: $scope.boletin.actual.boletinEnVistaPrevia
                }
            }).then(function(data) {
                if(data.resultado.errores){
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                }else{
                    $scope.boletin.correos = '';
                    avisos.alerta("<lang>Advertencia</lang>","<lang>Boletín enviado correctamente.</lang>");
                }
            });
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    
    //Sección: Lista de Envio.
    $scope.abreConfirmaEnvioSelec = function(pos,valor){
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            if($scope.boletin.listaEnvios.length > 0){
                if(valor === 1){
                    $scope.boletin.confirmaEnvioSelec = true;
                    $scope.boletin.confirmaEnvioSelecEvento = pos;
                }else{
                    $scope.boletin.confirmaEnvioSelec = false;  
                }
            }else{
                avisos.alerta("<lang>Advertencia</lang>","<lang>Debe seleccionar al menos un elemento de la lista de suscriptores</lang>");
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    $scope.deSeleccionaListaEnvio = function(item) {
        angular.forEach($scope.boletin.listaEnvios, function(en) {
            if (item.bjr_id === en.bjr_id && item.bco_id === en.bco_id && item.bco_email === en.bco_email) {
                $scope.boletin.listaEnvios.splice($scope.boletin.listaEnvios.indexOf(item), 1);
                return;
            }
        });
        if($scope.boletin.listaEnvios.length > 0){
            $scope.boletin.activaListaEnvio = true;
        }else{
            $scope.boletin.activaListaEnvio = false;
        }
    };
    $scope.seleccionaListaEnvio = function(item) {
        var existeEnvio = false;
        angular.forEach($scope.boletin.listaEnvios, function(en) {
            if (item.bjr_id === en.bjr_id && item.bco_id === en.bco_id) {
                existeEnvio = true;
                return;
            }
            if(item.bco_email === en.bco_email){
                existeEnvio = true;
                return;
            }
        });
        if (!existeEnvio) {
            $scope.boletin.listaEnvios.push(item);
        }
        if($scope.boletin.listaEnvios.length > 0){
            $scope.boletin.activaListaEnvio = true;
        }else{
            $scope.boletin.activaListaEnvio = false;
        }
    };
    $scope.enviaListado = function() {
        $scope.waitWindow = true;
        $scope.boletin.confirmaEnvioSelec = false;
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia >0) {
            pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=envioListaBoletin",
                data: {
                    correos: $scope.boletin.listaEnvios,
                    boletinId: $scope.boletin.actual.boletinEnVistaPrevia
                }
            }).then(function(data) {
                if(data.resultado.errores){
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                }else{
                    $scope.waitWindow = false;
                    $scope.boletin.listaEnvios = [];
                    $scope.boletin.activaListaEnvio = false;
                    avisos.alerta("<lang>Advertencia</lang>","<lang>Boletín enviado correctamente.</lang>");
                }
            });
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    
    //Sección: Envío por Bitácora.
    $scope.traeSiguienteEnvio=function(filtro){
        $scope.boletin.suscritos.paginaEnvio++;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=reporteSuscripcionesEnvio",
            data: {
                lista:$scope.boletin.serieActual,
                pagina:$scope.boletin.suscritos.paginaEnvio,
                filtro:filtro
            }
        }).then(function(data) {
            $scope.boletin.suscritos.envios = data.lista;
            $scope.boletin.suscritos.enviosTotal = data.Total;
        });
    };
    $scope.traeAnteriorEnvio=function(filtro){
        $scope.boletin.suscritos.paginaEnvio--;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=reporteSuscripcionesEnvio",
            data: {
                lista:$scope.boletin.serieActual,
                pagina:$scope.boletin.suscritos.paginaEnvio,
                filtro:filtro
            }
        }).then(function(data) {
            $scope.boletin.suscritos.envios = data.lista;
            $scope.boletin.suscritos.enviosTotal = data.Total;
        });
    };
    $scope.buscaEmailEnvio=function(filtro){
        $scope.boletin.suscritos.paginaEnvio = 0;        
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=reporteSuscripcionesEnvio",
            data: {
                lista: $scope.boletin.serieActual,
                pagina:$scope.boletin.suscritos.paginaEnvio,
                filtro:filtro
            }
        }).then(function(data) {
            $scope.boletin.suscritos.envios = data.lista;
            $scope.boletin.suscritos.enviosTotal = data.Total;
        });
    };
    $scope.abreProgramacionBoletin = function(pos,valor){
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            $scope.boletin.modalProgramacion = true; 
            $scope.boletin.modalProgramacionEvento = pos;
            $scope.boletin.modalProgramacionTipo = valor;
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    $scope.abreConfirmaEnvio = function(pos,valor){
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            $scope.boletin.confirmaEnvio = true;
            $scope.boletin.confirmaEnvioEvento = pos;
            $scope.boletin.confirmaEnvioTipo = valor;
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    $scope.enviarProgramacion = function(prg){
        $scope.waitWindow = true;
        $scope.boletin.modalProgramacion = false;
        var horaProgramada = prg.horaProgramada.getHours();
        var minutoProgramado = prg.horaProgramada.getMinutes();
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            if($scope.boletin.serieActual === ""){
                avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione grupos de envío</lang>");
            }else{
                var CorreosFiltrados = "";
                angular.forEach($scope.boletin.suscritos.envios, function(e) {
                    CorreosFiltrados += e.bco_id + "|";
                });
                if(CorreosFiltrados !== ""){
                    pedido.async({
                        method: 'POST',
                        url: "../boletines/BoletinesCtrl.php?act=envioBoletinBitacora",
                        data: {
                            lista: $scope.boletin.serieActual,
                            boletinId: $scope.boletin.actual.boletinEnVistaPrevia,
                            fechaProgramada:prg.fechaProgramada,
                            horaProgramada:horaProgramada,
                            minutoProgramado:minutoProgramado
                        }
                    }).then(function(data) {
                        if(data.resultado.errores){
                            $scope.waitWindow = false;
                            avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                        }else{
                            $scope.waitWindow = false;
                            $scope.boletin.actual = {};
                            avisos.alerta("<lang>Advertencia</lang>","<lang>Boletín enviado correctamente.</lang>");
                        }
                    });
                }else{
                     avisos.alerta("<lang>Advertencia</lang>","<lang>No se encontraron correos asociados</lang>");
                }
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    $scope.enviaFiltrado = function(){
        $scope.boletin.confirmaEnvio = false;
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia >0) {
            if($scope.boletin.serieActual === ""){
                avisos.alerta("<lang>Seleccione grupos de envío</lang>");
            }else{
                var CorreosFiltrados = "";
                angular.forEach($scope.boletin.suscritos.envios, function(e) {
                    CorreosFiltrados += e.bco_id + "|";
                });
                if(CorreosFiltrados !== ""){
                    $scope.waitWindow = true;
                    pedido.async({
                        method: 'POST',
                        url: "../boletines/BoletinesCtrl.php?act=envioBoletinBitacora",
                        data: {
                            lista: $scope.boletin.serieActual,
                            boletinId: $scope.boletin.actual.boletinEnVistaPrevia
                        }
                    }).then(function(data) {
                        if(angular.isDefined(data.resultado.errores)){
                            $scope.waitWindow = false;
                            avisos.alerta(data.resultado.errores);
                        }else{
                            $scope.boletin.actual = {};
                            $scope.waitWindow = false;
                            avisos.alerta("<lang>Boletín enviado correctamente.</lang>");
                        }
                    });
                }else{
                     avisos.alerta("<lang>Advertencia</lang>","<lang>No se encontraron correos asociados</lang>");
                }
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    
    //Seccion: Envío por Archivo
    $scope.inicializarArchivo = function(tipo){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=inicializarArchivo",
            data: { tipo:tipo }
        }).then(function(data) {
            if(angular.isDefined(data.resultado.error)){
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
            }else{
                $scope.catalogos.archivos = data.resultado.archivos;
                $scope.waitWindow = false;
            }
        });
    };
    $scope.generarVistaPreviaArchivo = function(archivo){
        $scope.waitWindow = true;
        pedido.async({
            method: 'POST',
            url: "../boletines/BoletinesCtrl.php?act=generarVistaPreviaArchivo",
            data: { archivo:archivo }
        }).then(function(data) {
            if(angular.isDefined(data.resultado.error)){
                $scope.waitWindow = false;
                avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
            }else{
                $scope.boletin.archivo.vistaprevia = true;
                $scope.boletin.archivo.contenido = data.resultado.contenido;
                $scope.waitWindow = false;
            }
        });
    };
    $scope.enviaFiltradoArchivo = function(){
        $scope.waitWindow = true;
        $scope.boletin.confirmaEnvio = false;
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia >0) {
            if(angular.isDefined($scope.boletin.archivo.seleccionado.id) && $scope.boletin.archivo.seleccionado.id !==''){
                pedido.async({
                        method: 'POST',
                        url: "../boletines/BoletinesCtrl.php?act=envioBoletinArchivo",
                        data: {
                            archivo: $scope.boletin.archivo.seleccionado.id,
                            boletinId: $scope.boletin.actual.boletinEnVistaPrevia
                        }
                    }).then(function(data) {
                        if(angular.isDefined(data.resultado.error)){
                            $scope.waitWindow = false;
                            avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                        }else{
                            $scope.boletin.actual = {};
                            $scope.waitWindow = false;
                            avisos.alerta("<lang>Advertencia</lang>","<lang>Archivo enviado correctamente.</lang>");
                        }
                    });
            }else{
                avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el archivo a enviar</lang>");
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };
    $scope.enviarProgramacionArchivo = function(prg){
        $scope.waitWindow = true;
        $scope.boletin.modalProgramacion = false;
        var horaProgramada = prg.horaProgramada.getHours();
        var minutoProgramado = prg.horaProgramada.getMinutes();
        if (angular.isDefined($scope.boletin.actual.boletinEnVistaPrevia) && $scope.boletin.actual.boletinEnVistaPrevia > 0) {
            if(angular.isDefined($scope.boletin.archivo.seleccionado.id) && $scope.boletin.archivo.seleccionado.id !==''){
                pedido.async({
                    method: 'POST',
                    url: "../boletines/BoletinesCtrl.php?act=envioBoletinArchivo",
                    data: {
                        archivo: $scope.boletin.archivo.seleccionado.id,
                        boletinId: $scope.boletin.actual.boletinEnVistaPrevia,
                        fechaProgramada:prg.fechaProgramada,
                        horaProgramada:horaProgramada,
                        minutoProgramado:minutoProgramado
                    }
                }).then(function(data) {
                    if(angular.isDefined(data.resultado.error)){
                        $scope.waitWindow = false;
                        avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                    }else{
                        $scope.boletin.actual = {};
                        $scope.waitWindow = false;
                        avisos.alerta("<lang>Advertencia</lang>","<lang>Boletín enviado correctamente.</lang>");
                    }
                });
            }else{
                avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el archivo a enviar</lang>");
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione el boletín que desea enviar</lang>");
        }
    };

    //------------------ Funciones de Edición Grupos -------------------------//
    $scope.volverCategorias = function() {
        $scope.cat.verCategorias = 1;
        $scope.cat.verCategoriaMenu = 0;
        $scope.cat.categoriaMostrar = 0;
    };
    $scope.verGrupos = function(item) {
        $scope.cat.categoriaActual = item.cat_nombre;
        $scope.cat.categoriaMostrar = item.cat_id;
    };
    $scope.eliminarCategoria = function(item, lista) {
        parametros = {
            url: '../boletines/BoletinesCtrl.php?act=eliminarCategoria',
            confirma: true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    $scope.cancelarEdicionJerarquia = function() {
        $scope.enEdicion.id = "";
        $scope.enEdicion.nombre = "";
        $scope.enEdicion.descripcion = "";
        $scope.enEdicion.activo = "";
        //$scope.laUltimaSeleccionada = item;
    };
    $scope.cambiarCategoria = function() {
        $scope.cat.recargar++;
    };
    $scope.guardarJerarquia = function(item) {
        //setea el URL
        //item.copy = item;
        parametros = {
            url: '../boletines/BoletinesCtrl.php?act=saveJerarquia',
            onsuccess: function(it, dt) {
                $scope.cat.cualAbrir = it.id;
            }
        };
        simple.inicializaItem(item);
        simple.guardaItem(item, parametros);

    };
    $scope.seleccionaUbicacion = function(item, seleccionadas) {
        if (item.seleccionada) {
            $scope.laUltimaSeleccionada = item;
            //OBTENGO LA INFORMACIÓN QUE ME FALTA DE LA CATEGORÍA
            $scope.estePedidoSuscripcion = pedido.async({
                method: 'POST',
                url: "../boletines/BoletinesCtrl.php?act=traerInfoCategoria",
                data: { cat_id: item.id }
            }).then(function(data) {
                if (data.errores) {
                    avisos.alerta("<lang>Advertencia</lang>",data.errores);
                } else {
                    if (data.Resultado) {
                        //si el resultado fue positivo:
                        //actualiza el modelo localmente
                        $scope.enEdicion.id = data.Resultado.boJerarquia_id;
                        $scope.enEdicion.nombre = data.Resultado.boJerarquia_nombre;
                        $scope.enEdicion.descripcion = data.Resultado.boJerarquia_descripcion;
                        $scope.enEdicion.activo = data.Resultado.boJerarquia_activo;
                    } else {
                        avisos.alerta("<lang>Advertencia</lang>","<lang>No se pudo traer información</lang>");
                    }
                }
            });
            this.form = {
                id: item.id,
                nombre: item.nombre,
                descripcion: item.descripcion,
                activo: item.activo
            };
            this.master = {
                id: item.id,
                nombre: item.nombre,
                descripcion: item.descripcion,
                activo: item.activo
            };
            $scope.visualizarDetalles = true;
        } else {
            $scope.laUltimaSeleccionada = {};
        }
        $scope.lasSeleccionadas = seleccionadas;
    };
    $scope.guardarCategoria = function(item) {
        //setea el URL
        parametros = {
            url: '../boletines/BoletinesCtrl.php?act=guardarCategoria'
        };
        simple.guardaItem(item, parametros);
    };
    $scope.preparaNuevoItem = function(lista, porDefecto) {
        parametros = {
            vacio: {
                editame: true,
                cat_id: -1,
                cat_nombre: "",
                cat_ultimoNivel: "0"
            }
        };
        simple.preparaNuevoItem(lista, parametros);
    };
    $scope.$watch("cat.categoriaMostrar", function(newVal, oldVal) {
        if (newVal > 0 && angular.isDefined(oldVal)) {
            $scope.cat.recargar++;
            $scope.enEdicion = {};
        }
    });
    //------------------------------ Parámetros -----------------------------//
    if (typeof $routeParams["boletinId"] !== 'undefined') {
        $scope.obtenerBoletin($routeParams["boletinId"]);
    }
}]);
app.filter('to_trusted', ['$sce', function ($sce) {
    return function (text) {
        return $sce.trustAsHtml(text);
    };
}]);
//_FIN_DE_ARCHIVO