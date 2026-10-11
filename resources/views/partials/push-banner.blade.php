{{-- Convite para ativar as notificações (ou instalar o app, no iPhone). Some ao ativar ou fechar. --}}
<div x-data="appDevice" x-show="bannerMode" x-cloak class="{{ $class ?? 'mb-4' }} flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-3">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white"><x-icon name="bell" class="size-5" /></span>
    <p class="min-w-0 flex-1 text-sm text-stone-800">
        <span x-show="bannerMode === 'enable'">Ative as notificações para saber na hora {{ $what }}.</span>
        <span x-show="bannerMode === 'install'">Instale o app na Tela de Início para ser avisado {{ $what }}.</span>
        <span x-show="error" x-text="error" class="mt-1 block font-medium text-red-700"></span>
    </p>
    <button type="button" x-show="bannerMode === 'enable'" @click="enable()" :disabled="busy" class="btn-primary btn-sm shrink-0">Ativar</button>
    <a href="{{ route('profile') }}" x-show="bannerMode === 'install'" wire:navigate class="btn-primary btn-sm shrink-0">Como instalar</a>
    <button type="button" @click="dismiss()" class="-mr-1 shrink-0 p-1 text-stone-400" aria-label="Fechar aviso"><x-icon name="x" class="size-5" /></button>
</div>
