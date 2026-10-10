@php
    use App\Modules\Customers\Models\Customer;
    use App\Modules\Messaging\OrderMessages;
    use App\Modules\Messaging\WhatsAppSettings;
@endphp
<div class="space-y-6">
    <x-ui.page-header title="WhatsApp" subtitle="Avise o cliente quando o pedido é recebido, sai para entrega, fica pronto ou é cancelado." />

    <form wire:submit="save" class="space-y-6">
        <section class="card card-body space-y-5">
            <label class="flex items-center justify-between gap-4">
                <span>
                    <span class="block font-semibold">Avisos pelo WhatsApp</span>
                    <span class="text-sm text-stone-500">Liga os avisos para os pedidos com telefone (online, balcão e entrega).</span>
                </span>
                <input type="checkbox" wire:model.live="enabled" class="checkbox size-5">
            </label>

            <div>
                <p class="label">Como enviar</p>
                <div class="grid gap-3 lg:grid-cols-3">
                    @foreach (WhatsAppSettings::PROVIDERS as $value => $label)
                        <label @class(['flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-4', 'border-brand-600 bg-brand-50' => $provider === $value, 'border-stone-200' => $provider !== $value])>
                            <input type="radio" wire:model.live="provider" value="{{ $value }}" class="mt-1 accent-brand-600">
                            <span>
                                <span class="block text-sm font-semibold">{{ $label }}</span>
                                <span class="text-xs text-stone-500">
                                    @switch($value)
                                        @case('manual') Grátis. No pedido aparece o botão com a mensagem pronta; a equipe só toca em enviar. @break
                                        @case('evolution') Automático com o seu número, conectado por QR code. Precisa de um servidor da Evolution API. @break
                                        @default Automático e oficial. Exige conta Meta Business e um modelo de mensagem aprovado. @break
                                    @endswitch
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('provider') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @if ($provider === 'evolution')
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label for="evolutionUrl" class="label">Endereço da Evolution API</label>
                        <input wire:model="evolutionUrl" id="evolutionUrl" type="url" class="input" placeholder="https://evolution.seudominio.com.br">
                        @error('evolutionUrl') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="evolutionInstance" class="label">Instância</label>
                        <input wire:model="evolutionInstance" id="evolutionInstance" type="text" class="input" placeholder="pizzaria">
                        @error('evolutionInstance') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="evolutionKey" class="label">Chave (apikey)</label>
                        <input wire:model="evolutionKey" id="evolutionKey" type="password" class="input" autocomplete="off" placeholder="{{ $hasEvolutionKey ? '•••••••• (salva; deixe em branco para manter)' : '' }}">
                    </div>
                </div>
            @elseif ($provider === 'cloud')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cloudPhoneId" class="label">ID do número de telefone</label>
                        <input wire:model="cloudPhoneId" id="cloudPhoneId" type="text" class="input" placeholder="123456789012345">
                        @error('cloudPhoneId') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cloudToken" class="label">Token de acesso</label>
                        <input wire:model="cloudToken" id="cloudToken" type="password" class="input" autocomplete="off" placeholder="{{ $hasCloudToken ? '•••••••• (salvo; deixe em branco para manter)' : '' }}">
                    </div>
                    <div>
                        <label for="cloudTemplate" class="label">Nome do modelo aprovado</label>
                        <input wire:model="cloudTemplate" id="cloudTemplate" type="text" class="input" placeholder="atualizacao_pedido">
                        <p class="hint">Modelo com uma variável no corpo, ex.: “Atualização do seu pedido: @{{1}}”.</p>
                        @error('cloudTemplate') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cloudLanguage" class="label">Idioma do modelo</label>
                        <input wire:model="cloudLanguage" id="cloudLanguage" type="text" class="input" placeholder="pt_BR">
                        @error('cloudLanguage') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </section>

        <section class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="section-title">Avisos</h2>
                    <p class="text-xs text-stone-500">Use {{ implode(', ', array_keys(OrderMessages::PLACEHOLDERS)) }}.</p>
                </div>
            </div>
            <div class="divide-y divide-stone-100">
                @foreach (WhatsAppSettings::EVENTS as $event => [$label])
                    <div class="grid gap-3 p-5 lg:grid-cols-[14rem_1fr]" wire:key="ev-{{ $event }}">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model="events.{{ $event }}" class="checkbox mt-0.5">
                            <span class="font-semibold">{{ $label }}</span>
                        </label>
                        <div>
                            <textarea wire:model="templates.{{ $event }}" rows="2" class="input" maxlength="600"></textarea>
                            @error("templates.$event") <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-stone-100 bg-stone-50/60 px-5 py-4">
                <button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button>
            </div>
        </section>
    </form>

    @if ($provider !== 'manual')
        <section class="card card-body">
            <h2 class="section-title">Testar o envio</h2>
            <form wire:submit="sendTest" class="mt-3 flex flex-wrap items-start gap-2">
                <div class="min-w-60 flex-1">
                    <input wire:model="testPhone" type="tel" class="input" placeholder="Seu celular com DDD" aria-label="Celular para o teste">
                    @error('testPhone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-secondary" wire:loading.attr="disabled"><x-icon name="phone" class="size-4" /> Enviar teste</button>
            </form>
        </section>
    @endif

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="section-title">Últimos avisos</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Quando</th><th>Pedido</th><th>Aviso</th><th>Telefone</th><th>Situação</th></tr></thead>
                <tbody>
                    @forelse ($recent as $message)
                        <tr>
                            <td class="whitespace-nowrap text-stone-500">{{ $message->created_at->timezone('America/Sao_Paulo')->format('d/m H:i') }}</td>
                            <td>{{ $message->order ? '#'.$message->order->number : '—' }}</td>
                            <td>{{ WhatsAppSettings::EVENTS[$message->event][0] ?? 'Teste' }}</td>
                            <td class="text-stone-600">{{ Customer::formatPhone($message->phone) }}</td>
                            <td>
                                @switch($message->status)
                                    @case('sent') <span class="badge-green">Enviado</span> @break
                                    @case('failed') <span class="badge-red" title="{{ $message->error }}">Falhou</span> @break
                                    @default <span class="badge-amber">Na fila</span>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="phone" title="Nenhum aviso enviado ainda" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
