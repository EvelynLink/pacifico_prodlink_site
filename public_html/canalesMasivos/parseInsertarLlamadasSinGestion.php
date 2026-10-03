<?php
set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

$origen = "";
if (isset($_SERVER["DOCUMENT_ROOT"])) {
    $origen = $_SERVER["DOCUMENT_ROOT"];
}
if ($origen == "") {
    if (!isset($_SERVER["argv"][0])) {
        echo "parseInsertarLlamadasSinGestion no pudo determinar su ruta absoluta";
        exit;
    }
    $rutaAbsoluta = str_replace("public_html/canalesMasivos/parseInsertarLlamadasSinGestion.php", "", $_SERVER["argv"][0]);
    chdir(__DIR__);
    require_once($rutaAbsoluta . '_configBasico.inc.php');
    require_once($rutaAbsoluta . "comunes/classes/class.mymongodb.php");
    require_once($rutaAbsoluta . "functions/basic.php");
} else {
    require_once('../../_configBasico.inc.php');
    require_once("../comunes/classes/class.mymongodb.php");
    require_once("../functions/basic.php");
}
require_once("../cubos/plugins/cu.gestionCobranzaMysql.class.php");
require_once("../cubos/plugins/cu.gestionVentas.class.php");

/* Inserta en los cubos de gestiones las llamadas de scllamadas que no tienen gestión en
   avProgramadas, avProgramadasWhatsApp ni cbEnvioMails (quedan con cubGC_origen / cubGV_origen = 'scllamadas').

   Lee los ids del mes en lotes y descarta primero, con consultas que usan índice, las llamadas que
   ya tienen gestión. Solo las que quedan se le pasan al cubo (misma vía que process()), juntándolas
   de a LOTE, porque la revisión del cubo sobre av_detalleReintentos.evento recorre avProgramadas
   completa (esa colección no tiene lugar para más índices).

 Mes actual, los dos cubos
parseInsertarLlamadasSinGestion.php

 Un mes específico, solo un cubo
parseInsertarLlamadasSinGestion.php?mes=2026-04&cubo=cobranza

 Desde consola
php parseInsertarLlamadasSinGestion.php --mes=2026-04 --cubo=ventas */

class ParseInsertarLlamadasSinGestion
{
    private const CUBOS = ['cobranza', 'ventas', 'ambos'];
    private const LOTE  = 1000;
    private const SALTO = "<br>\n";

    // Colección de gestión => campo indexado con el scLlamadas_id. Los reintentos
    // (av_detalleReintentos.evento) se cubren con el cubo, que guarda una fila por evento.
    private const CAMPOS_LLAMADA_GESTION = [
        'avProgramadas'         => 'av_evento',
        'avProgramadasWhatsApp' => 'ws_evento',
        'cbEnvioMails'          => 'cem_susLlamadaId',
    ];
    // Cubo => prefijo de sus campos. Se revisan los dos sin importar el parámetro cubo:
    // una fila de gestión en cualquiera de ellos significa que la llamada ya tiene gestión.
    private const PREFIJOS_CUBO = [
        cuPGgestionCobranzaMysql::COLLECTION_CUBO => 'cubGC_',
        cuPGgestionVentas::COLLECTION_CUBO        => 'cubGV_',
    ];
    private const ORIGEN_LLAMADAS = 'scllamadas';

    private array $params = ['mes' => '', 'cubo' => 'ambos'];
    // Segundos por colección del prefiltro: del último lote y acumulados en la corrida.
    private array $tiemposLote = [];
    private array $tiemposPrefiltro = [];

    public function __construct()
    {
        $this->params['mes'] = date('Y-m');
        $this->auxLeerParametros();
    }

