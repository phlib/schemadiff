<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @package phlib/schemadiff
 */
abstract class IntegrationTestCase extends TestCase
{
    protected \PDO $pdo;

    private array $schemaTableQuoted = [];

    protected function setUp(): void
    {
        if ((bool)getenv('INTEGRATION_ENABLED') !== true) {
            static::markTestSkipped();
            return;
        }

        parent::setUp();

        $this->pdo = new \PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->schemaTableQuoted as $schemaTableQuoted) {
            $this->pdo->query("DROP TABLE {$schemaTableQuoted}");
        }

        parent::tearDown();
    }

    final protected function generateTableName(): string
    {
        return 'phlib_schemadiff_test_' . substr(sha1(uniqid()), 0, 10);
    }

    final protected function createTestTable(
        string $schemaName,
        string $tableName,
        bool $charCol = true,
        bool $charIdx = false,
        string $colCharset = null,
        string $tableCharset = 'ascii',
    ) {
        $schemaTableQuoted = '`' . $schemaName . "`.`{$tableName}`";

        $sql = <<<SQL
CREATE TABLE {$schemaTableQuoted} (
  `test_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
SQL;

        if ($charCol) {
            $sql .= '  `char_col` varchar(255)';
            if ($colCharset !== null) {
                $sql .= 'CHARACTER SET ' . $colCharset;
            }
            $sql .= " DEFAULT NULL,\n";
        }

        $sql .= <<<SQL
  `update_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`)
SQL;

        if ($charIdx) {
            $sql .= ",\n  INDEX `idx_char` (`char_col`)\n";
        }

        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET={$tableCharset}";

        $this->pdo->query($sql);

        $this->schemaTableQuoted[] = $schemaTableQuoted;
    }
}
