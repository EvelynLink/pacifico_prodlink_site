app.controller("cmConfiguracionLiveKit", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', '$parse', '$sce', function ($scope, avisos, simple, pedido, $timeout, myIntercom, $parse, $sce) {
    window.Z_cbPanel = $scope;
    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = true;
    $scope.configuracion = {};
    $scope.agentes = [];
    $scope.agenteSeleccionado = "";
    $scope.crearConfiguracionAgente = false;
    $scope.provsSTT = [];
    $scope.provsTTS = [];

    $scope.traerConfiguracion = () => {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=cargaConfiguracion',
            data: {}
        }).then((response) => {
            if (response !== undefined) {
                if (response?.configuracion !== undefined) {
                    $scope.configuracion = response.configuracion;
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.traerAgentes();
        });
    };

    $scope.traerAgentes = () => {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=cargaAgentes',
            data: {}
        }).then((response) => {
            if (response !== undefined) {
                if (response?.agentes !== undefined) {
                    $scope.agentes = response.agentes;
                }
                if (response?.stt !== undefined) {
                    $scope.provsSTT = response.stt;
                }
                if (response?.tts !== undefined) {
                    $scope.provsTTS = response.tts;
                }
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.guardarConfiguracionGeneral = () => {
        if ($scope.configuracion["general"]["segundos_pausa_entre_procesos"] === undefined || $scope.configuracion["general"]["segundos_pausa_entre_procesos"] === null || $scope.configuracion["general"]["segundos_pausa_entre_procesos"] == "" || $scope.configuracion["general"]["segundos_pausa_entre_procesos"] < 0) {
            avisos.alerta("Error", "Pausa entre vuelta de lanzamiento debe ser un valor mayor a cero");
            return;
        }
        if ($scope.configuracion["general"]["registros_procesar"] === undefined || $scope.configuracion["general"]["registros_procesar"] === null || $scope.configuracion["general"]["registros_procesar"] == "" || $scope.configuracion["general"]["registros_procesar"] < 0) {
            avisos.alerta("Error", "Llamadas a lanzar por vuelta debe ser un valor mayor a cero");
            return;
        }
        if ($scope.configuracion["general"]["registros_finalizado_procesar"] === undefined || $scope.configuracion["general"]["registros_finalizado_procesar"] === null || $scope.configuracion["general"]["registros_finalizado_procesar"] == "" || $scope.configuracion["general"]["registros_finalizado_procesar"] < 0) {
            avisos.alerta("Error", "Llamadas a analizar por vuelta debe ser un valor mayor a cero");
            return;
        }
        if ($scope.configuracion["general"]["segundos_pausa_entre_procesos_finalizado"] === undefined || $scope.configuracion["general"]["segundos_pausa_entre_procesos_finalizado"] === null || $scope.configuracion["general"]["segundos_pausa_entre_procesos_finalizado"] == "" || $scope.configuracion["general"]["segundos_pausa_entre_procesos_finalizado"] < 0) {
            avisos.alerta("Error", "Pausa entre vuelta de análisis debe ser un valor mayor a cero");
            return;
        }
        if ($scope.configuracion["general"]["temperatura_analisis"] === undefined || $scope.configuracion["general"]["temperatura_analisis"] === null || $scope.configuracion["general"]["temperatura_analisis"] == "" || $scope.configuracion["general"]["temperatura_analisis"] < 0 || $scope.configuracion["general"]["temperatura_analisis"] > 1) {
            avisos.alerta("Error", "Temperatura modelo análisis llamadas debe ser un valor entre 0 y 1");
            return;
        }
        if ($scope.configuracion["general"]["modelo_analisis"] == "" || $scope.configuracion["general"]["modelo_analisis"] === null || $scope.configuracion["general"]["modelo_analisis"] === undefined) {
            avisos.alerta("Error", "Modelo análisis llamadas no puede estar vacío");
            return;
        }

        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=actualizaConfiguracion',
            data: {
                configuracion: $scope.configuracion["general"],
                agente: "general"
            }
        }).then((response) => {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.guardarConfiguracionAgente = (agenteSeleccionado) => {
        if ($scope.configuracion[agenteSeleccionado]["tts_proveedor_nombre"] === undefined || $scope.configuracion[agenteSeleccionado]["tts_proveedor_nombre"] === null || $scope.configuracion[agenteSeleccionado]["tts_proveedor_nombre"] == "") {
            avisos.alerta("Error", "Nombre proveedor TTS no puede estar vacio");
            return;
        }
        if ($scope.configuracion[agenteSeleccionado]["tts_api_key"] === undefined || $scope.configuracion[agenteSeleccionado]["tts_api_key"] === null || $scope.configuracion[agenteSeleccionado]["tts_api_key"] == "") {
            avisos.alerta("Error", "TTS API key no puede estar vacio");
            return;
        }
        if ($scope.configuracion[agenteSeleccionado]["tts_voice_id"] === undefined || $scope.configuracion[agenteSeleccionado]["tts_voice_id"] === null || $scope.configuracion[agenteSeleccionado]["tts_voice_id"] == "") {
            avisos.alerta("Error", "Id voz TTS no puede estar vacio");
            return;
        }
        if ($scope.configuracion[agenteSeleccionado]["tts_model"] === undefined || $scope.configuracion[agenteSeleccionado]["tts_model"] === null || $scope.configuracion[agenteSeleccionado]["tts_model"] == "") {
            avisos.alerta("Error", "Modelo TTS no puede estar vacio");
            return;
        }

        if ($scope.configuracion[agenteSeleccionado]["stt_proveedor_nombre"] === undefined || $scope.configuracion[agenteSeleccionado]["stt_proveedor_nombre"] === null || $scope.configuracion[agenteSeleccionado]["stt_proveedor_nombre"] == "") {
            avisos.alerta("Error", "Nombre proveedor STT no puede estar vacio");
            return;
        }
        if ($scope.configuracion[agenteSeleccionado]["stt_api_key"] === undefined || $scope.configuracion[agenteSeleccionado]["stt_api_key"] === null || $scope.configuracion[agenteSeleccionado]["stt_api_key"] == "") {
            avisos.alerta("Error", "STT API key no puede estar vacio");
            return;
        }
        if ($scope.configuracion[agenteSeleccionado]["stt_model"] === undefined || $scope.configuracion[agenteSeleccionado]["stt_model"] === null || $scope.configuracion[agenteSeleccionado]["stt_model"] == "") {
            avisos.alerta("Error", "Modelo STT no puede estar vacio");
            return;
        }

        $scope.cargando = true;
        if ($scope.crearConfiguracionAgente) {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=crearConfiguracion',
                data: {
                    configuracion: $scope.configuracion[agenteSeleccionado],
                    agente: agenteSeleccionado
                }
            }).then((response) => {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
                $scope.traerConfiguracion();
            });
        } else {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=actualizaConfiguracion',
                data: {
                    configuracion: $scope.configuracion[agenteSeleccionado],
                    agente: agenteSeleccionado
                }
            }).then((response) => {
                if (response?.respuesta !== undefined) {
                    avisos.alerta("Atención", response.respuesta);
                } else {
                    avisos.alerta("Error", "No hubo respuesta del servidor");
                }
                $scope.traerConfiguracion();
            });
        }
    };

    $scope.traerConfiguracionAgente = (agenteSeleccionado) => {

        if ($scope.configuracion[agenteSeleccionado] === undefined) {
            $scope.configuracion[agenteSeleccionado] = {
                "tipo": "agente",
                "agente_id": agenteSeleccionado,
                "tts_api_key": "",
                "tts_voice_id": "",
                "tts_model": "",
                "tts_proveedor_nombre": "",
                "stt_api_key": "",
                "stt_model": "",
                "stt_proveedor_nombre": "",
                "usuario_modificacion": "N/D",
                "fecha_modificacion": 0
            };
            $scope.crearConfiguracionAgente = true;
        } else {
            $scope.crearConfiguracionAgente = false;
        }
        $scope.agenteSeleccionado = agenteSeleccionado;
    };

    $scope.cambiaEstadoAgente = (accion) => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmConfiguracionLiveKitCtrl.php?act=actualizaEstadoServicio',
            data: {
                accion: accion
            }
        }).then((response) => {
            if (response?.respuesta !== undefined) {
                avisos.alerta("Atención", response.respuesta);
            } else {
                avisos.alerta("Error", "No hubo respuesta del servidor");
            }
            $scope.traerConfiguracion();
        });
    };

    $scope.traerConfiguracion();

}]);
//_FIN_DE_ARCHIVO