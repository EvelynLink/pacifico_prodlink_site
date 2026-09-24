<?
require_once("../comunes/classes/class.clase.php");

class mpMapa extends Clase {

    protected $colorMapa = "#fff";
    protected $provincias = array();
    protected $coloresAlerta = array();
    
    function __construct() {
        $this->colorMapa = "#fcf3cf";
        //de menos a mas
        $this->coloresAlerta = array(
            "#2ecc71",
            "#27ae60",
            "#16a085",
            "#1abc9c",
            "#3498db",
            "#2980b9",
            "#f39c12",
            "#f1c40f",
            "#e74c3c",
            "#c0392b",
        );
        $this->provincias = array(
            "esmeraldas"=>array(
                "id"=>array(
                    "M604","M601","M455"
                ),
                "reemplaza"=>"@colorEsmeraldas",
                "nombre"=>"ESMERALDAS",
                "total"=>0,
                "x"=>474,
                "y"=>240,
                "tx"=>520,
                "ty"=>263,
                "font"=>18
            ),
            "manabi"=>array(
                "id"=>array(
                    "M427"
                ),
                "reemplaza"=>"@colorManabi",
                "nombre"=>"MANABÍ",
                "total"=>0,
                "x"=>237,
                "y"=>600,
                "tx"=>260,
                "ty"=>623,
                "font"=>18
            ),
            "losrios"=>array(
                "id"=>array(
                    "M540"
                ),
                "reemplaza"=>"@colorLosrios",
                "nombre"=>"LOS RIOS",
                "total"=>0,
                "x"=>380,
                "y"=>750,
                "tx"=>410,
                "ty"=>773,
                "font"=>18
            ),
            "guayas"=>array(
                "id"=>array(
                    "M417","M404","M405","M399","M392","M391","M378","M376","M370","M362","M354","M349","M348","M347","M340","M339","M351",            "M329","M324"
                ),
                "reemplaza"=>"@colorGuayas",
                "nombre"=>"GUAYAS",
                "total"=>0,
                "x"=>280,
                "y"=>880,
                "tx"=>310,
                "ty"=>903,
                "font"=>18
            ),
            "santaelena"=>array(
                "id"=>array(
                    "M165"
                ),
                "reemplaza"=>"@colorSantaelena",
                "nombre"=>"SANTA ELENA",
                "total"=>0,
                "x"=>108,
                "y"=>900,
                "tx"=>158,
                "ty"=>923,
                "font"=>18
            ),
            "eloro"=>array(
                "id"=>array(
                    "M312","M308","M307"
                ),
                "reemplaza"=>"@colorEloro",
                "nombre"=>"EL ORO",
                "total"=>0,
                "x"=>318,
                "y"=>1240,
                "tx"=>342,
                "ty"=>1263,
                "font"=>18
            ),
            "carchi"=>array(
                "id"=>array(
                    "M706"
                ),
                "reemplaza"=>"@colorCarchi",
                "nombre"=>"CARCHI",
                "total"=>0,
                "x"=>784,
                "y"=>239,
                "tx"=>811,
                "ty"=>262,
                "font"=>18
            ),
            "imbabura"=>array(
                "id"=>array(
                    "M719"
                ),
                "reemplaza"=>"@colorImbabura",
                "nombre"=>"IMBABURA",
                "total"=>0,
                "x"=>672,
                "y"=>300,
                "tx"=>710,
                "ty"=>323,
                "font"=>18
            ),
            "pichincha"=>array(
                "id"=>array(
                    "M864"
                ),
                "reemplaza"=>"@colorPichincha",
                "nombre"=>"PICHINCHA",
                "total"=>0,
                "x"=>634,
                "y"=>430,
                "tx"=>674,
                "ty"=>453,
                "font"=>18
            ),
            "santodomingodelostsachilas"=>array(
                "id"=>array(
                    "M563"
                ),
                "reemplaza"=>"@colorSantodomingo",
                "nombre"=>"SANTO DOMINGO",
                "total"=>0,
                "x"=>478,
                "y"=>445,
                "tx"=>512,
                "ty"=>483,
                "font"=>12
            ),
            "cotopaxi"=>array(
                "id"=>array(
                    "M739"
                ),
                "reemplaza"=>"@colorCotopaxi",
                "nombre"=>"COTOPAXI",
                "total"=>0,
                "x"=>562,
                "y"=>600,
                "tx"=>602,
                "ty"=>623,
                "font"=>18
            ),
            "tungurahua"=>array(
                "id"=>array(
                    "M743"
                ),
                "reemplaza"=>"@colorTungurahua",
                "nombre"=>"TUNGURAHUA",
                "total"=>0,
                "x"=>605,
                "y"=>705,
                "tx"=>662,
                "ty"=>728,
                "font"=>18
            ),
            "bolivar"=>array(
                "id"=>array(
                    "M523"
                ),
                "reemplaza"=>"@colorBolivar",
                "nombre"=>"BOLÍVAR",
                "total"=>0,
                "x"=>505,
                "y"=>780,
                "tx"=>536,
                "ty"=>803,
                "font"=>18
            ),
            "chimborazo"=>array(
                "id"=>array(
                    "M620"
                ),
                "reemplaza"=>"@colorChimborazo",
                "nombre"=>"CHIMBORAZO",
                "total"=>0,
                "x"=>562,
                "y"=>850,
                "tx"=>612,
                "ty"=>873,
                "font"=>18
            ),
            "canar"=>array(
                "id"=>array(
                    "M693"
                ),
                "reemplaza"=>"@colorCanar",
                "nombre"=>"CAÑAR",
                "total"=>0,
                "x"=>536,
                "y"=>1000,
                "tx"=>560,
                "ty"=>1023,
                "font"=>18
            ),
            "azuay"=>array(
                "id"=>array(
                    "M564"
                ),
                "reemplaza"=>"@colorAzuay",
                "nombre"=>"AZUAY",
                "total"=>0,
                "x"=>496,
                "y"=>1130,
                "tx"=>518,
                "ty"=>1153,
                "font"=>18
            ),
            "loja"=>array(
                "id"=>array(
                    "M464"
                ),
                "reemplaza"=>"@colorLoja",
                "nombre"=>"LOJA",
                "total"=>0,
                "x"=>404,
                "y"=>1380,
                "tx"=>418,
                "ty"=>1403,
                "font"=>18
            ),
            "sucumbios"=>array(
                "id"=>array(
                    "M867"
                ),
                "reemplaza"=>"@colorSucumbios",
                "nombre"=>"SUCUMBÍOS",
                "total"=>0,
                "x"=>1136,
                "y"=>430,
                "tx"=>1178,
                "ty"=>453,
                "font"=>18
            ),
            "napo"=>array(
                "id"=>array(
                    "M730"
                ),
                "reemplaza"=>"@colorNapo",
                "nombre"=>"NAPO",
                "total"=>0,
                "x"=>788,
                "y"=>550,
                "tx"=>804,
                "ty"=>573,
                "font"=>18
            ),
            "orellana"=>array(
                "id"=>array(
                    "M1466"
                ),
                "reemplaza"=>"@colorOrellana",
                "nombre"=>"ORELLANA",
                "total"=>0,
                "x"=>1142,
                "y"=>590,
                "tx"=>1180,
                "ty"=>613,
                "font"=>18
            ),
            "pastaza"=>array(
                "id"=>array(
                    "M792"
                ),
                "reemplaza"=>"@colorPastaza",
                "nombre"=>"PASTAZA",
                "total"=>0,
                "x"=>1010,
                "y"=>790,
                "tx"=>1042,
                "ty"=>813,
                "font"=>18
            ),
            "moronasantiago"=>array(
                "id"=>array(
                    "M789"
                ),
                "reemplaza"=>"@colorMorona",
                "nombre"=>"MORONA SANTIAGO",
                "total"=>0,
                "x"=>730,
                "y"=>1000,
                "tx"=>812,
                "ty"=>1023,
                "font"=>18
            ),
            "zamorachinchipe"=>array(
                "id"=>array(
                    "M745"
                ),
                "reemplaza"=>"@colorZamora",
                "nombre"=>"ZAMORA",
                "total"=>0,
                "x"=>556,
                "y"=>1350,
                "tx"=>584,
                "ty"=>1393,
                "font"=>18
            ),
            "galapagos"=>array(
                "id"=>array(
                    "M1312","M1285","M1338","M1292","M1340","M1201","M1298","M1344","M1362","M1272","M1338","M1327","M1454","M1413","M1401","M1311","M1281","M1164","M1144","M1188","M1374","M1233","M1134"
                ),
                "reemplaza"=>"@colorGalapagos",
                "nombre"=>"GALÁPAGOS",
                "total"=>0,
                "x"=>1293,
                "y"=>1320,
                "tx"=>1340,
                "ty"=>1343,
                "font"=>18
            ),
        );
        
        //rutina para eliminar los mapas generados anteriores a la ultima hora
        $limite = strtotime("-1 hour");
        if ($handle = opendir('../canalesMasivos/images')) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $nombre = explode(".", $entry);
                    if ($nombre[0]<$limite){
                        //borro
                        unlink('../boletines/images/'.$entry);
                    }
                }
            }
            closedir($handle);
        }
    }
    
    public function crearMapaBase($width, $height, $incluyeNombresVecinos=true, $incluyeMar=true){
        $mapaLimpio = file_get_contents("../canalesMasivos/classes/ecuador.svg");
        
        $mapaw = str_replace("<!--@width-->", $width, $mapaLimpio);
        $mapah = str_replace("<!--@height-->", $height, $mapaw);
        
        $mapaVecinos=$mapah;
        if ($incluyeNombresVecinos){
            $vecinos = '<g>
                            <text x="1000" y="100" dx="30" fill="#000" font-size="24" font-family="sans-serif" font-weight="bold">COLOMBIA</text>
                        </g>
                        <g>
                            <text x="800" y="1500" dx="30" fill="#000" font-size="24" font-family="sans-serif" font-weight="bold">PERÚ</text>
                        </g>';
            $mapaVecinos = str_replace("<!--@dibujoVecinos-->", $vecinos, $mapah);
        }
        
        $mapaMar=$mapaVecinos;
        if ($incluyeMar){
            $mar = '<g>
                        <text x="15" y="220" dx="30" fill="#000" font-size="24" font-family="sans-serif" font-weight="bold">OCÉANO PACÍFICO</text>
                    </g>';
            $mapaMar = str_replace("<!--@dibujoMar-->", $mar, $mapaMar);
        }
        
        return $mapaMar;
    }
    
    public function reemplazarValores($data, $mapa, $total, $color=""){
        
        if ($color==""){
            $color=$this->colorMapa;
        }
        
        foreach ($this->provincias as $key => $value) {
            if (isset($data[$key]) && $data[$key]["total"]>0){
                $this->provincias[$key]["total"]=$data[$key]["total"];
            }
        }
        
        //determino rangos dinamicamente
        $rangos = $this->generarRango($total);
        
        $svgProvincias="";
        $elMapa = $mapa;
        foreach ($this->provincias as $k=>$value) {
            
            $svgProvincias.='<g><text x="'.$value["x"].'" y="'.$value["y"].'" dx="30" fill="#000" font-size="'.$value["font"].'" font-family="sans-serif" font-weight="bold">';
            
            if ($value["total"]>0){
                foreach ($rangos as $key=>$vr) {
                    if ($value["total"]>=$vr[0] && $value["total"]<=$vr[1]){
                        $elMapa = str_replace($value["reemplaza"], $this->coloresAlerta[$key], $elMapa);
                        break;
                    } 
                }
            } else{
                $elMapa = str_replace($value["reemplaza"], $color, $elMapa);
            }
            $svgProvincias.=$value["nombre"]."</text>";
            if ($k=="zamorachinchipe"){
                $svgProvincias.='<text x="546" y="1370" dx="30" fill="#000" font-size="18" font-family="sans-serif" font-weight="bold">CHINCHIPE</text>';
            }
            if ($k=="santodomingodelostsachilas"){
                $svgProvincias.='<text x="472" y="460" dx="30" fill="#000" font-size="12" font-family="sans-serif" font-weight="bold">DE LOS TSÁCHILAS</text>';
            }
            
            //dibujar total
            if ($value["total"]>0){
                $valorx=$value["tx"];
                if (strlen($value["total"])==2){
                    $valorx=$valorx-8;
                }
                if (strlen($value["total"])>2){
                    $valorx=$valorx-12;
                }
                $svgProvincias.='<text x="'.$valorx.'" y="'.$value["ty"].'" dx="30" fill="#000" font-size="24" font-family="sans-serif" font-weight="bold">'.$value["total"].'</text>';
            }
            
            $svgProvincias.="</g>";
        }  
        
        $elMapa = str_replace("<!--@dibujoEstadistica-->", $svgProvincias, $elMapa);        
        
        return $elMapa;
        
    }
    
    private function generarRango($total){
        $rangos = array(
            0=>array(1,1),
            2=>array(2,2),
            4=>array(3,3),
            6=>array(4,4),
            8=>array(5,5)
        );
        if ($total>5){
            $total++;
            $media = $total/10;
            $rangos = array();
            for ($i=0; $i<=$total; $i+=$media){
                $rangos[]=array($i, $i+$media-0.1);
            }            
        }
        return $rangos;
    }
    
}   

?><? //_FIN_DE_ARCHIVO  ?>
