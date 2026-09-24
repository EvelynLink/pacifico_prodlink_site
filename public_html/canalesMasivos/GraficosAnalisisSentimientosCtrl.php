<?

require_once "../comunes/top.inc.php";
require_once "../comunes/classes/class.coTabulaMongo.php";
require_once("../comunes/classes/class.mymongodb.php");
if (!$Central->conPermiso("Organigrama Roles y Permisos,Administrador")) {
    exit;
}
if (!isset($_REQUEST["act"])) {
    exit;
}
$mongo = new MYMONGODB();
$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();
eval('$db=new '.DB1.'DB();');

switch ($act) {
    case "graficar":
        $d = jsonStart();
        $datos = generarData();
        
        $totalHombres=0;
        $totalMujeres=0;
        $datosPieGeneral = array();       
        $datosPieGeneralMujeres = array();       
        $datosPieGeneralHombres = array();   
        $datosFuenteDatos = array("key"=>"Fuentes de datos","values"=>array());
        $datosEdad = array(
            array("key"=>"Todo", "values"=>array()),
            array("key"=>"POSITIVO", "values"=>array(), "color"=>"green"),
            array("key"=>"NEGATIVO", "values"=>array(), "color"=>"red"),
            array("key"=>"NEUTRAL", "values"=>array(), "color"=>"gray")
        );
        
        $tempo = array();
        $tempo["_0"]=array(0, 0);
        for ($i=1;$i<=8;$i++){
            $tempo["_".$i]=array($i, 0);
        }
        $tempoP=$tempo;
        $tempoN=$tempo;
        $tempoNU=$tempo;
        
        $datosFecha = array(
            array("key"=>"Fecha", "values"=>array(),"area"=>true)
        );
        
        $pgTempo=array();
        $pgTempoSexo=array();
        $pgTempoFD = array();
        $pgTempoEdad = array();
        $tempoFecha = array();
        
        $provincias=array();
        
        foreach ($datos as $value) {
            //mapa
            if (!isset($provincias[$value["ubicacion"]])){
                $provincias[$value["ubicacion"]]=array("total"=>1,"nombre"=>$value["ubicacion"]);                      
            } else {
                $provincias[$value["ubicacion"]]["total"]++;         
            }
            //pie general
            if (isset($pgTempo[$value["resultado"]])){
                $pgTempo[$value["resultado"]] += 1;
            } else {
                $pgTempo[$value["resultado"]] = 1;
            }
            //pie general sexo
            if (isset($pgTempoSexo[$value["resultado"]][$value["sexo"]])){
                $pgTempoSexo[$value["resultado"]][$value["sexo"]] += 1;
            } else {
                $pgTempoSexo[$value["resultado"]][$value["sexo"]] = 1;
            }
            //fuentes datos
            if (isset($pgTempoFD[$value["fuente"]])){
                $pgTempoFD[$value["fuente"]] += 1;
            } else {
                $pgTempoFD[$value["fuente"]] = 1;
            }
            //edad
            if (isset($pgTempoEdad[$value["resultado"]][$value["edad"]])){
                $pgTempoEdad[$value["resultado"]][$value["edad"]] += 1;
            } else {
                $pgTempoEdad[$value["resultado"]][$value["edad"]] = 1;
            }
            //fecha
            if (isset($tempoFecha[$value["fecha"]])){
                $tempoFecha[$value["fecha"]] += 1;
            } else {
                $tempoFecha[$value["fecha"]] = 1;
            }
        }
        
        foreach ($pgTempoEdad as $k=>$v) {
            foreach ($v as $key=>$value) {
                if ($key>9 && $key <21){
                    $tempo["_1"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_1"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_1"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_1"][1]+=$value;
                    }
                }
                if ($key>20 && $key <31){
                    $tempo["_2"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_2"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_2"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_2"][1]+=$value;
                    }
                }
                if ($key>30 && $key <41){
                    $tempo["_3"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_3"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_3"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_3"][1]+=$value;
                    }
                }
                if ($key>40 && $key <51){
                    $tempo["_4"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_4"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_4"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_4"][1]+=$value;
                    }
                }
                if ($key>50 && $key <61){
                    $tempo["_5"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_5"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_5"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_5"][1]+=$value;
                    }
                }
                if ($key>60 && $key <71){
                    $tempo["_6"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_6"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_6"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_6"][1]+=$value;
                    }
                }
                if ($key>70 && $key <81){
                    $tempo["_7"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_7"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_7"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_7"][1]+=$value;
                    }
                }
                if ($key>80){
                    $tempo["_8"][1]+=$value;
                    if ($k=="POSITIVO"){
                        $tempoP["_8"][1]+=$value;
                    }
                    if ($k=="NEGATIVO"){
                        $tempoN["_8"][1]+=$value;
                    }
                    if ($k=="NEUTRAL"){
                        $tempoNU["_8"][1]+=$value;
                    }
                }
            }
        }
        
        $tempo = array_values($tempo);
        $tempoP = array_values($tempoP);
        $tempoN = array_values($tempoN);
        $tempoNU = array_values($tempoNU);
        
        $fechas =array();
        $fecha = strtotime(date("Y-m-d",strtotime("-31 days"))." 12:00");
        $fechas[] = array($fecha * 1000, 0);
        for ($i=30;$i>=0;$i--){
            $fecha = strtotime(date("Y-m-d",strtotime("-".$i." days"))." 12:00");
            $fechas[] = array(($fecha * 1000), $tempoFecha[$fecha]);  
        }
        $datosFecha[0]["values"]=$fechas;
        
        
        foreach ($pgTempoFD as $key=>$value) {
            $datosFuenteDatos["values"][] = array("label"=>$key, "value"=>$value);
        }
        
        $porcentajesPolaridad = array();
        
        foreach ($pgTempo as $key=>$value) {
            $color = "gray";
            if ($key=="POSITIVO"){
                $color="green";
            }
            if ($key=="NEGATIVO"){
                $color="red";
            }
            $datosPieGeneral[] = array("key"=>$key, "y"=>$value, "color"=>$color);
            $porcentajesPolaridad[$key]=array("porcentaje"=>($value*100)/count($datos),"valor"=>$value);
        }
        
        foreach ($pgTempoSexo as $key=>$value) {
            foreach ($value as $k=>$v) {
                $color = "gray";
                if ($key=="POSITIVO"){
                    $color="green";
                }
                if ($key=="NEGATIVO"){
                    $color="red";
                }
                if ($k=="F"){
                    $datosPieGeneralMujeres[] = array("key"=>$key, "y"=>$v, "color"=>$color);
                    $totalMujeres+=$v;
                }
                if ($k=="M"){
                    $datosPieGeneralHombres[] = array("key"=>$key, "y"=>$v, "color"=>$color);
                    $totalHombres+=$v;
                }
            }
        }
        
        $datosEdad[0]["values"]=$tempo;
        $datosEdad[1]["values"]=$tempoP;
        $datosEdad[2]["values"]=$tempoN;
        $datosEdad[3]["values"]=$tempoNU;
        
        //MAPA
        require_once '../canalesMasivos/classes/class.mpMapa.php';
        $mapa = new mpMapa();
        
        $width = 0;//expect_integer($d["width"]);
        $height = 620;//expect_integer($d["height"]);
        
        //mapa sin nombres ni valores
        $mapaBase = $mapa->crearMapaBase($width, $height);
        $mayor=0;
        foreach ($provincias as $value) {
            if ($value["total"]>$mayor){
                $mayor=$value["total"];
            }
        }
        $mapaFinal = $mapa->reemplazarValores($provincias, $mapaBase, $mayor);
        //FIN MAPA
        
        $json["data"] = array(
            "porcentajePolaridad"=>$porcentajesPolaridad,
            "dataPieGeneral"=>$datosPieGeneral, 
            "dataPieGeneralHombres"=>$datosPieGeneralHombres, 
            "dataPieGeneralMujeres"=>$datosPieGeneralMujeres, 
            "totalHombres"=>$totalHombres,
            "totalMujeres"=>$totalMujeres,
            "totalRegistros"=>count($datos),
            "dataFuenteDatos"=>array($datosFuenteDatos),
            "dataEdad"=>$datosEdad,
            "dataFecha"=>$datosFecha,
            "dataUbicacion"=>$mapaFinal
        );
        break;        
    case "graficarChatbot":
        $d = jsonStart();
        $datos = generarDataChatbot();
        
        $pgTempoFD = array();
        
        $dataHora = array(
            array("key"=>"POSITIVO", "values"=>array(), "color"=>"green","area"=>true),
            array("key"=>"NEGATIVO", "values"=>array(), "color"=>"red","area"=>true)
        );
        
        $datosEdad = array(
            array("key"=>"Todo", "values"=>array()),
            array("key"=>"Positivo Masculino", "values"=>array(), "color"=>"#145A32"),
            array("key"=>"Negativo Masculino", "values"=>array(), "color"=>"#641E16"),
            array("key"=>"Positivo Femenino", "values"=>array(), "color"=>"#2ECC71"),
            array("key"=>"Negativo Femenino", "values"=>array(), "color"=>"#EC7063")
        );
        
        $provincias = array();
        $datosFuenteDatos = array("key"=>"Fuentes de datos","values"=>array());
        $pgTempoEdad = array();
        $pgHora = array();
        $tempo = array();
        $tempo["_0"]=array(0, 0);
        for ($i=1;$i<=8;$i++){
            $tempo["_".$i]=array($i, 0);
        }
        $tempoMP=$tempo;
        $tempoMN=$tempo;
        $tempoFP=$tempo;
        $tempoFN=$tempo;
        
        foreach ($datos as $key => $value) {
            //fuentes datos
            if (isset($pgTempoFD[$value["fuente"]])){
                $pgTempoFD[$value["fuente"]] += 1;
            } else {
                $pgTempoFD[$value["fuente"]] = 1;
            }
            //mapa
            if (!isset($provincias[$value["ubicacion"]])){
                $provincias[$value["ubicacion"]]=array("total"=>1,"nombre"=>$value["ubicacion"]);                      
            } else {
                $provincias[$value["ubicacion"]]["total"]++;         
            }
            //edad
            if (isset($pgTempoEdad[$value["resultado"]][$value["sexo"]][$value["edad"]])){
                $pgTempoEdad[$value["resultado"]][$value["sexo"]][$value["edad"]] += 1;
            } else {
                $pgTempoEdad[$value["resultado"]][$value["sexo"]][$value["edad"]] = 1;
            }
            //hora
            $fe = date("H", $value["fecha"]);
            if (isset($pgHora[$value["resultado"]][$fe])){
                $pgHora[$value["resultado"]][$fe] += 1;
            } else {
                $pgHora[$value["resultado"]][$fe] = 1;
            }
        }
        
        $tempohp = array();
        $tempohp["_0"]=array(0, 0);
        for ($i=0;$i<=23;$i++){
            $tempohp["_".$i]=array($i, 0);
        }
        $tempohn=$tempohp;
        
        foreach ($pgHora as $k=>$v) {
            foreach ($v as $key=>$value) {
                if ($k=="POSITIVO"){
                    $tempohp["_".intval($key)][1]+=$value;
                }
                if ($k=="NEGATIVO"){
                    $tempohn["_".intval($key)][1]+=$value;
                }
                
            }
        }
        
        $tempohp = array_values($tempohp);
        $tempohn = array_values($tempohn);
        
        foreach ($pgTempoEdad as $k=>$v) {
            foreach ($v as $k1=>$v1) {
                foreach ($v1 as $key=>$value) {
                    if ($key>9 && $key <21){
                        $tempo["_1"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_1"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_1"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_1"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_1"][1]+=$value;
                        }
                    }
                    if ($key>20 && $key <31){
                        $tempo["_2"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_2"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_2"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_2"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_2"][1]+=$value;
                        }
                    }
                    if ($key>30 && $key <41){
                        $tempo["_3"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_3"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_3"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_3"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_3"][1]+=$value;
                        }
                    }
                    if ($key>40 && $key <51){
                        $tempo["_4"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_4"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_4"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_4"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_4"][1]+=$value;
                        }
                    }
                    if ($key>50 && $key <61){
                        $tempo["_5"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_5"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_5"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_5"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_5"][1]+=$value;
                        }
                    }
                    if ($key>60 && $key <71){
                        $tempo["_6"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_6"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_6"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_6"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_6"][1]+=$value;
                        }
                    }
                    if ($key>70 && $key <81){
                        $tempo["_7"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_7"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_7"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_7"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_7"][1]+=$value;
                        }
                    }
                    if ($key>80){
                        $tempo["_8"][1]+=$value;
                        if ($k=="POSITIVO" && $k1=="M"){
                            $tempoMP["_8"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="M"){
                            $tempoMN["_8"][1]+=$value;
                        }
                        if ($k=="POSITIVO" && $k1=="F"){
                            $tempoFP["_8"][1]+=$value;
                        }
                        if ($k=="NEGATIVO" && $k1=="F"){
                            $tempoFN["_8"][1]+=$value;
                        }
                    }
                }
                
            }
        }
        
        $tempo = array_values($tempo);
        $tempoMP = array_values($tempoMP);
        $tempoMN = array_values($tempoMN);
        $tempoFP = array_values($tempoFP);
        $tempoFN = array_values($tempoFN);
        
        foreach ($pgTempoFD as $key=>$value) {
            $datosFuenteDatos["values"][] = array("label"=>$key, "value"=>$value);
        }
        
        $maximo = 1000;
    
        $preguntasConRespuesta=array(
            array("label"=>"Quiero saber cuánto debo","value"=>rand(0,$maximo)),
            array("label"=>"Cuánto es mi deuda","value"=>rand(0,$maximo)),
            array("label"=>"Hasta cuándo puedo pagar?","value"=>rand(0,$maximo)),
            array("label"=>"Qué me están cobrando?","value"=>rand(0,$maximo)),
            array("label"=>"Dónde puedo pagar?","value"=>rand(0,$maximo)),
            array("label"=>"Cuando me paguen","value"=>rand(0,$maximo)),
        );
        $preguntasSinRespuesta=array(
            array("label"=>"A qué hora cierra la agencia del CCI?","value"=>rand(0,$maximo)),
            array("label"=>"Qué promociones hay","value"=>rand(0,$maximo)),
            array("label"=>"Qué pasa si no cancelo?","value"=>rand(0,$maximo)),
        );
        
        $preguntasConRespuestaF = array(
            "key"=>"Preguntas frecuentes",
            "values"=>$preguntasConRespuesta,
            "color"=>"#DC7633"
        );
        $preguntasSinRespuestaF = array(
            "key"=>"Preguntas sin respuesta",
            "values"=>$preguntasSinRespuesta
        );
                
        //MAPA
        require_once '../canalesMasivos/classes/class.mpMapa.php';
        $mapa = new mpMapa();
        
        $width = 0;//expect_integer($d["width"]);
        $height = 620;//expect_integer($d["height"]);
        
        //mapa sin nombres ni valores
        $mapaBase = $mapa->crearMapaBase($width, $height);
        $mayor=0;
        foreach ($provincias as $value) {
            if ($value["total"]>$mayor){
                $mayor=$value["total"];
            }
        }
        $mapaFinal = $mapa->reemplazarValores($provincias, $mapaBase, $mayor);
        //FIN MAPA
        
        $datosEdad[0]["values"]=$tempo;
        $datosEdad[1]["values"]=$tempoMP;
        $datosEdad[2]["values"]=$tempoMN;
        $datosEdad[3]["values"]=$tempoFP;
        $datosEdad[4]["values"]=$tempoFN;
        
        $dataHora[0]["values"]=$tempohp;
        $dataHora[1]["values"]=$tempohn;
        
        $json["data"] = array(
            "conRespuesta"=>array($preguntasConRespuestaF),
            "sinRespuesta"=>array($preguntasSinRespuestaF),
            "dataFuenteDatos"=>array($datosFuenteDatos),
            "dataUbicacion"=>$mapaFinal,
            "dataEdad"=>$datosEdad,
            "dataHora"=>$dataHora
        );        
        break;        
}

