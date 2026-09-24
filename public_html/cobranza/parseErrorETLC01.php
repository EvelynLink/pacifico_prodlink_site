<?php

set_time_limit(60 * 60 * 60);
ini_set('memory_limit', '5096M');

chdir(__DIR__);

require_once("/home/pacifico/_configBasico.inc.php");

require_once(__DIR__ . "/classes/FixedWidthValidator.php");

print_h("ingresooo");



$config = [

    // ------------------ Layout del registro de detalle (REF 1-12) ------------------
    'detail' => [
        'line_length' => 264,
        'fields' => [

            // REF 1 - IDENTIFICACION CLIENTE: cédula, RUC o pasaporte
            'identificacion_cliente' => [
                'start' => 1, 'length' => 16, 'type' => 'alphanumeric', 'required' => true,
            ],

            // REF 2 - NUM. DE CREDITO CLIENTE (CIFCOD)
            'num_credito_cliente' => [
                'start' => 17, 'length' => 6, 'type' => 'alphanumeric', 'required' => true,
            ],

            // REF 3 - FECHA ASIGNACION CUENTA
            'fecha_asignacion_cuenta' => [
                'start' => 23, 'length' => 8, 'type' => 'date', 'format' => 'Ymd', 'required' => true,
            ],

            // REF 4 - NUMERO DE CONTACTO (secuencial, único por cuenta/cliente)
            'numero_contacto' => [
                'start' => 31, 'length' => 2, 'type' => 'numeric', 'required' => true,
            ],

            // REF 5 - FECHA DE GESTION CUENTA
            'fecha_gestion_cuenta' => [
                'start' => 33, 'length' => 8, 'type' => 'date', 'format' => 'Ymd', 'required' => true,
            ],

            // REF 6 - TIPO DE CONTACTO COBRANZA (ver TABLA - TIPOS DE CONTACTOS)
            'tipo_contacto_cobranza' => [
                'start' => 41, 'length' => 1, 'type' => 'regex', 'pattern' => '/^[CT]$/',
                'required' => true,
                // 'allowed_values' hace lo mismo que el regex de arriba; se deja el regex
                // porque es más explícito, pero puedes usar allowed_values si prefieres:
                // 'allowed_values' => ['C', 'T'],
            ],

            // REF 7 - CODIGO RESP. DE COBRANZAS (ver TABLA - RESPUESTAS DE COBRANZA)

                                       'codigo_resp_cobranzas' => [
                'start' => 42, 'length' => 2, 'type' => 'numeric', 'required' => true,
                'allowed_values' => ['01','02','03','04','05','06','12','16','17','18','19',
                                     '20','21','22','25','27','29','32','33','35','36','37','38','39',
                                     ],

            ],

            // REF 8 - OBSERVACIONES 1 (obligatorio: el código 052 "Falta observaciones"
            // sugiere que al menos esta primera observación es requerida)
            'observaciones_1' => [
                'start' => 44, 'length' => 70, 'type' => 'alphanumeric', 'required' => true,
            ],

            // REF 9 - OBSERVACIONES 2 (opcional)
            'observaciones_2' => [
                'start' => 114, 'length' => 70, 'type' => 'alphanumeric', 'required' => false,
            ],

            // REF 10 - OBSERVACIONES 3 (opcional)
            'observaciones_3' => [
                'start' => 184, 'length' => 70, 'type' => 'alphanumeric', 'required' => false,
            ],

            // REF 11 - FECHA DE PROXIMO SEGUIMIENTO
            'fecha_proximo_seguimiento' => [
                'start' => 254, 'length' => 8, 'type' => 'date', 'format' => 'Ymd', 'required' => false,
            ],

            // REF 12 - CODIGO DE RESPUESTA: para el archivo CO1 SIEMPRE debe ir en blanco.
            // Truco: trim => false + regex de "solo espacios" para validar que esté vacío.
            'codigo_respuesta' => [
                'start' => 262, 'length' => 3, 'type' => 'regex', 'pattern' => '/^\s*$/',
                'trim' => false, 'required' => false,
            ],
        ],
    ],

    // ------------------ Unicidad ------------------
    // REF 4 dice: "NUMERO SECUENCIAL CONTACTOS. UNICO PARA CADA CUENTA/CLIENTE
    // DENTRO DEL ARCHIVO ENVIADO." -> la unicidad NO es del numero_contacto solo,
    // sino de la combinación cliente + crédito + número de contacto.
    'unique_rules' => [
        [
            'fields' => ['identificacion_cliente', 'num_credito_cliente', 'numero_contacto'],
            'label'  => 'Identificación + Crédito + Número de contacto',
        ],
    ],

    // No hay 'header', 'trailer' ni 'totals': el archivo real analizado
    // solo contiene líneas de detalle.
];

// --------------------------------------------------------------------------
// Ejecución por línea de comandos: php validar_co1.php archivo.txt
// --------------------------------------------------------------------------

//$filePath = $argv[1] ?? null;
//$nombreArchivo = $_GET['archivo'] ?? '';
//$nombreArchivo="CO1MCELKD.203";
$nombreArchivo="CO1MCELKD_mongo.202b";

if ($nombreArchivo == '') {
    die("Debe enviar el parámetro archivo.");
}

$filePath = "/home/pacifico/public_html/cobranza/archivosCO1/" . $nombreArchivo;

if (!$filePath) {
    echo "Uso: php validar_co1.php <ruta_archivo_CO1>\n";
    exit(1);
} 

$validator = new FixedWidthValidator($config);
$resultado = $validator->validate($filePath);

echo "=== Resultado de validación CO1: $filePath ===\n";
echo $resultado['valido']
    ? "? El archivo es VÁLIDO.\n"
    : "? El archivo tiene {$resultado['total_errores']} error(es):\n";

// Si hay MUCHOS errores (típico si un campo del layout está mal alineado),
// mostramos solo los primeros 50 para no saturar la consola, más un resumen
// por categoría de campo.
$maxMostrar = 50;
$mostrados = 0;
$resumenPorMensaje = [];

foreach ($resultado['errores'] as $error) {
    // Normalizamos el mensaje quitando el valor específico para agrupar por tipo de error
    $claveResumen = preg_replace('/\'[^\']*\'\s*$/', '', $error['mensaje']);
    $claveResumen = trim($claveResumen);
    $resumenPorMensaje[$claveResumen] = ($resumenPorMensaje[$claveResumen] ?? 0) + 1;

    if ($mostrados < $maxMostrar) {
        printf("  [Línea %d] (%s) %s\n", $error['linea'], $error['categoria'], $error['mensaje']);
        $mostrados++;
    }
}

if ($resultado['total_errores'] > $maxMostrar) {
    echo "  ... y " . ($resultado['total_errores'] - $maxMostrar) . " error(es) más.\n";
}

if (!empty($resumenPorMensaje)) {
    echo "\n=== Resumen de errores por tipo ===\n";
    arsort($resumenPorMensaje);
    foreach ($resumenPorMensaje as $mensaje => $cantidad) {
        echo "  ($cantidad) $mensaje\n";
    }
}

exit($resultado['valido'] ? 0 : 1);

?>
<?

//_FIN_DE_ARCHIVO                                                                                                       
?>