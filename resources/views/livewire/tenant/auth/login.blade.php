<div>
    <h2 class="text-lg font-semibold">Entrar</h2>
    <p class="mt-1 text-sm text-stone-500">Use o e-mail cadastrado neste estabelecimento.</p>

    @if (session('status'))
        <div class="alert-success mt-4">{{ session('status') }}</div>
    @endif

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label for="email" class="label">E-mail</label>
            <input wire:model="email" id="email" type="email" class="input" autocomplete="username" autofocus required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="label">Senha</label>
                <a href="{{ route('password.request') }}" class="btn-link text-xs" wire:navigate>Esqueci minha senha</a>
            </div>
            <input wire:model="password" id="password" type="password" class="input" autocomplete="current-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input wire:model="remember" type="checkbox" class="checkbox"> Manter conectado
        </label>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando…</span>
        </button>
    </form>
</div>