    public function ejecutar(): void
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $this->params['mes'], $m) || (int)$m[2] < 1 || (int)$m[2] > 12) {
            echo "ERROR: parametro mes invalido. Formato esperado YYYY-MM." . self::SALTO;
            return;
        }
        if (!in_array($this->params['cubo'], self::CUBOS, true)) {
            echo "ERROR: parametro cubo invalido. Valores: " . implode(', ', self::CUBOS) . "." . self::SALTO;
            return;
        }
        $this->auxPrepararSalida();

        $desde = mktime(0, 0, 0, (int)$m[2], 1, (int)$m[1]);
        $hasta = mktime(0, 0, 0, (int)$m[2] + 1, 1, (int)$m[1]);
        $this->auxEnviar("Llamadas creadas entre " . date('Y-m-d', $desde) . " y " . date('Y-m-d', $hasta - 1));

        $cubos = [];
        if ($this->params['cubo'] !== 'ventas') {
            $cubos[cuPGgestionCobranzaMysql::COLLECTION_CUBO] = new cuPGgestionCobranzaMysql();
        }
        if ($this->params['cubo'] !== 'cobranza') {
            $cubos[cuPGgestionVentas::COLLECTION_CUBO] = new cuPGgestionVentas();
        }
        $totales = array_fill_keys(array_keys($cubos), ['segundos' => 0.0]);

        $inicioTotal = microtime(true);
        $inicio = microtime(true);
        $ultimoId = $this->auxIdAnteriorA($desde);
        $this->auxEnviar("Primera llamada del mes: " . round(microtime(true) - $inicio, 1) . " seg");
        if ($ultimoId === null) {
            $this->auxEnviar("No hay llamadas en el rango.");
            echo "EJECUCION_COMPLETA";
            return;
        }

        $segundosLectura = 0.0;
        $segundosPrefiltro = 0.0;
        $idsLeidos = 0;
        $idsConGestion = 0;
        $numLote = 0;
        $numEnvio = 0;
        $pendientes = [];
        do {
            $inicio = microtime(true);
            $lote = $this->auxLeerIds($desde, $hasta, $ultimoId);
            $segLectura = microtime(true) - $inicio;
            $segundosLectura += $segLectura;
            if (empty($lote)) {
                break;
            }
            $numLote++;
            $idsLeidos += count($lote);
            $ids = array_map('intval', array_column($lote, 'scLlamadas_id'));
            $ultimoId = (int)end($ids);
            $fechas = array_column($lote, 'scLlamadas_fechaCreacion');

            $inicio = microtime(true);
            $conGestion = $this->auxIdsConGestion($ids);
            $segPrefiltro = microtime(true) - $inicio;
            $segundosPrefiltro += $segPrefiltro;
            $idsConGestion += count($conGestion);
            foreach ($ids as $id) {
                if (!isset($conGestion[$id])) {
                    $pendientes[] = $id;
                }
            }

            $this->auxEnviar("Lote " . $numLote . ": " . count($ids) . " ids, del "
                . date('Y-m-d H:i', (int)min($fechas)) . " al " . date('Y-m-d H:i', (int)max($fechas))
                . " | con gestion " . count($conGestion) . " | pendientes acumuladas " . count($pendientes)
                . " (lectura " . round($segLectura, 1) . " seg, prefiltro " . round($segPrefiltro, 1) . " seg: "
                . $this->auxTextoTiempos($this->tiemposLote) . ")");

            if (count($pendientes) >= self::LOTE) {
                $numEnvio++;
                $totales = $this->auxEnviarAlCubo($cubos, $pendientes, $totales, $numEnvio);
                $pendientes = [];
            }
        } while (count($lote) === self::LOTE);

        if (!empty($pendientes)) {
            $numEnvio++;
            $totales = $this->auxEnviarAlCubo($cubos, $pendientes, $totales, $numEnvio);
        }

        $this->auxEnviar(self::SALTO . "TOTAL: " . $idsLeidos . " ids en " . $numLote . " lotes, "
            . round(microtime(true) - $inicioTotal, 1) . " seg");
        $this->auxEnviar("&nbsp;&nbsp;Descartadas por el parse (ya tienen gestion): " . $idsConGestion
            . " | lectura de scllamadas " . round($segundosLectura, 1) . " seg | prefiltro " . round($segundosPrefiltro, 1) . " seg ("
            . $this->auxTextoTiempos($this->tiemposPrefiltro) . ")");
        foreach ($totales as $nombre => $total) {
            $segundos = $total['segundos'];
            unset($total['segundos']);
            $this->auxEnviar("&nbsp;&nbsp;" . $nombre . ": " . $this->auxTextoResumen($total) . " | " . round($segundos, 1) . " seg");
        }
        echo "EJECUCION_COMPLETA";
    }

    /**
     * Le pasa al cubo un grupo de llamadas que pasaron el prefiltro y suma sus contadores al total.
     *
     * @param array $cubos      Cubos a procesar (nombre => objeto plugin).
     * @param int[] $pendientes scLlamadas_id sin gestión según el prefiltro.
     * @param array $totales    Totales por cubo.
     * @param int   $numEnvio   Número de envío, para la salida.
     * @return array Totales actualizados.
     */
    private function auxEnviarAlCubo(array $cubos, array $pendientes, array $totales, int $numEnvio): array
    {
        $this->auxEnviar("Envio " . $numEnvio . " al cubo: " . count($pendientes) . " llamadas");
        foreach ($cubos as $nombre => $cubo) {
            $inicio = microtime(true);
            $resumen = $cubo->insertarLlamadasSinGestion(0, 0, $pendientes);
            $segundos = microtime(true) - $inicio;
            $totales[$nombre] = $this->auxAcumular($totales[$nombre], $resumen, $segundos);
            $this->auxEnviar("&nbsp;&nbsp;" . $nombre . ": " . $this->auxTextoResumen($resumen) . " | " . round($segundos, 1) . " seg");
        }
        return $totales;
    }

    /**
     * Ids del lote que ya tienen gestión, buscados solo por campos con índice: el id de la llamada en
     * avProgramadas, avProgramadasWhatsApp y cbEnvioMails, y las filas de gestión de los cubos
     * (que incluyen los reintentos). Solo descarta llamadas con gestión; la decisión final la toma el cubo.
     *
     * @param int[] $ids scLlamadas_id del lote.
     * @return array<int, true>
     */
    private function auxIdsConGestion(array $ids): array
    {
        // El id puede estar guardado como número o como texto según la colección.
        $variantes = array_merge($ids, array_map('strval', $ids));
        $conGestion = [];
        $mdb = new MYMONGODB();

        // Los segundos de cada consulta se guardan por colección, para ver cuál no usa índice.
        $this->tiemposLote = [];
        foreach (self::CAMPOS_LLAMADA_GESTION as $coleccion => $campo) {
            $inicio = microtime(true);
            $mdb->buscar($coleccion, [$campo => ['$in' => $variantes]], [$campo]);
            while ($doc = $mdb->siguiente()) {
                $conGestion[(int)($doc[$campo] ?? 0)] = true;
            }
            $this->auxSumarTiempo($coleccion, microtime(true) - $inicio);
        }
        foreach (self::PREFIJOS_CUBO as $coleccion => $prefijo) {
            $inicio = microtime(true);
            $mdb->buscar($coleccion, [
                $prefijo . 'llamadaId' => ['$in' => $ids],
                $prefijo . 'origen'    => ['$ne' => self::ORIGEN_LLAMADAS],
            ], [$prefijo . 'llamadaId']);
            while ($doc = $mdb->siguiente()) {
                $conGestion[(int)($doc[$prefijo . 'llamadaId'] ?? 0)] = true;
            }
            $this->auxSumarTiempo($coleccion, microtime(true) - $inicio);
        }
        unset($conGestion[0]);
        return $conGestion;
    }

    // Suma el tiempo de una consulta del prefiltro al lote actual y al total de la corrida.
    private function auxSumarTiempo(string $coleccion, float $segundos): void
    {
        $this->tiemposLote[$coleccion] = $segundos;
        $this->tiemposPrefiltro[$coleccion] = ($this->tiemposPrefiltro[$coleccion] ?? 0) + $segundos;
    }

    // Texto con los segundos por colección, ej. "avProgramadas 0.2, cbEnvioMails 3.8".
    private function auxTextoTiempos(array $tiempos): string
    {
        $partes = [];
        foreach ($tiempos as $coleccion => $segundos) {
            $partes[] = $coleccion . " " . round($segundos, 1);
        }
        return implode(", ", $partes);
    }

    // Lee los parámetros desde la consola (--nombre=valor) o desde la URL.
    private function auxLeerParametros(): void
    {
        global $argv;
        foreach (array_keys($this->params) as $nombre) {
            if (php_sapi_name() === 'cli') {
                foreach (($argv ?? []) as $arg) {
                    if (strpos($arg, '--' . $nombre . '=') === 0) {
                        $this->params[$nombre] = trim(substr($arg, strlen('--' . $nombre . '=')));
                    }
                }
                continue;
            }
            if (isset($_GET[$nombre]) && $_GET[$nombre] !== '') {
                $this->params[$nombre] = trim((string)$_GET[$nombre]);
            }
        }
    }

    // Apaga los buffers para que el navegador reciba cada lote apenas termina.
    private function auxPrepararSalida(): void
    {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            // Evita que el proxy (nginx) retenga la salida hasta el final.
            header('X-Accel-Buffering: no');
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private function auxEnviar(string $texto): void
    {
        echo $texto . self::SALTO;
        flush();
    }

    /**
     * Id anterior a la primera llamada creada desde $desde, para empezar el recorrido por id.
     *
     * @return int|null null si no hay llamadas desde esa fecha.
     */
    private function auxIdAnteriorA(int $desde): ?int
    {
        $db = new MYSQLDB();
        $db->query($db->mkSQL("SELECT MIN(scLlamadas_id) AS primerId FROM scllamadas WHERE scLlamadas_fechaCreacion >= %N", $desde));
        $row = $db->fetchRow();
        if (empty($row['primerId'])) {
            return null;
        }
        return (int)$row['primerId'] - 1;
    }

    /**
     * Siguiente lote de ids creados en el rango que pasan los filtros de tipificación.
     * Son los mismos de auxLeerLlamadas() en los cubos: aquí solo evitan leer y acumular
     * llamadas que el cubo igual descartaría; el cubo los vuelve a aplicar.
     *
     * @return array<int, array{scLlamadas_id:string, scLlamadas_fechaCreacion:string}>
     */
    private function auxLeerIds(int $desde, int $hasta, int $despuesDeId): array
    {
        $db = new MYSQLDB();
        $sql = $db->mkSQL(
            "SELECT scLlamadas_id, scLlamadas_fechaCreacion FROM scllamadas
             WHERE scLlamadas_id > %N AND scLlamadas_fechaCreacion >= %N AND scLlamadas_fechaCreacion < %N
               AND IFNULL(scLlamadas_ruta1, '') NOT IN ('', 'Conexión exitosa')
               AND IFNULL(scLlamadas_ruta2, '') <> 'Mail No Enviado'
               AND IFNULL(scLlamadas_texto, '') NOT LIKE 'Llamada desprogramada%%'
               AND IFNULL(scLlamadas_texto, '') NOT IN ('WHATSAPP NO ENVIADO', 'WHATSAPP CON ERROR')
             ORDER BY scLlamadas_id
             LIMIT %N",
            $despuesDeId,
            $desde,
            $hasta,
            self::LOTE
        );
        $db->query($sql);
        $lote = [];
        while ($row = $db->fetchRow()) {
            $lote[] = $row;
        }
        return $lote;
    }

    // Suma los contadores de un envío al total del cubo.
    private function auxAcumular(array $total, array $resumen, float $segundos): array
    {
        foreach ($resumen as $clave => $valor) {
            $total[$clave] = ($total[$clave] ?? 0) + $valor;
        }
        $total['segundos'] += $segundos;
        return $total;
    }

    private function auxTextoResumen(array $resumen): string
    {
        $partes = [];
        foreach ($resumen as $clave => $valor) {
            $partes[] = $clave . "=" . $valor;
        }
        return implode(", ", $partes);
    }
}

(new ParseInsertarLlamadasSinGestion())->ejecutar();
// _FIN_DE_ARCHIVO
