<?php

/**
 * Objeto auxiliar que recibe un query de MongoDB y permite la creación y
 * personalización de una tabla de datos con el formato de API apropiado para
 * la directiva angular "tabula".
 */
class coTabulaMongo {

    /**
     * Equivalencias para búsqueda insensible a tildes.
     * Cada letra se sustituye por una clase de caracteres en el regex.
     */
    private const CLASES_REGEX = [
        "a" => "[aáãà]", "á" => "[aáãà]", "ã" => "[aáãà]", "à" => "[aáãà]",
        "e" => "[eéè]",  "é" => "[eéè]",  "è" => "[eéè]",
        "i" => "[ií]",   "í" => "[ií]",
        "o" => "[oóõ]",  "ó" => "[oóõ]",  "õ" => "[oóõ]",
        "u" => "[uúü]",  "ú" => "[uúü]",  "ü" => "[uúü]",
        "c" => "[cç]",   "ç" => "[cç]",
        "n" => "[nñ]",   "ñ" => "[nñ]",
    ];

    /** Caracteres que romperían un CSV y con qué se reemplazan. */
    private const CSV_BUSCA     = ["\n", "\r", ";"];
    private const CSV_REEMPLAZA = [" ",  " ",  ","];

    /** Separador de columnas del CSV generado. */
    private const CSV_SEPARADOR = ";";

    /** Semilla para los nombres de archivo aleatorios. */
    private const SEMILLA_ARCHIVO = "rU7nkLaqwie0)ofsjawWEe";

    private $input;
    private $limpiador = [];
    private $queryDatos;
    private $preparaDatos;
    private $preparaDatosExportar;
    private $preparaDatosCabeceraExportar;
    private $preparaDatosEjecutar;
    private $ejecutor = []; //array de ejecutores externos
    private $exportable = false;
    private $filename = '';
    private $tipo = 'csv';
    private $nombreHoja = "";
    private $tituloHoja = "";
    private $colorCabecera = "";
    private $debug = false;
    private $extra;
    private $filtroRestrict = [];
    private $activarTotalAgregado = false;

    public function __construct() {
        $this->ejecutor = []; //ejecutores en blanco
    }

    // =========================================================================
    // Configuración
    // =========================================================================

    /**
     * Permite imprimir en pantalla el SQL que construye tabula.
     *
     * @param bool $bool TRUE permite hacer debugging
     * @return void
     */
    public function setDebug($bool) {
        $this->debug = (bool) $bool;
    }

    /**
     * @param array $d Entrada recibida desde Angular
     * @return void
     */
    public function setInput($d) {
        if (!is_array($d)) {
            trigger_error("setInput debe recibir un array como input", E_USER_ERROR);
            exit;
        }
        $this->input = $d;
    }

    /**
     * @param bool $activar Si el aggregate debe contar el total (pipeline extra)
     * @return void
     */
    public function setActivarTotalAgregado($activar) {
        $this->activarTotalAgregado = $activar;
    }

    /**
     * Diccionario de alias de campos: `nombreReal => nombrePublico`.
     *
     * @param array $limpiar
     * @return void
     */
    public function setLimpiador($limpiar) {
        if (!is_array($limpiar)) {
            trigger_error("setLimpiador debe recibir un array como input", E_USER_ERROR);
            exit;
        }
        if (count(array_values($limpiar)) != count(array_unique(array_values($limpiar)))) {
            trigger_error("setLimpiador debe recibir un array sin valores duplicados", E_USER_ERROR);
            exit;
        }
        foreach (array_values($limpiar) as $k) {
            if (strlen(trim($k)) < 3) {
                trigger_error("setLimpiador debe recibir un array con valores de por lo menos 3 caracteres cada uno", E_USER_ERROR);
                exit;
            }
        }

        $this->limpiador = $limpiar;
    }

    /**
     * Inicializa el tabulaMongo con un find.
     *
     * @param string $coleccion La tabla a operar
     * @param array  $criterios Opciones de filtrado que se desea utilizar
     * @param array  $campos    Campos que se quiere mostrar
     * @param array  $orderBy   Ordenamiento por defecto
     * @return void
     */
    public function setQueryDatos($coleccion, $criterios = [], $campos = [], $orderBy = []) {
        if (!is_string($coleccion)) {
            trigger_error("setQueryDatos debe recibir un string como coleccion", E_USER_ERROR);
            exit;
        }
        if (isset($criterios) && !is_array($criterios)) {
            trigger_error("setQueryDatos debe recibir un array como criterios", E_USER_ERROR);
            exit;
        }
        if (isset($campos) && !is_array($campos)) {
            trigger_error("setQueryDatos debe recibir un array como campos", E_USER_ERROR);
            exit;
        }
        $this->queryDatos = [
            "tipo" => "find",
            "coleccion" => $coleccion,
            "criterios" => $criterios,
            "campos" => $campos,
            "orderBy" => $orderBy,
        ];
    }

