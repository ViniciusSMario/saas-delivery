<?php

namespace App\Modules\Imports;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Customers\Import\CustomersImport;
use App\Modules\Delivery\Import\ZonesImport;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Stock\Import\RecipesImport;
use App\Modules\Stock\Import\StockItemsImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Importações de uma aba (clientes, bairros, insumos, fichas): leitura, prévia, gravação tudo ou
 * nada, histórico e desfazer a última de cada tipo. O cardápio tem o fluxo próprio (Menu\Import).
 */
class TabularImporter
{
    public const MAX_ROWS = 3000;

    /** Endereço na URL => classe. */
    public const TYPES = [
        'clientes' => CustomersImport::class,
        'bairros' => ZonesImport::class,
        'insumos' => StockItemsImport::class,
        'fichas' => RecipesImport::class,
    ];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly SpreadsheetWriter $writer,
    ) {}

    public static function type(string $slug): TabularImport
    {
        return app(self::TYPES[$slug] ?? abort(404));
    }

    public static function slugOf(string $kind): ?string
    {
        foreach (self::TYPES as $slug => $class) {
            if (app($class)->kind() === $kind) {
                return $slug;
            }
        }

        return null;
    }

    /** Tipos que a pessoa pode importar (cada um tem a sua permissão). */
    public static function allowedFor(User $user): array
    {
        return array_filter(array_map(fn ($class) => app($class), self::TYPES), fn (TabularImport $type) => $user->can($type->permission()));
    }

    /**
     * @return array{rows: list<array>, columns: list<string>}
     *
     * @throws RuntimeException com a mensagem para o usuário
     */
    public function read(TabularImport $type, string $path, string $extension): array
    {
        $sheets = $this->reader->sheets($path, $extension);
        $rows = collect($sheets)->first(fn ($rows, $name) => ! preg_match('/instruc|como preencher|ajuda|leia/', SpreadsheetReader::plain($name))) ?? [];
        $table = $this->reader->table($rows, $type->columns());
        $labels = fn (array $keys) => implode(', ', array_map(fn ($key) => $type->columns()[$key][0], $keys));

        if ($table === null) {
            throw new RuntimeException('Não encontramos a linha de títulos. Use o modelo: a primeira linha precisa ter '.$labels($type->required()).'.');
        }

        if ($missing = array_values(array_diff($type->required(), $table['columns']))) {
            throw new RuntimeException('Faltam as colunas: '.$labels($missing).'. Use os títulos do modelo.');
        }

        return $table;
    }

    public function plan(TabularImport $type, string $path, string $extension, string $mode): ImportPlan
    {
        $table = $this->read($type, $path, $extension);

        if (count($table['rows']) > self::MAX_ROWS) {
            $plan = new ImportPlan;
            $plan->errors[] = 'A planilha tem mais de '.self::MAX_ROWS.' linhas. Divida em arquivos menores.';

            return $plan;
        }

        if ($table['rows'] === []) {
            $plan = new ImportPlan;
            $plan->errors[] = 'Nenhuma linha preenchida abaixo dos títulos.';

            return $plan;
        }

        return $type->plan($table['rows'], $table['columns'], $mode);
    }

    public function apply(TabularImport $type, ImportPlan $plan, string $mode, string $fileName, ?User $user): DataImport
    {
        if ($plan->hasErrors()) {
            throw ValidationException::withMessages(['file' => 'A planilha tem erros. Corrija e envie de novo.']);
        }

        return DB::connection('tenant')->transaction(function () use ($type, $plan, $mode, $fileName, $user) {
            $recorder = new ImportRecorder;
            $import = $recorder->start($type->kind(), $fileName, $mode, $user, $plan->counts());
            $type->apply($plan, $recorder, $user);

            Audit::record('data.imported', $import, ['tipo' => $type->title(), 'arquivo' => $import->file_name] + $plan->counts(), $user);

            return $import;
        });
    }

    public function undo(DataImport $import, ?User $user): void
    {
        $type = self::type(self::slugOf($import->kind) ?? abort(404));

        if (! $import->canUndo()) {
            throw ValidationException::withMessages(['import' => 'Só a última importação deste tipo pode ser desfeita.']);
        }

        DB::connection('tenant')->transaction(function () use ($type, $import, $user) {
            $type->undo($import->items()->orderByDesc('id')->get(), $user);
            $import->update(['status' => 'undone', 'undone_at' => now(), 'undone_by' => $user?->id]);
            Audit::record('data.import_undone', $import, ['tipo' => $type->title(), 'arquivo' => $import->file_name], $user);
        });
    }

    /** Modelo (com exemplos) ou dados atuais, em Excel (com instruções) ou CSV. */
    public function write(TabularImport $type, string $path, string $format, bool $current): void
    {
        $headers = array_column($type->columns(), 0);
        $rows = $current ? $type->current() : $type->examples();

        if ($format === 'csv') {
            $this->writer->csv($path, $headers, $rows);

            return;
        }

        $this->writer->xlsx($path, [
            ucfirst($type->noun()[1]) => ['headers' => $headers, 'rows' => $rows, 'widths' => $type->widths()],
            'Como preencher' => ['headers' => [], 'rows' => array_map(fn ($line) => [$line], $type->instructions()), 'notes' => true],
        ]);
    }
}
