<?php

require_once "../carga/interfaces/CaCargaPlugin.php";

class pgCrgAsignacionTerreno extends Clase implements CaCargaPlugin {

    
    public function solicitarCampos(): array
    {
        $db = new MYSQLDB();
        $campos = [
            'cartera' => [
                'selected'      => '',
                'default'       => '',
                'required'      => true,
                'type'          => 'select',
                'descripcion'   => 'Cartera',
                'grid'          => ['sm'=>12, 'md'=>12, 'lg'=>6],
                'db'            => []
            ],
            'campania' => [
                'selected'      => '',
                'default'       => '',
                'required'      => true,
                'type'          => 'select',
                'descripcion'   => 'CampaÒa',
                'grid'          => ['sm'=>12, 'md'=>12, 'lg'=>6],
                'db'            => []
            ],
            'prioridad' => [
                'selected'      => false,
                'default'       => false,
                'required'      => false,
                'type'          => 'bool',
                'descripcion'   => 'No modificar prioridad',
                'grid'          => ['sm'=>12, 'md'=>12, 'lg'=>6],
                'db'            => []
            ],
            'reasignar' => [
                'selected'      => false,
                'default'       => false,
                'required'      => false,
                'type'          => 'bool',
                'descripcion'   => 'Reasignar clientes incluso si estos ya estan gestionados',
                'grid'          => ['sm'=>12, 'md'=>12, 'lg'=>6],
                'db'            => []
            ]
        ];
        $db->query($db->mkSQL("SELECT * FROM cobcartera where cobCartera_estado=%N", 1));
        while ($row = $db->fetchRow()) {
            $campos['cartera']['db'][] = [$row['cobCartera_id'], $row['cobCartera_nombre']];
        }
        $db->query($db->mkSQL("SELECT 
            scRamas_id, 
            CONCAT(scRamas_id, ' - ', scRamas_nombre) AS nombre 
            FROM scramas WHERE scRamas_padreId = 1 ORDER BY scRamas_nombre"));
        while ($row = $db->fetchRow()) {
            $campos['campania']['db'][] = [$row['scRamas_id'], $row['nombre']];
        }
        return $campos;
    }

    public function procesarArchivo(CaCargaDTO $data): array
    {
        $return = [];
        $mongo = new MYMONGODB();
        $cargaId = $data->cargaId;
        $archivo = $data->archivo;
        $relativePath = $data->relativePath;
        $filename = $data->fileName;
        $idCartera = $data->camposAdicionales['cartera'];
        $idCampania = $data->camposAdicionales['campania'];
        $prioridad = $data->camposAdicionales['prioridad'];
        $reasignar = $data->camposAdicionales['reasignar'];

        
        $retVal = 'Archivo procesado' . ":<br>" . $filename;
        $retVal .= "<br><br>";
        $datos = file($archivo);
        $correct = 0;
        $numReg = 0;
        $numRegProg = 0;
        $numeroLineas = 0;
        foreach ($datos as $reg) {
            $dat = explode(";", $reg);
            $inicia = true;
            if (trim(strtoupper($dat[0])) == "CEDULA") {
                $inicia = false;
                $correct = $this->validaCabezera($dat);
                $numeroLineas = count($datos)-1;
                $mongo->actualizar('cbCreditos', [ 'cre_carteraId' => (string) $idCartera ], [ 'cre_asignacionPrioridadTerreno' => 0 ]);
            } else {
                $numReg++;
                if ($correct == 0) {
                    if ($inicia) {
                        $cedula = trim($dat[0]);
                        if (strlen($cedula) == 9 || strlen($cedula) == 12) {
                            $cedula = '0' . $cedula;
                        }
                        $cur = $mongo->buscar('CRM', array('crm_cedula' => (string) $cedula));
                        $crm = 0;
                        if ($cur > 0) {
                            $crm = 1;
                        } else {
                            $return[] = 'NO existe cedula ' . $cedula;
                        }
                        if ($crm == 1) {
                            $cur = $mongo->buscar('cbCreditos', array('cre_carteraId' => (string) $idCartera, 'cre_cedula' => (string) $cedula, 'cre_inactivo' => (int) 0, 'cre_pagado' => (int) 0));
                            $cre = 0;
                            if ($cur > 0) {
                                $cre = 1;
                            } else {
                                $return[] = 'NO tiene deuda ' . $cedula;
                            }
                        }
                        if ($cre == 1 && $crm == 1) {
                            print_h('Asignacion prioridad... ' . $cedula);
                            $mongo->actualizar('cbCreditos', [
                                'cre_carteraId' => (string) $idCartera,
                                'cre_cedula' => (string) $cedula
                                    ], [
                                'cre_asignacionPrioridadTerreno' => (int) 1
                            ]);
                            $numRegProg++;
                        }
                    }
                } else {
                    return ['error' => "Error en la columna {$correct} no coincide con la descripcion del formato establecido. ARCHIVO NO PROCESADO"];
                }
            }
        }
        $return[] = 'Total Registros: ' . $numeroLineas;
        $return[] = 'Total Procesadas: ' . $numReg;
        $return[] = 'Total Asignacion Prioridad: ' . $numRegProg;
        $archivoErrores = strtr(str_replace(" ", "_", $filename), "·ÈÌÛ˙¸Ò¡…ÕO⁄‹—-.,'\":;\\<>?/`~!@#$%^&*()+=[]", "aeiouunAEIOUUN__________________________________");
        $archivoErrores = time() . "." . $archivoErrores . ".txt";
        $fp = fopen(BASEFOLDER . $relativePath . $archivoErrores, "w+");
        $respuestaVal = str_replace("<br>", "\n", $retVal);
        fwrite($fp, $respuestaVal);
        $arrDatos = [
            'cargaId' => $cargaId,
            'nombre' => $filename,
            'tipo' => 'AsignacionTerreno',
            'ruta' => $relativePath,
            'bitacError' => $archivoErrores,
            'registros' => $numReg
        ];
        require_once("../carga/classes/class.caCargaArchivos.php");
        $archivoCarga = new caCargaArchivos();
        $archivoCarga->insertar($arrDatos);
        return $return;
    }

    private function validaCabezera($dat):int 
    {
        $err = 0;
        if (trim(strtoupper($dat[0])) != "CEDULA") {
            $err = 1;
        }
        return $err;
    }
}
// _FIN_DE_ARCHIVO