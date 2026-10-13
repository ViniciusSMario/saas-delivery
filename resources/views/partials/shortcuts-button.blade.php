{{-- Abre a lista de atalhos da tela (resources/js/shortcuts.js); só aparece com teclado/mouse. --}}
<button type="button" class="btn-secondary hidden [@media(pointer:fine)]:inline-flex" @click="window.showShortcuts()" title="Atalhos de teclado (?)">
    <span class="kbd">?</span> Atalhos
</button>
