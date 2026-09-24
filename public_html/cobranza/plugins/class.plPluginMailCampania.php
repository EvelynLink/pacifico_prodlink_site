<?php

// __PlantillaNombre__: Plantilla Para Mail de campañas
// __PlantillaModulo__: Cobranza
// __PlantillaPlugIn__: cobranza/plugins/class.plPluginMailCampania.php
// __PlantillaDescripcion__: Plantilla que sirve para el envio de mail de campañas de cobranza

require_once("../plantillas/classes/class.plBase.php");

class plPluginMailCampania extends plBase {

    protected $plantillaId;

    function __construct($plantillaId = 0) {
        if ($plantillaId > 0) {
            $this->plantillaId = $plantillaId;
        }
    }

    function enviarParametros() {

        $valores = [
            array('value' => '::nro_poliza::', 'name' => '::nro_poliza::'),
            array('value' => '::nombre_cliente::', 'name' => '::nombre_cliente::'),
            array('value' => '::cuotas_vencidas::', 'name' => '::cuotas_vencidas::'),
            array('value' => '::valor_cuota::', 'name' => '::valor_cuota::'),
            array('value' => '::dias_mora::', 'name' => '::dias_mora::'),
            array('value' => '::fecha_mas_vencida::', 'name' => '::fecha_mas_vencida::'),
            array('value' => '::dia_corte::', 'name' => '::dia_corte::'),
            array('value' => '::nro_cuotas_vencidas::', 'name' => '::nro_cuotas_vencidas::'),
            array('value' => '::ultimo_pago::', 'name' => '::ultimo_pago::'),
            array('value' => '::fecha_ultimo_pago::', 'name' => '::fecha_ultimo_pago::'),
            array('value' => '::monto_total::', 'name' => '::monto_total::'),
            array('value' => '::link_poliza::', 'name' => '::link_poliza::'),
            array('value' => '::valor_promocion::', 'name' => '::valor_promocion::'),
            array('value' => '::id_user::', 'name' => '::id_user::'),
            array('value' => '::marca::', 'name' => '::marca::'),
            array('value' => '::ultimoDiaMes::', 'name' => '::ultimoDiaMes::'),
            array('value' => '::valor_cuotaTotal::', 'name' => '::valor_cuotaTotal::'),
            array('value' => '::ultimoDiaMesAnt::', 'name' => '::ultimoDiaMesAnt::'),
            array('value' => '::ultimoDiaMesAnt2::', 'name' => '::ultimoDiaMesAnt2::')
        ];
        $cnf = getConf("Cobranza");
        $variablesReemplazo = $cnf["Variables reemplazo mail sms"];
        foreach ($variablesReemplazo as $value) {
            $partes = explode("|", $value);
            $valores[] = ['value' => $partes[0], 'name' => $partes[0]];
        }

        return $valores;
    }

    function obtenerPlantilla($plantillaId) {
        
    }

    function crearPlantilla($documento) {
        return $this->insertar($documento);
    }

    function guardar($documento) {
        return $this->insertar($documento);
    }

    function procesar($lsPlantillaId, $lsProcesoId) {

        $respuesta = array('status' => false, 'data' => '');
        $estrucuraProceso = array();
        $estrucuraProceso = parent::encuentraProceso($lsProcesoId);
        $this->familia = $estrucuraProceso['familia_id'];
        $this->procesoId = $estrucuraProceso['proceso_id'];
        $this->version = $estrucuraProceso['version_id'];
        $this->lsProcesoId = $lsProcesoId;
        $this->plantillaId = $lsPlantillaId;
        if (gettype($estrucuraProceso) == 'string') {
            return $estrucuraProceso;
        }
        if ($laPlantilla = parent::obteberPlantillaPorProcesoId($this->plantillaId)) {
            $resultadosBusqueda = array();
            $resultadosBusqueda = parent::buscarDatos($this->familia, $this->procesoId, $this->version, $this->lsProcesoId);
            foreach ($resultadosBusqueda as $key => $data) {
                $laPlantilla = str_replace($key, $data, $laPlantilla);
            }
            $respuesta = array('status' => true, 'data' => $laPlantilla);
            return $respuesta;
        } else {
            $respuesta = array('status' => false, 'data' => 'Sin plantilla configurada');
            return $respuesta;
        }
    }

}

?><?

//_FIN_DE_ARCHIVO      ?>