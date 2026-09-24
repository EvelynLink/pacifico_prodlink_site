<?
require_once("../comunes/top.inc.php");
set_time_limit(60 * 60);
ini_set('memory_limit', '512M');

//REAL TEST TO USE API
//$test = apiCall();
//print_h('Launched');
//print_h($test);


require_once("../boletines/classes/class.boCategoria.php");
require_once("../boletines/classes/class.boJerarquia.php");
require_once("../boletines/classes/class.boCorreoxJerarquia.php");
require_once("../boletines/classes/class.boCorreo.php");

//BYPASS TO TEST METHODS

$listado = [];
$listado[] = [
    "email" => "mikhael.portela@espaciolink.com",
    "nombre" => "Mikhael Portela",
    "identificacion" => "1757226467",
    "fechanacimientounix" => 555984000, "telefono" => "0999056037", "celular" => "0999056037"
];

$test_chain = "CNT|2017|Julio|Tramo30-60";
print_h($test_chain);
$boCorreo = new boCorreo();
$send = $boCorreo->enviarCorreoIntegrado($test_chain, false, $listado);
if ($send['op']) {
    print_h("Exito");
    print_h($send['data']);
} else {
    print_h("Disculpe, no pudo efectuarse");
}

exit('done');




//$ans = $jerarquias->obtieneDetalleOrigen(5, $categoria_id);
//$jerarquias->getIdPorNombreYCategoria("", $categoria_id);

print_h($arbol);

exit('done');



require_once("../boletines/apis/class.serviciosIntegradosAPI.php");
$test = new serviciosIntegradosAPI();
$ans = $test->api_testing();
print_h($ans);
exit('done');






/**
 * Funcion patron para uso de API boletines
 * @param string 
 */
function apiCall() {
    require_once("../boletines/apis/class.serviciosIntegradosAPI.php");
    $public_key = 'lwCc0HY9WdCF5hEOa6r7CFTKqKUy8URejl0ozDbzk9JLge1YfMKOiCC3AQm76Ph8';        //self
    $private_key = 'CgKsHp{*m!rN5*LoA+|S)-qouPqGfFpjyVXweGT7)nCeUFuTTN:K0ox+N.Z7ZPL{';        //self
    $api_boletines = new serviciosIntegradosAPI();
    $metodo = "api_testing";
    $token = $api_boletines->generaToken($private_key, $metodo);
    $arr = [
        "clavePublica" => $public_key,
        "token" => $token,
        "metodo" => $metodo
    ];
    $api_boletines->local($arr);
    return $api_boletines->local($arr);
}
exit("Done");

?><? //_FIN_DE_ARCHIVO ?>