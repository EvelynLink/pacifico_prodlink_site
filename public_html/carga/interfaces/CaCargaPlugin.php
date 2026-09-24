<?php

interface CaCargaPlugin
{
    /**
     * Permite que el plugin solicite campos adicionales requeridos para su proceso.
     *
     * Esta función devuelve la configuración de los campos que deben ser mostrados
     * al usuario antes de ejecutar la operación del plugin. Cada campo se define
     * mediante una estructura estándar que indica su tipo,
     * su valor por defecto, configuración de interfaz y posibles valores desde base de datos o manules.
     *
     * Estructura de cada campo:
     * - La KEY será el nombre del campo.
     * - selected: valor seleccionado actualmente; para manejo de angular.
     * - default: valor por defecto del campo.
     * - required: indica si el campo es obligatorio.
     * - type: tipo de campo (opciones disponibles: select, bool, text.).
     * - descripcion: etiqueta descriptiva que se mostrará al usuario.
     * - grid: configuración de tamaño para la interfaz (responsive).
     * - db: lista de opciones cuando el campo requiere valores para el tipo select [ ['clave1', 'valor1'], ['clave2', 'valor2'] ].
     *
     * Este mecanismo permite que cada plugin defina dinámicamente los datos
     * necesarios para su ejecución sin modificar la lógica central del sistema.
     *
     * @return array{
     *     cartera: array{
     *         selected: mixed,
     *         default: mixed,
     *         required: bool,
     *         type: string,
     *         descripcion: string,
     *         grid: array,
     *         db: array
     *     }
     * }
     */
    public function solicitarCampos(): array;

    /**
     * Función para procesar el archivo seleccionado por el usuario.
     * 
     * @param CaCargaDTO $dtoCarga
     * - Campos fijos:
     *   - cargaId: int
     *   - archivo: string
     *   - relativePath: string
     *   - fileName: string
     *   - camposAdicionales: array
     *
     * - Campos adicionales de configuración:
     *   Estos campos se agregan según la definición de solicitarCampos()
     *   y pueden variar dependiendo del proceso.
     * 
     * @return array<mixed>
     * - Casos de retorno
     *   - En caso de error
     *     - ['error' => 'Mensaje de error'].
     *   - En caso de éxito retorna array con mensajes para usuario final
     *     - ['Proceso finalizado', -'Registros procesados 1250', etc...]
     */
    public function procesarArchivo(CaCargaDTO $dtoCarga): array;
}
// _FIN_DE_ARCHIVO