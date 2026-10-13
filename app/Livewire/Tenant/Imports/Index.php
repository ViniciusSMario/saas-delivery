<?php

namespace App\Livewire\Tenant\Imports;

use App\Modules\Access\Models\User;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Imports\TabularImport;
use App\Modules\Imports\TabularImporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importar dados por planilha: clientes, bairros, insumos e fichas técnicas (o cardápio tem a
 * tela própria). Envia, confere a prévia e confirma; a última importação de cada tipo se desfaz.
 */
#[Title('Importar dados')]
class Index extends Component
{
    use WithFileUploads;

    public const PREVIEW_ROWS = 300;

    #[Url(as: 'tipo')]
    public string $type = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $mode = 'update';

    public ?string $previewKey = null;

    public ?array $result = null;

    public function mount(): void
    {
        $allowed = array_keys($this->allowed());

        abort_if($allowed === [] && ! $this->user()->can('menu.manage'), 403);

        if (! in_array($this->type, $allowed, true)) {
            $this->type = $allowed[0] ?? '';
        }

        // Só o cardápio liberado: vai direto para a tela dele.
        if ($this->type === '') {
            $this->redirectRoute('menu.import', navigate: true);
        }
    }

    public function choose(string $type): void
    {
        abort_unless(array_key_exists($type, $this->allowed()), 403);
        $this->type = $type;
        $this->startOver();
    }

    public function updatedFile(): void
    {
        $this->preview();
    }

    public function updatedMode(): void
    {
        if ($this->file) {
            $this->preview();
        }
    }

    public function preview(): void
    {
        $type = $this->current();
        $this->result = null;
        $this->previewKey = null;
        $this->validate();

        try {
            $plan = app(TabularImporter::class)->plan($type, $this->file->getRealPath(), $this->extension(), $this->mode);
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->previewKey = 'data-import:'.tenant()->getKey().':'.$this->user()->id.':'.Str::random(16);
        Cache::put($this->previewKey, $plan->toArray(), now()->addMinutes(30));
    }

    public function apply(TabularImporter $importer): void
    {
        $type = $this->current();
        $this->validate();

        try {
            // Confere de novo: os dados podem ter mudado desde a prévia.
            $plan = $importer->plan($type, $this->file->getRealPath(), $this->extension(), $this->mode);

            if ($plan->hasErrors() || ! $plan->hasWork()) {
                $this->previewKey ??= 'data-import:'.tenant()->getKey().':'.$this->user()->id.':'.Str::random(16);
                Cache::put($this->previewKey, $plan->toArray(), now()->addMinutes(30));

                return;
            }

            $import = $importer->apply($type, $plan, $this->mode, $this->file->getClientOriginalName(), $this->user());
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        } catch (ValidationException $e) {
            $this->addError('file', collect($e->errors())->flatten()->first());

            return;
        }

        $this->result = $import->summary;
        $this->reset('file', 'previewKey');
    }

    public function undo(int $importId, TabularImporter $importer): void
    {
        $type = $this->current();

        try {
            $importer->undo(DataImport::ofKind($type->kind())->findOrFail($importId), $this->user());
        } catch (ValidationException $e) {
            $this->addError('import', collect($e->errors())->flatten()->first());

            return;
        }

        $this->result = null;
    }

    public function startOver(): void
    {
        $this->reset('file', 'previewKey', 'result');
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv,txt'],
            'mode' => ['required', 'in:update,skip'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['file' => 'planilha'];
    }

    /** @return array<string, TabularImport> */
    private function allowed(): array
    {
        return TabularImporter::allowedFor($this->user());
    }

    private function current(): TabularImport
    {
        $type = $this->allowed()[$this->type] ?? abort(403);
        $this->authorize($type->permission());

        return $type;
    }

    private function extension(): string
    {
        return strtolower($this->file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $type = $this->allowed()[$this->type] ?? null;
        $imports = $type ? DataImport::ofKind($type->kind())->with('user')->latest('id')->limit(10)->get() : collect();

        return view('livewire.tenant.imports.index', [
            'types' => $this->allowed(),
            'typeInfo' => $type,
            'canMenu' => $this->user()->can('menu.manage'),
            'preview' => $this->previewKey ? Cache::get($this->previewKey) : null,
            'previewRows' => self::PREVIEW_ROWS,
            'imports' => $imports,
            'undoable' => $imports->first(fn (DataImport $import) => $import->canUndo())?->id,
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
