{{-- Painel do estabelecimento: garçom e entregador usam o "modo app" (celular); o resto, o painel com barra lateral. --}}
@include(auth()->user()->usesFieldMode() ? 'components.layouts.field' : 'components.layouts.panel')
