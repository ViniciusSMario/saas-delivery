@php
    use App\Livewire\Tenant\Onboarding\Wizard;
    $current = collect($steps)->firstWhere('key', $step);
    $index = collect($steps)->search(fn ($s) => $s['key'] === $step);
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Primeiros passos</h1>
            <p class="mt-1 text-sm text-stone-500">Em poucos minutos o seu cardápio fica pronto para receber pedidos. Dá para parar e continuar depois.</p>
        </div>
        <button type="button" wire:click="later" class="btn-ghost btn-sm">Continuar depois</button>
    </div>

    {{-- Progresso --}}
    <div>
        <div class="flex items-center justify-between text-xs font-semibold text-stone-500">
            <span>{{ $progress['done'] }} de {{ $progress['total'] }} etapas</span><span>{{ $progress['percent'] }}%</span>
        </div>
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-stone-200"><div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $progress['percent'] }}%"></div></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[15rem_1fr]">
        {{-- Etapas --}}
        <nav aria-label="Etapas" class="-mx-4 flex gap-2 overflow-x-auto px-4 scrollbar-none lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0">
            @foreach ($steps as $i => $item)
                <button type="button" wire:click="go('{{ $item['key'] }}')" wire:key="step-{{ $item['key'] }}"
                        @class([
                            'flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition',
                            'bg-white font-semibold shadow-(--shadow-card) ring-1 ring-brand-200' => $item['key'] === $step,
                            'text-stone-600 hover:bg-white/70' => $item['key'] !== $step,
                        ])>
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-emerald-600 text-white' => $item['done'],
                        'bg-stone-300 text-white' => ! $item['done'] && $item['skipped'],
                        'bg-brand-600 text-white' => ! $item['done'] && ! $item['skipped'] && $item['key'] === $step,
                        'bg-stone-200 text-stone-600' => ! $item['done'] && ! $item['skipped'] && $item['key'] !== $step,
                    ])>
                        @if ($item['done']) <x-icon name="check" class="size-4" stroke="3" /> @elseif ($item['skipped']) <x-icon name="minus" class="size-4" /> @else {{ $i + 1 }} @endif
                    </span>
                    <span class="whitespace-nowrap lg:whitespace-normal">{{ $item['title'] }}@if ($item['optional'])<span class="block text-xs font-normal text-stone-400">opcional</span>@endif</span>
                </button>
            @endforeach
        </nav>

        {{-- Etapa atual --}}
        <section class="card min-w-0">
            <div class="card-header">
                <div>
                    <p class="text-xs font-semibold text-brand-700 uppercase">Etapa {{ $index + 1 }} de {{ count($steps) }}</p>
                    <h2 class="text-lg font-bold">{{ $current['title'] }}</h2>
                </div>
                @if ($current['done'])<span class="badge-green"><x-icon name="check" class="size-3.5" /> Feito</span>@endif
            </div>

            <div class="card-body space-y-5">
                @switch($step)
                    {{-- ===== Loja ===== --}}
                    @case('store')
                        <p class="text-sm text-stone-600">É o que o cliente vê no topo do cardápio e no cupom impresso.</p>
                        <div class="grid gap-4 sm:grid-cols-6" data-cep-scope>
                            <div class="sm:col-span-4">
                                <label for="trade_name" class="label">Nome do restaurante</label>
                                <input wire:model="store.trade_name" id="trade_name" type="text" class="input">
                                @error('store.trade_name') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="phone" class="label">Telefone / WhatsApp</label>
                                <input wire:model="store.phone" id="phone" type="tel" data-mask="phone" class="input" placeholder="(11) 98888-7777">
                                @error('store.phone') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="zip_code" class="label">CEP</label>
                                <input wire:model="store.zip_code" id="zip_code" type="text" data-mask="cep" data-cep class="input" placeholder="00000-000">
                                <p data-cep-status hidden class="hint data-[tone=error]:text-amber-700"></p>
                                @error('store.zip_code') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-4">
                                <label for="street" class="label">Rua</label>
                                <input wire:model="store.street" id="street" type="text" data-cep-field="street" class="input">
                                @error('store.street') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="number" class="label">Número</label>
                                <input wire:model="store.number" id="number" type="text" data-cep-field="number" class="input">
                            </div>
                            <div class="sm:col-span-4">
                                <label for="district" class="label">Bairro</label>
                                <input wire:model="store.district" id="district" type="text" data-cep-field="district" class="input">
                            </div>
                            <div class="sm:col-span-4">
                                <label for="city" class="label">Cidade</label>
                                <input wire:model="store.city" id="city" type="text" data-cep-field="city" class="input">
                                @error('store.city') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="state" class="label">UF</label>
                                <input wire:model="store.state" id="state" type="text" maxlength="2" data-cep-field="state" class="input uppercase">
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 rounded-xl bg-stone-50 p-3">
                            <div class="flex size-16 items-center justify-center overflow-hidden rounded-xl border border-stone-200 bg-white">
                                @if ($logo && ! $errors->has('logo') && $logo->isPreviewable())
                                    <img src="{{ $logo->temporaryUrl() }}" alt="" class="size-full object-contain">
                                @elseif ($hasLogo)
                                    <img src="{{ route('establishment.logo') }}?v={{ now()->timestamp }}" alt="" class="size-full object-contain">
                                @else
                                    <x-icon name="store" class="size-6 text-stone-300" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="label mb-1">Logo <span class="font-normal text-stone-400">(opcional, quadrado, até 2 MB)</span></p>
                                <label class="inline-flex cursor-pointer items-center gap-3">
                                    <input wire:model="logo" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
                                    <span class="btn-secondary btn-sm pointer-events-none"><x-icon name="store" class="size-4" /> {{ $hasLogo || $logo ? 'Trocar logo' : 'Escolher logo' }}</span>
                                    <span wire:loading wire:target="logo" class="text-xs text-stone-500">Enviando...</span>
                                </label>
                                @error('logo') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end"><button type="button" wire:click="saveStore" wire:loading.attr="disabled" class="btn-primary">Salvar e continuar <x-icon name="chevron-right" class="size-4" /></button></div>
                        @break

                    {{-- ===== Horário ===== --}}
                    @case('hours')
                        <p class="text-sm text-stone-600">Fora do horário o cardápio continua no ar, mas não aceita pedidos.</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (Wizard::HOUR_PRESETS as $key => [$label])
                                <button type="button" wire:click="applyPreset('{{ $key }}')" class="chip">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div class="divide-y divide-stone-100 rounded-xl border border-stone-200">
                            @foreach ($weekdays as $weekday => $dayName)
                                <div wire:key="day-{{ $weekday }}" class="flex flex-wrap items-center gap-3 px-3 py-2.5">
                                    <span class="w-20 text-sm font-semibold">{{ $dayName }}</span>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="hours.{{ $weekday }}.is_closed"> Fechado</label>
                                    @unless ($hours[$weekday]['is_closed'])
                                        <input type="time" wire:model="hours.{{ $weekday }}.opens_at" class="input w-28" aria-label="Abre às">
                                        <span class="text-sm text-stone-400">às</span>
                                        <input type="time" wire:model="hours.{{ $weekday }}.closes_at" class="input w-28" aria-label="Fecha às">
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                        @error('hours') <p class="field-error">{{ $message }}</p> @enderror
                        @if ($errors->has('hours.*'))<p class="field-error">Confira os horários: abertura e fechamento no formato 18:00.</p>@endif
                        <p class="hint">Fecha depois da meia-noite? Use o horário de fechamento do dia seguinte, por exemplo 18:00 às 02:00.</p>
                        <div class="flex justify-end"><button type="button" wire:click="saveHours" wire:loading.attr="disabled" class="btn-primary">Salvar e continuar <x-icon name="chevron-right" class="size-4" /></button></div>
                        @break

                    {{-- ===== Entrega e pagamento ===== --}}
                    @case('sales')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label @class(['flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3', 'border-brand-600 bg-brand-50' => $acceptsDelivery, 'border-stone-200' => ! $acceptsDelivery])>
                                <input type="checkbox" wire:model.live="acceptsDelivery" class="checkbox mt-0.5">
                                <span><strong class="flex items-center gap-1.5"><x-icon name="truck" class="size-4" /> Entrega</strong><span class="text-sm text-stone-600">O cliente informa o endereço.</span></span>
                            </label>
                            <label @class(['flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3', 'border-brand-600 bg-brand-50' => $acceptsPickup, 'border-stone-200' => ! $acceptsPickup])>
                                <input type="checkbox" wire:model.live="acceptsPickup" class="checkbox mt-0.5">
                                <span><strong class="flex items-center gap-1.5"><x-icon name="bag" class="size-4" /> Retirada</strong><span class="text-sm text-stone-600">O cliente busca no balcão.</span></span>
                            </label>
                        </div>
                        @error('acceptsDelivery') <p class="field-error">{{ $message }}</p> @enderror

                        <div class="grid gap-4 sm:grid-cols-2">
                            @if ($acceptsDelivery)
                                <div>
                                    <label for="deliveryFee" class="label">Taxa de entrega (R$)</label>
                                    @if ($byZone)
                                        <p class="text-sm text-stone-600">Por bairro. <a href="{{ route('delivery.zones') }}" class="btn-link" wire:navigate>Editar bairros</a></p>
                                    @else
                                        <input wire:model="deliveryFee" id="deliveryFee" type="text" inputmode="decimal" class="input">
                                        <p class="hint">0 = grátis. Taxa diferente por bairro? <a href="{{ route('delivery.zones') }}" class="btn-link" wire:navigate>Cadastre os bairros</a>.</p>
                                    @endif
                                    @error('deliveryFee') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="etaDelivery" class="label">Tempo de entrega (min)</label>
                                    <input wire:model="etaDelivery" id="etaDelivery" type="number" min="0" max="240" class="input">
                                </div>
                            @endif
                            <div>
                                <label for="minOrder" class="label">Pedido mínimo (R$)</label>
                                <input wire:model="minOrder" id="minOrder" type="text" inputmode="decimal" class="input">
                                @error('minOrder') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            @if ($acceptsPickup)
                                <div>
                                    <label for="etaPickup" class="label">Tempo para retirada (min)</label>
                                    <input wire:model="etaPickup" id="etaPickup" type="number" min="0" max="240" class="input">
                                </div>
                            @endif
                        </div>

                        <fieldset>
                            <legend class="label">Formas de pagamento (na entrega ou no balcão)</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($paymentMethods as $method)
                                    <label @class(['chip cursor-pointer', 'chip-active' => $payments[$method->id] ?? false])>
                                        <input type="checkbox" wire:model.live="payments.{{ $method->id }}" class="sr-only"> {{ $method->name }}
                                    </label>
                                @endforeach
                            </div>
                            @error('payments') <p class="field-error">{{ $message }}</p> @enderror
                        </fieldset>
                        <div class="flex justify-end"><button type="button" wire:click="saveSales" wire:loading.attr="disabled" class="btn-primary">Salvar e continuar <x-icon name="chevron-right" class="size-4" /></button></div>
                        @break

                    {{-- ===== Cardápio ===== --}}
                    @case('menu')
                        @if (session('menu_template'))
                            <div class="alert-success"><x-icon name="check" class="mt-0.5 size-4" /> <span>Modelo {{ session('menu_template') }} aplicado. Ajuste os preços em Produtos quando quiser (ou desfaça em Importar planilha).</span></div>
                        @endif
                        <p class="text-sm text-stone-600">
                            @if ($products) Seu cardápio tem <strong>{{ $products }} {{ $products === 1 ? 'produto' : 'produtos' }}</strong>. Pode continuar ou acrescentar mais.
                            @else Escolha como começar. Tudo pode ser editado depois. @endif
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <a href="{{ route('menu.import') }}" wire:navigate class="flex items-start gap-3 rounded-xl border-2 border-stone-200 p-4 transition hover:border-brand-400">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="file" class="size-5" /></span>
                                <span><strong class="block">Importar planilha</strong><span class="text-sm text-stone-600">Já tem o cardápio no Excel? Envie e confira antes de gravar.</span></span>
                            </a>
                            <a href="{{ route('menu.products.create') }}" wire:navigate class="flex items-start gap-3 rounded-xl border-2 border-stone-200 p-4 transition hover:border-brand-400">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-600"><x-icon name="plus" class="size-5" /></span>
                                <span><strong class="block">Cadastrar à mão</strong><span class="text-sm text-stone-600">Um produto por vez, com fotos e adicionais.</span></span>
                            </a>
                        </div>

                        <div>
                            <p class="label">Ou comece por um modelo pronto</p>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($segments as $key => $segment)
                                    <div wire:key="segment-{{ $key }}" class="flex flex-col rounded-xl border border-stone-200 p-4">
                                        <p class="flex items-center gap-2 font-semibold"><x-icon :name="$segment['icon']" class="size-4 text-brand-600" /> {{ $segment['name'] }}</p>
                                        <p class="mt-1 text-xs text-stone-500">{{ $segment['text'] }}</p>
                                        <p class="mt-2 flex-1 text-xs text-stone-600">{{ implode(', ', $segment['preview']) }}...</p>
                                        <button type="button" wire:click="useTemplate('{{ $key }}')" wire:loading.attr="disabled" class="btn-secondary btn-sm mt-3"
                                                wire:confirm="Usar o modelo {{ $segment['name'] }}? Ele cria categorias, produtos e adicionais de exemplo com preços sugeridos, que você ajusta depois. Dá para desfazer em Produtos → Importar planilha."
                                                data-confirm-button="Usar modelo" data-confirm-tone="default">
                                            Usar este modelo
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @error('menu') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="flex justify-end"><button type="button" wire:click="confirmMenu" class="btn-primary" @disabled(! $products)>Continuar <x-icon name="chevron-right" class="size-4" /></button></div>
                        @break

                    {{-- ===== Mesas ===== --}}
                    @case('tables')
                        <p class="text-sm text-stone-600">Atende no salão? Cadastre as mesas para abrir comandas e imprimir o QR code de cada uma (o cliente pede pelo celular).</p>
                        @if ($tables)
                            <div class="alert-success"><x-icon name="check" class="mt-0.5 size-4" /> <span>{{ $tables }} {{ $tables === 1 ? 'mesa cadastrada' : 'mesas cadastradas' }}. <a href="{{ route('tables.qr') }}" class="font-semibold underline" wire:navigate>Imprimir QR codes</a></span></div>
                        @endif
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="tableCount" class="label">Quantas mesas?</label>
                                <input wire:model="tableCount" id="tableCount" type="number" min="1" max="100" class="input w-32">
                            </div>
                            <button type="button" wire:click="createTables" wire:loading.attr="disabled" class="btn-primary">Criar mesas 1 a {{ $tableCount ?: 'N' }}</button>
                        </div>
                        @error('tableCount') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" wire:click="skip('tables')" class="btn-ghost">Não atendo em mesas</button>
                            @if ($tables)<button type="button" wire:click="go('{{ $steps[$index + 1]['key'] ?? 'share' }}')" class="btn-primary">Continuar <x-icon name="chevron-right" class="size-4" /></button>@endif
                        </div>
                        @break

                    {{-- ===== Equipe ===== --}}
                    @case('team')
                        <p class="text-sm text-stone-600">Cada pessoa recebe um e-mail para criar a senha e vê só o que a função dela precisa. Garçom e entregador usam o modo app no celular.</p>
                        @if (session('invited'))
                            <div class="alert-success"><x-icon name="check" class="mt-0.5 size-4" /> <span>Convite enviado para {{ session('invited') }}.</span></div>
                        @endif
                        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_10rem_auto] sm:items-end">
                            <div>
                                <label for="inviteName" class="label">Nome</label>
                                <input wire:model="inviteName" id="inviteName" type="text" class="input">
                                @error('inviteName') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="inviteEmail" class="label">E-mail</label>
                                <input wire:model="inviteEmail" id="inviteEmail" type="email" class="input">
                                @error('inviteEmail') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="inviteRole" class="label">Função</label>
                                <select wire:model="inviteRole" id="inviteRole" class="input">
                                    @foreach (Wizard::INVITE_ROLES as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="invite" wire:loading.attr="disabled" class="btn-primary">Convidar</button>
                        </div>
                        <ul class="divide-y divide-stone-100 rounded-xl border border-stone-200 text-sm">
                            @foreach ($team as $member)
                                <li class="flex items-center justify-between gap-3 px-3 py-2"><span><strong>{{ $member->name }}</strong> <span class="text-stone-500">{{ $member->email }}</span></span><span class="badge-gray">{{ $member->roles->pluck('name')->implode(', ') }}</span></li>
                            @endforeach
                        </ul>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" wire:click="skip('team')" class="btn-ghost">Trabalho sozinho por enquanto</button>
                            @if ($team->count() > 1)<button type="button" wire:click="confirmTeam" class="btn-primary">Continuar <x-icon name="chevron-right" class="size-4" /></button>@endif
                        </div>
                        @break

                    {{-- ===== Pedido de teste ===== --}}
                    @case('test')
                        @if (! $testOrder)
                            <p class="text-sm text-stone-600">Vamos simular um pedido do cardápio online para você ver o caminho completo: o alarme no painel, o aceite, a impressão na cozinha e as etapas até ficar pronto.</p>
                            <ul class="space-y-1 text-sm text-stone-600">
                                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Funciona mesmo fora do horário de funcionamento.</li>
                                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> No fim ele é cancelado e não entra nas vendas.</li>
                            </ul>
                            @error('test') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="flex justify-end"><button type="button" wire:click="startTest" wire:loading.attr="disabled" class="btn-primary"><x-icon name="bell" class="size-4" /> Fazer pedido de teste</button></div>
                        @else
                            <div class="rounded-xl border-2 border-brand-200 bg-brand-50 p-4">
                                <p class="font-bold">Pedido #{{ $testOrder->number }} criado · {{ $testOrder->status->label() }}</p>
                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-stone-700">
                                    <li>Abra o <a href="{{ route('orders.board') }}" target="_blank" class="font-semibold underline">Painel de pedidos</a> em outra aba: o alarme toca e o pedido aparece em Recebido.</li>
                                    <li>Toque em "Aceitar e preparar" e depois em "Marcar pronto". Se tiver impressora configurada, o cupom sai na cozinha.</li>
                                    <li>Volte aqui e encerre o teste (não precisa concluir o pedido).</li>
                                </ol>
                            </div>
                            <div class="flex justify-end"><button type="button" wire:click="finishTest" wire:loading.attr="disabled" class="btn-primary">Encerrar teste e continuar <x-icon name="chevron-right" class="size-4" /></button></div>
                        @endif
                        @break

                    {{-- ===== Divulgar ===== --}}
                    @case('share')
                        <p class="text-sm text-stone-600">Este é o endereço do seu cardápio. Coloque na bio do Instagram, no status do WhatsApp e no Google Meu Negócio.</p>
                        <div class="grid gap-5 sm:grid-cols-[12rem_1fr] sm:items-center" x-data="{ copied: false }">
                            <div class="mx-auto size-48 rounded-xl border border-stone-200 bg-white p-2 [&_svg]:size-full">{!! $menuQr !!}</div>
                            <div class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="text" readonly value="{{ $menuUrl }}" class="input min-w-0 flex-1 text-sm" aria-label="Link do cardápio" onfocus="this.select()">
                                    <button type="button" class="btn-secondary shrink-0" @click="copyText(@js($menuUrl)).then((ok) => { copied = ok; $wire.shared() })">
                                        <x-icon name="copy" class="size-4" /> <span x-text="copied ? 'Copiado' : 'Copiar'">Copiar</span>
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="https://wa.me/?text={{ rawurlencode('Peça pelo nosso cardápio: '.$menuUrl) }}" target="_blank" rel="noopener" class="btn-success btn-sm" wire:click="shared"><x-icon name="message" class="size-4" /> Enviar no WhatsApp</a>
                                    <a href="data:image/svg+xml;base64,{{ base64_encode($menuQr) }}" download="qr-cardapio.svg" class="btn-secondary btn-sm" wire:click="shared"><x-icon name="download" class="size-4" /> Baixar QR code</a>
                                    <a href="{{ $menuUrl }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" /> Ver o cardápio</a>
                                </div>
                                <p class="hint">No celular, o cliente pode adicionar o cardápio à tela inicial e ele abre como um app.</p>
                            </div>
                        </div>
                        <div class="flex justify-end"><button type="button" wire:click="finish" class="btn-primary"><x-icon name="check" class="size-4" /> Concluir</button></div>
                        @break
                @endswitch
            </div>
        </section>
    </div>
</div>
