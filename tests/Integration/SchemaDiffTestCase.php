<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @package phlib/schemadiff
 */
abstract class SchemaDiffTestCase extends IntegrationTestCase
{
    abstract protected function runDiff(string $tableName, string &$output = null): bool;

    public function testSame(): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_1'), $tableName);
        $this->createTestTable(getenv('DB_DATABASE_2'), $tableName);

        $different = $this->runDiff($tableName, $output);

        static::assertFalse($different);

        static::assertEmpty($output);
    }

    public static function dataSchemaOrder(): array
    {
        return [
            'one-two' => [1, 2],
            'two-one' => [2, 1],
        ];
    }

    #[DataProvider('dataSchemaOrder')]
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

    #[DataProvider('dataSchemaOrder')]
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

    #[DataProvider('dataSchemaOrder')]
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

    #[DataProvider('dataSchemaOrder')]
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

    #[DataProvider('dataSchemaOrder')]
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
