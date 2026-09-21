<?php

use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers shared by the whole suite. No test may reach the network: every
| resource takes an injected Guzzle client, so we hand it a MockHandler and
| assert on the recorded request history.
|
*/

/**
 * build a Guzzle client backed by canned responses.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @param string $baseUri base uri the relative endpoints resolve against
 * @return Client
 */
function mockClient(
    array $responses,
    array &$history = [],
    string $baseUri = 'https://sandbox.asaas.com/api/v3/'
): Client {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client([
        'handler'  => $stack,
        'base_uri' => $baseUri,
    ]);
}

/**
 * json response helper.
 *
 * @param array<mixed> $data
 * @param int $status
 * @return Response
 */
function jsonResponse(array $data, int $status = 200): Response
{
    return new Response($status, ['content-type' => 'application/json'], (string) json_encode($data));
}

/**
 * decoded body of a recorded request.
 *
 * @param array<int, mixed> $history
 * @param int $index
 * @return array<mixed>
 */
function recordedBody(array $history, int $index = 0): array
{
    $decoded = json_decode((string) $history[$index]['request']->getBody(), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * mock client already pointed at the Mercado Pago host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function mpClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://api.mercadopago.com/');
}

/**
 * mock client already pointed at the PagBank orders host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function pagbankClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://sandbox.api.pagseguro.com/');
}

/**
 * mock client already pointed at the Pagar.me host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function pagarmeClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://api.pagar.me/core/v5/');
}

/**
 * mock client already pointed at the Cielo sandbox write host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function cieloClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://apisandbox.cieloecommerce.cielo.com.br/');
}

/**
 * mock client already pointed at the Cielo sandbox query host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function cieloQueryClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://apiquerysandbox.cieloecommerce.cielo.com.br/');
}

/**
 * mock client already pointed at the Rede sandbox transactions host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function redeClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://sandbox-erede.useredecloud.com.br/v2/');
}

/**
 * mock client already pointed at the Rede sandbox OAuth host.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, mixed> $history filled with the recorded transactions
 * @return Client
 */
function redeOauthClient(array $responses, array &$history = []): Client
{
    return mockClient($responses, $history, 'https://rl7-sandbox-api.useredecloud.com.br/');
}
