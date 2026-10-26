<?php

declare(strict_types = 1);

/*
 * Gera — e confere — a matriz de capacidades do README.
 *
 * A tabela tem dez gateways por oito capacidades: 80 células que ninguém
 * revisa. Escrita à mão, ela deriva, e derivou: ficou duas releases sem as
 * colunas de estornos e de leitura de webhook, desde que as capacidades
 * entraram nas v2.5 e v2.6.
 *
 * Por isso a fonte é o código: Gateway::cases() × Capability::cases(), com o
 * label() que cada enum já expõe. Nenhum rótulo é duplicado aqui — rótulo novo
 * na enum aparece na tabela sozinho.
 *
 * Uso:
 *   php .github/scripts/capability-matrix.php            # imprime a tabela
 *   php .github/scripts/capability-matrix.php --write    # reescreve no README
 *   php .github/scripts/capability-matrix.php --check    # reprova se divergir
 */

require __DIR__ . '/../../vendor/autoload.php';

use PHPay\Contracts\Capability;
use PHPay\PHPay;
use PHPay\Support\Gateway;

const README  = __DIR__ . '/../../README.md';
const INICIO  = '<!-- capacidades:inicio -->';
const FIM     = '<!-- capacidades:fim -->';

/*
 * O label() do enum dá o nome curto. Onde o README carrega informação que o
 * nome curto perde, ela fica aqui — a Woovi comprou a OpenPix, e quem procura
 * pelo nome antigo precisa se achar na tabela.
 */
const NOMES = [
    'woovi' => 'Woovi/OpenPix',
];

/**
 * a matriz, montada a partir do código.
 *
 * @return string
 */
function matriz(): string
{
    $capacidades = Capability::cases();

    $cabecalho = array_map(
        static fn (Capability $c): string => ucfirst($c->label()),
        $capacidades
    );

    $linhas = [
        '| Gateway | ' . implode(' | ', $cabecalho) . ' |',
        '| --- |' . str_repeat(' :---: |', count($capacidades)),
    ];

    foreach (Gateway::cases() as $gateway) {
        /* credencial falsa: nada aqui sai para a rede, nem no construtor */
        $phpay = PHPay::make($gateway, array_fill_keys($gateway->credentials(), 'x'));
        $nome  = NOMES[$gateway->value] ?? $gateway->label();

        $celulas = array_map(
            static fn (Capability $c): string => $phpay->supports($c) ? '✅' : '—',
            $capacidades
        );

        $linhas[] = sprintf('| **%s** | %s |', $nome, implode(' | ', $celulas));
    }

    return implode("\n", $linhas);
}

/**
 * o trecho do README entre os marcadores.
 *
 * @param string $conteudo
 * @return string
 */
function trechoAtual(string $conteudo): string
{
    $inicio = strpos($conteudo, INICIO);
    $fim    = strpos($conteudo, FIM);

    if ($inicio === false || $fim === false) {
        fwrite(STDERR, sprintf(
            "Os marcadores %s e %s não estão no README.\n",
            INICIO,
            FIM
        ));

        exit(1);
    }

    return trim(substr($conteudo, $inicio + strlen(INICIO), $fim - $inicio - strlen(INICIO)));
}

$modo     = $argv[1] ?? '';
$esperada = matriz();

if ($modo === '') {
    echo $esperada, "\n";

    exit(0);
}

$conteudo = (string) file_get_contents(README);
$atual    = trechoAtual($conteudo);

if ($modo === '--check') {
    if ($atual === $esperada) {
        printf("a matriz do README confere com o código (%d gateways, %d capacidades)\n", count(Gateway::cases()), count(Capability::cases()));

        exit(0);
    }

    fwrite(STDERR, "A matriz do README divergiu do código.\n\nO código diz:\n\n{$esperada}\n\n");
    fwrite(STDERR, "Rode: php .github/scripts/capability-matrix.php --write\n");

    exit(1);
}

if ($modo === '--write') {
    if ($atual === $esperada) {
        echo "a matriz já estava correta\n";

        exit(0);
    }

    $novo = substr($conteudo, 0, (int) strpos($conteudo, INICIO) + strlen(INICIO))
        . "\n" . $esperada . "\n"
        . substr($conteudo, (int) strpos($conteudo, FIM));

    file_put_contents(README, $novo);

    echo "matriz do README atualizada a partir do código\n";

    exit(0);
}

fwrite(STDERR, "Modo desconhecido: {$modo}. Use --write ou --check.\n");

exit(1);
