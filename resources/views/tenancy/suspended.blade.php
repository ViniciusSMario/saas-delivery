@php
    use App\Tenancy\Enums\TenantStatus;

    [$icon, $title, $text] = match (true) {
        $tenant->status === TenantStatus::Restoring => ['refresh', 'Em manutenção', "{$tenant->name} está passando por uma manutenção rápida. Tente de novo em alguns minutos."],
        $tenant->status === TenantStatus::PendingDeletion => ['alert', 'Conta em exclusão', "A conta de {$tenant->name} está agendada para exclusão. Para cancelar, fale com o suporte."],
        $tenant->suspension_reason === 'billing' => ['wallet', 'Conta suspensa por falta de pagamento', "O acesso a {$tenant->name} foi suspenso porque há uma fatura em aberto. Assim que o pagamento for confirmado, tudo volta a funcionar sozinho."],
        default => ['alert', 'Conta suspensa', "O acesso a {$tenant->name} está temporariamente suspenso. Entre em contato com o suporte."],
    };
    $payUrl = $tenant->suspension_reason === 'billing' ? $tenant->openInvoiceUrl() : null;
@endphp
<x-layouts.public :title="$title">
    <main class="flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center">
        <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-8" /></span>
        <h1 class="mt-5 text-2xl font-extrabold tracking-tight">{{ $title }}</h1>
        <p class="mt-3 max-w-sm text-stone-600">{{ $text }}</p>
        @if ($payUrl)
            <a href="{{ $payUrl }}" class="btn-primary btn-lg mt-6"><x-icon name="wallet" class="size-5" /> Pagar fatura</a>
        @endif
    </main>
</x-layouts.public>
