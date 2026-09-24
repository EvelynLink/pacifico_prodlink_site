<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '3096M');

chdir(__DIR__);
require_once("../comunes/top.inc.php");
require_once("../functions/basic.php");
require_once("../comunes/classes/class.mymongodb.php");

resultados();
exit;

function resultados()
{
    print_h("INICIO");

    $mdbCargaPer = new MYMONGODB();
    $condCargaPer = [
        'activo'   => (int) 1
    ];

    $mdbCargaPer->buscar('control_carga_periodo', $condCargaPer);

    while ($doc = $mdbCargaPer->siguiente()) {
        $cartera = (string)$doc['cartera'] ?? 0;
        $periodo = (int)$doc['periodo'] ?? 0;
        $fecha   = (int)$doc['fecha']   ?? 0;
        // print_h("cartera ".$cartera." periodo ".$periodo." fecha ".$fecha);

        //Actualizar cbCreditos

        $mdbCbCred = new MYMONGODB();
         //$fechaAnt = 1770613200;
         //$fechaAnt = 1771390800;
         $fechaAnt = 1773205200;

        $criterioAccion = [ 
            'cre_fechaPeriodo' => $fechaAnt,
            'cre_carteraId' => $cartera,
            'cre_periodo'   => $periodo
        ];
        $nuevaData = [
            'cre_fechaPeriodo' => $fecha,
            'cre_actualizaFecha'=> 1,
            'cre_fechaPeriodoAnt'=> $fechaAnt
        ];

        //$mdbCbCred->buscar('cbCreditos', $criterioAccion);
        //print_h("cont cbCred ".$mdbCbCred->buscar('cbCreditos', $criterioAccion));

        $res = $mdbCargaPer->actualizar('cbCreditos', $criterioAccion, $nuevaData);

        print_h("Actualizó cartera {$cartera} - periodo {$periodo} - fecha {$fecha}");
        print_h("RESP. ".$res);
    }
    
}

?><?

    //_FIN_DE_ARCHIVO 
    ?>