<?
require_once '../../_config.inc.php';
ini_set("max_execution_time",100000000);
ini_set('memory_limit', '3000M');
$mdb=new MYMONGODB();
$mdb2=new MYMONGODB();
$mdb3=new MYMONGODB();


exit;
//--------------------------------------------------------------------
//COMPARA cbCreditos con cubo de asignaciones y borra las asignaciones que no deberían existir
$cuantasEnMongo=$mdb->buscar('cbCreditos',['cre_carteraId'=>'2120','cre_fechaPeriodo'=>1772600400,'cre_fechaCarga'=>['$gte'=>1772341200]],[],['cre_factura'=>1]);
print_h('$cuantasEnMongo cbCreditos '.$cuantasEnMongo);
$facturas=[];
while($doc=$mdb->siguiente()){
    $facturas[]=$doc['cre_factura'];
}
//busque facturas duplicadas en una cartera
$cuantasEnMongo=$mdb2->buscar('cuAsignacionesGestionAP',["cubAG_carteraId"=>"2120","cubAG_fechaInicio"=>1772600400],[],['cubAG_numFactura'=>1]);
print_h('$cuantasEnMongo cubo '.$cuantasEnMongo);
while($doc2=$mdb2->siguiente()){
    if(!in_array($doc2['cubAG_numFactura'],$facturas)){
        print_h($doc2['cubAG_numFactura']);
        //$mdb3->borrar('cuAsignacionesGestionAP',['_id'=>$doc2['_id']]);
    }else{
        $facturas = array_diff($facturas, [$doc2['cubAG_numFactura']]);
    }
}
//inserte en el cubo todos los cbCreditos que no estén
print_h($facturas);
require_once '../cubos/plugins/cu.asignacionesGestion.class.php';
$cub = new cuPGasignacionesGestion();
foreach($facturas as $ff){
    //$cub->onAdd(['cbCreditos' . ':' . $ff]);
}
exit;
$facturas=[];
$facturasRepetidas=[];
while($doc=$mdb->siguiente()){
    if(in_array($doc['cubAG_numFactura'],$facturas)){
        $facturasRepetidas[]=$doc['cubAG_numFactura'];
        $mdb2->actualizar('cuAsignacionesGestionAP',['_id'=>$doc['_id']],['cubAG_fechaPeriodo'=>1770181200,'cubAG_ctrId'=>'69a0c3119e1dfc3dc517324e','cubAG_fechaInicio'=>1770181200,'cubAG_fechaFin'=>1772600400]);
        
    }else{
        $facturas[]=$doc['cubAG_numFactura'];
    }
}
sort($facturas);
$facturasUnicas=array_unique($facturas);
print_h($facturasRepetidas);




print_h($facturas);
print_h('count facturasRepetidas ' . count($facturasRepetidas));
print_h('count facturasUnicas ' . count($facturasUnicas));
print_h('count facturas ' . count($facturas));








exit;

//----------------------------------------------------------------------------------------
//BORRA REGISTROS DUPLICADOS EN cbCreditos
$cuantasEnMongo=$mdb->buscar('cbCreditos',['cre_carteraId'=>'2120','cre_fechaPeriodo'=>1772600400,'cre_fechaCarga'=>['$gte'=>1772341200]],[],['_id'=>1]);
print_h('$cuantasEnMongo cbCreditos '.$cuantasEnMongo);
$creditos=[];
while($doc=$mdb->siguiente()){
    if(!isset($creditos[$doc['cre_factura']])){
        $creditos[$doc['cre_factura']]=$doc;
    }else{
        //elimine duplicado
        $mdb3->borrar('cbCreditos',['_id'=>$doc['_id']]);
        print_h($doc['cre_factura']);
    }
}
exit;

//-------------------------------------------------------------
$clavePrivada=file_get_contents("/home/pacifico/gpg/LinkDesign_0x977F0C2C_SECRET.asc");
$clavePublica=file_get_contents("/home/pacifico/gpg/LINKDESING.BDP.PGPKEY.PROD.CLAVE.asc");

require_once '../comunes/plugins/class.pgGPG.php';
$gpg=new pgGPG();

