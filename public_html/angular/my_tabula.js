//
//******************* MODULO ANGULAR PARA UTILIZAR TABULA ********************
//  Mejoras de seguridad
//  1) XSS por construcción de HTML mediante concatenación de strings
//     (element.append/element.html) con datos que pueden variar en tiempo
//     de ejecución (titulo, etiqueta, nombres de campo), sin escape.
//  2) Inyección de expresiones Angular: se construían atributos como
//     ng-click / ng-class concatenando "scope.campo" directamente dentro
//     del string de la expresión y luego se compilaban con $compile.
//     En AngularJS 1.4.8 el sandbox de expresiones tiene bypasses
//     conocidos, así que una expresión Angular construida con datos no
//     estáticos equivale a poder ejecutar JavaScript arbitrario.
//     -> Se reemplaza por funciones de scope estáticas (sin interpolar
//        valores dentro del string de la expresión).
//  3) "Prototype pollution": se usaban nombres de campo recibidos como
//     clave dinámica de un objeto (obj[campo] = valor). Si "campo" fuese
//     alguna vez "__proto__", "constructor" o "prototype", se contamina
//     el prototipo global de Object. Se añade una función de validación.
//  4) Redirección insegura: se asignaba window.location.href con una URL
//     devuelta por el servidor (data.archivo) sin validar el esquema, lo
//     que permitiría URLs "javascript:" si esa respuesta llegase alguna
//     vez manipulada. Se valida que sea una ruta relativa.
//  5) Variable global implícita (fuga de scope) en tabulaEjecutar, que
//     además podía ser sobrescrita por cualquier otro script de la página.
//  6) Interpolación de valores potencialmente no numéricos directamente
//     dentro de strings de expresiones Angular (ng-click del paginador).
//     Se fuerza a entero seguro antes de insertarlos.

var tabulaModulo = angular.module('my.tabula', ['my.datepicker']);

//Devuelve un array con los elementos que cumplen la búsqueda hecha con los criterios recibidos
//Usos:
//  (lista | timestamprange:objeto) devuelve una lista con los elementos que contienen un timestamp >= objeto.desde y <=objeto.hasta en la propiedad objeto.campo
tabulaModulo.filter('timestamprange', function () {
    return function (lista, criterios) {
        if (angular.isUndefined(criterios)) {
            return lista; //no enviaron criterios de búsqueda, devuelva la lista completa
        }
        if (!angular.isObject(criterios)) {
            return lista; //no enviaron los criterios de búsqueda como un objeto javascript, devuelva la lista completa
        }
        if (angular.isUndefined(criterios.campo) || angular.isUndefined(criterios.desde) || angular.isUndefined(criterios.hasta)) {
            return lista;  //los criterios no tienen el campo, el valor desde o el valor hasta, devuelva la lista completa
        }
        // SEGURIDAD: evita usar __proto__/constructor/prototype como nombre de campo
        if (!objectKeySeguro(criterios.campo)) {
            return lista;
        }
        var respuesta = new Array();
        if (lista) {
            var arrayLength = lista.length;
            if (criterios.desde != "") { //llego un valor "desde"
                criterios.desdeDate = criterios.desde.getTime() / 1000; //dividalo para 1000 para usar UNIX timestamp
            }
            if (criterios.hasta != "") { //llego un valor "hasta"
                criterios.hastaDate = criterios.hasta.getTime() / 1000; //dividalo para 1000 para usar UNIX timestamp
            }
            for (var i = 0; i < arrayLength; i++) {  //para cada elemento de la lista...
                var origen = lista[i];
                if (angular.isUndefined(origen[criterios.campo])) {
                    respuesta.push(origen);  //si no tiene el "campo", no lo filtre
                    continue;
                } else {
                    //verifique que pase los criterios de filtro
                    var valido = true;
                    if (criterios.desde != "") {
                        if (origen[criterios.campo] < criterios.desdeDate) {
                            valido = false;
                        }
                    }
                    if (criterios.hasta != "") {
                        if (origen[criterios.campo] > criterios.hastaDate) {
                            valido = false;
                        }
                    }
                    if (valido) {
                        //inclúyalo en la lista
                        respuesta.push(origen);
                        continue;
                    }
                }
            }
            return respuesta;
        }
    }
});

//Devuelve un array con los elementos que cumplen la búsqueda hecha con los criterios recibidos
//Usos:
//  (lista | numberrange:objeto) devuelve una lista con los elementos que contienen un valor >= objeto.desde y <=objeto.hasta en la propiedad objeto.campo
tabulaModulo.filter('numberrange', function () {
    return function (lista, criterios) {
        if (angular.isUndefined(criterios)) {
            return lista; //no enviaron criterios de búsqueda, devuelva la lista completa
        }
        if (!angular.isObject(criterios)) {
            return lista; //no enviaron los criterios de búsqueda como un objeto javascript, devuelva la lista completa
        }
        if (angular.isUndefined(criterios.campo) || angular.isUndefined(criterios.desde) || angular.isUndefined(criterios.hasta)) {
            return lista; //los criterios no tienen el campo, el valor desde o el valor hasta, devuelva la lista completa
        }
        // SEGURIDAD: evita usar __proto__/constructor/prototype como nombre de campo
        if (!objectKeySeguro(criterios.campo)) {
            return lista;
        }
        var respuesta = new Array();
        if (lista) {
            var arrayLength = lista.length;
            for (var i = 0; i < arrayLength; i++) {
                var origen = lista[i];
                if (angular.isUndefined(origen[criterios.campo])) {
                    respuesta.push(origen); //si no tiene el "campo", no lo filtre
                    continue;
                } else {
                    //verifique que pase los criterios de filtro
                    var valido = true;
                    if (criterios.desde != "") { //llego un valor "desde"
                        if (origen[criterios.campo] < criterios.desde) {
                            valido = false;
                        }
                    }
                    if (criterios.hasta != "") { //llego un valor "hasta"
                        if (origen[criterios.campo] > criterios.hasta) {
                            valido = false;
                        }
                    }
                    if (valido) {
                        respuesta.push(origen);
                        continue;
                    }
                }
            }
            return respuesta;
        }
    }
});

