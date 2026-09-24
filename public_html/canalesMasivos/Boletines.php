<?
require_once("../comunes/top.inc.php");
?>
<html ng-app="LinkApp">

<head>
    <title><? echo $lang["Canales Masivos"]; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<? echo ENCODING; ?>">
    <link rel="shortcut icon" href="/favicon.ico" />

    <?
    //$ngModulos = array("ngRoute","datepicker","tabula","jerarquia","uploader","timepicker","d3","nvd3",'socketio');
    // $ngModulos = array(
    //     "ngMaterial",
    //     "datepicker",
    //     "bootstrap_varios",
    //     "tabula", /*"nvd3", "d3","angular-Dc",*/
    //     "ngRoute",
    //     "datepicker",/* "svg",*/
    //     'socketio',
    //     "timepicker",
    //     "gridster",
    //     "filtercolumn"
    // );
    $ngModulos = array(
        "ngRoute",
        "bootstrap_varios",
        "carousel",
        "ngMaterial",
        "datepicker",
        "tabula",
        "nvd3",
        "gridster",
        "filtercolumn",
        "uploader",
        "leaflet",
        "timepicker"
    );
    //            "uploader", "nota");
    $ngControladores = array(
        "comunes/controllers/ModulosSesion.js",
        /*"canalesMasivos/controllers/Boletines.js",
            "canalesMasivos/controllers/BoletinesEnvio.js",
            "canalesMasivos/controllers/BoletinesRedesConf.js",*/
        "canalesMasivos/controllers/chatbots.js",
        /*"canalesMasivos/controllers/chatbotConf.js",
            "canalesMasivos/controllers/analisisSentimientos.js",
            "canalesMasivos/controllers/GraficosAnalisisSentimientos.js",
            "canalesMasivos/controllers/GraficosChatbot.js",
            "canalesMasivos/controllers/facebookConf.js",
            "canalesMasivos/controllers/cwDemoStreamChat.js",
            "canalesMasivos/partials/cwDemoStreamChat.html",
            'functions/lightbox/js/lightbox.min.js'*/
        "canalesMasivos/controllers/agenteVirtual.js",
        "canalesMasivos/controllers/agenteVirtualParam.js",
        "canalesMasivos/controllers/cmReporteAgenteVirtual.js",
    );
    // $ngEstilos = array();
    $ngEstilos = array(
        "css/global.css",
        "css/angular_materia.minl.css",
        "canalesMasivos/css/cmReporteAgenteVirtual.css"
    );
    // $Central->angular($ngModulos, $ngControladores, $ngEstilos);
    // $Central->angularMenus("menuSuperior", "Canales Masivos", ['viewAngular'], ['vPrototype']);
    $Central->angular($ngModulos, $ngControladores, $ngEstilos);
    $Central->angularMenus("menuSuperior", "Canales Masivos", [], [], []);

    $losJSAutomaticos = [];
    $losJSAutomaticos[] = 'd3/liquidFillGauge.js';
    //        $losJSAutomaticos[] = 'd3/liquidFillGauge.js';
    echo $Central->loadJS($losJSAutomaticos);
    ?>

</head>

<body class='basicbody'>
    <cabecera></cabecera>
    <!--    <table width='100%' cellpadding='10'>
       <tr>
            <td valign='top' width='99%'><div ng-include src="'../comunes/loadTemplate.php?templateName=cobranza/partials/cbBuscador.html'" ></div></td>
        </tr>
        <tr>
            <td valign='top' width='99%'><div ng-view></div></td>
        </tr>
    </table>-->

    <table width='100%' cellpadding='10'>
        <tr>
            <td valign='top' width='99%'>
                <div ng-view></div>
            </td>
        </tr>
    </table>


    <logo-enlace></logo-enlace>
    <!-- <body class='basicbody'>
    <cabecera></cabecera>
    <div class="contenedorModulo" style="height: 100%;width:100%;overflow: scroll">
        <div id="viewAngular" ng-view></div>
        <div id="vPrototype"></div>
    </div>
    <logo-enlace></logo-enlace>
</body>

</html> -->
    <?
    require_once("../comunes/bottom.inc.php");
    ?>
    <?
    //_FIN_DE_ARCHIVO 
    ?>