<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test;

use Phlib\SchemaDiff\SchemaInfo;
use PHPUnit\Framework\TestCase;

/**
 * @package phlib/schemadiff
 */
class SchemaInfoTest extends TestCase
{
    private string $schemaName;

    private string $tableName;

    private array $schemaData;

    private array $tableData;

    private SchemaInfo $schemaInfo;

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

        $this->schemaInfo = new SchemaInfo($this->schemaName, $this->schemaData, $this->tableData);

        parent::setUp();
    }

    public function testGetName(): void
    {
        static::assertSame($this->schemaName, $this->schemaInfo->getName());
    }

    public function testGetInfo(): void
    {
        static::assertSame($this->schemaData, $this->schemaInfo->getInfo());
    }

    public function testGetTables(): void
    {
        $expected = [
            $this->tableName,
        ];

        static::assertSame($expected, $this->schemaInfo->getTables());
    }

    public function testHasTableTrue(): void
    {
        static::assertTrue($this->schemaInfo->hasTable($this->tableName));
    }

    public function testHasTableFalse(): void
    {
        static::assertFalse($this->schemaInfo->hasTable('does-not-exist'));
    }

    public function testGetTableInfo(): void
    {
        static::assertSame(
            $this->tableData[$this->tableName]['TABLE_INFO'],
            $this->schemaInfo->getTableInfo($this->tableName)
        );
    }

    public function testGetIndexes(): void
    {
        $expected = [
            'PRIMARY',
        ];

        static::assertSame($expected, $this->schemaInfo->getIndexes($this->tableName));
    }

    public function testHasIndexTrue(): void
    {
        static::assertTrue($this->schemaInfo->hasIndex($this->tableName, 'PRIMARY'));
    }

    public function testHasIndexFalse(): void
    {
        static::assertFalse($this->schemaInfo->hasIndex($this->tableName, 'does-not-exist'));
    }

    public function testGetIndexInfo(): void
    {
        static::assertSame(
            $this->tableData[$this->tableName]['INDEXES']['PRIMARY'],
            $this->schemaInfo->getIndexInfo($this->tableName, 'PRIMARY')
        );
    }

    public function testGetColumns(): void
    {
        $expected = [
            'test_id',
        ];

        static::assertSame($expected, $this->schemaInfo->getColumns($this->tableName));
    }

    public function testHasColumnTrue(): void
    {
        static::assertTrue($this->schemaInfo->hasColumn($this->tableName, 'test_id'));
    }

    public function testHasColumnFalse(): void
    {
        static::assertFalse($this->schemaInfo->hasColumn($this->tableName, 'does-not-exist'));
    }

    public function testGetColumnInfo(): void
    {
        static::assertSame(
            $this->tableData[$this->tableName]['COLUMNS']['test_id'],
            $this->schemaInfo->getColumnInfo($this->tableName, 'test_id')
        );
    }
}
