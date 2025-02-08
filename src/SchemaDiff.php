<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package phlib/schemadiff
 */
class SchemaDiff
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
        $this->initStyles();
    }

    private function initStyles(): void
    {
        $formatter = $this->output->getFormatter();

        $styles = [
            'schema' => new OutputFormatterStyle('green'),
            'table' => new OutputFormatterStyle('blue'),
            'column' => new OutputFormatterStyle('magenta'),
            'index' => new OutputFormatterStyle('cyan'),
            'attribute' => new OutputFormatterStyle('yellow'),
        ];

        foreach ($styles as $name => $style) {
            if ($formatter->hasStyle($name)) {
                continue;
            }

            $formatter->setStyle($name, $style);
        }
    }

    public function diff(SchemaInfo $schema1, SchemaInfo $schema2): bool
    {
        $differences = false;

        $differences = $this->compareSchemaInfo($schema1, $schema2) || $differences;

        $tables = array_unique(
            array_merge(
                $schema1->getTables(),
                $schema2->getTables(),
            ),
        );

        $msg = '<error>Missing table</error> <table>%s</table> missing on <schema>%s</schema> exists on <schema>%s</schema>';
        foreach ($tables as $tableName) {
            if (!$schema1->hasTable($tableName)) {
                $differences = true;
                $this->output->writeln(sprintf(
                    $msg,
                    $tableName,
                    $schema1->getName() . '@1',
                    $schema2->getName() . '@2',
                ));

                continue;
            }
            if (!$schema2->hasTable($tableName)) {
                $differences = true;
                $this->output->writeln(sprintf(
                    $msg,
                    $tableName,
                    $schema2->getName() . '@2',
                    $schema1->getName() . '@1',
                ));

                continue;
            }

            $differences = $this->compareTableInfo($schema1, $schema2, $tableName) || $differences;
            $differences = $this->compareColumns($schema1, $schema2, $tableName) || $differences;
            $differences = $this->compareIndexes($schema1, $schema2, $tableName) || $differences;
        }

        return $differences;
    }

    private function compareSchemaInfo(SchemaInfo $schema1, SchemaInfo $schema2): bool
    {
        $differences = false;
        $schemaInfo1 = $schema1->getInfo();
        $schemaInfo2 = $schema2->getInfo();

        $attributes = array_keys($schemaInfo1);

        foreach ($attributes as $attribute) {
            if ($schemaInfo1[$attribute] !== $schemaInfo2[$attribute]) {
                $differences = true;
                $this->output->writeln([
                    "<error>Schema attribute mismatch</error> attribute <attribute>{$attribute}</attribute> differs:",
                    "\t<schema>{$schema1->getName()}@1</schema>={$schemaInfo1[$attribute]}",
                    "\t<schema>{$schema2->getName()}@2</schema>={$schemaInfo2[$attribute]}",
                ]);
            }
        }

        return $differences;
    }

    private function compareTableInfo(SchemaInfo $schema1, SchemaInfo $schema2, string $tableName): bool
    {
        $differences = false;
        $tableInfo1 = $schema1->getTableInfo($tableName);
        $tableInfo2 = $schema2->getTableInfo($tableName);

        $attributes = array_keys($tableInfo1);

        foreach ($attributes as $attribute) {
            if ($tableInfo1[$attribute] !== $tableInfo2[$attribute]) {
                $differences = true;
                $this->output->writeln([
                    "<error>Table attribute mismatch</error> <table>{$tableName}</table> attribute <attribute>{$attribute}</attribute> differs:",
                    "\t<schema>{$schema1->getName()}@1</schema>={$tableInfo1[$attribute]}",
                    "\t<schema>{$schema2->getName()}@2</schema>={$tableInfo2[$attribute]}",
                ]);
            }
        }

        return $differences;
    }

    private function compareColumns(SchemaInfo $schema1, SchemaInfo $schema2, string $tableName): bool
    {
        $differences = false;
        $columns1 = $schema1->getColumns($tableName);
        $columns2 = $schema2->getColumns($tableName);

        $columns = array_unique(array_merge($columns1, $columns2));

        $msg = '<error>Missing column</error> <table>%s</table>.<column>%s</column> missing on <schema>%s</schema> exists on <schema>%s</schema>';
        foreach ($columns as $columnName) {
            if (!$schema1->hasColumn($tableName, $columnName)) {
                $differences = true;
                $this->output->writeln(
                    sprintf(
                        $msg,
                        $tableName,
                        $columnName,
                        $schema1->getName() . '@1',
                        $schema2->getName() . '@2',
                    ),
                );

                continue;
            }

            if (!$schema2->hasColumn($tableName, $columnName)) {
                $differences = true;
                $this->output->writeln(
                    sprintf(
                        $msg,
                        $tableName,
                        $columnName,
                        $schema2->getName() . '@2',
                        $schema1->getName() . '@1',
                    ),
                );

                continue;
            }

            $differences = $this->compareColumnInfo($schema1, $schema2, $tableName, $columnName) || $differences;
        }

        return $differences;
    }

    private function compareColumnInfo(SchemaInfo $schema1, SchemaInfo $schema2, string $tableName, string $columnName): bool
    {
        $differences = false;
        $columnInfo1 = $schema1->getColumnInfo($tableName, $columnName);
        $columnInfo2 = $schema2->getColumnInfo($tableName, $columnName);

        $attributes = array_keys($columnInfo1);
        foreach ($attributes as $attribute) {
            if ($columnInfo1[$attribute] !== $columnInfo2[$attribute]) {
                $differences = true;
                $this->output->writeln([
                    "<error>Column attribute mismatch</error> <table>{$tableName}</table>.<column>{$columnName}</column> attribute <attribute>{$attribute}</attribute> differs:",
                    "\t<schema>{$schema1->getName()}@1</schema>={$columnInfo1[$attribute]}",
                    "\t<schema>{$schema2->getName()}@2</schema>={$columnInfo2[$attribute]}",
                ]);
            }
        }

        return $differences;
    }

    private function compareIndexes(SchemaInfo $schema1, SchemaInfo $schema2, string $tableName): bool
    {
        $differences = false;
        $indexes1 = $schema1->getIndexes($tableName);
        $indexes2 = $schema2->getIndexes($tableName);

        $indexes = array_unique(array_merge($indexes1, $indexes2));

        $msg = '<error>Missing index</error> <table>%s</table>.<index>%s</index> missing on <schema>%s</schema> exists on <schema>%s</schema>';
        foreach ($indexes as $indexName) {
            if (!$schema1->hasIndex($tableName, $indexName)) {
                $differences = true;
                $this->output->writeln(sprintf(
                    $msg,
                    $tableName,
                    $indexName,
                    $schema1->getName() . '@1',
                    $schema2->getName() . '@2',
                ));

                continue;
            }

            if (!$schema2->hasIndex($tableName, $indexName)) {
                $differences = true;
                $this->output->writeln(sprintf(
                    $msg,
                    $tableName,
                    $indexName,
                    $schema2->getName() . '@2',
                    $schema1->getName() . '@1',
                ));

                continue;
            }

            $differences = $this->compareIndexInfo($schema1, $schema2, $tableName, $indexName) || $differences;
        }

        return $differences;
    }

    private function compareIndexInfo(SchemaInfo $schema1, SchemaInfo $schema2, string $tableName, string $indexName): bool
    {
        $differences = false;
        $indexInfo1 = $schema1->getIndexInfo($tableName, $indexName);
        $indexInfo2 = $schema2->getIndexInfo($tableName, $indexName);

        $attributes = array_keys($indexInfo1);
        foreach ($attributes as $attribute) {
            if ($indexInfo1[$attribute] !== $indexInfo2[$attribute]) {
                $differences = true;
                $this->output->writeln([
                    "<error>Index attribute mismatch</error> <table>{$tableName}</table>.<index>{$indexName}</index> attribute <attribute>{$attribute}</attribute> differs:",
                    "\t<schema>{$schema1->getName()}@1</schema>={$indexInfo1[$attribute]}",
                    "\t<schema>{$schema2->getName()}@2</schema>={$indexInfo2[$attribute]}",
                ]);
            }
        }

        return $differences;
    }
}