////directivas de tabla tipo "tabula"
//uso:
//<table tabula="xyz" url="../xyz/XyzCtrl.php?act=getAll" post-carga="ajustaDatos(filas,extra)" post-ejecuta="avisaResultado(accion,resultado)" local pagina-inicial="3" tamanio-inicial="33">
// donde xyz es un nombre unico para esta tabla; 
// url es una dirección en el servidor que entrega datos en formato tabula-JSON; 
// post-carga es una funcion opcional que se ejecuta sobre los datos antes de mostrarlos, esta función debe estar definida en el scope del controlador javascript y debe recibir los parametros filas y extra; 
// post-ejecuta es una funcion opcional que se ejecuta luego de una ejecucion en el servidor de una subdirectiva tabula-ejecutar, esta función debe estar definida en el scope del controlador javascript y debe recibir los parametros accion,resultado donde accion es el identificador de esta acción y resultado es cualquier objeto o variable javascript;
// autoejecuta es un objeto opcional que permite inyectar un objeto para que dispare la ejecucion de una accion en el servidor (de tipo tabula-ejecutar) llevando ademas de los datos del tabula parametros adicionales opcionales. El objeto debe tener formato {observa-y-recarga:0,accion:'nombre de la accion',parametros:{objeto de estructura arbitraria, o vacio}}.  La accion automatica se ejecuta cada vez que haya un cambio en objeto.observaYRecarga
//pagina-inicial es un atributo opcional que permite determinar la página en la que se presentarán inicialmente los datos
//tamanio-inicial es un atributo opcional que permite determinar el número de registros inicial en el que se presentarán inicialmente los datos
//observa-y-recarga es un atributo opcional que permite inyectar una variable javascript que será observada por cambios; cuando cambie, tabula se recargará.  Generalmente esta variable también es parte del url para que la recarga traiga datos diferentes
// local (funconalidad experimental) es un atributo opcional que hace que la tábula se filtre localmente; si 'local' no está presente, la tábula se remite al servidor en cada ocasión. El ordenamiento se hace siempre en el servidor de todos modos. Al trabajar en local no sirve el paginador y solo se pueden obtener hasta 1000 registros. Esta es una funcionalidad experimental
// ilimitada es un atributo opcional que hace que la tabula no espere un set cerrado de datos
// estatica es un atributo opcional que hace que los datos no se vacien al paginar o filtrar o recargar la tabla
// exige-filtro es un atributo opcional que hace que no se busquen datos mientras el usuario no haya ingresado al menos un criterio de filtrado
// fixed-id es un atributo opcional que permite implementar filas y columnas fijas utilizando la libreria angular/fixedtabla/gridviewscroll.js
//          ver un ejemplo en Modulos Encabezado
tabulaModulo.directive('tabula', function (pedido) {
    return {
        restrict: "A", //solo usar como atributo
        replace: true,
        transclude: true,
        scope: {
            tabula: "@", //valores que se toman desde los atributos: nombre de esta tabula
            url: "@", //valores que se toman desde los atributos: url que actúa como fuente de datos en el servidor
            observaYRecarga: "=", //variable de estado que recarga automáticamente la tábula
            postCarga: "&", //función javascript opcional a ejecutar luego de recibir los datos desde el servidor y antes de presentarlos
            postEjecuta: "&", //función javascript opcional a ejecutar luego de haber realizado una ejecucion en el servidor de una funcion de las subdirectivas tabula-ejecutar
            detalleIdentificador: "@", //nombre del campo de id asociado a detalleId y funcionDetalle
            detalleId: "=", //variable de estado que carga un registro específico; debe combinarse con funcionDetalle
            funcionDetalle: "&", //function javascript opcional a ejecutar cuando cambia el valor de detalleId y se ha cargado un ítem específico
            autoejecuta: "=", //objeto para ejecucion automatica
            waitTabula: "=", //variable wait
            fixedId: "@" //identificador de estilos para fijar columas y encabezados en el tabula
        },
        controller: function ($scope, avisos, $window, $filter, $timeout) {
            this.primeraBusqueda = [];
            $scope.ultimaPagina = -1;
            $scope.ultimoPedido = 0;
            $scope.cargaDetalle = function (identificador, valor) {
                if (angular.isUndefined($scope.dataset) || angular.isUndefined($scope.dataset.filas)) {
                    $timeout(function () {
                        $scope.cargaDetalle(identificador, valor)
                    }, 100);
                    return;
                }
                //esta este item cargado?
                var encontre = false;
                angular.forEach($scope.dataset.filas, function (fila) {
                    if (!encontre) {
                        if (fila[identificador] == valor) {
                            $scope.funcionDetalle({fila: fila});
                            encontre = true;
                        }
                    }
                });
                if (!encontre) {
                    var params = {};
                    params.identificador = identificador;
                    params.valor = valor;
                    $scope.waitTabula = true;
                    pedido.async({
                        method: 'POST',
                        url: $scope.url,
                        data: params
                    }).then(function (data) {
                        if (data.errores) {
                            $scope.waitTabula = false;
                            avisos.alerta(data.errores);
                        } else {
                            //si el resultado fue positivo:
                            //actualiza el modelo localmente
                            $scope.dataset.filas.push(data.fila);
                            $scope.funcionDetalle({fila: data.fila});
                            $scope.waitTabula = false;
                        }
                    });
                }
            };
            $scope.cargaTabula = function () { //trae datos desde el servidor
                $scope.waitTabula = true;
                if ($scope.local && $scope.cuantasCargas > 0 && !$scope.dataset.exporta) {
                    //no haga nada si es tabula local, o si no pidieron exportar a CSV
                    $scope.waitTabula = false;
                    return;
                }
                //ejecute la peticion.  La url debe saber responder en formato tabula-JSON
                if ($scope.dataset.exporta || $scope.dataset.ejecuta) {
                    //nothing
                } else {
                    if (!$scope.estatica) {
                        $scope.dataset.filas = [];
                    }
                }
                $scope.ultimoPedido = Date.now();
                pedido.async({
                    method: 'POST',
                    url: $scope.url,
                    data: {
                        "pagina": $scope.dataset.pagina,
                        "tamanio": $scope.dataset.tamanio,
                        "ordenapor": $scope.dataset.ordenapor,
                        "reversa": $scope.dataset.reversa,
                        "filtro": $scope.dataset.filtro,
                        "exporta": $scope.dataset.exporta,
                        "ejecuta": $scope.dataset.ejecuta,
                        "ejecutaParametros": $scope.dataset.ejecutaParametros,
                        "ilimitada": $scope.ilimitada,
                        "exigeFiltro": $scope.exigeFiltro,
                        "_identificadorPedido_": $scope.ultimoPedido
                    }
                }).then(function (data) {
                    //si este es el ultimo pedido de este tabula
                    if ($scope.ultimoPedido === data._identificadorPedido_) {
                        if (angular.isDefined(data)) {
                            if (angular.isDefined(data.errores)) {  //alerte en caso de errores
                                $scope.waitTabula = false;
                                avisos.alerta(data.errores);
                            } else {
                                if ($scope.dataset.exporta) {  //es una iteración de exportar datos
                                    //solo debemos haber recibido un nombre de archivo
                                    if (angular.isUndefined(data.archivo)) {
                                        $scope.waitTabula = false;
                                        avisos.alerta("<lang>No se recibió un archivo CSV</lang>");
                                        $scope.dataset.exporta = false;
                                    } else if (!urlSegura(data.archivo)) {
                                        // SEGURIDAD: nunca navegar a una URL con esquema explícito
                                        // (javascript:, data:, etc.) devuelta por el servidor.
                                        $scope.waitTabula = false;
                                        avisos.alerta("<lang>La URL de descarga recibida no es válida</lang>");
                                        $scope.dataset.exporta = false;
                                    } else {
                                        $scope.waitTabula = false;
                                        $window.location.href = data.archivo;
                                        $scope.dataset.exporta = false;
                                    }
                                } else if ($scope.dataset.ejecuta) {
                                    //es una iteración de ejecutar datos
                                    //debemos haber recibido una respuesta
                                    //dicha respuesta se la pasamos a una funcion externa del scope con el nombre de la ejecucion y la respuesta
                                    if (angular.isUndefined(data.respuesta)) {
                                        $scope.waitTabula = false;
                                        avisos.alerta("<lang>No se recibió una respuesta</lang>");
                                        $scope.dataset.ejecuta = false;
                                    } else {
                                        if ($scope.hayPostEjecuta && angular.isFunction($scope.postEjecuta)) {
                                            //llame a la funcion postEjecuta si existe
                                            $scope.postEjecuta({
                                                accion: $scope.dataset.ejecuta,
                                                resultado: data.respuesta
                                            });
                                        }
                                        $scope.dataset.ejecuta = false;
                                        $scope.waitTabula = false;
                                    }
                                } else {
                                    $scope.cuantasCargas++;
                                    if (angular.isUndefined(data.filas)) {
                                        $scope.waitTabula = false;
                                        console.log("<lang>No se recibieron datos</lang>");
                                        return;
                                    } else {
                                        if ($scope.hayPostCarga && angular.isFunction($scope.postCarga)) {  //prepare los datos de ser neceasario
                                            data.filas = $scope.postCarga({filas: data.filas, extra: data.extra});
                                        }
                                        if ($scope.local) { //duplique los datos para procesamiento local
                                            $scope.dataset.filasBK = data.filas;
                                        }
                                        if ($scope.ilimitada && $scope.dataset.pagina > 1 && data.filas.length == 0) {
                                            $scope.dataset.pagina--;
                                            $scope.ultimaPagina = $scope.dataset.pagina;
                                            $scope.cargaTabula();
                                            return;
                                        }
                                        //alimente los datos recibidos
                                        $scope.dataset.filas = data.filas;
                                        $scope.dataset.cuantos = data.cuantos;
                                        //carga datos que llegar en el cajón de extras
                                        if (angular.isDefined(data.extra)) {
                                            $scope.dataset.extra = data.extra;
                                        }
                                        if (angular.isUndefined($scope.$parent.tabulas))
                                            $scope.$parent.tabulas = [];
                                        $scope.$parent.tabulas[$scope.tabula] = $scope.dataset;
                                        $scope.waitTabula = false;
                                    }
                                }
                            }
                        } else {
                            $window.location = "../comunes/salida.php";
                        }
                    }
                });
            };
            this.getDataset = function () {
                if (angular.isDefined($scope.dataset)) {
                    return $scope.dataset;
                }
            };
            this.getNombre = function () {
                return $scope.tabula;
            };
            this.opera = function (campo, op) {
                var total = 0;
                var minimo = false;
                var maximo = false;
                if (angular.isDefined($scope.dataset) && angular.isDefined($scope.dataset.filas)) {
                    angular.forEach($scope.dataset.filas, function (fila) {
                        switch (op) {
                            case "suma":
                            case "promedio":
                                total += parseFloat(fila[campo]);
                                break;
                            case "cuenta":
                                total++;
                                break;
                            case "min":
                                if (minimo == false) {
                                    total = fila[campo];
                                    minimo = true;
                                } else {
                                    total = Math.min(total, fila[campo]);
                                }
                                break;
                            case "max":
                                if (maximo == false) {
                                    total = fila[campo];
                                    maximo = true;
                                } else {
                                    total = Math.max(total, fila[campo]);
                                }
                                break;
                            default:
                                total = "<lang>Operación no definida</lang>";
                                break;
                        }
                    });
                    if (op == "promedio") {
                        total = total / $scope.dataset.filas.length;
                    }
                }
                return total;
            };
            this.isRemote = function () {  //informa si esta tabula es local o remota
                return !$scope.local;
            };
            this.esIlimitada = function () {  //informa si esta tabula es ilimitada
                return $scope.ilimitada;
            };
            this.dameUltimaPagina = function () { //devuleve el valor de la última página encontrada en el dataset o -1 si no sabe
                return $scope.ultimaPagina;
            };
            this.exportaTabula = function () { //ejecuta la exportación a CSV
                $scope.dataset.exporta = true;
                $scope.cargaTabula();
            };
            this.ejecutarTabula = function (accion) { //ejecuta la accion
                $scope.dataset.ejecuta = accion;
                $scope.cargaTabula();
            };
            this.filtraTabula = function (filtrapor, valor, valor2, tipo) {
                //filtra los datos según los criterios recibidos
                //Input: filtrapor es el nombre del campo
                //       valor es el valor a filtrar
                //       valor2 se utiliza cuando es un filtro de rango de fechas o de rango numérico
                //       tipo indica si es búsqueda libre (""), por rango de fechas ("timestamp") o por rango numérico ("range")
                // SEGURIDAD: nunca usar __proto__/constructor/prototype como nombre de campo dinámico
                if (!objectKeySeguro(filtrapor)) {
                    return;
                }
                $scope.ultimaPagina = -1;
                if (valor + valor2 != "") {
                    this.primeraBusqueda[filtrapor] = true;
                }
                //busca el campo filtrapor en el objeto filtro
                var encontre = false;
                for (var ii = 0; ii < $scope.dataset.filtro.length; ii++) {
                    if ($scope.dataset.filtro[ii].campo == filtrapor) { //si ya estaba definido actualiza los valores
                        $scope.dataset.filtro[ii].filtro = valor;
                        if (angular.isDefined(valor2)) {
                            $scope.dataset.filtro[ii].filtro2 = valor2;
                        }
                        if (tipo != "") {
                            $scope.dataset.filtro[ii].tipo = tipo;
                        }
                        encontre = true;
                    }
                }
                if (!encontre) {  //añade el filtro al objeto filtro si aún no estaba definido
                    var obj = {"campo": filtrapor, "filtro": valor};
                    if (angular.isDefined(valor2)) {
                        obj.filtro2 = valor2;
                    }
                    if (tipo != "") {
                        obj.tipo = tipo;
                    }
                    $scope.dataset.filtro.push(obj);
                }
                if ($scope.local) {  //si se trata de una tabula local, hace el filtrado directamente sobre la copia original del dataset
                    $scope.dataset.filas = $scope.dataset.filasBK;
                    angular.forEach($scope.dataset.filtro, function (val, key) {
                        switch (val.tipo) {
                            case "timestamp":
                                if (val.filtro != "" || val.filtro2 != "") {
                                    var objeto = {}
                                    objeto.campo = val.campo;
                                    objeto.desde = val.filtro;
                                    objeto.hasta = val.filtro2;
                                    $scope.dataset.filas = $filter('timestamprange')($scope.dataset.filas, objeto);
                                }
                                break;
                            case "range":
                                if (val.filtro != "" || val.filtro2 != "") {
                                    var objeto = {}
                                    objeto.campo = val.campo;
                                    objeto.desde = val.filtro;
                                    objeto.hasta = val.filtro2;
                                    $scope.dataset.filas = $filter('numberrange')($scope.dataset.filas, objeto);
                                }
                                break;
                            default:
                                // SEGURIDAD: no construir obj[val.campo]=... si val.campo es una
                                // clave peligrosa (__proto__/constructor/prototype)
                                if (val.filtro != "" && objectKeySeguro(val.campo)) {
                                    var objeto = {}
                                    objeto[val.campo] = val.filtro;
                                    $scope.dataset.filas = $filter('tildes')($scope.dataset.filas, objeto);
                                }
                                break;
                        }
                    });
                    if (angular.isDefined($scope.dataset.filas)) {
                        //actualiza el número de filas del dataset
                        $scope.dataset.cuantos = $scope.dataset.filas.length;
                    }
                } else {
                    //si es una tabula remota, carga los datos desde el servidor, siempre que haya algo que filtrar
                    if (valor + valor2 != "" || this.primeraBusqueda[filtrapor]) {
                        $scope.dataset.pagina = 1;
                        $scope.cargaTabula();
                    }
                }
            };
            this.ordenaTabula = function (ordenapor, reversa) {
                //ordena la tabula por el campo ordenapor, ascendente o descendentemente según el valor de reversa
                $scope.ultimaPagina = -1;
                if ($scope.local) {
                    $scope.cuantasCargas = 0;
                }
                //si es una tabula remota recarga los datos con el nuevo ordenamiento
                $scope.dataset.pagina = 1;
                $scope.dataset["ordenapor"] = ordenapor;
                $scope.dataset.reversa = reversa;
                $scope.cargaTabula();
            };
            this.paginaTabula = function (pagina, tamanio, desde) {
                //carga otra página de datos desde el servidor
                //Input: número de página a cargar, número de registros por página, empezar en registro #desde (opcional)
                $scope.ultimaPagina = -1;
                if (desde > 0) {
                    pagina = (parseInt(desde) + parseInt(tamanio)) / tamanio;
                }
                $scope.dataset.pagina = pagina;
                $scope.dataset.tamanio = tamanio;
                $scope.cargaTabula();
            };
        },
        template: function (element, attrs) {
            if (angular.isDefined(attrs.moderna)) {
                return '<div id="{{fixedId}}" ng-transclude></div>';
            } else {
                return '<table id="{{fixedId}}" class="table SUBtableModulos tb table-striped" border="1" ng-transclude></table>';
            }
        },
        link: function (scope, element, attrs) {
            if (angular.isDefined(attrs.waitTabula)) {
                scope.waitTabula = false;
            }
            //inicializa valores de la tabula si no existen ya
            if (angular.isUndefined(scope.dataset)) {
                scope.dataset = {};
                scope.ilimitada = false;
                if (angular.isDefined(attrs.ilimitada)) {
                    scope.ilimitada = true;
                }
                scope.exigeFiltro = false;
                if (angular.isDefined(attrs.exigeFiltro)) {
                    scope.exigeFiltro = true;
                }
                scope.estatica = false;
                if (angular.isDefined(attrs.estatica)) {
                    scope.estatica = true;
                }
                scope.local = false;
                if (angular.isDefined(attrs.local)) {
                    scope.local = true;
                }
                scope.hayPostCarga = false;
                if (angular.isDefined(attrs.postCarga)) {
                    scope.hayPostCarga = true;
                }
                scope.hayPostEjecuta = false;
                if (angular.isDefined(attrs.postEjecuta)) {
                    scope.hayPostEjecuta = true;
                }
                if (angular.isDefined(attrs.autoejecuta)) {
                    if (angular.isDefined(scope.autoejecuta.observaYRecarga)
                            && angular.isDefined(scope.autoejecuta.accion)
                            && angular.isDefined(scope.autoejecuta.parametros)) {
                        scope.$watch('autoejecuta.observaYRecarga', function (nuevoValor) {
                            if (nuevoValor > 0) {
                                scope.dataset.ejecuta = scope.autoejecuta.accion;
                                scope.dataset.ejecutaParametros = scope.autoejecuta.parametros;
                                scope.dataset.pagina = 1;
                                scope.cargaTabula(); //autoejecute una accion cada vez que cambie el valor de control
                            }
                        });
                    }
                }

                scope.cuantasCargas = 0;
                scope.dataset.filtro = new Array();
                scope.dataset.pagina = 1;
                if (angular.isDefined(attrs.paginaInicial)) {  //vino una pagina inicial desde la vista?
                    scope.dataset.pagina = parseInt(attrs.paginaInicial);
                }
                if (scope.dataset.pagina <= 0)
                    scope.dataset.pagina = 1;

                if (scope.local) {
                    scope.dataset.tamanio = 1000; //si es una tabula a manejar localmente, traiga muchos registros
                } else {
                    scope.dataset.tamanio = 5; //si es una tabula remota traiga pocos registros a pantalla
                    if (angular.isDefined(attrs.tamanioInicial)) {  //vino una pagina inicial desde la vista?
                        scope.dataset.tamanio = parseInt(attrs.tamanioInicial);
                    }
                    if (scope.dataset.tamanioInicial <= 0)
                        scope.dataset.tamanio = 5;
                }
                scope.dataset.reversa = false;
                scope.dataset.exporta = false;
                scope.cargaTabula();  //cargue los primeros datos
                if (!angular.isDefined(attrs.observaYRecarga)) {

                } else {
                    //observe cualquier cambio en el valor de observaYRecarga
                    scope.$watch('observaYRecarga', function (nuevoValor) {
                        if (scope.cuantasCargas && nuevoValor) {
                            scope.dataset.pagina = 1;
                            scope.cargaTabula(); //cargue datos cada vez que cambie la variable observaYRecarga
                        }
                    });
                }

                if (!angular.isDefined(attrs.detalleId)) {

                } else {
                    //observe cualquier cambio en el valor del id de detalle
                    scope.$watch('detalleId', function (nuevoValor) {
                        if (nuevoValor) {
                            scope.cargaDetalle(scope.detalleIdentificador, nuevoValor); //cargue datos cada vez que cambie la variable observaYRecarga
                        }
                    });
                }
            }
        }
    };
});


