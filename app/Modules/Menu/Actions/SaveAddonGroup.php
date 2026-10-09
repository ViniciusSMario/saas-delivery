<?php

namespace App\Modules\Menu\Actions;

use App\Modules\Access\Audit;
use App\Modules\Menu\Models\Addon;
use App\Modules\Menu\Models\AddonGroup;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAddonGroup
{
    /**
     * @param  array{name: string, min_choices: int, max_choices: int, is_active?: bool}  $data
     * @param  list<array{id?: int|null, name: string, price: Money, is_active?: bool}>  $addons  na ordem de exibição
     */
    public function handle(?AddonGroup $group, array $data, array $addons): AddonGroup
    {
        $active = count(array_filter($addons, fn (array $a) => $a['is_active'] ?? true));

        if ($data['max_choices'] < 1 || $data['min_choices'] > $data['max_choices']) {
            throw ValidationException::withMessages(['max_choices' => 'O máximo precisa ser pelo menos 1 e não pode ser menor que o mínimo.']);
        }

        if ($data['min_choices'] > $active) {
            throw ValidationException::withMessages(['min_choices' => "O mínimo ({$data['min_choices']}) é maior que o número de opções ativas ({$active})."]);
        }

        $isNew = $group === null;
        $group ??= new AddonGroup;

        DB::connection('tenant')->transaction(function () use ($group, $data, $addons, $isNew) {
            $group->fill($data)->save();

            $keep = [];

            foreach (array_values($addons) as $position => $row) {
                $addon = ! empty($row['id']) ? $group->addons()->findOrFail($row['id']) : new Addon(['addon_group_id' => $group->id]);
                $addon->fill([
                    'name' => $row['name'],
                    'price_cents' => $row['price'],
                    'is_active' => $row['is_active'] ?? true,
                    'position' => $position,
                ])->save();
                $keep[] = $addon->id;
            }

            $group->addons()->whereNotIn('id', $keep)->delete();

            Audit::record($isNew ? 'addon_group.created' : 'addon_group.updated', $group, [
                'nome' => $group->name,
                'opcoes' => count($keep),
            ]);
        });

        return $group;
    }

    public function delete(AddonGroup $group): void
    {
        Audit::record('addon_group.deleted', $group, ['nome' => $group->name, 'produtos' => $group->products()->count()]);
        $group->delete();
    }
}
