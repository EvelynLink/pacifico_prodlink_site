<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/**
 * Description of class
 *
 * @author Sandra Galarza
 */

require_once("../comunes/classes/class.mymongodb.php");

ini_set("max_execution_time", "5000");
ini_set('memory_limit', '1000M');

class logParamCredito extends Clase {

  function __construct() {
    ini_set('track_errors', 1);
  }

  function insertarLog($usUsuario_id,$usUsuario_nombre,$controlador,$nombreDefinicion, $tipoTransaccion,$datos) {

    $coleccion = "fcLogParamCredito";    
    $dbMongo = new MYMONGODB();
    //$result = array();
    try {
      //Array to String
//      $str = serialize($arrayMongo);
      
      //$str= $this->encodeTemporal($str);      

      //String to array
//      $arrayMongo = unserialize($str);
      $fecha = time() . substr(microtime(),2,8);
      $fechaHumana = date('d-m-Y h:i:s a', time());
      $datosLog=array();
      $datosLog=array('usUsuario_id'=>$usUsuario_id,
                   'usUsuario_Nombre'=>$usUsuario_nombre,
                   'controlador'=>$controlador,
                   'nombreDefinicion'=> $nombreDefinicion,
                   'tipoTransaccion'=>$tipoTransaccion,
                   'datos'=>$datos,
                   'fecha'=>$fecha,
                   'fechaHumana'=>$fechaHumana
            );
                   
      $resultadoMongo = $dbMongo->guardar($coleccion, $datosLog);
      if (!isset($resultadoMongo)) {
          trigger_error("No se pudo obtener el id al guardar Comprobante en LogParamCredito", E_USER_NOTICE);
      }else{
          //return=$resultadoMongo;
      }
    } catch (Exception $exc) {
      trigger_error("Existio un problema al guardar en la coleccion Comprobante en LogParamCredito" . $exc->getMessage(), E_USER_NOTICE);
    }
  }

  

}

?><? //_FIN_DE_ARCHIVO ?>


