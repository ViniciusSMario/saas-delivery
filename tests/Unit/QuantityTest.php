<?php

use App\Support\Quantity;

it('converte o que se digita numa quantidade', function (string|int $input, string $expected) {
    expect(Quantity::parse($input))->toBe($expected);
})->with([
    ['2,5', '2.500'],
    ['0,250', '0.250'],
    // Sem vírgula, o ponto é decimal (teclado numérico do celular): "1.250" kg = 1,25 kg.
    ['1.250', '1.250'],
    ['1.250,5', '1250.500'],
    ['12.5', '12.500'],
    ['3', '3.000'],
    [7, '7.000'],
    ['', '0.000'],
]);

it('recusa quantidades inválidas', function (string $input) {
    expect(Quantity::isValid($input))->toBeFalse();
})->with(['abc', '1,2,3', '--1']);

it('faz contas sem ponto flutuante e formata em pt-BR', function () {
    expect(Quantity::add('0.100', '0.200'))->toBe('0.300')
        ->and(Quantity::mul('0.200', '3'))->toBe('0.600')
        ->and(Quantity::format('1250.500'))->toBe('1.250,5')
        ->and(Quantity::format('2.000'))->toBe('2')
        ->and(Quantity::format('-0.250'))->toBe('-0,25')
        ->and(Quantity::round('4333.3333'))->toBe('4333')
        ->and(Quantity::round('0.5'))->toBe('1');
});
