<?php

use App\Http\Controllers\Infra\CaddyAskController;
use App\Http\Controllers\Infra\HealthController;
use App\Platform\Http\UsePlatformGuard;
use App\Tenancy\Middleware\EnsureSessionBelongsToTenant;
use App\Tenancy\Middleware\EnsureTenantIsActive;
use App\Tenancy\Middleware\IdentifyTenant;
use App\Tenancy\Middleware\UseTenantGuard;
use App\Tenancy\Resolvers\PathResolver;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Sentry\Laravel\Integration;

$isPlatformHost = fn (Request $request) => $request->getHost() === config('tenancy.platform_domain');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            // Infraestrutura, em qualquer host e sem sessão: saúde e liberação de certificado (Caddy).
            Route::get('/saude', HealthController::class)->name('infra.health');
            Route::get('/caddy/ask', CaddyAskController::class)->name('infra.caddy-ask');

            // Admin da plataforma (equipe do SaaS), só no domínio dele.
            Route::middleware('platform')
                ->domain(config('tenancy.platform_domain'))
                ->name('platform.')
                ->group(base_path('routes/platform.php'));

            // Domínios centrais: site/cadastro e o cardápio por slug.
            foreach (config('tenancy.central_domains') as $domain) {
                Route::middleware('web')->domain($domain)->group(base_path('routes/web.php'));

                Route::middleware('tenant')->domain($domain)
                    ->prefix('cardapio/{'.PathResolver::PARAMETER.'}')
                    ->group(base_path('routes/menu.php'));
            }

            // Qualquer outro host é tratado como tenant (404 se não for conhecido).
            Route::middleware('tenant')->group(base_path('routes/tenant.php'));
            Route::middleware('tenant')->prefix('cardapio')->group(base_path('routes/menu.php'));
        },
    )
    ->withCommands([
        __DIR__.'/../app/Tenancy/Console',
        __DIR__.'/../app/Platform/Console',
    ])
    ->withMiddleware(function (Middleware $middleware) use ($isPlatformHost): void {
        // O tenant é resolvido e ativado antes da sessão, da autenticação e do binding.
        $middleware->group('tenant', [
            IdentifyTenant::class,
            EnsureTenantIsActive::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            EnsureSessionBelongsToTenant::class,
            UseTenantGuard::class,
            SubstituteBindings::class,
        ]);
        // Os três middlewares de tenancy ficam fora da lista de prioridade de propósito:
        // assim o Laravel nunca os reordena em relação à sessão.

        $middleware->group('platform', ['web', UsePlatformGuard::class]);

        // Em produção o app fica atrás do Caddy (rede interna do Docker): confia nos
        // cabeçalhos X-Forwarded-* para saber o host, o IP do cliente e se é HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn (Request $request) => $isPlatformHost($request) ? route('platform.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $isPlatformHost($request) ? route('platform.tenants.index') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Erros para o Sentry (só com SENTRY_LARAVEL_DSN configurado).
        Integration::handles($exceptions);
    })->create();