//uso:
//<th tabula-encabezado="nombre_campo" titulo="<lang>Nombre del Campo</lang>" ordenar filtrar tipo="timestamp"></th>
//para indicar un encabezado para la columna de datos nombre_campo, que se podrá ordenar y filtrar y que aparecerá como 'Nombre del Campo'
//opcionalmente, el atributo tabula-encabezado puede recibir una lista de campos separados por un espacio, en cuyo caso, el filtro buscará el valor en cualquiera de dichos campos y ordenará por todos esos campos en la misma secuencia que se ingresen
//si en el lado del servidor se utiliza coTabulaMongo, el nombre del campo puede ser multidimensional, por ejemplo sa_persona.nombres
//el atributo titulo es opcional
//el atributo ordenar es opcional
//el atributo filtrar es opcional
//el atributo tipo es opcional.  Si está ausente, el filtro es de texto libre (mysql) o de expresion regular (mongo); si es timestamp, se asume que la columna de datos trae fechas en formato UnixTimestamp; si es range, se asume que la columna contiene valores numéricos
tabulaModulo.directive('tabulaEncabezado', function ($compile, $debounce, $timeout) {
    return {
        restrict: "A", //solo puede usarse como atributo
        require: "^tabula", //debe estar dentro del HTML de una directiva tabula
        scope: {
            campo: "@tabulaEncabezado", //recibe el nombre del campo y lo asigna a scope.campo
            titulo: "@", //recibe el título de columna a mostra
            tipo: "@" //recibe el tipo de campo y el filtrado a utilizar
        },
        controller: function ($scope) {
            // SEGURIDAD: "ordena" y "reversa" ya NO se fijan mediante una expresión
            // Angular construida por concatenación de strings (ver directiva más
            // abajo); en su lugar viven aquí, en el controlador, y se modifican
            // solo desde funciones de scope reales. Esto elimina por completo la
            // posibilidad de inyección de expresiones Angular a través de
            // "scope.campo".
            $scope.ordena = null;
            $scope.reversa = false;

            $scope.lanzaEnfoque = function () {  //activa el campo de los filtros al abrirlos
                $timeout(function () {
                    $scope.enfocarme = true
                }, 100);
            };
            $scope.reordena = function () { //reordena la tabula a la que pertenece este encabezado
                $scope.ordena = $scope.campo;
                $scope.reversa = !$scope.reversa;
                $scope.parentC.ordenaTabula($scope.ordena, $scope.reversa);
            };
            // SEGURIDAD: reemplaza el objeto de ng-class que antes se construía
            // como string con "scope.campo" interpolado. Ahora es una función
            // normal de scope, sin construir expresiones dinámicamente.
            $scope.claseOrdenamiento = function () {
                return {
                    'sortable': true,
                    'sort-asc': ($scope.ordena === $scope.campo && !$scope.reversa),
                    'sort-desc': ($scope.ordena === $scope.campo && $scope.reversa)
                };
            };
            $scope.lanzaFiltro = function () { //filtra la tabula a la que pertenece este encabezado
                $scope.parentC.filtraTabula($scope.campo, $scope.ftemp, $scope.ftemp2, $scope.tipo);
            };
            $scope.cancelaBusqueda = function () {  //vacía los filtros
                $scope.ftemp = "";
                $scope.ftemp2 = "";
                $scope.fshow = false;
            };
        },
        link: function (scope, element, attrs, tabulaCtrl) {
            scope.columna = attrs.nombreColumna || "";
            scope.parentC = tabulaCtrl; //carga el controlador de la directiva padre
            //inicializa los datos
            scope.ftemp = "";
            scope.ftemp2 = "";
            //alinea verticalmente hacia arriba
            element.attr("valign", "top");
            if (scope.campo) {
                // SEGURIDAD: el título puede provenir de un atributo configurable;
                // se escapa antes de insertarse como HTML para prevenir XSS.
                var tituloSeguro = escapeHtml(scope.titulo ? scope.titulo : scope.campo);
                //nos enviaron un nombre de campo? entonces podemos ordenar y de ser el caso filtrar por este campo
                if (angular.isUndefined(attrs.filtrar)) {
                    element.append("<div><div>" + tituloSeguro + "</div></div>");
                } else {
                    element.append("<div><div><nobr>" + tituloSeguro + "&nbsp;&nbsp;<i class='bi bi-funnel' ng-click='fshow=!fshow;lanzaEnfoque();$event.stopPropagation();'></i></nobr></div></div>");
                }
                if (!angular.isUndefined(attrs.ordenar)) {
                    //añada los css que controlan la presentación de las columnas de orden y la respuesta a los clicks del usuario
                    element.children().addClass("sortable");
                    // SEGURIDAD: antes se construía aquí un string de expresión
                    // Angular concatenando "scope.campo" directamente
                    // (p.ej. "ordena='"+scope.campo+"';..."), lo que permitía
                    // inyectar una expresión Angular arbitraria si "campo"
                    // contuviera comillas u otros caracteres especiales.
                    // Ahora las expresiones son estáticas y llaman a
                    // funciones definidas en el controlador, que a su vez
                    // usan scope.campo como VALOR (no como código).
                    element.children().attr("ng-click", "reordena()");
                    element.children().attr("ng-class", "claseOrdenamiento()");
                }
                if (!angular.isUndefined(attrs.filtrar)) {
                    //tipo de filtro
                    switch (scope.tipo) {
                        case "range":
                            //escuchemos cualquier cambio en el valor de los filtros temporales
                            scope.$watch('ftemp', $debounce(scope.lanzaFiltro, 1000));
                            scope.$watch('ftemp2', $debounce(scope.lanzaFiltro, 1000));
                            //dibujemos el campo de filtro
                            element.append("<div ng-show='fshow'><input class='form-control' type='text' ng-model='ftemp' placeholder='<lang>Desde</lang>'><input class='form-control' type='text' ng-model='ftemp2' placeholder='<lang>Hasta</lang>'><i ng-click='cancelaBusqueda()' class='bi bi-x-circle fa-red' tooltip='<lang>Cancelar búsqueda</lang>'></i></div>");
                            break;
                        case "timestamp":
                            //escuchemos cualquier cambio en el valor de los filtros temporales
                            scope.$watch('ftemp', $debounce(scope.lanzaFiltro, 1000));
                            scope.$watch('ftemp2', $debounce(scope.lanzaFiltro, 1000));
                            //dibujemos el campo de filtro
                            element.append("<div ng-show='fshow'><input type='text' class='form-control' datepicker-popup='yyyy-MM-dd' ng-model='ftemp' placeholder='<lang>Desde</lang>' is-open='abredesde' ng-click='abredesde=!abredesde'/><input type='text' class='form-control' datepicker-popup='yyyy-MM-dd' ng-model='ftemp2' placeholder='<lang>Hasta</lang>' is-open='abrehasta' ng-click='abrehasta=!abrehasta'/><i ng-click='cancelaBusqueda()' class='bi bi-x-circle fa-red' tooltip='<lang>Cancelar búsqueda</lang>'></i></div>");
                            break;
                        default:
                            //escuchemos cualquier cambio en el valor de los filtros temporales
                            scope.$watch('ftemp', $debounce(scope.lanzaFiltro, 1000));
                            //dibujemos el campo de filtro
                            // SEGURIDAD: scope.titulo escapado (tituloSeguro) para evitar
                            // que un valor con comillas rompa el atributo "placeholder" e
                            // inyecte HTML/atributos adicionales.
                            element.append("<div ng-show='fshow' style='display:inline-flex'><input class='form-control' type='text' ng-model='ftemp' placeholder='<lang>Buscar por</lang> " + tituloSeguro + "' my-focus='enfocarme'><i ng-show='ftemp.length' ng-click='cancelaBusqueda()' tooltip-placement='bottom' tooltip='<lang>Cancelar búsqueda</lang>' class='fa fa-times-circle-o fa-red fa-fw' style='padding-top:8px'></i></div>");
                            break;
                    }
                }
            }
            $compile(element.contents())(scope);
        }
    };
});

