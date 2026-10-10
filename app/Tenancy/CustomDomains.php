<?php

namespace App\Tenancy;

use App\Tenancy\Models\Tenant;
use App\Tenancy\Models\TenantDomain;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Domínio próprio do estabelecimento (ex.: pedidos.pizzariadojoao.com.br).
 * 1. o dono cadastra; 2. cria no DNS o CNAME (aponta para o subdomínio dele) e o TXT
 * com o código; 3. verifica. Só domínio verificado abre o sistema e ganha certificado.
 */
class CustomDomains
{
    public const MAX_PER_TENANT = 3;

    /** Consulta de DNS (tipo => registros). Trocável nos testes. */
    public static ?Closure $resolver = null;

    public function add(Tenant $tenant, string $domain): TenantDomain
    {
        $domain = mb_strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim(explode('/', $domain)[0], '.');

        if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw ValidationException::withMessages(['domain' => 'Domínio inválido. Ex.: pedidos.seurestaurante.com.br']);
        }

        $base = config('tenancy.base_domain');

        if ($domain === $base || str_ends_with($domain, '.'.$base) || in_array($domain, [...config('tenancy.central_domains'), config('tenancy.platform_domain')], true)) {
            throw ValidationException::withMessages(['domain' => 'Use um domínio seu, não um endereço do sistema.']);
        }

        if (TenantDomain::where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['domain' => 'Este domínio já está cadastrado.']);
        }

        if ($tenant->domains()->where('type', 'custom')->count() >= self::MAX_PER_TENANT) {
            throw ValidationException::withMessages(['domain' => 'Limite de '.self::MAX_PER_TENANT.' domínios próprios.']);
        }

        $record = $tenant->domains()->create([
            'domain' => $domain,
            'type' => 'custom',
            'is_primary' => false,
            'verification_token' => 'saas-'.Str::lower(Str::random(32)),
        ]);
        $tenant->recordEvent('domain_added', ['domain' => $domain]);

        return $record;
    }

    /** Nome do registro TXT que prova a posse do domínio. */
    public static function txtName(TenantDomain $domain): string
    {
        return config('tenancy.domain_verification_prefix', '_saas-verify').'.'.$domain->domain;
    }

    /**
     * Confere o TXT. Devolve também para onde o domínio aponta (CNAME), para orientar o dono.
     *
     * @return array{verified: bool, txt_found: bool, cname: string|null}
     */
    public function verify(TenantDomain $domain): array
    {
        $txt = collect($this->lookup(self::txtName($domain), 'TXT'))->map(fn ($v) => trim((string) $v, '"'));
        $cname = collect($this->lookup($domain->domain, 'CNAME'))->first();
        $found = $txt->contains($domain->verification_token);

        $domain->update(['last_checked_at' => now()] + ($found && ! $domain->verified_at ? ['verified_at' => now()] : []));

        if ($found) {
            $domain->tenant->recordEvent('domain_verified', ['domain' => $domain->domain]);
        }

        return ['verified' => (bool) $domain->fresh()->verified_at, 'txt_found' => $found, 'cname' => $cname ? rtrim($cname, '.') : null];
    }

    /** O domínio próprio vira o endereço principal (links de e-mail, WhatsApp, cardápio). */
    public function makePrimary(TenantDomain $domain): void
    {
        if ($domain->type === 'custom' && ! $domain->verified_at) {
            throw ValidationException::withMessages(['domain' => 'Verifique o domínio antes de usá-lo como principal.']);
        }

        $domain->tenant->domains()->update(['is_primary' => false]);
        $domain->update(['is_primary' => true]);
    }

    public function remove(TenantDomain $domain): void
    {
        if ($domain->type !== 'custom') {
            throw ValidationException::withMessages(['domain' => 'O endereço do sistema não pode ser removido.']);
        }

        $tenant = $domain->tenant;
        $wasPrimary = $domain->is_primary;
        $domain->delete();

        if ($wasPrimary) {
            $tenant->domains()->where('type', 'subdomain')->update(['is_primary' => true]);
        }

        $tenant->recordEvent('domain_removed', ['domain' => $domain->domain]);
    }

    /** @return list<string> */
    private function lookup(string $name, string $type): array
    {
        if (self::$resolver) {
            return (self::$resolver)($name, $type);
        }

        $records = @dns_get_record($name, $type === 'TXT' ? DNS_TXT : DNS_CNAME) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['txt'] ?? $r['target'] ?? null, $records)));
    }
}
