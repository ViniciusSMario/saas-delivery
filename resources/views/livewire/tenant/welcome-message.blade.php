<div class="rounded-lg border border-stone-200 bg-white p-6">
    <p class="text-lg">{{ $message }}</p>

    <form wire:submit="save" class="mt-4 flex gap-2">
        <input wire:model="draft" type="text" maxlength="120"
               class="flex-1 rounded border border-stone-300 px-3 py-2" aria-label="Mensagem de boas-vindas">
        <button type="submit" class="rounded bg-stone-900 px-4 py-2 text-white">Salvar</button>
    </form>
    @error('draft') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
