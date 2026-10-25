<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Http\Certificate;

/**
 * a file with the given extension in the system temp dir.
 *
 * the Certificate does not parse the file — cURL does, at request time — so
 * any content works here.
 *
 * @param string $extension
 * @return string
 */
function certificateFile(string $extension = 'p12'): string
{
    $path = sys_get_temp_dir() . '/phpay-test-' . bin2hex(random_bytes(6)) . ".{$extension}";
    file_put_contents($path, 'conteúdo do certificado');

    return $path;
}

it('aceita certificado .p12 e .pem', function (string $extension) {
    $path = certificateFile($extension);

    expect((new Certificate($path))->format())->toBe($extension)
        ->and((new Certificate($path))->path())->toBe($path);

    unlink($path);
})->with(['p12', 'pem'])->group('support');

it('entrega ao Guzzle o caminho, com a senha só quando houver', function () {
    $path = certificateFile();

    expect((new Certificate($path))->guzzleOptions())->toBe(['cert' => $path])
        ->and((new Certificate($path, 'segredo'))->guzzleOptions())->toBe(['cert' => [$path, 'segredo']]);

    unlink($path);
})->group('support');

it('recusa certificado que não existe', function () {
    expect(fn () => new Certificate('/nao/existe/certificado.p12'))
        ->toThrow(ValidationException::class, 'arquivo não encontrado');
})->group('support');

it('recusa formato que o cURL não reconhece pela extensão', function () {
    $path = certificateFile('pfx');

    expect(fn () => new Certificate($path))
        ->toThrow(ValidationException::class, 'basta renomear');

    unlink($path);
})->group('support');

it('monta o certificado a partir de base64, num arquivo privado', function () {
    $certificate = Certificate::fromBase64(base64_encode('bytes do p12'), 'segredo');

    expect($certificate->format())->toBe('p12')
        ->and(file_get_contents($certificate->path()))->toBe('bytes do p12')
        ->and(fileperms($certificate->path()) & 0777)->toBe(0600)
        ->and($certificate->guzzleOptions()['cert'])->toBe([$certificate->path(), 'segredo']);
})->group('support');

it('recusa base64 inválido', function () {
    expect(fn () => Certificate::fromBase64('isto não é base64!'))
        ->toThrow(ValidationException::class, 'base64 válido');
})->group('support');

it('não expõe a senha num dump', function () {
    $path = certificateFile();

    $dump = print_r(new Certificate($path, 'senha-super-secreta'), true);

    expect($dump)->not->toContain('senha-super-secreta')
        ->and($dump)->toContain('********');

    unlink($path);
})->group('support');

it('aceita certificado e chave em arquivos separados, como o Inter entrega', function () {
    $crt = certificateFile('crt');
    $key = certificateFile('key');

    $certificate = Certificate::pair($crt, $key);

    expect($certificate->isPair())->toBeTrue()
        ->and($certificate->path())->toBe($crt)
        ->and($certificate->keyPath())->toBe($key)
        ->and($certificate->format())->toBe('crt');

    unlink($crt);
    unlink($key);
})->group('support');

it('arquivo único não é par', function () {
    $path = certificateFile();

    expect((new Certificate($path))->isPair())->toBeFalse()
        ->and((new Certificate($path))->keyPath())->toBeNull();

    unlink($path);
})->group('support');

it('entrega o par ao Guzzle como cert e ssl_key', function () {
    $crt = certificateFile('crt');
    $key = certificateFile('key');

    expect(Certificate::pair($crt, $key)->guzzleOptions())
        ->toBe(['cert' => $crt, 'ssl_key' => $key]);

    unlink($crt);
    unlink($key);
})->group('support');

it('num par a senha vai na chave, nunca no certificado', function () {
    /*
     * é o detalhe que erra em silêncio: Guzzle transforma o segundo elemento de
     * `cert` em CURLOPT_SSLCERTPASSWD, e num par o .crt é público — não tem o
     * que decifrar. A senha protege a chave.
     */
    $crt = certificateFile('crt');
    $key = certificateFile('key');

    $opcoes = Certificate::pair($crt, $key, 'segredo')->guzzleOptions();

    expect($opcoes)->toBe(['cert' => $crt, 'ssl_key' => [$key, 'segredo']])
        ->and($opcoes['cert'])->toBeString();

    unlink($crt);
    unlink($key);
})->group('support');

it('recusa certificado sozinho quando o PSP entregou dois arquivos', function () {
    /* a mensagem tem de apontar a saída, senão o .crt do Inter só dá "formato" */
    $crt = certificateFile('crt');

    expect(fn () => new Certificate($crt))
        ->toThrow(ValidationException::class, 'Certificate::pair()');

    unlink($crt);
})->group('support');

it('recusa metade de par com extensão que o cURL leria errado', function () {
    $crt = certificateFile('crt');
    $key = certificateFile('key');
    $p12 = certificateFile('p12');

    expect(fn () => Certificate::pair($p12, $key))
        ->toThrow(ValidationException::class, '.crt, .cer ou .pem')
        ->and(fn () => Certificate::pair($crt, $p12))
        ->toThrow(ValidationException::class, '.key ou .pem');

    unlink($crt);
    unlink($key);
    unlink($p12);
})->group('support');

it('recusa chave privada que não existe', function () {
    $crt = certificateFile('crt');

    expect(fn () => Certificate::pair($crt, '/nao/existe/chave.key'))
        ->toThrow(ValidationException::class, 'chave privada não encontrada');

    unlink($crt);
})->group('support');

it('monta o par a partir de base64, em dois arquivos privados', function () {
    $certificate = Certificate::pairFromBase64(
        base64_encode('bytes do crt'),
        base64_encode('bytes da key'),
        'segredo',
    );

    $key = $certificate->keyPath();

    expect($key)->toBeString()
        ->and(file_get_contents($certificate->path()))->toBe('bytes do crt')
        ->and(file_get_contents((string) $key))->toBe('bytes da key')
        ->and(fileperms($certificate->path()) & 0777)->toBe(0600)
        ->and(fileperms((string) $key) & 0777)->toBe(0600)
        ->and($certificate->guzzleOptions())
        ->toBe(['cert' => $certificate->path(), 'ssl_key' => [$key, 'segredo']]);
})->group('support');

it('recusa base64 inválido em qualquer metade do par', function () {
    expect(fn () => Certificate::pairFromBase64('não é base64!', base64_encode('ok')))
        ->toThrow(ValidationException::class, 'base64 válido')
        ->and(fn () => Certificate::pairFromBase64(base64_encode('ok'), 'não é base64!'))
        ->toThrow(ValidationException::class, 'base64 válido');
})->group('support');

it('o dump do par mostra os dois caminhos e esconde a senha', function () {
    $crt = certificateFile('crt');
    $key = certificateFile('key');

    $dump = print_r(Certificate::pair($crt, $key, 'senha-super-secreta'), true);

    expect($dump)->not->toContain('senha-super-secreta')
        ->and($dump)->toContain('********')
        ->and($dump)->toContain($crt)
        ->and($dump)->toContain($key);

    unlink($crt);
    unlink($key);
})->group('support');
