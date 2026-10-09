<div class="space-y-6">
    <div>
        <a href="{{ route('finance.index') }}" class="btn-link" wire:navigate>← Financeiro</a>
        <h1 class="page-title mt-1">Contas financeiras</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-body">
            <h2 class="font-semibold">Contas</h2>
            <ul class="mt-2 divide-y divide-stone-100 text-sm">
                @foreach ($accounts as $account)
                    <li class="flex justify-between py-2" wire:key="a-{{ $account->id }}">
                        <span>{{ $account->name }} <span class="text-xs text-stone-500">· {{ $types[$account->type] }}</span></span>
                        <span class="font-medium">{{ $account->balance()->format() }}</span>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="create" class="mt-4 grid gap-2 border-t border-stone-100 pt-4 sm:grid-cols-[1fr_10rem_7rem_auto] sm:items-end">
                <div>
                    <label for="name" class="label">Nova conta</label>
                    <input wire:model="name" id="name" type="text" class="input" placeholder="Ex.: Banco Inter">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="type" class="label">Tipo</label>
                    <select wire:model="type" id="type" class="input">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="openingBalance" class="label">Saldo inicial</label>
                    <input wire:model="openingBalance" id="openingBalance" type="text" inputmode="decimal" class="input">
                    @error('openingBalance') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-secondary">Adicionar</button>
            </form>
        </section>

        <form wire:submit="saveMapping" class="card card-body space-y-3">
            <h2 class="font-semibold">Para onde vai cada forma de pagamento</h2>
            <p class="hint">No fechamento do caixa, o recebido de cada forma vira um lançamento já baixado nesta conta.</p>
            @foreach ($methods as $method)
                <div class="flex items-center justify-between gap-3" wire:key="m-{{ $method->id }}">
                    <span class="text-sm">{{ $method->name }}</span>
                    <select wire:model="mapping.{{ $method->id }}" class="input w-56" aria-label="Conta de {{ $method->name }}">
                        <option value="">Não lançar</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <button type="submit" class="btn-primary">Salvar</button>
        </form>
    </div>
</div>
