<?php

namespace App\Modules\Customers\Import;

use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Imports\ImportPlan;
use App\Modules\Imports\ImportRecorder;
use App\Modules\Imports\SpreadsheetReader as Sheet;
use App\Modules\Imports\TabularImport;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Collection;

/**
 * Clientes vindos de outro sistema ou de uma agenda: o telefone identifica o cliente (sem
 * duplicar); o endereço entra como mais um endereço dele quando ainda não está cadastrado.
 */
class CustomersImport implements TabularImport
{
    public function kind(): string
    {
        return 'customers';
    }

    public function title(): string
    {
        return 'Clientes';
    }

    public function noun(): array
    {
        return ['cliente', 'clientes', 'm'];
    }

    public function permission(): string
    {
        return 'customers.manage';
    }

    public function columns(): array
    {
        return [
            'name' => ['Nome', ['nome', 'cliente', 'nome do cliente', 'nome completo']],
            'phone' => ['Telefone', ['telefone', 'celular', 'whatsapp', 'fone', 'tel', 'telefone celular']],
            'email' => ['E-mail', ['email', 'e-mail', 'e mail']],
            'notes' => ['Observações', ['observacoes', 'observacao', 'obs', 'anotacoes']],
            'zip' => ['CEP', ['cep']],
            'street' => ['Rua', ['rua', 'endereco', 'logradouro']],
            'number' => ['Número', ['numero', 'n', 'no', 'num']],
            'complement' => ['Complemento', ['complemento', 'compl']],
            'district' => ['Bairro', ['bairro']],
            'city' => ['Cidade', ['cidade', 'municipio']],
            'reference' => ['Referência', ['referencia', 'ponto de referencia']],
        ];
    }

    public function required(): array
    {
        return ['name', 'phone'];
    }

    public function widths(): array
    {
        return [26, 18, 26, 30, 11, 28, 9, 16, 20, 18, 26];
    }

    public function examples(): array
    {
        return [
            ['Ana Lima', '(11) 98888-7777', 'ana@email.com', 'Prefere entrega no portão', '01310-100', 'Avenida Paulista', '1000', 'Apto 52', 'Bela Vista', 'São Paulo', 'Prédio azul'],
            ['Bruno Costa', '11977776666', '', 'Alérgico a camarão', '', '', '', '', '', '', ''],
        ];
    }

    public function instructions(): array
    {
        return [
            '# Como preencher a planilha de clientes',
            '',
            'Nome e Telefone são obrigatórios. O telefone (com DDD) identifica o cliente: quem já está cadastrado não é duplicado.',
            'Endereço é opcional; se preencher, precisa de Rua, Número e Bairro. Ele entra como mais um endereço do cliente.',
            'Observações aparecem no PDV quando o telefone é digitado (ex.: alergias, ponto de referência).',
            '',
            '# Clientes que já existem',
            'Ao importar você escolhe: atualizar (nome, e-mail e observações preenchidos na planilha) ou só criar os novos.',
            'Célula em branco não muda nada no cliente que já existe.',
            '',
            '# Privacidade (LGPD)',
            'Importe só clientes que já compraram ou aceitaram receber contato. Quem pedir para sair pode ser apagado na ficha do cliente.',
        ];
    }

    public function current(): iterable
    {
        foreach (Customer::with('addresses')->orderBy('name')->lazy() as $customer) {
            $address = $customer->addresses->first();

            yield [
                $customer->name, Customer::formatPhone($customer->phone), (string) $customer->email, (string) $customer->notes,
                (string) $address?->zip_code, (string) $address?->street, (string) $address?->number, (string) $address?->complement,
                (string) $address?->district, (string) $address?->city, (string) $address?->reference,
            ];
        }
    }

