<?php

namespace App\Modules\Delivery\Import;

use App\Modules\Access\Models\User;
use App\Modules\Delivery\DeliveryFees;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Imports\ImportPlan;
use App\Modules\Imports\ImportRecorder;
use App\Modules\Imports\SpreadsheetReader as Sheet;
use App\Modules\Imports\TabularImport;
use App\Support\Money;
use Illuminate\Support\Collection;

/** Bairros atendidos com taxa, pedido mínimo e tempo de entrega (reconhecidos pelo nome). */
class ZonesImport implements TabularImport
{
    public function kind(): string
    {
        return 'zones';
    }

    public function title(): string
    {
        return 'Bairros e taxas';
    }

    public function noun(): array
    {
        return ['bairro', 'bairros', 'm'];
    }

    public function permission(): string
    {
        return 'settings.manage';
    }

    public function columns(): array
    {
        return [
            'name' => ['Bairro', ['bairro', 'nome', 'regiao', 'zona', 'localidade']],
            'fee' => ['Taxa', ['taxa', 'taxa de entrega', 'frete', 'valor', 'entrega']],
            'min' => ['Pedido mínimo', ['pedido minimo', 'minimo', 'valor minimo']],
            'eta' => ['Tempo (min)', ['tempo', 'tempo de entrega', 'prazo', 'minutos']],
            'active' => ['Ativo', ['ativo', 'atende', 'entrega ativa']],
        ];
    }

    public function required(): array
    {
        return ['name', 'fee'];
    }

    public function widths(): array
    {
        return [30, 12, 16, 13, 10];
    }

    public function examples(): array
    {
        return [
            ['Centro', '5,00', '', 30, 'sim'],
            ['Jardim América', '8,00', '40,00', 45, 'sim'],
            ['Vila Nova', '0', '60,00', 40, 'sim'],
        ];
    }

    public function instructions(): array
    {
        return [
            '# Como preencher a planilha de bairros',
            '',
            'Bairro e Taxa são obrigatórios. Taxa 0 = entrega grátis naquele bairro.',
            'Pedido mínimo e Tempo (minutos) são opcionais: em branco valem os gerais das Configurações.',
            'Ativo: sim ou não (em branco = sim). Bairro inativo some da lista do cardápio.',
            'O bairro é reconhecido pelo nome (sem diferenciar maiúsculas): importar de novo atualiza as taxas.',
            '',
            'Para as taxas valerem, a entrega precisa estar no modo "Taxa por bairro" (Entregas → Bairros).',
        ];
    }

    public function current(): iterable
    {
        $money = fn (?Money $m) => $m ? number_format($m->cents / 100, 2, ',', '.') : '';

        foreach (DeliveryZone::ordered()->get() as $zone) {
            yield [$zone->name, $money($zone->fee_cents), $money($zone->min_order_cents), $zone->eta_minutes ?? '', $zone->is_active ? 'sim' : 'não'];
        }
    }

    public function plan(array $rows, array $columns, string $mode): ImportPlan
    {
        $plan = new ImportPlan;
        $existing = DeliveryZone::all()->keyBy(fn ($z) => Sheet::plain($z->name));
        $seen = [];
        $money = fn (?int $cents) => $cents === null ? '—' : Money::cents($cents)->format();

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $name = Sheet::text($v['name'] ?? null);
            $fee = Sheet::blank($v['fee'] ?? null) ? null : Sheet::money($v['fee']);
            $min = Sheet::blank($v['min'] ?? null) ? null : Sheet::money($v['min']);
            $eta = Sheet::blank($v['eta'] ?? null) ? null : filter_var($v['eta'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 300]]);
            $active = Sheet::blank($v['active'] ?? null) ? null : Sheet::bool($v['active']);
            $key = Sheet::plain($name);

