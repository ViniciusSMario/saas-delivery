<?php

namespace App\Livewire\Tenant\Menu\AddonGroups;

use App\Modules\Menu\Actions\SaveAddonGroup;
use App\Modules\Menu\Models\AddonGroup;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Adicionais')]
class Index extends Component
{
    public function delete(int $id, SaveAddonGroup $save): void
    {
        $this->authorize('menu.manage');
        $save->delete(AddonGroup::findOrFail($id));
    }

    public function render()
    {
        return view('livewire.tenant.menu.addon-groups.index', [
            'groups' => AddonGroup::with('addons')->withCount('products')->orderBy('name')->get(),
        ]);
    }
}