require_once '../comunes/plugins/class.pgConexionSFTP.php';
//Conectarse con SFTP
$server="emisores.sfbp.ec";
$puerto="22";
$usuario="LINKDESI";
$password="LINK75DESI";
$sftp=new pgConexionSFTP($server,$puerto,$usuario,$password);
print_h($sftp);
$ultimosMesDias=[];
for($i=0;$i<3;$i++){
    $timestamp = time()-$i*24*60*60;
    $ultimosMesDias[] = str_pad((string)(date('md',$timestamp)),4,"0", STR_PAD_LEFT);
}
print_h($ultimosMesDias);
$ultimosArchivos=[];
foreach($ultimosMesDias as $umd){
    $ultimosArchivos[]="/linkdesi/RECUPERACION_CARTERA-LINKDESIGN_TC_".$umd.".XLSX.PGP";
}
print_h($ultimosArchivos);
foreach($ultimosArchivos as $arch){
    print_h($arch);
    $archivoADescifrar=BASEFOLDER."cache/".md5($arch);
    if($sftp->descargar_archivo($arch,$archivoADescifrar)){
        print_h(file_get_contents($archivoADescifrar));
        //descifre el archivo
        $archivoDestino=BASEFOLDER."cache/".md5(md5($arch));
        print_h('archivoDestino '.$archivoDestino);
        $gpg->descifrarGNUPG($clavePrivada,$archivoADescifrar,$archivoDestino);
        print_h(file_get_contents($archivoDestino));
        //lea el archivo con Excel
        
        //busque si tiene fechas
//        if(tieneFechas()){ //tiene fechas
//            $datosDelArchivoDeFechas="blablabla";
//            break;
//        }
    }else{
        print_h("No se pudo copiar el archivo remoto");
    }
}
// para listar sftp
//print "<pre>";print_r($sftp->listar_directorio("/linkdesi"));
//if($sftp->descargar_archivo($archivoRemoto,$archivoLocal)){
//    print_h(file_get_contents($archivoLocal));
//}else{
//    print_h("No se pudo copiar el archivo remoto");
//}
exit;




/* para subir un archivo */
//$sftp->subir_archivo("/var/www/desarrollo/urbano3/prueba.pdf","/var/www/urbanowd/urbano3/prueba6.pdf");

/* para crear un directorio */
//$sftp->crear_directorio("/var/www/urbanowd/urbano3/habilitantes/b".date("Ymd"));

/* para subir un directorio */
//$sftp->subir_directorio("/var/www/desarrollo/urbano3/habilitantes/20140703","/var/www/urbanowd/urbano3/habilitantes");

/* para eliminar un archivo */
//$sftp->elimina_archivo_remoto("/var/www/urbanowd/urbano3/habilitantes/20140703/CED-98H3250637665310239990181.tif");

/* para eliminar todo un directorio (pendiente no elimina) */
//$sftp->elimina_directorio_remoto("/var/www/urbanowd/urbano3/habilitantes/20140703/20140704/20140702");

//Descargar archivo CO1 y archivo con 

//Crear archivo de respuesta CO1 para Banco del Pacífico
// 1.Buscar todas las gestiones no enviadas en el cubo de gestiones
require_once '../cubos/plugins/cu.gestionCobranza.class.php';
$cub=new cuPGgestionCobranza();
$gestionesNoEnviadas = $cub->obtenerGestionesNoEnviadas($datosDelArchivoDeFechas);
$gestionesNoEnviadas[] = $cub->obtenerLineaTotales(count($gestionesNoEnviadas));
foreach($gestionesNoEnviadas as $gne){
    echo $gne;
}
exit;






/* Copiar registros desde cbEnvioMails al cubo de gestiones si no existen */

