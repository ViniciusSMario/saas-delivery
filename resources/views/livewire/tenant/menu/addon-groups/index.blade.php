<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Adicionais e opções</h1>
            <p class="mt-1 text-sm text-stone-500">Um grupo pode ser usado em vários produtos (ex.: a mesma "Borda" em todas as pizzas).</p>
        </div>
        <a href="{{ route('menu.addon-groups.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo grupo</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @forelse ($groups as $group)
            <div wire:key="group-{{ $group->id }}" class="card card-body">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="section-title">
                            {{ $group->name }}
                            @unless ($group->is_active)<span class="badge ml-1 bg-stone-200 text-stone-600">inativo</span>@endunless
                        </h2>
                        <p class="text-xs text-stone-500">{{ $group->rule() }}{{ $group->isRequired() ? ' · obrigatório' : '' }} · usado em {{ $group->products_count }} produto(s)</p>
                    </div>
                    <div class="whitespace-nowrap">
                        <a href="{{ route('menu.addon-groups.edit', $group) }}" class="btn-link" wire:navigate>Editar</a>
                        <button type="button" wire:click="delete({{ $group->id }})" wire:confirm="Excluir o grupo {{ $group->name }}? Ele sai de {{ $group->products_count }} produto(s)." class="btn-link ml-3 text-red-700">Excluir</button>
                    </div>
                </div>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($group->addons as $addon)
                        <li @class(['flex justify-between', 'text-stone-400 line-through' => ! $addon->is_active])>
                            <span>{{ $addon->name }}</span>
                            <span>{{ $addon->price_cents->isZero() ? 'sem custo' : '+ '.$addon->price_cents->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="card sm:col-span-2">
                <x-ui.empty icon="plus-circle" title="Nenhum grupo de adicionais ainda" text="Exemplos: “Escolha a borda” (obrigatório, 1 opção) ou “Adicionais” (opcional, até 5). Depois é só ligar o grupo aos produtos.">
                    <a href="{{ route('menu.addon-groups.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> Novo grupo</a>
                    <a href="{{ route('menu.import') }}" class="btn-secondary" wire:navigate><x-icon name="file" class="size-4" /> Importar planilha</a>
                </x-ui.empty>
            </div>
        @endforelse
    </div>
</div>
