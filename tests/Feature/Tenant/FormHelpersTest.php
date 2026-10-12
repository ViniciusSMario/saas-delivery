<?php

use App\Livewire\Tenant\Establishment\EditEstablishment;
use App\Livewire\Tenant\Orders\Counter;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Livewire\Livewire;

// Máscaras, CEP e confirmação são feitos no navegador (resources/js/masks.js e dialogs.js);
// aqui fica o que depende do servidor: os campos certos nas telas e o CEP chegando ao pedido.

it('o checkout online tem CEP com busca e telefone com máscara', function () {
    ['tenant' => $tenant] = salon();

    asCustomer($tenant, fn () => $this->get(tenantUrl($tenant, '/cardapio/pedido')))
        ->assertOk()
        ->assertSee('name="address[zip_code]" type="text" data-mask="cep" data-cep', false)
        ->assertSee('data-cep-field="street"', false)->assertSee('data-cep-field="district"', false)->assertSee('data-cep-field="city"', false)
        ->assertSee('name="phone" type="tel" data-mask="phone"', false)
        ->assertSee("confirmDialog('Esvaziar a sacola?", false)->assertDontSee("confirm('Esvaziar", false);
});

it('o PDV guarda o CEP do endereço de entrega no pedido e no cadastro do cliente', function () {
    ['tenant' => $tenant, 'burger' => $burger] = salon();
    $caixa = createTenantUser($tenant, ['caixa']);

    $this->actingAs($caixa);
    Livewire::actingAs($caixa)->test(Counter::class)
        ->assertSee('data-mask="phone"', false)
        ->call('addProduct', $burger->id)->set('fulfillment', 'delivery')
        ->assertSee('data-mask="cep" data-cep', false)
        ->set('customerName', 'Rita')->set('customerPhone', '(11) 97777-5555')
        ->set('address.zip_code', '01310-100')->set('address.street', 'Avenida Paulista')->set('address.number', '1000')
        ->set('address.district', 'Bela Vista')->set('address.city', 'São Paulo')
        ->set('paymentMethod', 'cash')
        ->call('checkout')->assertHasNoErrors();

    $order = Order::sole();
    expect($order->only(['address_zip_code', 'address_street', 'address_city', 'customer_phone']))
        ->toBe(['address_zip_code' => '01310-100', 'address_street' => 'Avenida Paulista', 'address_city' => 'São Paulo', 'customer_phone' => '11977775555'])
        ->and(Customer::sole()->addresses()->first()->zip_code)->toBe('01310-100');

    // Próximo pedido do mesmo cliente: o telefone traz o endereço com o CEP.
    Livewire::actingAs($caixa)->test(Counter::class)->set('fulfillment', 'delivery')->set('customerPhone', '11977775555')
        ->assertSet('address.zip_code', '01310-100')->assertSet('address.street', 'Avenida Paulista');
});

it('o cadastro do estabelecimento tem máscara de CNPJ, telefone e CEP com busca', function () {
    ['tenant' => $tenant] = salon();
    $dono = createTenantUser($tenant, ['dono']);

    $this->actingAs($dono);
    Livewire::actingAs($dono)->test(EditEstablishment::class)
        ->assertSee('data-mask="cnpj"', false)->assertSee('data-mask="phone"', false)
        ->assertSee('data-mask="cep" data-cep', false)->assertSee('data-cep-field="state"', false)
        ->set('form.document', '12.345.678/0001-90')->set('form.zip_code', '01310-100')->call('save')->assertHasNoErrors();
});
