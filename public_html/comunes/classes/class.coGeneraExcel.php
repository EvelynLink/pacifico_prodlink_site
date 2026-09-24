<?php
//__Descripcion__: Objeto que permite la generación de un array en archivo XLSX
//__nombreArchivo__: nombre con el cual el archivo final se mostrará en pantalla
//__plantilla__: array de información

//require_once("../PHPExcel/Classes/PHPExcel.php");
require_once '../PHPSpreadsheet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class coGeneraExcel {
    
    protected $nomenclaturaMax=3; //indica cuantas series de columnas genera A..Z,AA..ZZ,AAA..ZZZ
    protected $nomenclatura=array();
    protected $hojas=array();
    protected $filas=array();
    protected $estilos=array();
    protected $datos=array();
    protected $tabs=array();
    protected $merge=array();
    protected $cellFont=array();
    protected $formatos=array();
    protected $nombreArchivo;
    protected $ubicacion;
    protected $widthColumn=18; //ancho fijo de columnas
    protected $estilosValidos=array('borders','fill','font','alignment');
    protected $salidaDirecta=false;

    public function __construct() {
        //inicializa guias
        if(count($this->nomenclatura) == 0){
            $guia0 = array();
            for($i=65;$i<=90;$i++){
                $letra = chr($i);
                $guia0[$letra] = $letra;
            }
            //creo guía de columnas primera ronda
            foreach ($guia0 as $letra){
                $this->nomenclatura[]=$letra;
            }
            //creo guía de columnas segunda ronda
            $guia1=$guia0; $i=1;
            foreach ($guia0 as $letra0){
                foreach ($guia1 as $letra1){
                    $this->nomenclatura[]=$letra0.$letra1;
                }
                $i++;
                if($i==$this->nomenclaturaMax){
                    break;
                }
            }
        }
    }
    
    //__Descripcion__: Función que permite pedir a la clase que exporte directo el archivo al navegador
    //__Input__: boolean $directo, true exporta directo, false guarda a archivo
    //__Output__: 
    public function setSalidaDirecta($directo){
        if($directo==true){
            $this->salidaDirecta = true;
        }else{
            $this->salidaDirecta = false;
        }
    }
    
    //__Descripcion__: Función que permite definir el nombre del archivo limpiando caracteres no permitidos
    //__Input__: $nombre:string nombre del archivo
    //__Output__:
    public function setNombreArchivo($nombre) {
        if ($nombre != "") {
            $search = array("á","é","í","ó","ú","ñ","Á","É","Í","Ó","Ú","Ñ"," ","#","[","]","|","!","$","%","&","/","(",")","=","?","¡","´","+","*","{","}","`");
            $replace = array("a","e","i","o","u","n","A","E","I","O","U","N","","","","","","","","","","","","","","","","","","","","","");
            $nombre = str_replace($search, $replace, $nombre);
            $this->nombreArchivo = $nombre.".xlsx";
        }
    }
    
    //__Descripcion__: Función que permite definir la ruta física donde se colocarán los archivos XLSX generados
    //__Input__: $ubicacion:string la direcció no deberá contener "/" ni al inicio, ni al final Ej: smasivo/reclamos/cliente
    //__Output__: 
    public function setUbicacion($ubicacion){
        if(!empty($ubicacion)){
            $this->ubicacion = rtrim($ubicacion,"/");
        }
    }
    
    //__Descripcion__: Función que permite definir el ancho de las columnas del archivo
    //__Input__: $ubicacion:string la direcció no deberá contener "/" ni al inicio, ni al final Ej: smasivo/reclamos/cliente
    //__Output__: 
    public function setAnchoColumna($ancho){
        if(!empty($ancho)){
            $this->widthColumn = $ancho;
        }else{
            $this->widthColumn = 18;
        }
    }
    
    //__Descripción:__ Función que permite agregar una hoja de excel
    //__Inputs:__ $orden:int empezando desde 0
    //            $nombre:string nombre de la hoja
    //__Outputs:__ $result:string/boolean string para error
    public function addHoja($orden,$nombre){
        if(!empty($nombre)){
            $this->hojas[$nombre]=$orden;
            return true;
        }else{
            return "No se pudo registrar nueva hoja";
        }
    }
    
    //__Descripción:__ Función que permite generar los tabs de la hoja de cálculo en el orden indicado
    //__Inputs:__ 
    //__Outputs:__ $result:string/boolean string para error
    private function getTabs(){
        $hojasOrden = $hojasFinales = array();
        if(count($this->hojas)>0){
            foreach ($this->hojas as $hoja=>$orden){
                $hojasOrden[$orden]=$hoja;
            }
            ksort($hojasOrden);
            foreach ($hojasOrden as $hoja){
                $hojasFinales[]=$hoja;
            }
            $this->tabs = $hojasFinales;
            return true;
        }else{
            return "No se pudo ordenar hojas del archivo";
        }
    }
    
    //__Descripción:__ Función que permite agregar una fila a la hoja de cálculo
    //__Inputs:__ $hojaNombre:string nombre de la hoja en la que se insertará
    //            $fila:array datos a insertar
    //            [estilos] =>$font,$background,$borders,$alinear (usan funciones de la clase para armar array),$merge (puede ser un entero o un array de enteros), $cellFont (etilos de fuente para celdas)
    //__Outputs:__ $result:string/boolean string para error
    public function addFila($hojaNombre,$fila,$font=array(),$background=array(),$borders=array(),$alinear=array(),$merge="",$cellFont=array(),$cellFormat=array()){
        if(empty($hojaNombre)){
            return "Hoja inválida '".$hojaNombre."'";
        }
        if(!isset($this->hojas[$hojaNombre])){
            return "Hoja desconocida '".$hojaNombre."'";
        }
        $filaExcel = $estiloFilaExcel = $nomenclarutasExcel = array();
        $mergeExcel = "";
        //tiene columnas que agregar?
        $columnas=count($fila);
        if($columnas>0){
            $guiaColumna=0;
            foreach ($fila as $valor){
                if(isset($this->nomenclatura[$guiaColumna])){
                    $campo=$this->nomenclatura[$guiaColumna];
                    $nomenclarutasExcel[]=$campo;
                    $filaExcel[$campo]= utf8_2_encode($valor);
                    $guiaColumna++;
                }else{
                    trigger_error("coGeneraExcel :: addFila()-> Superó el límite de columnas a generar");
                    break;
                }
            }
            //hay merge?
            if(!empty($merge)){
                $mergeExcel = $this->getFilaMerge($merge);
            }
            //genero estilos de fila
            if(!isset($font['font'])){
                $font = $this->setFilaEstiloFuente();
            }
            foreach ($font as $key=>$estilo){
                if(in_array($key, $this->estilosValidos)){
                    $estiloFilaExcel[$key]=$estilo;
                }
            }
            foreach ($background as $key=>$estilo){
                if(in_array($key, $this->estilosValidos)){
                    $estiloFilaExcel[$key]=$estilo;
                }
            }
            foreach ($borders as $key=>$estilo){
                if(in_array($key, $this->estilosValidos)){
                    $estiloFilaExcel[$key]=$estilo;
                }
            }
            foreach ($alinear as $key=>$estilo){
                if(in_array($key, $this->estilosValidos)){
                    $estiloFilaExcel[$key]=$estilo;
                }
            }
        }
        if(count($filaExcel)>0){
            $this->filas[$hojaNombre][] = $filaExcel;
            $this->estilos[$hojaNombre][] = $estiloFilaExcel;
            $this->merge[$hojaNombre][] = $mergeExcel;
            $this->cellFont[$hojaNombre][] = $cellFont;
            $this->formatos[$hojaNombre][] = $cellFormat;
        }
        return true;
    }
    
    //__Descripción:__ Función que permite obtener los rangos en los cuales se realiza el merge de una fila
    //__Inputs:__ $merge:int/array configuración solicitada
    //            $nomenclarutasExcel:array columnas usadas por la fila
    //__Outputs:__ $result:array lista de rangos de merge
    private function getFilaMerge($merge){
        $mergeExcel = array();
        if(is_array($merge)){
            $mergeLimpio = array();
            foreach ($merge as $m){
                if(is_array($m)){
                    foreach ($m as $n=>$c){
                        $n = expect_integer($n);
                        if($n>0){
                            $mergeLimpio[]=array("m"=>$n,"c"=>$c);
                        }
                    }
                }else{
                    $m = expect_integer($m);
                    if($m>0){
                        $mergeLimpio[]=array("m"=>$m,"c"=>"");
                    }
                }
            }
            $cuantosMerge = count($mergeLimpio);
            if($cuantosMerge>0){
                $mergePos = $mergePosLimpio = array();
                $i=0;
                foreach ($mergeLimpio as $mr){
                    $m=$mr["m"];
                    $c=$mr["c"];
                    $ini=$i;
                    for($j=0;$j<$m;$j++){
                        $mergePos[$i]=$i;
                        $i++;
                    }
                    $fin=$i-1;
                    $col1 = isset($this->nomenclatura[$ini]) ? $this->nomenclatura[$ini] : "";
                    $col2 = isset($this->nomenclatura[$fin]) ? $this->nomenclatura[$fin] : "";
                    if(!empty($col1) && !empty($col2)){
                        $mergeExcel[] = array("ini"=>$col1,"fin"=>$col2,"color"=>$c);
                    }
                }
            }
        }
        return $mergeExcel;
    }
    
    //__Descripción:__ Función que permite obtener el array de configuración de fuente según los parámetros enviados
    //__Inputs:__ $nombre:string nombre del tipo de letra
    //            $tamaño:string tamaño de la letra
    //            $negrita:boolean indica si la letra llevará negrita
    //            $colorRgb:string color en formato RGB sin #
    //            $cursiva:boolean indica si la letra llevará cursiva
    //__Outputs:__ $result:array configuración
    public function setFilaEstiloFuente($nombre="Calibri",$tamaño="11",$negrita=false,$colorRgb="000000",$cursiva=false){
        $style=array();
        $style['font'] = array(
            'bold'  => $negrita,
            'color' => array('rgb' => str_replace("#", "", $colorRgb)),
            'size'  => $tamaño,
            'name'  => $nombre, //'Verdana',
            'italic' => $cursiva
        );
        return $style;
    }
    
    //__Descripción:__ Función que permite obtener el array de configuración del estilo de fondo de la fila
    //__Inputs:__ $tipo:string tipo de fondo ['none','solid','linear','path','darkDown','darkGray','darkGrid','darkHorizontal','darkTrellis','darkUp','darkVertical','gray0625','gray125','lightDown','lightGray','lightGrid,'lightHorizontal','lightTrellis','lightUp,'lightVertical','mediumGray']
    //            $colorRgb:string color en formato RGB sin #
    //__Outputs:__ $result:array configuración
    public function setFilaEstiloFondo($tipo="",$colorRgb=""){
        $style=array();
        if(!empty($tipo) && !empty($colorRgb)){
            switch ($tipo){
                case "solid": $tipo = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID; break;
                default: $tipo = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID; break;
            }
            $style['fill'] = array(
                'type'  => $tipo,
                'color' => array('rgb' => str_replace("#", "", $colorRgb))
            );
        }
        return $style;
    }
    
    //__Descripción:__ Función que permite obtener el array de configuración de los bordes de las celdas de la fila
    //__Inputs:__ $colorRgb:string color en formato RGB sin #
    //            $left:boolean borde izquierdo
    //            $top:boolean borde arriba
    //            $right:boolean borde derecho
    //            $bottom:boolean borde bajo
    //            $grosor:string tipo de borde ['none','dashDot','dashDotDot','dashed','dotted','double','hair','medium','mediumDashDot','mediumDashDotDot','mediumDashed','slantDashDot','thick','thin']
    //__Outputs:__ $result:array configuración
    public function setFilaEstiloBordes($colorRgb="",$left=true,$top=true,$right=true,$bottom=true,$grosor='thin'){
        $style=array();
        $colorRgb = str_replace("#", "", $colorRgb);
        if(!empty($colorRgb)){
            if($left && $top && $right && $bottom){
                $bordes = array("allborders");
            }else{
                $bordes = array(
                    ($left ? "left" : ""),
                    ($top ? "top" : ""),
                    ($right ? "right" : ""),
                    ($bottom ? "bottom" : "")
                );
            }
            foreach ($bordes as $borde){
                if(!empty($borde)){
                    $style['borders'][$borde] = array(
                        'style' => $grosor,
                        'color' => array( 'rgb' =>$colorRgb )
                    );
                }
            }
        }
        return $style;
    }
    
    //__Descripción:__ Función que permite obtener el array de configuración de alineación de las celdas de la fila
    //__Inputs:__ $horizontal:string tipo de alineación horizontal ['general','left','right','center','centerContinuous','justify']
    //            $vertical:string tipo de alineación vertical ['bottom','top','center','justify']
    //__Outputs:__ $result:array configuración
    public function setFilaEstiloAlineacion($horizontal="left",$vertical="center"){
        $style=array();
        if(!empty($horizontal) && !empty($vertical)){
            $style['alignment'] = array(
                'horizontal'  => $horizontal,
                'vertical' => $vertical
            );
        }
        return $style;
    }
    
    //__Descripción:__ Función que permite obtener el array de configuración de estilos para celdas
    //__Inputs:__ $nombre:string nombre del tipo de letra
    //            $tamaño:string tamaño de la letra
    //            $negrita:boolean indica si la letra llevará negrita
    //            $colorRgb:string color en formato RGB sin #
    //            $tipoFondo:string tipo de fondo ['none','solid','linear','path','darkDown','darkGray','darkGrid','darkHorizontal','darkTrellis','darkUp','darkVertical','gray0625','gray125','lightDown','lightGray','lightGrid,'lightHorizontal','lightTrellis','lightUp,'lightVertical','mediumGray']
    //            $colorFondoRgb:string color en formato RGB sin #
    //            $colorBordeRgb:string color en formato RGB sin #
    //            $bordeGrosor:string tipo de borde ['none','dashDot','dashDotDot','dashed','dotted','double','hair','medium','mediumDashDot','mediumDashDotDot','mediumDashed','slantDashDot','thick','thin']
    //__Outputs:__ $result:array configuración
    public function setCeldaEstilos($nombre="Calibri",$tamaño="11",$negrita=false,$colorRgb="000000",
                                    $tipoFondo="",$colorFondoRgb="",
                                    $bordeGrosor="",$colorBordeRgb=""){
        $style=array();
        if(!empty($tipoFondo) && !empty($colorFondoRgb)){
            $style['background']=array('type'=>$tipoFondo,
                                       'color' =>array('rgb' =>str_replace("#", "", $colorFondoRgb) )
                                       );
        }
        if($nombre <> "Calibri" || $tamaño <> "11" || $negrita <> false || $colorRgb <> "000000"){
            $style['font']=array('bold'=>$negrita,
                                 'name'=>$nombre,
                                 'size'=>$tamaño,
                                 'color'=>array('rgb' =>str_replace("#", "", $colorRgb) )
                                );
        }
        if(!empty($colorBordeRgb) && !empty($bordeGrosor)){
            $style['border']=array(
                                    'borders' => array(
                                        'allborders' => array(
                                            'style' => $bordeGrosor,
                                            'color' => array('rgb' => str_replace("#", "", $colorBordeRgb))
                                        )
                                    )
                                );
        }
        return $style;
    }
    
    //__Descripción:__ Función que permite obtener el formato traducido para aplicación en celdas
    //__Inputs:__ $formato:string ['general','texto','numero','decimal','numerico','numerico2','porcentaje','porcentaje2','fecha','hora','moneda']
    //__Outputs:__ $result:array configuración
    public function setCeldaFormato($formato=""){
        switch ($formato){
            case "general":
                //FORMAT_GENERAL			= 'General';
                $formato = NumberFormat::FORMAT_GENERAL; //PHPExcel_Style_NumberFormat::FORMAT_GENERAL;
            break;
            case "texto":
                //FORMAT_TEXT                           = '@';
                $formato = NumberFormat::FORMAT_TEXT; //PHPExcel_Style_NumberFormat::FORMAT_TEXT;
            break;
            case "numero":
                //FORMAT_NUMBER                         = '0';
                $formato = NumberFormat::FORMAT_NUMBER; //PHPExcel_Style_NumberFormat::FORMAT_NUMBER;
            break;
            case "decimal":
                //FORMAT_NUMBER_00                      = '0.00';
                $formato = NumberFormat::FORMAT_NUMBER_00; //PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00;
            break;
            case "numerico":
                //FORMAT_NUMBER_COMMA_SEPARATED1	= '#,##0.00';
                $formato = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1; //PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
            break;
            case "numerico2":
                //FORMAT_NUMBER_COMMA_SEPARATED2	= '#,##0.00_-';
                $formato = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2; //PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;
            break;
            case "porcentaje":
                //FORMAT_PERCENTAGE			= '0%';
                $formato = NumberFormat::FORMAT_PERCENTAGE; //PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE;
            break;
            case "porcentaje2":
                //FORMAT_PERCENTAGE_00			= '0.00%';
                $formato = NumberFormat::FORMAT_PERCENTAGE_00; //PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00;
            break;
            case "fecha":
                //FORMAT_DATE_YYYYMMDD2			= 'yyyy-mm-dd';
                $formato = NumberFormat::FORMAT_DATE_YYYYMMDD2; //PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;
            break;
            case "fecha2":
                //FORMAT_DATE_YYYYMMDD			= 'yy-mm-dd';
                $formato = NumberFormat::FORMAT_DATE_YYYYMMDD; //PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD;
            break;
            case "fecha3":
                //FORMAT_DATE_DDMMYYYY			= 'dd/mm/yy';
                $formato = NumberFormat::FORMAT_DATE_DDMMYYYY; //PHPExcel_Style_NumberFormat::FORMAT_DATE_DDMMYYYY;
            break;
            case "fecha4":
                //FORMAT_DATE_DMYSLASH			= 'd/m/y';
                $formato = NumberFormat::FORMAT_DATE_DMYSLASH; //PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYSLASH;
            break;
            case "fecha5":
                //FORMAT_DATE_DMYMINUS			= 'd-m-y';
                $formato = NumberFormat::FORMAT_DATE_DMYMINUS; //PHPExcel_Style_NumberFormat::FORMAT_DATE_DMYMINUS;
            break;
            case "fecha6":
                //FORMAT_DATE_DMMINUS			= 'd-m';
                $formato = NumberFormat::FORMAT_DATE_DMMINUS; //PHPExcel_Style_NumberFormat::FORMAT_DATE_DMMINUS;
            break;
            case "fecha7":
                //FORMAT_DATE_MYMINUS			= 'm-y';
                $formato = NumberFormat::FORMAT_DATE_MYMINUS; //PHPExcel_Style_NumberFormat::FORMAT_DATE_MYMINUS;
            break;
            case "fecha8":
                //FORMAT_DATE_XLSX14			= 'mm-dd-yy';
                $formato = NumberFormat::FORMAT_DATE_XLSX14; //PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14;
            break;
            case "fecha9":
                //FORMAT_DATE_XLSX15			= 'd-mmm-yy';
                $formato = NumberFormat::FORMAT_DATE_XLSX15; //PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX15;
            break;
            case "fecha10":
                //FORMAT_DATE_XLSX16			= 'd-mmm';
                $formato = NumberFormat::FORMAT_DATE_XLSX16; //PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX16;
            break;
            case "fecha11":
                //FORMAT_DATE_XLSX17			= 'mmm-yy';
                $formato = NumberFormat::FORMAT_DATE_XLSX17; //PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX17;
            break;
            case "fecha12":
                //FORMAT_DATE_XLSX22			= 'm/d/yy h:mm';
                $formato = NumberFormat::FORMAT_DATE_XLSX22; //PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX22;
            break;
            case "fecha13":
                //FORMAT_DATE_DATETIME			= 'd/m/y h:mm';
                $formato = NumberFormat::FORMAT_DATE_DATETIME; //PHPExcel_Style_NumberFormat::FORMAT_DATE_DATETIME;
            break;
            case "hora":
                //FORMAT_DATE_TIME1			= 'h:mm AM/PM';
                $formato = NumberFormat::FORMAT_DATE_TIME1; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME1;
            break;
            case "hora2":
                //FORMAT_DATE_TIME2			= 'h:mm:ss AM/PM';
                $formato = NumberFormat::FORMAT_DATE_TIME2; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME2;
            break;
            case "hora3":
                //FORMAT_DATE_TIME3			= 'h:mm';
                $formato = NumberFormat::FORMAT_DATE_TIME3; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME3;
            break;
            case "hora4":
                //FORMAT_DATE_TIME4			= 'h:mm:ss';
                $formato = NumberFormat::FORMAT_DATE_TIME4; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4;
            break;
            case "hora5":
                //FORMAT_DATE_TIME5			= 'mm:ss';
                $formato = NumberFormat::FORMAT_DATE_TIME5; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME5;
            break;
            case "hora6":
                //FORMAT_DATE_TIME6			= 'h:mm:ss';
                $formato = NumberFormat::FORMAT_DATE_TIME6; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME6;
            break;
            case "hora7":
                //FORMAT_DATE_TIME7			= 'i:s.S';
                $formato = NumberFormat::FORMAT_DATE_TIME7; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME7;
            break;
            case "hora8":
                //FORMAT_DATE_TIME8			= 'h:mm:ss;@';
                $formato = NumberFormat::FORMAT_DATE_TIME8; //PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME8;
            break;
            case "hora9":
                //FORMAT_DATE_YYYYMMDDSLASH		= 'yy/mm/dd;@';
                $formato = NumberFormat::FORMAT_DATE_YYYYMMDDSLASH; //PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDDSLASH;
            break;
            case "moneda":
                //FORMAT_CURRENCY_USD_SIMPLE		= '"$"#,##0.00_-';
                $formato = NumberFormat::FORMAT_CURRENCY_USD_SIMPLE; //PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE;
            break;
            case "moneda2":
                //FORMAT_CURRENCY_USD			= '$#,##0_-';
                $formato = NumberFormat::FORMAT_CURRENCY_USD; //PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD;
            break;
            case "moneda3":
                //FORMAT_CURRENCY_EUR_SIMPLE		= '[$EUR ]#,##0.00_-';
                $formato = NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE; //PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
            break;
        }
        return $formato;
    }
    
    //__Descripción:__ Función que permite obtener la estructura de información necesaria para generar la hoja de cálculo según los datos ingresados
    //__Inputs:__ 
    //__Outputs:__ $result:string/boolean string para error
    public function getDatos(){
        $result = $this->getTabs();
        if(is_string($result)){
            return $result;
        }
        $datos = array();
        if(count($this->tabs)>0){
            foreach ($this->tabs as $activo=>$nombreHoja){
                if(isset($this->filas[$nombreHoja])){
                    $datos[$activo]["titulo"]=$nombreHoja;
                    $datos[$activo]["filas"]=$this->filas[$nombreHoja];
                    $datos[$activo]["estilos"]=$this->estilos[$nombreHoja];
                    $datos[$activo]["merge"]=$this->merge[$nombreHoja];
                    $datos[$activo]["cellFont"]=$this->cellFont[$nombreHoja];
                    $datos[$activo]["formatos"]=$this->formatos[$nombreHoja];
                }else{
                    trigger_error("coGeneraExcel :: getDatos()-> No se pudo encontrar filas para hoja '".$nombreHoja."'");
                }
            }
            if(count($datos)>0){
                $this->datos=$datos;
                return true;
            }else{
                return "No se pudo establecer los datos para el archivo";
            }
        }else{
            return "No se han definido hojas para el archivo";
        }
    }
    
    //__Descripción:__ Función que permite generar el archivo excel solicitado
    //__Inputs:__ 
    //__Outputs:__ $result:string/boolean string para error
    public function genera(){
        $r = array();
        if (!isset($this->nombreArchivo) || (!$this->salidaDirecta && !isset($this->ubicacion))) {
            trigger_error("genera() no puede ejecutarse pues el objeto coGeneraExcel no está debidamente inicializado, nombre archivo, ubicacion", E_USER_ERROR);
            return "El objeto coGeneraExcel no está debidamente inicializado, nombre archivo, ubicacion";
        }
        $objPHPExcel = new Spreadsheet();//PHPExcel();
        //obtengo datos
        $result = $this->getDatos();
        if(is_string($result)){
            return $result;
        }
        $i=0;
        foreach ($this->datos as $activo=>$contenido){
            $titulo = $contenido["titulo"];
            if(empty($titulo)){
                trigger_error("coGeneraExcel :: genera()-> Tab ".$activo." sin título");
                continue;
            }
            $filas = $contenido["filas"];
            if(count($filas)==0){
                trigger_error("coGeneraExcel :: genera()-> Tab ".$activo." sin registros que generar");
                continue;
            }
            $estilos = isset($contenido["estilos"]) ? $contenido["estilos"] : array();
            $merge = isset($contenido["merge"]) ? $contenido["merge"] : array();
            $celdas = isset($contenido["cellFont"]) ? $contenido["cellFont"] : array();
            $formatos = isset($contenido["formatos"]) ? $contenido["formatos"] : array();
            //set hoja
            if($i==0){
                $objPHPExcel->setActiveSheetIndex($activo);
                $objPHPExcel->getActiveSheet()->setTitle($titulo);
            }else{
                $objWorkSheet = $objPHPExcel->createSheet($activo); //Setting index when creating
                $objPHPExcel->setActiveSheetIndex($activo);
                $objPHPExcel->getActiveSheet()->setTitle($titulo);
            }
            $i++;
            //set datos
            $guiaFila = 1;
            foreach ($filas as $guia=>$fila){
                foreach ($fila as $campo=>$valor){
                    $objPHPExcel->getActiveSheet()->SetCellValue($campo.$guiaFila,$valor);
                    $styleArray=isset($estilos[$guia]) ? $estilos[$guia] : array();
                    $cellArray=isset($celdas[$guia][$campo]) ? $celdas[$guia][$campo] : array();
                    $cellFormat=isset($formatos[$guia][$campo]) ? $formatos[$guia][$campo] : "";
                    if(count($styleArray)>0){
                        $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->applyFromArray($styleArray);
                        $objPHPExcel->getActiveSheet()->getColumnDimension($campo)->setWidth($this->widthColumn);
                        if(isset($styleArray['fill']['type']) && isset($styleArray['fill']['color']['rgb'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFill()->setFillType($styleArray['fill']['type'])->getStartColor()->setRGB($styleArray['fill']['color']['rgb']);
                        }
                    }
                    if(count($cellArray)>0){
                        if(isset($cellArray['background']['type']) && isset($cellArray['background']['color']['rgb'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFill()->setFillType($cellArray['background']['type'])->getStartColor()->setRGB($cellArray['background']['color']['rgb']);
                        }
                        if(isset($cellArray['font']['bold'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFont()->setBold($cellArray['font']['bold']);
                        }
                        if(isset($cellArray['font']['name'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFont()->setName($cellArray['font']['name']);
                        }
                        if(isset($cellArray['font']['size'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFont()->setSize($cellArray['font']['size']);
                        }
                        if(isset($cellArray['font']['color']['rgb'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getFont()->getColor()->setRGB($cellArray['font']['color']['rgb']);
                        }
                        if(isset($cellArray['border'])){
                            $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->applyFromArray($cellArray['border']);
                        }
                    }
                    if(!empty($cellFormat)){
                        $objPHPExcel->getActiveSheet()->getStyle($campo.$guiaFila)->getNumberFormat()->setFormatCode($cellFormat);
                    }
                }
                $mergeArray=isset($merge[$guia]) ? $merge[$guia] : array();
                if(is_array($mergeArray) && count($mergeArray)>0){
                    foreach ($mergeArray as $m){
                        $nom = $m["ini"].$guiaFila.":".$m["fin"].$guiaFila;
                        $conom = $m["color"];
                        if(!empty($nom)){
                            $objPHPExcel->getActiveSheet()->mergeCells($nom);
                            if(!empty($conom)){
                                $tipoBck = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;
                                $styleArrayMerge['fill'] = array(
                                    'type'  => $tipoBck, //"solid",
                                    'color' => array('rgb' => str_replace("#", "", $conom))
                                );
                                $objPHPExcel->getActiveSheet()->getStyle($nom)->applyFromArray($styleArrayMerge);
                            }
                        }
                    }
                }
                $guiaFila++;
            }
        }
        $objPHPExcel->setActiveSheetIndex(0);
        //generé algo en excel?
        if($i>0){
            $objWriter = new Xlsx($objPHPExcel);//PHPExcel_Writer_Excel2007($objPHPExcel);
            if($this->salidaDirecta){
                header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                header("Content-Disposition: attachment; filename=\"".$this->nombreArchivo."\"");
                header("Cache-Control: max-age=0");
                header("Expires: 0");
                header("Pragma: public");
                // Write file to output
                $objWriter->save('php://output');
                exit;
            }else{
                $rutaInterna = $this->ubicacion."/".$this->nombreArchivo;
                $rutaExterna = BASEURL.$this->ubicacion."/".$this->nombreArchivo;
                $objWriter->save("../".$rutaInterna);
                $r = array(
                    "archivo" => $rutaExterna,
                    "ruta" => $rutaInterna,
                    "nombre" =>$this->nombreArchivo,
                );
                return $r;
            }
            
        }else{
            return "No se pudo generar archivo";
        }
    }

    //__Descripción:__ Función que permite convertir una fecha Unixtime en formato humano de número de día, nombre de día, nombre de mes y año, opcionalmente agrega Lugar
    //__Inputs:__ $fechaUnix:int fecha en formato unixtime
    //            $lugar:string lugar a incorporar a fecha
    //            $conDia:boolean indica si la fecha saldrá con el nombre del día o no
    //__Outputs:__ $result:string
    public function getFechaHumana($fechaUnix,$lugar="",$conDia=true){
        global $lang;
        $formatoFecha = "";
        if($lugar != ""){
            $formatoFecha = $lugar.", ";
        }
        if($fechaUnix > 0){
            $anio = date("Y",$fechaUnix);
            $dia = date("N",$fechaUnix);
            $diaAbs = date("d",$fechaUnix);
            $mes = date("n",$fechaUnix);
            $diaNombre = "";
            if($conDia){
                switch ($dia){
                    case 1: $diaNombre = $lang["Lunes"]; break;
                    case 2: $diaNombre = $lang["Martes"]; break;
                    case 3: $diaNombre = $lang["Miércoles"]; break;
                    case 4: $diaNombre = $lang["Jueves"]; break;
                    case 5: $diaNombre = $lang["Viernes"]; break;
                    case 6: $diaNombre = $lang["Sábado"]; break;
                    case 7: $diaNombre = $lang["Domingo"]; break;
                }
            }
            $mesNombre = "";
            switch ($mes){
                case 1: $mesNombre = $lang["enero"]; break;
                case 2: $mesNombre = $lang["febrero"]; break;
                case 3: $mesNombre = $lang["marzo"]; break;
                case 4: $mesNombre = $lang["abril"]; break;
                case 5: $mesNombre = $lang["mayo"]; break;
                case 6: $mesNombre = $lang["junio"]; break;
                case 7: $mesNombre = $lang["julio"]; break;
                case 8: $mesNombre = $lang["agosto"]; break;
                case 9: $mesNombre = $lang["septiembre"]; break;
                case 10: $mesNombre = $lang["octubre"]; break;
                case 11: $mesNombre = $lang["noviembre"]; break;
                case 12: $mesNombre = $lang["diciembre"]; break;
            }
            if($conDia){
                $formatoFecha .= $diaNombre." ".$diaAbs." ".$lang["de"]." ".$mesNombre." ".$lang["de"]." ".$anio;
            }else{
                $formatoFecha .= $diaAbs." ".$lang["de"]." ".$mesNombre." ".$lang["de"]." ".$anio;
            }
        }
        return $formatoFecha;
    }
    
    //__Descripción:__ Función que permite convertir el número de mes en nombre de mes
    //__Inputs:__ $mes:int número de mes
    //__Outputs:__ $result:string nombre del mes
    public function getMesHumano($mes){
        global $lang;
        $mesNombre = "";
        switch ($mes){
            case 1: $mesNombre = $lang["Enero"]; break;
            case 2: $mesNombre = $lang["Febrero"]; break;
            case 3: $mesNombre = $lang["Marzo"]; break;
            case 4: $mesNombre = $lang["Abril"]; break;
            case 5: $mesNombre = $lang["Mayo"]; break;
            case 6: $mesNombre = $lang["Junio"]; break;
            case 7: $mesNombre = $lang["Julio"]; break;
            case 8: $mesNombre = $lang["Agosto"]; break;
            case 9: $mesNombre = $lang["Septiembre"]; break;
            case 10: $mesNombre = $lang["Octubre"]; break;
            case 11: $mesNombre = $lang["Noviembre"]; break;
            case 12: $mesNombre = $lang["Diciembre"]; break;
        }
        return $mesNombre;
    }
    
    //__Descripción:__ Función que permite obtener la edad de una persona a partir de su fecha de nacimiento
    //__Inputs:__ $fechaNacimiento:int número de mes
    //            $fechaCorte:int fecha de corte para calcular la edad
    //__Outputs:__ $result:string edad en formato decimal
    public function getEdad($fechaNacimiento,$fechaCorte=0){
        $edadCalculada = 0;
        if($fechaCorte == 0){
            $fechaCorte = time();
        }
        if($fechaNacimiento != 0 && $fechaNacimiento != -1 && $fechaCorte >= $fechaNacimiento ){
            require_once("../comunes/classes/sc_calendar.php");
            $edadCalculada = sc_calendar::dateDiff("y", $fechaNacimiento, $fechaCorte );
        }
        return $edadCalculada;
    }
    
    //__Descripción:__ Función que permite obtener la edad de una persona a partir de su fecha de nacimiento
    //__Inputs:__ $fechaNacimiento:int número de mes
    //            $fechaCorte:int fecha de corte para calcular la edad
    //            $conMes:boolean indica si la edad retornará o no con los meses de la edad aproximada
    //            $conEtiquetas:boolean indica si la edad retornará o no con etiquetas de años o meses
    //__Outputs:__ $result:string edad en formato años - meses
    public function getEdadTraducida($fechaNacimiento,$fechaCorte=0,$conMes=true,$conEtiquetas=true){
        $edad = "";
        if($fechaCorte == 0){
            $fechaCorte = time();
        }
        if($fechaNacimiento != 0 && $fechaNacimiento != -1 && $fechaCorte >= $fechaNacimiento ){
            require_once("../comunes/classes/sc_calendar.php");
            $edadCalculada = sc_calendar::dateDiff("y", $fechaNacimiento, $fechaCorte );
            if($edadCalculada > 1){
                $edad = (int)$edadCalculada;
                if($conEtiquetas){
                    $edad.=" años";
                }
            }else if($edadCalculada == 1){
                $edad = (int)$edadCalculada;
                if($conEtiquetas){
                    $edad.=" año";
                }
            }
            if($conMes){
                $mesesCalculados = isset(explode('.', $edadCalculada)[1]) ? explode('.', $edadCalculada)[1] : 0;
                if($mesesCalculados > 0){
                    $mesesCalculados = "0.".$mesesCalculados;
                    $mesesCalculados = ($mesesCalculados * 12)/1;
                    if($mesesCalculados > 1){
                        $edad.=" ".(int)$mesesCalculados;
                        if($conEtiquetas){
                            $edad.=" meses";
                        }
                    }else if($mesesCalculados == 1){
                        $edad.=" ".(int)$mesesCalculados;
                        if($conEtiquetas){
                            $edad.=" mes";
                        }
                    }
                }
            }
        }
        return $edad;
    }

    //__Descripción:__ Función que permite traducir mes numérico a letras en español
    //__Inputs:__ $mes:int mes
    //__Outputs:__ $result:string traducción de mes
    public function traduceMes($mes) {
        $traduccion = "";
        switch ($mes) {
            case "1":$traduccion = "Enero";
                break;
            case "2":$traduccion = "Febrero";
                break;
            case "3":$traduccion = "Marzo";
                break;
            case "4":$traduccion = "Abril";
                break;
            case "5":$traduccion = "Mayo";
                break;
            case "6":$traduccion = "Junio";
                break;
            case "7":$traduccion = "Julio";
                break;
            case "8":$traduccion = "Agosto";
                break;
            case "9":$traduccion = "Septiembre";
                break;
            case "10":$traduccion = "Octubre";
                break;
            case "11":$traduccion = "Noviembre";
                break;
            case "12":$traduccion = "Diciembre";
                break;
        }
        return $traduccion;
    }

    //__Descripción:__ Función que permite traducir dia en Ingles a letras en español
    //__Inputs:__ $day:string día en inglés
    //__Outputs:__ $result:string día en español
    public function traduceDia($day) {
        $traduccion = "";
        switch ($day) {
            case "Monday":$traduccion = "Lunes";
                break;
            case "Tuesday":$traduccion = "Martes";
                break;
            case "Wednesday":$traduccion = "Miércoles";
                break;
            case "Thursday":$traduccion = "Jueves";
                break;
            case "Friday":$traduccion = "Viernes";
                break;
            case "Saturday":$traduccion = "Sábado";
                break;
            case "Sunday":$traduccion = "Domingo";
                break;
        }
        return $traduccion;
    }
    
    
}
?>
<?//_FIN_DE_ARCHIVO ?>