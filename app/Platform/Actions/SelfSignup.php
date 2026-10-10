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
use Illuminate\Support\Facades\URL;
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
        $tenant->update(['email_verified_at' => null]);
        $tenant->recordEvent('self_signup', ['plan' => $plan->slug, 'ip' => $ip], $data['email']);

        static::sendVerification($tenant, welcome: true);

        return $tenant;
    }

    /** E-mail com o link (assinado, válido por 7 dias) para confirmar o endereço do dono. */
    public static function sendVerification(Tenant $tenant, bool $welcome = false): void
    {
        $link = URL::temporarySignedRoute('signup.verify', now()->addDays(7), ['tenant' => $tenant->id]);

        $lines = $welcome
            ? [
                "Seu estabelecimento está pronto e o teste grátis do plano {$tenant->plan?->name} vai até ".$tenant->trial_ends_at?->timezone('America/Sao_Paulo')->format('d/m/Y').'.',
                'Confirme o seu e-mail para colocar o cardápio online no ar. Enquanto isso, você já pode entrar no painel e montar o cardápio.',
                'Painel: '.static::url($tenant, '/login').' (entre com o e-mail e a senha que você escolheu).',
            ]
            : ['Confirme o seu e-mail para colocar o cardápio online de '.$tenant->name.' no ar.'];

        Notification::route('mail', $tenant->billing_email)->notify(new TenantNotice(
            $welcome ? 'Bem-vindo ao '.config('app.name').", {$tenant->name}! Confirme seu e-mail" : 'Confirme seu e-mail',
            $lines,
            'Confirmar e-mail',
            $link,
        ));
    }

    public static function markVerified(Tenant $tenant, string $actor = 'dono'): void
    {
        if (! $tenant->email_verified_at) {
            $tenant->update(['email_verified_at' => now()]);
            $tenant->recordEvent('email_verified', [], $actor);
        }
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
