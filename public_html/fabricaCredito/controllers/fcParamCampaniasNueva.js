app.controller("fcParamCampaniasNueva", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$timeout', '$mdToast', '$mdDialog', function ($scope, avisos, simple, pedido, myIntercom, $timeout, $mdToast, $mdDialog) {
        window.Z_cbPanel = $scope;
        myIntercom.publica('ruta', menuModulo);
        $scope.waitWindow = false;

        const URL = "../fabricaCredito/fcParamCampaniasNuevaCtrl.php?act=";
        $scope.fechaMin = "";

        //Guarda las empresas
        $scope.wizards = {};

        //Valor por defecto para los select de agregar nuevas condiciones
        $scope.valorPorDefecto = 'TODOS';

        //Variables para manejar las sucursales y la seleccion de las mismas
        $scope.oficinas = [];
        $scope.nivelesOficinas = [];
        $scope.lugarSelected = [];
        var soloNombres = [];

        //Guarda las opciones de los selects para la configuración de campaña
        $scope.tipos = {};
        $scope.tiposDescuentos = [];

        //Guarda los items para la eleccion de las condiciones de campaña (categorias, articulos)
        $scope.valores = [{"nombre": $scope.valorPorDefecto}];

        //arreglo de plazos
        $scope.tiposPlazos = [];

        //Objeto para guardar los campos configurados para el nuevo detalle
        $scope.objNuevoDetalle = {
            sucursal: [],
            tipoDetalle: "",
            subtipoDetalle: [],
            productos: [],
            condiciones: [],
            beneficios: [],
            obsequios: []
        };

        //Variables para manejar el guardado de la nueva campaña
        $scope.campania = {
            nombre: "",
            descripcion: "",
            fechaInicioComercial: 0,
            fechaFinComercial: 0,
            fechaFinOperativo: 0,
            wizardSelected: "",
            wizardSelectedRuc: "",
            conRecursoConcesionario: false,
            conObsequio: false
        };

        //Para guardar las condiciones, beneficios y obsequios
        $scope.arrCondiciones = [];
        $scope.arrBeneficios = [];
        $scope.arrObsequios = [];
        $scope.objObsequio = {codigo: "", nombre: "", cantidad: ""};
        $scope.objCondicion = {campoAfectado: "", valorAsignado: "Ingrese valor"};
        $scope.objBeneficio = {campoAfectado: "", valorAsignado: "Ingrese valor"};

        //Texto buscado en el autocomplete
        $scope.itemTipo = {item: ""};
        $scope.searchTextTipos = "";


        //Arreglo para guardar los articulos y los objetos que filtran para presentar cuando se eligen por categoria
        $scope.arrArticulos = [];
        $scope.articulosPorCategoria = [];
        $scope.articulosPorGama = [];
        $scope.articulosPorOrigen = [];
        $scope.auxFiltroArticulos = [];
        $scope.subItemSelect = "";

        //Arreglo para guardar las campañas que tienen conflicto con las fechas seleccionadas
        $scope.arrCampaniasChocan = [];
        $scope.verCampaniasChocan = false;

        //Controla que no ingrese algun nombre de campaña que ya exista
        $scope.banderaNombreRepetido = true;

        //Para el timeout que hace al validar los nombres
        $scope.barraProgresoNombre = false;

        //Para ingresar manualmente los plazos
        $scope.cambiarIngresoPlazos = false;

        //Para que puedan modificar las fechas directamente de la tabla, despues de ser creada
        $scope.cambioFecha = {fechaAux: "", bandera: false};
        $scope.indexFecha = "";
        var tipoFecha = '';
        $scope.campaniaEditar = {};
        var control = 0;

        //Para presentar el modal para configurar campañas
        $scope.verNuevaCampania = false;

        //Variables para presentar barra de proceso
        $scope.verBarraProcesoSegNivel = false;
        $scope.verBarraProcesoSegNivelArt = false;

        $scope.guardando = false;
        $scope.duplicando = false; //Duplica una campaña para evitar el proceso de crear desde cero una nueva campaña
        $scope.observando = false; //Bandera para la opción de ver la configuración de la campaña

        $scope.cambioCondiciones = false; //Bandera para indicar el cambio de condiciones cuando duplica campañas
        $scope.correctoValorAsignado = false; //Como último paso activar el botón de guardar de nueva condición

        $scope.presentarAdvertencia = false;

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
            $scope.setearFechas();
            $scope.cargarWizard();
            $scope.cargarTipos();
        };

        // Para hacer presentables en tabla los valores de arreglos
        $scope.formatearValoresArreglos = function (arreglo) {
            var texto = "";
            if (Array.isArray(arreglo)) {
                if (arreglo.length) {
                    var ultimo = arreglo.at(-1);
                    arreglo.forEach((element) => {
                        (ultimo === element) ? texto += element : texto += element + ' - ';
                    });
                    return texto;
                } else {
                    return 'NINGUNA';
                }
            } else {
                return arreglo;
            }


            //            var texto = "";
            //            if (arreglo.length) {
            //                var ultimo = arreglo.at(-1);
            //                arreglo.forEach((element) => {
            //                    if (Array.isArray(element)) {
            //                        if ($scope.tipos[3] === tipoDetalle) {
            //                            (ultimo === element) ? texto += "Min: $ " + element[0] + " | Max: $ " + element[1] : texto += "Min: $ " + element[0] + " | Max: $ " + element[1] + " - ";
            //                        } else {
            //                            (ultimo === element) ? texto += element[1] : texto += element[1] + ' - ';
            //                        }
            //                    } else {
            //                        (ultimo === element) ? texto += element : texto += element + ' - ';
            //                    }
            //                    //                    (Array.isArray(element)) ? texto += element[1] + ', ' : texto += element + ', ';
            //                });
            //                return texto;
            //            } else {
            //                return 'NINGUNA';
            //            }
        };


        //Método para presentar el select de artículos cuando es por categoría
        $scope.verSelectArticulos = function () {
            if ($scope.objNuevoDetalle.tipoDetalle === $scope.tipos[6] && $scope.objNuevoDetalle.subtipoDetalle.length) {
                $scope.arrArticulos = [];
                $scope.traerArticulosPorCategoria();
                $scope.verBarraProcesoSegNivelArt = true;
            }
        };

        //Comprueba que la fecha de inicio comercial no choque con otras campañas, sirve de advertencia
        $scope.comprobarFechas = function (input) {
            $scope.verCampaniasChocan = false;
            if (input === "inicio") {
                if (typeof ($scope.campania.fechaInicioComercial) === 'object') {
                    var fecha = formatearFecha($scope.campania.fechaInicioComercial);
                    $scope.verificarRangoFechas(fecha, $scope.campania.fechaFinComercial);
                } else {
                    $scope.verificarRangoFechas($scope.campania.fechaInicioComercial, $scope.campania.fechaFinComercial);
                }
                $scope.booFechaInicialDisponible = false;
            } else if (input === "final") {
                if (typeof ($scope.campania.fechaFinComercial) === 'object') {
                    var fecha = formatearFecha($scope.campania.fechaFinComercial);
                    $scope.verificarRangoFechas($scope.campania.fechaInicioComercial, fecha);
                } else {
                    $scope.verificarRangoFechas($scope.campania.fechaInicioComercial, $scope.campania.fechaFinComercial);
                }
                $scope.booFechaFinalDisponible = false;
            } else if (input === "finalOp") {
                $scope.booFechaFinalDisponible1 = false;
            } else {
                $scope.verificarRangoFechas($scope.campania.fechaInicioComercial, $scope.campania.fechaFinComercial);
            }
        };

        //Quita el toast cuando hacen click en el imput para modificar el nombre
        $scope.reiniciarToast = function () {
            $mdToast.hide(false);
        };

        //Llama a la funcion de comprobar el nombre, despues de un segundo que deja de teclear
        var $input = document.getElementById("nombre");
        var timeout = "";
        $input.addEventListener('keydown', () => {
            clearTimeout(timeout);
            $scope.barraProgresoNombre = true;
            timeout = setTimeout(() => {
                verificarNombre();
                clearTimeout(timeout);
            }, 800);
        });

        //Método para verificar que el nombre de la campaña no se repita 
        function verificarNombre() {
            if ($scope.campania.nombre) {
                pedido.async({
                    method: 'POST',
                    url: URL + 'verificarNombre',
                    data: {
                        nombre: $scope.campania.nombre.trim(),
                        wizard: $scope.campania.wizardSelected
                    }
                }).then(function (datos) {
                    if (angular.isDefined(datos.errores)) {
                        avisos.alerta(datos.errores);
                    } else {
                        texto = datos.resultado;
                        $scope.barraProgresoNombre = false;
                        if (texto !== "Correcto") {
                            $scope.showSimpleToast('datosGenerales', texto);
                            $scope.banderaNombreRepetido = true;
                        } else {
                            $scope.reiniciarToast();
                            $scope.banderaNombreRepetido = false;
                        }
                    }
                });
            }
        }

        //Método para comprobar que las fechas de la nueva campaña no cruce con otras campañas, sirve de advertencia.
        $scope.verificarRangoFechas = function (fechaInicio, fechaFin) {
            pedido.async({
                method: 'POST',
                url: URL + 'verificarRangoFechas',
                data: {
                    fechaInicio: fechaInicio,
                    fechaFin: fechaFin,
                    wizard: $scope.campania.wizardSelected
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else if (datos.resultado) {
                    $scope.arrCampaniasChocan = datos.resultado;
                    $scope.verCampaniasChocan = true;
                }
            });
        };

        //Modal de fechas en conflicto
        $scope.verAdvertencia = function () {
            $scope.presentarAdvertencia = true;
        };

        $scope.cerrarAdvertencia = function () {
            $scope.presentarAdvertencia = false;
        };

        //Valida si cuando ingresa los plazos manualmente esté correcto
        $scope.estaEnFormatoPlazo = function (plazo) {
            var esOK = true;
            if (typeof (plazo) === "string") {
                var arrayDeCadenas = plazo.split('-');
                for (var i = 0; i < arrayDeCadenas.length; i++) {
                    if (!$scope.isInteger(arrayDeCadenas[i])) {
                        var esOK = false;
                    }
                }
            }
            return esOK;
        };

        //Funcion para formatear las fechas tipo date en yyyy-mm-dd
        function formatearFecha(date) {
            return dateFormateado = date.getFullYear().toString() + '-' + (date.getMonth() + 1).toString() + '-' + date.getDate().toString();
        }

        //Setea fechas para presentar en los datepickers de la ventana de editar/nueva campaña
        $scope.setearFechas = function () {
            const date = new Date();
            let cuantosCambia = 0;
            let anio = date.getFullYear();
            let mesFin = null;
            let mesFinOp = null;
            const dia = (date.getDate() < 10) ? '0' + date.getDate().toString() : date.getDate().toString();
            const mesInicio = (date.getMonth() + 1) < 10 ? '0' + (date.getMonth() + 1).toString() : (date.getMonth() + 1).toString();
            let dateFormateadoFin = null;
            let dateFormateadoOperativo = null;
            
            if (date.getMonth() + 1 == 11) {
                cuantosCambia = 1;
                mesFinOp = '01';
            } else if (date.getMonth() + 1 > 11) {
                cuantosCambia = 2;
                mesFin = '01';
                mesFinOp = '02';
            }
            
            const dateFormateadoInicio = anio.toString() + '-' + mesInicio + '-' + dia;
            $scope.campania.fechaInicioComercial = dateFormateadoInicio;
            
            if (cuantosCambia == 2) {
                anio++;
                dateFormateadoFin = anio.toString() + '-' + mesFin + '-' + dia;
            } else {
                mesFin = (date.getMonth() + 2) < 10 ? '0' + (date.getMonth() + 2).toString() : (date.getMonth() + 2).toString();
                dateFormateadoFin = anio.toString() + '-' + mesFin + '-' + dia;
            }
            $scope.campania.fechaFinComercial = dateFormateadoFin;
            
            if (cuantosCambia >= 1) {
                anio++;                
                dateFormateadoOperativo = anio.toString() + '-' + mesFinOp + '-' + dia;
            } else {
                mesFinOp = (date.getMonth() + 3) < 10 ? '0' + (date.getMonth() + 3).toString() : (date.getMonth() + 3).toString();
                dateFormateadoOperativo = anio.toString() + '-' + mesFinOp + '-' + dia;
            }
            
            $scope.campania.fechaFinOperativo = dateFormateadoOperativo;
            setearFechaMinima();
            //</editor-fold>
        };

        function setearFechaMinima() {
            console.log("campania: ", $scope.campania);
            let fechaActual = new Date();
            $scope.fechaMin = fechaActual;
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

        //Trae artículos por categoría
        $scope.traerArticulosPorCategoria = function (buscarPorCategoria = false, sonTodos = false) {
            //<editor-fold defaultstate="collapsed" desc=" Metodo que trae los articulos ">
            var consulta = '';
            //var idCategoria = '';

            //Servia para seccionar la consulta para traer los articulos segun las categorías que se elija
            //Ahora está consultando todos los articulos de la marca, y para eso se tiene que mandar la consulta vacia
            if (buscarPorCategoria) {
                let lastIndex = $scope.objNuevoDetalle.subtipoDetalle.length - 1;
                if (sonTodos) {
                    $scope.objNuevoDetalle.subtipoDetalle.forEach((element, index) => {
                        (lastIndex === index) ? consulta = consulta.concat(element.id + ' )') : consulta = consulta.concat(element.id + ', ');
                    });
                } else {
                    consulta = $scope.objNuevoDetalle.subtipoDetalle[lastIndex].id;
                }
                //                let tamSubtipo = $scope.objNuevoDetalle.subtipoDetalle.length;
                //                if (Array.isArray($scope.objNuevoDetalle.subtipoDetalle) && $scope.objNuevoDetalle.subtipoDetalle.length > 1) {
                //                    $scope.objNuevoDetalle.subtipoDetalle.forEach((element, index) => {
                //                        //TODO: Ahora el scope.valores es un arreglo 
                //                        //                        for (var item in $scope.valores) {
                //                        //                            ($scope.valores[item] === element)? idCategoria = item : '';
                //                        //                        }
                //                        (tamSubtipo - 1 === index) ? consulta = consulta.concat(element.id + ' )') : consulta = consulta.concat(element.id + ', ');
                //                    });
                //                } else if (Array.isArray($scope.objNuevoDetalle.subtipoDetalle) && $scope.objNuevoDetalle.subtipoDetalle.length === 1) {
                //                    //                    for (var item in $scope.valores) {
                //                    //                        ($scope.valores[item] === $scope.objNuevoDetalle.subtipoDetalle[0]) ? idCategoria = item : '';
                //                    //                    }
                //                    consulta = $scope.objNuevoDetalle.subtipoDetalle[0].id;
                //                }
            }
            console.log("consulta articulos: ", consulta + ' - ' + buscarPorCategoria + ' - ' + sonTodos);
            pedido.async({
                method: 'POST',
                url: URL + 'getArticulosPorCategoria',
                data: {
                    ruc: $scope.campania.wizardSelectedRuc,
                    categoria: consulta
                }
            }).then(function (datos) {
                //console.log("datos resultantes: ", datos);
//                console.log("datos articulos: ", datos);
                if (angular.isDefined(datos.resultado.error)) {
                    $scope.showConfirm(datos.resultado.error);
                    $scope.objNuevoDetalle.subtipoDetalle.pop();
                } else {
                    // Ya no se valida si se ha seleccinado mas de una categoría, ahora consulta todos los articulos
                    //                    if ($scope.objNuevoDetalle.subtipoDetalle.length > 0) {
                    //                        
                    //                        datos.resultado.forEach((element) => $scope.arrArticulos.push(element.ctArticulos_nombre));
                    //                        $scope.verBarraProcesoSegNivelArt = false;
                    //                    } else {
                    //$scope.valores[element.ctArticulos_id] = element.ctArticulos_nombre)

                    if (buscarPorCategoria) {
                        //console.log("por categoria: ", datos.resultado);
                        if (sonTodos) {
                            $scope.articulosPorCategoria = datos.resultado;
                        } else {
                            $scope.articulosPorCategoria = $scope.articulosPorCategoria.concat(datos.resultado);
                        }
                        //$scope.desactivarAutocomplete = false;
                    } else {
                        datos.resultado.forEach((element, index) => $scope.valores.push({
                                id: element.id,
                                codigo: element.codigo,
                                nombre: element.nombre.toLowerCase() + ' - ' + element.codigo.toLowerCase(),
                                idPadre: element.idPadre}));
                        $scope.verBarraProcesoSegNivel = false;
                        //                    }
                    }

                }
            });
            //</editor-fold>
        };

        //Traer artículos por gama

        $scope.traerArticulosPorGama = function(){
            let gama = [];
            gama = $scope.objNuevoDetalle.subtipoDetalle.map(element => element.nombre);
            pedido.async({
                method: 'POST',
                url: URL + 'getArticulosPorGama',
                data: {
                    ruc: $scope.campania.wizardSelectedRuc,
                    gama: gama
                }
            }).then(function(datos)
            {
                console.log("Los datos resultado");
                console.log(datos.resultado);
                if (angular.isDefined(datos.resultado.error)){
                    $scope.showConfirm(datos.resultado.error);
                    $scope.objNuevoDetalle.subtipoDetalle.pop();
                }else{
                    $scope.articulosPorGama = datos.resultado;
                    $scope.verBarraProcesoSegNivel = false;
                }
            });
        }

        //Traer artículos por origen

        $scope.traerArticulosPorOrigen = function(){
            let origen = [];
            origen = $scope.objNuevoDetalle.subtipoDetalle.map(element => element.nombre);
            pedido.async({
                method: 'POST',
                url: URL + 'getArticulosPorOrigen',
                data: {
                    ruc: $scope.campania.wizardSelectedRuc,
                    origen: origen
                }
            }).then(function(datos){
                console.log("Los datos resultado");
                console.log(datos.resultado);
                if(angular.isDefined(datos.resultado.error)){
                    $scope.showConfirm(datos.resultado.error);
                    $scope.objNuevoDetalle.subtipoDetalle.pop();
                }else{
                    $scope.articulosPorOrigen = datos.resultado;
                    $scope.verBarraProcesoSegNivel = false;
                }
            });
        }

        //Buscar en el autocomplete de la primera condicion
        $scope.busquedaAutocompleteTipo = function (query) {
            if (query === null || query === "" || query === undefined)
                return $scope.valores;

            var results = query ? $scope.valores.filter(createFilterFor(query)) : $scope.valores;
            return results;
        };

        //Filtro para la busqueda del autocomplete
        function createFilterFor(query) {
            var lowercaseQuery = query.toLowerCase();

            return function filterFn(state) {
                return (state.nombre.search(lowercaseQuery) != -1);
            };
        }

        //Elimina todas las selecciones de las condiciones y sucursales
        $scope.eliminarTodaSeleccion = function (valor) {
            $scope.objNuevoDetalle[valor] = [];
            if (valor === 'subtipoDetalle') {
                $scope.auxFiltroArticulos = [];
                $scope.subItemSelect = "";
            }
        };

        $scope.ocultarSubSeleccion = function () {
            $scope.auxFiltroArticulos = [];
            $scope.subItemSelect = "";
        };
        
        $scope.buscarProductos = function (seleccionado) {
        if ($scope.objNuevoDetalle.tipoDetalle.desglosable === 1) {
            console.log("seleccionado", seleccionado);
            $scope.subItemSelect = seleccionado.nombre;
            $scope.auxFiltroArticulos = [];
            console.log("articulos: ", $scope.articulosPorCategoria);
            if($scope.objNuevoDetalle.tipoDetalle.nombre == 'POR CATEGORIA'){
                $scope.auxFiltroArticulos = $scope.articulosPorCategoria.filter(function (element) {
                    //if ($scope.observando || $scope.duplicando) {
                    return element.idPadre == seleccionado.id;
                    //}else{
                    //    return element.ctArticulos_categoriaId === seleccionado.id;
                    //}

                });
            }
            if($scope.objNuevoDetalle.tipoDetalle.nombre == 'POR GAMA DEL ARTICULO'){
                $scope.auxFiltroArticulos = $scope.articulosPorGama.filter(function (element){
                    return element.ctArticulos_gama == seleccionado.nombre.toUpperCase();
                });
            }
            if($scope.objNuevoDetalle.tipoDetalle.nombre == 'POR ORIGEN DEL ARTICULO'){
                $scope.auxFiltroArticulos = $scope.articulosPorOrigen.filter(function (element){
                    return element.ctArticulos_origen == seleccionado.nombre.toUpperCase();
                });
                console.log("Arreglo de productos ");
                console.log($scope.auxFiltroArticulos.length);
            }
        }

        };

        $scope.quitarTodosArticulos = function (eliminado) {
            $scope.articulosPorCategoria = $scope.articulosPorCategoria.filter((element) => element.idPadre !== eliminado.id);
            let borrarTodos = $scope.auxFiltroArticulos.every((element) => element.idPadre === eliminado.id);
            if (borrarTodos) {
                $scope.auxFiltroArticulos = [];
                $scope.subItemSelect = "";
            }
//            console.log("articulos filtrados: ", $scope.articulosPorCategoria);
        };

        $scope.quitarArticulo = function (eliminado) {
            let indiceModificar = null;
//            console.log("eliminado: ", eliminado);
            for (let index in $scope.articulosPorCategoria) {
                if (eliminado.id === $scope.articulosPorCategoria[index].id) {
                    indiceModificar = $scope.articulosPorCategoria[index].idPadre;
                    $scope.articulosPorCategoria.splice(index, 1);
                    break;
                }
            }
            //console.log("articulos por categoria: ", $scope.articulosPorCategoria);

            if (indiceModificar !== null) {
//                console.log("aux filtro articulos: ", $scope.auxFiltroArticulos);
//                console.log("index: ", indiceModificar);
                if ($scope.auxFiltroArticulos.length === 0) {
//                    console.log("entro cuando es 0");
                    for (let index in $scope.objNuevoDetalle.subtipoDetalle) {
                        if (indiceModificar === $scope.objNuevoDetalle.subtipoDetalle[index].id) {
                            $scope.objNuevoDetalle.subtipoDetalle.splice(index, 1);
                            $scope.subItemSelect = "";
                            break;
                        }
                    }
                } else {
                    for (let index in $scope.objNuevoDetalle.subtipoDetalle) {
                        if ($scope.objNuevoDetalle.subtipoDetalle[index].id === indiceModificar) {
                            if (!$scope.objNuevoDetalle.subtipoDetalle[index].hasOwnProperty("personalizo")) {
                                $scope.objNuevoDetalle.subtipoDetalle[index]["personalizo"] = 1;
                            }
                            break;
                        }
                    }
                }

            }
//            console.log("subtipo de detalle: ", $scope.objNuevoDetalle.subtipoDetalle);
        };

        $scope.cambiaAutocompleteTipo = function () {
            let sonTodos = false;
            let repetido = false;
            if ($scope.itemTipo.item !== "" && $scope.itemTipo.item !== null && $scope.itemTipo.item !== undefined) {
                console.log("tipo: ", Object.assign({}, $scope.itemTipo));
                if ($scope.objNuevoDetalle.subtipoDetalle.length === 1 && $scope.objNuevoDetalle.subtipoDetalle.includes($scope.valorPorDefecto)) {
                    $scope.objNuevoDetalle.subtipoDetalle.shift();
                    $scope.objNuevoDetalle.subtipoDetalle.push($scope.itemTipo.item);
                } else {
                    repetido = $scope.objNuevoDetalle.subtipoDetalle.some(function (element) {
                        if (element.hasOwnProperty("id") && $scope.itemTipo.item.hasOwnProperty("id")) {
                            return element.id == $scope.itemTipo.item.id;
                        } else {
                            return element.nombre.toLowerCase() == $scope.itemTipo.item.nombre.toLowerCase();
                        }
                    });

                    //!$scope.objNuevoDetalle.subtipoDetalle.includes($scope.itemTipo.item)
                    if (!repetido) {
                        if ($scope.itemTipo.item.nombre !== $scope.valorPorDefecto) {
                            $scope.objNuevoDetalle.subtipoDetalle.push($scope.itemTipo.item);
                        } else {
                            sonTodos = true;
                            $scope.objNuevoDetalle.subtipoDetalle = [];
                            $scope.valores.forEach((element) => {
                                if (element.nombre !== "TODOS") {
                                    $scope.objNuevoDetalle.subtipoDetalle.push(element);
                                }
                            });
                        }
                    } else {
                        $scope.showSimpleToast("seleccionTipos", "Ya seleccionó este elemento", 500);
                    }
                }
                if (!repetido) {
                    if ($scope.objNuevoDetalle.tipoDetalle.desglosable === 1) {
                        //$scope.desactivarAutocomplete = true;
                        if($scope.objNuevoDetalle.tipoDetalle.nombre == 'POR CATEGORIA'){
                            $scope.traerArticulosPorCategoria(true, sonTodos);
                        }
                        if($scope.objNuevoDetalle.tipoDetalle.nombre == 'POR GAMA DEL ARTICULO'){
                            console.log("Entro a traer por categorias");
                            $scope.traerArticulosPorGama();
                        }
                        if($scope.objNuevoDetalle.tipoDetalle.nombre == "POR ORIGEN DEL ARTICULO"){
                            console.log("Entro a ver por origenes toda la nota");
                            $scope.traerArticulosPorOrigen();
                        }
                    }
                }
//                console.log("subtipos: ", $scope.objNuevoDetalle.subtipoDetalle);

                $scope.searchTextTipos = "";
                $scope.itemTipo.item = undefined;
            }
        };

        $scope.borrarInputUsuario = function (valor) {
            $scope.objNuevoDetalle[valor] = [];
        };

        //Guarda cada configuración de afectacion al objeto objNuevoDetalle
        $scope.guardarConfigCondicion = function () {
            var condicion = {};
//            console.log("condicion: ", $scope.objCondicion);
            condicion = Object.assign({}, $scope.objCondicion);
            
            let banderaRepetidoBeneficios = false;
            let banderaRepetido = false;
            let existeAnteriorPlazo = false;
            let banderaEntrada = false;
            let indexPlazos = "";
            let plazoPersonalizado = "";

            if (condicion.campoAfectado.afectacion === 'PLAZOS') {
                if (typeof (condicion.valorAsignado) === "string") {
                    if (condicion.valorAsignado.includes("-")) {
                        plazoPersonalizado = condicion.valorAsignado.split("-");
                        condicion.valorAsignado = plazoPersonalizado;
                    } else {
                        plazoPersonalizado = condicion.valorAsignado;
                        condicion.valorAsignado = [plazoPersonalizado];
                    }
                }
                $scope.arrCondiciones.forEach((element, index) => {
                    if (element.campoAfectado.afectacion === 'PLAZOS') {
                        existeAnteriorPlazo = true;
                        element.valorAsignado.forEach((item) => {
                            if (!condicion.valorAsignado.includes(item)) {
                                condicion.valorAsignado.push(item);
                            }
                        });
                        indexPlazos = index;
                    }
                });
                condicion.valorAsignado.sort(function (a, b) {
                    return a - b;
                });
            } else {
                banderaRepetidoBeneficios = $scope.arrBeneficios.some(function (element) {
                    return element.campoAfectado.afectacion === condicion.campoAfectado.afectacion;
                });
                
                banderaRepetido = $scope.arrCondiciones.some(function (element) {
                    return element.campoAfectado.afectacion === condicion.campoAfectado.afectacion;
                });
                
                if (condicion.campoAfectado.afectacion === "ENTRADA") {
                    banderaEntrada = $scope.arrCondiciones.some(function (element) {
                        return element.campoAfectado.afectacion === "PORCENTAJE DE ENTRADA";    
                    });
                } else if (condicion.campoAfectado.afectacion === "PORCENTAJE DE ENTRADA") {
                    banderaEntrada = $scope.arrCondiciones.some(function (element) {
                        return element.campoAfectado.afectacion === "ENTRADA";    
                    });
                }
            }

            if (banderaRepetido) {
                $scope.showSimpleToast("divCondicion", "La condición ingresada ya se encuentra en lista", 2000);
            } else if (banderaEntrada) {
                $scope.showSimpleToast("divCondicion", "Ya se encuentra una condición referida a la entrada", 2000);
            } else if (banderaRepetidoBeneficios) {
                $scope.showSimpleToast("divCondicion", "La condición ingresada ya se encuentra en beneficios", 2000);
            } else {
                (existeAnteriorPlazo) ? $scope.arrCondiciones.splice(indexPlazos, 1) : '';
                $scope.arrCondiciones.push(condicion);
            }
//            console.log("condiciones: ", $scope.arrCondiciones);
            $scope.objCondicion = {campoAfectado: "", valorAsignado: "Ingrese valor"};
            $scope.correctoValorAsignado = false;
            cuantosClickInput1 = 0;
        };

        //Elimina la condicion configurada
        $scope.eliminarCondicion = function (index) {
            $scope.arrCondiciones.splice(index, 1);
        };

        //Quita el cero que viene por defecto al dar un click
        var cuantosClickInput1 = 0;
        $scope.resetearInput = function (input) {
            //para la afectacion principal
            (input == 0 && cuantosClickInput1 == 0) ? $scope.objCondicion.valorAsignado = "" : '';
            (input == 0) ? cuantosClickInput1++ : '';

            //para la afectacion adicional
            (input == 1 && cuantosClickInput1 == 0) ? $scope.objBeneficio.valorAsignado = "" : '';
            (input == 1) ? cuantosClickInput1++ : '';
        };

        //Valida que guarde si es sobre el precio base o precio total
        $scope.validarSelectBeneficio = function () {
            if ($scope.objBeneficio.campoAfectado.tipoDato === 'MONTO') {
                $scope.objBeneficio.baseImponible = "1";
            } else {
                delete $scope.objBeneficio.baseImponible;
            }
//            console.log("objeto beneficio: ", $scope.objBeneficio);
        };

        //Guarda las afectaciones adicionales en el desgloseAfectacion
        $scope.guardarBeneficio = function () {
            var beneficio = {};
            beneficio = Object.assign({}, $scope.objBeneficio);

                let banderaRepetido = $scope.arrBeneficios.some(function (element) {
                    return element.campoAfectado.afectacion === beneficio.campoAfectado.afectacion;
                });
                let banderaRepetidoCondiciones = $scope.arrCondiciones.some(function (element) {
                        return element.campoAfectado.afectacion === beneficio.campoAfectado.afectacion;    
                });
                if (banderaRepetido) {
                    $scope.showSimpleToast("divBeneficio", "El benecifio ingresado ya se encuentra en lista", 2000);
                } else if (banderaRepetidoCondiciones) {
                    $scope.showSimpleToast("divBeneficio", "El benecifio ingresado ya se encuentra en condiciones", 2000);
                } else {
                    $scope.arrBeneficios.push(beneficio);
                }

            $scope.objBeneficio = {campoAfectado: "", valorAsignado: "Ingrese valor"};
            $scope.correctoValorAsignado = false;
            cuantosClickInput1 = 0;
        };

        //Guarda los obsequios definidos
        $scope.agregarObsequio = function () {
            var obsequio = {};
            obsequio = Object.assign({}, $scope.objObsequio);

            if ($scope.arrObsequios.length === 0) {
                $scope.arrObsequios.push(obsequio);
            } else {
                let banderaRepetido = $scope.arrObsequios.some(function (element) {
                    return element.codigo === obsequio.codigo;
                });
                if (banderaRepetido) {
                    $scope.showSimpleToast("divObsequio", "El obsequio ingresado ya se encuentra en lista", 2000);
                } else {
                    $scope.arrObsequios.push(obsequio);
                }
            }

            $scope.objObsequio = {codigo: "", nombre: "", cantidad: ""};
        };


        //Elimina el beneficio configurado
        $scope.eliminarBeneficio = function (index) {
            $scope.arrBeneficios.splice(index, 1);
        };

        //Elimina el obsequio
        $scope.eliminarObsequio = function (index) {
            $scope.arrObsequios.splice(index, 1);
        };

        //Trae las empresas para la presentancion en el select principal de campaña
        $scope.cargarWizard = function () {
            $scope.waitWindow = true;

            pedido.async({
                method: 'POST',
                url: URL + 'getListaWizards',
                data: {
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    $scope.wizards = datos.resultado;
                }
                $scope.waitWindow = false;
            });
        };

        //Trae las configuraciones del módulo
        $scope.cargarTipos = function () {
            pedido.async({
                method: 'POST',
                url: URL + 'getListaTiposParametrizacion',
                data: {
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    $scope.tipos = datos.resultado.tiposParametrizacion;
                    $scope.tiposDescuentos = datos.resultado.tiposAfectacion;
                    $scope.tiposPlazos = datos.resultado.plazos;
                }
            });

        };

        var metodosCtrl = {
            "POR PERFIL DE COMPRADOR": 'getListaCalificacionesMatrizDual',
            "POR GAMA DEL ARTICULO": 'getListaGamas',
            "POR ORIGEN DEL ARTICULO": 'obtieneOrigen',
            "POR PRECIO DEL ARTICULO": 'getListaPrecios',
            "POR ZONA": 'obtieneZonas',
            "POR SUCURSAL DE LA EMPRESA": '',
            "POR CATEGORIA": 'obtieneCategorias',
            "POR ACTIVIDAD VETADA DEL COMPRADOR": 'getActividadVetada',
            "POR ZONA VETADA": 'getZonaVetada',
            "POR CIUDAD": 'obtieneCiudades',
            "POR PLAZO DE CREDITO": 'getPlazos',
            "POR ARTICULO": ''
        };

        //Método generalizado para traer configuraciones del módulo
        function traerConfiguracionesModulo(seleccion) {
            pedido.async({
                method: 'POST',
                url: URL + metodosCtrl[seleccion],
                data: {
                    ruc: $scope.campania.wizardSelectedRuc,
                    nombreWizard: $scope.campania.wizardSelected
                }
            }).then(function (datos) {
                console.log("resultado dtatatat seleccion: ", datos);
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    datos.resultado.forEach((element, index) => {
                        if (seleccion === "POR CATEGORIA") {
                            if (element.NOMBRE !== 'MOTOS' && element.NOMBRE !== 'SERVICIOS') {
                                $scope.valores.push({
                                    id: element.ID,
                                    nombre: element.NOMBRE.toLowerCase()});
                            }
                        } else if(seleccion === "POR ORIGEN DEL ARTICULO"){
                            if(element.length == 0 ){
                                $scope.valores.push({
                                    id: 1,
                                    nombre: "todos"
                                })
                            }
                        } else {
                            $scope.valores.push({"nombre": element.toLowerCase()});
                        }
                    });
                    $scope.verBarraProcesoSegNivel = false;
                }
            });
        }

        //Utilizado para traer valores según la elección del select Tipo de campaña
        $scope.cargarValores = function (tiposSelected) {
//            console.log("seleccionado: ", $scope.objNuevoDetalle.tipoDetalle);

            ($scope.duplicando) ? '' : $scope.objNuevoDetalle.subtipoDetalle = [];
            $scope.valores = [{"nombre": $scope.valorPorDefecto}];
            ($scope.duplicando) ? '' : $scope.articulosPorCategoria = [];
            $scope.auxFiltroArticulos = [];
            $scope.subItemSelect = "";
            $scope.verBarraProcesoSegNivel = true;

            //TODO: Hacerle general en este switch segun las que se activen o desactiven en la configuración
            switch (tiposSelected) {
                case "POR ARTICULO":
                    $scope.traerArticulosPorCategoria();
                    break;

                case "POR CATEGORIA":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR SUCURSAL DE LA EMPRESA":
                    //$scope.valores = $scope.oficinas;
                    break;

                case "POR GAMA DEL ARTICULO":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR ZONA":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR PERFIL DE COMPRADOR":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR ORIGEN DEL ARTICULO":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR ACTIVIDAD VETADA DEL COMPRADOR":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR PLAZO DE CREDITO":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR CIUDAD":
                    traerConfiguracionesModulo(tiposSelected);
                    break;

                case "POR ZONA VETADA":
                    traerConfiguracionesModulo(tiposSelected);
                    break;
            }
        };

        //Trae las sucursales de la empresa seleccionada
        $scope.cargarOficinas = function (nombreWizard) {
            pedido.async({
                method: 'POST',
                url: URL + 'obtieneRuc',
                data: {
                    nombreWizard: nombreWizard
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.errores)) {
                    avisos.alerta(datos.errores);
                } else {
                    $scope.campania.wizardSelectedRuc = datos.resultado;
                    pedido.async({
                        method: 'POST',
                        url: URL + 'obtieneSucursalesConcesionarios',
                        data: {
                            ruc: $scope.campania.wizardSelectedRuc
                        }
                    }).then(function (datos) {
//                        console.log("datos: ", datos);
                        if (angular.isDefined(datos.errores)) {
                            avisos.alerta(datos.errores);
                        } else {
                            $scope.nivelesOficinas = datos.resultado.niveles;
                            $scope.oficinas = datos.resultado.oficinas;
                        }
                    });
                }
            });
        };


        $scope.clickGrupoSucursales = function (valor) {
            if ($scope.lugarSelected.length === 0) {
                soloNombres = $scope.nivelesOficinas[valor].map(function (element) {
                    return element[0];
                });
            }
        };


        $scope.cambiaSelectOficinas = function () {
            let todosIncluidos = true;
            let indexAEliminar = null;
            let indexFinal = $scope.nivelesOficinas.length - 1;
            let indexPadres = [];
            let indexPadresAux = [];

            $scope.lugarSelected.forEach((elementL, index) => {
                if (!soloNombres.includes(elementL[0])) {
                    todosIncluidos = false;
                    indexAEliminar = index;
                }
            });

            if (!todosIncluidos) {
                $scope.showSimpleToast('seleccionSucursales', 'Seleccione opciones de la misma categoría', 2000);
                $scope.lugarSelected.splice(indexAEliminar, 1);
            } else {
                $scope.lugarSelected.forEach((element) => {
                    indexPadres.push(element[2]);
                });

                $scope.nivelesOficinas.forEach((element, ind) => {
                    if (ind !== 0) {
                        //console.log("element", element);
                        element.forEach((elem) => {
                            //console.log("elem :", elem[1]);
                            let index = indexPadres.indexOf(elem[1]);
                            if (index !== -1) {
                                //indexPadres.splice(index, 1);
                                indexPadresAux.push(elem[2]);
                            }
                        });
                        (indexPadresAux.length > 0) ? indexPadres = indexPadresAux : '';

                        if (indexFinal === ind) {
                            $scope.objNuevoDetalle.sucursal = [];
                            element.forEach((item) => {
                                let index = indexPadres.indexOf(item[2]);
                                if (index !== -1) {
                                    $scope.objNuevoDetalle.sucursal.push({id: item[2], nombre: item[0]});
                                }
                            });
                        }
                    }

                });
            }
        };


        $scope.isInteger = function (value) {
            return Number(value) % 1 === 0;
        };

        //Oculta el toast de advertencia para los campos de afectación
        $scope.reiniciarToastCondicion = function () {
            $mdToast.hide(false);
        };

        //Verifica que elija un plazo
        $scope.comprobarSeleccionPlazos = function (valor) {
            (valor !== '') ? $scope.correctoValorAsignado = true : $scope.correctoValorAsignado = false;
        };

        //Verifica si el valor afectado ingresado es correcto según el que elija el el select anterior
        var valorIncorrecto = 0;
        $scope.verificarCamposCondicion = function (valor, div) {
            var objReferencia = {};
            (valor === "condiciones") ? objReferencia = $scope.objCondicion : objReferencia = $scope.objBeneficio;

            if (objReferencia.valorAsignado) {
                //Verifica si ingresa un numero entero para esos campos que lo requieren
                if (objReferencia.campoAfectado.tipoDato === "NUMERO" && objReferencia.campoAfectado.afectacion !== "PLAZOS" &&
                        (isNaN(Number(objReferencia.valorAsignado)) || (!$scope.isInteger(objReferencia.valorAsignado)))) {
                            console.log("Entro a hacer esta veri de FG 1");
                    (valorIncorrecto === 0) ? $scope.showSimpleToast(div, "Se espera un valor numérico sin decimales") : '';
                    valorIncorrecto = 1;

                } else if ((objReferencia.campoAfectado.tipoDato === "PORCENTAJE" || objReferencia.campoAfectado.tipoDato === "MONTO") &&
                        isNaN(Number(objReferencia.valorAsignado))) {
                            console.log("Entro a hacer esta veri de FG 2");

                    (valorIncorrecto === 0) ? $scope.showSimpleToast(div, "Se espera un valor numérico, debe usar el 'punto' para decimales") : '';
                    valorIncorrecto = 1;

                } else if (objReferencia.campoAfectado.afectacion === "PLAZOS" && !$scope.estaEnFormatoPlazo(objReferencia.valorAsignado)) {
                    //Verifica si el plazo es correcto
                    console.log("Entro a hacer esta veri de FG 3");
                    (valorIncorrecto === 0) ? $scope.showSimpleToast(div, "Ingrese un formato correcto, Ej: 12-13-14") : '';
                    valorIncorrecto = 1;
                }else if (objReferencia.campoAfectado.afectacion === "FONDO DE GARANTIA" && typeof objReferencia.valorAsignado !== 'boolean') {
                    //Verifica si el plazo es correcto
                    console.log("Entro a hacer esta veri de FG 4");
                    (valorIncorrecto === 0) ? $scope.showSimpleToast(div, "Seleccione una opción facilitada") : '';
                    valorIncorrecto = 1;
                } else {
                    //Si pone un valor correcto oculta el Toast y reinicia la variable valorIncorrecto
                    valorIncorrecto = 0;
                    $mdToast.hide(false);
                }


                //Setea la variable para la última condición de deshabilitar el botón de guardar
                (valorIncorrecto) ? $scope.correctoValorAsignado = false : $scope.correctoValorAsignado = true;
            } else {
                $scope.correctoValorAsignado = false;
            }
        };

        console.log("El valor de valorIncorrecto ");
        console.log(valorIncorrecto);
        console.log($scope.correctoValorAsignado);
        //Cierra la ventana principal de configuración
        $scope.cerrarConfiguracion = function (valor) {
            if (valor !== "duplicado") {
                var objDiv = document.getElementById("pantallaCampania");
                objDiv.scrollTop = objDiv.scrollHeight = 0;
                congelarScrollBody(valor);
                resetearObjetos();
                $scope.verNuevaCampania = false;
                $mdToast.hide(false);
            } else {
                if ($scope.cambioCondiciones) {
                    var confirm = $mdDialog.confirm()
                            .title("¿Desea descartar el duplicado de campaña?")
                            .textContent("No se guardará ninguna información")
                            .ariaLabel('Descartar campania')
                            .targetEvent()
                            .ok('GUARDAR')
                            .cancel('DESCARTAR');
                    $mdDialog.show(confirm).then(function () {
                        //confirmar
                        $scope.guardar();
                    }, function () {
                        //cancelar
                        $scope.cerrarConfiguracion(1);
                    });
                } else {
                    $scope.cerrarConfiguracion(1);
                }

            }
        };

        //Guarda la campaña y configuracion
        $scope.guardar = function () {
            if ($scope.campania.wizardSelected == 0 || $scope.campania.wizardSelected == "") {
                $scope.showConfirm("Seleccione una empresa.");
                return;
            }

            if ($scope.campania.nombre == "") {
                $scope.showConfirm("Ingrese el nombre de la campaña.");
                return;
            }

            $scope.campania.nombre = angular.uppercase($scope.campania.nombre);
            $scope.campania.descripcion = angular.uppercase($scope.campania.descripcion);

            if (new Date($scope.campania.fechaInicioComercial) > new Date($scope.campania.fechaFinComercial) ||
                    new Date($scope.campania.fechaInicioComercial) > new Date($scope.campania.fechaFinOperativo)) {
                $scope.showConfirm("Revise el rango de fechas. La fecha \' Fin Comercial \' debe ser mayor a la fecha \' Inicio Comercial\' .  \n\
                        La fecha \' Fin Operativo\'  debe ser mayor a la fecha \' Inicio Comercial\' .");
                return;
            }

//            console.log("detalle campaña: ", $scope.objNuevoDetalle);
//            console.log("arreglo condiciones: ", $scope.arrCondiciones);
//            console.log("arreglo beneficios: ", $scope.arrBeneficios);
//            console.log("cabecera campania: ", $scope.campania);

            if ($scope.objNuevoDetalle.tipoDetalle.desglosable === 0) {
                $scope.objNuevoDetalle.productos = $scope.objNuevoDetalle.subtipoDetalle;
                $scope.objNuevoDetalle.subtipoDetalle = [];
            } else if($scope.objNuevoDetalle.tipoDetalle.nombre == "POR ARTICULO") {
                $scope.objNuevoDetalle.productos = $scope.articulosPorCategoria;
            } else if($scope.objNuevoDetalle.tipoDetalle.nombre == "POR GAMA DEL ARTICULO"){
                $scope.objNuevoDetalle.productos = $scope.articulosPorGama;
            }else if($scope.objNuevoDetalle.tipoDetalle.nombre == "POR ORIGEN DEL ARTICULO"){
                $scope.objNuevoDetalle.productos = $scope.articulosPorOrigen;
            }
            $scope.objNuevoDetalle.condiciones = $scope.arrCondiciones;
            $scope.objNuevoDetalle.beneficios = $scope.arrBeneficios;
            $scope.objNuevoDetalle.obsequios = $scope.arrObsequios;

            $scope.guardando = true;
            $scope.waitWindow = true;
            pedido.async({
                method: 'POST',
                url: URL + 'guardarParametrizacion',
                data: {
                    cabeceraCampania: $scope.campania,
                    detalleCampania: $scope.objNuevoDetalle
                }
            }).then(function (datos) {
                if (angular.isDefined(datos.resultado)) {
                    $timeout(function () {
                        $scope.tabuCampanias++;
                    }, 300);

                    $scope.guardando = false;
                    $scope.waitWindow = false;
                    if (datos.resultado === 'Nueva campaña guardada correctamente') {
                        $scope.cerrarConfiguracion(1);
                    }
                    $scope.showConfirm(datos.resultado);
                }
            });
        };

        //Para solo visualizacion de la configuracion de la campaña
        $scope.verConfiguracionCampania = function (campania) {
            console.log("ver campaña: ", campania);
            $scope.duplicarCampania(campania, "ver");
        };

        //Sirve para duplicar alguna campaña para que puedan crear más rapidamente una campaña parecida
        $scope.duplicarCampania = function (campania, proceso = "duplicar") {
            $scope.waitWindow = true;
            congelarScrollBody(0);

            pedido.async({
                method: 'POST',
                url: URL + 'traerObjetoCampania',
                data: {
                    campaniaId: campania.id
                }
            }).then(function (datos) {
//                console.log("lo que trae al duplicar: ", datos);
                if (datos.hasOwnProperty("resultado")) {
                    $scope.campania = Object.assign({}, datos.resultado);
                    (proceso === "duplicar") ? $scope.campania.nombre += " - copia" : '';
                    (proceso === "duplicar") ? $scope.duplicando = true : $scope.observando = true;

                    //Guardar en los objetos para presentar la configuracion
                    if (datos.resultado.detalle.tipoDetalle.desglosable === 0) {
                        $scope.objNuevoDetalle = Object.assign({}, datos.resultado.detalle);
                        $scope.objNuevoDetalle.subtipoDetalle = $scope.objNuevoDetalle.productos;
                    } else {
                        $scope.objNuevoDetalle = Object.assign({}, datos.resultado.detalle);
                        $scope.articulosPorCategoria = $scope.objNuevoDetalle.productos;
                    }

//                    console.log("detalles: ", $scope.objNuevoDetalle);

                    if (proceso === "duplicar") {
                        $scope.cargarOficinas($scope.campania.wizardSelected);
                        $scope.cargarValores($scope.objNuevoDetalle.tipoDetalle.nombre);
                    }

                    $scope.arrCondiciones = $scope.objNuevoDetalle.condiciones;
                    $scope.arrBeneficios = $scope.objNuevoDetalle.beneficios;
                    $scope.arrObsequios = $scope.objNuevoDetalle.obsequios;

                    $scope.waitWindow = false;
                    $scope.verNuevaCampania = true;
                }

            });
        };

        // Presenta el dialog para eliminar, inactivar o activar alguna campaña
        $scope.showConfirmEliminar = function (ev, campania, proceso) {
            let texto = 'Activado';
            var confirm = $mdDialog.confirm()
                    .title("¿Está seguro que desea " + proceso + " la campaña?")
                    .textContent(campania.nombre)
                    .ariaLabel('Eliminar campania')
                    .targetEvent(ev)
                    .ok('CONTINUAR')
                    .cancel('CANCELAR');
            $mdDialog.show(confirm).then(function () {
                $scope.waitWindow = true;
                pedido.async({
                    method: 'POST',
                    url: URL + 'eliminaCampania',
                    data: {
                        campaniaId: campania.id,
                        campaniaNombre: campania.nombre,
                        proceso: proceso
                    }
                }).then(function (data) {
                    $scope.waitWindow = false;
                    if (angular.isDefined(data.resultado.error)) {
                        $scope.showConfirm("<lang>Advertencia</lang>", data.resultado.error);
                    } else {
                        if (angular.isDefined(data.resultado)) {
                            if (proceso === 'eliminar') {
                                texto = 'Eliminado';
                            } else if (proceso === 'inactivar') {
                                texto = 'Inactivado';
                            }
                            $scope.showConfirm(texto + " correctamente.", data.resultado.cuantas + " Campaña - " + data.resultado.nombre);
                        }
                        $timeout(function () {
                            $scope.tabuCampanias++;
                        }, 500);
                    }
                });
            }, function () {

            });
        };

        //Cuando editan el valor de una fecha, modifica el registro de la campaña
        $scope.$watch('cambioFecha.fechaAux', function () {
//            console.log("cambio fecha aux");
//            console.log("control: ", control);
            if (typeof ($scope.cambioFecha.fechaAux) === 'object' && control === 2) {
//                console.log("fecha: ....", $scope.cambioFecha.fechaAux.toString());
                //let fecha = formatearFecha($scope.cambioFecha.fechaAux);
                //console.log("fecha formateada: --", fecha);
//                console.log("fecha timestamp: ---", Date.parse($scope.cambioFecha.fechaAux));
                let fechaFormateada = Date.parse($scope.cambioFecha.fechaAux) / 1000;
                if (tipoFecha === 'fechaInicioComercial') {
                    if ($scope.campaniaEditar.fechaFinComercial <= fechaFormateada) {
                        $scope.showConfirm('No se puede ingresar fecha mayor o igual a la Fecha Fin', 'Intente de nuevo');
                        $scope.cancelarCambioFecha();
                        return;
                    } else {
                        $scope.campaniaEditar.fechaInicioComercial = fechaFormateada;
                        control = 1;
                    }
                }

                if (tipoFecha === 'fechaFinComercial') {
                    if ($scope.campaniaEditar.fechaInicioComercial >= fechaFormateada) {
                        $scope.showConfirm('No se puede ingresar fecha menor o igual a la Fecha Inicio', 'Intente de nuevo');
                        $scope.cancelarCambioFecha();
                        return;
                    } else {
                        $scope.campaniaEditar.fechaFinComercial = fechaFormateada;
                        control = 1;
                    }
                }
//                console.log("campania modificada: ", $scope.campaniaEditar);
            }
            control++;

        });
        
        //Guarda el indicativo de que fecha está modificando
        $scope.focusEditarFecha = function (fecha) {
            tipoFecha = fecha;
        };
        
        //Guarda la edición de fechas
        $scope.guardarEdicionFechas = function () {
            var confirm = $mdDialog.confirm()
                    .title("¿Desea modificar las fechas?")
                    .textContent('')
                    .ariaLabel('Cambiar fechas')
                    .targetEvent()
                    .ok('CONTINUAR')
                    .cancel('CANCELAR');
            $mdDialog.show(confirm).then(function () {
                $scope.waitWindow = true;
                pedido.async({
                    method: 'POST',
                    url: URL + 'editarFechasCampania',
                    data: {
                        campania: $scope.campaniaEditar
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
            $scope.indexFecha = "";
            control = 1;
            $scope.campaniaEditar = {};
        };
        
        //Para presentar el datapicker para edición de fecha
        $scope.validarBotonesFecha = function () {
            if ($scope.indexFecha === "") {
                return true;
            } else {
                return false;
            }
        };
        
        //Al clickear el boton de edición de fecha
        $scope.editarFecha = function (index, x) {
            $scope.campaniaEditar = Object.assign({}, x);
//            console.log("campania copiada: ", $scope.campaniaEditar);
            $scope.indexFecha = index;
            var fechaActual = new Date();
            $scope.cambioFecha.fechaAux = fechaActual;
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

            congelarScrollBody(0);

            $timeout(function () {
                $scope.waitWindow = false;
                $scope.verNuevaCampania = true;
            }, 500);

            $scope.setearFechas();
        };

        //Reinicia todas las variables
        function resetearObjetos() {
            $scope.duplicando = false;
            $scope.guardando = false;
            $scope.banderaNombreRepetido = true;
            $scope.observando = false;
            $scope.presentarAdvertencia = false;
            $scope.verCampaniasChocan = false;

            $scope.campania.nombre = "";
            $scope.campania.descripcion = "";
            $scope.campania.wizardSelected = "";
            $scope.campania.wizardSelectedRuc = "";
            $scope.campania.fechaInicioComercial = 0;
            $scope.campania.fechaFinComercial = 0;
            $scope.campania.fechaFinOperativo = 0;
            $scope.campania.conRecursoConcesionario = false;
            $scope.campania.conObsequio = false;

            $scope.objNuevoDetalle.obsequios = [];
            $scope.objNuevoDetalle.beneficios = [];
            $scope.objNuevoDetalle.condiciones = [];
            $scope.objNuevoDetalle.subtipoDetalle = [];
            $scope.objNuevoDetalle.sucursal = [];
            $scope.objNuevoDetalle.tipoDetalle = "";

            $scope.valores = [];
            $scope.oficinas = [];
            $scope.nivelesOficinas = [];
            $scope.lugarSelected = [];
            soloNombres = [];

            $scope.itemTipo.item = "";
            $scope.searchTextTipos = "";

            $scope.arrBeneficios = [];
            $scope.arrCondiciones = [];
            $scope.arrObsequios = [];

            $scope.auxFiltroArticulos = [];
            $scope.articulosPorCategoria = [];
            $scope.subItemSelect = "";

            $scope.objCondicion = {campoAfectado: "", valorAsignado: "Ingrese valor"};
            $scope.objBeneficio = {campoAfectado: "", valorAsignado: "Ingrese valor"};
        }

}]);


//_FIN_DE_ARCHIVO