{{-- Minha conta: instalar o app no celular e ligar as notificações deste aparelho. --}}
@php
    $user = auth()->user();
    $receives = \App\Modules\Push\StaffAlerts::receivesAlerts($user);
    $choices = \App\Modules\Push\StaffAlerts::choicesFor($user);
    $alerts = $user->usesFieldMode()
        ? array_filter([
            $user->can('tables.manage') ? 'quando um prato da mesa ficar pronto' : null,
            $user->can('deliveries.operate') ? 'quando uma entrega ficar pronta ou for mandada para você' : null,
        ])
        : ['quando chegar um pedido pelo cardápio online'];
@endphp
<section class="card card-body space-y-4" x-data="appDevice" x-cloak>
    <div>
        <h2 class="section-title flex items-center gap-2"><x-icon name="phone" class="size-5 text-brand-600" /> App no celular</h2>
        <p class="mt-1 text-sm text-stone-500">Instale o painel como um app: abre direto pelo ícone do restaurante, em tela cheia.</p>
    </div>

    {{-- Instalação --}}
    <div class="rounded-2xl border border-stone-200 p-4">
        <template x-if="installed">
            <p class="flex items-center gap-2 font-semibold text-emerald-700"><x-icon name="check" class="size-5" stroke="2.4" /> App instalado neste aparelho.</p>
        </template>
        <template x-if="! installed && canInstall">
            <button type="button" @click="install()" class="btn-primary btn-xl sm:w-auto"><x-icon name="download" class="size-5" /> Instalar app</button>
        </template>
        <template x-if="! installed && ! canInstall && isIos">
            <div class="space-y-2 text-sm">
                <p class="font-semibold">Para instalar no iPhone (pelo Safari):</p>
                <ol class="space-y-2">
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-stone-900 text-xs font-bold text-white">1</span> <span>Toque em <strong>Compartilhar</strong> <x-icon name="share" class="inline size-4 align-text-bottom" /> na barra do Safari.</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-stone-900 text-xs font-bold text-white">2</span> <span>Escolha <strong>Adicionar à Tela de Início</strong>.</span></li>
                    <li class="flex items-center gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-stone-900 text-xs font-bold text-white">3</span> <span>Abra pelo ícone novo e entre com seu e-mail e senha.</span></li>
                </ol>
            </div>
        </template>
        <template x-if="! installed && ! canInstall && ! isIos">
            <p class="text-sm text-stone-600">No Android, abra este endereço no <strong>Chrome</strong> e toque em <strong>Instalar app</strong> (ou "Adicionar à tela inicial") no menu <strong>⋮</strong>. No computador, use o ícone de instalar na barra de endereço.</p>
        </template>
    </div>

    @if ($receives)
        {{-- Notificações --}}
        <div class="space-y-3 rounded-2xl border border-stone-200 p-4">
            <div>
                <p class="flex items-center gap-2 font-semibold"><x-icon name="bell" class="size-5 text-brand-600" /> Notificações</p>
                <p class="mt-1 text-sm text-stone-500">Avisa no celular {{ implode(' e ', $alerts) }}, mesmo com a tela bloqueada.</p>
            </div>

            <template x-if="needsInstallForPush">
                <p class="alert-warning"><x-icon name="info" class="mt-0.5 size-4" /> No iPhone, as notificações só funcionam pelo app instalado: instale (acima), abra pelo ícone e ative por lá.</p>
            </template>
            <template x-if="! needsInstallForPush && ! pushSupported">
                <p class="text-sm text-stone-600">Este navegador não recebe notificações. Use o Chrome (Android) ou o app instalado (iPhone).</p>
            </template>
            <template x-if="pushSupported && permission === 'denied'">
                <p class="alert-error"><x-icon name="alert" class="mt-0.5 size-4" /> Notificações bloqueadas neste aparelho. Libere nas configurações do site (Android: cadeado na barra de endereço) ou em Ajustes → Notificações (iPhone) e volte aqui.</p>
            </template>
            <template x-if="pushSupported && permission !== 'denied' && subscribed">
                <div class="space-y-3">
                    <p class="flex items-center gap-2 font-semibold text-emerald-700"><x-icon name="check" class="size-5" stroke="2.4" /> Ativas neste celular.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="test()" :disabled="busy" class="btn-secondary"><x-icon name="bell" class="size-4" /> Enviar teste</button>
                        <button type="button" @click="disable()" :disabled="busy" class="btn-ghost text-stone-500">Desativar neste celular</button>
                    </div>
                </div>
            </template>
            <template x-if="pushSupported && permission !== 'denied' && ! subscribed">
                <button type="button" @click="enable()" :disabled="busy" class="btn-primary btn-xl sm:w-auto"><x-icon name="bell" class="size-5" /> Ativar notificações</button>
            </template>

            <p x-show="message" x-text="message" class="text-sm font-medium text-emerald-700"></p>
            <p x-show="error" x-text="error" class="field-error"></p>

            @if ($choices)
                {{-- Vale para todos os aparelhos da pessoa; salva ao marcar. --}}
                <fieldset class="space-y-2 border-t border-stone-100 pt-3" x-data="{ saved: false }" @alerts-saved.window="saved = true; setTimeout(() => saved = false, 2000)">
                    <legend class="flex w-full items-center justify-between text-sm font-semibold">
                        Quero ser avisado de
                        <span x-show="saved" x-transition.opacity class="text-xs font-medium text-emerald-700">Salvo</span>
                    </legend>
                    @foreach ($choices as $type => $label)
                        <label class="flex items-center gap-2 text-sm" wire:key="alert-{{ $type }}">
                            <input type="checkbox" wire:model.live="alerts.{{ $type }}" class="checkbox"> {{ $label }}
                        </label>
                    @endforeach
                </fieldset>
            @endif
        </div>
    @endif
</section>