//require_once '../cubos/plugins/cu.gestionCobranza.class.php';
//$cub=new cuPGgestionCobranza();
//$carteras=[
//    2120,
//    2121,
//    2122,
//];
//foreach($carteras as $carteraId){
//    print_h("CARTERA ".$carteraId);    
//    $mdb->buscar('control_carga_periodo',['activo'=>1,'cartera'=>$carteraId]);
//    if($doc=$mdb->siguiente()){
//        $fechaInicio = $doc['fecha'];
//        $fechaFin = $doc['fechaFin'];
//    }
//    //busque todas las gestiones en el cubo de gestiones para esta cartera y fechas
//    $cuantas=$mdb->buscar('cuGestionCobranza',[
//        'cubGC_carteraId'=>strval($carteraId),
//        'cubGC_fechaGestion'=>['$gte'=>$fechaInicio],
//    ]);
//    $encubados=[];
//    while($doc=$mdb->siguiente()){
//        $encubados[($doc['cubGC_avId'])->__toString()]=$doc;
//    }
//    print_h('encubados '.count($encubados));
//
//
//
//    $canales=[
//        'EMAILS'=>[
//            'tabla'=>'cbEnvioMails',
//            'campoCartera'=>'cem_susCarteraId',
//            'campoFecha'=>'cem_susFechaEnvio',
//        ],
//        'WHATSAPP'=>[
//            'tabla'=>'avProgramadasWhatsApp',
//            'campoCartera'=>'ws_carteraId',
//            'campoFecha'=>'ws_fecha',
//        ],
//        'AGENTES VIRTUALES'=>[
//            'tabla'=>'avProgramadas',
//            'campoCartera'=>'av_carteraId',
//            'campoFecha'=>'av_fechaActualizaLlamada',
//        ],
//    ];
//
//
//    foreach($canales as $key=>$val){
//        print_h('IGUALO '.$key);
//        //busque todas las gestiones de este canal para esta cartera y fechas
//        $cuantas=$mdb->buscar($val['tabla'],[
//            $val['campoCartera']=>$carteraId,
//            $val['campoFecha']=>['$gte'=>$fechaInicio]
//        ]);
//        $gestiones=[];
//        while($doc=$mdb->siguiente()){
//            $gestiones[($doc['_id'])->__toString()]=$doc;
//        }
//        print_h(count($gestiones));
//        $encontrados=0;
//        $noencontrados=0;
//        foreach($gestiones as $idG=>$ges){
//            if(isset($encubados[$idG])){
//                $encontrados++;
//            }else{
//                $noencontrados++;
//                trigger_error(".");
//                //print_h($ges);
//                $cub->onAdd([$val['tabla'].':'.$idG]);
//                trigger_error(".");
//            }
//        }
//        print_h('encontrados '.$encontrados);
//        print_h('noencontrados '.$noencontrados);
//        print_h('');
//        print_h('');
//        print_h('');
//        print_h('');
//    }
//}
//
//exit;
/*
//normalizar gestiones segun control_carga_periodo
$mdb->buscar('control_carga_periodo',['activo'=>1]);
while($doc=$mdb->siguiente()){
    print_h($doc);
    $fechaPeriodo=$doc['fecha'];
    $fechaInicio=$doc['fecha'];
    $fechaFin=$doc['fechaFin'];
    $cuantos=$mdb2->buscar('cuGestionCobranza',["cubGC_carteraId"=>(string)$doc['cartera'],"cubGC_ciclo"=>$doc['periodo'],"cubGC_fechaGestion"=>['$gte'=>$fechaPeriodo]]);
    print_h($cuantos);
    while($doc2=$mdb2->siguiente()){
        //print_h($doc2);
//        print_h([
//            "cubGC_fechaPeriodo"=>(int)$fechaPeriodo,
//            "cubGC_ctrId"=>$doc["_id"],
//            "cubGC_fechaInicio"=>(int)$fechaInicio,
//            "cubGC_fechaFin"=>(int)$fechaFin
//        ]);
        $mdb3->actualizar('cuGestionCobranza',['_id'=>$doc2["_id"]],[
            "cubGC_fechaPeriodo"=>(int)$fechaPeriodo,
            "cubGC_ctrId"=>$doc["_id"],
            "cubGC_fechaInicio"=>(int)$fechaInicio,
            "cubGC_fechaFin"=>(int)$fechaFin
        ]);
    }
}
*/

/*
//normalizar cbCreditos segun control_carga_periodo
$mdb->buscar('control_carga_periodo',['activo'=>1]);
while($doc=$mdb->siguiente()){
    $fechaPeriodo=$doc['fecha'];
    $fechaInicio=$doc['fecha'];
    $fechaFin=$doc['fechaFin'];
    $cuantos=$mdb2->buscar('cbCreditos',["cre_carteraId"=>(string)$doc['cartera'],"cre_periodo"=>$doc['periodo'],"cre_fechaPeriodo"=>['$gte'=>$fechaPeriodo]]);
    print_h($cuantos);
    $cuenta=0;
    while($doc2=$mdb2->siguiente()){
        print_h(++$cuenta);
        $mdb3->actualizar('cbCreditos',['_id'=>$doc2["_id"]],[
            "cre_fechaPeriodo"=>(int)$fechaPeriodo
        ]);
    }
}
 */

/*
//mover registros de cubo temporal a cubo definitivo
$mdb->buscar('control_carga_periodo',['activo'=>1]);
$suma=0;
while($doc=$mdb->siguiente()){
    $esteId=$doc["_id"]->__toString();
    print_h($esteId);
    //busque todas las asignaciones con este _id
    $cuantos=$mdb2->buscar('cuAsignacionesGestion',['cubAG_ctrId'=>$mdb2->String2MongoId($esteId)]);
    print_h($cuantos);
    $suma+=$cuantos;
    while($doc2=$mdb2->siguiente()){
        $mdb3->guardar('cuAsignacionesGestionAP',$doc2);
        echo ".";
    }
}
print_h("suma ".$suma);
*/
echo "EJECUCION_COMPLETA";
?><?

//_FIN_DE_ARCHIVO ?>