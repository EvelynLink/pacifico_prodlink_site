app.controller("chatbots", ['$scope', 'avisos', 'simple', 'pedido', '$timeout', 'myIntercom', '$parse', '$sce', function ($scope, avisos, simple, pedido, $timeout, myIntercom, $parse, $sce) {
    window.Z_cbPanel = $scope;
    //informamos la ruta al encabezado
    myIntercom.publica('ruta', menuSuperior);
    $scope.write = true;
    $scope.tabu = 0;
    $scope.tabuVariable = 0;
    $scope.tab3n = 0;
    $scope.detM3 = 0;
    $scope.respondeEditResp = 0;
    $scope.nuevaCar = false;
    $scope.editResp = false;
    $scope.editRespData = {};
    $scope.verBtnCartera = true;
    $scope.verCheckGuardar = true;
    $scope.edita = true;
    $scope.verBtnVar = true;
    $scope.obligatorio = false;
    $scope.tdatocanal = [{ nombre: 'Facebook' }, { nombre: 'Twitter' }, { nombre: 'Chatbot' }];
    //$scope.tdatocartera = [{nombre: 'CNT'}, {nombre: 'BANCO EC'},{nombre:'CNT COORPORATIVO'}];
    $scope.tdatocartera = [];
    $scope.flex1 = 100;
    $scope.banderaFlex = false;
    $scope.sinonimo1 = false;
    $scope.visible = true;
    $scope.encabezado = true;
    $scope.tituloPregunta = false;
    $scope.listasinonimos = false;
    $scope.listarespuestas = false;
    $scope.waitWindows = false;
    $scope.visibleb = false;
    $scope.listaEntrenarChatbot = false;
    $scope.inputSinonimo = '';
    $scope.inputSinonimoIndex = '';
    $scope.searchSelected = '';

    $scope.input = { tipoRespuesta: 'texto', respuesta: '', respuestaAcc: '', tipoRespuestaEvento: 'cliente', sinonimo: '' };
    $scope.resultadoRespuesta = [];
    $scope.tipoRespuesta = 'texto';
    $scope.abreFechaStart = false;
    $scope.tabuReentrenar = 0;
    $scope.tabuListaPreguntas = 0;
    $scope.vistaEditSinonimo = false;
    $scope.tabuEntrenar = 0;
    $scope.tabuListaConstPreguntas = 0;
    $scope.resultadoRespuestaJson = [];
    $scope.resultadoRespuestaNota = [];
    $scope.editPregunta = false;
    $scope.laPregunta = { input: '', id: '' };
    $scope.inputGrdPreg = false;
    $scope.reentrenarChatbot = false;
    $scope.reentrenarChatBotData = [];
    $scope.verSinonimosdePregunta = false;
    $scope.sinonimosReentrenar = {};


    ////////////////////////////////chatbot//////////////////////////////

    $scope.guardaChatbot = function (item) {
        parametros = {
            url: '../canalesMasivos/chatbotsCtrl.php?act=guardaChatbot'
        };
        simple.guardaItem(item, parametros);

        $scope.deslistaConfigurarChatbot();
        $timeout(function () {
            $scope.tabuVariable++;
        }, 100);
    };

    $scope.borraChatbot = function (item, lista) {
        avisos.setFuncion(function () {
            parametros = {
                url: '../canalesMasivos/chatbotsCtrl.php?act=borraChatbot',
                confirma: true
            };
            simple.eliminaItem(item, lista, parametros);
        });
        avisos.confirma("<lang>Seguro desea eliminar el canal masivo?</lang>", null);
    };

    $scope.traducirEstadoVariable = function (filas) {
        angular.forEach(filas, function (item) {
            if (item.chatEstado == "1") {
                item.chatEstado = true;
            } else {
                item.chatEstado = false;
            }
        });
        return filas;
    };

    $scope.traerCarteras = function () {
        pedido.async({
            method: 'POST',
            url: '../canalesMasivos/chatbotsCtrl.php?act=generarLlamada',
            data: $scope.llamada
        }).then(function (response) {
            console.log(response);
        });
    };

    $scope.prueba = function () {

        var item = {
            "id": round(microtime(true) * 1000),

            "pregunta": $scope.chatbots.chatPreguntas.pregunta,
            "respuestas": [],
            "sinonimos": []
        };
        $scope.chatbots.chatPreguntas.push(item);
        console.log($scope.chatbots);
    };

    ////////////////////////////////guarda y borra chatbot//////////////////// 

    $scope.guardapreg = function () {
        if ($scope.inputGrdPreg == false && $scope.chatbots.chatPreguntas.pregunta !== "" && $scope.chatbots.chatPreguntas.pregunta !== undefined) {
            $scope.inputGrdPreg = true;
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=guardapreg',
                data: {
                    "chatbot": $scope.chatbots,
                    "pregunta": $scope.chatbots.chatPreguntas.pregunta
                }
            }).then(function (data) {
                $scope.chatbots.chatPreguntas = data.mensajePreg;
                $scope.inputGrdPreg = false;
                $timeout(function () {
                    $scope.tabuEntrenar++;
                }, 100);
            });
        } else {
            avisos.alerta("<lang>Advertencia</lang>", "<lang>Debe ingresar una pregunta</lang>");
        }
    };

    $scope.borrapreg = function (ch, index, id) {
        avisos.setFuncion(function () {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=borrapreg',
                data: {
                    "chatbotId": $scope.chatbots.id,
                    "preguntaId": ch.id
                }
            }).then(function (data) {
                //console.log($scope.chatbots.chatPreguntas = data["mensajePreg"].reverse());
                $scope.chatbots.chatPreguntas.splice(index, 1);
                $scope.chatbots.chatPreguntas;
                $scope.preguntaHide();
                $timeout(function () {
                    $scope.tabuEntrenar++;
                }, 100);
            });

        });

        avisos.confirma("<lang>Seguro desea eliminar la Pregunta?</lang>", null);

    };

    $scope.guardasinonim = function () {

        if ($scope.input.sinonimo !== "" && $scope.input.sinonimo !== undefined) {

            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=guardasinonim',
                data: {
                    "chatbot": $scope.chatbots.id,
                    "sinonimosId": $scope.ch.id,
                    "sinonimos": $scope.input.sinonimo
                }
            }).then(function (data) {
                $scope.input.sinonimo = '';
                if (data.mensajeSinonim === 1) {
                    $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                    angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                        if (v.id === $scope.ch.id) {
                            console.log("guarda sinonimo true");
                            //                            $scope.chatbots.chatPreguntas[i] = data.data;
                            $scope.ch = $scope.chatbots.chatPreguntas[i];
                        }
                    });
                    $timeout(function () {
                        $scope.tabuEntrenar++;
                    }, 100);
                    $timeout(function () {
                        $scope.tabuListaSinonimos++;
                    }, 200);
                } else {
                    avisos.alerta("<lang>Advertencia</lang>", "<lang>No se pudo guardar el Sinónimo</lang>");
                }
            });
        } else {
            avisos.alerta("<lang>Advertencia</lang>", "<lang>Debe ingresar un sinónimo</lang>");
        }
    };

    $scope.borrasinonim = function (ch, index, id) {
        avisos.setFuncion(function () {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=borrasinonim',
                data: {
                    "chatbotId": $scope.chatbots.id,
                    "sinonimosId": ch.id,
                    "sinonimosIndice": index
                }
            }).then(function (data) {
                $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                    if (v.id === $scope.ch.id) {
                        console.log("borrar sinonimo true");
                        //                            $scope.chatbots.chatPreguntas[i] = data.data;
                        $scope.ch = $scope.chatbots.chatPreguntas[i];
                    }
                });
                $timeout(function () {
                    $scope.tabuEntrenar++;
                }, 100);
                $timeout(function () {
                    $scope.tabuListaSinonimos++;
                }, 200);
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar el sinónimo?</lang>", null);
    };

    /////////////////////////////////items//////////////////////////////

    $scope.inicializaItemVar = function (item) {
        simple.inicializaItem(item);
    };

    $scope.reinicializaItem = function (item, lista) {
        $scope.verBtnVar = true;
        $scope.edita = false;
        simple.reinicializaItem(item, lista);
        ///
        $scope.deslistaConfigurarChatbot();
    };

    $scope.listaConfiguraciones = function (item) {
        $scope.listaConfigurarChatbot = true;
        $scope.tabu++;
        if (angular.isUndefined($scope.detM) || $scope.detM._id !== item._id) {
            $scope.tabulas['cf'] = { "cuantos": "0", "filas": [] };
            $scope.detM = item;
        }
    };

    ////////////////////////////Botones dentro de chatbot/////////////////// 
    $scope.reentrenarSinonimo = function (val) {
        $scope.waitWindows = true;
        return pedido.async({
            method: 'POST',
            url: '../canalesMasivos/chatbotsCtrl.php?act=getIDentrenarChatbot',
            data: {
                id: $scope.reentrenarChatBotData.id
            }
        }).then(function (data) {
            $scope.viewComoSinonimo = true;
            $scope.waitWindows = false;
            $scope.ComoSinonimo = val;
            $scope.reentrenarChatBotData = {};
            $scope.reentrenarChatBotData = data[0];
            $timeout(function () {
                $scope.tabuListaSinonimosReen++;
            }, 100);
        });
    };
    $scope.salirPreguntaSinonimo = function (val) {
        $scope.viewComoSinonimo = false;
    };
    $scope.reentrenarPregunta = function (val) {
        avisos.setFuncion(function () {
            $scope.waitWindows = true;
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=reentrenarPregunta',
                data: {
                    id: $scope.reentrenarChatBotData.id,
                    coleccion: $scope.reentrenarChatBotData.coleccionConversacion,
                    data: val
                }
            }).then(function (data) {
                $scope.waitWindows = false;
                $timeout(function () {
                    $scope.tabuReentrenar++;
                }, 100);
            });
        });
        avisos.confirma('Esta seguro de registrar como pregunta?');
    };
    $scope.borrarReentrenamiento = function (val) {
        avisos.setFuncion(function () {
            $scope.waitWindows = true;
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=borrarReentrenamiento',
                data: {
                    coleccion: $scope.reentrenarChatBotData.coleccionConversacion,
                    data: val
                }
            }).then(function (data) {
                $scope.waitWindows = false;
                $timeout(function () {
                    $scope.tabuReentrenar++;
                }, 100);
            });
        });
        avisos.confirma('Esta seguro de eliminar la pregunta?');
    };
    $scope.reentrenar = function (val) {
        console.log(val);
        $scope.reentrenarChatBotData = val;
        $timeout(function () {
            $scope.tabuReentrenar++;
            $scope.reentrenarChatbot = true;
        }, 100);
    };
    $scope.enviarEntrenar = function (val) {
        console.log(val);
        avisos.setFuncion(function () {
            $scope.waitWindows = true;
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=enviarEntrenar',
                data: {
                    id: val.id
                }
            }).then(function (data) {
                if (data.respuesta === 'error') {
                    avisos.alerta('Error al entrenar');
                    $scope.waitWindows = false;
                } else {
                    avisos.alerta('Entrenamiento realizado con exito');
                    $scope.waitWindows = false;
                }
            });
        });
        avisos.confirma('Esta seguro de ejecutar el entrenamiento?, este proceso puede tardar varios minutos...');
    };
    $scope.guardaresp = function () {
        console.log({
            data: {
                chatbot: $scope.chatbots.id,
                respuestasId: $scope.ch.id,
                respuestaJson: $scope.resultadoRespuestaJson
            }
        });
        if ($scope.resultadoRespuesta !== "" && $scope.resultadoRespuesta !== undefined) {
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=guardaresp',
                data: {
                    chatbot: $scope.chatbots.id,
                    respuestasId: $scope.ch.id,
                    respuestas: $scope.resultadoRespuesta,
                    respuestaJson: $scope.resultadoRespuestaJson
                }
            }).then(function (data) {
                if (data.mensajeResp === 1) {
                    $scope.resultadoRespuestaJson = [];
                    $scope.editResp = false;
                    //                        $scope.limpiarRespuesta();
                    $scope.input.respuesta = '';
                    $scope.input.respuestaAcc = '';
                    $scope.input.tipoRespuesta = 'texto';
                    $scope.resultadoRespuesta = [];
                    $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                    angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                        if (v.id === $scope.ch.id) {
                            $scope.ch = $scope.chatbots.chatPreguntas[i];
                            //                                for (var i = 0; i < $scope.ch.respuestas.length; i++) {
                            //                                    $scope.ch.respuestas[i] = $sce.trustAsHtml($scope.ch.respuestas[i]);
                            //                                }
                        }
                    });
                    $timeout(function () {
                        $scope.tabuVariable++;
                    }, 100);
                    $timeout(function () {
                        $scope.tabuListaPreguntas++;
                        $scope.tabuEntrenar++;
                    }, 200);
                } else {
                    avisos.alerta("<lang>Advertencia</lang>", "<lang>No se pudo guardar la Respuesta</lang>");
                }
            });
        } else {
            avisos.alerta("<lang>Advertencia</lang>", "<lang>Debe ingresar una respuesta</lang>");
        }
    };

    $scope.borraresp = function (ch, index, id) {
        avisos.setFuncion(function () {
            pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=borraresp',
                data: {
                    "chatbotId": $scope.chatbots.id,
                    "respuestasId": ch.id,
                    "respuestasIndice": index
                }
            }).then(function (data) {
                if (data.mensajeResp === 1) {
                    $scope.input.respuesta = '';
                    $scope.input.respuestaAcc = '';
                    $scope.input.tipoRespuesta = 'texto';
                    $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                    angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                        if (v.id === $scope.ch.id) {
                            $scope.ch = $scope.chatbots.chatPreguntas[i];
                            //                                for (var i = 0; i < $scope.ch.respuestas.length; i++) {
                            //                                    $scope.ch.respuestas[i] = $sce.trustAsHtml($scope.ch.respuestas[i]);
                            //                                }
                        }
                    });
                    //                        $timeout(function () {
                    //                            $scope.tabuVariable++;
                    //                        }, 100);
                    $timeout(function () {
                        $scope.tabuListaPreguntas++;
                        $scope.tabuEntrenar++;
                    }, 100);
                } else {

                    avisos.alerta("<lang>Advertencia</lang>", "<lang>No se pudo borrar la Respuesta</lang>");
                }
                //                    $scope.chatbots.chatPreguntas = data["mensajeResp"].reverse();
                //                    $scope.entrenarRespuestaHide();
            });
        });
        avisos.confirma("<lang>Seguro desea eliminar la respuesta?</lang>", null);
    };
    $scope.entrenarRespuesta = function (val) {
        $scope.input = { respuesta: '', respuestaAcc: '' };
        $scope.resultadoRespuesta = [];
        $scope.ch = JSON.parse(JSON.stringify(val));
        $scope.resultadoRespuestaJson = [];
        $scope.banderaFlex = true;
        $timeout(function () {
            $scope.tabuListaPreguntas++;
        }, 100);
    };

    $scope.entrenarRespuestaHide = function (id) {
        $scope.banderaFlex = false;
        $scope.editResp = false;
    };
    $scope.registrarSinonimoPregunta = function (val) {
        avisos.setFuncion(function () {
            $scope.waitWindows = true;
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=registrarSinonimoPregunta',
                data: {
                    id: $scope.reentrenarChatBotData.id,
                    sinonimo: $scope.ComoSinonimo['declaracion-user'],
                    pregunta: val,
                    coleccion: $scope.reentrenarChatBotData.coleccionConversacion
                }
            }).then(function (data) {
                $scope.waitWindows = false;
                $scope.viewComoSinonimo = false;
                $timeout(function () {
                    $scope.tabuReentrenar++;
                }, 100);
            });
        });
        avisos.confirma('Esta seguro de registrar la pregunta como sinonimo?');
    };
    $scope.viewSinonimoPreguntaSalir = function () {
        $scope.verSinonimosdePregunta = false;
    };
    $scope.viewSinonimoPregunta = function (res, val) {
        $scope.verSinonimosdePregunta = true;
        $scope.sinonimosReentrenar.data = val;
        $scope.sinonimosReentrenar.titulo = res;
        $timeout(function () {
            $scope.tabuListaSinonimosReen++;
        }, 100);
    };

    $scope.entrenarSinonimo = function (val) {
        $scope.ch = JSON.parse(JSON.stringify(val));
        $scope.sinonimo1 = true;
        $timeout(function () {
            $scope.tabuListaSinonimos++;
        }, 100);
    };

    $scope.postPregunta = function (filas) {
        $scope.chatbots.chatPreguntas = filas;
        return filas;
    };
    $scope.entrenarSinonimoHide = function (id) {
        $scope.sinonimo1 = false;
        $scope.vistaEditSinonimo = false;
    };

    $scope.agregarRespNotaEdit = function (item) {
        console.log('agregarRespNotaEdit');
        console.log(item);
        $scope.resultadoRespuestaNota = item;
    };
    $scope.eliminarRespuesta = function (index) {
        $scope.editRespData.splice(index, 1);
        $timeout(function () {
            $scope.respondeEditResp++;
        }, 100);
    };
    $scope.agregarRespNota = function (item) {
        $scope.resultadoRespuestaNota = item;
        $scope.agregarResp();
    };
    $scope.cerrarEditResp = function () {
        $scope.editResp = false;
    };
    $scope.editarPregunta = function (val, id) {
        $scope.editPregunta = true;
        $scope.laPregunta.input = val;
        $scope.laPregunta.id = id;
    };
    $scope.salirPregunta = function () {
        $scope.editPregunta = false;
    };
    $scope.actualizaPregunta = function () {
        console.log({ data: $scope.laPregunta });
        return pedido.async({
            method: 'POST',
            url: '../canalesMasivos/chatbotsCtrl.php?act=actpregunta',
            data: {
                chatbot: $scope.chatbots.id,
                data: $scope.laPregunta
            }
        }).then(function (data) {
            if (data.mensajeResp === 1) {
                $scope.salirPregunta();
                $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                    if (v.id === $scope.ch.id) {
                        $scope.ch = $scope.chatbots.chatPreguntas[i];
                    }
                });
                $timeout(function () {
                    $scope.tabuEntrenar++;
                }, 100);
            } else {
                avisos.alerta("<lang>Advertencia</lang>", "<lang>No se pudo guardar la Pregunta</lang>");
            }
        });
    };
    $scope.guardaEditResp = function () {
        console.log({
            data: {
                chatbot: $scope.chatbots.id,
                respuestasId: $scope.ch.id,
                respuestaJson: $scope.editRespData
            }
        });
        angular.forEach($scope.editRespData, function (v, i) {
            delete v['nuevo'];
        });
        if ($scope.resultadoRespuesta !== "" && $scope.resultadoRespuesta !== undefined) {
            return pedido.async({
                method: 'POST',
                url: '../canalesMasivos/chatbotsCtrl.php?act=actresp',
                data: {
                    chatbot: $scope.chatbots.id,
                    respuestasId: $scope.ch.id,
                    respuestaJson: $scope.editRespData,
                    item: $scope.editRespItem
                }
            }).then(function (data) {
                if (data.mensajeResp === 1) {
                    $scope.resultadoRespuestaJson = [];
                    $scope.editResp = false;
                    //                        $scope.limpiarRespuesta();
                    $scope.input.respuesta = '';
                    $scope.input.respuestaAcc = '';
                    $scope.input.tipoRespuesta = 'texto';
                    $scope.resultadoRespuesta = [];
                    $scope.chatbots = JSON.parse(JSON.stringify(data.data));
                    angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                        if (v.id === $scope.ch.id) {
                            $scope.ch = $scope.chatbots.chatPreguntas[i];
                        }
                    });
                    $timeout(function () {
                        $scope.tabuListaPreguntas++;
                        $scope.tabuEntrenar++;
                    }, 100);
                } else {
                    avisos.alerta("<lang>Advertencia</lang>", "<lang>No se pudo guardar la Respuesta</lang>");
                }
            });
        } else {
            avisos.alerta("<lang>Advertencia</lang>", "<lang>Debe ingresar una respuesta</lang>");
        }
    };
    $scope.addUpResp = function (ind) {
        if (ind === 0) {
            $scope.editRespData.unshift({ tipo: 'texto', contenido: {}, nuevo: 1 });
        } else {
            $scope.editRespData.splice(ind, 0, { tipo: 'texto', contenido: {}, nuevo: 1 });
            $timeout(function () {
                $scope.respondeEditResp++;
            }, 100);
        }
    };
    $scope.addDownResp = function (ind) {
        if (ind === ($scope.editRespData.length - 1)) {
            $scope.editRespData.push({ tipo: 'texto', contenido: {}, nuevo: 1 });
        } else {
            $scope.editRespData.splice(ind, 0, { tipo: 'texto', contenido: {}, nuevo: 1 });
            $timeout(function () {
                $scope.respondeEditResp++;
            }, 100);
        }
    };
    $scope.guardaEditSinonimo = function () {
        var a = JSON.parse(JSON.stringify($scope.ch.sinonimos));
        a.splice($scope.inputSinonimoIndex, 1);
        a.push($scope.input.sinonimo);
        a.sort();
        $scope.ch.sinonimos = a;
        return pedido.async({
            method: 'POST',
            url: '../canalesMasivos/chatbotsCtrl.php?act=actsinonimo',
            data: {
                chatbot: $scope.chatbots.id,
                sinonimos: $scope.ch.sinonimos,
                respuestasId: $scope.ch.id
            }
        }).then(function (data) {
            $scope.cancelEditSinonimo();
            $scope.chatbots = JSON.parse(JSON.stringify(data.data));
            angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
                if (v.id === $scope.ch.id) {
                    $scope.ch = $scope.chatbots.chatPreguntas[i];
                }
            });
            $timeout(function () {
                $scope.tabuListaSinonimos++;
                $scope.tabuEntrenar++;
            }, 100);
        });
    };
    $scope.editSinonimo = function (res, ind) {
        $scope.vistaEditSinonimo = true;
        $scope.input.sinonimo = res;
        $scope.inputSinonimoIndex = ind;
    };
    $scope.cancelEditSinonimo = function () {
        $scope.vistaEditSinonimo = false;
        $scope.input.sinonimo = '';
        $scope.inputSinonimoIndex = '';
    };
    $scope.editarResp = function (da, ind) {
        console.log('editarResp');
        console.log(da);
        $scope.editResp = true;
        $scope.editRespItem = ind;
        $scope.editRespData = JSON.parse(JSON.stringify(da));
        $timeout(function () {
            $scope.respondeEditResp++;
        }, 100);
    };
    $scope.agregarResp = function () {
        console.log('agregarResp');
        console.log($scope.input);
        if ($scope.input.tipoRespuesta === 'texto' && $scope.input.respuesta !== '') {
            $scope.resultadoRespuesta.push({ tipo: 'texto', contenido: { texto: $scope.input.respuesta } });
            $scope.resultadoRespuestaJson.push({ tipo: 'texto', contenido: { texto: $scope.input.respuesta } });
        }
        if ($scope.input.tipoRespuesta === 'boton' && $scope.input.respuesta !== '' && $scope.input.respuestaAcc !== '') {
            $scope.resultadoRespuesta.push({ tipo: 'boton', contenido: { titulo: $scope.input.respuesta, accion: $scope.input.respuestaAcc } });
            $scope.resultadoRespuestaJson.push({ tipo: 'boton', contenido: { titulo: $scope.input.respuesta, accion: $scope.input.respuestaAcc } });
        }
        if ($scope.input.tipoRespuesta === 'evento' && $scope.input.tipoRespuestaEvento !== '' && $scope.input.respuestaAcc !== '') {
            $scope.resultadoRespuesta.push({ tipo: 'evento', contenido: { evento: $scope.input.tipoRespuestaEvento, accion: $scope.input.respuestaAcc } });
            $scope.resultadoRespuestaJson.push({ tipo: 'evento', contenido: { evento: $scope.input.tipoRespuestaEvento, accion: $scope.input.respuestaAcc } });
        }
        if ($scope.input.tipoRespuesta === 'calendario') {
            $scope.resultadoRespuesta.push({ tipo: 'calendario', contenido: { fecha: '' } });
            $scope.resultadoRespuestaJson.push({ tipo: 'calendario', contenido: { fecha: '' } });
        }
        if ($scope.input.tipoRespuesta === 'notas') {
            $scope.resultadoRespuesta.push({ tipo: 'nota', contenido: { notaId: $scope.resultadoRespuestaNota.notaId, titulo: $scope.resultadoRespuestaNota.titulo } });
            $scope.resultadoRespuestaJson.push({ tipo: 'nota', contenido: { notaId: $scope.resultadoRespuestaNota.notaId, titulo: $scope.resultadoRespuestaNota.titulo } });
        }
        if ($scope.input.tipoRespuesta === 'enlace') {
            $scope.resultadoRespuesta.push({ tipo: 'enlace', contenido: { titulo: $scope.input.respuesta, url: $scope.input.respuestaAcc } });
            $scope.resultadoRespuestaJson.push({ tipo: 'enlace', contenido: { titulo: $scope.input.respuesta, url: $scope.input.respuestaAcc } });
        }
        console.log($scope.resultadoRespuestaJson);
        $scope.input.respuesta = '';
        $scope.input.respuestaAcc = '';
        $scope.input.tipoRespuesta = 'texto';

        $timeout(function () {
            $scope.tabuListaConstPreguntas++;
        }, 100);
    };

    $scope.limpiarRespuesta = function () {
        $scope.resultadoRespuesta = [];
        $scope.resultadoRespuestaJson = [];
    };
    $scope.preguntaHide = function (id) {
        $scope.visibleb = false;
        $scope.encabezado = true;
        console.log("ocultar pregunta");
        angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
            if (i !== id) {
                console.log("cerrar pregunta true");
                $scope.chatbots.chatPreguntas[i].mostrar = true;
            } else {
                console.log("cerrar pregunta false");
                $scope.chatbots.chatPreguntas[i].mostrar = false;
            }
        });
    };

    /////////////////////////////////Botones de inicio desplegables////////////////////  

    $scope.configurarChatbot = function (item) {
        $scope.listaConfigurarChatbot = true;
        $scope.chatbots = item;
        $timeout(function () {
            $scope.tabuVariable++;
        }, 100);

    };

    $scope.entrenarChatbot = function (item) {
        $scope.chatbots = item;
        $timeout(function () {
            $scope.tabuEntrenar++;
        }, 100);
        $timeout(function () {
            $scope.listaEntrenarChatbot = true;
        }, 200);
    };

    $scope.analizarChatbot = function (item, lista) {
        $scope.listaAnalizarChatbot = true;
        $scope.detM3 = item;
        $timeout(function () {
            $scope.tabuVariable++;
        }, 100);
    };

    $scope.deslistaConfigurarChatbot = function () {
        $scope.listaConfigurarChatbot = false;
    };

    $scope.deslistaReentrenarChatbot = function () {
        $scope.reentrenarChatbot = false;
        $timeout(function () {
            $scope.tabuVariable++;
        }, 100);
    };
    $scope.deslistaEntrenarChatbot = function () {
        console.log('deslistaEntrenarChatbot');
        $scope.listaEntrenarChatbot = false;
        $timeout(function () {
            $scope.tabuVariable++;
        }, 100);
    };

    $scope.deslistaAnalizarChatbot = function () {
        $scope.listaAnalizarChatbot = false;
    };

    ////////////////////////////////ver lista de sinonimos y respuestas/////////// 
    $scope.verlistasinonimos = function (id) {
        console.log("verlistasinonimos");

        //
        angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
            if (i !== id) {
                console.log("mostrar sinonimos lista true");
                $scope.chatbots.chatPreguntas[i].mostrar = false;
            } else {
                console.log("mostrar sinonimos lista false");
                $scope.chatbots.chatPreguntas[i].mostrar = true;
            }
        });
        //

        $scope.visibleb = true;
        $scope.listasinonimos = true;
        $scope.listarespuestas = false;
        $scope.encabezado = false;

    };

    $scope.verlistarespuestas = function (id) {
        console.log("verlistasinonimos");

        //
        angular.forEach($scope.chatbots.chatPreguntas, function (v, i) {
            if (i !== id) {
                console.log("mostrar respuestas lista true");
                $scope.chatbots.chatPreguntas[i].mostrar = false;
            } else {
                console.log("mostrar respuestas lista false");
                $scope.chatbots.chatPreguntas[i].mostrar = true;
            }
        });
        //

        $scope.visibleb = true;
        $scope.listarespuestas = true;
        $scope.listasinonimos = false;
        $scope.encabezado = false;
    };

    //

    $scope.controlador = { "nombre": "chatbots", "barra": 1, "buscadorCartera": 1, "cargandoBarra": 0, "recargaCartera": 1, "buscadorEstadoGestion": 0 };
    $timeout(function () {
        myIntercom.publica('controlador', $scope.controlador);
    }, 1000);
}]);
//_FIN_DE_ARCHIVO