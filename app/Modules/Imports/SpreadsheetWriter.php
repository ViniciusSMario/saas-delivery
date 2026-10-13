<?php

namespace App\Modules\Imports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/** Gera modelos e exportações (Excel com abas, cabeçalho destacado e larguras; CSV com ";"). */
class SpreadsheetWriter
{
    /**
     * @param  array<string, array{headers: list<string>, rows: iterable<list<mixed>>, widths?: list<float>, notes?: bool}>  $sheets
     *                                                                                                                            notes = aba de instruções (uma coluna larga, linhas em negrito marcadas com "# ")
     */
    public function xlsx(string $path, array $sheets): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $head = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('C2410C');
        $bold = (new Style)->setFontBold();
        $first = true;

        foreach ($sheets as $name => $sheet) {
            $current = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $current->setName($name);
            $first = false;

            if ($sheet['notes'] ?? false) {
                $current->setColumnWidth(110, 1);

                foreach ($sheet['rows'] as [$line]) {
                    $isTitle = str_starts_with((string) $line, '# ');
                    $writer->addRow(Row::fromValues([$isTitle ? substr($line, 2) : $line], $isTitle ? $bold : null));
                }

                continue;
            }

            foreach ($sheet['widths'] ?? [] as $i => $width) {
                $current->setColumnWidth($width, $i + 1);
            }

            $writer->addRow(Row::fromValues($sheet['headers'], $head));

            foreach ($sheet['rows'] as $values) {
                $writer->addRow(Row::fromValues($values));
            }
        }

        $writer->close();
    }

    /** @param  iterable<list<mixed>>  $rows */
    public function csv(string $path, array $headers, iterable $rows): void
    {
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = ';';
        $writer = new CsvWriter($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();
    }
}
