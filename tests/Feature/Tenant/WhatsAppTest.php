<?php

use App\Livewire\Tenant\Messaging\Settings;
use App\Modules\Establishment\Models\OpeningHour;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Messaging\Models\WhatsAppMessage;
use App\Modules\Messaging\OrderMessages;
use App\Modules\Messaging\Providers\SafeUrl;
use App\Modules\Messaging\WhatsApp;
use App\Modules\Messaging\WhatsAppSettings;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'America/Sao_Paulo'));
    $this->tenant = provisionTenant('wpp');
    inTenant($this->tenant, fn () => OpeningHour::query()->update(['is_closed' => false, 'opens_at' => '00:00', 'closes_at' => '23:59']));
    $this->menu = orderMenu($this->tenant);
    // Sem DNS nos testes: o host do provedor "resolve" para um IP público.
    SafeUrl::$resolver = fn (string $host) => $host === 'interno.example.com' ? ['10.0.0.5'] : ['93.184.216.34'];
});

afterEach(fn () => SafeUrl::$resolver = null);

function useEvolution(): void
{
    Setting::write('whatsapp_enabled', true);
    Setting::write('whatsapp_provider', 'evolution');
    WhatsAppSettings::saveCredentials(['evolution_url' => 'https://evo.example.com', 'evolution_instance' => 'pizzaria', 'evolution_key' => 'chave-secreta']);
}

it('não manda nada com o WhatsApp desligado', function () {
    Http::fake();
    placeOnline($this->tenant, onlineOrderInput($this->menu));

    expect(inTenant($this->tenant, fn () => WhatsAppMessage::count()))->toBe(0);
    Http::assertNothingSent();
});

it('avisa o cliente pela Evolution API em cada momento ligado, uma vez só', function () {
    Http::fake(['evo.example.com/*' => Http::response(['key' => ['id' => 'MSG1']])]);
    inTenant($this->tenant, fn () => useEvolution());

    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    Http::assertSent(fn (Request $r) => $r->url() === 'https://evo.example.com/message/sendText/pizzaria'
        && $r->hasHeader('apikey', 'chave-secreta')
        && $r['number'] === '5511988887777'
        && str_contains($r['text'], 'Olá, Ana! Recebemos seu pedido #'.$order->number)
        && str_contains($r['text'], '/cardapio/pedido/'.$order->public_token));

    inTenant($this->tenant, function () use ($order) {
        $message = WhatsAppMessage::sole();
        expect($message->status)->toBe('sent')->and($message->provider_message_id)->toBe('MSG1');

        // "Em preparo" vem desligado; pronto para retirada e cancelamento, ligados.
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Preparing);
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Cancelled, null, 'Faltou ingrediente');

        expect(WhatsAppMessage::pluck('event')->all())->toBe(['received', 'ready_pickup', 'cancelled'])
            ->and(WhatsAppMessage::where('event', 'cancelled')->value('body'))->toContain('Motivo: Faltou ingrediente');

        // O mesmo aviso não sai duas vezes.
        app(WhatsApp::class)->notify($order, 'received');
        expect(WhatsAppMessage::where('event', 'received')->count())->toBe(1);
    });
});

it('envia pelo modelo aprovado da API oficial e registra a falha', function () {
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['messages' => [['id' => 'wamid.1']]])
        ->push(['error' => ['message' => 'Template não encontrado']], 400)]);

    inTenant($this->tenant, function () {
        Setting::write('whatsapp_enabled', true);
        Setting::write('whatsapp_provider', 'cloud');
        WhatsAppSettings::saveCredentials(['cloud_phone_id' => '123456789', 'cloud_token' => 'token-meta', 'cloud_template' => 'atualizacao_pedido', 'cloud_language' => 'pt_BR']);

        $ok = app(WhatsApp::class)->sendNow('11988887777', "Linha 1\nLinha 2");
        $fail = app(WhatsApp::class)->sendNow('11988887777', 'outra');

        expect($ok->status)->toBe('sent')->and($ok->provider_message_id)->toBe('wamid.1')
            ->and($fail->status)->toBe('failed')->and($fail->error)->toContain('Template não encontrado');
    });

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/123456789/messages')
        && $r->hasHeader('Authorization', 'Bearer token-meta')
        && $r['template']['name'] === 'atualizacao_pedido'
        && $r['template']['components'][0]['parameters'][0]['text'] === 'Linha 1 Linha 2');
});