    /**
     * Inicializa el tabulaMongo con un aggregate en lugar de un find.
     *
     * @param string $coleccion La tabla a operar
     * @param array  $pipelines Etapas de agregación
     * @param array  $orderBy   Ordenamiento por defecto
     * @return void
     */
    public function setAggregate($coleccion, $pipelines, $orderBy = []) {
        if (!is_string($coleccion)) {
            trigger_error("setAggregate debe recibir un string como coleccion", E_USER_ERROR);
            exit;
        }
        if (!is_array($pipelines) || count($pipelines) == 0) {
            trigger_error("setAggregate debe recibir un array como pipelines", E_USER_ERROR);
            exit;
        }
        $this->queryDatos = [
            "tipo" => "aggregate",
            "coleccion" => $coleccion,
            "pipelines" => $pipelines,
            "criterios" => [],
            "orderBy" => $orderBy,
        ];
    }

    /**
     * Determina si este tabula puede o no exportar, aun si en el lado angular
     * se ha dibujado la directiva tabula-exportar.
     *
     * @param bool   $permite
     * @param string $nombreArchivo         Nombre del archivo
     * @param string $tipo                  `csv` (por defecto), `coGeneraExcel` o xlsx
     * @param string $nombreHojaGeneraExcel
     * @param string $tituloGeneraExcel
     * @param string $colorGeneraExcel
     * @return void
     */
    public function permiteExportar($permite = false, $nombreArchivo = '', $tipo = 'csv', $nombreHojaGeneraExcel = '', $tituloGeneraExcel = '', $colorGeneraExcel = '') {
        $this->exportable = expect_boolean($permite);
        $this->filename = expect_pure_alphanumeric($nombreArchivo);
        $this->tipo = expect_pure_alphanumeric($tipo);
        $this->nombreHoja = expect_pure_alphanumeric($nombreHojaGeneraExcel);
        $this->tituloHoja = expect_pure_alphanumeric($tituloGeneraExcel);
        $this->colorCabecera = expect_safe_html($colorGeneraExcel);
    }

    /**
     * Función que se ejecutará antes de enviar los datos a Angular.
     *
     * @param callable $func Recibe un array $fila y devuelve la fila procesada
     * @return void
     */
    public function setPreparaDatos($func) {
        $this->preparaDatos = $func;
    }

    /**
     * Función que se ejecutará antes de exportar cada fila.
     *
     * @param callable $func Recibe un array $fila y devuelve la fila procesada
     * @return void
     */
    public function setPreparaDatosExportar($func) {
        $this->preparaDatosExportar = $func;
    }

    /**
     * Función que se ejecutará sobre cada nombre de columna al exportar.
     *
     * @param callable $func Recibe la clave de la columna y devuelve el título
     * @return void
     */
    public function setPreparaDatosCabeceraExportar($func) {
        $this->preparaDatosCabeceraExportar = $func;
    }

    /**
     * Función que se ejecutará sobre cada fila antes de invocar un ejecutor.
     *
     * @param callable $func Recibe un array $fila y devuelve la fila procesada
     * @return void
     */
    public function setPreparaDatosEjecutar($func) {
        $this->preparaDatosEjecutar = $func;
    }

    /**
     * Registra una función asociada a un botón (ver directiva tabula-ejecutar).
     *
     * @param string   $identificador Nombre único, solo alfanumérico
     * @param callable $func          Recibe `(array $datos, array $titulos, mixed $extra)`
     * @return void
     */
    public function setEjecutor($identificador, $func) {
        $identificadorLimpio = expect_pure_alphanumeric($identificador);
        if ($identificadorLimpio == $identificador) {
            $this->ejecutor[$identificador] = $func;
        }
    }

    /**
     * Envía cualquier dato adicional adjunto al dataset. Estará disponible para
     * Angular en la variable designada en el atributo datos-adicionales.
     *
     * @param mixed $datos Cualquier estructura serializable a json
     * @return void
     */
    public function setExtra($datos) {
        $this->extra = $datos;
    }

    /**
     * Agrega filtros a la información consultada averiguando la asignación del
     * usuario logeado y la empresa relacionada al dominio del mismo.
     *
     * @param array $campos     Campos por los cuales se filtrará
     * @param bool  $aplicaInt  Castear los ids de empresa a int
     * @return void
     */
    public function setCamposFiltroRestrinctivo($campos, $aplicaInt = true) {
        global $Central;
        $this->filtroRestrict = [];
        if ($Central->hasDomain("Link")) {
            return;
        }

        $dominiosPermitidos = isset($_SESSION[MID . "inforestrict"]) ? expect_safe_html($_SESSION[MID . "inforestrict"]) : [];
        $empresas = [];
        foreach ($dominiosPermitidos as $dom) {
            if (empty($dom["empresaId"])) {
                trigger_error("TabulaMongo::setCamposFiltroRestrinctivo, se intenta prefiltrar información con empresa no configurada. Por favor configure la empresa del dominio: " . $dom["dominioId"] . " y recargar permisos.");
            }
            $empresas[$dom["empresaId"]] = $dom["empresaId"];
        }

        $empresasId = [];
        foreach ($empresas as $empId) {
            $empresasId[] = $aplicaInt ? (int) $empId : $empId;
        }

        if (count($empresasId) == 0) {
            return;
        }

        $camposFiltrados = [];
        foreach ($campos as $campo) {
            $camposFiltrados[] = [$campo => ['$in' => $empresasId]];
        }
        $this->filtroRestrict = ['$or' => $camposFiltrados];
    }

