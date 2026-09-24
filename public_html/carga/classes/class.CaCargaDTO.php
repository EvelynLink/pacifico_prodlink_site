<?php
/**
 * DTO para la carga de archivos.
 *
 * Campos base (obligatorios):
 * - cargaId
 * - archivo
 * - relativePath
 * - fileName
 * - camposAdicionales
 */
class CaCargaDTO
{

    /**
     * @param int    $cargaId           Identificador de la carga.
     * @param string $archivo           Nombre original del archivo.
     * @param string $relativePath      Ruta relativa donde se encuentra el archivo.
     * @param string $fileName          Nombre físico del archivo.
     * @param array $camposAdicionales Campos adionales creados en el plugin.
     */
    public function __construct(
        public readonly int $cargaId,
        public readonly string $archivo,
        public readonly string $relativePath,
        public readonly string $fileName,
        public readonly array $camposAdicionales
    ){}

}
// _FIN_DE_ARCHIVO