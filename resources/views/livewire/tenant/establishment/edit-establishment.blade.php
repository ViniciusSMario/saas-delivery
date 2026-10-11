@php
    $fields = [
        ['trade_name', 'Nome fantasia', 'sm:col-span-2'],
        ['legal_name', 'Razão social', 'sm:col-span-2'],
        ['document', 'CNPJ', ''],
        ['phone', 'Telefone', ''],
        ['email', 'E-mail de contato', 'sm:col-span-2'],
    ];
    $address = [
        ['zip_code', 'CEP', ''],
        ['street', 'Rua', 'sm:col-span-3'],
        ['number', 'Número', ''],
        ['complement', 'Complemento', 'sm:col-span-3'],
        ['district', 'Bairro', 'sm:col-span-2'],
        ['city', 'Cidade', ''],
        ['state', 'UF', ''],
    ];
@endphp

<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Estabelecimento</h1>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Salvar alterações</button>
    </div>

    <section class="card card-body">
        <h2 class="section-title">Dados cadastrais</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            @foreach ($fields as [$key, $label, $span])
                <div class="{{ $span }}">
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    <input wire:model="form.{{ $key }}" id="{{ $key }}" type="text" class="input">
                    @error("form.$key") <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Endereço</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            @foreach ($address as [$key, $label, $span])
                <div class="{{ $span }}">
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    <input wire:model="form.{{ $key }}" id="{{ $key }}" type="text" class="input" @if ($key === 'state') maxlength="2" @endif>
                    @error("form.$key") <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Logo</h2>
        <div class="mt-4 flex flex-wrap items-center gap-6">
            <div class="flex size-24 items-center justify-center overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
                @if ($logo && ! $errors->has('logo') && $logo->isPreviewable())
                    <img src="{{ $logo->temporaryUrl() }}" alt="Pré-visualização do logo" class="size-full object-contain">
                @elseif ($hasLogo)
                    <img src="{{ route('establishment.logo') }}?v={{ now()->timestamp }}" alt="Logo atual" class="size-full object-contain">
                @else
                    <span class="text-xs text-stone-400">Sem logo</span>
                @endif
            </div>
            <div class="space-y-2">
                <input wire:model="logo" type="file" accept="image/png,image/jpeg,image/webp" class="text-sm">
                <p class="hint">PNG, JPG ou WEBP, até 2 MB.</p>
                @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                @if ($hasLogo)
                    <button type="button" wire:click="removeLogo" wire:confirm="Remover o logo?" class="btn-link text-red-700">Remover logo</button>
                @endif
            </div>
        </div>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Foto de capa do cardápio</h2>
        <p class="hint">Aparece no topo do cardápio online. Use uma foto horizontal bonita da casa ou do prato mais famoso.</p>
        <div class="mt-4 space-y-3">
            <div class="flex aspect-[3/1] w-full max-w-xl items-center justify-center overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
                @if ($cover && ! $errors->has('cover') && $cover->isPreviewable())
                    <img src="{{ $cover->temporaryUrl() }}" alt="Pré-visualização da capa" class="size-full object-cover">
                @elseif ($hasCover)
                    <img src="{{ route('establishment.cover') }}?v={{ now()->timestamp }}" alt="Capa atual" class="size-full object-cover">
                @else
                    <span class="text-xs text-stone-400">Sem capa (o cardápio usa a cor da marca)</span>
                @endif
            </div>
            <input wire:model="cover" type="file" accept="image/png,image/jpeg,image/webp" class="text-sm">
            <p class="hint">PNG, JPG ou WEBP, até 4 MB. Ideal: 1200 × 400 px ou maior.</p>
            @error('cover') <p class="field-error">{{ $message }}</p> @enderror
            @if ($hasCover)
                <button type="button" wire:click="removeCover" wire:confirm="Remover a foto de capa?" class="btn-link text-red-700">Remover capa</button>
            @endif
        </div>
    </section>

    <section class="card card-body">
        <h2 class="section-title">Horário de funcionamento</h2>
        <div class="mt-4 divide-y divide-stone-100">
            @foreach ($weekdays as $day => $label)
                <div class="flex flex-wrap items-center gap-3 py-2" wire:key="day-{{ $day }}">
                    <span class="w-24 text-sm font-medium">{{ $label }}</span>
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" wire:model.live="hours.{{ $day }}.is_closed" class="checkbox"> Fechado
                    </label>
                    @unless ($hours[$day]['is_closed'] ?? false)
                        <input type="time" wire:model="hours.{{ $day }}.opens_at" class="input w-32" aria-label="Abertura de {{ $label }}">
                        <span class="text-sm text-stone-500">às</span>
                        <input type="time" wire:model="hours.{{ $day }}.closes_at" class="input w-32" aria-label="Fechamento de {{ $label }}">
                        <span class="hint">Fechamento antes da abertura = vira a madrugada.</span>
                    @endunless
                    @error("hours.$day.opens_at") <p class="field-error w-full">{{ $message }}</p> @enderror
                    @error("hours.$day.closes_at") <p class="field-error w-full">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Salvar alterações</button>
    </div>
</form>
