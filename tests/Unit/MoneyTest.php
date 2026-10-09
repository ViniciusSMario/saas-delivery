<?php

use App\Support\Money;

it('converte o que se digita num campo de preço', function (string|int $input, int $cents) {
    expect(Money::parse($input)->cents)->toBe($cents);
})->with([
    ['39,90', 3990],
    ['39,9', 3990],
    ['39', 3900],
    ['1.234,56', 123456],
    ['1.234', 123400],
    ['12.5', 1250],
    ['R$ 9,90', 990],
    ['0,05', 5],
    [7, 700],
    ['', 0],
]);

it('recusa valores inválidos', function (string $input) {
    expect(Money::isValid($input))->toBeFalse();
})->with(['abc', '1,234,56', '12,345', '1.23.4', '--1']);

it('formata em reais', function () {
    expect(Money::cents(123456)->format())->toBe('R$ 1.234,56')
        ->and(Money::cents(5)->decimal())->toBe('0,05')
        ->and(Money::cents(-990)->format())->toBe('-R$ 9,90');
});

it('soma, subtrai e multiplica sem ponto flutuante', function () {
    $a = Money::parse('0,10');
    $total = $a->add(Money::parse('0,20'));

    expect($total->cents)->toBe(30)
        ->and(Money::parse('19,99')->multiply(3)->cents)->toBe(5997)
        ->and(Money::parse('10')->subtract(Money::parse('2,50'))->format())->toBe('R$ 7,50');
});