tabulaModulo.directive('tabulaColumna', function () {
    return {
        restrict: 'A',
        require: "^tabula",
        link: function (scope, element, attrs, tabulaCtrl) {
            scope.columna = attrs.nombreColumna || "";
        }
    }
});

//////uso:
//<th tabula-pie="nombreDelCampo" total="suma cuenta" decimales="3">texto a presentar antes de totales</th>
//El atributo tabula-pie es una referencia al campo a totalizar
//El atributo total es un string de operaciones separadas por espacios, las operaciones definidas son suma promedio cuenta min max
//El atributo decimales es opcional y determina con cuántos decimales se presentan los totales (por defecto 2)
//
//La directiva permite incluir valores dentro del elemento que se presentarán ANTES de los totales
tabulaModulo.directive('tabulaPie', function () {
    // SEGURIDAD: lista blanca de operaciones válidas. El nombre de la
    // operación se usa para construir texto de plantilla; restringirlo a
    // valores conocidos evita que un atributo "total" con contenido
    // inesperado inyecte marcado HTML/expresiones adicionales.
    var OPERACIONES_VALIDAS = ['suma', 'promedio', 'cuenta', 'min', 'max'];

    var directiveObj = {
        restrict: 'A',
        require: "^tabula", //debe estar dentro del HTML de una directiva tabula
        transclude: true,
        controller: function ($scope) {
            $scope.totales = {}
            $scope.formatNumber = function (numero, decimales) {
                if (angular.isDefined(decimales) && angular.isDefined(numero) && numero !== '' && decimales !== '') {
                    let val = (numero / 1).toFixed(decimales).replace('.', ',');
                    return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                } else {
                    return numero;
                }
            };
            $scope.opera = function (campo, operacion) {
                // SEGURIDAD: nunca usar __proto__/constructor/prototype como clave
                if (!objectKeySeguro(campo)) {
                    return;
                }
                if (angular.isUndefined($scope.totales[campo])) {
                    $scope.totales[campo] = {};
                }
                var resultado = $scope.parentC.opera(campo, operacion);
                if (!isNaN(parseFloat(resultado)) && isFinite(resultado) && typeof (resultado.toFixed) == "function") {
                    $scope.totales[campo][operacion] = resultado.toFixed($scope.decimales);
                } else {
                    $scope.totales[campo][operacion] = resultado;
                }
                if ($scope.formatoNumero) {
                    $scope.totales[campo][operacion] = $scope.formatNumber($scope.totales[campo][operacion], $scope.decimales);
                }
            };
        },
        compile: function (element, attrs) {
            var operaciones = new Array();
            var campo = attrs.tabulaPie || "";
            // SEGURIDAD: "campo" y cada operación se concatenan dentro de una
            // expresión de interpolación Angular ("{{totales.campo.op}}") que
            // luego se inserta con element.html() y se compila. Si "campo" u
            // "operacion" contuvieran caracteres como "}}" seguidos de HTML,
            // ese HTML quedaría insertado en el DOM real ANTES de que Angular
            // termine de compilar, ejecutándose de inmediato (XSS). Por eso
            // se valida el nombre de campo contra un patrón estricto y las
            // operaciones contra una lista blanca antes de construir la
            // plantilla; si no son válidos, simplemente no se agregan totales.
            var campoValido = campo === "" || nombreCampoValido(campo);
            var strTemplate = "<div ng-transclude></div>";
            if (campo && campoValido) {
                var operacion = attrs.total || "";
                var operacionesCrudas = operacion.split(" ");
                angular.forEach(operacionesCrudas, function (op) {
                    if (OPERACIONES_VALIDAS.indexOf(op) !== -1) {
                        operaciones.push(op);
                    }
                });
                if (operaciones.length > 0) {
                    angular.forEach(operaciones, function (op) {
                        strTemplate += "<div> " + op.charAt(0).toUpperCase() + op.substr(1) + ": " + "{{totales." + campo + "." + op + "}} </div>";
                    });
                }
            }
            element.html(strTemplate);
            return {
                pre: function (scope, element, attrs, tabulaCtrl) {

                },
                post: function (scope, element, attrs, tabulaCtrl) {
                    scope.decimales = attrs.decimales || 2;

//                        scope.formatoNumero=attrs.formatoNumero;
                    scope.formatoNumero = true;
//                        console.log('formato',scope.formatoNumero);
                    scope.columna = attrs.nombreColumna || "";
                    scope.parentC = tabulaCtrl; //carga el controlador de la directiva padre
                    scope.tabula = scope.parentC.getNombre();
                    if (campo && campoValido && operaciones.length > 0) {
                        scope.$watch(
                                function (scope) {
                                    return scope.parentC.getDataset();
                                },
                                function (nuevovalor) {
                                    angular.forEach(operaciones, function (op) {
                                        scope.opera(campo, op);
                                    });
                                },
                                true
                                );
                    }
                }
            }
        }
    }
    return directiveObj;
});


