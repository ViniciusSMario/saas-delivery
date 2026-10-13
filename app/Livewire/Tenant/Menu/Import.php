<?php

namespace App\Livewire\Tenant\Menu;

use App\Modules\Access\Models\User;
use App\Modules\Menu\Import\MenuImporter;
use App\Modules\Menu\Import\MenuImportPlan;
use App\Modules\Menu\Import\MenuImportPlanner;
use App\Modules\Menu\Import\MenuSpreadsheet;
use App\Modules\Menu\Import\PhotoArchive;
use App\Modules\Imports\Models\DataImport;
use App\Modules\Establishment\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importar o cardápio por planilha: envia a planilha (e as fotos em .zip), confere a prévia
 * e confirma. A prévia fica em cache; ao confirmar, a planilha é conferida de novo e gravada.
 */
#[Title('Importar cardápio')]
class Import extends Component
{
    use WithFileUploads;

    /** Linhas de produto mostradas na prévia (o resto aparece só nos totais). */
    public const PREVIEW_ROWS = 300;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var TemporaryUploadedFile|null */
    public $photos = null;

    /** update = atualizar os que já existem; skip = só criar os novos */
    public string $mode = 'update';

    public ?string $previewKey = null;

    public ?array $result = null;

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv,txt'],
            'photos' => ['nullable', 'file', 'max:20480', 'extensions:zip'],
            'mode' => ['required', 'in:update,skip'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['file' => 'planilha', 'photos' => 'fotos'];
    }

    public function updatedFile(): void
    {
        $this->preview();
    }

    public function updatedPhotos(): void
    {
        if ($this->file) {
            $this->preview();
        }
    }

    public function updatedMode(): void
    {
        if ($this->file) {
            $this->preview();
        }
    }

    public function preview(): void
    {
        $this->authorize('menu.manage');
        $this->result = null;
        $this->previewKey = null;
        $this->validate();

        if ($plan = $this->plan()) {
            $this->previewKey = 'menu-import:'.tenant()->getKey().':'.$this->user()->id.':'.Str::random(16);
            Cache::put($this->previewKey, $plan->toArray(), now()->addMinutes(30));
        }
    }

    public function apply(MenuImporter $importer): void
    {
        $this->authorize('menu.manage');
        $this->validate();

        // Confere de novo: o cardápio pode ter mudado desde a prévia.
        $photos = null;
        $plan = $this->plan(keepPhotosOpen: true, archive: $photos);

        if (! $plan) {
            return;
        }

        if ($plan->hasErrors() || ! $plan->hasWork()) {
            Cache::put($this->previewKey ??= 'menu-import:'.tenant()->getKey().':'.$this->user()->id.':'.Str::random(16), $plan->toArray(), now()->addMinutes(30));

            return;
        }

        try {
            $import = $importer->apply($plan, $this->mode, $this->file->getClientOriginalName(), $photos, $this->photos?->getClientOriginalName(), $this->user());
        } catch (ValidationException $e) {
            $this->addError('file', collect($e->errors())->flatten()->first());

            return;
        } finally {
            $photos?->close();
        }

        $this->result = $import->summary;
        $this->reset('file', 'photos', 'previewKey');
    }

    public function undo(int $importId, MenuImporter $importer): void
    {
        $this->authorize('menu.manage');

        try {
            $importer->undo(DataImport::ofKind('menu')->findOrFail($importId), $this->user());
        } catch (ValidationException $e) {
            $this->addError('import', collect($e->errors())->flatten()->first());

            return;
        }

        $this->result = null;
        $this->dispatch('import-undone');
    }

    public function startOver(): void
    {
        $this->reset('file', 'photos', 'previewKey', 'result');
        $this->resetValidation();
    }

    /**
     * Lê a planilha e monta o plano; erro de arquivo vira erro no campo.
     * Na prévia o .zip só é consultado; na gravação ele fica aberto para ler as fotos ($archive).
     */
    private function plan(bool $keepPhotosOpen = false, ?PhotoArchive &$archive = null): ?MenuImportPlan
    {
        $extension = strtolower($this->file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        try {
            $sheet = app(MenuSpreadsheet::class)->read($this->file->getRealPath(), $extension);
        } catch (\RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return null;
        }

        try {
            $archive = $this->photos ? new PhotoArchive($this->photos->getRealPath()) : null;
        } catch (\RuntimeException $e) {
            $this->addError('photos', $e->getMessage());

            return null;
        }

        $plan = app(MenuImportPlanner::class)->plan($sheet, $this->mode, $archive);

        if (! $keepPhotosOpen) {
            $archive?->close();
        }

        return $plan;
    }

    private function user(): User
    {
        return Auth::guard('web')->user();
    }

    public function render()
    {
        $imports = DataImport::ofKind('menu')->with('user')->latest('id')->limit(10)->get();

        return view('livewire.tenant.menu.import', [
            'preview' => $this->previewKey ? Cache::get($this->previewKey) : null,
            'previewRows' => self::PREVIEW_ROWS,
            'imports' => $imports,
            'undoable' => $imports->first(fn ($import) => MenuImporter::canUndo($import))?->id,
            'timezone' => Setting::read('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
