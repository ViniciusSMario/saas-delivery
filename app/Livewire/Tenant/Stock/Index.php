<?php

namespace App\Livewire\Tenant\Stock;

use App\Modules\Access\Audit;
use App\Modules\Finance\FinanceBook;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\Unit;
use App\Modules\Stock\StockLedger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Estoque')]
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public bool $onlyLow = false;

    // Novo insumo
    public string $name = '';

    public ?int $unitId = null;

    public string $minQuantity = '0';

    // Movimento (entrada, perda ou contagem) de um insumo
    public ?int $movingId = null;

    public string $movement = 'purchase';

    public string $quantity = '';

    public string $cost = '';

    public string $reason = '';

    // Compra: gerar conta a pagar no financeiro (opcional, desmarcado por padrão)
    public bool $createPayable = false;

    public string $supplier = '';

    public string $dueDate = '';

    public function mount(): void
    {
        $this->unitId = Unit::where('code', 'kg')->value('id');
    }

    public function create(): void
    {
        $this->authorize('stock.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tenant.stock_items', 'name')],
            'unitId' => ['required', Rule::exists('tenant.units', 'id')],
            'minQuantity' => ['required', fn ($a, $v, $fail) => Quantity::isValid($v) && Quantity::compare(Quantity::parse($v), '0') >= 0 ? null : $fail('Quantidade inválida.')],
        ], attributes: ['name' => 'nome', 'unitId' => 'unidade', 'minQuantity' => 'estoque mínimo']);

        $item = StockItem::create(['name' => trim($this->name), 'unit_id' => $this->unitId, 'min_quantity' => Quantity::parse($this->minQuantity)]);
        Audit::record('stock_item.created', $item, ['nome' => $item->name]);
        $this->reset('name', 'minQuantity');
    }

    public function startMovement(int $id, string $type): void
    {
        $this->movingId = $id;
        $this->movement = in_array($type, ['purchase', 'loss', 'count'], true) ? $type : 'purchase';
        $this->reset('quantity', 'cost', 'reason', 'createPayable', 'supplier');
        $this->dueDate = now()->toDateString();
        $this->resetErrorBag();
    }

    public function saveMovement(StockLedger $ledger): void
    {
        $this->authorize('stock.manage');

        if (! Quantity::isValid($this->quantity)) {
            $this->addError('quantity', 'Quantidade inválida. Ex.: 2,5');

            return;
        }

        if ($this->movement === 'purchase' && ($this->cost === '' || ! Money::isValid($this->cost))) {
            $this->addError('cost', 'Informe o valor total pago. Ex.: 120,00');

            return;
        }

        $item = StockItem::findOrFail($this->movingId);
        $user = Auth::guard('web')->user();
        $quantity = Quantity::parse($this->quantity);

        if ($this->movement === 'purchase' && $this->createPayable && ! $user->can('finance.manage')) {
            $this->addError('createPayable', 'Você não tem permissão para lançar no financeiro.');

            return;
        }

        try {
            $movement = match ($this->movement) {
                'purchase' => $ledger->purchase($item, $quantity, Money::parse($this->cost), $user, $this->reason ?: null),
                'loss' => $ledger->loss($item, $quantity, $user, $this->reason),
                'count' => $ledger->count($item, $quantity, $user, $this->reason ?: null),
            };

            if ($this->movement === 'purchase' && $this->createPayable) {
                app(FinanceBook::class)->create([
                    'kind' => 'payable',
                    'description' => 'Compra: '.Quantity::format($quantity).' '.$item->unit->code.' de '.$item->name,
                    'counterparty' => $this->supplier,
                    'category_id' => FinancialCategory::where('kind', 'payable')->where('name', 'Fornecedores')->value('id'),
                    'amount' => Money::parse($this->cost),
                    'due_date' => $this->dueDate ?: now()->toDateString(),
                    'source_ref' => 'stock_purchase:'.$movement->id,
                ], $user);
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }

        $this->movingId = null;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'onlyLow');
    }

    public function render()
    {
        $items = StockItem::with('unit')
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'ilike', '%'.trim($this->search).'%'))
            ->when($this->onlyLow, fn ($q) => $q->belowMinimum())
            ->orderBy('name')
            ->get();

        return view('livewire.tenant.stock.index', [
            'items' => $items,
            'units' => Unit::orderBy('id')->get(),
            'moving' => $this->movingId ? StockItem::with('unit')->find($this->movingId) : null,
            'totalValue' => $items->reduce(fn (Money $sum, StockItem $i) => $sum->add($i->stockValue()), Money::zero()),
            'lowCount' => StockItem::belowMinimum()->count(),
        ]);
    }
}
