<?php

namespace App\Modules\Menu\Import;

/**
 * O que a importação vai fazer (prévia): categorias, grupos de adicionais e produtos com a ação
 * de cada um (criar, atualizar, igual ou pular), erros que impedem gravar e avisos que não impedem.
 */
class MenuImportPlan
{
    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, array{name: string, id: int|null}> chave = nome sem acento */
    public array $categories = [];

    /**
     * @var array<string, array{name: string, id: int|null, action: string, min: int, max: int, before: array|null,
     *                          options: list<array{name: string, price: int, id: int|null, action: string, before: int|null}>}>
     */
    public array $groups = [];

    /**
     * @var list<array{line: int, action: string, id: int|null, category: string, name: string, description: string|null,
     *                 price: int|null, code: string|null, available: bool|null, featured: bool|null, groups: list<string>|null,
     *                 photo: string|null, changes: list<string>}>
     */
    public array $products = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $products = collect($this->products)->countBy('action');
        $groups = collect($this->groups)->countBy('action');

        return [
            'products_created' => $products['create'] ?? 0,
            'products_updated' => $products['update'] ?? 0,
            'products_same' => $products['same'] ?? 0,
            'products_skipped' => $products['skip'] ?? 0,
            'categories_created' => collect($this->categories)->whereNull('id')->count(),
            'groups_created' => $groups['create'] ?? 0,
            'groups_updated' => $groups['update'] ?? 0,
            'photos' => collect($this->products)->filter(fn ($p) => $p['photo'] && in_array($p['action'], ['create', 'update'], true))->count(),
        ];
    }

    public function hasWork(): bool
    {
        $c = $this->counts();

        return $c['products_created'] + $c['products_updated'] + $c['categories_created'] + $c['groups_created'] + $c['groups_updated'] > 0;
    }

    /** Para a tela de prévia (guardada em cache entre o envio e a confirmação). */
    public function toArray(): array
    {
        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'counts' => $this->counts(),
            'hasWork' => $this->hasWork(),
            'categories' => array_values(array_map(fn ($c) => $c['name'], array_filter($this->categories, fn ($c) => $c['id'] === null))),
            'groups' => array_values(array_map(fn ($g) => [
                'name' => $g['name'],
                'action' => $g['action'],
                'rule' => $g['min'] === $g['max'] ? "escolha {$g['max']}" : ($g['min'] === 0 ? "até {$g['max']}" : "de {$g['min']} a {$g['max']}"),
                'options' => count($g['options']),
                'new_options' => collect($g['options'])->where('action', 'create')->count(),
            ], array_filter($this->groups, fn ($g) => in_array($g['action'], ['create', 'update'], true)))),
            'products' => array_map(fn ($p) => [
                'line' => $p['line'],
                'action' => $p['action'],
                'category' => $this->categories[MenuSpreadsheet::plain($p['category'])]['name'] ?? $p['category'],
                'name' => $p['name'],
                'price' => $p['price'],
                'code' => $p['code'],
                'photo' => (bool) $p['photo'],
                'changes' => $p['changes'],
            ], $this->products),
        ];
    }
}
