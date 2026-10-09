<x-layouts.app :title="'Cardápio · '.tenant()->name">
    <h1 class="text-2xl font-semibold">Cardápio de {{ tenant()->name }}</h1>
    <p class="mt-4">{{ \App\Modules\Establishment\Models\Setting::read('welcome_message') }}</p>
    <p class="mt-2 text-sm text-stone-500">Os produtos chegam na fase 3.</p>
</x-layouts.app>