    /**
     * Respuesta vacía, con la misma forma que una consulta normal sin resultados.
     *
     * @return array
     */
    public function respondeVacio() {
        return [
            "cuantos" => 0,
            "filas" => [],
            "extra" => [],
        ];
    }

    // =========================================================================
    // Punto de entrada
    // =========================================================================

    /**
     * Ejecuta el query configurado y devuelve el resultado en el formato que
     * espera tabulaAngular, o genera un archivo de exportación, según la
     * entrada recibida.
     *
     * Lee la paginación, el orden y los filtros desde `$this->input`, los
     * traduce a criterios de MongoDB y los mezcla con
     * `queryDatos["criterios"]` y `$this->filtroRestrict`. Los nombres de campo
     * se destraducen con `$this->limpiador` (alias público ? nombre real) antes
     * de consultar.
     *
     * Claves de `$this->input` que interpreta:
     * - `pagina`      int    Página solicitada, mínimo 1. Por defecto 1.
     * - `tamanio`     int    Registros por página, acotado a 1..1000. Por defecto 10.
     * - `ordenapor`   string Uno o varios campos separados por espacio.
     * - `reversa`     bool   Invierte el orden.
     * - `filtro`      array  Lista de objetos `{campo, filtro, filtro2, tipo}`, donde
     *                        `tipo` es `free` (regex insensible a tildes), `timestamp`
     *                        (rango de fechas) o `range` (rango numérico).
     * - `exigeFiltro` bool   Si es true y no se aplicó ningún filtro, corta y
     *                        devuelve `respondeVacio()`.
     * - `exporta`     bool   Genera archivo en lugar de devolver filas. Requiere
     *                        `$this->exportable`.
     * - `ejecuta`     string Clave de `$this->ejecutor` a invocar con todo el
     *                        resultado, sin paginar.
     *
     * @return array Una de estas formas:
     *               - `["cuantos" => int, "filas" => array, "extra" => mixed]` y, si
     *                 `$this->debug`, además `"query" => array`.
     *               - `["cuantos" => int, "archivo" => string]` al exportar.
     *               - `["respuesta" => mixed]` al ejecutar una acción externa.
     *               - `["errores" => string]` si no hay registros que exportar o
     *                 falla la creación del archivo.
     *
     * @see setPreparaDatosCabeceraExportar()
     * @see respondeVacio()
     *
     * @internal Emite E_USER_ERROR y termina el script si el objeto no fue
     *           inicializado con `input` y `queryDatos`, o si
     *           `queryDatos["tipo"]` no es `find` ni `aggregate`.
     */
    public function responde() {
        if (!isset($this->input) || !isset($this->queryDatos)) {
            trigger_error("responde() no puede ejecutarse pues el objeto coTabulaMongo no está debidamente inicializado", E_USER_ERROR);
            exit;
        }

        $pagina    = $this->leePagina();
        $tamanio   = $this->leeTamanio();
        $ordenapor = $this->aNombreReal($this->leeOrdenapor());
        $reversa   = isset($this->input["reversa"]) ? expect_boolean($this->input["reversa"]) : false;
        $filtros   = $this->filtrosANombreReal($this->leeFiltro());

        $dbm = new MYMONGODB();

        list($nuevosFiltros, $cuentaFiltros) = $this->construyeFiltros($filtros);

        if (isset($this->input["exigeFiltro"]) && $this->input["exigeFiltro"] && $cuentaFiltros == 0) {
            return $this->respondeVacio();
        }

        $this->queryDatos["criteriosCompletos"] = $this->mezclaCriterios($nuevosFiltros);
        $this->aplicaOrden($ordenapor, $reversa);

        //averigue el registro inicial
        $desde = ($pagina - 1) * $tamanio;

        if ($this->exportable && isset($this->input["exporta"]) && $this->input["exporta"]) {
            return $this->exporta($dbm);
        }
        if (isset($this->input["ejecuta"]) && $this->input["ejecuta"]) {
            return $this->ejecutaAccion($dbm);
        }
        return $this->respuestaPaginada($dbm, $tamanio, $desde);
    }

    // =========================================================================
    // Lectura de la entrada
    // =========================================================================

    /** @return int Página solicitada, mínimo 1 */
    private function leePagina() {
        if (!isset($this->input["pagina"])) {
            return 1;
        }
        return max(1, floor(expect_float($this->input["pagina"])));
    }

