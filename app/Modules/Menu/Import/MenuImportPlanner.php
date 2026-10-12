<?php

namespace App\Modules\Menu\Import;

use App\Modules\Menu\Models\AddonGroup;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Confere a planilha linha a linha contra o cardápio atual e monta o plano (prévia).
 * Nada é gravado aqui. Produto existente é reconhecido pelo código ou, sem código, pelo nome.
 */
class MenuImportPlanner
{
    public const MAX_ROWS = 1000;

    public const MAX_ADDON_ROWS = 2000;

    public const MAX_PRICE_CENTS = 10_000_000;

    private MenuImportPlan $plan;

    /** @var array<string, Category> */
    private array $existingCategories = [];

    /** @var array<string, AddonGroup> */
    private array $existingGroups = [];

    /**
     * @param  array{products: list<array>, addons: list<array>, columns: list<string>, addonSheet: bool}  $sheet  de MenuSpreadsheet::read
     * @param  string  $mode  update = atualiza o que existe; skip = só cria o que é novo
     */
    public function plan(array $sheet, string $mode, ?PhotoArchive $photos = null): MenuImportPlan
    {
        $this->plan = new MenuImportPlan;
        $this->existingCategories = Category::all()->keyBy(fn ($c) => MenuSpreadsheet::plain($c->name))->all();
        $this->existingGroups = AddonGroup::with('addons')->get()->keyBy(fn ($g) => MenuSpreadsheet::plain($g->name))->all();

        if (count($sheet['products']) > self::MAX_ROWS) {
            $this->plan->errors[] = 'A planilha tem mais de '.self::MAX_ROWS.' produtos. Divida em arquivos menores.';

            return $this->plan;
        }

        if (count($sheet['addons']) > self::MAX_ADDON_ROWS) {
            $this->plan->errors[] = 'A aba Adicionais tem mais de '.self::MAX_ADDON_ROWS.' linhas.';

            return $this->plan;
        }

        if ($sheet['products'] === []) {
            $this->plan->errors[] = 'Nenhum produto na planilha (as linhas abaixo dos títulos estão vazias).';

            return $this->plan;
        }

        $this->planGroups($sheet['addons'], $mode);
        $this->planProducts($sheet['products'], $sheet['columns'], $mode, $photos);
        $this->checkPlanLimit();

        if ($photos) {
            $used = collect($this->plan->products)->pluck('photo')->filter()->map(fn ($p) => PhotoArchive::key($p))->unique();
            $unused = array_diff($photos->names(), $used->all());

            if ($unused !== []) {
                $this->plan->warnings[] = count($unused).' foto(s) do .zip não combinam com nenhum produto: '.implode(', ', array_slice($unused, 0, 8)).(count($unused) > 8 ? '...' : '').'.';
            }

            if ($photos->ignored !== []) {
                $this->plan->warnings[] = 'Arquivos ignorados no .zip (só JPG, PNG ou WebP de até 4 MB): '.implode(', ', array_slice($photos->ignored, 0, 8)).'.';
            }
        }

        return $this->plan;
    }

    // ---------- Adicionais ----------

    private function planGroups(array $rows, string $mode): void
    {
        $sheetGroups = [];

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $where = "Adicionais, linha {$line}";
            $groupName = self::text($v['group'] ?? null);
            $optionName = self::text($v['option'] ?? null);

            if ($groupName === '' || $optionName === '') {
                $this->plan->errors[] = "{$where}: preencha o Grupo e a Opção.";

                continue;
            }

            if (mb_strlen($groupName) > 80 || mb_strlen($optionName) > 80) {
                $this->plan->errors[] = "{$where}: nome com mais de 80 caracteres.";

                continue;
            }

            $price = self::blank($v['price'] ?? null) ? 0 : self::price($v['price']);

            if ($price === null) {
                $this->plan->errors[] = "{$where}: preço inválido (\"{$v['price']}\"). Use 8,00.";

                continue;
            }

            $key = MenuSpreadsheet::plain($groupName);
            $sheetGroups[$key] ??= ['name' => $groupName, 'min' => null, 'max' => null, 'options' => [], 'line' => $line];

            foreach (['min', 'max'] as $limit) {
                if (! self::blank($v[$limit] ?? null)) {
                    $number = filter_var($v[$limit], FILTER_VALIDATE_INT);

                    if ($number === false || $number < 0 || $number > 50) {
                        $this->plan->errors[] = "{$where}: ".($limit === 'min' ? 'Mínimo' : 'Máximo').' deve ser um número de 0 a 50.';
                    } elseif ($sheetGroups[$key][$limit] === null) {
                        $sheetGroups[$key][$limit] = $number;
                    }
                }
            }

            $optionKey = MenuSpreadsheet::plain($optionName);

            if (isset($sheetGroups[$key]['options'][$optionKey])) {
                $this->plan->errors[] = "{$where}: a opção {$optionName} aparece duas vezes no grupo {$groupName}.";

                continue;
            }

            $sheetGroups[$key]['options'][$optionKey] = ['name' => $optionName, 'price' => $price];
        }