function generarDataChatbot(){
    $maximo = rand(1000,10000);
    $ubicaciones = array("azuay","bolivar","canar","carchi","chimborazo","cotopaxi","eloro","esmeraldas","galapagos","guayas","imbabura","loja","losrios","manabi","moronasantiago", "napo", "orellana", "pastaza", "pichincha", "santaelena", "santodomingodelostsachilas", "sucumbios", "tungurahua", "zamorachinchipe","azuay","azuay","pichincha","pichincha","guayas","guayas");
    
    $fuente=array("Teléfonos","Direcciones","Nombre completos","Direcciones","Nombre completos");
    
    $sexo = array("M","F","F");
    $fechas =array();
    $max = (date('H'));
    for ($i=1;$i<=$max;$i++){
        $format = "Y-m-d ".$i.":i";
        $fecha = strtotime(date($format));
        $fechas[] = $fecha;  
    }
    
    $resultado = array("POSITIVO","NEGATIVO","POSITIVO","NEGATIVO","NEGATIVO");
    $data=array();
    for ($i=1; $i<=$maximo; $i++){
        $item = array(
            "edad" => rand(18, 70),
            "sexo" => $sexo[rand(0,2)],
            "fuente" => $fuente[rand(0,4)],
            "resultado" => $resultado[rand(0, 4)],
            "ubicacion" => $ubicaciones[rand(0, 30)],
            "fecha" => $fechas[rand(0, $max)]
        );
        $data[] = $item;
    }
    
    return $data;
}

