<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test\Integration;

use Phlib\SchemaDiff\SchemaDiffCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package phlib/schemadiff
 * @group integration
 *
 * @todo This has to be an integration test because SchemaDiffCommand has hardwired dependencies on \PDO and
 *       SchemaInfoFactory, which will instantiate a DB connection.
 *       For now, this is a copy of the tests in Integration\SchemaDiffTest but run through the Command.
 *       Refactoring in future to allow mock dependencies will allow coverage of more features, e.g. ignore-databases
 */
class SchemaDiffCommandTest extends IntegrationTestCase
{
    private SchemaDiffCommand $command;

    private CommandTester $commandTester;

    private string $dsn1;

    private string $dsn2;

    protected function setUp(): void
    {
        $this->command = new SchemaDiffCommand();

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);

        $dsnBase = 'h=' . getenv('DB_HOST') . ',u=' . getenv('DB_USERNAME') . ',p=' . getenv('DB_PASSWORD');
        $this->dsn1 = $dsnBase . ',D=' . getenv('DB_DATABASE_1');
        $this->dsn2 = $dsnBase . ',D=' . getenv('DB_DATABASE_2');

        parent::setUp();
    }

    public function testSame(): void
    {
        $tableName = $this->generateTableName();

        $this->createTestTable(getenv('DB_DATABASE_1'), $tableName);
        $this->createTestTable(getenv('DB_DATABASE_2'), $tableName);

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
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

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(1, $different);

        $expected = "Missing table {$tableName} missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        $output = $this->commandTester->getDisplay();
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

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(1, $different);

        $expected = "Missing column {$tableName}.char_col missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        $output = $this->commandTester->getDisplay();
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

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(1, $different);

        $expected = "Missing index {$tableName}.idx_char missing on " .
            getenv('DB_DATABASE_' . $second) . "@{$second} exists on " . getenv('DB_DATABASE_' . $first) . "@{$first}";
        $output = $this->commandTester->getDisplay();
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

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(1, $different);

        $expected = "Column attribute mismatch {$tableName}.char_col attribute character set differs:";
        $output = $this->commandTester->getDisplay();
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

        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(1, $different);

        $expected = "Table attribute mismatch {$tableName} attribute collation differs:";
        $output = $this->commandTester->getDisplay();
        static::assertStringStartsWith($expected, $output);

        static::assertStringContainsString(getenv('DB_DATABASE_' . $first) . "@{$first}=ascii", $output);
        static::assertStringContainsString(getenv('DB_DATABASE_' . $second) . "@{$second}=utf8mb4", $output);
    }
}
