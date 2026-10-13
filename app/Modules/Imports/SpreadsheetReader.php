<?php

namespace App\Modules\Imports;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Leitura de planilha (Excel ou CSV) com cabeçalhos flexíveis: "Preço", "valor", "PREÇO (R$)"...
 * O CSV do Excel brasileiro (Windows-1252 e ";") também é lido certo.
 */
class SpreadsheetReader
{
    /** Linhas lidas por aba (além disso a planilha é recusada pelo importador). */
    public const MAX_ROWS = 5000;

    /**
     * @return array<string, list<list<mixed>>> nome da aba => linhas (CSV vira uma aba "dados")
     *
     * @throws RuntimeException com a mensagem para o usuário
     */
    public function sheets(string $path, string $extension): array
    {
        return strtolower($extension) === 'xlsx' ? $this->xlsxSheets($path) : ['dados' => $this->csvRows($path)];
    }

    /**
     * Acha a linha de títulos (até a 10ª linha) e devolve as linhas seguintes por coluna.
     *
     * @param  array<string, array{0: string, 1: list<string>}>  $columns  chave => [título, outros nomes aceitos sem acento]
     * @return array{columns: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}|null
     */
    public function table(array $rows, array $columns): ?array
    {
        foreach (array_slice($rows, 0, 10, true) as $headerIndex => $header) {
            $map = [];

            foreach ($header as $index => $title) {
                $title = self::plain(preg_replace('/\(.*?\)|\*/', '', (string) $title));

                foreach ($columns as $key => [$label, $aliases]) {
                    if (! isset($map[$key]) && ($title === self::plain($label) || in_array($title, $aliases, true))) {
                        $map[$key] = $index;
                        break;
                    }
                }
            }

            if (count($map) < 2) {
                continue;
            }

            $result = [];

            foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
                $values = [];

                foreach ($map as $key => $column) {
                    $values[$key] = self::cell($row[$column] ?? null);
                }

                // Linha toda em branco: ignora.
                if (array_filter($values, fn ($v) => $v !== null && $v !== '') === []) {
                    continue;
                }

                $result[] = ['line' => $index + 1, 'values' => $values];
            }

            return ['columns' => array_keys($map), 'rows' => $result];
        }

        return null;
    }

    /** @return array<string, list<list<mixed>>> */
    private function xlsxSheets(string $path): array
    {
        $reader = new XlsxReader;

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new RuntimeException('Não conseguimos abrir a planilha. Salve como Excel (.xlsx) ou CSV e tente de novo.');
        }

        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $index => $row) {
                $rows[$index - 1] = $row->toArray();

                if (count($rows) > self::MAX_ROWS + 50) {
                    break;
                }
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }

    /** @return list<list<mixed>> */
    private function csvRows(string $path): array
    {
        $raw = (string) file_get_contents($path, length: 5 * 1024 * 1024);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        // Excel no Brasil salva CSV em Windows-1252 e separado por ";".
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $temp = tempnam(sys_get_temp_dir(), 'planilha');
        file_put_contents($temp, $raw);

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = $delimiter;
        $reader = new CsvReader($options);
        $rows = [];

        try {
            $reader->open($temp);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $rows[$index - 1] = $row->toArray();

                    if (count($rows) > self::MAX_ROWS + 50) {
                        break;
                    }
                }

                break;
            }
        } catch (\Throwable) {
            throw new RuntimeException('Não conseguimos ler o arquivo CSV. Salve de novo pelo Excel ou use o modelo.');
        } finally {
            $reader->close();
            @unlink($temp);
        }

        return $rows;
    }

    private static function cell(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('d/m/Y'),
            is_string($value) => trim(preg_replace('/\s+/u', ' ', $value)),
            default => $value,
        };
    }

    /** Sem acento, minúsculas e espaços simples (para comparar títulos e nomes). */
    public static function plain(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** Texto da célula (número vira texto; vazio vira ""). */
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string) $value)) : '';
    }

    public static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** "29,90", "R$ 1.234,56", "29.90", 29.9 → centavos; null se não for valor. */
    public static function money(mixed $value, int $max = 10_000_000): ?int
    {
        if (is_int($value) || is_float($value)) {
            $cents = (int) round($value * 100);
        } else {
            $text = str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value);

            if (str_contains($text, ',')) {
                $text = str_replace(['.', ','], ['', '.'], $text);
            } elseif (substr_count($text, '.') > 1) {
                $text = str_replace('.', '', $text);
            }

            if (! preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
                return null;
            }

            $cents = (int) round(((float) $text) * 100);
        }

        return $cents >= 0 && $cents <= $max ? $cents : null;
    }

    /** sim/não, s/n, x, 1/0, verdadeiro/falso; null quando vazio ou desconhecido. */
    public static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $plain = self::plain((string) $value);

        return match (true) {
            in_array($plain, ['sim', 's', 'x', '1', 'yes', 'y', 'true', 'verdadeiro', 'ok', 'ativo'], true) => true,
            in_array($plain, ['nao', 'n', '0', 'no', 'false', 'falso', '-', 'inativo'], true) => false,
            default => null,
        };
    }
}
