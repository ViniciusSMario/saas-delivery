<?php

namespace App\Platform\Actions;

use App\Platform\Billing\Subscriptions;
use App\Platform\Models\Plan;
use App\Platform\Notifications\TenantNotice;
use App\Tenancy\Actions\ProvisionTenant;
use App\Tenancy\Exceptions\InvalidTenantData;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro feito pelo próprio restaurante: cria o estabelecimento com o dono (senha
 * escolhida no formulário), começa o teste grátis do plano e manda as boas-vindas.
 */
class SelfSignup
{
    public function __construct(
        private readonly OnboardTenant $onboard,
        private readonly Subscriptions $subscriptions,
    ) {
    }

    /**
     * @param  array{name: string, slug: string, owner_name: string, email: string, password: string, phone?: ?string, document?: ?string, plan: string}  $data
     */
    public function handle(array $data, ?string $ip = null): Tenant
    {
        $plan = Plan::available()->where('slug', $data['plan'])->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan' => 'Escolha um plano.']);
        }

        $slug = mb_strtolower(trim($data['slug']));

        if (! static::isAvailable($slug)) {
            throw ValidationException::withMessages(['slug' => 'Este endereço já está em uso. Escolha outro.']);
        }

        try {
            $tenant = $this->onboard->handle(
                slug: $slug,
                name: trim($data['name']),
                document: $data['document'] ?? null,
                ownerName: trim($data['owner_name']),
                ownerEmail: mb_strtolower(trim($data['email'])),
                planId: $plan->id,
                ownerPassword: $data['password'],
                phone: $data['phone'] ?? null,
            );
        } catch (InvalidTenantData $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        $this->subscriptions->startTrial($tenant, $plan, mb_strtolower(trim($data['email'])));
        $tenant->recordEvent('self_signup', ['plan' => $plan->slug, 'ip' => $ip], $data['email']);

        Notification::route('mail', $data['email'])->notify(new TenantNotice(
            "Bem-vindo ao ".config('app.name').", {$tenant->name}!",
            [
                "Seu estabelecimento está pronto e o teste grátis do plano {$plan->name} vai até ".$tenant->trial_ends_at->timezone('America/Sao_Paulo')->format('d/m/Y').'.',
                'Comece cadastrando o cardápio e as mesas. O link do seu cardápio online é '.static::url($tenant, '/cardapio').'.',
                'Entre com o e-mail e a senha que você escolheu.',
            ],
            'Entrar no painel',
            static::url($tenant, '/login'),
        ));

        return $tenant;
    }

    public static function isAvailable(string $slug): bool
    {
        if (! preg_match(ProvisionTenant::SLUG_PATTERN, $slug)) {
            return false;
        }

        $central = DB::connection('central');

        return ! $central->table('reserved_slugs')->where('slug', $slug)->exists()
            && ! $central->table('tenants')->where('slug', $slug)->exists()
            && ! $central->table('tenant_domains')->where('domain', $slug.'.'.config('tenancy.base_domain'))->exists();
    }

    public static function url(Tenant $tenant, string $path): string
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return 'http://'.$tenant->primaryDomain().($port ? ':'.$port : '').$path;
    }
}
