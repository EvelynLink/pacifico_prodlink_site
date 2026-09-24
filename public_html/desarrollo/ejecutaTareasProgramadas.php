<?php
/*  Ejecuta scripts programados */
$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}

if ($origen == "") { //si es ejecutado por servicio
    if (!isset($_SERVER["argv"][0])) {
        //echo "ejecutaTareasProgramadas no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/desarrollo/ejecutaTareasProgramadas.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . "_configBasico.inc.php");
    require_once(BASEFOLDER . "comunes/classes/class.mymongodb.php");
    require_once(BASEFOLDER . "comunes/classes/class.mysqldb.php");
    require_once(BASEFOLDER . "functions/basic.php");
} else { //si es ejecutado por browser
    require_once("../comunes/top.inc.php");
}
$losDominios = explode(",",HOSTALIAS);
$baseUrl=$losDominios[0];
require_once "../desarrollo/classes/class.desProgramado.php";
$desProg=new desProgramado();

//estas líneas se utilizan para monitorear la ejecución automática de este script, por el servicio "programados"
$tareasProgramadas = BASEFOLDER . "desarrollo/TareasProgramadas.txt";
$fileIdTest = fopen($tareasProgramadas, "w");
fwrite($fileIdTest, time());
fclose($fileIdTest);
//fin líneas para monitoreo
$desProg->log("Empieza el proceso en " . $baseUrl);
$periodicidadesEjecucion = array(
    "cada6H" => array("periodoEjecucion" => "h", "cuantos" => 6),
    "cada3H" => array("periodoEjecucion" => "h", "cuantos" => 3),
    "cada2H" => array("periodoEjecucion" => "h", "cuantos" => 2),
    "cada1H" => array("periodoEjecucion" => "h", "cuantos" => 1),
    "cada30M" => array("periodoEjecucion" => "n", "cuantos" => 30),
    "cada15M" => array("periodoEjecucion" => "n", "cuantos" => 15),
    "cada5M" => array("periodoEjecucion" => "n", "cuantos" => 5),
    "cada1M" => array("periodoEjecucion" => "n", "cuantos" => 1),
);
$frecuenciasExito = array(
    "cadaMes" => array("periodo" => "m", "cuantos" => 1),
    "cadaSemana" => array("periodo" => "d", "cuantos" => 7),
    "cada24H" => array("periodo" => "h", "cuantos" => 24),
    "cada12H" => array("periodo" => "h", "cuantos" => 12),
    "cada6H" => array("periodo" => "h", "cuantos" => 6),
    "cada3H" => array("periodo" => "h", "cuantos" => 3),
    "cada2H" => array("periodo" => "h", "cuantos" => 2),
    "cada1H" => array("periodo" => "n", "cuantos" => 60),
    "cada30M" => array("periodo" => "n", "cuantos" => 30),
    "cada15M" => array("periodo" => "n", "cuantos" => 15),
);
//asumiendo q esto se ejecuta cada 5 minutos
$ahora = time();
require_once("../comunes/classes/sc_calendar.php");
$scCalendar = new sc_calendar($ahora, $ahora);
$scriptsAEjecutarse = [];
$advertencias = [];
$programados = $desProg->traerScriptsProgramados();
//tomo la hora actual para el análisis de todos los scripts

$horaActual = date('G', $ahora); //hora 24 sin ceros 
$minutoActual = date('i', $ahora);
if (substr($minutoActual, 0, 1) == '0') {
    $minutoActual = substr($minutoActual, 1, 1);  // minuto sin ceros iniciales
}
$informeDeRetrasos='';
foreach ($programados as $script) {
    //PRIMERO VALIDEMOS SI HAY BANDERA DE BLOQUEO EN REDIS...
    if ($desProg->getBandera($script["desFiles_archivo"]) > 0) { //hay bandera, no haga nada más y vaya al siguiente script programado
        //print_h("hay bloqueo en redis para " . $script["desFiles_archivo"].", salgo!");
        continue;
    }
    //inicializa variables de este script
    $archivo = BASEFOLDER . $script['desFiles_ruta'];
    $scriptUrl = 'https://'.$baseUrl.'/'.$script["desFiles_ruta"];
    
    foreach ($periodicidadesEjecucion as $perio => $datos) {
        //determinemos si se debe o no ejecutar este script en este momento
        if ($script["desProgramados_periodicidad"] == $perio) {
            $periodicidad = $script["desProgramados_periodicidad"];
            $horaDesde = $script["desProgramados_horaDesde"];
            $horaHasta = $script["desProgramados_horaHasta"];
            $horaArranca = $script["desProgramados_arrancaEjecucion"];
            $horaAcaba = $script["desProgramados_finalizaEjecucion"];
            $periodoEspera = $script["desProgramados_periodoEspera"];
            $cuantosEspera = $script["desProgramados_cuantosEspera"];
            $tipoEjecucion = $script["desProgramados_tipoEjecucion"];
            $frecuenciaExito = $script["desProgramados_frecuenciaExito"];
            if ($horaDesde != "" && !is_null($horaDesde)) {
                $data = explode(":", $horaDesde);
                $parteHoraDesde = $data[0];
                $minutoDesde = $data[1];
            } else {
                $parteHoraDesde = "";
                $minutoDesde = "";
            }
            if ($horaHasta != "" && !is_null($horaHasta)) {
                $data1 = explode(":", $horaHasta);
                $parteHoraHasta = $data1[0];
                $minutoHasta = $data1[1];
            } else {
                $parteHoraHasta = "";
                $minutoHasta = "";
            }
            $continuar2 = false;
            if (($horaArranca != 0 && $horaAcaba != 0) || ($horaArranca == 0 && $horaAcaba == 0)) { //ya terminó o aún no inicia
                if ($parteHoraDesde != "" && $parteHoraHasta != "" && $minutoDesde != "" && $minutoHasta != "") { //tiene correctamente definidas hora desde y hasta
                    //si la hora inicio es menor a la hora fin, ej: desde las 15h00 hasta las 20h00
                    if ($parteHoraDesde < $parteHoraHasta || ($parteHoraDesde == $parteHoraHasta && $minutoDesde < $minutoHasta)) {
                        if (($horaActual > $parteHoraDesde || ($horaActual == $parteHoraDesde && $minutoActual >= $minutoDesde)) &&
                                ($horaActual < $parteHoraHasta || ($horaActual == $parteHoraHasta && $minutoActual <= $minutoHasta))) {
                            //hora actual esta en el rango, es candidato
                            $continuar2 = true;
                        }
                    } else {
                        //si la hora inicio es mayor a la hora fin, ej: desde las 22h30 hasta las 05h00
                        //si hora actual es mayor o igual a hora inicio, ejecute, 
                        //porque por la condición de arriba, se sabe que el rango 
                        //de ejecución como mínimo va hasta las 12 de la noche, 
                        //por tanto cualquier hora posterior al inicio se debería ejecutar.
                        if ($horaActual > $parteHoraDesde || ($horaActual == $parteHoraDesde && $minutoActual >= $minutoDesde)) {
                            //porque hasta las 00 horas del día de hoy esto se cumple
                            $continuar2 = true; //es candidato
                        } else {
                            //si hora actual es menor a la hora inicio, ejecute solo si 
                            //es menor también a la hora 'hasta'
                            if ($horaActual < $parteHoraHasta || ($horaActual == $parteHoraHasta && $minutoActual <= $minutoHasta)) {
                                $continuar2 = true; //es candidato
                            }
                        }
                    }
                }

                if ($continuar2) {
                    if ($horaArranca != 0) { //tiene hora de inicio
                        if ($horaAcaba != 0) { //primero veamos si tiene hora fin (seguro tiene por la condición de arriba) porque ese sería el mejor criterio
                            //cuántos (días, horas, minutos) han pasado desde la última ejecución?
                            $haPasado = $scCalendar::DateDiff($datos["periodoEjecucion"], $horaAcaba, $ahora);
                        } else {//cuántos (días, horas, minutos) han pasado desde la última ejecución?
                            $haPasado = $scCalendar::DateDiff($datos["periodoEjecucion"], $horaArranca, $ahora);
                        }
                    } else {
                        $haPasado = 0;
                    }
                    //sss. aquí debería ser desde la hora de finalización no de arranque (da lo mismo porque según condición de arriba o tiene ambos [arranca y termina] o no tiene ninguna)
                    if ($horaAcaba == 0 || ($horaAcaba != 0 && $haPasado >= $datos["cuantos"])) { //han pasado más de los que dice la periodicidad (ej: cada3H y han pasado 3.5 horas)
                        //YA LE TOCA UN NUEVO INTENTO, PERO! VEAMOS SI HAY FECHA MÍNIMA EN REDIS.... no porque ya esto se hace arriba apenas se entra en el bucle
                        //si luego de validar las anteriores condiciones y además ó
                        //si nunca se ha ejecutado lo ejecuto por primera vez
                        //guardo la cabecera del resumen de ejecucion, sirve tanto para ETLs como para PHPs
                        $desProg = new desProgramado();
                        $desProg->initFromDB($script["desProgramados_id"], "noFiltrar");
                        if ($desProg->get("tipo") == "kjb") { //sólo el job se ejecuta verdad??
                            $desProg->ejecutaKJB($archivo,$scriptUrl);
                        } else {
                            if (file_exists($archivo) && $tipoEjecucion == "curl") {
                                $desProg->ejecutaCURL($archivo,$scriptUrl);
                            } else {
                                $detalle1 = $lang["Intento de ejecutar un script programado inexistente"] . ":<br>" . $scriptUrl;
                                $desProg->log($detalle1);
                            }
                        }
                    }
                }
            } elseif ($horaArranca != 0 && $horaAcaba == 0) {
                //si aún no acaba luego del período de retraso maximo, envio una 
                //notificacion de error o advertencia
                // alerta por este estancamiento
                if ($periodoEspera == "" || $cuantosEspera == "") {
                    $periodoEspera = "h";
                    $cuantosEspera = 12;
                }
                $haPasado = $scCalendar::DateDiff($periodoEspera, $horaArranca, $ahora);
                if ($haPasado >= $cuantosEspera) {
                    $informeDeRetrasos .= $baseUrl.'/'.$script["desFiles_ruta"];
                    $desProg->actualizarFinalizaEjecucion($script["desProgramados_id"], "S"); //Lo pone como finalizado manualmente
                }
            }
        }
    }
}
//mantengo el archivo de alerta de no ejecucion
try{
    if(!file_exists('/home/AlertaProgramadas')){
        mkdir('/home/AlertaProgramadas');
    }
    file_put_contents('/home/AlertaProgramadas/'.$baseUrl,$informeDeRetrasos);
}catch(Exception $e){
    trigger_error('No se pudo crear el archivo '.'/home/AlertaProgramadas/'.$baseUrl,$informeDeRetrasos);
}
$desProg->log("Termino hasta actualizar fecha finalizacion");
?>
<?php //_FIN_DE_ARCHIVO ?>