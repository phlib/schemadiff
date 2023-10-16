<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test;

use Phlib\SchemaDiff\SchemaDiff;
use Phlib\SchemaDiff\SchemaDiffCommand;
use Phlib\SchemaDiff\SchemaInfo;
use Phlib\SchemaDiff\SchemaInfoFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package phlib/schemadiff
 */
class SchemaDiffCommandTest extends TestCase
{
    /**
     * @var SchemaInfoFactory|MockObject
     */
    private MockObject $schemaInfoFactory;

    /**
     * @var SchemaDiff|MockObject
     */
    private MockObject $schemaDiff;

    private SchemaDiffCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->schemaInfoFactory = $this->createMock(SchemaInfoFactory::class);
        $this->schemaDiff = $this->createMock(SchemaDiff::class);

        $this->command = new SchemaDiffCommand(
            $this->schemaInfoFactory,
            function (): SchemaDiff {
                return $this->schemaDiff;
            },
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);

        parent::setUp();
    }

    /**
     * @dataProvider dataDsnInvalid
     */
    public function testDsnInvalid(string $dsn1, string $dsn2): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN contains invalid key');

        $this->commandTester->execute([
            'dsn1' => $dsn1,
            'dsn2' => $dsn2,
        ]);
    }

    public function dataDsnInvalid(): iterable
    {
        $validDsn = $this->createDsn(true);

        $invalidDsn = $validDsn['string'] . ',' . sha1(uniqid()) . '=' . sha1(uniqid());

        return [
            'one' => [$invalidDsn, $validDsn['string']],
            'two' => [$validDsn['string'], $invalidDsn],
        ];
    }

    public function testDsn1MissingDatabase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DSN 1 missing database (D)');

        $dsn1 = $this->createDsn(false);
        $dsn2 = $this->createDsn(true);

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            );

        $this->commandTester->execute([
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ]);
    }

    public function testTwoDbNoDiff(): void
    {
        $pdo1 = $this->createMock(\PDO::class);
        $pdo2 = $this->createMock(\PDO::class);

        $dsn1 = $this->createDsn(true);
        $dsn2 = $this->createDsn(true);

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            )
            ->willReturnOnConsecutiveCalls(
                $pdo1,
                $pdo2,
            );

        $schemaInfo1 = $this->createMock(SchemaInfo::class);
        $schemaInfo2 = $this->createMock(SchemaInfo::class);

        $expectedOutput = $this->setupFromPdoExpectation(
            $pdo1,
            $pdo2,
            $dsn1['parts']['D'],
            [$dsn2['parts']['D']],
            [
                $schemaInfo1,
                $schemaInfo2,
            ]
        );

        $this->schemaDiff->expects(static::once())
            ->method('diff')
            ->with($schemaInfo1, $schemaInfo2)
            ->willReturn(false);

        $this->commandTester->execute([
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ], [
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
        static::assertSame(
            implode("\n", $expectedOutput) . "\n",
            $output
        );
    }

    public function testMultipleDbNoDiff(): void
    {
        $pdo1 = $this->createMock(\PDO::class);
        $pdo2 = $this->createMock(\PDO::class);

        $dsn1 = $this->createDsn(true);
        $dsn2 = $this->createDsn(false);

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            )
            ->willReturnOnConsecutiveCalls(
                $pdo1,
                $pdo2,
            );

        $databases = [
            sha1(uniqid('one')),
            sha1(uniqid('two')),
        ];

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_COLUMN)
            ->willReturn($databases);

        $pdo2->expects(static::once())
            ->method('query')
            ->with('SHOW DATABASES')
            ->willReturn($pdoStatement);

        $schemaInfo1 = $this->createMock(SchemaInfo::class);
        $schemaInfo2 = $this->createMock(SchemaInfo::class);
        $schemaInfo3 = $this->createMock(SchemaInfo::class);

        $expectedOutput = $this->setupFromPdoExpectation(
            $pdo1,
            $pdo2,
            $dsn1['parts']['D'],
            $databases,
            [
                $schemaInfo1,
                $schemaInfo2,
                $schemaInfo3,
            ]
        );

        $this->schemaDiff->expects(static::exactly(2))
            ->method('diff')
            ->withConsecutive(
                [$schemaInfo1, $schemaInfo2],
                [$schemaInfo1, $schemaInfo3],
            )
            ->willReturn(false);

        $this->commandTester->execute([
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ], [
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
        static::assertSame(
            implode("\n", $expectedOutput) . "\n",
            $output
        );
    }

    /**
     * @dataProvider dataMultipleDbIgnoreSystem
     */
    public function testMultipleDbIgnoreSystem(string $systemDatabase): void
    {
        $pdo1 = $this->createMock(\PDO::class);
        $pdo2 = $this->createMock(\PDO::class);

        $dsn1 = $this->createDsn(true);
        $dsn2 = $this->createDsn(false);

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            )
            ->willReturnOnConsecutiveCalls(
                $pdo1,
                $pdo2,
            );

        $databases = [
            sha1(uniqid('one')),
            $systemDatabase,
            sha1(uniqid('two')),
        ];

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_COLUMN)
            ->willReturn($databases);

        $pdo2->expects(static::once())
            ->method('query')
            ->with('SHOW DATABASES')
            ->willReturn($pdoStatement);

        $schemaInfo1 = $this->createMock(SchemaInfo::class);
        $schemaInfo2 = $this->createMock(SchemaInfo::class);
        $schemaInfo3 = $this->createMock(SchemaInfo::class);

        // Remove unexpected database
        unset($databases[1]);

        $expectedOutput = $this->setupFromPdoExpectation(
            $pdo1,
            $pdo2,
            $dsn1['parts']['D'],
            $databases,
            [
                $schemaInfo1,
                $schemaInfo2,
                $schemaInfo3,
            ]
        );

        // Add message for skipped database
        array_splice($expectedOutput, 2, 0, "Database {$systemDatabase} is a system database, ignoring");

        $this->schemaDiff->expects(static::exactly(2))
            ->method('diff')
            ->withConsecutive(
                [$schemaInfo1, $schemaInfo2],
                [$schemaInfo1, $schemaInfo3],
            )
            ->willReturn(false);

        $this->commandTester->execute([
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ], [
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
        static::assertSame(
            implode("\n", $expectedOutput) . "\n",
            $output
        );
    }

    public function dataMultipleDbIgnoreSystem(): array
    {
        return [
            'mysql' => ['mysql'],
            'information_schema' => ['information_schema'],
            'performance_schema' => ['performance_schema'],
            'lost+found' => ['lost+found'],
            'percona' => ['percona'],
            'percona_schema' => ['percona_schema'],
            'test' => ['test'],
        ];
    }

    public function dataAllowIgnoreRegex(): array
    {
        return [
            'ignore-plain' => ['ignore-plain'],
            'ignore-regex' => ['ignore-regex'],
            'allow-plain' => ['allow-plain'],
            'allow-regex' => ['allow-regex'],
        ];
    }

    /**
     * @dataProvider dataAllowIgnoreRegex
     */
    public function testMultipleDbIgnoreDatabases(string $allowIgnoreRegex): void
    {
        $pdo1 = $this->createMock(\PDO::class);
        $pdo2 = $this->createMock(\PDO::class);

        $dsn1 = $this->createDsn(true);
        $dsn2 = $this->createDsn(false);

        $ignore1 = 'ignore-' . sha1(uniqid('one'));
        $ignore2 = 'ignore-' . sha1(uniqid('two'));
        $allow1 = 'allow-' . sha1(uniqid('one'));
        $allow2 = 'allow-' . sha1(uniqid('two'));

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            )
            ->willReturnOnConsecutiveCalls(
                $pdo1,
                $pdo2,
            );

        $databases = [
            $ignore1,
            $allow1,
            $ignore2,
            $allow2,
        ];

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_COLUMN)
            ->willReturn($databases);

        $pdo2->expects(static::once())
            ->method('query')
            ->with('SHOW DATABASES')
            ->willReturn($pdoStatement);

        $schemaInfo1 = $this->createMock(SchemaInfo::class);
        $schemaInfo2 = $this->createMock(SchemaInfo::class);
        $schemaInfo3 = $this->createMock(SchemaInfo::class);

        // Remove unexpected databases
        unset($databases[0], $databases[2]);

        $expectedOutput = $this->setupFromPdoExpectation(
            $pdo1,
            $pdo2,
            $dsn1['parts']['D'],
            $databases,
            [
                $schemaInfo1,
                $schemaInfo2,
                $schemaInfo3,
            ]
        );

        // Add message for ignored databases
        switch ($allowIgnoreRegex) {
            case 'ignore-plain':
                $message = ' is in --ignore-databases list';
                break;
            case 'ignore-regex':
                $message = ' matches --ignore-databases-regex';
                break;
            case 'allow-plain':
                $message = ' is not in --databases list, ignoring';
                break;
            case 'allow-regex':
                $message = ' does not match --databases-regex, ignoring';
                break;
        }
        array_splice($expectedOutput, 1, 0, 'Database ' . $ignore1 . $message);
        array_splice($expectedOutput, 3, 0, 'Database ' . $ignore2 . $message);

        $this->schemaDiff->expects(static::exactly(2))
            ->method('diff')
            ->withConsecutive(
                [$schemaInfo1, $schemaInfo2],
                [$schemaInfo1, $schemaInfo3],
            )
            ->willReturn(false);

        $input = [
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ];
        switch ($allowIgnoreRegex) {
            case 'ignore-plain':
                $input['--ignore-databases'] = $ignore1 . ',' . $ignore2;
                break;
            case 'ignore-regex':
                $input['--ignore-databases-regex'] = '^ignore-';
                break;
            case 'allow-plain':
                $input['--databases'] = $allow1 . ',' . $allow2;
                break;
            case 'allow-regex':
                $input['--databases-regex'] = '^allow-';
                break;
        }
        $this->commandTester->execute($input, [
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
        static::assertSame(
            implode("\n", $expectedOutput) . "\n",
            $output
        );
    }

    /**
     * @dataProvider dataAllowIgnoreRegex
     */
    public function testTwoDbIgnoreTables(string $allowIgnoreRegex): void
    {
        $pdo1 = $this->createMock(\PDO::class);
        $pdo2 = $this->createMock(\PDO::class);

        $dsn1 = $this->createDsn(true);
        $dsn2 = $this->createDsn(true);

        $ignore1 = 'ignore-' . sha1(uniqid('one'));
        $ignore2 = 'ignore-' . sha1(uniqid('two'));
        $allow1 = 'allow-' . sha1(uniqid('one'));
        $allow2 = 'allow-' . sha1(uniqid('two'));

        $this->schemaInfoFactory->expects(static::exactly(2))
            ->method('createPdo')
            ->withConsecutive(
                [$dsn1['parts']],
                [$dsn2['parts']],
            )
            ->willReturnOnConsecutiveCalls(
                $pdo1,
                $pdo2,
            );

        $schemaInfo1 = $this->createMock(SchemaInfo::class);
        $schemaInfo2 = $this->createMock(SchemaInfo::class);

        $tables = [
            $ignore1,
            $allow1,
            $ignore2,
            $allow2,
        ];

        $expectedOutput = $this->setupFromPdoExpectation(
            $pdo1,
            $pdo2,
            $dsn1['parts']['D'],
            [$dsn2['parts']['D']],
            [
                $schemaInfo1,
                $schemaInfo2,
            ],
            $tables
        );

        // Add message for ignored databases
        switch ($allowIgnoreRegex) {
            case 'ignore-plain':
                $message = ' is in --ignore-tables list';
                break;
            case 'ignore-regex':
                $message = ' matches --ignore-tables-regex';
                break;
            case 'allow-plain':
                $message = ' is not in --tables list, ignoring';
                break;
            case 'allow-regex':
                $message = ' does not match --tables-regex, ignoring';
                break;
        }
        array_splice($expectedOutput, 1, 0, 'Table ' . $ignore1 . $message);
        array_splice($expectedOutput, 2, 0, 'Table ' . $ignore2 . $message);
        array_splice($expectedOutput, 4, 0, 'Table ' . $ignore1 . $message);
        array_splice($expectedOutput, 5, 0, 'Table ' . $ignore2 . $message);

        $this->schemaDiff->expects(static::once())
            ->method('diff')
            ->withConsecutive(
                [$schemaInfo1, $schemaInfo2],
            )
            ->willReturn(false);

        $input = [
            'dsn1' => $dsn1['string'],
            'dsn2' => $dsn2['string'],
        ];
        switch ($allowIgnoreRegex) {
            case 'ignore-plain':
                $input['--ignore-tables'] = $ignore1 . ',' . $ignore2;
                break;
            case 'ignore-regex':
                $input['--ignore-tables-regex'] = '^ignore-';
                break;
            case 'allow-plain':
                $input['--tables'] = $allow1 . ',' . $allow2;
                break;
            case 'allow-regex':
                $input['--tables-regex'] = '^allow-';
                break;
        }
        $this->commandTester->execute($input, [
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $different = $this->commandTester->getStatusCode();
        static::assertSame(0, $different);

        $output = $this->commandTester->getDisplay();
        static::assertSame(
            implode("\n", $expectedOutput) . "\n",
            $output
        );
    }

    private function createDsn(bool $withDatabase = false): array
    {
        $dsnParts = [
            'h' => sha1(uniqid('host')),
            'P' => rand(3000, 9000),
            'u' => sha1(uniqid('username')),
            'p' => sha1(uniqid('password')),
        ];

        if ($withDatabase) {
            $dsnParts['D'] = sha1(uniqid('database'));
        }

        $dsn = [];
        foreach ($dsnParts as $k => $v) {
            $dsn[] = $k . '=' . $v;
        }

        return [
            'string' => implode(',', $dsn),
            'parts' => $dsnParts,
        ];
    }

    private function setupFromPdoExpectation(
        MockObject $pdo1,
        MockObject $pdo2,
        string $database1,
        array $databases,
        array $schemaInfos,
        array $tables = null
    ): array {
        $tableFilterConstraint = static::isInstanceOf(\Closure::class);
        if (is_array($tables)) {
            $tableFilterConstraint = static::callback(function (\Closure $tableFilter) use ($tables) {
                foreach ($tables as $tableName) {
                    $tableFilter($tableName);
                }
                return true;
            });
        }

        $arguments = [
            [$pdo1, $database1, $tableFilterConstraint],
        ];
        $output = [
            "Fetching schema details for database {$database1}",
        ];

        foreach ($databases as $database2) {
            $arguments[] = [$pdo2, $database2, $tableFilterConstraint];
            $output[] = "Fetching schema details for database {$database2}";
        }

        $this->schemaInfoFactory->expects(static::exactly(count($schemaInfos)))
            ->method('fromPdo')
            ->withConsecutive(...$arguments)
            ->willReturnOnConsecutiveCalls(...$schemaInfos);

        return $output;
    }
}
