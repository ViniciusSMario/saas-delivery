<?php

namespace App\Livewire\Tenant\Menu\Combos;

use App\Modules\Menu\Actions\SaveCombo;
use App\Modules\Menu\Models\Combo;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Combos')]
class Index extends Component
{
    public function delete(int $id, SaveCombo $save): void
    {
        $this->authorize('menu.manage');
        $save->delete(Combo::findOrFail($id));
    }

    public function render()
    {
        return view('livewire.tenant.menu.combos.index', [
            'combos' => Combo::with('items.product')->orderBy('position')->get(),
        ]);
    }
}
