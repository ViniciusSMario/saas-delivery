@php
    $features = [
        ['utensils', 'Cardápio online que parece app', 'Seu cliente pede pelo celular, com fotos, adicionais e acompanhamento do pedido em tempo real.'],
        ['flame', 'Painel da cozinha', 'Pedidos chegam com aviso sonoro, organizados por etapa e com o tempo de espera de cada um.'],
        ['table', 'Mesas e comandas', 'Abra mesas, lance itens, divida a conta e feche com taxa de serviço em poucos toques.'],
        ['pos', 'PDV e caixa', 'Venda no balcão com busca rápida, abertura e fechamento de caixa com conferência.'],
        ['box', 'Estoque e ficha técnica', 'Baixa automática dos insumos a cada venda, custo e margem de cada produto.'],
        ['trending', 'Financeiro e relatórios', 'Contas a pagar e receber, vendas por dia, canal e produto, com exportação.'],
    ];
@endphp
<x-layouts.site>
    {{-- Destaque --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-50 via-white to-white"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="badge-brand">14 dias grátis, sem cartão</span>
                <h1 class="mt-4 text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl">
                    O sistema completo do seu <span class="text-brand-600">restaurante</span>.
                </h1>
                <p class="mt-5 max-w-lg text-lg text-stone-600">
                    Cardápio online, pedidos, cozinha, mesas, PDV, caixa, estoque e financeiro num só lugar. Funciona no computador, no tablet e no celular.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('signup.create') }}" class="btn-primary btn-lg">Começar teste grátis <x-icon name="chevron-right" class="size-5" /></a>
                    <a href="#planos" class="btn-secondary btn-lg">Ver planos</a>
                </div>
                <p class="mt-4 text-sm text-stone-500">Pronto em 1 minuto. Cancele quando quiser.</p>
            </div>

            {{-- Ilustração: celular com o cardápio --}}
            <div class="relative mx-auto w-full max-w-sm">
                <div class="absolute -inset-6 rotate-3 rounded-[2.5rem] bg-brand-100/60"></div>
                <div class="relative rounded-[2.2rem] border-8 border-stone-900 bg-stone-50 shadow-2xl">
                    <div class="h-28 rounded-t-[1.6rem] bg-gradient-to-br from-brand-500 to-brand-700"></div>
                    <div class="-mt-10 space-y-3 px-4 pb-6">
                        <div class="card p-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-12 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">P</span>
                                <div><p class="font-bold">Pizzaria do Bairro</p><p class="text-xs text-emerald-700">● Aberto agora</p></div>
                            </div>
                        </div>
                        @foreach ([['Margherita', 'R$ 49,90'], ['Calabresa', 'R$ 52,90'], ['Quatro queijos', 'R$ 58,90']] as [$item, $price])
                            <div class="card flex items-center justify-between p-3">
                                <div><p class="text-sm font-semibold">{{ $item }}</p><p class="text-xs font-bold text-stone-700">{{ $price }}</p></div>
                                <span class="flex size-7 items-center justify-center rounded-full bg-white text-brand-600 shadow ring-1 ring-stone-200"><x-icon name="plus" class="size-4" stroke="2.4" /></span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between rounded-2xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white">
                            <span>Ver carrinho</span><span>R$ 102,80</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Recursos --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-center text-3xl font-extrabold tracking-tight">Tudo o que a operação precisa</h2>
        <p class="mx-auto mt-3 max-w-xl text-center text-stone-600">Do pedido no celular do cliente até o fechamento do caixa.</p>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$icon, $title, $text])
                <div class="card p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5" /></span>
                    <h3 class="mt-4 font-bold">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm text-stone-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Planos --}}
    <section id="planos" class="scroll-mt-16 bg-stone-50 py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <h2 class="text-center text-3xl font-extrabold tracking-tight">Planos</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-stone-600">Todos com 14 dias grátis. Pague por Pix, boleto ou cartão.</p>
            @include('central.partials.plans', ['plans' => $plans])
        </div>
    </section>
</x-layouts.site>
