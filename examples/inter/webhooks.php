<?php

use PHPay\Exceptions\PHPayException;
use PHPay\Http\Certificate;
use PHPay\Inter\InterGateway;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$gateway = new InterGateway(
    CLIENT_ID,
    CLIENT_SECRET,
    certificate: Certificate::pair(CERTIFICATE, PRIVATE_KEY),
    account: ACCOUNT ?: null,
);

try {
    /*
    | Um webhook por chave Pix, endereçado pela chave — não existe id de
    | webhook. Cadastrar de novo substitui a URL.
    |
    | A notificação chega na URL que você cadastrou. Se você viu por aí que o
    | Inter acrescenta /pix, isso é leitura equivocada da expressão que o Redoc
    | mostra na referência — o sufixo /pix é quirk do Itaú, não do Inter.
    */
    $gateway->webhook([
        'chave'      => PIX_KEY,
        'webhookUrl' => 'https://app.exemplo.com/webhook/',
    ])->create();

    $cadastrado = $gateway->webhook()->find(PIX_KEY);

    echo 'webhook: ' . ($cadastrado['webhookUrl'] ?? '(nenhum)') . PHP_EOL;

    /*
    | O log de entregas é o que diz se uma notificação saiu e o que o seu
    | servidor respondeu: sucesso, httpStatus, mensagemErro, numeroTentativa.
    */
    $entregas = $gateway->webhook()->callbacks();

    echo 'entregas: ' . count($entregas['data'] ?? []) . PHP_EOL;

    /*
    | Lendo a notificação. O corpo do Inter é um ARRAY na raiz, não o
    | {"pix": [...]} do padrão do BACEN — e webhookEvents() cuida disso.
    |
    | Em produção, $corpo vem de file_get_contents('php://input').
    */
    $corpo = (string) json_encode([[
        'endToEndId' => 'E87654321202009091221dfghi123456',
        'txid'       => '971122d8f37211eaadc10242ac120002',
        'valor'      => '1.00',
        'horario'    => gmdate('Y-m-d\TH:i:s\Z'),
    ]]);

    foreach ($gateway->webhookEvents($corpo) as $evento) {
        echo sprintf(
            '%s | %s | %s%s',
            $evento->type->label(),
            $evento->resourceId ?? '-',
            $evento->status?->label() ?? 'precisa consultar',
            PHP_EOL
        );
    }

    /* reenvio, para quando o seu servidor estava fora do ar */
    $gateway->webhook()->retryCallbacks(PIX_KEY, ['971122d8f37211eaadc10242ac120002']);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
