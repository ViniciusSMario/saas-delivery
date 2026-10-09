<?php

use App\Tenancy\Middleware\EnsureSessionBelongsToTenant;
use App\Tenancy\Middleware\EnsureTenantIsActive;
use App\Tenancy\Middleware\IdentifyTenant;
use App\Tenancy\Resolvers\PathResolver;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            // Domínios centrais primeiro: site/cadastro e o cardápio por slug.
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
    ])
    ->withMiddleware(function (Middleware $middleware): void {
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
            SubstituteBindings::class,
        ]);

        $middleware->prependToPriorityList(EncryptCookies::class, IdentifyTenant::class);
        $middleware->appendToPriorityList(IdentifyTenant::class, EnsureTenantIsActive::class);
        $middleware->appendToPriorityList(StartSession::class, EnsureSessionBelongsToTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