    public function plan(array $rows, array $columns, string $mode): ImportPlan
    {
        $plan = new ImportPlan;
        $existing = Customer::with('addresses')->get()->keyBy('phone');
        $seen = [];

        foreach ($rows as ['line' => $line, 'values' => $v]) {
            $name = mb_substr(Sheet::text($v['name'] ?? null), 0, 200);
            $rawPhone = Sheet::text($v['phone'] ?? null);
            $phone = Customer::normalizePhone($rawPhone);
            $email = mb_strtolower(Sheet::text($v['email'] ?? null));
            $notes = Sheet::text($v['notes'] ?? null);
            $address = array_map(fn ($key) => Sheet::text($v[$key] ?? null), ['zip_code' => 'zip', 'street' => 'street', 'number' => 'number', 'complement' => 'complement', 'district' => 'district', 'city' => 'city', 'reference' => 'reference']);
            $hasAddress = $address['street'] !== '' || $address['number'] !== '' || $address['district'] !== '';

            $errors = array_keys(array_filter([
                'falta o Nome' => $name === '',
                'Nome com mais de 120 caracteres' => mb_strlen($name) > 120,
                'falta o Telefone' => $rawPhone === '',
                "telefone inválido (\"{$rawPhone}\"), use DDD + número" => $rawPhone !== '' && ! Customer::isValidPhone($phone),
                'e-mail inválido' => $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL),
                'Observações com mais de 1000 caracteres' => mb_strlen($notes) > 1000,
                'endereço incompleto: precisa de Rua, Número e Bairro' => $hasAddress && ($address['street'] === '' || $address['number'] === '' || $address['district'] === ''),
                'CEP inválido, use 00000-000' => $address['zip_code'] !== '' && ! preg_match('/^\d{5}-?\d{3}$/', $address['zip_code']),
                'Rua, Bairro ou Cidade com mais de 120 caracteres' => collect(['street', 'district', 'city'])->contains(fn ($k) => mb_strlen($address[$k]) > 120),
                'telefone repetido na linha '.($seen[$phone] ?? '') => $phone !== '' && isset($seen[$phone]),
            ]));

            if ($errors) {
                $plan->errors[] = "Linha {$line}: ".implode('; ', $errors).'.';

                continue;
            }

            $seen[$phone] = $line;
            $data = ['phone' => $phone, 'name' => $name, 'email' => $email ?: null, 'notes' => $notes ?: null, 'address' => $hasAddress ? $address : null];
            $customer = $existing->get($phone);
            $detail = Customer::formatPhone($phone).($hasAddress ? ' · '.$address['street'].', '.$address['number'] : '');

            if (! $customer) {
                $plan->add($line, 'create', $name, $detail, [], $data);

                continue;
            }

            $changes = array_filter([
                $customer->name !== $name ? "nome: {$customer->name} → {$name}" : null,
                $data['email'] && $customer->email !== $data['email'] ? 'e-mail' : null,
                $data['notes'] && $customer->notes !== $data['notes'] ? 'observações' : null,
                $hasAddress && ! $customer->addresses->contains(fn (CustomerAddress $a) => Sheet::plain($a->street) === Sheet::plain($address['street']) && Sheet::plain($a->number) === Sheet::plain($address['number']))
                    ? 'endereço novo' : null,
            ]);

            $action = $mode === 'skip' ? 'skip' : ($changes ? 'update' : 'same');
            $plan->add($line, $action, $name, $detail, array_values($changes), $data + ['id' => $customer->id, 'add_address' => in_array('endereço novo', $changes, true)]);
        }

        return $plan;
    }

    public function apply(ImportPlan $plan, ImportRecorder $recorder, ?User $user): void
    {
        foreach ($plan->rows as ['action' => $action, 'data' => $data]) {
            if ($action === 'create') {
                $customer = Customer::create(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'], 'notes' => $data['notes']]);
                $recorder->track('customer', $customer->id, 'created');
            } elseif ($action === 'update') {
                $customer = Customer::findOrFail($data['id']);
                $recorder->track('customer', $customer->id, 'updated', $customer->only(['name', 'email', 'notes']));
                $customer->fill(array_filter(['name' => $data['name'], 'email' => $data['email'], 'notes' => $data['notes']]))->save();
            } else {
                continue;
            }

            if ($data['address'] && ($action === 'create' || ($data['add_address'] ?? false))) {
                $address = $customer->addresses()->create(array_map(fn ($v) => $v === '' ? null : $v, $data['address']));
                $recorder->track('customer_address', $address->id, 'created');
            }
        }
    }

    public function undo(Collection $items, ?User $user): void
    {
        foreach ($items as $item) {
            match ([$item->subject_type, $item->action]) {
                ['customer_address', 'created'] => CustomerAddress::whereKey($item->subject_id)->delete(),
                ['customer', 'updated'] => Customer::whereKey($item->subject_id)->update($item->before),
                // Cliente que já pediu depois da importação fica (os pedidos apontam para ele).
                ['customer', 'created'] => Order::where('customer_id', $item->subject_id)->exists()
                    ? null
                    : Customer::withTrashed()->whereKey($item->subject_id)->forceDelete(),
                default => null,
            };
        }
    }
}
