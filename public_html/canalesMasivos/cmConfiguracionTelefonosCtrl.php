<?php
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.coTabulaMongo.php");
require_once("../comunes/classes/class.coCompleteAngular.php");

if (!isset($_REQUEST["act"])) {
    exit;
}
if (!$Central->hasDomain("Link")) {
    exit;
}

global $Central;
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

switch ($act) {
    case "devolverParametrizacion":
        $json["proveedores"] = [
            ["id" => "ELEVENLABS", "nombre" => "ELEVENLABS"],
            ["id" => "LINK", "nombre" => "LINK"],
            ["id" => "RETELL", "nombre" => "RETELL"]
        ];

        $carteras = [];
        $mysql = new MYSQLDB();
        $mysql2 = new MYSQLDB();
        $sql = $mysql->mkSQL("SELECT cobCartera_id,cobCartera_nombre "
            . "FROM cobcartera ORDER BY cobCartera_nombre ASC");
        if ($mysql->query($sql) > 0) {
            while ($row = $mysql->fetchRow()) {
                $carteras[] = $row;
            }
        }
        $json["carteras"] = $carteras;

        $campanias = [];
        $sql = $mysql->mkSQL("SELECT cobCarteraRamas_ramaIdfk, cobCarteraRamas_carteraIdfk "
            . "FROM cobcarteraramas");
        if ($mysql->query($sql) > 0) {
            while ($row = $mysql->fetchRow()) {
                $sql2 = $mysql2->mkSQL("SELECT scRamas_id, scRamas_nombre FROM scramas WHERE scRamas_id=%N", $row["cobCarteraRamas_ramaIdfk"]);
                if ($mysql2->query($sql2) > 0) {
                    while ($row2 = $mysql2->fetchRow()) {
                        $campanias[] = ["scRamas_nombre" => $row2["scRamas_nombre"], "scRamas_id" => $row2["scRamas_id"]];
                    }
                }
            }
        }
        $json["campanias"] = $campanias;
        break;
    case "cargarTabula":
        #region cargarTabula 
        $d = jsonStart();
        $cartera = expect_integer($_GET["cartera"]);
        $campania = expect_integer($_GET["campania"]);

        $mongo = new MYMONGODB();
        $ids = [];
        if ($cartera != "") {
            $c = $mongo->buscar("avNumeroPorCartera", ["tipoRelacion" => "cartera", "idRelacionado" => intval($cartera)]);
            while ($row = $mongo->siguiente()) {
                $ids[$row["idNumero"]] = $row["idNumero"];
            }
        }
        if ($campania != "") {
            $c = $mongo->buscar("avNumeroPorCartera", ["tipoRelacion" => "campania", "idRelacionado" => intval($campania)]);
            while ($row = $mongo->siguiente()) {
                $ids[$row["idNumero"]] = $row["idNumero"];
            }
        }

        $condicion = ["tipo" => "numero"];
        if (count($ids) > 0) {
            $idsFinal = [];
            foreach ($ids as $id) {
                $idsFinal[] = $mongo->String2MongoId($id);
            }
            $condicion["_id"] = ['$in' => $idsFinal];
        }

        $ngTabula = new coTabulaMongo();
        $ngTabula->setInput($d);
        $ngTabula->setLimpiador($limpiar);
        $ngTabula->setQueryDatos("avParametros", $condicion, [], ["proveedor" => 1, "numero" => 1]);
        $mysql = new MYSQLDB();
        $ngTabula->setPreparaDatos(function ($campos) use ($mongo, $mysql) {
            $carteras = [];
            $campanias = [];
            $c = $mongo->buscar("avNumeroPorCartera", ["idNumero" => $campos["id"]]);
            if ($c > 0) {
                while ($r = $mongo->siguiente()) {
                    if ($r["tipoRelacion"] == "cartera") {
                        $nombre = "";
                        $sql = $mysql->mkSQL("SELECT cobCartera_nombre FROM cobcartera WHERE cobCartera_id=%N", $r["idRelacionado"]);
                        if ($mysql->query($sql) > 0) {
                            while ($row = $mysql->fetchRow()) {
                                $nombre = $row["cobCartera_nombre"];
                            }
                        }
                        $carteras[] = [
                            "idRelacionado" => $r["idRelacionado"],
                            "nombre" => $nombre,
                            "exclusivo" => $r["exclusivo"] == 1
                        ];
                    }
                    if ($r["tipoRelacion"] == "campania") {
                        $nombre = "";
                        $sql = $mysql->mkSQL("SELECT scRamas_nombre FROM scramas WHERE scRamas_id=%N", $r["idRelacionado"]);
                        if ($mysql->query($sql) > 0) {
                            while ($row = $mysql->fetchRow()) {
                                $nombre = $row["scRamas_nombre"];
                            }
                        }
                        $campanias[] = [
                            "idRelacionado" => $r["idRelacionado"],
                            "nombre" => $nombre,
                            "exclusivo" => $r["exclusivo"] == 1
                        ];
                    }
                }
            }
            $campos["carteras"] = $carteras;
            $campos["campanias"] = $campanias;
            $campos["activo"] = $campos["activo"] === 1;
            if (isset($campos["modificaFecha"]) && $campos["modificaFecha"] > 0) {
                $campos["registrofecha"] = $campos["modificaFecha"];
                $campos["registroUsuaruioNombre"] = $campos["modificaUsuarioNombre"];
            }
            return $campos;
        });
        $json = $ngTabula->responde();
        #endregion 
        break;
    case "buscarRelacionPorId":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $mongo = new MYMONGODB();
        $mysql = new MYSQLDB();
        $mongo->buscar("avParametros", ["_id" => $mongo->String2MongoId($id)]);
        $fila = $mongo->siguiente();

        $carteras = [];
        $campanias = [];
        $c = $mongo->buscar("avNumeroPorCartera", ["idNumero" => $fila["id"]]);
        if ($c > 0) {
            while ($r = $mongo->siguiente()) {
                if ($r["tipoRelacion"] == "cartera") {
                    $nombre = "";
                    $sql = $mysql->mkSQL("SELECT cobCartera_nombre FROM cobcartera WHERE cobCartera_id=%N", $r["idRelacionado"]);
                    if ($mysql->query($sql) > 0) {
                        while ($row = $mysql->fetchRow()) {
                            $nombre = $row["cobCartera_nombre"];
                        }
                    }
                    $carteras[] = [
                        "idRelacionado" => $r["idRelacionado"],
                        "nombre" => $nombre
                    ];
                }
                if ($r["tipoRelacion"] == "campania") {
                    $nombre = "";
                    $sql = $mysql->mkSQL("SELECT scRamas_nombre FROM scramas WHERE scRamas_id=%N", $r["idRelacionado"]);
                    if ($mysql->query($sql) > 0) {
                        while ($row = $mysql->fetchRow()) {
                            $nombre = $row["scRamas_nombre"];
                        }
                    }
                    $campanias[] = [
                        "idRelacionado" => $r["idRelacionado"],
                        "nombre" => $nombre,
                        "exclusivo" => $r["exclusivo"] == 1
                    ];
                }
            }
        }
        $fila["carteras"] = $carteras;
        $fila["campanias"] = $campanias;
        $fila["activo"] = $fila["activo"] === 1;
        if (isset($fila["modificaFecha"]) && $fila["modificaFecha"] > 0) {
            $fila["registrofecha"] = $fila["modificaFecha"];
            $fila["registroUsuaruioNombre"] = $fila["modificaUsuarioNombre"];
        }

        $json["fila"] = $fila;

        break;
    case "cargarCarteras":
        $d = jsonStart();
        $mysql = new MYSQLDB();
        $autocom = new coCompleteAngular();
        $autocom->setInput($d);

        $autocom->setQueryDatos("SELECT cobCartera_id,cobCartera_nombre "
            . "FROM cobcartera ORDER BY cobCartera_nombre ASC ", "cobCartera_id cobCartera_nombre");
        $autocom->setCamposConTabla(false);
        $json = $autocom->responde();
        break;
    case "cargarCampanias":
        $d = jsonStart();
        $mysql = new MYSQLDB();
        $autocom = new coCompleteAngular();
        $autocom->setInput($d);
        $cartera = expect_integer($_GET["cartera"]);

        $autocom->setQueryDatos("SELECT cobCarteraRamas_ramaIdfk, cobCarteraRamas_carteraIdfk "
            . "FROM cobcarteraramas WHERE cobCarteraRamas_carteraIdfk=" . $cartera, "cobCarteraRamas_ramaIdfk cobCarteraRamas_carteraIdfk");
        $autocom->setCamposConTabla(false);
        $mysql = new MYSQLDB();
        $autocom->setPreparaDatos(function ($campos) use ($mysql) {
            $sql = $mysql->mkSQL("SELECT scRamas_nombre FROM scramas WHERE scRamas_id=%N", $campos["cobCarteraRamas_ramaIdfk"]);
            if ($mysql->query($sql) > 0) {
                while ($row = $mysql->fetchRow()) {
                    $campos["scRamas_nombre"] = $row["scRamas_nombre"];
                }
            }
            return $campos;
        });
        $json = $autocom->responde();
        break;
    case "relacionar":
        $d = jsonStart();
        $numero = expect_safe_html($d["numero"]);
        $cartera = expect_integer($d["cartera"]);
        $campania = expect_integer($d["campania"]);
        $exclusivo = expect_integer($d["exclusivo"]);

        $mongo = new MYMONGODB();
        $continuar = true;
        if ($exclusivo == 1) {
            //si ya tiene otra relacion no se deja poner como exclusivo
            $c = $mongo->buscar("avNumeroPorCartera", ["idNumero" => $numero]);
            if ($c > 0) {
                $json["error"] = ["No se puede crear una relación exclusiva con este número porque ya existe relaciones previas.", "Primero elimine las relaciones exostentes para crear la relación exclusiva."];
                $continuar = false;
            }
        }

        if ($continuar) {
            $tipo = "campania";
            if ($cartera == 0) {
                $json["error"] = "Selecciones la cartera";
            } else {
                if ($campania == 0) {
                    $tipo = "cartera";
                }

                if ($tipo == "campania") {
                    $c = $mongo->buscar("avNumeroPorCartera", ["idNumero" => $numero, "idRelacionado" => intval($campania), "tipoRelacion" => "campania"]);
                }
                if ($tipo == "cartera") {
                    $c = $mongo->buscar("avNumeroPorCartera", ["idNumero" => $numero, "idRelacionado" => intval($cartera), "tipoRelacion" => "cartera"]);
                }
                if ($c > 0) {
                    $json["error"] = "Esta relación ya existe";
                } else {
                    $relacionado = $tipo == "cartera" ? $cartera : $campania;
                    $resp = $mongo->guardar("avNumeroPorCartera", [
                        "idNumero" => $numero,
                        "idRelacionado" => $relacionado,
                        "tipoRelacion" => $tipo,
                        "exclusivo" => $exclusivo
                    ]);
                    if ($resp != 0) {
                        $json["respuesta"] = "Relación creada con éxito";
                    } else {
                        $json["error"] = "No se pudo crear la relación";
                    }
                }
            }
        }
        break;
    case "eliminarRelacion":
        $d = jsonStart();
        $idRelacionado = expect_integer($d["idRelacionado"]);
        $tipo = expect_safe_html($d["tipo"]);
        $numero = expect_safe_html($d["numero"]);
        $mongo = new MYMONGODB();
        $resp = $mongo->borrar("avNumeroPorCartera", [
            "idNumero" => $numero,
            "idRelacionado" => intval($idRelacionado),
            "tipoRelacion" => $tipo
        ]);
        if ($resp) {
            $json["respuesta"] = "Relación eliminada con éxito";
        } else {
            $json["error"] = "No se pudo eliminar la relación";
        }
        break;
    case "eliminarTelefono":
        $d = jsonStart();
        $numero = expect_safe_html($d["numero"]);
        $mongo = new MYMONGODB();
        $resp = $mongo->borrar("avNumeroPorCartera", [
            "idNumero" => $numero
        ]);
        $resp2 = $mongo->borrar("avParametros", [
            "_id" => $mongo->String2MongoId($numero),
            "tipo" => "numero"
        ]);
        if ($resp2) {
            $json["respuesta"] = "Teléfono eliminado con éxito";
        } else {
            $json["error"] = "No se pudo eliminar el teléfono";
        }
        break;
    case "guardarNuevoTelefono":
        $d = jsonStart();
        $proveedor = expect_safe_html($d["proveedor"]);
        $numero = expect_safe_html($d["numero"]);
        $mongo = new MYMONGODB();
        $c = $mongo->buscar("avParametros", ["proveedor" => $proveedor, "numero" => $numero]);
        if ($c > 0) {
            $json["error"] = "Ya existe ese número en ese proveedor";
        } else {
            $nuevo = [
                "proveedor" => $proveedor,
                "numero" => $numero,
                "numeroId" => $numero,
                "tipoNumero" => "Individual",
                "registrofecha" => time(),
                "registroUsuarioId" =>  (int) $_SESSION[MID . "userId"],
                "registroUsuaruioNombre" => $_SESSION[MID . "userNombre"],
                "tipo" => "numero",
                "activo" => 1
            ];
            $resp = $mongo->guardar("avParametros", $nuevo);
            if ($resp != 0) {
                $json["respuesta"] = "Nuevo número creado con éxito";
            } else {
                $json["error"] = "No se pudo crear el nuevo número";
            }
        }
        break;
    case "activarTelefono":
        $d = jsonStart();
        $id = expect_safe_html($d["id"]);
        $activo = expect_integer($d["activo"]);

        $mongo = new MYMONGODB();
        $resp = $mongo->actualizar("avParametros", ["_id" => $mongo->String2MongoId($id)], [
            "activo" => $activo,
            "registrofecha" => time(),
            "registroUsuarioId" =>  (int) $_SESSION[MID . "userId"],
            "registroUsuaruioNombre" => $_SESSION[MID . "userNombre"],
        ]);

        if ($resp > 0) {
            $json["respuesta"] = "Teléfono modificado con éxito";
        } else {
            $json["error"] = "No se pudo modificar el teléfono";
        }

        break;
}

jsonEnd($json, $limpiar);

?><? //_FIN_DE_ARCHIVO                                                                                                                                  
    ?>