    /** @return int Registros por página, acotado a 1..1000 */
    private function leeTamanio() {
        if (!isset($this->input["tamanio"])) {
            return 10;
        }
        return max(1, min(1000, expect_integer($this->input["tamanio"])));
    }

    /** @return string Campos de ordenamiento separados por espacio */
    private function leeOrdenapor() {
        if (isset($this->input["ordenapor"]) && $this->input["ordenapor"] != "") {
            return expect_safe_html($this->input["ordenapor"]);
        }
        return "";
    }

    /** @return array Lista de descriptores de filtro */
    private function leeFiltro() {
        if (isset($this->input["filtro"])) {
            return expect_safe_html($this->input["filtro"]);
        }
        return [];
    }

    // =========================================================================
    // Traducción de nombres de campo (limpiador)
    // =========================================================================

    /**
     * Traduce alias público ? nombre real de campo.
     *
     * @param string $texto
     * @return string
     */
    private function aNombreReal($texto) {
        if (!count($this->limpiador)) {
            return $texto;
        }
        return str_replace(array_values($this->limpiador), array_keys($this->limpiador), $texto);
    }

    /**
     * Traduce nombre real ? alias público de campo.
     *
     * @param string $texto
     * @return string
     */
    private function aNombrePublico($texto) {
        if (!count($this->limpiador)) {
            return $texto;
        }
        return str_replace(array_keys($this->limpiador), array_values($this->limpiador), $texto);
    }

    /**
     * Aplica aNombreReal() al campo de cada descriptor de filtro.
     *
     * @param array $filtro
     * @return array
     */
    private function filtrosANombreReal($filtro) {
        if (!count($this->limpiador)) {
            return $filtro;
        }
        $limpios = [];
        foreach ($filtro as $obj) {
            $obj["campo"] = $this->aNombreReal($obj["campo"]);
            $limpios[] = $obj;
        }
        return $limpios;
    }

    // =========================================================================
    // Construcción de criterios de Mongo
    // =========================================================================

    /**
     * Traduce los descriptores de filtro de la directiva a criterios de Mongo.
     *
     * @param array $descriptores
     * @return array `[array $criterios, int $cuantosFiltrosAplicados]`
     */
    private function construyeFiltros($descriptores) {
        $filtros = [];
        $cuenta = 0;

        foreach ($descriptores as $obj) {
            if (!isset($obj["tipo"])) {
                $obj["tipo"] = "free";
            }
            switch ($obj["tipo"]) {
                case "free":
                    $this->filtroTexto($obj, $filtros, $cuenta);
                    break;
                case "timestamp":
                    $this->filtroFechas($obj, $filtros, $cuenta);
                    break;
                case "range":
                    $this->filtroRango($obj, $filtros, $cuenta);
                    break;
            }
        }

        return [$filtros, $cuenta];
    }

    /**
     * Filtro de texto libre: regex insensible a mayúsculas y tildes.
     *
     * @param array $obj
     * @param array $filtros Por referencia, se le agregan los criterios
     * @param int   $cuenta  Por referencia, contador de filtros aplicados
     * @return void
     */
    private function filtroTexto($obj, &$filtros, &$cuenta) {
        if ($obj["filtro"] == "") {
            return;
        }
        $cuenta++;

        $patron = $this->patronInsensible($obj["filtro"]);

        //encode utf-8
        if (ENCODING != "UTF-8") {
            $patron = iconv(ENCODING, "UTF-8", $patron);
        }

        //vino uno o varios campos?
        $campos   = explode(" ", trim($obj["campo"]));
        $palabras = explode(" ", trim($patron));

        if (count($campos) > 1) {
            // OJO: comportamiento original — con varios campos se descartan los
            // criterios acumulados por descriptores anteriores.
            $condiciones = [];
            for ($i = 0; $i < count($palabras); $i++) {
                $orAr = ['$or' => []];
                for ($j = 0; $j < count($campos); $j++) {
                    $orAr['$or'][] = [$campos[$j] => ['$regex' => $palabras[$i], '$options' => 'i']];
                }
                $condiciones[] = $orAr;
            }
            $filtros = ['$and' => $condiciones];
            return;
        }

        foreach ($campos as $campo) {
            if ($campo != "") {
                $filtros[$campo] = ['$regex' => $patron, '$options' => 'i'];
            }
        }
    }

    /**
     * Filtro de rango de fechas sobre timestamps.
     *
     * @param array $obj
     * @param array $filtros Por referencia
     * @param int   $cuenta  Por referencia
     * @return void
     */
    private function filtroFechas($obj, &$filtros, &$cuenta) {
        if (!empty($obj["filtro"]) && strtotime($obj["filtro"]) !== false) {
            $cuenta++;
            $fecha = strtotime($obj["filtro"]);
            $this->aplicaCota($filtros, $obj["campo"], '$gte', strtotime(date('Y-m-d ', $fecha) . ' 00:00:00'));
        }
        if (isset($obj["filtro2"]) && $obj["filtro2"] != "" && strtotime($obj["filtro2"]) !== false) {
            $cuenta++;
            $fecha = strtotime($obj["filtro2"]);
            $this->aplicaCota($filtros, $obj["campo"], '$lte', strtotime(date('Y-m-d ', $fecha) . ' 23:59:59'));
        }
    }

