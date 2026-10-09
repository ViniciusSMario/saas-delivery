<form wire:submit="save" class="space-y-6">
    <div>
        <a href="{{ route('finance.index') }}" class="btn-link" wire:navigate>← Financeiro</a>
        <h1 class="page-title mt-1">{{ $entryId ? 'Editar lançamento' : ($kind === 'payable' ? 'Nova conta a pagar' : 'Nova conta a receber') }}</h1>
    </div>

    <section class="card card-body grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="description" class="label">Descrição</label>
            <input wire:model="description" id="description" type="text" class="input" placeholder="{{ $kind === 'payable' ? 'Ex.: Conta de luz de outubro' : 'Ex.: Festa de aniversário (sinal)' }}">
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="counterparty" class="label">{{ $kind === 'payable' ? 'Fornecedor / favorecido' : 'Cliente / pagador' }}</label>
            <input wire:model="counterparty" id="counterparty" type="text" class="input">
        </div>
        <div>
            <label for="categoryId" class="label">Categoria</label>
            <select wire:model="categoryId" id="categoryId" class="input">
                <option value="">Sem categoria</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @error('categoryId') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="amount" class="label">Valor (R$)</label>
            <input wire:model="amount" id="amount" type="text" inputmode="decimal" class="input" placeholder="0,00">
            @error('amount') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="dueDate" class="label">Vencimento</label>
            <input wire:model="dueDate" id="dueDate" type="date" class="input">
            @error('dueDate') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" class="checkbox" wire:model="monthly">
            Repetir todo mês (ao pagar, cria o do mês seguinte)
        </label>
        <div class="sm:col-span-2">
            <label for="notes" class="label">Observações</label>
            <textarea wire:model="notes" id="notes" rows="2" class="input"></textarea>
        </div>
    </section>

    <div class="flex justify-end"><button type="submit" class="btn-primary">Salvar</button></div>
</form>
