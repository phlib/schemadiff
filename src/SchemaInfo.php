<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff;

/**
 * @package phlib/schemadiff
 */
class SchemaInfo
{
    public function __construct(
        private readonly string $name,
        private readonly array $schemaData,
        private readonly array $tableData,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInfo(): array
    {
        return $this->schemaData;
    }

    public function getTables(): array
    {
        return array_keys($this->tableData);
    }

    public function hasTable(string $tableName): bool
    {
        return isset($this->tableData[$tableName]);
    }

    public function getTableInfo(string $tableName): array
    {
        return $this->tableData[$tableName]['TABLE_INFO'];
    }

    public function getIndexes(string $tableName): array
    {
        return array_keys($this->tableData[$tableName]['INDEXES']);
    }

    public function hasIndex(string $tableName, string $indexName): bool
    {
        return isset($this->tableData[$tableName]['INDEXES'][$indexName]);
    }

    public function getIndexInfo(string $tableName, string $indexName): array
    {
        return $this->tableData[$tableName]['INDEXES'][$indexName];
    }

    public function getColumns(string $tableName): array
    {
        return array_keys($this->tableData[$tableName]['COLUMNS']);
    }

    public function hasColumn(string $tableName, string $columnName): bool
    {
        return isset($this->tableData[$tableName]['COLUMNS'][$columnName]);
    }

    public function getColumnInfo(string $tableName, string $columnName): array
    {
        return $this->tableData[$tableName]['COLUMNS'][$columnName];
    }
}
