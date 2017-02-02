<?php
declare(strict_types=1);

namespace Phlib\SchemaDiff;

/**
 * Class SchemaInfo
 *
 * @package Application\Database
 */
class SchemaInfo
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $tableData;

    /**
     * TableInfo constructor.
     *
     * @param string $name
     * @param array $tableData
     */
    public function __construct(string $name, array $tableData)
    {
        $this->name = $name;
        $this->tableData = $tableData;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return array_keys($this->tableData);
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function hasTable(string $tableName): bool
    {
        return isset($this->tableData[$tableName]);
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getTableInfo(string $tableName): array
    {
        return $this->tableData[$tableName]['TABLE_INFO'];
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getIndexes(string $tableName): array
    {
        return array_keys($this->tableData[$tableName]['INDEXES']);
    }

    /**
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    public function hasIndex(string $tableName, string $indexName): bool
    {
        return isset($this->tableData[$tableName]['INDEXES'][$indexName]);
    }

    /**
     * @param string $tableName
     * @param string $indexName
     * @return array
     */
    public function getIndexInfo(string $tableName, string $indexName): array
    {
        return $this->tableData[$tableName]['INDEXES'][$indexName];
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getColumns(string $tableName): array
    {
        return array_keys($this->tableData[$tableName]['COLUMNS']);
    }

    /**
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    public function hasColumn(string $tableName, string $columnName): bool
    {
        return isset($this->tableData[$tableName]['COLUMNS'][$columnName]);
    }

    /**
     * @param string $tableName
     * @param string $columnName
     * @return array
     */
    public function getColumnInfo(string $tableName, string $columnName): array
    {
        return $this->tableData[$tableName]['COLUMNS'][$columnName];
    }
}
