<div>
    <h2 class="text-lg font-semibold">Esqueci minha senha</h2>

    @if ($sent)
        <div class="alert-success mt-4">Se o e-mail estiver cadastrado neste estabelecimento, você vai receber um link para definir uma nova senha.</div>
    @else
        <p class="mt-1 text-sm text-stone-500">Informe seu e-mail e enviaremos um link para definir uma nova senha.</p>

        <form wire:submit="send" class="mt-6 space-y-4">
            <div>
                <label for="email" class="label">E-mail</label>
                <input wire:model="email" id="email" type="email" class="input" autocomplete="username" autofocus required>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Enviar link</button>
        </form>
    @endif

    <p class="mt-6 text-center">
        <a href="{{ route('login') }}" class="btn-link" wire:navigate>Voltar para o login</a>
    </p>
</div>
