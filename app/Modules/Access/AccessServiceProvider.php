<?php

namespace App\Modules\Access;

use App\Modules\Access\Http\EnsureUserIsActive;
use App\Modules\Access\Models\User;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Habilidades com o nome de uma Permission (ex.: "users.manage") são resolvidas
        // pelos perfis do usuário. null deixa Policies e outros Gates decidirem.
        // Recurso fora do plano do estabelecimento (ex.: estoque) é negado para todos.
        Gate::before(function (mixed $user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            $permission = Permission::tryFrom($ability);

            if ($permission?->feature() && ! (tenant()?->hasFeature($permission->feature()) ?? true)) {
                return false;
            }

            return $permission && $user->hasPermission($permission) ? true : null;
        });

        // Assinatura e plano: só o dono.
        Gate::define('billing.manage', fn (mixed $user) => $user instanceof User && $user->isOwner());

        // Ações do Livewire também derrubam usuário desativado no meio da sessão.
        Livewire::addPersistentMiddleware([AuthenticateSession::class, EnsureUserIsActive::class]);
    }
}
