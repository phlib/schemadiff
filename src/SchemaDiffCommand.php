<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package phlib/schemadiff
 */
class SchemaDiffCommand extends Command
{
    /**
     * @var
     */
    private $ignoreDatabases;

    /**
     * @var
     */
    private $ignoreDatabasesRegex;

    /**
     * @var
     */
    private $databases;

    /**
     * @var
     */
    private $databasesRegex;

    /**
     * @var
     */
    private $ignoreTables;

    /**
     * @var
     */
    private $ignoreTablesRegex;

    /**
     * @var
     */
    private $tables;

    /**
     * @var
     */
    private $tablesRegex;

    protected function configure(): void
    {
        $this->setName('schemadiff')
            ->setDescription(
                <<<DESC
Schema Diff Tool

Used to diff a single database schema against other database schemas.
DESC
            );

        $this->addUsage('h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass,D=db2');
        $this->addUsage('h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass --ignore-databases="db1,dbtest"');
        $this->addUsage('h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass --databases="db2,db3,db4"');
        $this->addUsage('h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass --ignore-databases="db1" --ignore-tables-regex="custom_table_\d+|other_custom_\d+"');

        $this->addArgument(
            'dsn1',
            InputArgument::REQUIRED,
            'DSN to first schema, needs to contain the main database'
        );

        $this->addArgument(
            'dsn2',
            InputArgument::REQUIRED,
            'DSN to second schema, if it contains a database that will be used, otherwise uses the filter options'
        );

        $this->addOption(
            'ignore-databases',
            null,
            InputOption::VALUE_REQUIRED,
            'Ignore this comma-separated list of databases'
        );

        $this->addOption(
            'ignore-databases-regex',
            null,
            InputOption::VALUE_REQUIRED,
            'Ignore databases whose names match this regex'
        );

        $this->addOption(
            'databases',
            null,
            InputOption::VALUE_REQUIRED,
            'Only compare this comma-separated list of databases'
        );

        $this->addOption(
            'databases-regex',
            null,
            InputOption::VALUE_REQUIRED,
            'Only compare databases whose names match this regex'
        );

        $this->addOption(
            'ignore-tables',
            null,
            InputOption::VALUE_REQUIRED,
            'Ignore this comma-separated list of tables. Table names may be qualified with the database name'
        );

        $this->addOption(
            'ignore-tables-regex',
            null,
            InputOption::VALUE_REQUIRED,
            'Ignore tables whose names match the regex'
        );

        $this->addOption(
            'tables',
            null,
            InputOption::VALUE_REQUIRED,
            'Compare only this comma-separated list of tables. Table names may be qualified with the database name'
        );

        $this->addOption(
            'tables-regex',
            null,
            InputOption::VALUE_REQUIRED,
            'Compare only tables whose names match this regex'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initFilters($input);

        $dsn1 = $this->parseDsn($input->getArgument('dsn1'));
        $pdo1 = $this->createPdo($dsn1);

        $dsn2 = $this->parseDsn($input->getArgument('dsn2'));
        $pdo2 = $this->createPdo($dsn2);

        $schemaDiff = new SchemaDiff($output);

        $schema1 = $this->createSchemaInfo($pdo1, $dsn1['D'], $output);

        if (isset($dsn2['D'])) {
            $schema2 = $this->createSchemaInfo($pdo2, $dsn2['D'], $output);
            $differences = $schemaDiff->diff($schema1, $schema2);

            return $differences ? 1 : 0;
        }

        $differences = false;
        $databases = $pdo2->query('SHOW DATABASES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($databases as $database) {
            if (!$this->isDatabaseAllowed($database, $output)) {
                continue;
            }

            $schema2 = $this->createSchemaInfo($pdo2, $database, $output);
            $differences = $schemaDiff->diff($schema1, $schema2) || $differences;
        }

        return $differences ? 1 : 0;
    }

    private function createSchemaInfo(\PDO $pdo, string $database, OutputInterface $output): SchemaInfo
    {
        $output->writeln("Fetching schema details for database <info>{$database}</info>", Output::VERBOSITY_DEBUG);
        return SchemaInfoFactory::fromPdo(
            $pdo,
            $database,
            function ($tableName) use ($database, $output): bool {
                return $this->isTableAllowed($database, $tableName, $output);
            }
        );
    }

    private function initFilters(InputInterface $input): void
    {
        // databases
        $this->ignoreDatabases = [];
        if ($input->getOption('ignore-databases')) {
            foreach (explode(',', $input->getOption('ignore-databases')) as $database) {
                $this->ignoreDatabases[$database] = true;
            }
        }
        $this->ignoreDatabasesRegex = $input->getOption('ignore-databases-regex');

        $this->databases = [];
        if ($input->getOption('databases')) {
            foreach (explode(',', $input->getOption('databases')) as $database) {
                $this->databases[$database] = true;
            }
        }
        $this->databasesRegex = $input->getOption('databases-regex');

        // tables
        $this->ignoreTables = [];
        if ($input->getOption('ignore-tables')) {
            foreach (explode(',', $input->getOption('ignore-tables')) as $table) {
                [$database, $table] = $this->splitUnquoteTable($table);
                if (!isset($this->ignoreTables[$database])) {
                    $this->ignoreTables[$database] = [];
                }

                $this->ignoreTables[$database][$table] = true;
            }
        }
        $this->ignoreTablesRegex = $input->getOption('ignore-tables-regex');

        $this->tables = [];
        if ($input->getOption('tables')) {
            foreach (explode(',', $input->getOption('tables')) as $table) {
                [$database, $table] = $this->splitUnquoteTable($table);
                if (!isset($this->tables[$database])) {
                    $this->tables[$database] = [];
                }

                $this->tables[$database][$table] = true;
            }
        }
        $this->tablesRegex = $input->getOption('tables-regex');
    }

    private function splitUnquoteTable(string $table): array
    {
        [$database, $table] = array_pad(explode('.', $table, 2), 2, null);
        if (!$table) {
            $table = $database;
            $database = '*';
        }

        $regexes = [
            '/^`/' => '',
            '/`$/' => '',
            '/``/' => '`',
        ];

        foreach ($regexes as $pattern => $replacement) {
            $table = preg_replace($pattern, $replacement, $table);
        }

        foreach ($regexes as $pattern => $replacement) {
            $database = preg_replace($pattern, $replacement, $database);
        }

        return [$database, $table];
    }

    private function isDatabaseAllowed(string $database, OutputInterface $output): bool
    {
        if (preg_match('/mysql|information_schema|performance_schema|lost\+found|percona|percona_schema|test/', $database) > 0) {
            $output->writeln("Database <info>{$database}</info> is a system database, ignoring", Output::VERBOSITY_DEBUG);
            return false;
        }

        if (isset($this->ignoreDatabases[$database])) {
            $output->writeln("Database <info>{$database}</info> is in --ignore-databases list", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->ignoreDatabasesRegex && preg_match("/{$this->ignoreDatabasesRegex}/", $database)) {
            $output->writeln("Database <info>{$database}</info> matches --ignore-databases-regex", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->databases && !isset($this->databases[$database])) {
            $output->writeln("Database <info>{$database}</info> is not in --databases list, ignoring", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->databasesRegex && !preg_match("/{$this->databasesRegex}/", $database)) {
            $output->writeln("Database <info>{$database}</info> does not match --databases-regex, ignoring", Output::VERBOSITY_DEBUG);
            return false;
        }

        return true;
    }

    private function isTableAllowed(string $database, string $table, OutputInterface $output): bool
    {
        if (isset($this->ignoreTables['*'][$table]) || isset($this->ignoreTables[$database][$table])) {
            $output->writeln("Table <info>{$table}</info> is in --ignore-tables list", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->ignoreTablesRegex && preg_match("/{$this->ignoreTablesRegex}/", $table)) {
            $output->writeln("Table <info>{$table}</info> matches --ignore-tables-regex", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->tables && (!isset($this->tables['*'][$table]) && !isset($this->tables[$database][$table]))) {
            $output->writeln("Table <info>{$table}</info> is not in --tables list, ignoring", Output::VERBOSITY_DEBUG);
            return false;
        }

        if ($this->tablesRegex && !preg_match("/{$this->tablesRegex}/", $table)) {
            $output->writeln("Table <info>{$table}</info> does not match --tables-regex, ignoring", Output::VERBOSITY_DEBUG);
            return false;
        }

        return true;
    }

    private function parseDsn(string $dsn): array
    {
        $allowedOptions = 'hupPD';
        $parts = preg_split('/(?<!\\\\),/', $dsn);

        $options = [];
        foreach ($parts as $part) {
            $part = str_replace('\,', ',', $part);

            if (preg_match('/^(.*)=(.*)$/', $part, $matches)) {
                if (!strstr($allowedOptions, $matches[1])) {
                    throw new \InvalidArgumentException('DSN contains invalid key');
                }
                $options[$matches[1]] = $matches[2];
            }
        }

        return $options;
    }

    private function createPdo(array $options): \PDO
    {
        $host = $options['h'] ?? '';
        $user = $options['u'] ?? '';
        $pass = $options['p'] ?? '';
        $port = $options['P'] ?? 3306;

        $dsn = "mysql:host={$host};port={$port};charset=utf8";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        return new \PDO($dsn, $user, $pass, $options);
    }
}
