<?php

namespace App\Modules\Messaging\Providers;

use Closure;
use RuntimeException;

/**
 * O endereço do provedor é digitado pelo estabelecimento: o servidor só chama URLs
 * https públicas, nunca a rede interna (banco, Redis, metadados da nuvem...).
 */
final class SafeUrl
{
    /** Resolve um host em IPs. Trocável nos testes. */
    public static ?Closure $resolver = null;

    public static function assert(string $url): void
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Use um endereço https:// do provedor.');
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (self::$resolver ?? fn ($h) => gethostbynamel($h) ?: [])($host);

        if ($ips === []) {
            throw new RuntimeException("Não foi possível encontrar o servidor {$host}.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Endereço interno não é permitido.');
            }
        }
    }
}
