<?php

/** API de interacción con multiples clases de Boletines para ofrecer Servicios Integrados
 *  @package Boletines
 *  @version v1.0
 *  @author Mikhael Portela (mikhael [punto] portela [arroba] espaciolink [punto] com)
 *  @cambios
 *  Fecha        Cambio                                      Autor
 *  ---------------------------------------------------------------------------
 *  2017-07-20   Version inicial del archivo                 Mikhael Portela
 *  ---------------------------------------------------------------------------
 */
// <editor-fold defaultstate="collapsed" desc="Requires from FRAMEWORK">
require_once("../boletines/classes/class.boCategoria.php");
require_once("../boletines/classes/class.boJerarquia.php");
require_once("../boletines/classes/class.boCorreoxJerarquia.php");
require_once("../boletines/classes/class.boCorreo.php");
require_once("../comunes/classes/class.API.php");
// </editor-fold>

class serviciosIntegradosAPI extends API {

    function __construct() { }

    //__Descripción:__ Función que permite enviar un boletin preestablecido a un grupo de suscriptores para mailing
    //__Inputs:__ $estructura:string Estructrura de categoria y jerarquias con la forma: GRUPO|CATEGORIA1|CATEGORIA2|BOLETIN (CNT|2017|Julio|Tramo30-60) donde el nombre de ultima jerarquia debe coincidir con nombre de boletin
    //            $estricto:boolean TRUE: Si se desea que la estructura $estructura deba existir previamente (omite su creacion en caso de inexistencia) | FALSE: Si desea que se creen categoria y jerarquias indicadas en $estructura en caso de no existir
    //            $listado:array Lista de receptores de mails con formato: [0] => ["email" => $correo, "nombre" => $nombres, "identificacion" => $identificacion, "fechanacimientounix" => $fechaNacimiento, "telefono" => $telefono, "celular" => $celular, "empresa"=>$empresa, "direccion"=>$direccion]
    //__Outputs:__ $ans:array Array con dos posiciones op y data, OP=>TRUE: indica procedente en el registro efectivo para envio | OP=>FALSE: no se completo el registro, DATA: mismo $listado con key "suscrito" adicional en cada elemento para indica suscripcion exitosa
    function enviarCorreoIntegrado($estructura, $estricto, $listado) {
        $boCorreo = new boCorreo();
        $boCorreo_resultado = $boCorreo->enviarCorreoIntegrado($estructura, $estricto, $listado);
        return $boCorreo_resultado;
    }
    
    //__Descripción:__ Función que permite enviar un boletin preestablecido a un grupo de suscriptores para mailing
    //__Inputs:__ $estructura:string Estructrura de categoria y jerarquias con la forma: GRUPO|CATEGORIA1|CATEGORIA2|BOLETIN (CNT|2017|Julio|Tramo30-60) donde el nombre de ultima jerarquia debe coincidir con nombre de boletin
    //            $estricto:boolean TRUE: Si se desea que la estructura $estructura deba existir previamente (omite su creacion en caso de inexistencia) | FALSE: Si desea que se creen categoria y jerarquias indicadas en $estructura en caso de no existir
    //            $listado:array Lista de receptores de mails con formato: [0] => ["email" => $correo, "nombre" => $nombres, "identificacion" => $identificacion, "fechanacimientounix" => $fechaNacimiento, "telefono" => $telefono, "celular" => $celular, "empresa"=>$empresa, "direccion"=>$direccion]
    //            $url:string dirección Url de la plantilla a usar para el envío del boletín a los usuarios de $listado
    //__Outputs:__ $ans:array Array con dos posiciones op y data, OP=>TRUE: indica procedente en el registro efectivo para envio | OP=>FALSE: no se completo el registro, DATA: mismo $listado con key "suscrito" adicional en cada elemento para indica suscripcion exitosa
    function enviarCorreoIntegradoUrl($estructura, $estricto, $listado,$url) {
        $boCorreo = new boCorreo();
        $boCorreo_resultado = $boCorreo->enviarCorreoIntegradoUrl($estructura, $estricto, $listado,$url);
        return $boCorreo_resultado;
    }
}
?>
<? //_FIN_DE_ARCHIVO ?>