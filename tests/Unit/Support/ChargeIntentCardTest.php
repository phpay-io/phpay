<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\ClientFactory;
use PHPay\PHPay;
use PHPay\Support\{Card, ChargeIntent, Customer, Method, Money};

/*
| A fase 3 fecha os dez — mas não fecha "cartão em todos". Dois gateways
| aceitam o número no servidor; os outros querem token ou blob cifrado, e por
| isso simplesmente não declaram o método.
*/

afterEach(function () {
    ClientFactory::stopFaking();
});

function cartaoDeTeste(?string $bandeira = null): Card
{
    return Card::make('4111 1111 1111 1111', 'MARIA OLIVEIRA', '07', '2030', '123', $bandeira);
}

function clienteSimples(): Customer
{
    return Customer::make(name: 'Maria Oliveira', document: '123.456.789-09', email: 'maria@exemplo.com');
}

it('a cielo faz os três meios com o mesmo intent, mudando só a fábrica', function () {
    $esperado = ['Pix', 'Boleto', 'CreditCard'];
    $tipos    = [];

    foreach ([
        ChargeIntent::pix(Money::reais('149,90'), clienteSimples()),
        ChargeIntent::boleto(Money::reais('149,90'), clienteSimples(), '2099-11-10'),
        ChargeIntent::credito(Money::reais('149,90'), clienteSimples(), cartaoDeTeste('Visa'), 3),
    ] as $intent) {
        ClientFactory::fake([new Response(200, [], '{"Payment":{"PaymentId":"1"}}')]);

        PHPay::make('cielo', ['merchantId' => 'i', 'merchantKey' => 'k'])->createCharge($intent);

        $tipos[] = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true)['Payment']['Type'];

        ClientFactory::stopFaking();
    }

    expect($tipos)->toBe($esperado);
})->group('support');

it('manda o cartão para a cielo com as chaves dela e as parcelas', function () {
    ClientFactory::fake([new Response(200, [], '{"Payment":{"PaymentId":"1"}}')]);

    PHPay::make('cielo', ['merchantId' => 'i', 'merchantKey' => 'k'])->createCharge(
        ChargeIntent::credito(Money::reais('149,90'), clienteSimples(), cartaoDeTeste('Visa'), 3)
    );

    $pagamento = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true)['Payment'];

    expect($pagamento['CreditCard']['CardNumber'])->toBe('4111111111111111')
        ->and($pagamento['CreditCard']['ExpirationDate'])->toBe('07/2030')
        ->and($pagamento['CreditCard']['Brand'])->toBe('Visa')
        ->and($pagamento['Installments'])->toBe(3);
})->group('support');

it('a cielo recusa cartão sem bandeira, em vez de adivinhar pela faixa do número', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('cielo', ['merchantId' => 'i', 'merchantKey' => 'k'])
        ->createCharge(ChargeIntent::credito(Money::reais('149,90'), clienteSimples(), cartaoDeTeste())))
        ->toThrow(ValidationException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('manda o cartão para a rede com os campos planos dela', function () {
    ClientFactory::fake([
        new Response(200, [], '{"access_token":"t","expires_in":3600}'),
        new Response(200, [], '{"tid":"1","returnCode":"00"}'),
    ]);

    PHPay::make('rede', ['affiliation' => 'pv', 'secret' => 's'])->createCharge(
        ChargeIntent::credito(Money::reais('149,90'), clienteSimples(), cartaoDeTeste(), 3)
    );

    $gravadas = ClientFactory::recorded();
    $corpo    = json_decode((string) $gravadas[count($gravadas) - 1]['request']->getBody(), true);

    expect($corpo['cardNumber'])->toBe('4111111111111111')
        ->and($corpo['expirationMonth'])->toBe('07')
        ->and($corpo['kind'])->toBe('credit')
        ->and($corpo['installments'])->toBe(3)
        ->and($corpo['amount'])->toBe(14990);
})->group('support');

it('a rede recusa pix nomeando o que faz, antes de qualquer HTTP', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('rede', ['affiliation' => 'pv', 'secret' => 's'])
        ->createCharge(ChargeIntent::pix(Money::reais('10,00'))))
        ->toThrow(ValidationException::class, 'Aceita: cartão de crédito.')
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('o boleto da efí é o charge(), não o pixCharge()', function () {
    ClientFactory::fake([
        new Response(200, [], '{"access_token":"t","token_type":"Bearer","expires_in":3600}'),
        new Response(200, [], '{"data":{"charge_id":1}}'),
    ]);

    PHPay::make('efi', ['clientId' => 'i', 'clientSecret' => 's'])->createCharge(
        ChargeIntent::boleto(Money::reais('149,90'), clienteSimples(), '2099-11-10', 'Plano anual')
    );

    $gravadas = ClientFactory::recorded();
    $corpo    = json_decode((string) $gravadas[count($gravadas) - 1]['request']->getBody(), true);

    /* centavos aqui, enquanto a API Pix do MESMO gateway quer reais em string */
    expect($corpo['items'][0]['value'])->toBe(14990)
        ->and($corpo['payment']['banking_billet']['expire_at'])->toBe('2099-11-10');
})->group('support');

it('a efí recusa pix no caminho portável, porque lá charge() é o boleto', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('efi', ['clientId' => 'i', 'clientSecret' => 's'])
        ->createCharge(ChargeIntent::pix(Money::reais('10,00'))))
        ->toThrow(ValidationException::class, 'Aceita: boleto.')
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('o boleto exige vencimento, e o pix não', function () {
    expect(ChargeIntent::pix(Money::reais('10,00'))->dueDate)->toBeNull()
        ->and(ChargeIntent::boleto(Money::reais('10,00'), clienteSimples(), '2099-11-10')->dueDate)
        ->toBe('2099-11-10');
})->group('support');

it('recusa parcela zero', function () {
    expect(fn () => ChargeIntent::credito(Money::reais('10,00'), clienteSimples(), cartaoDeTeste('Visa'), 0))
        ->toThrow(ValidationException::class, ChargeIntent::messages()->installments);
})->group('support');

it('nenhum dos dez faz débito pelo caminho portável, e nenhum finge que faz', function () {
    foreach (['cielo', 'rede', 'asaas', 'pagarme'] as $nome) {
        $config = match ($nome) {
            'cielo'   => ['merchantId' => 'i', 'merchantKey' => 'k'],
            'rede'    => ['affiliation' => 'pv', 'secret' => 's'],
            'asaas'   => ['token' => 't'],
            'pagarme' => ['secretKey' => 'sk_test_1'],
        };

        expect(PHPay::make($nome, $config)->supportedMethods())->not->toContain(Method::DEBIT_CARD);
    }
})->group('support');
