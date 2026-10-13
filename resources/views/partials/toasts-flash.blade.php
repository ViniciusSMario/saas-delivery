{{-- Mensagem guardada antes de um redirecionamento: vira aviso rápido (resources/js/toasts.js). --}}
@foreach (['status' => 'success', 'error' => 'error'] as $key => $tone)
    @if (session($key))
        <div hidden data-flash-toast="{{ $tone }}">{{ session($key) }}</div>
    @endif
@endforeach
