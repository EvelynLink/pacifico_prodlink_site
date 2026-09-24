<?
require_once("../comunes/top.inc.php");
require_once("../boletines/classes/class.boBitacora.php");
$bol = new boBitacora();
?>
<html ng-app="LinkApp">
<head>
<title><? echo $lang["Boletines"]; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<? echo ENCODING; ?>">
<link rel="shortcut icon" href="/favicon.ico" />
<?
$ngModulos = array("ngRoute","datepicker","tabula","jerarquia","uploader","timepicker");
$ngControladores = array(
	"comunes/controllers/ModulosSesion.js",
	"boletines/controllers/SuscripcionWeb.js",
);
$ngEstilos = array();
$Central->angular($ngModulos, $ngControladores, $ngEstilos);
?>
<script type="text/javascript">
//añadimos las rutas de este módulo
app.config(['$routeProvider', function($routeProvider) {
        $routeProvider.when('/SuscripcionWeb/:usrreg?/:correoId?/:bitacoraId?', {
		templateUrl: '../comunes/loadTemplate.php?templateName=boletines/partials/SuscripcionWeb.html',
		controller: 'SuscripcionWeb'
	});
	$routeProvider.otherwise({ redirectTo: '/SuscripcionWeb' });
}]);

//y la estructura de menú de este módulo
menuSuperior={
}
</script>
</head>
<body class='basicbody'>


<div class="contenedorModulo">
	<div id="vAngular" ng-view></div>	
</div>
<logo-enlace></logo-enlace>
<?
require_once("../comunes/bottom.inc.php");
?><? //_FIN_DE_ARCHIVO ?>
