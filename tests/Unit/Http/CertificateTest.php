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
