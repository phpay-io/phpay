<?php

use PHPay\AbacatePay\Requests\AbacatePayCustomerRequest;
use PHPay\Asaas\Requests\AsaasCustomerRequest;
use PHPay\Cielo\Requests\CieloSaleRequest;
use PHPay\Efi\Requests\EfiCustomerRequest;
use PHPay\MercadoPago\Requests\MercadoPagoCustomerRequest;
use PHPay\PagarMe\Requests\PagarMeCustomerRequest;
use PHPay\PagBank\Requests\PagBankCustomerRequest;
use PHPay\Support\Customer;
use PHPay\Woovi\Requests\WooviCustomerRequest;

/*
| O ponto do value object: o MESMO cliente, escrito uma vez, vira o formato de
| cada gateway. Sem ele, quem sai do Asaas para o Pagar.me reescreve cpfCnpj
| como document, e nada no tipo avisa.
*/

function clienteExemplo(): Customer
{
    return Customer::make(
        name: 'Mário Lucas',
        document: '123.456.789-01',
        email: 'fale@phpay.io',
        phone: '(11) 94002-8922',
    );
}

it('mapeia o mesmo cliente para as oito grafias de documento', function () {
    $cliente = clienteExemplo();

    expect(AsaasCustomerRequest::fromCustomer($cliente)['cpfCnpj'])->toBe('12345678901')
        ->and(PagBankCustomerRequest::fromCustomer($cliente)['tax_id'])->toBe('12345678901')
        ->and(PagarMeCustomerRequest::fromCustomer($cliente)['document'])->toBe('12345678901')
        ->and(AbacatePayCustomerRequest::fromCustomer($cliente)['taxId'])->toBe('12345678901')
        ->and(WooviCustomerRequest::fromCustomer($cliente)['taxID'])->toBe('12345678901')
        ->and(EfiCustomerRequest::fromCustomer($cliente)['cpf_cnpj'])->toBe('12345678901')
        ->and(CieloSaleRequest::fromCustomer($cliente)['Identity'])->toBe('12345678901')
        ->and(MercadoPagoCustomerRequest::fromCustomer($cliente)['identification']['number'])
        ->toBe('12345678901');
})->group('support');

it('respeita a forma que cada gateway espera, não só o nome do campo', function () {
    $cliente = clienteExemplo();

    /* o pagbank quer o telefone quebrado */
    expect(PagBankCustomerRequest::fromCustomer($cliente)['phones'][0])
        ->toBe(['country' => '55', 'area' => '11', 'number' => '940028922', 'type' => 'MOBILE']);

    /* o pagar.me quer o tipo derivado do documento */
    expect(PagarMeCustomerRequest::fromCustomer($cliente)['type'])->toBe('individual');

    /* o mercado pago quer o nome separado */
    expect(MercadoPagoCustomerRequest::fromCustomer($cliente))
        ->toMatchArray(['first_name' => 'Mário', 'last_name' => 'Lucas']);

    /* a cielo é a única que usa maiúscula */
    expect(CieloSaleRequest::fromCustomer($cliente))
        ->toMatchArray(['Name' => 'Mário Lucas', 'IdentityType' => 'CPF']);

    /* abacate chama o telefone de cellphone; woovi, de phone */
    expect(AbacatePayCustomerRequest::fromCustomer($cliente)['cellphone'])->toBe('11940028922')
        ->and(WooviCustomerRequest::fromCustomer($cliente)['phone'])->toBe('11940028922');
})->group('support');

it('deriva company do CNPJ', function () {
    $empresa = Customer::make('Sixtec LTDA', '12.345.678/0001-99');

    expect(PagarMeCustomerRequest::fromCustomer($empresa)['type'])->toBe('company')
        ->and(CieloSaleRequest::fromCustomer($empresa)['IdentityType'])->toBe('CNPJ');
})->group('support');

it('omite o que não foi informado em vez de mandar null', function () {
    $minimo = new Customer('Mário Lucas');

    expect(AsaasCustomerRequest::fromCustomer($minimo))->toBe(['name' => 'Mário Lucas'])
        ->and(PagBankCustomerRequest::fromCustomer($minimo))->not->toHaveKey('phones');
})->group('support');

it('carrega campos específicos de um gateway pelo escape hatch', function () {
    $cliente = clienteExemplo()->withExtra(['externalReference' => 'cliente-42']);

    expect(AsaasCustomerRequest::fromCustomer($cliente))
        ->toHaveKey('externalReference')
        ->and(AsaasCustomerRequest::fromCustomer($cliente)['externalReference'])->toBe('cliente-42');
})->group('support');

it('funciona de ponta a ponta na cobrança', function () {
    $history = [];

    (new PHPay\PagBank\Resources\Charge\Charge('token', true, mockClient([jsonResponse([])], $history)))
        ->setCustomer(clienteExemplo())
        ->addItem('Item', PHPay\Support\Money::reais(100.50))
        ->setQrCode(PHPay\Support\Money::reais(100.50))
        ->create();

    $enviado = recordedBody($history)['customer'];

    expect($enviado['tax_id'])->toBe('12345678901')
        ->and($enviado['name'])->toBe('Mário Lucas')
        ->and($enviado['phones'][0]['area'])->toBe('11');
})->group('support');