    /**
     * Filtro de rango numérico.
     *
     * @param array $obj
     * @param array $filtros Por referencia
     * @param int   $cuenta  Por referencia
     * @return void
     */
    private function filtroRango($obj, &$filtros, &$cuenta) {
        if ($obj["filtro"] != "" && is_numeric($obj["filtro"])) {
            $cuenta++;
            $this->aplicaCota($filtros, $obj["campo"], '$gte', floatval($obj["filtro"]));
        }
        if (isset($obj["filtro2"]) && $obj["filtro2"] != "" && is_numeric($obj["filtro2"])) {
            $cuenta++;
            $this->aplicaCota($filtros, $obj["campo"], '$lte', floatval($obj["filtro2"]));
        }
    }

    /**
     * Aplica una cota ($gte / $lte) a uno o varios campos separados por espacio.
     *
     * @param array  $filtros   Por referencia
     * @param string $campos
     * @param string $operador  `$gte` o `$lte`
     * @param mixed  $valor
     * @return void
     */
    private function aplicaCota(&$filtros, $campos, $operador, $valor) {
        //vino uno o varios campos?
        foreach (explode(" ", trim($campos)) as $campo) {
            if ($campo != "") {
                $filtros[$campo][$operador] = $valor;
            }
        }
    }

    /**
     * Convierte un texto en un patrón regex que ignora tildes.
     *
     * @param string $texto
     * @return string
     */
    private function patronInsensible($texto) {
        $patron = "";
        for ($i = 0; $i < strlen($texto); $i++) {
            $letra = strtolower(substr($texto, $i, 1));
            $patron .= isset(self::CLASES_REGEX[$letra]) ? self::CLASES_REGEX[$letra] : $letra;
        }
        return $patron;
    }

    /**
     * Mezcla los filtros de la directiva con los criterios fijos del query y
     * con el filtro restrictivo por empresa.
     *
     * @param array $delUsuario
     * @return array
     */
    private function mezclaCriterios($delUsuario) {
        if (count($delUsuario) > 0) {
            $criterios = array_merge($delUsuario, $this->queryDatos["criterios"]);
        } else {
            $criterios = $this->queryDatos["criterios"];
        }
        if (count($this->filtroRestrict) > 0) {
            $criterios = array_merge($criterios, $this->filtroRestrict);
        }
        return $criterios;
    }

    /**
     * Sobrescribe queryDatos["orderBy"] si la directiva pidió un orden.
     *
     * @param string $ordenapor Campos separados por espacio
     * @param bool   $reversa
     * @return void
     */
    private function aplicaOrden($ordenapor, $reversa) {
        $direccion = $reversa ? -1 : 1;
        $orden = [];
        foreach (explode(" ", trim($ordenapor)) as $campo) {
            if ($campo != "") {
                $orden[$campo] = $direccion;
            }
        }
        if (count($orden) > 0) {
            $this->queryDatos["orderBy"] = $orden;
        }
    }

    // =========================================================================
    // Ejecución del query
    // =========================================================================

    /**
     * Abre el cursor según el tipo de query configurado.
     *
     * @param MYMONGODB $dbm
     * @param int|null  $tamanio Null para traer todo el resultado
     * @param int|null  $desde
     * @return mixed Lo que devuelva buscar()/agregar(), normalmente el total
     */
    private function abreCursor($dbm, $tamanio = null, $desde = null) {
        switch ($this->queryDatos["tipo"]) {
            case "find":
                if ($tamanio === null) {
                    return $dbm->buscar($this->queryDatos["coleccion"], $this->queryDatos["criteriosCompletos"], $this->queryDatos["campos"], $this->queryDatos["orderBy"]);
                }
                return $dbm->buscar($this->queryDatos["coleccion"], $this->queryDatos["criteriosCompletos"], $this->queryDatos["campos"], $this->queryDatos["orderBy"], $tamanio, $desde);
            case "aggregate":
                if ($tamanio === null) {
                    return $dbm->agregar($this->queryDatos["coleccion"], $this->queryDatos["pipelines"], $this->queryDatos["criteriosCompletos"], $this->queryDatos["orderBy"]);
                }
                return $dbm->agregar($this->queryDatos["coleccion"], $this->queryDatos["pipelines"], $this->queryDatos["criteriosCompletos"], $this->queryDatos["orderBy"], $tamanio, $desde);
            default:
                trigger_error("responde() no puede ejecutarse pues el objeto coTabulaMongo no está debidamente inicializado", E_USER_ERROR);
                return 0;
        }
    }

