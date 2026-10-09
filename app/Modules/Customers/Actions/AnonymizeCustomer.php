<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Access\Audit;
use App\Modules\Access\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Pedido de exclusão (LGPD): apaga os dados pessoais do cliente e dos pedidos dele,
 * mantendo os pedidos (valores e itens) para a contabilidade.
 */
class AnonymizeCustomer
{
    public function handle(Customer $customer, ?User $user): void
    {
        DB::connection('tenant')->transaction(function () use ($customer, $user) {
            Order::where('customer_id', $customer->id)->update([
                'customer_name' => 'Cliente removido',
                'customer_phone' => null,
                'address_zip_code' => null,
                'address_street' => null,
                'address_number' => null,
                'address_complement' => null,
                'address_district' => null,
                'address_city' => null,
                'address_reference' => null,
            ]);

            $customer->addresses()->delete();
            $customer->forceFill([
                'name' => 'Cliente removido',
                'phone' => 'anon-'.$customer->id,
                'email' => null,
                'notes' => null,
                'anonymized_at' => now(),
            ])->save();
            $customer->delete();

            Audit::record('customer.anonymized', $customer, ['cliente' => '#'.$customer->id], $user);
        });
    }
}
