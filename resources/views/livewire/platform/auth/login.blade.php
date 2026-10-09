<div>
    <h2 class="text-lg font-semibold">Entrar</h2>
    <p class="mt-1 text-sm text-stone-500">Acesso restrito à equipe. Depois da senha, vamos pedir o código do autenticador.</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label for="email" class="label">E-mail</label>
            <input wire:model="email" id="email" type="email" class="input" autocomplete="username" autofocus required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Senha</label>
            <input wire:model="password" id="password" type="password" class="input" autocomplete="current-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Continuar</button>
    </form>
</div>
