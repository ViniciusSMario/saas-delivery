<?php

namespace App\Livewire\Tenant\Concerns;

/**
 * Rascunho da tela guardado na sessão do usuário: atualizar a página, fechar a aba sem
 * querer ou cair a internet não perde o pedido em andamento. Cada tela diz a chave
 * (draftKey) e os campos guardados (draftFields). O rascunho vale por 12 horas.
 */
trait KeepsDraft
{
    public const DRAFT_HOURS = 12;

    /** A tela abriu com um pedido recuperado (mostra o aviso com "Começar do zero"). */
    public bool $draftRestored = false;

    abstract protected function draftKey(): string;

    /** @return list<string> */
    abstract protected function draftFields(): array;

    /** Vale a pena guardar? Por padrão, quando há itens no carrinho. */
    protected function draftHasContent(): bool
    {
        return ! empty($this->cart);
    }

    protected function restoreDraft(): void
    {
        $draft = session()->get($this->draftKey());

        if (! is_array($draft) || ($draft['saved_at'] ?? 0) < now()->subHours(self::DRAFT_HOURS)->getTimestamp()) {
            session()->forget($this->draftKey());

            return;
        }

        foreach ($this->draftFields() as $field) {
            if (array_key_exists($field, $draft['data'] ?? []) && gettype($draft['data'][$field]) === gettype($this->{$field})) {
                $this->{$field} = $draft['data'][$field];
            }
        }

        $this->draftRestored = $this->draftHasContent();
    }

    /** Ao fim de cada requisição do Livewire: guarda (ou apaga, se a tela ficou vazia). */
    public function dehydrateKeepsDraft(): void
    {
        if (! $this->draftHasContent()) {
            session()->forget($this->draftKey());

            return;
        }

        $data = [];

        foreach ($this->draftFields() as $field) {
            $data[$field] = $this->{$field};
        }

        session()->put($this->draftKey(), ['saved_at' => now()->getTimestamp(), 'data' => $data]);
    }

    protected function forgetDraft(): void
    {
        session()->forget($this->draftKey());
        $this->draftRestored = false;
    }
}
