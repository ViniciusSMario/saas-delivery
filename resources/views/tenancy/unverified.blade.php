<x-layouts.public title="Cardápio em preparação">
    <main class="flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center">
        <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="utensils" class="size-8" /></span>
        <h1 class="mt-5 text-2xl font-extrabold tracking-tight">Cardápio em preparação</h1>
        <p class="mt-3 max-w-sm text-stone-600">O cardápio online de {{ $tenant->name }} ainda não está no ar. Volte em breve!</p>
    </main>
</x-layouts.public>