it('não avisa pedido de mesa nem pedido sem telefone', function () {
    Http::fake(['evo.example.com/*' => Http::response(['key' => ['id' => 'X']])]);
    inTenant($this->tenant, fn () => useEvolution());

    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));
    inTenant($this->tenant, function () use ($order) {
        $order->update(['customer_phone' => null]);
        app(ChangeOrderStatus::class)->handle($order->refresh(), OrderStatus::Preparing);
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Ready);

        expect(WhatsAppMessage::where('event', 'ready_pickup')->exists())->toBeFalse()
            ->and(OrderMessages::link($order))->toBeNull();
    });
});

it('monta o link do WhatsApp com a mensagem pronta (modo manual)', function () {
    $order = placeOnline($this->tenant, onlineOrderInput($this->menu));

    $link = inTenant($this->tenant, fn () => OrderMessages::link($order));

    expect($link)->toStartWith('https://wa.me/5511988887777?text=')
        ->and(rawurldecode(substr($link, strpos($link, '=') + 1)))->toContain('Recebemos seu pedido #'.$order->number);
});

it('só chama provedores em endereço https público', function () {
    expect(fn () => SafeUrl::assert('http://evo.example.com'))->toThrow(RuntimeException::class, 'https');
    expect(fn () => SafeUrl::assert('https://127.0.0.1/api'))->toThrow(RuntimeException::class, 'interno');
    expect(fn () => SafeUrl::assert('https://interno.example.com'))->toThrow(RuntimeException::class, 'interno');
    expect(fn () => SafeUrl::assert('https://user:pass@evo.example.com'))->toThrow(RuntimeException::class);
    SafeUrl::assert('https://evo.example.com/message');
});

it('salva a configuração com a chave criptografada e testa o envio', function () {
    Http::fake(['evo.example.com/*' => Http::response(['key' => ['id' => 'T1']])]);
    activateTenant($this->tenant);
    $this->actingAs(createTenantUser($this->tenant, ['dono']));

    Livewire::test(Settings::class)
        ->set('enabled', true)->set('provider', 'evolution')
        ->set('evolutionUrl', 'https://interno.example.com')->set('evolutionInstance', 'loja')->set('evolutionKey', 'chave-1')
        ->call('save')->assertHasErrors('evolutionUrl')
        ->set('evolutionUrl', 'https://evo.example.com')
        ->set('events.preparing', true)->set('templates.preparing', 'Já no forno, {nome}!')
        ->call('save')->assertHasNoErrors();

    expect(Setting::read('whatsapp_credentials'))->not->toContain('chave-1')
        ->and(WhatsAppSettings::credentials()['evolution_key'])->toBe('chave-1')
        ->and(WhatsAppSettings::eventEnabled('preparing'))->toBeTrue()
        ->and(WhatsAppSettings::template('preparing'))->toBe('Já no forno, {nome}!');

    // Chave em branco mantém a salva.
    Livewire::test(Settings::class)->set('evolutionInstance', 'loja2')->call('save');
    expect(WhatsAppSettings::credentials())->toMatchArray(['evolution_key' => 'chave-1', 'evolution_instance' => 'loja2']);

    Livewire::test(Settings::class)->set('testPhone', '(11) 97777-6666')->call('sendTest')->assertHasNoErrors();
    Http::assertSent(fn (Request $r) => $r['number'] === '5511977776666');
});
