<x-layouts.app :title="tenant()->name">
    <h1 class="text-2xl font-semibold">{{ tenant()->name }}</h1>
    <p class="mt-1 text-sm text-stone-500">Schema: {{ tenant()->schema_name }}</p>

    <div class="mt-8">
        <livewire:tenant.welcome-message />
    </div>
</x-layouts.app>