    /**
     * Cuenta el total de registros que cumplen los criterios.
     *
     * En aggregate el conteo exige un pipeline extra, así que solo se hace si
     * `$this->activarTotalAgregado` está activo; en caso contrario devuelve -1.
     *
     * @param MYMONGODB $dbm
     * @return int
     */
    private function cuentaTotal($dbm) {
        switch ($this->queryDatos["tipo"]) {
            case "find":
                return $dbm->buscar($this->queryDatos["coleccion"], $this->queryDatos["criteriosCompletos"], $this->queryDatos["campos"]);
            case "aggregate":
                if (!$this->activarTotalAgregado) {
                    return -1;
                }
                $pipelinesConteo = $this->queryDatos["pipelines"];
                $pipelinesConteo[] = ['$count' => 'total'];

                $resConteo = $dbm->agregar($this->queryDatos["coleccion"], $pipelinesConteo);

                // Extraemos el número del Iterador
                $datosConteo = iterator_to_array($resConteo);
                return (!empty($datosConteo)) ? $datosConteo[0]['total'] : 0;
            default:
                trigger_error("responde() no puede ejecutarse pues el objeto coTabulaMongo no está debidamente inicializado", E_USER_ERROR);
                return 0;
        }
    }

    // =========================================================================
    // Modo consulta normal
    // =========================================================================

    /**
     * Consulta paginada para la grilla de Angular.
     *
     * @param MYMONGODB $dbm
     * @param int       $tamanio
     * @param int       $desde
     * @return array
     */
    private function respuestaPaginada($dbm, $tamanio, $desde) {
        //cuente
        $cuantos = $this->cuentaTotal($dbm);
        //ejecute el query
        $this->abreCursor($dbm, $tamanio, $desde);

        $filas = [];
        while ($row = $dbm->siguiente()) {
            if (is_callable($this->preparaDatos)) {
                $row = call_user_func($this->preparaDatos, $row);
            }
            $filas[] = $row;
        }

        $r = [
            "cuantos" => $cuantos,
            "filas" => $filas,
            "extra" => $this->extra,
        ];
        if ($this->debug) {
            $r["query"] = $this->queryDatos;
        }
        return $r;
    }

    // =========================================================================
    // Modo ejecutar acción externa
    // =========================================================================

    /**
     * Recorre todo el resultado (sin paginar) y lo entrega al ejecutor pedido.
     *
     * @param MYMONGODB $dbm
     * @return array `["respuesta" => mixed]`
     */
    private function ejecutaAccion($dbm) {
        $this->abreCursor($dbm);

        $datos = [];
        $titulos = [];
        $primera = true;
        while ($row = $dbm->siguiente()) {
            if (is_callable($this->preparaDatosEjecutar)) {
                $row = call_user_func($this->preparaDatosEjecutar, $row);
            }
            if ($primera) {
                $titulos = [];
                foreach ($row as $key => $val) {
                    $titulos[] = $this->aNombrePublico($key);
                }
                $primera = false;
            }
            $datos[] = $row;
        }

        //ahora que ya tenemos la información busquemos si hay una funcion definida en la clase
        $respuesta = false;
        $clave = $this->input["ejecuta"];
        if (isset($this->ejecutor[$clave]) && is_callable($this->ejecutor[$clave])) {
            $respuesta = call_user_func($this->ejecutor[$clave], $datos, $titulos, $this->extra);
        }

        return ["respuesta" => $respuesta];
    }

    // =========================================================================
    // Modo exportar
    // =========================================================================

    /**
     * Ejecuta el query completo y despacha al generador de archivo según
     * `$this->tipo`.
     *
     * @param MYMONGODB $dbm
     * @return array
     */
    private function exporta($dbm) {
        //ejecute el query
        $cuantos = $this->abreCursor($dbm);
        $archivo = $this->nombreArchivoExport();

        if (empty($cuantos)) {
            return ["errores" => "No existen registros para exportar"];
        }

        if ($this->tipo == 'csv') {
            return $this->exportaCsv($dbm, $cuantos, $archivo);
        }
        if ($this->tipo == 'coGeneraExcel') {
            return $this->exportaCoGeneraExcel($dbm, $cuantos);
        }
        return $this->exportaPhpExcel($dbm, $cuantos, $archivo);
    }

    /**
     * Ruta relativa del archivo a generar, con o sin nombre configurado.
     *
     * @return string
     */
    private function nombreArchivoExport() {
        $extension = ($this->tipo == 'csv') ? ".csv" : ".xlsx";
        if ($this->filename != '') {
            return "cache/" . date('Ymd_His', time()) . '_' . $this->filename . $extension;
        }
        return "cache/" . md5(self::SEMILLA_ARCHIVO . md5(microtime()) . $this->queryDatos["coleccion"]) . $extension;
    }

