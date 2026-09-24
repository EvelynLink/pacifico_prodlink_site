<?php
require_once '../comunes/classes/class.mymongodb.php';
require_once '../comunes/classes/class.mysqldb.php';

abstract class AbstractCuboPlugin
{

    public const COLLECTION_CUBO = null;
    protected bool $activateLog = true;

    public function __construct()
    {
        if (static::COLLECTION_CUBO === null) {
            throw new Exception('La clase debe definir la constante publica COLLECTION_CUBO');
        }
    }

    final protected function insertLog(string $texto): void {
        if($this->activateLog){
            $filePath = BASEFOLDER.'cubos/plugins/logs/' . static::COLLECTION_CUBO . '.csv';
            $limite = 4 * 1024 * 1024;
            if (file_exists($filePath) && filesize($filePath) > $limite) {
                unlink($filePath);
            }
            $fp = fopen($filePath, "a");
            fwrite($fp, date('Y-m-d H:i:s') . ';' . $texto . PHP_EOL);
            fclose($fp);
        }
    }

    public function onAdd($ids): void
    {
        $this->process($ids, 'ADD');
        $log = 'ADD;' . json_encode($ids);
        $this->insertLog($log);
    }

    public function onDelete($ids): void
    {
        $this->process($ids, 'DELETE');
        $log = 'DELETE;' . json_encode($ids);
        $this->insertLog($log);
    }

    abstract protected function process(array $ids, string $accion): void;

    public function recreate(): void
    {
        $i   = 0;
        $mdb = new MYMONGODB();
        $db  = new MYSQLDB();
        $mdb->borrarColeccion(static::COLLECTION_CUBO);
        $sql = $db->mkSQL($this->baseQuery());
        $db->query($sql);
        while ($row = $db->fetchRow('EXTENDED')) {
            $mdb->guardar(static::COLLECTION_CUBO, $this->createData($row));
            $i++;
            if ($i == 1000) {
                usleep(10000);
                $i = 0;
            }
        }
        $this->createIndices($mdb);
    }

    public function count(): int
    {
        $mdb = new MYMONGODB();
        return $mdb->buscar(static::COLLECTION_CUBO, []);
    }

    abstract public function getMapeoCampos(): array;

    protected function buildBIResponse(int $limit): array
    {
        $data         = [];
        $mapeoCampos  = $this->getMapeoCampos();
        $mdb          = new MYMONGODB();
        $cursor = $mdb->buscar(
            static::COLLECTION_CUBO, [], [], [], $limit
        );
        if (!$cursor) {
            return $data;
        }
        $data[] = array_keys($mapeoCampos);
        while ($doc = $mdb->siguiente()) {
            $fila = [];
            foreach ($mapeoCampos as $campo) {
                $valor = $doc[$campo['mdb']] ?? $campo['defaultValue'];
                $valor = $doc[$campo['mdb']] === 'null' ? $campo['defaultValue'] : $valor;
                $fila[] = $valor;
            }
            $data[] = $fila;
        }
        return $data;
    }

    public function returnAll($limit): array
    {
        return $this->buildBIResponse($limit);
    }

    public function returnAllHeredado($limit): array
    {
        return $this->buildBIResponse($limit);
    }

    abstract protected function createData($row): array;

    abstract protected function baseQuery(int $limit = 0): string;

    abstract protected function createIndices(object $mongoDb): void;

    protected function organizeByTable($ids) : array
    {
        $tablas = [];
        foreach ($ids as $item) {
            [$tabla, $id]     = explode(':', $item);
            $tablas[$tabla][] = $id;
        }
        return $tablas;
    }

    abstract protected function buildCondition(string $tabla, array $regIds) : array;
}