            $errors = array_keys(array_filter([
                'falta o Bairro' => $name === '',
                'Bairro com mais de 120 caracteres' => mb_strlen($name) > 120,
                'falta a Taxa' => Sheet::blank($v['fee'] ?? null),
                'taxa inválida ("'.Sheet::text($v['fee'] ?? null).'"), use 5,00' => ! Sheet::blank($v['fee'] ?? null) && $fee === null,
                'pedido mínimo inválido' => ! Sheet::blank($v['min'] ?? null) && $min === null,
                'tempo deve ser em minutos (0 a 300)' => ! Sheet::blank($v['eta'] ?? null) && $eta === false,
                'Ativo deve ser sim ou não' => ! Sheet::blank($v['active'] ?? null) && $active === null,
                'bairro repetido na linha '.($seen[$key] ?? '') => $key !== '' && isset($seen[$key]),
            ]));

            if ($errors) {
                $plan->errors[] = "Linha {$line}: ".implode('; ', $errors).'.';

                continue;
            }

            $seen[$key] = $line;
            $data = ['name' => $name, 'fee' => $fee, 'min' => $min, 'eta' => $eta === false ? null : $eta, 'active' => $active];
            $detail = 'taxa '.$money($fee).($min !== null ? ' · mínimo '.$money($min) : '').($data['eta'] !== null ? " · {$data['eta']} min" : '');
            $zone = $existing->get($key);

            if (! $zone) {
                $plan->add($line, 'create', $name, $detail, [], $data);

                continue;
            }

            $changes = array_values(array_filter([
                $zone->fee_cents->cents !== $fee ? 'taxa: '.$zone->fee_cents->format().' → '.$money($fee) : null,
                $min !== null && $zone->min_order_cents?->cents !== $min ? 'mínimo: '.$money($zone->min_order_cents?->cents).' → '.$money($min) : null,
                $data['eta'] !== null && $zone->eta_minutes !== $data['eta'] ? 'tempo: '.($zone->eta_minutes ?? '—').' → '.$data['eta'].' min' : null,
                $active !== null && $zone->is_active !== $active ? ($active ? 'volta a atender' : 'deixa de atender') : null,
            ]));

            $plan->add($line, $mode === 'skip' ? 'skip' : ($changes ? 'update' : 'same'), $zone->name, $detail, $changes, $data + ['id' => $zone->id]);
        }

        if ($plan->rows && ! DeliveryFees::byZone()) {
            $plan->warnings[] = 'A entrega está com taxa única. Depois de importar, escolha "Taxa por bairro" em Entregas → Bairros para estas taxas valerem.';
        }

        return $plan;
    }

    public function apply(ImportPlan $plan, ImportRecorder $recorder, ?User $user): void
    {
        foreach ($plan->rows as ['action' => $action, 'data' => $d]) {
            if ($action === 'create') {
                $zone = DeliveryZone::create([
                    'name' => $d['name'], 'fee_cents' => Money::cents($d['fee']), 'min_order_cents' => $d['min'] !== null ? Money::cents($d['min']) : null,
                    'eta_minutes' => $d['eta'], 'is_active' => $d['active'] ?? true,
                ]);
                $recorder->track('delivery_zone', $zone->id, 'created');
            } elseif ($action === 'update') {
                $zone = DeliveryZone::findOrFail($d['id']);
                $recorder->track('delivery_zone', $zone->id, 'updated', [
                    'fee_cents' => $zone->fee_cents->cents, 'min_order_cents' => $zone->min_order_cents?->cents,
                    'eta_minutes' => $zone->eta_minutes, 'is_active' => $zone->is_active,
                ]);
                $zone->fill(array_filter([
                    'fee_cents' => Money::cents($d['fee']),
                    'min_order_cents' => $d['min'] !== null ? Money::cents($d['min']) : null,
                    'eta_minutes' => $d['eta'],
                    'is_active' => $d['active'],
                ], fn ($value) => $value !== null))->save();
            }
        }
    }

    public function undo(Collection $items, ?User $user): void
    {
        foreach ($items->where('subject_type', 'delivery_zone') as $item) {
            $item->action === 'created'
                ? DeliveryZone::whereKey($item->subject_id)->delete()
                : DeliveryZone::whereKey($item->subject_id)->update($item->before);
        }
    }
}