    /**
     * Genera el CSV recorriendo el cursor.
     *
     * @param MYMONGODB $dbm
     * @param int       $cuantos
     * @param string    $archivo Ruta relativa
     * @return array
     */
    private function exportaCsv($dbm, $cuantos, $archivo) {
        $fp = fopen(BASEFOLDER . $archivo, "w");
        if (!$fp) {
            return ["errores" => "No se pudo crear el archivo CSV"];
        }

        $primera = true;
        while ($row = $dbm->siguiente()) {
            if (is_callable($this->preparaDatosExportar)) {
                $row = call_user_func($this->preparaDatosExportar, $row);
            }
            if ($primera) {
                $titulos = $this->cabecerasDe($row, true);
                fwrite($fp, implode(self::CSV_SEPARADOR, $titulos) . "\n");
                $primera = false;
            }
            fwrite($fp, implode(self::CSV_SEPARADOR, $this->filaCsv($row)) . "\n");
        }
        fclose($fp);

        return [
            "cuantos" => $cuantos,
            "archivo" => BASEURL . $archivo,
        ];
    }

    /**
     * Genera el xlsx usando la clase coGeneraExcel.
     *
     * @param MYMONGODB $dbm
     * @param int       $cuantos
     * @return array
     */
    private function exportaCoGeneraExcel($dbm, $cuantos) {
        require_once("../comunes/classes/class.coGeneraExcel.php");
        $objExcel = new coGeneraExcel();

        if (empty($this->filename)) {
            $archivoExcelNombre = md5(self::SEMILLA_ARCHIVO . md5(time()));
        } else {
            $archivoExcelNombre = date('Ymd_His', time()) . '_' . $this->filename;
        }
        $objExcel->setNombreArchivo($archivoExcelNombre);
        $objExcel->setUbicacion("cache/");

        $nombreHoja = empty($this->nombreHoja) ? "Resumen" : $this->nombreHoja;
        $objExcel->addHoja(0, $nombreHoja);

        //obtengo datos
        $primera = true;
        while ($row = $dbm->siguiente()) {
            if (is_callable($this->preparaDatosExportar)) {
                $row = call_user_func($this->preparaDatosExportar, $row);
            }
            if ($primera) {
                $titulos = $this->cabecerasDe($row, false);
                $primera = false;
                //conf excel
                $this->escribeTituloHoja($objExcel, $nombreHoja, count($titulos));
                $this->escribeCabeceraExcel($objExcel, $nombreHoja, $titulos);
            }
            foreach ($row as &$val) {
                if (!empty($val)) {
                    $val = $this->celdaLimpia($val);
                }
            }
            unset($val);

            $font = $objExcel->setFilaEstiloFuente("Calibri", "11", false, "#000000");
            $objExcel->addFila($nombreHoja, $row, $font, [], [], [], "");
        }

        //genera excel
        $archivoExcel = $objExcel->genera();
        $rutaArchivo = isset($archivoExcel["archivo"]) ? $archivoExcel["archivo"] : "";
        if (empty($rutaArchivo)) {
            return ["errores" => "No se pudo crear el archivo CSV"];
        }
        return [
            "cuantos" => $cuantos,
            "archivo" => $rutaArchivo,
        ];
    }

    /**
     * Escribe el título grande de la hoja (fila en blanco, título, fila en blanco).
     * No hace nada si no se configuró tituloHoja.
     *
     * @param coGeneraExcel $objExcel
     * @param string        $nombreHoja
     * @param int           $cuantasCols
     * @return void
     */
    private function escribeTituloHoja($objExcel, $nombreHoja, $cuantasCols) {
        if (empty($this->tituloHoja)) {
            return;
        }

        $espacio = array_fill(0, $cuantasCols, "");
        $cabTit = array_merge([$this->tituloHoja], array_fill(0, max(0, $cuantasCols - 1), ""));
        $merge = [$cuantasCols];

        $fontEspacio = $objExcel->setFilaEstiloFuente("Calibri", "11", false, "#000000");
        $fontTitulo = $objExcel->setFilaEstiloFuente("Calibri", "20", true, "#000000");
        $alinear = $objExcel->setFilaEstiloAlineacion('center', 'center');

        $objExcel->addFila($nombreHoja, $espacio, $fontEspacio, [], [], [], $merge);
        $objExcel->addFila($nombreHoja, $cabTit, $fontTitulo, [], [], $alinear, $merge);
        $objExcel->addFila($nombreHoja, $espacio, $fontEspacio, [], [], [], $merge);
    }

    /**
     * Escribe la fila de cabeceras con el estilo configurado.
     *
     * @param coGeneraExcel $objExcel
     * @param string        $nombreHoja
     * @param array         $titulos
     * @return void
     */
    private function escribeCabeceraExcel($objExcel, $nombreHoja, $titulos) {
        $font = $objExcel->setFilaEstiloFuente("Calibri", "11", true, "#ffffff");
        $backgroundColor = empty($this->colorCabecera) ? "1e2040" : $this->colorCabecera;
        $background = $objExcel->setFilaEstiloFondo("solid", $backgroundColor);
        $borders = $objExcel->setFilaEstiloBordes('3d3939');
        $alinear = $objExcel->setFilaEstiloAlineacion('center', 'center');

        $objExcel->addFila($nombreHoja, $titulos, $font, $background, $borders, $alinear, "");
    }

