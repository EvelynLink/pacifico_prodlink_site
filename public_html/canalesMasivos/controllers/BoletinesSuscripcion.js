app.controller("BoletinesSuscripcion",["$scope", "avisos", "simple", "pedido", "myIntercom", "$timeout",function($scope, avisos, simple, pedido, myIntercom, $timeout) {
    myIntercom.publica('ruta', menuSuperior);
    $scope.permisoAdministrador = (permisoAdministrador === 'write') ? true : false;
    $scope.waitWindow = false;
    $scope.Suscribe = true;
    $scope.SuscribeAuto = false;
    $scope.SuscribeCorreo = false;

    //FORMULARIO SUSCRITOS
    $scope.numeroSuscripcion = 1;
    $scope.keys = {13:'enter', 40:'down'}; //teclas a observar para agregar nuevo
    $scope.listaNuevos = [{id:$scope.numeroSuscripcion,susEmail:"",susIdentificacion:"",susNombre:""}];
    $scope.regPegado = false;
    $scope.regPegadoEv = {};
    
    //SUSCRIPCION POR ARCHIVO
    $scope.archivo = {ejemplo:false,lista:{},cuantos:0,activa:false};    
    //JERARQUIA
    $scope.listaMarcados = [];
    $scope.listaMarcadosVista = [];
    $scope.recarga = 0;
    $scope.listaCopiado={correos:''};
    
    //REPORTE SUSCRIPCIONES
    $scope.reporteSuscripciones = {};
    $scope.correosReporte = [];
    $scope.recargaId = 0;
    $scope.reporteActivo = false;
    $scope.extraIds = [];
    $scope.extraIdsBuscados = [];
    $scope.sqlFiltrado = false;
    $scope.duplicados = {recargaId: 0};
    $scope.serieMarcadosLimpieza = "";
    
    //ELIMINACIONES
    $scope.eliminaSuscripciones = {activa:false,tipo:1};
    $scope.eliminaTodoEncontrado = {activa:false};
    $scope.paraEliminar = [];
    $scope.paraEliminarBuscados = [];
    $scope.muestraTodos = false;
    $scope.muestraTodosBuscados = false;
    
    //EDICION
    $scope.edita = {activa:false,idEdita:0,nombre:"",identificacion:"",email:"",fechaNacimiento:0,telefono:"",celular:"",empresa:"",direccion:"",recarga:0};
    $scope.limpiaDesuscritos = { recarga:0 };
    
    //CONFIGURACION
    $scope.configuracion = { recargaId:0, datos:{}, tipos:[{id:'Contacto',nombre:'<lang>Contacto</lang>'},{id:'Boletin',nombre:'<lang>Boletín</lang>'}]};
    
    //---------------------- Funciones Sucripcion y Envio --------------------//
    $scope.seleccionaItem = function(item, seleccionadas) {
        if (item.seleccionada) {
            if (item.id.split('C').length === 2) {
                item.seleccionada = false;
                avisos.alerta("<lang>Advertencia</lang>","<lang>No puede seleccionar una categoría</lang>");
            }
        }
        if(item.seleccionada){
            $scope.seleccionHijos(item, false);
            angular.forEach(seleccionadas, function(s) {
                if(s.id !== item.id && s.hijos.length > 0){
                    $scope.seleccionPadres(s.hijos,item,s);
                }
            });
        }
        
        $scope.listaMarcados = [];
        angular.forEach(seleccionadas, function(s) {
            if(s.seleccionada){
                $scope.listaMarcados.push(s);
            }
        });
        
        if($scope.listaMarcados.length > 0){
            $scope.waitWindow = true;
            pedido.async({
                    method : 'POST',
                    url : "../boletines/BoletinesCtrl.php?act=traerInfoJerarquia",
                    data : {"lista":$scope.listaMarcados}
            }).then(function(data) {
                $scope.listaMarcadosVista = data;
                $scope.recarga++;
                $scope.waitWindow = false;
            });
        }else{
            $scope.listaMarcadosVista = [];
        }
        $scope.serieMarcados = "";
        angular.forEach(seleccionadas, function(s) {
            $scope.serieMarcados += "" + s.id + "|" + s.padreId + "|" + s.categoriaId +";";
        });
        $scope.SeleccionaJerarquia = true;
        $scope.paraEliminar = [];
    };
    $scope.seleccionHijos = function(item, seleccion) {
        if (item.hijos.length > 0) {
            angular.forEach(item.hijos, function(h) {
                h.seleccionada = seleccion;
                $scope.seleccionHijos(h, seleccion);   
            });
        }
    };
    $scope.seleccionPadres = function(hijos,item,s){
        $scope.existe = false;
        angular.forEach(hijos, function(h) {
                    if(h.id === item.id){
                        $scope.existe = true;
                    }else{
                        if(h.hijos.length > 0){
                            $scope.seleccionPadres(h.hijos,item,s);
                        }
                    }
        });
        if($scope.existe){
            s.seleccionada = false;
        }
    };
    $scope.erroresGuardado = [];
    $scope.guardarSuscripciones=function(pos){
	 if (angular.isUndefined($scope.listaMarcados) || $scope.listaMarcados.length === 0) {
            avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione al menos un grupo de asociación</lang>");
         }else{
            $scope.waitWindow = true;
            pedido.async({
                    method : 'POST',
                    url : "../boletines/BoletinesCtrl.php?act=guardaSuscripciones",
                    data : {
                            "lstCategoriasMarcadas":$scope.listaMarcados,
                            "lstCorreos":$scope.listaNuevos
                           }
            }).then(function(data) {
                $scope.recarga++;
                if(!angular.isUndefined(data.resultado.errores)){
                    $scope.regPegado = true;
                    $scope.regPegadoEv = pos;
                    $scope.erroresGuardado = data.resultado.errores;
                }
                //Limpia los valores ingresados y respuestas de ejecución
                $scope.listaNuevos = [{id:$scope.numeroSuscripcion,susEmail:"",susIdentificacion:"",susNombre:""}];
                $scope.numeroSuscripcion = 1;
                $scope.waitWindow = false;
            });
        }
    };
    $scope.cierraErroresPegado = function(){
        $scope.erroresGuardado = [];
        $scope.regPegado = false;
    };
    $scope.eliminarSuscripcion = function(item,lista){
        parametros = {
            url: '../boletines/BoletinesCtrl.php?act=eliminaSuscripcion',
            confirma:true
        };
        simple.eliminaItem(item, lista, parametros);
    };
    $scope.agregaNuevaFilaSuscripcion = function(evento){
        var key = $scope.keys[evento.which];
        if ( !key || evento.shiftKey || evento.altKey ) {
            return;
        }else{
            $scope.numeroSuscripcion+=1;
            $scope.listaNuevos.push({id:$scope.numeroSuscripcion,susEmail:"",susIdentificacion:"",susNombre:""});   
        }          
    };
    $scope.eliminaNuevaFilaSuscripcion = function(id){
        if(id > 1){
            $scope.listaNuevos.splice($scope.listaNuevos.indexOf(id),1);
            if($scope.numeroSuscripcion > 1){
                $scope.numeroSuscripcion-=1;
            }
        }
    };
    $scope.pegarListado = function(){
       $timeout(function(){
           $scope.correos = $scope.listaCopiado.correos.split('\n');
           angular.forEach($scope.correos, function(c) {
               $scope.linea = c.split(';');
               $scope.encuentraVacio = false;
               angular.forEach($scope.listaNuevos, function(n) {
                    if(n.susEmail === ""){
                        n.susEmail = $scope.linea[0];
                        n.susIdentificacion = $scope.linea[1];
                        n.susNombre = $scope.linea[2];
                        $scope.encuentraVacio = true;
                        return;
                    }
                });
                if(!$scope.encuentraVacio){
                    $scope.numeroSuscripcion+=1;
                    $scope.listaNuevos.push({id:$scope.numeroSuscripcion,susEmail:$scope.linea[0],susIdentificacion:$scope.linea[1],susNombre:$scope.linea[2]});
                }
           });
           $scope.listaCopiado.correos = "";
       },100);
    };
    $scope.suscribeArchivo = function(){
        if($scope.archivo.lista.length === 1){
             if (angular.isUndefined($scope.listaMarcados) || $scope.listaMarcados.length === 0) {
                avisos.alerta("<lang>Advertencia</lang>","<lang>Seleccione al menos un grupo de asociación</lang>");
             }else{
                $scope.waitWindow = true;
                $scope.archivo.activa = true;
                pedido.async({
                        method : 'POST',
                        url : "../boletines/BoletinesCtrl.php?act=cargaSuscripciones",
                        data : {
                                "lstCategoriasMarcadas":$scope.listaMarcados,
                                "lstArchivos":$scope.archivo.lista
                               }
                }).then(function(data) {
                    $scope.recarga++;
                    $scope.archivo.cuantos = 0;
                    $scope.archivo.activa = false;
                    $scope.waitWindow = false;
                    if(!angular.isUndefined(data.resultado.errores)){
                        avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                    }else{
                        avisos.alerta("<lang>Carga finalizada correctamente.</lang>");
                    }
                });
            }
        }else{
            avisos.alerta("<lang>Advertencia</lang>","<lang>Debe subir un archivo a la vez.</lang>");
        }
    };
    $scope.activaEjemploCarga = function(pos){
        $scope.archivo.ejemplo = true;
        $scope.archivo.evento = pos;
    };
    $scope.asignaCarga = function(archivosUpd){
        $scope.archivo.lista = archivosUpd;
        $scope.archivo.cuantos = archivosUpd.length;
    };
    
    //----------------- Reporte de suscripciones actuales --------------------//
    $scope.obtenerListaSuscripciones = function(){
        if($scope.reporteSuscripciones.copy.correos !== ""){
            $scope.correosReporte = $scope.reporteSuscripciones.copy.correos.split("\n");
            $scope.recargaId++;
            $scope.reporteActivo = true;
        }
    };
    $scope.verificaSuscritos = function(filas,extra){
        $scope.extraIds = extra.asociacionLista;
        $scope.sqlFiltrado = extra.esFiltrado;
        angular.forEach(filas,function(it){
            if($scope.paraEliminar.length > 0){
                $scope.encontreSuscri = false;
                angular.forEach($scope.paraEliminar,function(p){
                    if(it.bcj_id === p){
                        $scope.encontreSuscri = true;
                        return;
                    }
                });
                if($scope.encontreSuscri){
                    it.seleccionada = "fa fa-check-square-o fa-green fa-fw";
                }else{
                    it.seleccionada ="fa fa-square-o fa-green fa-fw";
                }
            }else{
                it.seleccionada ="fa fa-square-o fa-green fa-fw";
            }
        });
	return filas;
    };
    $scope.seleccionarSuscripcion=function(item){
        if(item.seleccionada === "fa fa-square-o fa-green fa-fw"){
            //existe item en lista de seleccionados?
            $scope.encontreSuscri = false;
            if($scope.paraEliminar.length > 0){
                angular.forEach($scope.paraEliminar,function(it){
                    if(it === item.bcj_id){
                        //requiere ser eliminado de lista
                        $scope.encontreSuscri = true;
                      return;
                    }
                });
            }
            if(!$scope.encontreSuscri){
                $scope.paraEliminar.push(item.bcj_id);
                item.seleccionada = "fa fa-check-square-o fa-green fa-fw";
            }
        }else{
            if($scope.paraEliminar.length > 0){
                angular.forEach($scope.paraEliminar,function(it){
                    if(it === item.bcj_id){
                        $scope.paraEliminar.splice($scope.paraEliminar.indexOf(item.bcj_id), 1);
                        item.seleccionada = "fa fa-square-o fa-green fa-fw";
                        return;
                    }
                });
            }
        }
    };
    $scope.seleccionaTodos = function(check){
        $scope.paraEliminar = [];
        if($scope.extraIds.length > 0){
            if(check === 1){
                $scope.muestraTodos = true;
            }else{
                $scope.muestraTodos = false;
            }
            //Marca, desmarca items
            angular.forEach($scope.extraIds,function(item){
                if(check === 1){ //agrega en lista de ids
                    //existe item en lista de seleccionados?
                    $scope.encontreSuscri = false;
                    if($scope.paraEliminar.length > 0){
                        angular.forEach($scope.paraEliminar,function(it){
                            if(it === item){
                                //requiere ser eliminado de lista
                                $scope.encontreSuscri = true;
                              return;
                            }
                        });
                    }
                    if(!$scope.encontreSuscri){
                        $scope.paraEliminar.push(item);
                        item.seleccionada = "fa fa-check-square-o fa-green fa-fw";
                    }
                }else{ //elimina de lista de ids
                    if($scope.paraEliminar.length > 0){
                        angular.forEach($scope.paraEliminar,function(it){
                            if(it === item){
                                $scope.paraEliminar.splice($scope.paraEliminar.indexOf(item), 1);
                                item.seleccionada = "fa fa-square-o fa-green fa-fw";
                                return;
                            }
                        });
                    }
                }
            });
            $scope.recarga++;
        }
    };
    //Limpieza
    $scope.seleccionaItemLimpieza = function(item, seleccionadas) {
        if (item.seleccionada) {
            if (item.id.split('C').length === 2) {
                item.seleccionada = false;
                avisos.alerta("<lang>Advertencia</lang>","<lang>No puede seleccionar una categoría</lang>");
            }
        }
        if(item.seleccionada){
            $scope.seleccionHijos(item, false);
            angular.forEach(seleccionadas, function(s) {
                if(s.id !== item.id && s.hijos.length > 0){
                    $scope.seleccionPadres(s.hijos,item,s);
                }
            });
        }
        $scope.serieMarcadosLimpieza = "";
        angular.forEach(seleccionadas, function(s) {
            $scope.serieMarcadosLimpieza += "" + s.id + "|" + s.padreId + "|" + s.categoriaId +";";
        });
    };
    $scope.eliminarSuscripcionDuplicada = function(correoId,jerarquiaId){
        if(correoId > 0 && jerarquiaId > 0){
            $scope.waitWindow = true;
            pedido.async({
                    method : 'POST',
                    url : "../boletines/BoletinesCtrl.php?act=eliminarSuscripcionDuplicada",
                    data : { correoId:correoId,jerarquiaId:jerarquiaId,desde:$scope.duplicados.desde }
            }).then(function(data) {
                $scope.duplicados.recargaId++;
                $scope.waitWindow = false;
            });
        }
    };
    $scope.actualizaReporte = function(){ $scope.duplicados.recargaId++; };
    
    //Busqueda por correos ----------------------------------------------------
    $scope.verificaSeleccionados = function(filas,extra){
        $scope.extraIdsBuscados = extra.asociacionListaBuscados;
        $scope.sqlFiltradoBuscados = extra.esFiltrado;
        angular.forEach(filas,function(it){
            if($scope.paraEliminarBuscados.length > 0){
                $scope.encontreSuscri = false;
                angular.forEach($scope.paraEliminarBuscados,function(p){
                    if(it.bcj_id === p){
                        $scope.encontreSuscri = true;
                        return;
                    }
                });
                if($scope.encontreSuscri){
                    it.seleccionada = "fa fa-check-square-o fa-green fa-fw";
                }else{
                    it.seleccionada ="fa fa-square-o fa-green fa-fw";
                }
            }else{
                it.seleccionada ="fa fa-square-o fa-green fa-fw";
            }
        });
	return filas;
    };
    $scope.seleccionarSuscripcionBuscados=function(item){
        if(item.seleccionada === "fa fa-square-o fa-green fa-fw"){
            //existe item en lista de seleccionados?
            $scope.encontreSuscri = false;
            if($scope.paraEliminarBuscados.length > 0){
                angular.forEach($scope.paraEliminarBuscados,function(it){
                    if(it === item.bcj_id){
                        //requiere ser eliminado de lista
                        $scope.encontreSuscri = true;
                      return;
                    }
                });
            }
            if(!$scope.encontreSuscri){
                $scope.paraEliminarBuscados.push(item.bcj_id);
                item.seleccionada = "fa fa-check-square-o fa-green fa-fw";
            }
        }else{
            if($scope.paraEliminarBuscados.length > 0){
                angular.forEach($scope.paraEliminarBuscados,function(it){
                    if(it === item.bcj_id){
                        $scope.paraEliminarBuscados.splice($scope.paraEliminarBuscados.indexOf(item.bcj_id), 1);
                        item.seleccionada = "fa fa-square-o fa-green fa-fw";
                        return;
                    }
                });
            }
        }
    };
    $scope.seleccionaTodosBuscados = function(check){
        $scope.paraEliminarBuscados = [];
        if($scope.extraIdsBuscados.length > 0){
            if(check === 1){
                $scope.muestraTodosBuscados = true;
            }else{
                $scope.muestraTodosBuscados = false;
            }
            //Marca, desmarca items
            angular.forEach($scope.extraIdsBuscados,function(item1){
                if(check === 1){ //agrega en lista de ids
                    //existe item en lista de seleccionados?
                    $scope.encontreSuscri = false;
                    if($scope.paraEliminarBuscados.length > 0){
                        angular.forEach($scope.paraEliminarBuscados,function(it){
                            if(it === item1){
                                //requiere ser eliminado de lista
                                $scope.encontreSuscri = true;
                              return;
                            }
                        });
                    }
                    if(!$scope.encontreSuscri){
                        $scope.paraEliminarBuscados.push(item1);
                        item1.seleccionada = "fa fa-check-square-o fa-green fa-fw";
                    }
                }else{ //elimina de lista de ids
                    if($scope.paraEliminarBuscados.length > 0){
                        angular.forEach($scope.paraEliminarBuscados,function(it){
                            if(it === item1){
                                $scope.paraEliminarBuscados.splice($scope.paraEliminarBuscados.indexOf(item1), 1);
                                item1.seleccionada = "fa fa-square-o fa-green fa-fw";
                                return;
                            }
                        });
                    }
                }
            });
            $scope.recargaId++;
        }
    };
    
    //Eliminación en reporte de suscripciones ---------------------------------
    $scope.activaConfirmacionEliminacion = function(pos,tipo){
        if(tipo === 1){
            if($scope.paraEliminar.length > 0){
                $scope.eliminaSuscripciones.activa = true;
                $scope.eliminaSuscripciones.evento = pos;
            }else{
                avisos.alerta("<lang>Advertencia</lang>","<lang>No existen valores a eliminar</lang>");
            }
        }else if(tipo === 2){
            if($scope.paraEliminarBuscados.length > 0){
                
                $scope.eliminaSuscripciones.activa = true;
                $scope.eliminaSuscripciones.evento = pos;
            }else{
                avisos.alerta("<lang>Advertencia</lang>","<lang>No existen valores a eliminar</lang>");
            }
        }
        $scope.eliminaSuscripciones.tipo = tipo;
    };
    $scope.eliminaSuscripcionesReporte = function(){
        if($scope.eliminaSuscripciones.tipo === 1){
            if($scope.paraEliminar.length > 0){
                  pedido.async({
                        method : 'POST',
                        url : "../boletines/BoletinesCtrl.php?act=eliminaSuscripcionesReporte",
                        data : { asociaciones:$scope.paraEliminar }
                    }).then(function(data) {
                        $scope.recarga++;
                        $scope.eliminaSuscripciones.activa = false;
                        if(!angular.isUndefined(data.resultado.errores)){
                            avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                        }else{
                            if(angular.isDefined(data.resultado.mensaje)){
                                avisos.alerta(data.resultado.mensaje);
                            }
                        }
                    });   
            }
        }else if($scope.eliminaSuscripciones.tipo === 2){
            if($scope.paraEliminarBuscados.length > 0){
                pedido.async({
                    method : 'POST',
                    url : "../boletines/BoletinesCtrl.php?act=eliminaSuscripcionesReporte",
                    data : { asociaciones:$scope.paraEliminarBuscados }
                }).then(function(data) {
                    $scope.recarga++;
                    $scope.recargaId++;
                    $scope.eliminaSuscripciones.activa = false;
                    if(!angular.isUndefined(data.resultado.errores)){
                        avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
                    }else{
                        if(angular.isDefined(data.resultado.mensaje)){
                            avisos.alerta(data.resultado.mensaje);
                        }
                    }
                });   
            }
        }
    };
    
    //Edita datos de suscripción ----------------------------------------------
    $scope.activaEdicionSuscripcion = function(pos,item,recarga){
        $scope.edita.activa = true;
        $scope.edita.evento = pos;
        $scope.edita.idEdita = item.bco_id;
        $scope.edita.nombre = item.bco_nombre;
        $scope.edita.identificacion = item.bco_identificacion;
        $scope.edita.email=item.bco_email;
        if(item.bco_fechaNacimiento > 0){
        $scope.edita.fechaNacimiento=item.bco_fechaNacimiento;
        }else{
            $scope.edita.fechaNacimiento=-1;
        }
        $scope.edita.telefono = item.bco_telefono;
        $scope.edita.celular = item.bco_celular;
        $scope.edita.empresa = item.bco_empresa;
        $scope.edita.direccion = item.bco_direccion;
        $scope.edita.recarga = recarga;
    };
    $scope.editaSuscripcion = function(suscripcion){
        pedido.async({
            method : 'POST',
            url : "../boletines/BoletinesCtrl.php?act=editaSuscripcion",
        data : {"suscripcion":suscripcion }
        }).then(function(data) {
            if($scope.edita.recarga === 0){
                $scope.recarga++;
            }else if($scope.edita.recarga === 1){
                $scope.recargaId++;
            }
            $scope.edita.activa = false;
            if(angular.isDefined(data.resultado.errores)){
                avisos.alerta("<lang>Advertencia</lang>",data.resultado.errores);
            }else{
                avisos.alerta("<lang>Suscripción guardada correctamente.</lang>");
            }
        });
    };
    
    //------------------ Funciones para Limpieza de desuscritos ---------------
    $scope.limpiarDesuscritosTodos = function(){
        avisos.setFuncion(function(){
            $scope.waitWindow = true;
            pedido.async({
                method : 'POST',
                url : '../boletines/BoletinesCtrl.php?act=eliminarDesuscritosTodos',
                data:{}
            }).then(function(data) {
                if(angular.isDefined(data.resultado.error)){
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                }else{
                    $scope.limpiaDesuscritos.recarga++;
                    $scope.waitWindow = false;
                }
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar las suscripciones?</lang>",null);
    };
    $scope.limpiarDesuscripcion = function(pr){
        avisos.setFuncion(function(){
            $scope.waitWindow = true;
            pedido.async({
                method : 'POST',
                url : '../boletines/BoletinesCtrl.php?act=eliminarDesuscrito',
                data:{  id:pr.bcj_id }
            }).then(function(data) {
                if(angular.isDefined(data.resultado.error)){
                    $scope.waitWindow = false;
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                }else{
                    $scope.limpiaDesuscritos.recarga++;
                    $scope.waitWindow = false;
                }
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar la suscripción?</lang>",null);
    };
    
    //------------------ Funciones para Configuracion -------------------------
    $scope.nuevaConfiguracion = function(){
        $scope.waitWindow = true;
        pedido.async({
            method : 'POST',
            url : '../boletines/BoletinesCtrl.php?act=nuevaConfiguracion',
            data:{ }
        }).then(function(data) {
            $scope.waitWindow = false;
            if(angular.isDefined(data.resultado.error)){
                avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
            }else{
                $scope.configuracion.recargaId++;
            }
        });
    };
    $scope.editarConfiguracion = function(datos){
        $scope.configuracion.datos.id = datos.bof_id;
        $scope.configuracion.datos.nombre = datos.bof_nombre;
        $scope.configuracion.datos.titulo = datos.bof_titulo;
        $scope.configuracion.datos.correos = datos.bof_correos;
        $scope.configuracion.datos.grupoSuscripcion = datos.bof_grupoSuscripcion;
        $scope.configuracion.datos.pluginEnvio = datos.bof_pluginEnvio;
        $scope.configuracion.datos.tipo = datos.bof_tipo;
    };
    $scope.guardarConfiguracion = function(datos){
        $scope.waitWindow = true;
        pedido.async({
            method : 'POST',
            url : '../boletines/BoletinesCtrl.php?act=guardarConfiguracion',
            data:{ datos:datos }
        }).then(function(data) {
            $scope.waitWindow = false;
            if(angular.isDefined(data.resultado.error)){
                avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
            }else{
                $scope.configuracion.recargaId++;
                $scope.configuracion.datos.id = 0;
            }
        });
    };
    $scope.eliminarConfiguracion = function(id){
        avisos.setFuncion(function () {
            $scope.waitWindow = true;
            pedido.async({
                method : 'POST',
                url : '../boletines/BoletinesCtrl.php?act=eliminarConfiguracion',
                data:{ id:id }
            }).then(function(data) {
                $scope.waitWindow = false;
                if(angular.isDefined(data.resultado.error)){
                    avisos.alerta("<lang>Advertencia</lang>",data.resultado.error);
                }else{
                    $scope.configuracion.recargaId++;
                    $scope.configuracion.datos.id = 0;
                }
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar la configuración?</lang>");
    };
}]);
//templates virtuales asociados con HTML reales
angular.module("template/boletines/partials/Boletines.html", []).run(["$templateCache", function($templateCache) {
  $templateCache.put("template/boletines/partials/Boletines.html",
    '<v-template-url>boletines/partials/Boletines.html</v-template-url>');
}]);
//_FIN_DE_ARCHIVO