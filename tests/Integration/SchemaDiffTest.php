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
class SchemaDiffTest extends SchemaDiffTestCase
{
    protected function runDiff(string $tableName, string &$output = null): bool
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
}
