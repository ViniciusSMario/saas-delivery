<?php

namespace App\Livewire\Tenant\Stock;

use App\Livewire\Tenant\Concerns\Toasts;
use App\Modules\Access\Audit;
use App\Modules\Stock\Models\StockItem;
use App\Support\Quantity;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use Toasts, WithPagination;

    #[Locked]
    public int $itemId;

    public string $name = '';

    public string $minQuantity = '';

    public bool $isActive = true;

    public function mount(StockItem $item): void
    {
        $this->itemId = $item->id;
        $this->name = $item->name;
        $this->minQuantity = Quantity::format($item->min_quantity);
        $this->isActive = $item->is_active;
    }

    public function save(): void
    {
        $this->authorize('stock.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tenant.stock_items', 'name')->ignore($this->itemId)],
            'minQuantity' => ['required', fn ($a, $v, $fail) => Quantity::isValid($v) ? null : $fail('Quantidade inválida.')],
        ], attributes: ['name' => 'nome', 'minQuantity' => 'estoque mínimo']);

        $item = StockItem::findOrFail($this->itemId);
        $item->update(['name' => trim($this->name), 'min_quantity' => Quantity::parse($this->minQuantity), 'is_active' => $this->isActive]);

        if ($item->wasChanged()) {
            Audit::record('stock_item.updated', $item, Audit::diff($item));
        }

        $this->toast('Insumo salvo.');
    }

    public function render()
    {
        $item = StockItem::with('unit')->findOrFail($this->itemId);

        return view('livewire.tenant.stock.show', [
            'item' => $item,
            'movements' => $item->movements()->with(['user', 'order'])->paginate(30),
            'usedIn' => \App\Modules\Stock\Models\Recipe::whereHas('items', fn ($q) => $q->where('stock_item_id', $item->id))->with(['product', 'addon'])->get(),
        ])->title($item->name);
    }
}