//////uso:
//<tabula-paginador dataset="tabulas['xyz']" maxbotones="0" no-cambiar-tamanio no-mostrar-total tamanios="3,5,7"></tabula-paginador>
//El dataset es una referencia a los datos de la tabula padre
//El atributo maxbotones es opcional y controla el número de botones numéricos a mostrar en el paginador
//El atributo no-cambiar-tamanio es opcional y oculta el selector de número de registros
//El atributo no-mostrar-total es opcional y oculta el contador de registros 
//El atributo tamanios es opcional y permite definir las opciones que aparecerán en el selector de tamanios de página
tabulaModulo.directive('tabulaPaginador', function ($compile) {
    return {
        restrict: "E", //solo puede usarse como elemento
        require: "^tabula", //debe estar dentro del HTML de una directiva tabula
        scope: {
            maxbotones: "@", //recibe el número de botones a mostrar
            dataset: "=" //recibe como variable javascript el dataset a paginar
        },
        controller: function ($scope, $attrs) {
            $scope.seleccionador = function (pagina, tamanio, total) {
                //funcion que ejecuta la recarga de registros en la directiva padre
                if (!$scope.parentC.esIlimitada()) {
                    if ((pagina - 1) * tamanio >= total) {
                        pagina = parseInt(total / tamanio);
                    }
                }
                $scope.pagina = pagina;
                $scope.tamanio = tamanio;
                $scope.parentC.paginaTabula($scope.pagina, $scope.tamanio);
            }
        },
        link: function (scope, element, attrs, tabulaCtrl) {
            scope.parentC = tabulaCtrl;
            scope.noCambiarTamanio = false;
            if (angular.isDefined(attrs.noCambiarTamanio)) {
                scope.noCambiarTamanio = true;
            }
            scope.noMostrarTotal = false;
            if (angular.isDefined(attrs.noMostrarTotal)) {
                scope.noMostrarTotal = true;
            }

            scope.tamanios = [5, 10, 20, 50];
            if (angular.isDefined(attrs.tamanios)) {
                scope.tamanios = attrs.tamanios.split(",").map(function (val) {
                    return enteroSeguro(val);
                });
            }
            scope.redibujaTbPaginador = function () {
                //funcion que dibuja el paginador
                if (!scope.parentC.isRemote()) {
                    //no dibuje el paginador en tabulas locales
                    return;
                }
                var tamanio = parseInt(scope.dataset.tamanio);
                scope.tamanio = tamanio;
                var pagina = parseInt(scope.dataset.pagina);
                scope.pagina = pagina;
                if (scope.maxbotones == null) {
                    scope.maxbotones = 5;
                }
                var maxbotones = parseInt(scope.maxbotones);
                if (maxbotones < 0) {
                    maxbotones = 5;
                }
                if (!scope.parentC.esIlimitada()) {
                    var totalPaginas = parseInt(scope.cuantos / tamanio);
                    if (totalPaginas != scope.cuantos / tamanio)
                        totalPaginas++;
                } else {
                    if (scope.parentC.dameUltimaPagina() > -1) {
                        totalPaginas = scope.parentC.dameUltimaPagina();
                    } else {
                        scope.cuantos = -1;
                        totalPaginas = pagina + maxbotones * 2;
                    }
                }
                if (totalPaginas < maxbotones) {
                    maxbotones = totalPaginas;
                }

                var minBoton = pagina - parseInt(maxbotones / 2);
                if (minBoton < 1)
                    minBoton = 1;
                var botones = new Array();

                //botones de rewind
                if (pagina <= 1) {
                    var est = "disabled";
                } else {
                    var est = "";
                }
                botones.push({"pg": 1, "tx": "<i class='fa fa-angle-double-left'></i>", "estilo": est});
                botones.push({"pg": pagina - 1, "tx": "<i class='fa fa-angle-left'></i>", "estilo": est});

                //boton de paginas previas
                if (minBoton > 1 && maxbotones > 1) {
                    var prepagina = minBoton - parseInt(maxbotones / 2);
                    if (prepagina < 1)
                        prepagina = 1;
                    botones.push({"pg": prepagina, "tx": "...", "estilo": ""});
                }

                var maxBoton = 0;
                if (maxbotones > 0) {
                    //botones de numeros de pagina
                    for (var ii = 0; ii < maxbotones; ii++) {
                        if (minBoton + ii <= totalPaginas) {
                            maxBoton = minBoton + ii;
                            if (pagina == maxBoton) {
                                var est = "active";
                            } else {
                                var est = "";
                            }
                            botones.push({"pg": maxBoton, "tx": maxBoton, "estilo": est});
                        }
                    }
                }

                //boton de paginas posteriores
                if (maxBoton < totalPaginas && maxbotones > 1) {
                    var postpagina = maxBoton + parseInt(maxbotones / 2);
                    if (postpagina > totalPaginas)
                        postpagina = totalPaginas;
                    botones.push({"pg": postpagina, "tx": "...", "estilo": ""});
                }
                //botones de forward
                if (pagina >= totalPaginas) {
                    var est = "disabled";
                } else {
                    var est = "";
                }
                botones.push({"pg": pagina + 1, "tx": "<i class='fa fa-angle-right'></i>", "estilo": est});
                if (!scope.parentC.esIlimitada()) {
                    botones.push({"pg": totalPaginas, "tx": "<i class='fa fa-angle-double-right'></i>", "estilo": est});
                }

                //dibujemos el div	
                var texto = "<ul class='pagination pagination-sm'>";
                if (!scope.parentC.esIlimitada() && !scope.noMostrarTotal) {
                    // SEGURIDAD: scope.cuantos siempre debe ser numérico aquí, pero se
                    // fuerza explícitamente con enteroSeguro por defensa en
                    // profundidad, ya que su valor se inserta luego en varios
                    // atributos ng-click construidos como texto.
                    texto += "<li><a><lang>Total</lang> " + enteroSeguro(scope.cuantos) + "</a></li>";
                }
                angular.forEach(botones, function (val, key) {
                    // SEGURIDAD: "pg" y "cuantos" se fuerzan a enteros seguros antes de
                    // insertarse en el string de la expresión ng-click. Así, aunque el
                    // valor de origen (respuesta del servidor, atributos, etc.) no sea
                    // numérico, nunca se puede inyectar código dentro de la expresión
                    // Angular resultante.
                    var pgSeguro = enteroSeguro(val.pg);
                    var cuantosSeguro = enteroSeguro(scope.cuantos, -1);
                    if (val.estilo != "disabled" && val.estilo != "active") {
                        texto += "<li class='" + val.estilo + "'><a ng-click='seleccionador(" + pgSeguro + ",tamanio," + cuantosSeguro + ")'>" + val.tx + "</a></li>";
                    } else {
                        texto += "<li class='" + val.estilo + "'><a>" + val.tx + "</a></li>";
                    }
                });
                if (!scope.noCambiarTamanio) {
                    var cuantosSeguro = enteroSeguro(scope.cuantos, -1);
                    texto += "<li><select class='form-control tabula-paginador-ancho' ng-model='tamanio' ng-options='t for t in tamanios' ng-change='seleccionador(pagina,tamanio," + cuantosSeguro + ")'></select></li>";
                }
                texto += "</ul>";

                element.text("");
                element.append(texto);
                $compile(element.contents())(scope);
            }
            //observe cualquier cambio en el número de registros del dataset
            scope.$watch("dataset", function (nuevovalor) {
                if (nuevovalor) {
                    scope.cuantos = parseInt(nuevovalor.cuantos);
                    scope.redibujaTbPaginador();
                }
            }, true);
        }
    };
});

