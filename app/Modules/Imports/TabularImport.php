<?php

namespace App\Modules\Imports;

use App\Modules\Access\Models\User;
use Illuminate\Support\Collection;

/**
 * Um tipo de dado que se importa por planilha de uma aba só (clientes, bairros, insumos, fichas).
 * O TabularImporter cuida do resto: leitura, prévia, transação, histórico e desfazer.
 */
interface TabularImport
{
    /** Tipo gravado no histórico (data_imports.kind). */
    public function kind(): string;

    public function title(): string;

    /** @return array{0: string, 1: string, 2: string} singular, plural e gênero ("ficha", "fichas", "f") */
    public function noun(): array;

    /** Permissão para importar e baixar os dados. */
    public function permission(): string;

    /** @return array<string, array{0: string, 1: list<string>}> chave => [título no modelo, outros nomes aceitos] */
    public function columns(): array;

    /** @return list<string> colunas obrigatórias */
    public function required(): array;

    /** @return list<float> largura de cada coluna no Excel */
    public function widths(): array;

    /** @return list<list<mixed>> linhas de exemplo do modelo */
    public function examples(): array;

    /** @return list<string> aba "Como preencher" ("# " no começo = título) */
    public function instructions(): array;

    /** @return iterable<list<mixed>> dados atuais no formato do modelo (exportar para editar e importar de volta) */
    public function current(): iterable;

    /**
     * @param  list<array{line: int, values: array<string, mixed>}>  $rows
     * @param  list<string>  $columns  colunas presentes na planilha
     * @param  string  $mode  update = atualiza o que já existe; skip = só cria o que é novo
     */
    public function plan(array $rows, array $columns, string $mode): ImportPlan;

    /** Grava o plano (já dentro da transação) anotando cada registro no $recorder. */
    public function apply(ImportPlan $plan, ImportRecorder $recorder, ?User $user): void;

    /** Desfaz (já dentro da transação): itens da importação, do último para o primeiro. */
    public function undo(Collection $items, ?User $user): void;
}
