<?php

namespace App\Http\Controllers\Central;

use App\Platform\Actions\SelfSignup;
use App\Platform\Models\Plan;
use App\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Site central: página inicial com os planos e cadastro self-service. */
class SignupController
{
    public function home(): View
    {
        return view('central.home', ['plans' => Plan::available()->get()]);
    }

    public function create(Request $request): View
    {
        $plans = Plan::available()->get();

        return view('central.signup', [
            'plans' => $plans,
            'selected' => $plans->firstWhere('slug', $request->query('plano'))?->slug ?? $plans->get(1)?->slug ?? $plans->first()?->slug,
            'baseDomain' => config('tenancy.base_domain'),
        ]);
    }

    public function store(Request $request, SelfSignup $signup): RedirectResponse
    {
        // Robôs preenchem o campo escondido.
        abort_if($request->filled('website'), 422);

        $key = 'signup:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('platform.signup_per_hour'))) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['name' => 'Muitos cadastros a partir deste endereço. Tente de novo mais tarde.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan' => ['required', 'string'],
            'terms' => ['accepted'],
        ], [
            'slug.regex' => 'Use só letras minúsculas, números e hífen (ex.: pizzaria-do-joao).',
            'terms.accepted' => 'É preciso aceitar os termos de uso.',
        ], [
            'name' => 'nome do estabelecimento', 'slug' => 'endereço', 'owner_name' => 'seu nome',
            'email' => 'e-mail', 'phone' => 'telefone', 'password' => 'senha', 'plan' => 'plano',
        ]);

        RateLimiter::hit($key, 3600);

        $tenant = $signup->handle($data, $request->ip());

        return redirect()->route('signup.done')->with('signup_tenant', $tenant->id);
    }

    /** Sugestão de endereço e disponibilidade (usado enquanto a pessoa digita). */
    public function check(Request $request): JsonResponse
    {
        $slug = Str::slug((string) $request->query('slug'));
        $slug = trim(mb_substr($slug, 0, 40), '-');

        return response()->json(['slug' => $slug, 'available' => $slug !== '' && SelfSignup::isAvailable($slug)]);
    }

    public function done(Request $request): View|RedirectResponse
    {
        $tenant = Tenant::find($request->session()->get('signup_tenant'));

        if (! $tenant) {
            return redirect()->route('signup.create');
        }

        $request->session()->keep('signup_tenant');

        return view('central.signup-done', [
            'tenant' => $tenant,
            'loginUrl' => SelfSignup::url($tenant, '/login'),
            'menuUrl' => SelfSignup::url($tenant, '/cardapio'),
        ]);
    }
}
