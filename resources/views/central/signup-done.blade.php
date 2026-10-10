<x-layouts.site title="Conta criada">
    <section class="bg-gradient-to-b from-brand-50 to-white">
        <div class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-icon name="check" class="size-8" stroke="2.4" /></span>
            <h1 class="mt-5 text-3xl font-extrabold tracking-tight">Tudo pronto, {{ $tenant->name }}!</h1>
            <p class="mt-3 text-stone-600">
                Seu teste grátis vai até <strong>{{ $tenant->trial_ends_at?->timezone('America/Sao_Paulo')->format('d/m/Y') }}</strong>.
                Enviamos um e-mail de boas-vindas com os links abaixo.
            </p>

            <div class="card mt-8 space-y-4 p-6 text-left">
                <div>
                    <p class="stat-label">Seu painel</p>
                    <a href="{{ $loginUrl }}" class="mt-1 block truncate font-semibold text-brand-700 hover:underline">{{ $loginUrl }}</a>
                </div>
                <div>
                    <p class="stat-label">Seu cardápio online</p>
                    <a href="{{ $menuUrl }}" target="_blank" class="mt-1 block truncate font-semibold text-brand-700 hover:underline">{{ $menuUrl }}</a>
                </div>
                <a href="{{ $loginUrl }}" class="btn-primary btn-lg w-full">Entrar no painel <x-icon name="chevron-right" class="size-5" /></a>
            </div>

            <p class="mt-6 text-sm text-stone-500">Dica: comece cadastrando as categorias e os produtos do cardápio.</p>
        </div>
    </section>
</x-layouts.site>