function generarData(){
    $fechas =array();
    for ($i=30;$i>=0;$i--){
        $fecha = strtotime(date("Y-m-d",strtotime("-".$i." days"))." 12:00");
        $fechas[] = $fecha;  
    }
    
    //$ubicaciones = array("BOLÍVAR","CAÑAR","CARCHI","CHIMBORAZO","COTOPAXI","EL ORO","ESMERALDAS","GALÁPAGOS","GUAYAS","IMBABURA","LOJA","LOS RÍOS","MANABÍ","MORONA SANTIAGO", "NAPO", "ORELLANA", "PASTAZA", "PICHINCHA", "SANTA ELENA", "SANTO DOMINGO", "SUCUMBÍOS", "TUNGURAHUA", "ZAMORA CHINCHIPE");
    $ubicaciones = array("azuay","bolivar","canar","carchi","chimborazo","cotopaxi","eloro","esmeraldas","galapagos","guayas","imbabura","loja","losrios","manabi","moronasantiago", "napo", "orellana", "pastaza", "pichincha", "santaelena", "santodomingodelostsachilas", "sucumbios", "tungurahua", "zamorachinchipe","azuay","azuay","pichincha","pichincha","guayas","guayas");
    
    $sexo = array("M","F","F");
    $lenguaje = array("ES","EN");
    $fuente = array("Twitter para android","Twitter para iphone", "Twitter windows", "Facebook android", "Facebook Iphone", "Facebook messenger", "Correo electrónico","Twitter para android","Facebook Iphone");
    $resultado = array("POSITIVO","NEGATIVO","NEUTRAL","POSITIVO","NEGATIVO");
    //$ubicacion = array("Ecuador","EEUU","Ecuador","España","Ecuador","Venezuela","Ecuador","Italia","Ecuador");
    $cantidad = rand(1000, 10000);
    $data=array();
    for ($i=1; $i<=$cantidad; $i++){
        $item = array(
            "edad" => rand(18, 70),
            "lenguaje" => $lenguaje[rand(0,1)],
            "sexo" => $sexo[rand(0,2)],
            "fuente" => $fuente[rand(0,8)],
            "resultado" => $resultado[rand(0, 4)],
            "ubicacion" => $ubicaciones[rand(0, 29)],
            "fecha" => $fechas[rand(0, 30)]
        );
        $data[] = $item;
    }
    return $data;
}

jsonEnd($json, $limpiar);
?><?

//_FIN_DE_ARCHIVO ?>