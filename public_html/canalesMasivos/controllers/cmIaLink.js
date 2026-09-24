app.controller("cmIaLink", ['$scope', 'avisos', 'simple', 'pedido', 'myIntercom', '$window', '$timeout', '$filter', function ($scope, avisos, simple, pedido, myIntercom, $window, $timeout, $filter) {

    myIntercom.publica('ruta', menuSuperior);
    $scope.cargando = false;
    $scope.pregunta = "";
    $scope.esperandoRespuesta = false;
    $scope.permiteCambiarModelo = true;
    $scope.placeholder = "Cómo puedo ayudarte hoy?";
    $scope.nombreLLM = "LLM";
    $scope.modelos = [
        { "id": "0", "nombre": "Seleccione un modelo" }
    ];
    $scope.modeloSeleccionado = "";
    $scope.temperaturas = [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1];
    $scope.ocultarInput = false;
    $scope.ocultarSidebar = true;
    $scope.misChats = [];
    $scope.cargandoChats = false;

    $scope.sesion = {
        temperatura: 0.8,
        modeloId: "",
        modelo: "",
        conversacion: [],
        id: ""
    };

    $scope.focoEnPregunta = () => {
        let txt = document.getElementById("textpregunta");
        txt.focus();
    };

    $scope.traerModelos = () => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmIaLinkCtrl.php?act=obtenerModelos',
            data: {}
        }).then(function (response) {
            if (response?.modelos !== undefined) {
                $scope.modelos = $scope.modelos.concat(response.modelos);
                $timeout(() => {
                    $scope.focoEnPregunta();
                    if ($scope.modelos.length > 1) {
                        $scope.modeloSeleccionado = $scope.modelos[1]["id"];
                        $scope.establecerNombreLlm();
                    }
                }, 500);
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
        });
    };

    $scope.establecerNombreLlm = () => {
        $scope.sesion.modeloId = $scope.modelos.find((el) => el.id === $scope.modeloSeleccionado)["id"];
        $scope.sesion.modelo = $scope.modelos.find((el) => el.id === $scope.modeloSeleccionado)["nombre"];
        $scope.nombreLLM = $scope.sesion.modelo;
    };

    $scope.enviarPregunta = () => {

        if ($scope.pregunta === '') {
            avisos.alerta("Error", "Escribe en lo que deseas que te ayude");
            return;
        }

        $scope.ocultarSidebar = true;
        let fecha = Math.floor((new Date().getTime()) / 1000);
        $scope.sesion.conversacion.push({ rol: "usuario", mensaje: $scope.pregunta, fecha: fecha })
        $scope.esperandoRespuesta = true;
        $scope.irUltimo();
        $scope.permiteCambiarModelo = false;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmIaLinkCtrl.php?act=enviarPregunta',
            data: {
                id: $scope.sesion.id,
                modelo: $scope.sesion.modeloId,
                temperatura: $scope.sesion.temperatura,
                pregunta: $scope.pregunta
            }
        }).then(function (response) {
            if (response?.respuesta !== undefined) {
                fecha = Math.floor((new Date().getTime()) / 1000);
                $scope.sesion.conversacion.push(response.respuesta);
                $scope.sesion.id = $scope.sesion.id === "" ? response.id : $scope.sesion.id;
                $scope.misChats.push(
                    {
                        "id": $scope.sesion.id,
                        "modelo": $scope.sesion.modeloId,
                        "primer_mensaje": $scope.pregunta,
                        "fecha": fecha,
                        "seleccionado": true
                    }
                );
                $scope.pregunta = "";
                $scope.placeholder = "Responder...";
                $scope.irUltimo();
            } else if (response?.error !== undefined) {
                avisos.alerta("Error", response.error);
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.esperandoRespuesta = false;
        });
    };

    $scope.irUltimo = () => {
        $timeout(() => {
            document.getElementById('footer').scrollIntoView({ behavior: 'smooth' });
        }, 300);
    };

    $scope.traerChats = () => {
        $scope.cargandoChats = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmIaLinkCtrl.php?act=traerChats',
            data: {}
        }).then(function (response) {
            if (response?.chats !== undefined) {
                $scope.misChats = response.chats;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor1");
            }
            $scope.cargandoChats = false;
        });
    };

    $scope.abrirChat = (chat) => {
        $scope.cargando = true;
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cmIaLinkCtrl.php?act=traerChat',
            data: {
                id: chat["id"]
            }
        }).then(function (response) {
            if (response?.chat !== undefined) {
                $scope.sesion = {
                    temperatura: response.chat["temperatura"],
                    modeloId: response.chat["id_agente"],
                    modelo: response.chat["id_agente"],
                    conversacion: response.chat["mensajes"],
                    id: response.chat["_id"]
                };
                $scope.modeloSeleccionado = response.chat["id_agente"];
                $scope.establecerNombreLlm();
                $scope.permiteCambiarModelo = false;
                $scope.placeholder = "Responder...";
                $scope.irUltimo();
                $scope.focoEnPregunta();
                $scope.misChats.forEach((el) => { el.seleccionado = false; });
                chat.seleccionado = true;
            } else {
                avisos.alerta("Atención", "Sin respuesta del servidor");
            }
            $scope.cargando = false;
            $scope.ocultarSidebar = true;
        });
    };

    $scope.nuevoChat = () => {
        $scope.sesion = {
            temperatura: 0.8,
            modeloId: "",
            modelo: "",
            conversacion: [],
            id: ""
        };
        $scope.placeholder = "Cómo puedo ayudarte hoy?";
        $scope.nombreLLM = "LLM";
        $scope.misChats.forEach((el) => { el.seleccionado = false; });
        $timeout(() => {
            $scope.focoEnPregunta();
            if ($scope.modelos.length > 1) {
                $scope.modeloSeleccionado = $scope.modelos[1]["id"];
                $scope.establecerNombreLlm();
            }
        }, 500);
        $scope.permiteCambiarModelo = true;
    };

    $scope.traerModelos();
    $scope.traerChats();

}]);

//_FIN_DE_ARCHIVO

