app.controller("cwDemoStreamChat", ['$document', '$window', '$scope', 'avisos', 'simple', '$sce', 'pedido', 'myIntercom', '$timeout', '$location', '$routeParams', '$http', function ($document, $window, $scope, avisos, simple, $sce, pedido, myIntercom, $timeout, $location, $routeParams, $http) {
        //informamos la ruta al encabezado
        myIntercom.publica('ruta', menuSuperior);

        $scope.nick = '';
        $scope.chatbot = {input: '', callToUsernameInput: ''};
        $scope.espera = {cedula: 0, fecNac: 0, confirmar: 0};
        $scope.id = '';
        $scope.antecedentes = '';
        $scope.mensajes = [];
        $scope.observarProductos = 1;
        $scope.observarEventos = 1;
        $scope.stopGeneralAudio = false;
        $scope.datosGenerales = {fecha: '', celular: '', identificacion: '', nombres: '', direccion: '', email: ''};
        $scope.buscandoCI = false;
        $scope.grabando = false;
        $scope.candidate = false;

        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/cwDemoStreamChatCtrl.php?act=getUsuario',
            data: {},
            cache: true
        }).then(function (response) {
            $scope.usuario = response.resp;
            name = response.resp;
            console.log($scope.usuario);
        });

        // Prompt for setting a username
        var username;
        var connected = false;
//        var $currentInput = $usernameInput.focus();
        var socket = io('https://' + document.domain + ':2029');

        $scope.loginPagina = true;
        // seteo la informacion del usuario
        $scope.setUsername = function () {
//            console.log('entre a setear parametros');
            username = $scope.nick;
            // If the username is valid
            if (username) {
                $scope.loginPagina = true;
//                $currentInput = $inputMessage.focus();
            }
        };
        function uuidv4() {
            return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            )
        }
        //añado los mensajes en el chat

        $scope.addChatMessage = function (data, options) {
//            angular.forEach(data.msg, function (value, key) {
//                if (value.tipo === 'evento') {
//                    if (value.contenido.accion === 'cedula') {
//                        $scope.cedula = true;
//                    }
//                    if (value.contenido.accion === 'prediccion') {
//                        $scope.prediccion = true;
//                    }
//                    if (value.contenido.accion === 'recomienda') {
//                        $scope.recomienda = true;
//                    }
//                }
//            });


            // reproduce en el navegador el texto recibido del chatbot

            if (data.origen === 'socket' && data.msg[0].tipo === 'texto') {
                if ($scope.grabando === true) {
                    $scope.grabando = false;
                    recognition.stop();
                }

//                var synth = window.speechSynthesis;
//                var utterThis = new SpeechSynthesisUtterance(data.msg[0].contenido.texto);
//                utterThis.pitch = 0.5;
//                utterThis.rate = 0.9;
////                utterThis.lang = 'es-US';
//
//                synth.speak(utterThis);


//                utterThis.onend = function (event) {
//                    $timeout(function () {
//                        if ($scope.grabando === false) {
//                            if ($scope.stopGeneralAudio === false) {
////                                $scope.grabando = true;
////                                recognition.start();
//                            }
//                        }
//                    }, 200);
//                };
            }

            $scope.bloqueaInput = false;
            $scope.placeHolderInput = 'Pregunte aqui...';
            angular.forEach(data.msg, function (value, key) {
                if (value.tipo === 'boton') {
                    $scope.bloqueaInput = true;
                    $scope.placeHolderInput = 'Seleccione una opcion de los botones..';
                } else {
                    document.getElementById("idMensaje").focus();
                }
            });
            $scope.mensajes.push(data);
            console.log('scope.mensajes');
            console.log($scope.mensajes);
            $timeout(function () {
                $scope.scrollDown();
            }, 100);

        };

        //evento que envia al servidor de socket el mensaje de la accion del boton
        $scope.evenBoton = function (accion) {
//            console.log('accion ' + accion);
            if (accion === 'campaniasSI') {
                $scope.campanias = true;
                $scope.mensajeBot({keyCode: 13});
                return;
            }
            $scope.preparoMensaje(accion, $scope.id, $scope.nick);
        };

        $scope.mensajeBot = function (ev) {
            console.log('entro1');
            if (ev.keyCode === 13) {
                $scope.addChatMessage({
                    origen: 'local',
                    username: $scope.nick,
                    msg: [{tipo: 'texto', contenido: {texto: $scope.chatbot.input}}]
                });
                $scope.preparoMensaje($scope.chatbot.input, $scope.id, $scope.nick);
                $scope.chatbot.input = '';
            }
        };
        //funcion centralizada para preparacion y envio de mensaje por socket
        $scope.preparoMensaje = function (texto, id, nombre) {
            var men = {texto: texto, id: id, nombre: nombre};
            var jsonMensaje = JSON.stringify(men);
            var m = window.btoa(jsonMensaje);
            //envio el mensaje al socket del servidor
            socket.emit('streamIN', m);
        };

