<div class="space-y-6">
    <h1 class="page-title">Minha conta</h1>

    @include('partials.app-device-card')

    <form wire:submit="saveName" class="card card-body space-y-4">
        <h2 class="section-title">Dados</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nome</label>
                <input wire:model="name" id="name" type="text" class="input" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">E-mail</label>
                <input type="email" class="input" value="{{ auth()->user()->email }}" disabled>
                <p class="hint">Para trocar o e-mail, peça a quem administra os usuários.</p>
            </div>
        </div>
        <div><button type="submit" class="btn-primary"><x-icon name="check" class="size-4" /> Salvar</button></div>
    </form>

    <form wire:submit="savePassword" class="card card-body space-y-4">
        <h2 class="section-title">Alterar senha</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="current_password" class="label">Senha atual</label>
                <input wire:model="current_password" id="current_password" type="password" class="input" autocomplete="current-password">
                @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="label">Nova senha</label>
                <input wire:model="password" id="password" type="password" class="input" autocomplete="new-password">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirme</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password">
            </div>
        </div>
        <p class="hint">Ao trocar a senha, as outras sessões abertas com o seu usuário são encerradas.</p>
        <div><button type="submit" class="btn-primary">Alterar senha</button></div>
    </form>
</div>
