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
class SchemaDiffCommandTest extends SchemaDiffTestCase
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

    protected function runDiff(string $tableName, string &$output = null): bool
    {
        $this->commandTester->execute([
            '--tables' => $tableName,
            'dsn1' => $this->dsn1,
            'dsn2' => $this->dsn2,
        ]);

        $different = $this->commandTester->getStatusCode();

        $output = $this->commandTester->getDisplay();

        return (bool)$different;
    }
}
