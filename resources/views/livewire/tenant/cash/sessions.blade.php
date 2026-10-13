@php($tz = \App\Modules\Establishment\Models\Setting::read('timezone', 'America/Sao_Paulo'))
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="page-title">Caixas</h1>
        <a href="{{ route('cash.current') }}" class="btn-secondary" wire:navigate>Meu caixa</a>
    </div>

    <section class="card card-body">
        <h2 class="section-title">Abertos agora</h2>
        <ul class="mt-3 divide-y divide-stone-100 text-sm">
            @forelse ($open as $session)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="open-{{ $session->id }}">
                    <span>{{ $session->register->name }} · {{ $session->operator->name }} · desde {{ $session->opened_at->timezone($tz)->format('d/m H:i') }}</span>
                    <button type="button" wire:click="forceClose({{ $session->id }})" wire:confirm="Fechar o caixa de {{ $session->operator->name }} sem conferência?" class="btn-link text-red-700">Fechar pela gerência</button>
                </li>
            @empty
                <li class="py-2 text-stone-500">Nenhum caixa aberto.</li>
            @endforelse
        </ul>
    </section>

    <section class="card overflow-x-auto">
        <div class="card-header"><h2 class="section-title">Fechados</h2></div>
        <table class="table table-cards">
            <thead><tr><th>Caixa</th><th>Operador</th><th>Período</th><th class="text-right">Diferença</th><th></th></tr></thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr wire:key="s-{{ $session->id }}">
                        <td>{{ $session->register->name }}</td>
                        <td>{{ $session->operator->name }}</td>
                        <td class="text-stone-500">{{ $session->opened_at->timezone($tz)->format('d/m H:i') }} – {{ $session->closed_at?->timezone($tz)->format('H:i') }}</td>
                        <td @class(['text-right font-medium', 'text-red-700' => $session->difference_cents?->cents < 0, 'text-green-700' => $session->difference_cents?->cents > 0])>{{ ($session->difference_cents ?? $zero)->format() }}</td>
                        <td class="text-right"><a href="{{ route('cash.report', $session) }}" class="btn-link" wire:navigate>Relatório</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty icon="wallet" title="Nenhum caixa fechado ainda" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $sessions->links() }}

    <section class="card card-body">
        <h2 class="section-title">Caixas cadastrados</h2>
        <p class="mt-1 text-sm text-stone-600">{{ $registers->pluck('name')->implode(', ') }}</p>
        <form wire:submit="addRegister" class="mt-3 flex flex-wrap gap-2">
            <input wire:model="newRegister" type="text" class="input max-w-xs" placeholder="Ex.: Caixa 2" aria-label="Nome do caixa">
            <button type="submit" class="btn-secondary">Adicionar caixa</button>
        </form>
        @error('newRegister') <p class="field-error">{{ $message }}</p> @enderror
    </section>
</div>
