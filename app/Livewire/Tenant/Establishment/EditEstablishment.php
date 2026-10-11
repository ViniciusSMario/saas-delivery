<?php

namespace App\Livewire\Tenant\Establishment;

use App\Modules\Access\Audit;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\OpeningHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Estabelecimento')]
class EditEstablishment extends Component
{
    use WithFileUploads;

    /** @var array<string, string|null> */
    public array $form = [];

    /** @var array<int, array{is_closed: bool, opens_at: string|null, closes_at: string|null}> */
    public array $hours = [];

    public $logo;

    public bool $hasLogo = false;

    /** Foto de capa do cardápio público. */
    public $cover;

    public bool $hasCover = false;

    public function mount(): void
    {
        $establishment = Establishment::current();

        foreach ((new Establishment)->getFillable() as $field) {
            if (! in_array($field, ['logo_path', 'cover_path'], true)) {
                $this->form[$field] = $establishment->{$field};
            }
        }

        $this->hasLogo = (bool) $establishment->logo_path;
        $this->hasCover = (bool) $establishment->cover_path;

        OpeningHour::seedDefaults();

        foreach (OpeningHour::orderBy('weekday')->get() as $hour) {
            $this->hours[$hour->weekday] = [
                'is_closed' => $hour->is_closed,
                'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'form.trade_name' => ['required', 'string', 'max:120'],
            'form.legal_name' => ['nullable', 'string', 'max:160'],
            'form.document' => ['nullable', 'string', 'regex:/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/'],
            'form.phone' => ['nullable', 'string', 'max:20'],
            'form.email' => ['nullable', 'email', 'max:160'],
            'form.zip_code' => ['nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'form.street' => ['nullable', 'string', 'max:160'],
            'form.number' => ['nullable', 'string', 'max:20'],
            'form.complement' => ['nullable', 'string', 'max:120'],
            'form.district' => ['nullable', 'string', 'max:120'],
            'form.city' => ['nullable', 'string', 'max:120'],
            'form.state' => ['nullable', 'string', 'size:2'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'hours.*.is_closed' => ['boolean'],
            'hours.*.opens_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.trade_name' => 'nome fantasia',
            'form.legal_name' => 'razão social',
            'form.document' => 'CNPJ',
            'form.zip_code' => 'CEP',
            'form.state' => 'UF',
            'hours.*.opens_at' => 'abertura',
            'hours.*.closes_at' => 'fechamento',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.document.regex' => 'Informe um CNPJ no formato 00.000.000/0000-00.',
            'form.zip_code.regex' => 'Informe um CEP no formato 00000-000.',
        ];
    }

    public function save(): void
    {
        $this->authorize('establishment.manage');
        $this->validate();

        DB::connection('tenant')->transaction(function () {
            $establishment = Establishment::current();
            $establishment->fill(array_map(fn ($v) => $v === '' ? null : $v, $this->form));
            $establishment->state = $establishment->state ? mb_strtoupper($establishment->state) : null;

            if ($this->logo) {
                $old = $establishment->logo_path;
                // Caminho gerado aqui, dentro do disco do tenant; nunca vem do usuário.
                $establishment->logo_path = $this->logo->store('establishment', 'tenant');

                if ($old && $old !== $establishment->logo_path) {
                    Storage::disk('tenant')->delete($old);
                }
            }

            if ($this->cover) {
                $old = $establishment->cover_path;
                $establishment->cover_path = $this->cover->store('establishment', 'tenant');

                if ($old && $old !== $establishment->cover_path) {
                    Storage::disk('tenant')->delete($old);
                }
            }

            $isNew = ! $establishment->exists;
            $establishment->save();

            if ($isNew || $establishment->wasChanged()) {
                Audit::record('establishment.updated', $establishment, $isNew ? ['nome' => $establishment->trade_name] : Audit::diff($establishment));
            }

            $hourChanges = [];

            foreach ($this->hours as $weekday => $data) {
                $hour = OpeningHour::firstOrNew(['weekday' => $weekday]);
                $hour->fill([
                    'is_closed' => (bool) $data['is_closed'],
                    'opens_at' => $data['is_closed'] ? null : $data['opens_at'],
                    'closes_at' => $data['is_closed'] ? null : $data['closes_at'],
                ])->save();

                if ($hour->wasChanged()) {
                    $hourChanges[$hour->label()] = $hour->is_closed ? 'fechado' : "{$data['opens_at']}–{$data['closes_at']}";
                }
            }

            if ($hourChanges !== []) {
                Audit::record('opening_hours.updated', null, $hourChanges);
            }
        });

        $this->reset('logo', 'cover');
        $this->hasLogo = (bool) Establishment::current()->logo_path;
        $this->hasCover = (bool) Establishment::current()->cover_path;

        session()->flash('status', 'Dados do estabelecimento salvos.');
        $this->redirectRoute('establishment.edit', navigate: true);
    }

    public function removeLogo(): void
    {
        $this->authorize('establishment.manage');

        $establishment = Establishment::current();

        if ($establishment->exists && $establishment->logo_path) {
            Storage::disk('tenant')->delete($establishment->logo_path);
            $establishment->update(['logo_path' => null]);
            Audit::record('establishment.updated', $establishment, ['logo' => 'removido']);
        }

        $this->hasLogo = false;
    }

    public function removeCover(): void
    {
        $this->authorize('establishment.manage');

        $establishment = Establishment::current();

        if ($establishment->exists && $establishment->cover_path) {
            Storage::disk('tenant')->delete($establishment->cover_path);
            $establishment->update(['cover_path' => null]);
            Audit::record('establishment.updated', $establishment, ['capa' => 'removida']);
        }

        $this->hasCover = false;
    }

    public function render()
    {
        return view('livewire.tenant.establishment.edit-establishment', [
            'weekdays' => OpeningHour::WEEKDAYS,
        ]);
    }
}