    /**
     * Genera el xlsx con PHPExcel.
     *
     * OJO: esta rama viene de la versión SQL de la clase y usa `$db` y `$mod`,
     * que no existen aquí. Revisar antes de habilitarla.
     *
     * @param MYMONGODB $dbm
     * @param int       $cuantos
     * @param string    $archivo Ruta relativa
     * @return array
     */
    private function exportaPhpExcel($dbm, $cuantos, $archivo) {
        require_once("../PHPExcel/Classes/PHPExcel/IOFactory.php");
        $objExcel = new PHPExcel();
        $fil = 2;
        $primera = true;

        while ($row = $db->fetchRow($mod)) {
            if (is_callable($this->preparaDatosExportar)) {
                $row = call_user_func($this->preparaDatosExportar, $row);
            }
            if ($primera) {
                $titulos = $this->cabecerasDe($row, false);
                $objExcel->getActiveSheet()->fromArray($titulos, null, 'A1');
                $primera = false;
            }
            $col = 0;
            foreach ($row as $valor) {
                $objExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $fil, utf8_2_encode($valor));
                $col++;
            }
            $fil++;
        }

        // read data to active sheet
        $objExcel->setActiveSheetIndex(0);
        $objWriter = PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
        $objWriter->save(BASEFOLDER . $archivo);

        return [
            "cuantos" => $cuantos,
            "archivo" => BASEURL . $archivo,
        ];
    }

    // =========================================================================
    // Cabeceras y celdas
    // =========================================================================

    /**
     * Construye la fila de títulos a partir de las claves de la primera fila.
     *
     * @param array $row
     * @param bool  $expandeHijos Si un valor es array, generar una columna por
     *                            hijo (un solo nivel) en lugar de una sola
     * @return string[]
     */
    private function cabecerasDe($row, $expandeHijos) {
        $titulos = [];
        foreach ($row as $key => $val) {
            if ($expandeHijos && is_array($val)) {
                //Hijos a un nivel: una columna por hijo
                foreach ($val as $key2 => $val2) {
                    $titulos[] = $this->tituloLimpio($key2);
                }
            } else {
                $titulos[] = $this->tituloLimpio($key);
            }
        }
        return $titulos;
    }

    /**
     * Normaliza y limpia un título de cabecera.
     *
     * Aplica la callback de cabecera (si existe), blinda el resultado contra
     * arrays / objetos / null para que str_replace nunca reciba un array como
     * subject, y devuelve siempre un string.
     *
     * @param mixed $valor Clave de columna
     * @return string
     */
    private function tituloLimpio($valor): string {
        if (is_callable($this->preparaDatosCabeceraExportar)) {
            $valor = call_user_func($this->preparaDatosCabeceraExportar, $valor);
        }
        $tit = $this->aNombrePublico(strtoupper($this->aTexto($valor)));
        return str_replace(self::CSV_BUSCA, self::CSV_REEMPLAZA, $tit);
    }

    /**
     * Deja un valor de dato listo para una celda: sin saltos de línea ni
     * separadores que rompan el CSV.
     *
     * @param mixed $valor
     * @return string
     */
    private function celdaLimpia($valor): string {
        return str_replace(self::CSV_BUSCA, self::CSV_REEMPLAZA, $this->aTexto($valor));
    }

    /**
     * Convierte cualquier valor en string sin disparar "Array to string
     * conversion" ni fallar con objetos o null.
     *
     * @param mixed $valor
     * @return string
     */
    private function aTexto($valor): string {
        if (is_array($valor)) {
            $planos = [];
            foreach ($valor as $v) {
                if (is_scalar($v)) {
                    $planos[] = (string) $v;
                }
            }
            return implode(" ", $planos);
        }
        if (is_object($valor)) {
            return method_exists($valor, '__toString') ? (string) $valor : '';
        }
        if ($valor === null || is_bool($valor)) {
            return '';
        }
        return (string) $valor;
    }

    /**
     * Limpia todos los valores de una fila para escribirla en el CSV.
     *
     * Los hijos de un valor array se unen con el mismo separador del CSV, a
     * propósito: así cada hijo cae en su propia columna, igual que sus
     * cabeceras.
     *
     * @param array $row
     * @return string[]
     */
    private function filaCsv($row) {
        $celdas = [];
        foreach ($row as $val) {
            if (is_array($val)) {
                $partes = [];
                foreach ($val as $v) {
                    $partes[] = $this->celdaLimpia($v);
                }
                $celdas[] = implode(self::CSV_SEPARADOR, $partes);
            } else {
                $celdas[] = $this->celdaLimpia($val);
            }
        }
        return $celdas;
    }
}
?><? //_FIN_DE_ARCHIVO