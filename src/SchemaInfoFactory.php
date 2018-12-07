<?php
declare(strict_types=1);

namespace Phlib\SchemaDiff;

/**
 * Class SchemaInfoFactory
 *
 * @package Application\Database\SchemaInfo
 */
class SchemaInfoFactory
{
    /**
     * @param \PDO $connection
     * @param string $schemaName
     * @param callable $tableFilter
     * @return SchemaInfo
     */
    public static function fromPdo(\PDO $connection, string $schemaName, callable $tableFilter = null): SchemaInfo
    {
        $sql = <<<SQL
SELECT
    DEFAULT_CHARACTER_SET_NAME AS 'default character set',
    DEFAULT_COLLATION_NAME AS 'default collation'
FROM INFORMATION_SCHEMA.SCHEMATA
WHERE SCHEMA_NAME = ?
SQL;
        $schemaStmt = $connection->prepare($sql);
        $schemaStmt->execute([$schemaName]);
        $schemaInfo = $schemaStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$schemaInfo) {
            throw new \InvalidArgumentException("Schema $schemaName doesn't exist");
        }

        $sql = <<<SQL
SELECT
    `TABLE_NAME`,
    `ENGINE` AS 'engine',
    `TABLE_COLLATION` AS 'collation',
    `TABLE_COMMENT` AS 'table comment'
FROM `information_schema`.`TABLES`
WHERE
    `TABLE_SCHEMA` = ?
SQL;

        $tablesStmt = $connection->prepare($sql);
        $tablesStmt->execute([$schemaName]);
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $sql = <<<SQL
SELECT
    `COLUMN_NAME`,
    `ORDINAL_POSITION` AS 'column position',
    `COLUMN_DEFAULT` AS 'default',
    `IS_NULLABLE` AS 'nullable',
    `COLUMN_TYPE` AS 'column type',
    `CHARACTER_SET_NAME` AS 'character set',
    `COLLATION_NAME` AS 'collation',
    `EXTRA` AS 'extra',
    `COLUMN_COMMENT` AS 'column comment'

FROM `information_schema`.`COLUMNS`
WHERE
    `TABLE_SCHEMA` = ?
    AND
    `TABLE_NAME` = ?
SQL;

        $columnsStmt = $connection->prepare($sql);

        $sql = <<<SQL
SELECT
    `INDEX_NAME`,
    `COLUMN_NAME`,
    `NON_UNIQUE`
FROM `information_schema`.`STATISTICS`
WHERE
    table_schema = ?
    AND
    table_name = ?
ORDER BY INDEX_NAME, SEQ_IN_INDEX
SQL;
        $indexesStmt = $connection->prepare($sql);

        $tableData = [];
        foreach ($tables as $tableInfo) {
            $tableName = $tableInfo['TABLE_NAME'];

            if ($tableFilter && !call_user_func($tableFilter, $tableName)) {
                continue;
            }

            $columnsStmt->execute([$schemaName, $tableName]);
            $columns = $columnsStmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

            $indexesStmt->execute([$schemaName, $tableName]);
            $indexResult = $indexesStmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

            $indexes = [];
            foreach ($indexResult as $indexName => $indexInfo) {
                $indexes[$indexName] = [
                    'columns' => implode(',', array_column($indexInfo, 'COLUMN_NAME')),
                    'unique'  => $indexInfo[0]['NON_UNIQUE'] == 0 ? 'Yes' : 'No'
                ];
            }

            $tableData[$tableName] = [
                'TABLE_INFO' => $tableInfo,
                'COLUMNS'    => $columns,
                'INDEXES'    => $indexes
            ];
        }

//        $sampleData = [
//            'tableName' => [
//                'TABLE_INFO' => [
//                    'attribs' => 'values'
//                ],
//                'COLUMNS' => [
//                    'columnName' => [
//                        'attribs' => 'values'
//                    ]
//                ],
//                'INDEXES' => [
//                    'indexName' => [
//                        'columns'    => 'id,etc',
//                        'other_keys' => 'other_values'
//                    ]
//                ]
//            ]
//        ];

        return new SchemaInfo($schemaName, $schemaInfo, $tableData);
    }
}
