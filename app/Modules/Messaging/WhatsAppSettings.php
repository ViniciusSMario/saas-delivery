<?php

namespace App\Modules\Messaging;

use App\Modules\Establishment\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Configuração do WhatsApp do estabelecimento (tabela settings). As credenciais do
 * provedor ficam criptografadas com a APP_KEY.
 *
 * Provedores: manual (botão com a mensagem pronta, sem envio automático),
 * evolution (Evolution API) e cloud (API oficial do WhatsApp, da Meta).
 */
class WhatsAppSettings
{
    public const PROVIDERS = [
        'manual' => 'Manual (botão “Avisar no WhatsApp”)',
        'evolution' => 'Evolution API',
        'cloud' => 'WhatsApp Cloud API (Meta, oficial)',
    ];

    /** evento => [rótulo, ligado por padrão, texto padrão] */
    public const EVENTS = [
        'received' => ['Pedido recebido', true, 'Olá, {nome}! Recebemos seu pedido #{numero} no {estabelecimento}. Total: {total}. Acompanhe: {link}'],
        'preparing' => ['Em preparo', false, '{nome}, seu pedido #{numero} já está sendo preparado!'],
        'ready_pickup' => ['Pronto para retirada', true, '{nome}, seu pedido #{numero} está pronto para retirada no {estabelecimento}.'],
        'dispatched' => ['Saiu para entrega', true, '{nome}, seu pedido #{numero} saiu para entrega! Acompanhe: {link}'],
        'completed' => ['Entregue', false, 'Pedido #{numero} entregue. Obrigado pela preferência, {nome}!'],
        'cancelled' => ['Cancelado', true, '{nome}, seu pedido #{numero} foi cancelado. Motivo: {motivo}. Qualquer dúvida, fale com a gente.'],
    ];

    public static function enabled(): bool
    {
        return (bool) Setting::read('whatsapp_enabled', false);
    }

    public static function provider(): string
    {
        $provider = (string) Setting::read('whatsapp_provider', 'manual');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'manual';
    }

    /** Envio automático ligado (provedor que manda sozinho). */
    public static function automatic(): bool
    {
        return self::enabled() && self::provider() !== 'manual';
    }

    /** @return array<string, string> */
    public static function credentials(): array
    {
        $stored = Setting::read('whatsapp_credentials');

        if (! is_string($stored) || $stored === '') {
            return [];
        }

        try {
            return (array) json_decode(Crypt::decryptString($stored), true);
        } catch (Throwable) {
            return [];
        }
    }

    /** @param  array<string, string>  $credentials */
    public static function saveCredentials(array $credentials): void
    {
        Setting::write('whatsapp_credentials', Crypt::encryptString(json_encode($credentials)));
    }

    public static function eventEnabled(string $event): bool
    {
        $toggles = (array) Setting::read('whatsapp_events', []);

        return (bool) ($toggles[$event] ?? self::EVENTS[$event][1] ?? false);
    }

    public static function template(string $event): string
    {
        $templates = (array) Setting::read('whatsapp_templates', []);

        return trim((string) ($templates[$event] ?? '')) ?: (self::EVENTS[$event][2] ?? '');
    }
}
