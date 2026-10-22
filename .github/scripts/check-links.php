<?php

declare(strict_types=1);

/*
 * Confere todo link relativo e toda âncora dos markdowns do repositório.
 *
 * A documentação são 17 arquivos desde que o README foi quebrado em docs/, e o
 * link entre eles é relativo. Link relativo quebra em silêncio: markdown não
 * reclama, o PHPStan não olha e o GitHub só mostra o 404 para quem clicar.
 * Este script é o que reclama.
 *
 * Para cada link que não é http(s), mailto ou tel:
 *   - o arquivo (ou a pasta) apontado precisa existir;
 *   - se o link tem #âncora, algum título daquele arquivo precisa gerar essa
 *     âncora pela regra do GitHub — minúsculas, pontuação fora, espaço vira
 *     hífen, acento fica.
 *
 * Título dentro de bloco de código NÃO conta como título. Foi assim que a
 * cerca não fechada no exemplo da Cielo passou meses escondida: o `### Rede`
 * virou código, e dez links do sumário ficaram apontando para âncoras que não
 * existiam mais. Um verificador que ignorasse as cercas diria que estava tudo
 * bem.
 *
 * Uso: php .github/scripts/check-links.php  (ou composer test:links)
 */

$raiz  = dirname(__DIR__, 2);
$pular = ['vendor', 'node_modules', '.git', '.claude'];

/**
 * âncora que o GitHub gera para um título.
 *
 * @param string $titulo
 * @return string
 */
function ancoraDe(string $titulo): string
{
    $texto = trim($titulo);
    $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
    $texto = (string) preg_replace('/[`*_]/u', '', $texto);
    $texto = (string) preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $texto);

    return str_replace(' ', '-', trim($texto));
}

/**
 * linhas do arquivo que estão fora de bloco de código.
 *
 * @param string $arquivo
 * @return array<int, string> número da linha => conteúdo
 */
function linhasForaDeBloco(string $arquivo): array
{
    $fora  = [];
    $cerca = false;

    foreach (explode("\n", (string) file_get_contents($arquivo)) as $i => $linha) {
        if (str_starts_with(ltrim($linha), '```')) {
            $cerca = !$cerca;

            continue;
        }

        if (!$cerca) {
            $fora[$i + 1] = $linha;
        }
    }

    return $fora;
}

/**
 * âncoras que os títulos daquele arquivo geram.
 *
 * @param string $arquivo
 * @return array<int, string>
 */
function ancorasDe(string $arquivo): array
{
    $ancoras = [];

    foreach (linhasForaDeBloco($arquivo) as $linha) {
        if (preg_match('/^#{1,6} (.+)$/u', $linha, $m) === 1) {
            $ancoras[] = ancoraDe($m[1]);
        }
    }

    return $ancoras;
}

/* todo markdown do repositório, menos o que não é nosso */
$arquivos = [];
$arvore   = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $item): bool => !in_array($item->getFilename(), $pular, true)
    )
);

foreach ($arvore as $item) {
    if ($item instanceof SplFileInfo && $item->getExtension() === 'md') {
        $arquivos[] = $item->getPathname();
    }
}

sort($arquivos);

$cache     = [];
$conferidos = 0;
$quebrados = [];

foreach ($arquivos as $arquivo) {
    foreach (linhasForaDeBloco($arquivo) as $numero => $linha) {
        if (preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/u', $linha, $achados) === 0) {
            continue;
        }

        foreach ($achados[1] as $destino) {
            if (preg_match('#^(https?|mailto|tel):#i', $destino) === 1) {
                continue;
            }

            $conferidos++;
            [$caminho, $ancora] = array_pad(explode('#', $destino, 2), 2, '');
            $relativo           = str_replace($raiz . '/', '', $arquivo);

            if ($caminho === '') {
                $alvo = $arquivo;
            } else {
                $alvo = realpath(dirname($arquivo) . '/' . $caminho);

                if ($alvo === false) {
                    $quebrados[] = sprintf('%s:%d  %s  — não existe', $relativo, $numero, $destino);

                    continue;
                }

                if (!str_starts_with($alvo, $raiz)) {
                    $quebrados[] = sprintf('%s:%d  %s  — sai do repositório', $relativo, $numero, $destino);

                    continue;
                }
            }

            if ($ancora === '' || !str_ends_with($alvo, '.md')) {
                continue;
            }

            $cache[$alvo] ??= ancorasDe($alvo);

            if (!in_array($ancora, $cache[$alvo], true)) {
                $quebrados[] = sprintf(
                    '%s:%d  %s  — nenhum título gera essa âncora em %s',
                    $relativo,
                    $numero,
                    $destino,
                    str_replace($raiz . '/', '', $alvo)
                );
            }
        }
    }
}

printf("%d arquivos, %d links relativos conferidos\n", count($arquivos), $conferidos);

if ($quebrados !== []) {
    printf("\n%d quebrado(s):\n", count($quebrados));

    foreach ($quebrados as $quebrado) {
        printf("  %s\n", $quebrado);
    }

    exit(1);
}

echo "nenhum link quebrado\n";
