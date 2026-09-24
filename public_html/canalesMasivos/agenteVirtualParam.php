<?php
require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
if (!isset($_REQUEST["act"])) {
    exit;
}
if (!$Central->hasDomain("Link")) {
    exit;
}
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = [];
$limpiar = [];

switch ($act) {
    case "devolverParametrizacion":
        $d = jsonStart();
        $json["proveedores"] = [
            [
                "id" => "RETELL",
                "nombre" => "RETELL",
                "tipos" => [
                    [
                        "id" => "telefono",
                        "nombre" => "Teléfono"
                    ],
                    [
                        "id" => "whatsapp",
                        "nombre" => "WhatsApp"
                    ]
                ]
            ],
            [
                "id" => "LINK",
                "nombre" => "LINK-IA",
                "tipos" => [
                    [
                        "id" => "telefono",
                        "nombre" => "Teléfono"
                    ],
                    [
                        "id" => "whatsapp",
                        "nombre" => "WhatsApp"
                    ]
                ]
            ],
            // [
            //     "id" => "ELEVENLABS",
            //     "nombre" => "ELEVENLABS"
            // ],            
        ];
        $json["proveedoresInternos"] = [
            [
                "id" => "LINK",
                "nombre" => "LINK",
                "tipos" => [
                    [
                        "id" => "whatsapp",
                        "nombre" => "WhatsApp"
                    ],
                    [
                        "id" => "telefono",
                        "nombre" => "Teléfono"
                    ]
                ]
            ],
        ];
        // $json["modelos"] = [
        //     [
        //         "id" => "deepseek-r1:latest",
        //         "nombre" => "deepseek-r1:latest"
        //     ],
        //     [
        //         "id" => "gemma:7b",
        //         "nombre" => "gemma:7b"
        //     ],
        //     [
        //         "id" => "qwen3:latest",
        //         "nombre" => "qwen3:latest"
        //     ],
        //     [
        //         "id" => "GandalfBaum/llama3.2-claude3.7:latest",
        //         "nombre" => "llama3.2-claude3.7:latest"
        //     ],
        //     [
        //         "id" => "nous-hermes2-mixtral:latest",
        //         "nombre" => "nous-hermes2-mixtral:latest"
        //     ],
        //     [
        //         "id" => "mistral-openorca:latest",
        //         "nombre" => "mistral-openorca:latest"
        //     ]
        // ];

        // $json["tipos"] = [
        //     [
        //         "id" => "telefono",
        //         "nombre" => "Teléfono"
        //     ],
        //     [
        //         "id" => "whatsapp",
        //         "nombre" => "WhatsApp"
        //     ]
        // ];
        break;
    case "traerDetalleProveedor":
        $d = jsonStart();
        $proveedor = expect_safe_html($d["id"]);
        $gpu = expect_boolean($d["gpu"]);
        if ($proveedor == "RETELL") {
            require_once("../canalesMasivos/apis/class.retellAPI.php");
            $retellAPI = new retellAPI();
            $agentes = [];
            $apiAgentes = $retellAPI->api_obtenerAgentes();
            if ($apiAgentes["estado"] == "OK") {
                foreach ($apiAgentes["datos"] as $value) {
                    $agentes[$value["agent_id"]] = [
                        "nombre" => utf8_2_decode($value["agent_name"]),
                        "id" => $value["agent_id"]
                    ];
                }
            }
            $telefonos = [];
            $apiTelefonos = $retellAPI->api_obtenerTelefonos();
            if ($apiTelefonos["estado"] == "OK") {
                foreach ($apiTelefonos["datos"] as $value) {
                    $telefonos[$value["phone_number"]] = [
                        "nombre" => isset($value["nickname"]) && $value["nickname"] != "" ? $value["nickname"] : (isset($value["phone_number_pretty"]) ? $value["phone_number_pretty"] : $value["phone_number"]),
                        "id" => $value["phone_number"]
                    ];
                }
            }
            $concurrencia = 0;
            $apiConc = $retellAPI->api_devolverConcurrencia();
            if ($apiConc["estado"] == "OK") {
                $concurrencia = intval($apiConc["datos"]["concurrency_limit"]);
            }
            sort($agentes);
            sort($telefonos);
            $json["agentes"] = $agentes;
            $json["telefonos"] = $telefonos;
            $json["voces"] = [];
            $json["concurrencia"] = $concurrencia;
        } else if ($proveedor == "ELEVENLABS") {
            require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");
            $elevenlabsAPI = new elevenlabsAPI();
            $agentes = [];
            $apiAgentes = $elevenlabsAPI->api_obtenerAgentes();
            if ($apiAgentes["estado"] == "OK") {
                foreach ($apiAgentes["datos"] as $value) {
                    $agentes[$value["agente_id"]] = [
                        "nombre" => $value["agente_nombre"],
                        "id" => $value["agente_id"]
                    ];
                }
            }
            $telefonos = [];
            $apiTelefonos = $elevenlabsAPI->api_obtenerTelefonos();
            if ($apiTelefonos["estado"] == "OK") {
                foreach ($apiTelefonos["datos"] as $value) {
                    $telefonos[$value["telefono_id"]] = [
                        "nombre" => $value["telefono_nombre"],
                        "id" => $value["telefono_id"]
                    ];
                }
            }
            $voces = [];
            $apiVoces = $elevenlabsAPI->api_obtenerVoces();
            if ($apiVoces["estado"] == "OK") {
                foreach ($apiVoces["datos"] as $value) {
                    $voces[$value["voz_id"]] = [
                        "nombre" => $value["voz_nombre"],
                        "id" => $value["voz_id"]
                    ];
                }
            }

            sort($agentes);
            sort($telefonos);
            sort($voces);
            $json["agentes"] = $agentes;
            $json["telefonos"] = $telefonos;
            $json["voces"] = $voces;
            $json["concurrencia"] = 0;
        } else if ($proveedor == "LINK") {
            $servidor = $gpu ? "gpu" : "local";
            require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
            $linkLocalLlmAPI = new linkLocalLlmAPI($servidor);
            $agentes = [];
            $apiAgentes = $linkLocalLlmAPI->api_obtenerAgentes(100, 0, 0, 0);
            if ($apiAgentes["estado"] == "OK") {
                foreach ($apiAgentes["datos"] as $value) {
                    $agentes[$value["agente_id"]] = [
                        "nombre" => utf8_2_decode($value["agente_nombre"]),
                        "id" => $value["agente_id"]
                    ];
                }
            }
            $modelos = [];
            $apiModelos = $linkLocalLlmAPI->api_obtenerModelos();
            if ($apiModelos["estado"] == "OK") {
                foreach ($apiModelos["datos"] as $value) {
                    $modelos[] = [
                        "nombre" => $value["nombre"],
                        "id" => $value["nombre"]
                    ];
                }
            }
            $telefonos = [];
            $concurrencia = 0;
            sort($agentes);
            sort($modelos);
            sort($telefonos);
            $json["agentes"] = $agentes;
            $json["modelos"] = $modelos;
            $json["telefonos"] = $telefonos;
            $json["voces"] = [];
            $json["concurrencia"] = $concurrencia;
        } else {
            $json["error"] = "Proveedor desconocido";
        }
        break;
    case "cambiarActivo":
        $mongo = new MYMONGODB();
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $activo = expect_boolean($d["activo"]);

        $data = [
            "activo" => $activo ? 1 : 0,
            "modificaFecha" => (int) time(),
            "modificaUsuarioId" => (int) $_SESSION[MID . "userId"],
            "modificaUsuarioNombre" => $_SESSION[MID . "userNombre"],
        ];
        $resp = $mongo->actualizar('avParametros', ["_id" => $mongo->String2MongoId($id)], $data);
        if ($resp > 0) {
            $json["respuesta"] = "Agente modificado con éxito";
        } else {
            $json["respuesta"] = "No se pudo modificar el agente";
        }

        break;
    case "eliminarAgente":
        $mongo = new MYMONGODB();
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $gpu = expect_boolean($d["gpu"]);
        $c = $mongo->buscar("avParametros", ["_id" => $mongo->String2MongoId($id)]);

        if ($c > 0) {
            $a = $mongo->siguiente();
            if ($a["proveedor"] == "LINK") {
                $servidor = $gpu ? "gpu" : "local";
                //elimino de ollama
                require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
                $link = new linkLocalLlmAPI($servidor);
                $respuesta = $link->api_eliminarAgente($a["agenteId"]);
                if ($respuesta["estado"] == "OK") {
                    $resp = $mongo->borrar('avParametros', ["_id" => $mongo->String2MongoId($id)]);
                } else {
                    $resp = false;
                }
            } else {
                $resp = $mongo->borrar('avParametros', ["_id" => $mongo->String2MongoId($id)]);
            }
            if ($resp) {
                //si es un agente local debo eliminar de ollama
                $json["respuesta"] = "Agente eliminado con éxito";
            } else {
                $json["respuesta"] = "No se pudo eliminar el agente";
            }
        } else {
            $json["respuesta"] = "No se pudo eliminar el agente";
        }

        break;
    case "guardar":
        $d = jsonStart();
        if (!isset($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [];
        }
        if (!is_array($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [$d["valores_reemplazo"]];
        }
        if (
            isset($d["agenteId"]) && $d["agenteId"] != '' &&
            isset($d["numeroId"]) && $d["numeroId"] != ''
        ) {
            $continuar = true;
            if ($d["proveedor"] == "ELEVENLABS") {
                if (!isset($d["vozId"]) || $d["vozId"] != '') {
                    $json["respuesta"] = 'Ingrese todos los datos requeridos';
                    $continuar = false;
                }
            }

            if ($continuar) {
                $data = [
                    "agenteId" => strval($d["agenteId"]),
                    "agenteNombre" => $d["agenteNombre"],
                    "apikey" => $d["apikey"],
                    "proveedor" => $d["proveedor"],
                    "numero" => strval($d["numero"]),
                    "numeroId" => strval($d["numeroId"]),
                    "voz" => isset($d["voz"]) ? strval($d["voz"]) : "",
                    "vozId" => strval($d["vozId"]),
                    "vozGenero" => "",
                    "valores_reemplazo" => $d["valores_reemplazo"],
                    "concurrencia" => intval($d["concurrencia"]),
                    "registrofecha" => time(),
                    "registroUsuarioId" => intval($_SESSION[MID . "userId"]),
                    "registroUsuaruioNombre" => $_SESSION[MID . "userNombre"],
                    "tipo" => "agente",
                    "subtipo" => isset($d["subtipo"]) ? $d["subtipo"] : "telefono",
                    "activo" => 1
                ];
                $mongo = new MYMONGODB();
                $c = $mongo->buscar('avParametros', ["agenteId" => $d["agenteId"]]);
                if ($c > 0) {
                    $json["respuesta"] = 'Agente ya existente, no se puede crear nuevo';
                    //$mongo->actualizar('avParametros', ["agenteId" => $d["agenteId"]], $data);
                } else {
                    $resp = $mongo->guardar('avParametros', $data);
                    if ($resp != 0) {
                        $json["respuesta"] = 'Agente creado con éxito';
                    } else {
                        $json["respuesta"] = 'No se pudo crear el agente';
                    }
                }
            }
        } else {
            $json["respuesta"] = 'Ingrese todos los datos requeridos';
        }
        break;
    case "guardarAgenteInterno":
        $d = jsonStart();
        $gpu = expect_boolean($d["gpu"]);
        if (!isset($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [];
        }
        if (!is_array($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [$d["valores_reemplazo"]];
        }
        if (
            isset($d["agenteId"]) && $d["agenteId"] != '' &&
            isset($d["numeroId"]) && $d["numeroId"] != '' &&
            isset($d["prompt"]) && $d["prompt"] != ''
        ) {
            $data = [
                "agenteId" => strval($d["agenteId"]),
                "agenteNombre" => $d["agenteNombre"],
                "apikey" => "",
                "proveedor" => $d["proveedor"],
                "numero" => strval($d["numeroId"]),
                "numeroId" => strval($d["numeroId"]),
                "voz" => "",
                "vozId" => "",
                "vozGenero" => "",
                "valores_reemplazo" => $d["valores_reemplazo"],
                "concurrencia" => intval($d["concurrencia"]),
                "registrofecha" => time(),
                "registroUsuarioId" => intval($_SESSION[MID . "userId"]),
                "registroUsuaruioNombre" => $_SESSION[MID . "userNombre"],
                "tipo" => "agente",
                "subtipo" => isset($d["subtipo"]) ? $d["subtipo"] : "telefono",
                "activo" => 1,
                "agentePrompt" => $d["promptCodificado"],
                "agenteSaludo" => $d["saludosCodificado"],
                "temperatura" => floatval($d["temperatura"]),
                "modeloBase" => isset($d["modelo"]) ? $d["modelo"] : ""
            ];
            $mongo = new MYMONGODB();
            $c = $mongo->buscar('avParametros', ["agenteId" => $d["agenteId"]]);
            if ($c > 0) {
                $json["respuesta"] = 'Agente ya existente, no se puede crear nuevo';
                //$mongo->actualizar('avParametros', ["agenteId" => $d["agenteId"]], $data);
            } else {
                $resp = $mongo->guardar('avParametros', $data);
                if ($resp != 0) {
                    //creo en ollama

                    //$j = base64_decode($d["saludosCodificado"]);
                    $parametrosLLM = [
                        "nombre" => $d["agenteNombre"],
                        "codigo" => $d["agenteId"],
                        "modelo" => $d["modelo"],
                        "valores-reemplazo" => $d["valores_reemplazo"],
                        "prompt" => $d["promptCodificado"],
                        "saludos" => $d["saludosCodificado"],
                        "sitio" => BASEURL,
                        "version" => 1,
                        "temperatura" => floatval($d["temperatura"])
                    ];
                    $servidor = $gpu ? "gpu" : "local";
                    require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
                    $link = new linkLocalLlmAPI($servidor);
                    $respuesta = $link->api_crearAgente($parametrosLLM);
                    if ($respuesta["estado"] == "OK") {
                        $json["respuesta"] = 'Agente creado con éxito';
                    } else {
                        //borro el registro creado
                        //$mongo->borrar('avParametros', ["_id" => $mongo->String2MongoId($resp)]);
                        $json["respuesta"] = $respuesta["mensaje"];
                    }
                } else {
                    $json["respuesta"] = 'No se pudo crear el agente';
                }
            }
        } else {
            $json["respuesta"] = 'Ingrese todos los datos requeridos';
        }
        break;
    case "actualizar":
        $d = jsonStart();
        $gpu = expect_boolean($d["gpu"]);
        if (!isset($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [];
        }
        if (!is_array($d["valores_reemplazo"])) {
            $d["valores_reemplazo"] = [$d["valores_reemplazo"]];
        }
        if (
            isset($d["agenteId"]) && $d["agenteId"] != '' &&
            isset($d["numeroId"]) && $d["numeroId"] != ''
        ) {
            $continuar = true;
            if ($d["proveedor"] == "ELEVENLABS") {
                if (!isset($d["vozId"]) || $d["vozId"] != '') {
                    $json["respuesta"] = 'Ingrese todos los datos requeridos';
                    $continuar = false;
                }
            }

            if ($continuar) {
                $continuar2 = true;
                if ($d["proveedor"] == "LINK") {
                    $codigoAgente = $d["agenteId"];
                    $parametrosLLM = [
                        "valores-reemplazo" => $d["valores_reemplazo"],
                        "que" => "valores"
                    ];
                    $servidor = $gpu ? "gpu" : "local";
                    require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
                    $link = new linkLocalLlmAPI($servidor);
                    $resp = $link->api_modificarAgente($codigoAgente, $parametrosLLM, "valores");
                    if ($resp["estado"] == "OK") {
                        $continuar2 = true;
                    } else {
                        $continuar2 = false;
                    }
                }

                if ($continuar2) {
                    $data = [
                        "agenteId" => strval($d["agenteId"]),
                        "agenteNombre" => $d["agenteNombre"],
                        "numero" => strval($d["numero"]),
                        "numeroId" => strval($d["numeroId"]),
                        "voz" => strval($d["voz"]),
                        "vozId" => strval($d["vozId"]),
                        "vozGenero" => "",
                        "valores_reemplazo" => $d["valores_reemplazo"],
                        "concurrencia" => intval($d["concurrencia"]),
                        "modificaFecha" => (int) time(),
                        "modificaUsuarioId" => (int) $_SESSION[MID . "userId"],
                        "modificaUsuarioNombre" => $_SESSION[MID . "userNombre"],
                    ];
                    $mongo = new MYMONGODB();
                    $resp = $mongo->actualizar('avParametros', ["agenteId" => $d["agenteId"]], $data);
                    if ($resp > 0) {
                        $json["respuesta"] = 'Agente actualizado con éxito';
                    } else {
                        $json["respuesta"] = 'No se pudo actualizar el agente';
                    }
                } else {
                    $json["respuesta"] = 'No se pudo actualizar el agente [002]';
                }
            }
        } else {
            $json["respuesta"] = 'Ingrese todos los datos requeridos';
        }
        break;
    case "lista":
        $coleccion = "avParametros";
        $d = jsonStart();

        foreach ($d["filtro"] as $key => $value) {
            if ($value["campo"] == "activo") {
                if ($value["filtro"] != "") {
                    if (strpos(strtolower($value["filtro"]), "inactivo") !== false) {
                        $d["filtro"][$key]["filtro"] = 0;
                        $d["filtro"][$key]["filtro2"] = 0;
                        $d["filtro"][$key]["tipo"] = "range";
                    } else if (strpos(strtolower($value["filtro"]), "activo") !== false) {
                        $d["filtro"][$key]["filtro"] = 1;
                        $d["filtro"][$key]["filtro2"] = 1;
                        $d["filtro"][$key]["tipo"] = "range";
                    } else {
                        $d["filtro"][$key]["filtro"] = "";
                        $d["filtro"][$key]["filtro2"] = "";
                        $d["filtro"][$key]["tipo"] = "range";
                    }
                }
            }
        }

        $condition = ['tipo' => 'agente'];
        $campos = [];
        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos($coleccion, $condition, $campos, ["registrofecha" => -1]);
        $mongo = new MYMONGODB();
        $ngTabula->setPreparaDatos(function ($campos) use ($mongo) {
            $c = $mongo->buscar('avRamasxAgente', ['agenteId' => $campos['_id']]);
            if ($c > 0) {
                $campos["campanias"] = [];
                while ($row = $mongo->siguiente()) {
                    $campos["campanias"][] = ['rama' => $row['rama']];
                    $campos["callcenter"] = $row['callcenter'];
                }
            }
            $campos["activo"] = $campos["activo"] === 1;
            if (isset($campos["modificaFecha"]) && $campos["modificaFecha"] > 0) {
                $campos["registrofecha"] = $campos["modificaFecha"];
                $campos["registroUsuaruioNombre"] = $campos["modificaUsuarioNombre"];
            }
            return $campos;
        });
        $json = $ngTabula->responde();
        break;
    case "traerDetalleAgente":
        $d = jsonStart();
        $proveedor = expect_safe_html($d["proveedor"]);
        $id = expect_safe_html($d["id"]);
        $prompt = "";
        $saludo = "";
        if ($proveedor == "RETELL") {
            require_once("../canalesMasivos/apis/class.retellAPI.php");
            $retellAPI = new retellAPI();
            $agente = $retellAPI->api_traerAgente($id);
            if ($agente["estado"] == "OK") {
                $idagt = $agente["datos"]["response_engine"]["llm_id"];
                $detalle = $retellAPI->api_traerPrompt($idagt);
                if ($detalle["estado"] == "OK") {
                    $prompt = base64_encode(utf8_2_decode($detalle["datos"]["general_prompt"]));
                    $saludo = isset($detalle["datos"]["begin_message"]) ? $detalle["datos"]["begin_message"] : "";
                }
            }
        }
        if ($proveedor == "ELEVENLABS") {
            require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");
            $elevenlabsAPI = new elevenlabsAPI();
        }
        $json["respuesta"] = [
            "prompt" => $prompt,
            "saludo" => $saludo
        ];
        break;
    case "actualizarPrompt":
        $d = jsonStart();
        $proveedor = expect_safe_html($d["proveedor"]);
        $id = expect_safe_html($d["id"]);
        $temperatura = expect_float($d["temperatura"]);
        $prompt = expect_safe_html($d["prompt"]);
        $saludo = expect_safe_html($d["saludo"]);
        $promptDecodificado = base64_decode($prompt);
        $gpu = expect_boolean($d["gpu"]);
        if ($promptDecodificado == false) {
            $json["respuesta"] = "No se recibió el prompt";
        } else {
            //$nuevoPrompt = utf8_2_encode($promptDecodificado);
            $nuevoPrompt = $promptDecodificado;
            $nuevoSaludo = base64_decode($saludo);
            $nuevoSaludo = $nuevoSaludo == false ? "" : $nuevoSaludo;
            if ($proveedor == "RETELL") {
                require_once("../canalesMasivos/apis/class.retellAPI.php");
                $retellAPI = new retellAPI();
                $agente = $retellAPI->api_traerAgente($id);
                if ($agente["estado"] == "OK") {
                    $idagt = $agente["datos"]["response_engine"]["llm_id"];
                    $data = [];
                    $data["general_prompt"] = $nuevoPrompt;
                    if ($nuevoSaludo != "") {
                        $data["begin_message"] = $nuevoSaludo;
                    }

                    $act = $retellAPI->api_actualizarPrompSaludoAgente($idagt, $data);
                    if ($act["estado"] == "OK") {
                        $json["respuesta"] = "Prompt/saludo actualizado con éxito";
                    } else {
                        $json["respuesta"] = $act["mensaje"];
                    }
                }
            }
            if ($proveedor == "ELEVENLABS") {
                require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");
                $elevenlabsAPI = new elevenlabsAPI();
                $data = [
                    "conversation_config" => [
                        "agent" => [
                            "first_message" => $nuevoSaludo,
                            "prompt" => [
                                "prompt" => $nuevoPrompt
                            ]
                        ]
                    ]
                ];
                $act = $elevenlabsAPI->api_modificarAgente($id, $data);
                if ($act["estado"] == "OK") {
                    $json["respuesta"] = "Prompt/saludo actualizado con éxito";
                } else {
                    $json["respuesta"] = $act["mensaje"];
                }
            }
            if ($proveedor == "LINK") {
                $mongo = new MYMONGODB();
                $c = $mongo->buscar("avParametros", ["agenteId" => $id]);
                if ($c > 0) {
                    $r = $mongo->siguiente();
                    $codigoAgente = $r["agenteId"];
                    $d = base64_decode($saludo);
                    $parametrosLLM = [
                        "temperatura" => $temperatura,
                        "prompt" => $prompt,
                        "saludos" => $saludo,
                        "que" => "prompt"
                    ];
                    $servidor = $gpu ? "gpu" : "local";
                    require_once("../canalesMasivos/apis/class.linkLocalLlmAPI.php");
                    $link = new linkLocalLlmAPI($servidor);
                    $resp = $link->api_modificarAgente($codigoAgente, $parametrosLLM, "prompt");

                    if ($resp["estado"] == "OK") {
                        $nuevo = [
                            "temperatura" => $temperatura,
                            "agentePrompt" => $prompt,
                            "agenteSaludo" => $saludo
                        ];
                        $mongo->actualizar("avParametros", ["agenteId" => $id], $nuevo);
                        $json["respuesta"] = "Prompt/saludo actualizado con éxito";
                    } else {
                        $json["respuesta"] = $resp["mensaje"];
                    }
                } else {
                    $json["respuesta"] = "No se encontró el agente a modificar";
                }
            }
        }

        break;
    case "iniciarLlamada":
        $d = jsonStart();
        $agente = expect_safe_html($d["agente"]);
        $telefono = expect_safe_html($d["telefono"]);
        $valores_reemplazo = expect_safe_html($d["valores_reemplazo"]);
        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avParametros", ["_id" => $mongo->String2MongoId($agente)]);
        if ($cursor > 0) {
            $r = $mongo->siguiente();
            if ($r["proveedor"] == "RETELL") {
                require_once("../canalesMasivos/apis/class.retellAPI.php");
                $api = new retellAPI();
            }
            if ($r["proveedor"] == "ELEVENLABS") {
                require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");
                $api = new elevenlabsAPI();
            }
            if ($api != null) {
                $dataValores = [
                    "genero_agente" => $r["vozId"], //id de voz, solo elevenlabs
                    "tipo_agente" => $r["agenteId"], //id del agente
                    "telefono" => $telefono, //telefono al cual llamar
                    "numero_telefono_agente" => $r["numeroId"], //id del telefono
                ];
                $dataNombreCampos = [];
                foreach ($valores_reemplazo as $key => $value) {
                    $dataValores[$value["nombre"]] = $value["valor"];
                    $dataNombreCampos[] = $value["nombre"];
                }

                $resp = $api->api_generarLlamada($dataValores, $dataNombreCampos, "cobranza");
                if ($resp["estado"] == "OK") {
                    $json["respuesta"] = "Llamada generada";
                    $json["idLlamada"] = isset($resp["datos"]["callSid"]) ? $resp["datos"]["callSid"] : (isset($resp["datos"]["call_id"]) ? $resp["datos"]["call_id"] : "");
                } else {
                    $json["respuesta"] = $resp["mensaje"];
                }
            } else {
                $json["respuesta"] = "Proveedor desconocido";
            }
        } else {
            $json["respuesta"] = "Agente desconocido";
        }
        break;
    case "detalleLlamada":
        $d = jsonStart();
        $agente = expect_safe_html($d["agente"]);
        $telefono = expect_safe_html($d["telefono"]);
        $idLlamada = expect_safe_html($d["idLlamada"]);
        $mongo = new MYMONGODB();
        $cursor = $mongo->buscar("avParametros", ["_id" => $mongo->String2MongoId($agente)]);
        if ($cursor > 0) {
            $r = $mongo->siguiente();
            if ($r["proveedor"] == "RETELL") {
                require_once("../canalesMasivos/apis/class.retellAPI.php");
                $api = new retellAPI();
            }
            if ($r["proveedor"] == "ELEVENLABS") {
                require_once("../canalesMasivos/apis/class.elevenLabsAPI.php");
                $api = new elevenlabsAPI();
            }
            if ($api != null) {
                $respuesta = $api->api_obtenerDetalleConversacion($idLlamada, $telefono);
                $json["respuesta"] = $respuesta["datos"];
            } else {
                $json["respuesta"] = "Proveedor desconocido";
            }
        } else {
            $json["respuesta"] = "Agente desconocido";
        }
        break;
    case "programarLlamada":
        $d = jsonStart();
        $programada = expect_safe_html($d);
        $programada["nombre"] = ucfirst(strtolower($programada["nombre"]));
        $valores_reemplazo = variablePorDefecto($programada["nombre"], $programada["telefono"]);
        require_once("../canalesMasivos/apis/class.retellAPI.php");
        $api = new retellAPI();
        if ($d["llamada"]) {
            $resp = $api->api_generarLlamada($valores_reemplazo[0], $valores_reemplazo[1],  "cobranza");
            if ($resp["estado"] == "OK") {
                $json["respuesta"][] = "Llamada programada con éxito";
                $json["idLlamada"] = isset($resp["datos"]["callSid"]) ? $resp["datos"]["callSid"] : (isset($resp["datos"]["call_id"]) ? $resp["datos"]["call_id"] : "");
                $mongo2 = new MYMONGODB();
                $mongo2->guardar("tempLogLlamadasDemo", [
                    "call_id" => isset($resp["datos"]["callSid"]) ? $resp["datos"]["callSid"] : (isset($resp["datos"]["call_id"]) ? $resp["datos"]["call_id"] : ""),
                    "origen" => $programada["telefono"],
                    "fecha" => time(),
                    "dinamicos" => $valores_reemplazo[0],
                    "tipo" => "telefono"
                ]);
            } else {
                $json["respuesta"][] = $resp["mensaje"];
            }
        }
        if ($d["whatsapp"]) {
            $prog = [
                "ws_carteraId" => 39,
                "ws_carteraNombre" => "BANCO DEL PACIFICO",
                "ws_campaniaId" => 14745,
                "ws_campaniaNombre" => "Agente Conversacional Multiprompt BP (Desarrollo) WS",
                "ws_fecha" => time(),
                "ws_cedula" => "17xxxxxxxx",
                "ws_nombre" => $programada["nombre"],
                "ws_producto" => "MASTERCARD-REESTRUCTURACION",
                "ws_telefono" => "593" . $programada["telefono"],
                "ws_marca" => "MASTERCARD",
                "ws_clienteId" => rand(11111, 99999),
                "ws_lote" => time(),
                "ws_procesado" => 0,
                "ws_evento" => 0,
                "ws_eventoFecha" => 0,
                "ws_agenteParametroId" => "687e8526f265a3b9a10ef754",
                "ws_agenteId" => "agent_875e7b2b0e45761dd1a9eca479", //agente multiprompt
                "ws_numeroId" => "593999873468",
                "ws_valoresReemplazo" => $valores_reemplazo[0],
                "ws_estadoEnvio" => "ENVIADO",
                "ws_error" => "",
                "ws_fechaIniciaLlamada" => 0,
                "ws_idChat" => "",
                "ws_fechaActualizaChat" => 0,
                "ws_tieneTranscripcion" => 0,
                "ws_factura" => "J" . rand(11111, 99999),
                "ws_proveedor" => "RETELL",
                "ws_duracionSegundos" => 0,
                "ws_fechaFinChat" => 0,
                "ws_finalizacion" => "",
                "ws_sentimiento" => "",
                "ws_numeroWP" => "593999873468"
            ];
            $mongo3 = new MYMONGODB();
            $mongo3->actualizar("avProgramadasWhatsApp", ["ws_telefono" => "593" . $programada["telefono"], "ws_estadoEnvio" => "PENDIENTE"], ["ws_estadoEnvio" => "DESPROGRAMADA"]);
            $mongo3->guardar("avProgramadasWhatsApp", $prog);
            require_once("../whatsapp/apis/class.whatsappAPI.php");
            $whatsappAPI = new whatsappAPI();
            $mongo3->buscar("plPlantillas", ["pl_nombrePlantilla" => "Agente WhatsApp Premora Normal NO Titular"]);
            $p = $mongo3->siguiente();
            $plantilla = str_replace(["::saludos::", "::nombreCorto::", "::monto_total::"], ["Buen día", $valores_reemplazo[0]["nombreCorto"], $valores_reemplazo[0]["monto_total_vencido"]], base64_decode($p["pl_plantilla"]));
            $plantilla = strip_tags($plantilla);

            $mensajes = explode("::salto_pagina::", $plantilla);
            $envios = 0;
            $error = "";
            foreach ($mensajes as $mensaje) {
                $mensaje = html_entity_decode($mensaje);
                if ($mensaje != "") {
                    $m = trim(preg_replace('/\s\s+/', ' ', $mensaje));

                    $envio = $whatsappAPI->enviar("593" . $programada["telefono"], utf8_2_encode($m), "593999873468", "", "", "", "");
                    if ($envio["estado"] == "OK") {
                        $envios++;
                    } else {
                        $error = $envio["mensaje"];
                    }
                } else {
                    $error = "Mensaje vacio";
                }
            }
            if ($envios > 0) {
                $mongo2 = new MYMONGODB();
                $mongo2->guardar("tempLogLlamadasDemo", [
                    "mensaje" => $mensajes,
                    "origen" => $programada["telefono"],
                    "fecha" => time(),
                    "dinamicos" => $valores_reemplazo[0],
                    "tipo" => "whatsapp"
                ]);
                $json["respuesta"][] = "Enviado whatsapp con éxito";
            } else {
                $json["respuesta"][] = $error;
            }
        }

        break;
}
jsonEnd($json, $limpiar);

function variablePorDefecto($nombre, $telefono)
{
    $dias = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
    $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    $hoy = time();
    $data = [
        "genero_agente" => "", //id de voz, solo elevenlabs
        "tipo_agente" => "agent_c193c277b250a3f0d3e6db0a01", //"agent_d9cdaed8222516a866d7545ce2", //id del agente
        "telefono" => "+593" . $telefono, //telefono al cual llamar
        "numero_telefono_agente" => "+593964285881",
        "fecha_hora_local" => ($dias[intval(date("w", $hoy)) - 1]) . ", " . ($meses[(intval(date("m", $hoy)))]) . " " . intval(date("d")) . ", " . date("Y") . " ECT (GMT-05:00) " . date("H:i:s") . " (Hoy)",
        "nombre" => $nombre,
        "nombreCorto" => explode(" ", $nombre)[0],
        "dias_mora" => rand(1, 8),
        "nro_cuotas_vencidas" => "1",
        "cuotas_por_vencer" => "1",
        "monto_total_vencido" => rand(15, 80),
        "monto_total_por_vencer" => rand(15, 80),
        "cuota" => "::cuota::",
        "fecha_vencimiento" => (intval(date("d")) + 5) . " de " . ($meses[(intval(date("m", $hoy)) - 1)]) . " de " . date("Y"),
        "nro_operacion" => "OP" . rand(11111, 99999),
        "dia_corte" => intval(date("d")) + 5,
        "marca" => "MASTERCARD",
        "producto" => "MASTERCARD-REESTRUCTURACION",
        "es_titular" => "si"
    ];

    $variable = [
        "fecha_hora_local",
        "nombre",
        "nombreCorto",
        "dias_mora",
        "nro_cuotas_vencidas",
        "cuotas_por_vencer",
        "monto_total_vencido",
        "monto_total_por_vencer",
        "cuota",
        "fecha_vencimiento",
        "nro_operacion",
        "dia_corte",
        "marca",
        "producto"
    ];

    return [$data, $variable];
}


?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
