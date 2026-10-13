<?php

namespace App\Livewire\Tenant\Concerns;

/**
 * Aviso rápido no canto da tela (resources/js/toasts.js), sem recarregar a página.
 * Antes de um redirecionamento, use session()->flash('status', ...): o aviso aparece na
 * tela seguinte. Com $undo, o aviso ganha o botão "Desfazer", que chama esse método do
 * componente com $params (a tela precisa continuar aberta para isso).
 */
trait Toasts
{
    /** @param  'success'|'error'|'warning'|'info'  $tone */
    protected function toast(string $message, string $tone = 'success', ?string $undo = null, array $params = [], string $undoLabel = 'Desfazer'): void
    {
        $this->dispatch('toast',
            message: $message,
            tone: $tone,
            undo: $undo ? ['component' => $this->getId(), 'method' => $undo, 'params' => array_values($params), 'label' => $undoLabel] : null,
        );
    }
}
