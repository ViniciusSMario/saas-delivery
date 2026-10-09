<form wire:submit="save" class="space-y-6">
    <div>
        <a href="{{ route('platform.tenants.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-500 hover:text-stone-800" wire:navigate><x-icon name="chevron-left" class="size-4" /> Estabelecimentos</a>
        <h1 class="page-title mt-1">Novo estabelecimento</h1>
    </div>

    <section class="card card-body space-y-4">
        <h2 class="section-title">Estabelecimento</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model.live.debounce.400ms="name" id="name" type="text" class="input" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="slug" class="label">Endereço</label>
                <div class="flex items-center gap-1">
                    <input wire:model.blur="slug" id="slug" type="text" class="input" required>
                    <span class="text-sm whitespace-nowrap text-stone-500">.{{ $baseDomain }}</span>
                </div>
                <p class="hint">Vira o subdomínio. O nome interno do banco é gerado pelo sistema.</p>
                @error('slug') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="document" class="label">CNPJ</label>
                <input wire:model="document" id="document" type="text" class="input">
                @error('document') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="plan_id" class="label">Plano</label>
                <select wire:model="plan_id" id="plan_id" class="input">
                    <option value="">Sem plano</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }} · R$ {{ number_format($plan->price_cents / 100, 2, ',', '.') }}</option>
                    @endforeach
                </select>
                @error('plan_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="card card-body space-y-4">
        <h2 class="section-title">Dono</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="owner_name" class="label">Nome</label>
                <input wire:model="owner_name" id="owner_name" type="text" class="input" required>
                @error('owner_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="owner_email" class="label">E-mail</label>
                <input wire:model="owner_email" id="owner_email" type="email" class="input" required>
                @error('owner_email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="hint">O dono recebe um e-mail com um link para definir a senha.</p>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Criar estabelecimento</span>
            <span wire:loading wire:target="save">Criando…</span>
        </button>
    </div>
</form>