        foreach ($sheetGroups as $key => $group) {
            $existing = $this->existingGroups[$key] ?? null;
            $min = $group['min'] ?? $existing?->min_choices ?? 0;
            $max = $group['max'] ?? $existing?->max_choices ?? 1;
            $where = "Adicionais, grupo {$group['name']}";

            $options = [];
            $current = $existing ? $existing->addons->keyBy(fn ($a) => MenuSpreadsheet::plain($a->name)) : collect();

            foreach ($group['options'] as $optionKey => $option) {
                $addon = $current->get($optionKey);
                $options[] = [
                    'name' => $option['name'],
                    'price' => $option['price'],
                    'id' => $addon?->id,
                    'action' => ! $addon ? 'create' : ($addon->price_cents->cents !== $option['price'] ? 'update' : 'same'),
                    'before' => $addon?->price_cents->cents,
                ];
            }

            $total = $existing ? $current->count() + collect($options)->where('action', 'create')->count() : count($options);

            if ($max < 1 || $min > $max) {
                $this->plan->errors[] = "{$where}: o Máximo precisa ser pelo menos 1 e não pode ser menor que o Mínimo.";
            } elseif ($min > $total) {
                $this->plan->errors[] = "{$where}: Mínimo {$min}, mas o grupo só tem {$total} opção(ões).";
            }

            $changed = $existing && ($existing->min_choices !== $min || $existing->max_choices !== $max
                || collect($options)->contains(fn ($o) => $o['action'] !== 'same'));

            $this->plan->groups[$key] = [
                'name' => $existing?->name ?? $group['name'],
                'id' => $existing?->id,
                'action' => match (true) {
                    ! $existing => 'create',
                    $mode === 'skip' => 'skip',
                    $changed => 'update',
                    default => 'same',
                },
                'min' => $min,
                'max' => $max,
                'before' => $existing ? ['min_choices' => $existing->min_choices, 'max_choices' => $existing->max_choices] : null,
                'options' => $options,
            ];
        }
    }

    // ---------- Produtos ----------

    private function planProducts(array $rows, array $columns, string $mode, ?PhotoArchive $photos): void
    {
        $existing = Product::with(['addonGroups:id', 'category:id,name'])->withCount('images')->get();
        $byCode = $existing->filter(fn ($p) => $p->code)->keyBy(fn ($p) => mb_strtolower($p->code));
        $byName = $existing->groupBy(fn ($p) => MenuSpreadsheet::plain($p->name));
        $seenCodes = [];
        $seenNames = [];
        $matchedIds = [];

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $where = "Produtos, linha {$line}";

            $name = self::text($v['name'] ?? null);
            $category = self::text($v['category'] ?? null);
            $description = in_array('description', $columns, true) ? self::text($v['description'] ?? null) : '';
            $code = in_array('code', $columns, true) ? self::text($v['code'] ?? null) : '';
            $price = self::blank($v['price'] ?? null) ? null : self::price($v['price']);
            $available = self::bool($v['available'] ?? null);
            $featured = self::bool($v['featured'] ?? null);

            $errors = array_keys(array_filter([
                'falta o Nome' => $name === '',
                'falta a Categoria' => $category === '',
                'falta o Preço' => self::blank($v['price'] ?? null),
                'preço inválido ("'.self::text($v['price'] ?? null).'"), use 29,90' => ! self::blank($v['price'] ?? null) && $price === null,
                'Nome com mais de 120 caracteres' => mb_strlen($name) > 120,
                'Categoria com mais de 80 caracteres' => mb_strlen($category) > 80,
                'Descrição com mais de 1000 caracteres' => mb_strlen($description) > 1000,
                'Código com mais de 20 caracteres' => mb_strlen($code) > 20,
                'Disponível deve ser sim ou não' => ! self::blank($v['available'] ?? null) && ! $available && ! self::isNo($v['available']),
                'Destaque deve ser sim ou não' => ! self::blank($v['featured'] ?? null) && ! $featured && ! self::isNo($v['featured']),
            ]));

            // Grupos de adicionais citados: da aba Adicionais ou já cadastrados.
            $groups = null;

            if (! self::blank($v['addons'] ?? null)) {
                $groups = [];

                foreach (preg_split('/[;|]/', (string) $v['addons']) as $groupName) {
                    $groupKey = MenuSpreadsheet::plain($groupName);

                    if ($groupKey === '') {
                        continue;
                    }

                    if (! isset($this->plan->groups[$groupKey]) && ! isset($this->existingGroups[$groupKey])) {
                        $errors[] = 'o grupo de adicionais "'.trim($groupName).'" não existe (crie na aba Adicionais)';
                    } else {
                        $groups[] = $groupKey;
                    }
                }

                $groups = array_values(array_unique($groups));
            }

            // Repetidos dentro da planilha.
            $nameKey = MenuSpreadsheet::plain($category).'|'.MenuSpreadsheet::plain($name);

            if ($code !== '' && isset($seenCodes[mb_strtolower($code)])) {
                $errors[] = "o código {$code} já está na linha {$seenCodes[mb_strtolower($code)]}";
            } elseif ($code === '' && $name !== '' && isset($seenNames[$nameKey])) {
                $errors[] = "o produto {$name} já está na linha {$seenNames[$nameKey]}";
            }

            if ($code !== '') {
                $seenCodes[mb_strtolower($code)] = $line;
            }

            if ($name !== '') {
                $seenNames[$nameKey] ??= $line;
            }

            // Produto que já existe: pelo código; sem código, pelo nome (único ou na mesma categoria).
            $product = null;

            if ($code !== '' && $byCode->has(mb_strtolower($code))) {
                $product = $byCode->get(mb_strtolower($code));
            } elseif ($name !== '') {
                $sameName = $byName->get(MenuSpreadsheet::plain($name), collect())->filter(fn ($p) => ! $p->code || $code === '' || mb_strtolower($p->code) === mb_strtolower($code));
                $product = $sameName->count() === 1 ? $sameName->first()
                    : $sameName->first(fn ($p) => MenuSpreadsheet::plain($p->category?->name) === MenuSpreadsheet::plain($category));
            }

            if ($product && isset($matchedIds[$product->id])) {
                $errors[] = "é o mesmo produto da linha {$matchedIds[$product->id]}";
            }

            if ($errors !== []) {
                $this->plan->errors[] = "{$where}: ".implode('; ', $errors).'.';

                continue;
            }

            if ($product) {
                $matchedIds[$product->id] = $line;
            }

            // Foto: pela coluna Foto; sem ela, pelo código ou pelo nome do produto.
            $photo = null;

            if ($photos) {
                $wanted = self::text($v['photo'] ?? null);
                $photo = collect([$wanted, $code, $name])->first(fn ($candidate) => $photos->has($candidate));

                if ($wanted !== '' && ! $photo) {
                    $this->plan->warnings[] = "{$where}: a foto {$wanted} não está no .zip.";
                }

                // Produto que já tem foto não recebe outra (importar de novo não duplica).
                if ($photo && $product && $product->images_count > 0) {
                    $photo = null;
                }
            }

            $this->registerCategory($category);

            $row = [
                'line' => $line,
                'action' => 'create',
                'id' => $product?->id,
                'category' => $category,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'price' => $price,
                'code' => $code !== '' ? $code : null,
                'available' => self::blank($v['available'] ?? null) ? null : $available,
                'featured' => self::blank($v['featured'] ?? null) ? null : $featured,
                'groups' => $groups,
                'photo' => $photo,
                'changes' => [],
            ];

            if ($product) {
                $row['changes'] = $this->changes($product, $row);
                $row['action'] = match (true) {
                    $mode === 'skip' => 'skip',
                    $row['changes'] !== [] => 'update',
                    default => 'same',
                };

                if ($row['action'] === 'skip') {
                    $row['photo'] = null;
                }
            }

            $this->plan->products[] = $row;
        }
    }

    /** @return list<string> o que muda no produto ("preço R$ 45,00 → R$ 49,90") */
    private function changes(Product $product, array $row): array
    {
        $money = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');
        $changes = [];

        if (MenuSpreadsheet::plain($product->category?->name) !== MenuSpreadsheet::plain($row['category'])) {
            $changes[] = "categoria: {$product->category?->name} → {$row['category']}";
        }

        if ($product->name !== $row['name']) {
            $changes[] = "nome: {$product->name} → {$row['name']}";
        }

        if ($row['price'] !== null && $product->price_cents->cents !== $row['price']) {
            $changes[] = 'preço: '.$money($product->price_cents->cents).' → '.$money($row['price']);
        }

        if ($row['description'] !== null && (string) $product->description !== $row['description']) {
            $changes[] = 'descrição';
        }

        if ($row['code'] !== null && $product->code !== $row['code']) {
            $changes[] = 'código: '.($product->code ?: 'sem código').' → '.$row['code'];
        }

        if ($row['available'] !== null && $product->is_available !== $row['available']) {
            $changes[] = $row['available'] ? 'volta a ficar disponível' : 'fica esgotado';
        }

        if ($row['featured'] !== null && $product->is_featured !== $row['featured']) {
            $changes[] = $row['featured'] ? 'entra nos destaques' : 'sai dos destaques';
        }

        if ($row['groups'] !== null) {
            // Grupo que ainda vai ser criado entra como "novo:<nome>" e sempre conta como mudança.
            $current = $product->addonGroups->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $wanted = collect($row['groups'])
                ->map(fn ($key) => (string) ($this->plan->groups[$key]['id'] ?? ($this->existingGroups[$key] ?? null)?->id ?? 'novo:'.$key))
                ->sort()->values()->all();

            if ($current !== $wanted) {
                $changes[] = 'adicionais';
            }
        }

        if ($row['photo']) {
            $changes[] = 'foto';
        }

        return $changes;
    }

    private function registerCategory(string $name): void
    {
        $key = MenuSpreadsheet::plain($name);
        $this->plan->categories[$key] ??= [
            'name' => $this->existingCategories[$key]->name ?? $name,
            'id' => $this->existingCategories[$key]->id ?? null,
        ];
    }

    private function checkPlanLimit(): void
    {
        $creating = collect($this->plan->products)->where('action', 'create')->count();

        if (! $creating || ! tenant()) {
            return;
        }

        try {
            tenant()->assertWithinLimit('products', Product::count(), 'file', $creating);
        } catch (ValidationException $e) {
            $this->plan->errors[] = collect($e->errors())->flatten()->first();
        }
    }

    // ---------- Valores da planilha ----------

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string) $value)) : '';
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** "29,90", "R$ 1.234,56", "29.90", 29.9 → centavos; null se não for preço. */
    public static function price(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $cents = (int) round($value * 100);
        } else {
            $text = str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value);

            if (str_contains($text, ',')) {
                $text = str_replace(['.', ','], ['', '.'], $text);
            } elseif (substr_count($text, '.') > 1) {
                $text = str_replace('.', '', $text);
            }

            if (! preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
                return null;
            }

            $cents = (int) round(((float) $text) * 100);
        }

        return $cents >= 0 && $cents <= self::MAX_PRICE_CENTS ? $cents : null;
    }

    /** sim/não, s/n, x, 1/0, verdadeiro/falso → bool; vazio ou desconhecido → false (o chamador confere). */
    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(MenuSpreadsheet::plain((string) $value), ['sim', 's', 'x', '1', 'yes', 'y', 'true', 'verdadeiro', 'ok'], true);
    }

    private static function isNo(mixed $value): bool
    {
        if (is_bool($value)) {
            return ! $value;
        }

        return in_array(MenuSpreadsheet::plain((string) $value), ['nao', 'n', '0', 'no', 'false', 'falso', '-'], true);
    }
}
