<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test;

use Phlib\SchemaDiff\SchemaInfoFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @package phlib/schemadiff
 */
class SchemaInfoFactoryTest extends TestCase
{
    private string $schemaName;

    private string $tableName;

    private array $schemaData;

    private array $tableData;

    /**
     * @var \PDO|MockObject
     */
    private MockObject $pdo;

    private string $schemaSql;

    /**
     * @var \PDOStatement|MockObject
     */
    private MockObject $schemaStmt;

    private string $tablesSql;

    private string $columnsSql;

    private string $indexSql;

    protected function setUp(): void
    {
        $this->schemaName = sha1(uniqid());
        $this->tableName = sha1(uniqid());

        $this->schemaData = [
            'default character set' => 'utf8mb4',
            'default collation' => 'utf8mb4_general_ci',
        ];

        $this->tableData = [
            $this->tableName => [
                'TABLE_INFO' => [
                    'TABLE_NAME' => $this->tableName,
                    'engine' => 'InnoDB',
                    'collation' => 'utf8mb4_general_ci',
                ],
                'COLUMNS' => [
                    'test_id' => [
                        'column position' => '1',
                        'default' => null,
                        'nullable' => 'NO',
                        'column type' => 'int(10) unsigned',
                        'character set' => null,
                        'collation' => null,
                        'extra' => '',
                    ],
                ],
                'INDEXES' => [
                    'PRIMARY' => [
                        'columns' => 'test_id',
                        'unique' => 'Yes',
                    ],
                ],
            ],
        ];

        $this->pdo = $this->createMock(\PDO::class);

        $this->schemaSql = <<<SQL
SELECT
    DEFAULT_CHARACTER_SET_NAME AS 'default character set',
    DEFAULT_COLLATION_NAME AS 'default collation'
FROM INFORMATION_SCHEMA.SCHEMATA
WHERE SCHEMA_NAME = ?
SQL;
        $this->schemaStmt = $this->createMock(\PDOStatement::class);
        $this->schemaStmt->method('execute')
            ->with([$this->schemaName]);
        $this->schemaStmt->method('fetch')
            ->willReturn($this->schemaData);

        $this->tablesSql = <<<SQL
SELECT
    `TABLE_NAME`,
    `ENGINE` AS 'engine',
    `TABLE_COLLATION` AS 'collation',
    `TABLE_COMMENT` AS 'table comment'
FROM `information_schema`.`TABLES`
WHERE
    `TABLE_SCHEMA` = ?
SQL;

        $this->columnsSql = <<<SQL
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

        $this->indexSql = <<<SQL
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

        parent::setUp();
    }

    public function testFromPdoSchemaInfo(): void
    {
        // Set up all the expected SQL queries
        $tablesStmt = $this->createMock(\PDOStatement::class);
        $tablesStmt->expects(static::once())
            ->method('execute')
            ->with([$this->schemaName]);
        $tablesData = [
            $this->tableData[$this->tableName]['TABLE_INFO'],
        ];
        $tablesStmt->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($tablesData);

        $columnsStmt = $this->createMock(\PDOStatement::class);
        $columnsStmt->expects(static::once())
            ->method('execute')
            ->with([$this->schemaName, $this->tableName]);
        $columnsData = $this->tableData[$this->tableName]['COLUMNS'];
        $columnsStmt->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC)
            ->willReturn($columnsData);

        $indexStmt = $this->createMock(\PDOStatement::class);
        $indexStmt->expects(static::once())
            ->method('execute')
            ->with([$this->schemaName, $this->tableName]);
        $indexData = [
            'PRIMARY' => [
                [
                    'COLUMN_NAME' => 'test_id',
                    'NON_UNIQUE' => '0',
                ],
            ],
        ];
        $indexStmt->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC)
            ->willReturn($indexData);

        $statementMap = [
            [
                'with' => [$this->schemaSql],
                'return' => $this->schemaStmt,
            ],
            [
                'with' => [$this->tablesSql],
                'return' => $tablesStmt,
            ],
            [
                'with' => [$this->columnsSql],
                'return' => $columnsStmt,
            ],
            [
                'with' => [$this->indexSql],
                'return' => $indexStmt,
            ],
        ];

        $this->pdo->expects(static::exactly(count($statementMap)))
            ->method('prepare')
            ->withConsecutive(...array_column($statementMap, 'with'))
            ->willReturnOnConsecutiveCalls(...array_column($statementMap, 'return'));

        // Use the Factory to create the SchemaInfo
        $schemaInfo = (new SchemaInfoFactory())->fromPdo($this->pdo, $this->schemaName);

        // Test the SchemaInfo constructor params:
        // - schemaName
        static::assertSame($this->schemaName, $schemaInfo->getName());

        // - schemaData
        static::assertSame($this->schemaData, $schemaInfo->getInfo());

        // - tableData
        static::assertSame(
            $this->tableData[$this->tableName]['TABLE_INFO'],
            $schemaInfo->getTableInfo($this->tableName),
        );
        static::assertSame(
            $this->tableData[$this->tableName]['INDEXES']['PRIMARY'],
            $schemaInfo->getIndexInfo($this->tableName, 'PRIMARY'),
        );
        static::assertSame(
            $this->tableData[$this->tableName]['COLUMNS']['test_id'],
            $schemaInfo->getColumnInfo($this->tableName, 'test_id'),
        );
    }

    public function testFromPdoInvalidSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Schema {$this->schemaName} doesn't exist");

        // No result for schema statement, to trigger exception
        $schemaStmt = $this->createMock(\PDOStatement::class);
        $schemaStmt->expects(static::once())
            ->method('execute')
            ->with([$this->schemaName]);
        $schemaStmt->expects(static::once())
            ->method('fetch')
            ->willReturn([]);

        $this->pdo->expects(static::once())
            ->method('prepare')
            ->with($this->schemaSql)
            ->willReturn($schemaStmt);

        (new SchemaInfoFactory())->fromPdo($this->pdo, $this->schemaName);
    }

    public function testFromPdoTableFilter(): void
    {
        // Set up all the expected SQL queries
        $tablesStmt = $this->createMock(\PDOStatement::class);
        $tablesStmt->expects(static::once())
            ->method('execute')
            ->with([$this->schemaName]);
        $tablesData = [
            $this->tableData[$this->tableName]['TABLE_INFO'],
        ];
        $tablesStmt->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($tablesData);

        $statementMap = [
            [
                'with' => [$this->schemaSql],
                'return' => $this->schemaStmt,
            ],
            [
                'with' => [$this->tablesSql],
                'return' => $tablesStmt,
            ],
            [
                'with' => [$this->columnsSql],
                'return' => false,
            ],
            [
                'with' => [$this->indexSql],
                'return' => false,
            ],
        ];

        $this->pdo->expects(static::exactly(count($statementMap)))
            ->method('prepare')
            ->withConsecutive(...array_column($statementMap, 'with'))
            ->willReturnOnConsecutiveCalls(...array_column($statementMap, 'return'));

        // Use the Factory to create the SchemaInfo
        $tableFilter = function (): bool {
            return false;
        };
        $schemaInfo = (new SchemaInfoFactory())->fromPdo($this->pdo, $this->schemaName, $tableFilter);

        // No tables should be found
        static::assertEmpty($schemaInfo->getTables());
    }
}