//////uso:
//<tabula-exportar dataset="tabulas['xyz']"></tabula-exportar>
//El dataset es una referencia a los datos de la tabula padre
tabulaModulo.directive('tabulaExportar', function ($compile) {
    return {
        restrict: "E", //solo puede utilizarse como elemento
        require: "^tabula", //debe estar dentro de una directiva tabula
        scope: {
            dataset: "=" //el dataset de la tabula padre
        },
        controller: function ($scope) {
            $scope.exportarCSV = function () {
                //llama a la función de exportar en el padre
                $scope.parentC.exportaTabula();
            };
        },
        link: function (scope, element, attrs, tabulaCtrl) {
            scope.parentC = tabulaCtrl; //recibe el controlador de la directiva padre
            scope.redibujaTbExportar = function () {
                //dibujemos el div
                if (scope.parentC.esIlimitada()) {
                    var tx = "<lang>todos los registros existentes</lang>";
                } else {
                    // SEGURIDAD: se fuerza a entero seguro antes de insertarlo en el HTML
                    var tx = enteroSeguro(scope.cuantos) + " <lang>registros</lang>";
                }
                var texto = "<button type='button' class='btn btn-light' ng-click='exportarCSV()' target='_blank' tooltip='<lang>Se exportarán a un archivo de texto</lang> " + tx + "'><lang>Exportar CSV</lang><i class='bi bi-filetype-csv'></i></button>";
                element.text("");
                element.append(texto);
                $compile(element.contents())(scope);
            };
            //observe cualquier cambio en el número de registros del dataset
            scope.$watch("dataset", function (nuevovalor) {
                if (nuevovalor) {
                    scope.cuantos = parseInt(nuevovalor.cuantos);
                    scope.redibujaTbExportar();
                }
            }, true);
        }
    };
});

