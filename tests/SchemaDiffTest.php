<?php

declare(strict_types=1);

namespace Phlib\SchemaDiff\Test;

use Phlib\SchemaDiff\SchemaDiff;
use Phlib\SchemaDiff\SchemaInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface as StyleInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package phlib/schemadiff
 */
class SchemaDiffTest extends TestCase
{
    /**
     * @var OutputInterface|MockObject
     */
    private MockObject $output;

    private SchemaDiff $diff;

    /**
     * @var SchemaInfo|MockObject
     */
    private MockObject $schema1;

    /**
     * @var SchemaInfo|MockObject
     */
    private MockObject $schema2;

    private string $schema1Name;

    private string $schema2Name;

    #[DataProvider('dataFormatterAddStyles')]
    public function testFormatterAddStyles(array $hasStyles): void
    {
        $formatter = $this->createMock(OutputFormatter::class);

        $formatter->expects(static::exactly(5))
            ->method('hasStyle')
            ->willReturnMap([
                ['schema', (bool)$hasStyles[0]],
                ['table', (bool)$hasStyles[1]],
                ['column', (bool)$hasStyles[2]],
                ['index', (bool)$hasStyles[3]],
                ['attribute', (bool)$hasStyles[4]],
            ]);

        $newStyles = [
            ['schema', new OutputFormatterStyle('green')],
            ['table', new OutputFormatterStyle('blue')],
            ['column', new OutputFormatterStyle('magenta')],
            ['index', new OutputFormatterStyle('cyan')],
            ['attribute', new OutputFormatterStyle('yellow')],
        ];

        $expectedStyles = array_values(array_filter(
            $newStyles,
            fn(int $idx): bool => !$hasStyles[$idx],
            ARRAY_FILTER_USE_KEY,
        ));

        $matcher = static::exactly(count($expectedStyles));
        $formatter->expects($matcher)
            ->method('setStyle')
            ->with(
                static::callback(function (string $actualName) use ($matcher, $expectedStyles): bool {
                    $expected = $expectedStyles[$matcher->numberOfInvocations() - 1];
                    static::assertSame($expected[0], $actualName);
                    return true;
                }),
                static::callback(function (StyleInterface $actualStyle) use ($matcher, $expectedStyles): bool {
                    $expected = $expectedStyles[$matcher->numberOfInvocations() - 1];
                    static::assertEquals($expected[1], $actualStyle);
                    return true;
                }),
            );

        $output = $this->createMock(OutputInterface::class);
        $output->expects(static::once())
            ->method('getFormatter')
            ->willReturn($formatter);

        new SchemaDiff($output);
    }

    public static function dataFormatterAddStyles(): array
    {
        $styleCount = 5;
        $totalCombos = 2 ** $styleCount;
        $combinations = [];
        for ($i = 0; $i < $totalCombos; $i++) {
            $combo = str_pad(decbin($i), $styleCount, '0', STR_PAD_LEFT);
            $combinations['c' . $combo] = [str_split($combo)];
        }
        return $combinations;
    }

