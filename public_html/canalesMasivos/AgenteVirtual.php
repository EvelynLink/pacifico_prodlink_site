<?
require_once("../comunes/top.inc.php");
?>
<html ng-app="LinkApp">

<head>
    <title><? echo $lang["Agente virtual"]; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<? echo ENCODING; ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="/favicon.ico" />

    <?
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
        "canalesMasivos/controllers/chatbots.js",
        "canalesMasivos/controllers/cmDashboardGestionIndividual.js",
        "canalesMasivos/controllers/cmReporteAgenteVirtual.js",
        "canalesMasivos/controllers/agenteVirtual.js",
        "canalesMasivos/controllers/cmDashboardGestiones.js",
        "canalesMasivos/controllers/cmDashboardGestionesCarteraPorMora.js",
        "canalesMasivos/controllers/cmDashboardGestionesVentas.js",
        "canalesMasivos/controllers/cmDashboardColocacionTarjetas.js",
        "canalesMasivos/partials/cmDashboardColocacionTarjetas.html",
        "canalesMasivos/controllers/cmReporteAgenteVirtualWhatsapp.js",
        "canalesMasivos/controllers/cmReporteAgenteVirtualWhatsappDesarrollo.js",
        "canalesMasivos/controllers/cmLocalLLM.js",
        "canalesMasivos/controllers/cmDashboardAuditoriaAgentes.js",
        "canalesMasivos/partials/cmDashboardAuditoriaAgentes.html",
        "canalesMasivos/controllers/cmDashboardEstadoCartera.js",
        "canalesMasivos/partials/cmDashboardEstadoCartera.html",
        "canalesMasivos/controllers/cmReporteMinutosUsados.js",
        "canalesMasivos/controllers/cmFechasPeriodo.js",
        "canalesMasivos/controllers/agenteVirtualParam.js",
        "canalesMasivos/controllers/cmConfiguracionTelefonos.js",
        "canalesMasivos/controllers/cmReporteDesarrollo.js",
        "canalesMasivos/controllers/cmDetalleGestionBase.js",
        "canalesMasivos/controllers/cmDetalleContactabilidad.js",
        "canalesMasivos/controllers/cmDetalleEfectividad.js",
        "canalesMasivos/controllers/cmDetalleHorarios.js",
        "canalesMasivos/controllers/cmDetalleIntensidad.js",
        "canalesMasivos/controllers/cmDashboardGestionesCobranza.js",
        "canalesMasivos/controllers/cmDetalleCobranzaGestionBase.js",
        "canalesMasivos/controllers/cmDetalleCobranzaContactabilidad.js",
        "canalesMasivos/controllers/cmDetalleCobranzaEfectividad.js",
        "canalesMasivos/controllers/cmDetalleCobranzaHorarios.js",
        "canalesMasivos/controllers/cmDetalleCobranzaIntensidad.js"
    );
    // $ngEstilos = array();
    $ngEstilos = array(
        //"css/global.css",
        "css/angular_materia.minl.css",
        "canalesMasivos/css/cmReporteAgenteVirtual.css",
        "canalesMasivos/css/cmCanalesMasivos.css",

    );
    $Central->angular($ngModulos, $ngControladores, $ngEstilos);
    $Central->angularMenus("menuSuperior", "Canales Masivos", [], [], []);

    $losJSAutomaticos = [];
    $losJSAutomaticos[] = 'd3/liquidFillGauge.js';
    $losJSAutomaticos[] = 'functions/js/innersvg.js';
    $losJSAutomaticos[] = 'functions/vendor_echarts/echarts.min.js';
    /*$losJSAutomaticos[] = 'canalesMasivos/js/jquery-3.7.1.min.js';
    $losJSAutomaticos[] = 'canalesMasivos/js/jquery-ui.min.js';
    $losJSAutomaticos[] = 'canalesMasivos/js/wwb21.min.js';*/
    //        $losJSAutomaticos[] = 'd3/liquidFillGauge.js';
    echo $Central->loadJS($losJSAutomaticos);
    ?>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/wwb21.min.js"></script>

</head>

<body class='basicbody'>
    <cabecera></cabecera>
    <table width='100%'>
        <tr>
            <td valign='top'>
                <div ng-view></div>
            </td>
        </tr>
    </table>
    <logo-enlace></logo-enlace>
    <?
    require_once("../comunes/bottom.inc.php");
    ?>
    <?
    //_FIN_DE_ARCHIVO 
    ?>