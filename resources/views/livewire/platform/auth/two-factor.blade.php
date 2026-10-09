<div>
    <h2 class="section-title text-lg">Verificação em duas etapas</h2>

    @if ($setup)
        <p class="mt-1 text-sm text-stone-500">Primeiro acesso: escaneie o QR code com um app autenticador (Google Authenticator, Authy, 1Password...) e digite o código gerado.</p>
        <div class="mt-4 flex justify-center rounded-lg border border-stone-200 bg-white p-3">{!! $qr !!}</div>
        <p class="hint mt-2 text-center">Ou digite a chave: <code class="font-mono break-all">{{ $secret }}</code></p>
    @else
        <p class="mt-1 text-sm text-stone-500">Digite o código de 6 dígitos do seu app autenticador.</p>
    @endif

    <form wire:submit="verify" class="mt-6 space-y-4">
        <div>
            <label for="code" class="label">Código</label>
            <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="input text-center font-mono text-lg tracking-widest" autofocus required>
            @error('code') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ $setup ? 'Ativar e entrar' : 'Entrar' }}</button>
    </form>

    <p class="mt-6 text-center"><a href="{{ route('platform.login') }}" class="btn-link">Voltar</a></p>
</div>
