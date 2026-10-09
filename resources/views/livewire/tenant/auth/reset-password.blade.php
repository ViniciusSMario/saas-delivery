<div>
    <h2 class="text-lg font-semibold">Definir senha</h2>
    <p class="mt-1 text-sm text-stone-500">Mínimo de 8 caracteres, com letras e números.</p>

    <form wire:submit="save" class="mt-6 space-y-4">
        <div>
            <label for="email" class="label">E-mail</label>
            <input wire:model="email" id="email" type="email" class="input" autocomplete="username" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Nova senha</label>
            <input wire:model="password" id="password" type="password" class="input" autocomplete="new-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirme a nova senha</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Salvar senha</button>
    </form>
</div>