    public function testDiffCompareSchemaInfo(): void
    {
        $this->initDiff();
        $this->initSchema();

        $attributeName = uniqid('attr_');
        $val1 = sha1(uniqid('one'));
        $val2 = sha1(uniqid('two'));

        $this->schema1->expects(static::once())
            ->method('getInfo')
            ->willReturn([
                $attributeName => $val1,
            ]);

        $this->schema2->expects(static::once())
            ->method('getInfo')
            ->willReturn([
                $attributeName => $val2,
            ]);

        $expected = [
            "<error>Schema attribute mismatch</error> attribute <attribute>{$attributeName}</attribute> differs:",
            "\t<schema>{$this->schema1Name}@1</schema>={$val1}",
            "\t<schema>{$this->schema2Name}@2</schema>={$val2}",
        ];
        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    #[DataProvider('dataSchemaOrder')]
    public function testDiffHasTable(int $order1, int $order2): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = sha1(uniqid('table'));

        // First schema has the table, second does not
        $this->{'schema' . $order1}->expects(static::once())
            ->method('getTables')
            ->willReturn([
                $tableName,
            ]);
        // Second schema is not checked for the table if the first was missing
        $expectation = ($order1 === 2) ? static::never() : static::once();
        $this->{'schema' . $order1}->expects($expectation)
            ->method('hasTable')
            ->with($tableName)
            ->willReturn(true);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('getTables')
            ->willReturn([]);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('hasTable')
            ->with($tableName)
            ->willReturn(false);

        $expected = "<error>Missing table</error> <table>{$tableName}</table> missing on " .
            "<schema>{$this->{'schema' . $order2 . 'Name'}}@{$order2}</schema> exists on " .
            "<schema>{$this->{'schema' . $order1 . 'Name'}}@{$order1}</schema>";

        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    public function testDiffCompareTableInfo(): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = $this->initSchemaTable();

        $attributeName = uniqid('attr_');
        $val1 = sha1(uniqid('one'));
        $val2 = sha1(uniqid('two'));

        $this->schema1->expects(static::once())
            ->method('getTableInfo')
            ->with($tableName)
            ->willReturn([
                $attributeName => $val1,
            ]);

        $this->schema2->expects(static::once())
            ->method('getTableInfo')
            ->with($tableName)
            ->willReturn([
                $attributeName => $val2,
            ]);

        $expected = [
            "<error>Table attribute mismatch</error> <table>{$tableName}</table> " .
                "attribute <attribute>{$attributeName}</attribute> differs:",
            "\t<schema>{$this->schema1Name}@1</schema>={$val1}",
            "\t<schema>{$this->schema2Name}@2</schema>={$val2}",
        ];
        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    #[DataProvider('dataSchemaOrder')]
    public function testDiffCompareColumns(int $order1, int $order2): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = $this->initSchemaTable();

        $columnName = sha1(uniqid('column'));

        // First schema has the column, second does not
        $this->{'schema' . $order1}->expects(static::once())
            ->method('getColumns')
            ->with($tableName)
            ->willReturn([
                $columnName,
            ]);
        // Second schema is not checked for the column if the first was missing
        $expectation = ($order1 === 2) ? static::never() : static::once();
        $this->{'schema' . $order1}->expects($expectation)
            ->method('hasColumn')
            ->with($tableName, $columnName)
            ->willReturn(true);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('getColumns')
            ->with($tableName)
            ->willReturn([]);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('hasColumn')
            ->with($tableName, $columnName)
            ->willReturn(false);

        $expected = '<error>Missing column</error> ' .
                "<table>{$tableName}</table>.<column>{$columnName}</column> missing on " .
            "<schema>{$this->{'schema' . $order2 . 'Name'}}@{$order2}</schema> exists on " .
            "<schema>{$this->{'schema' . $order1 . 'Name'}}@{$order1}</schema>";

        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    public function testDiffCompareColumnInfo(): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = $this->initSchemaTable();
        $columnName = $this->initSchemaColumn($tableName);

        $attributeName = uniqid('attr_');
        $val1 = sha1(uniqid('one'));
        $val2 = sha1(uniqid('two'));

        $this->schema1->expects(static::once())
            ->method('getColumnInfo')
            ->with($tableName, $columnName)
            ->willReturn([
                $attributeName => $val1,
            ]);

        $this->schema2->expects(static::once())
            ->method('getColumnInfo')
            ->with($tableName, $columnName)
            ->willReturn([
                $attributeName => $val2,
            ]);

        $expected = [
            '<error>Column attribute mismatch</error> ' .
                "<table>{$tableName}</table>.<column>{$columnName}</column> " .
                "attribute <attribute>{$attributeName}</attribute> differs:",
            "\t<schema>{$this->schema1Name}@1</schema>={$val1}",
            "\t<schema>{$this->schema2Name}@2</schema>={$val2}",
        ];
        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    #[DataProvider('dataSchemaOrder')]
    public function testDiffCompareIndexes(int $order1, int $order2): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = $this->initSchemaTable();

        $indexName = sha1(uniqid('index'));

        // First schema has the column, second does not
        $this->{'schema' . $order1}->expects(static::once())
            ->method('getIndexes')
            ->with($tableName)
            ->willReturn([
                $indexName,
            ]);
        // Second schema is not checked for the index if the first was missing
        $expectation = ($order1 === 2) ? static::never() : static::once();
        $this->{'schema' . $order1}->expects($expectation)
            ->method('hasIndex')
            ->with($tableName, $indexName)
            ->willReturn(true);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('getIndexes')
            ->with($tableName)
            ->willReturn([]);

        $this->{'schema' . $order2}->expects(static::once())
            ->method('hasIndex')
            ->with($tableName, $indexName)
            ->willReturn(false);

        $expected = '<error>Missing index</error> ' .
            "<table>{$tableName}</table>.<index>{$indexName}</index> missing on " .
            "<schema>{$this->{'schema' . $order2 . 'Name'}}@{$order2}</schema> exists on " .
            "<schema>{$this->{'schema' . $order1 . 'Name'}}@{$order1}</schema>";

        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    public function testDiffCompareIndexInfo(): void
    {
        $this->initDiff();
        $this->initSchema();

        $tableName = $this->initSchemaTable();
        $indexName = $this->initSchemaIndex($tableName);

        $attributeName = uniqid('attr_');
        $val1 = sha1(uniqid('one'));
        $val2 = sha1(uniqid('two'));

        $this->schema1->expects(static::once())
            ->method('getIndexInfo')
            ->with($tableName, $indexName)
            ->willReturn([
                $attributeName => $val1,
            ]);

        $this->schema2->expects(static::once())
            ->method('getIndexInfo')
            ->with($tableName, $indexName)
            ->willReturn([
                $attributeName => $val2,
            ]);

        $expected = [
            '<error>Index attribute mismatch</error> ' .
                "<table>{$tableName}</table>.<index>{$indexName}</index> " .
                "attribute <attribute>{$attributeName}</attribute> differs:",
            "\t<schema>{$this->schema1Name}@1</schema>={$val1}",
            "\t<schema>{$this->schema2Name}@2</schema>={$val2}",
        ];
        $this->output->expects(static::once())
            ->method('writeln')
            ->with($expected);

        $hasDiff = $this->diff->diff($this->schema1, $this->schema2);
        static::assertTrue($hasDiff);
    }

    private function initDiff(): void
    {
        $formatter = $this->createMock(OutputFormatter::class);

        $formatter->method('hasStyle')
            ->willReturn(true);

        $this->output = $this->createMock(OutputInterface::class);
        $this->output->method('getFormatter')
            ->willReturn($formatter);

        $this->diff = new SchemaDiff($this->output);
    }

    private function initSchema(): void
    {
        $this->schema1 = $this->createMock(SchemaInfo::class);
        $this->schema2 = $this->createMock(SchemaInfo::class);

        $this->schema1Name = sha1(uniqid('one'));
        $this->schema2Name = sha1(uniqid('two'));

        $this->schema1->method('getName')
            ->willReturn($this->schema1Name);

        $this->schema2->method('getName')
            ->willReturn($this->schema2Name);
    }

    private function initSchemaTable(): string
    {
        $tableName = sha1(uniqid('table'));

        $this->schema1->method('getTables')
            ->willReturn([
                $tableName,
            ]);
        $this->schema1->method('hasTable')
            ->with($tableName)
            ->willReturn(true);

        $this->schema2->method('getTables')
            ->willReturn([
                $tableName,
            ]);
        $this->schema2->method('hasTable')
            ->with($tableName)
            ->willReturn(true);

        return $tableName;
    }

    private function initSchemaColumn(string $tableName): string
    {
        $columnName = sha1(uniqid('column'));

        $this->schema1->method('getColumns')
            ->with($tableName)
            ->willReturn([
                $columnName,
            ]);
        $this->schema1->method('hasColumn')
            ->with($tableName, $columnName)
            ->willReturn(true);

        $this->schema2->method('getColumns')
            ->with($tableName)
            ->willReturn([
                $columnName,
            ]);
        $this->schema2->method('hasColumn')
            ->with($tableName, $columnName)
            ->willReturn(true);

        return $columnName;
    }

    private function initSchemaIndex(string $tableName): string
    {
        $indexName = sha1(uniqid('index'));

        $this->schema1->method('getIndexes')
            ->with($tableName)
            ->willReturn([
                $indexName,
            ]);
        $this->schema1->method('hasIndex')
            ->with($tableName, $indexName)
            ->willReturn(true);

        $this->schema2->method('getIndexes')
            ->with($tableName)
            ->willReturn([
                $indexName,
            ]);
        $this->schema2->method('hasIndex')
            ->with($tableName, $indexName)
            ->willReturn(true);

        return $indexName;
    }

    public static function dataSchemaOrder(): array
    {
        return [
            'one-two' => [1, 2],
            'two-one' => [2, 1],
        ];
    }
}
