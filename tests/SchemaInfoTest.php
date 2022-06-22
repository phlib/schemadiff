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
    /**
     * @var string
     */
    private $schemaName;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string[]
     */
    private $schemaData;

    /**
     * @var array[]
     */
    private $tableData;

    /**
     * @var SchemaInfo
     */
    private $schemaInfo;

    protected function setUp()
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

    public function testGetName()
    {
        static::assertSame($this->schemaName, $this->schemaInfo->getName());
    }

    public function testGetInfo()
    {
        static::assertSame($this->schemaData, $this->schemaInfo->getInfo());
    }

    public function testGetTables()
    {
        $expected = [
            $this->tableName,
        ];

        static::assertSame($expected, $this->schemaInfo->getTables());
    }

    public function testHasTableTrue()
    {
        static::assertTrue($this->schemaInfo->hasTable($this->tableName));
    }

    public function testHasTableFalse()
    {
        static::assertFalse($this->schemaInfo->hasTable('does-not-exist'));
    }

    public function testGetTableInfo()
    {
        static::assertSame(
            $this->tableData[$this->tableName]['TABLE_INFO'],
            $this->schemaInfo->getTableInfo($this->tableName)
        );
    }

    public function testGetIndexes()
    {
        $expected = [
            'PRIMARY',
        ];

        static::assertSame($expected, $this->schemaInfo->getIndexes($this->tableName));
    }

    public function testHasIndexTrue()
    {
        static::assertTrue($this->schemaInfo->hasIndex($this->tableName, 'PRIMARY'));
    }

    public function testHasIndexFalse()
    {
        static::assertFalse($this->schemaInfo->hasIndex($this->tableName, 'does-not-exist'));
    }

    public function testGetIndexInfo()
    {
        static::assertSame(
            $this->tableData[$this->tableName]['INDEXES']['PRIMARY'],
            $this->schemaInfo->getIndexInfo($this->tableName, 'PRIMARY')
        );
    }

    public function testGetColumns()
    {
        $expected = [
            'test_id',
        ];

        static::assertSame($expected, $this->schemaInfo->getColumns($this->tableName));
    }

    public function testHasColumnTrue()
    {
        static::assertTrue($this->schemaInfo->hasColumn($this->tableName, 'test_id'));
    }

    public function testHasColumnFalse()
    {
        static::assertFalse($this->schemaInfo->hasColumn($this->tableName, 'does-not-exist'));
    }

    public function testGetColumnInfo()
    {
        static::assertSame(
            $this->tableData[$this->tableName]['COLUMNS']['test_id'],
            $this->schemaInfo->getColumnInfo($this->tableName, 'test_id')
        );
    }
}