//        // eventos que recibe del servidor Socket
//        // retorno de ccion de login
        socket.on('login', function (data) {
            console.log(data);
            if ($scope.nick === data.username) {
                $scope.id = '';
                $scope.id = data.id;
                $scope.setUsername();
                var message = "";
            }
        });

        // modificado para levantar chatbot
        socket.on('loginChatbot', function (data) {
            if (Object.keys(data).length > 0) {
                $scope.loginPagina = true;
//                $scope.preparoMensaje("hola", $scope.id, $scope.nick);
//                $timeout(function () {
                $scope.$apply();
//                }, 3000);
                //envio el mensaje al socket del servidor
//                $scope.preparoMensaje();
            }
        });

        // Whenever the server emits 'new message', update the chat body
//        socket.on('streamIN', function (data) {
//            console.log(data);
//            if (Object.keys(data).length > 0) {
//                var objData = JSON.parse(data.message);
//                if (typeof objData.tipo !== 'undefined') {
//                    objData = [];
//                    objData = [JSON.parse(data.message)];
//                }
//                let obj2 = [];
//                obj2 = {origen: "socket", username: data.username, msg: objData};
//                $scope.addChatMessage(obj2);
//                $scope.$apply();
//            }
//        });
        socket.on('streamIN', function (data) {
            console.log('stream');
            console.log(data);
            let te = JSON.parse(data.message.message);
//            let te = JSON.parse(data.message.message);
            console.log(te)
            $scope.addChatMessage({
                origen: 'socket',
                username: 'Chatbot',
                msg: te
            });
            $timeout(function () {
                $scope.scrollDown();
            }, 100);
//            $scope.preparoMensaje(te.texto, $scope.id, $scope.nick);
        });
        $scope.inicioChatBot = function () {
            $scope.nick = uuidv4();
            socket.emit('add user', $scope.nick);
//            socket.emit('add chatbot', 'Chatbot');
        };
        $scope.inicioChatBot();
        //************************************** gridster


        $scope.scrollDown = function () {
            var objDiv = document.getElementById("chtbot");
            objDiv.scrollTop = objDiv.scrollHeight;
        };

        //-------------------------------------------------------------------
        //Speech To Text
        //-------------------------------------------------------------------

        //valido que esto este disponible para el navegador
        try {
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            var recognition = new SpeechRecognition();
            recognition.lang = 'es-US';
            recognition.continuous = true;
            //recognition.interimResults = true;

        } catch (e) {
            avisos.alerta("Este browser no soporta esta función");
        }

        $scope.cambiarEstadoGrabacion = function () {
            if ($scope.grabando) {
                $scope.pausarGrabacion();
            } else {
                $scope.iniciarGrabacion();
            }
        };

        /*-----------------------------
         Voice Recognition 
         ------------------------------*/

        // If false, the recording will stop after a few seconds of silence.
        // When true, the silence period is longer (about 15 seconds),
        // allowing us to keep recording even when the user pauses. 


        // This block is called every time the Speech APi captures a line. 
        recognition.onresult = function (event) {

            // event is a SpeechRecognitionEvent object.
            // It holds all the lines we have captured so far. 
            // We only need the current one.
            var current = event.resultIndex;
            console.log('current');
            console.log(current);
            // Get a transcript of what was said.
            var transcript = event.results[current][0].transcript;

            // Add the current transcript to the contents of our Note.
            // There is a weird bug on mobile, where everything is repeated twice.
            // There is no official solution so far so we have to handle an edge case.
            var mobileRepeatBug = (current == 1 && transcript == event.results[0][0].transcript);

            var textoReconocido = transcript;

//                $scope.addChatMessage({
//                    origen: 'local',
//                    username: $scope.nick,
//                    msg: [{tipo: 'texto', contenido: {texto: textoReconocido}}]
//                });
//                $scope.preparoMensaje(textoReconocido, $scope.id, $scope.nick);
            $scope.chatbot.input = textoReconocido;
            $scope.mensajeBot({keyCode: 13});

            //$scope.$apply();
//            }
        };

        recognition.onstart = function () {

//            console.log("Escuchando, hable al micrófono");
        };

        recognition.onspeechend = function () {

//            console.log("Se detecto una pausa larga, el reconocimiento de voz se apagó automáticamente");

        };

        recognition.onerror = function (event) {
            if (event.error == 'no-speech') {
//                console.log('No se detecto sonido. Intente otra vez.');
            }
            ;
        };

        /*-----------------------------
         App buttons and input 
         ------------------------------*/

        $scope.stopGAudio = function () {
            if ($scope.stopGeneralAudio) {
                $scope.stopGeneralAudio = false;
            } else {
                $scope.stopGeneralAudio = true;
                if ($scope.grabando) {
                    $scope.grabando = false;
                    recognition.stop();
                }
            }
        };
        $scope.iniciarGrabacion = function () {
            $scope.stopGeneralAudio = false;
            $scope.grabando = true;
            recognition.start();
        };

        $scope.pausarGrabacion = function () {
            $scope.grabando = false;
            recognition.stop();
        };


        /*-----------------------------
         Speech Synthesis 
         ------------------------------*/

        $scope.leerTexto = function () {
            var speech = new SpeechSynthesisUtterance();

            // Set the text and voice attributes.
            speech.text = $scope.grabacion;
            speech.volume = 3;
            speech.rate = 1;
            speech.pitch = 1;

            window.speechSynthesis.speak(speech);
        };
        $scope.grabacion = "";

    }]);


//_FIN_DE_ARCHIVO