//////uso:
//<tabula-ejecutar dataset="tabulas['xyz']" accion="nombre de la accion en el servidor" etiqueta="texto a mostrar"></tabula-ejecutar>
//El dataset es una referencia a los datos de la tabula padre
//Para configurar la accion en el servidor se utiliza la funcion ->setEjecutor($identificador,$func) del objeto TabulaAngular
tabulaModulo.directive('tabulaEjecutar', function ($compile) {
    return {
        restrict: "E", //solo puede utilizarse como elemento
        require: "^tabula", //debe estar dentro de una directiva tabula
        scope: {
            dataset: "=", //el dataset de la tabula padre
            accion: "@",
            etiqueta: "@"
        },
        controller: function ($scope) {
            $scope.ejecutar = function () {
                //llama a la función de exportar en el padre
                $scope.parentC.ejecutarTabula($scope.accion);
            };
        },
        link: function (scope, element, attrs, tabulaCtrl) {
            scope.parentC = tabulaCtrl; //recibe el controlador de la directiva padre
            // SEGURIDAD: la función original se asignaba a una variable SIN "var",
            // lo que creaba una variable global implícita (fuga al objeto window),
            // visible y sobrescribible por cualquier otro script de la página.
            // Ahora queda correctamente declarada dentro del closure del link.
            // También se escapa "etiqueta", ya que proviene de un atributo que
            // podría contener texto dinámico/traducciones no controladas.
            var redibujaTbEjecutar = function () {
                //dibujemos el div
                var etiquetaSegura = escapeHtml(scope.etiqueta);
                var texto = "<button type='button' class='btn btn-success' ng-click='ejecutar()'>" + etiquetaSegura + "</button>";
                element.text("");
                element.append(texto);
                $compile(element.contents())(scope);
            };
            redibujaTbEjecutar();
        }
    };
});
//_FIN_DE_ARCHIVO
