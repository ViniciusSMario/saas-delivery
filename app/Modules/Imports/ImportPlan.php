<?php

namespace App\Modules\Imports;

/**
 * Prévia de uma importação em tabela (clientes, bairros, insumos, fichas): uma linha por registro
 * com a ação (criar, atualizar, igual ou pular), o que muda, os erros que impedem gravar e avisos.
 * "data" leva o que o importador precisa para gravar (não vai para a tela).
 */
class ImportPlan
{
    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<array{line: int|string, action: string, label: string, detail: string, changes: list<string>, data: array}> */
    public array $rows = [];

    public function add(int|string $line, string $action, string $label, string $detail = '', array $changes = [], array $data = []): void
    {
        $this->rows[] = compact('line', 'action', 'label', 'detail', 'changes', 'data');
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array{created: int, updated: int, same: int, skipped: int} */
    public function counts(): array
    {
        $by = collect($this->rows)->countBy('action');

        return ['created' => $by['create'] ?? 0, 'updated' => $by['update'] ?? 0, 'same' => $by['same'] ?? 0, 'skipped' => $by['skip'] ?? 0];
    }

    public function hasWork(): bool
    {
        $counts = $this->counts();

        return $counts['created'] + $counts['updated'] > 0;
    }

    public function toArray(): array
    {
        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'counts' => $this->counts(),
            'hasWork' => $this->hasWork(),
            'rows' => array_map(fn ($row) => array_diff_key($row, ['data' => true]), $this->rows),
        ];
    }
}
