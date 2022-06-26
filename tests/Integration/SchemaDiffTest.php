<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test\Integration;

use Phlib\SchemaDiff\SchemaDiff;
use Phlib\SchemaDiff\SchemaInfoFactory;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @package phlib/schemadiff
 * @group integration
 */
class SchemaDiffTest extends IntegrationTestCase
{
    private function runDiff(string $tableName, string &$output = null): bool
    {
        $tableFilter = function ($testTable) use ($tableName): bool {
            return $testTable === $tableName;
        };

        $schemaInfo1 = SchemaInfoFactory::fromPdo($this->pdo, getenv('DB_DATABASE_1'), $tableFilter);
        $schemaInfo2 = SchemaInfoFactory::fromPdo($this->pdo, getenv('DB_DATABASE_2'), $tableFilter);

        $outputBuffer = new BufferedOutput();
        $schemaDiff = new SchemaDiff($outputBuffer);

        $different = $schemaDiff->diff($schemaInfo1, $schemaInfo2);

        $output = $outputBuffer->fetch();

        return $different;
    }

    public function testSame(): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_1'), $tableName);
        $this->createTestTable(getenv('DB_DATABASE_2'), $tableName);

        $different = $this->runDiff($tableName, $output);

        static::assertFalse($different);

        static::assertEmpty($output);
    }

    public function dataSchemaOrder(): array
    {
        return [
            'one-two' => [1, 2],
            'two-one' => [2, 1],
        ];
    }

    /**
     * @dataProvider dataSchemaOrder
     */
    public function testMissingTable(int $first, int $second): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_' . $first), $tableName);

        $different = $this->runDiff($tableName, $output);

        static::assertTrue($different);

        $expected = "Missing table {$tableName} missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        static::assertStringStartsWith($expected, $output);
    }

    /**
     * @dataProvider dataSchemaOrder
     */
    public function testMissingColumn(int $first, int $second): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_' . $first), $tableName, true);
        $this->createTestTable(getenv('DB_DATABASE_' . $second), $tableName, false);

        $different = $this->runDiff($tableName, $output);

        static::assertTrue($different);

        $expected = "Missing column {$tableName}.char_col missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        static::assertStringContainsString($expected, $output);
    }

    /**
     * @dataProvider dataSchemaOrder
     */
    public function testMissingIndex(int $first, int $second): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_' . $first), $tableName, true, true);
        $this->createTestTable(getenv('DB_DATABASE_' . $second), $tableName, true, false);

        $different = $this->runDiff($tableName, $output);

        static::assertTrue($different);

        $expected = "Missing index {$tableName}.idx_char missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        static::assertStringStartsWith($expected, $output);
    }

    /**
     * @dataProvider dataSchemaOrder
     */
    public function testDiffColumnCharset(int $first, int $second): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_' . $first), $tableName, true, false, null);
        $this->createTestTable(getenv('DB_DATABASE_' . $second), $tableName, true, false, 'utf8mb4');

        $different = $this->runDiff($tableName, $output);

        static::assertTrue($different);

        $expected = "Column attribute mismatch {$tableName}.char_col attribute character set differs:";
        static::assertStringStartsWith($expected, $output);

        static::assertStringContainsString(getenv('DB_DATABASE_' . $first) . "@{$first}=ascii", $output);
        static::assertStringContainsString(getenv('DB_DATABASE_' . $second) . "@{$second}=utf8mb4", $output);
    }

    /**
     * @dataProvider dataSchemaOrder
     */
    public function testDiffTableCharset(int $first, int $second): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_' . $first), $tableName, true, false, null, 'ascii');
        $this->createTestTable(getenv('DB_DATABASE_' . $second), $tableName, true, false, null, 'utf8mb4');

        $different = $this->runDiff($tableName, $output);

        static::assertTrue($different);

        $expected = "Table attribute mismatch {$tableName} attribute collation differs:";
        static::assertStringStartsWith($expected, $output);

        static::assertStringContainsString(getenv('DB_DATABASE_' . $first) . "@{$first}=ascii", $output);
        static::assertStringContainsString(getenv('DB_DATABASE_' . $second) . "@{$second}=utf8mb4", $output);
    }
}
