<?php

use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Http\ClientFactory;

it('dá prazo a todo client que monta', function () {
    $client = ClientFactory::make(['base_uri' => 'https://gateway.test/']);

    expect($client->getConfig('timeout'))->toBe(ClientFactory::TIMEOUT)
        ->and($client->getConfig('connect_timeout'))->toBe(ClientFactory::CONNECT_TIMEOUT)
        ->and((string) $client->getConfig('base_uri'))->toBe('https://gateway.test/');
})->group('phpay');

it('deixa a config do gateway vencer os padrões', function () {
    expect(ClientFactory::make(['timeout' => 5])->getConfig('timeout'))->toBe(5);
})->group('phpay');

it('entrega client com prazo ao recurso que monta o próprio', function () {
    $client = (new ReflectionProperty(Customer::class, 'client'))->getValue(new Customer('token'));

    expect($client->getConfig('timeout'))->toBe(ClientFactory::TIMEOUT);
})->group('phpay');

it('não deixa nenhum gateway montar client fora da fábrica', function () {
    /* o Guzzle espera para sempre por padrão: um client montado à mão nasce sem prazo */
    $files     = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src'));
    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || $file->getFilename() === 'ClientFactory.php') {
            continue;
        }

        if (str_contains((string) file_get_contents($file->getPathname()), 'new Client(')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('phpay');
