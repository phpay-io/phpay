<?php

namespace PHPay\Http;

use PHPay\Exceptions\ValidationException;

/**
 * client certificate for mutual TLS.
 *
 * the BACEN standard for Pix APIs requires mTLS on every request, the token
 * one included: the PSP only talks to whoever presents the certificate it
 * issued. Efí is the first gateway here that needs it, and Inter, BB, Itaú,
 * Sicoob and Sicredi follow the same scheme — which is why this lives in Http
 * and not inside a gateway.
 *
 * the passphrase never shows up in a dump: __debugInfo() masks it.
 */
final class Certificate
{
    /**
     * formats Guzzle hands to cURL by extension, without extra options
     */
    private const FORMATS = ['p12', 'pem'];

    /**
     * construct
     *
     * @param string $path path to the .p12 or .pem file issued by the PSP
     * @param string|null $passphrase only when the private key is encrypted
     * @throws ValidationException
     */
    public function __construct(
        private string $path,
        #[\SensitiveParameter]
        private ?string $passphrase = null,
    ) {
        $messages = self::messages();

        if (!is_file($path) || !is_readable($path)) {
            throw ValidationException::make('Certificado', sprintf($messages->notFound, $path));
        }

        if (!in_array(self::extensionOf($path), self::FORMATS, true)) {
            throw ValidationException::make('Certificado', $messages->format);
        }
    }

    /**
     * build a certificate from its base64 content.
     *
     * for containers and serverless, where the certificate travels in an
     * environment variable instead of a file. the content is written to a
     * private temporary file (0600) removed when the process ends.
     *
     * @param string $content base64 of the .p12 or .pem file
     * @param string|null $passphrase
     * @param string $format 'p12' or 'pem'
     * @return self
     * @throws ValidationException
     */
    public static function fromBase64(
        #[\SensitiveParameter]
        string $content,
        #[\SensitiveParameter]
        ?string $passphrase = null,
        string $format = 'p12',
    ): self {
        $messages = self::messages();
        $format   = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::make('Certificado', $messages->format);
        }

        $bytes = base64_decode($content, true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::make('Certificado', $messages->base64);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'phpay-cert-');

        if ($temporary === false) {
            throw ValidationException::make('Certificado', $messages->temporary);
        }

        // tempnam() creates the file as 0600; rename() keeps the mode and adds
        // the extension Guzzle reads to tell cURL the format.
        $path = "{$temporary}.{$format}";

        if (!rename($temporary, $path) || file_put_contents($path, $bytes, LOCK_EX) === false) {
            throw ValidationException::make('Certificado', $messages->temporary);
        }

        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return new self($path, $passphrase);
    }

    /**
     * path to the certificate file
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 'p12' or 'pem'
     *
     * @return string
     */
    public function format(): string
    {
        return self::extensionOf($this->path);
    }

    /**
     * the Guzzle request options that present this certificate.
     *
     * @return array{cert: string|array{0: string, 1: string}}
     */
    public function guzzleOptions(): array
    {
        return [
            'cert' => $this->passphrase === null ? $this->path : [$this->path, $this->passphrase],
        ];
    }

    /**
     * keep the passphrase out of var_dump() and print_r().
     *
     * @return array{path: string, passphrase: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'path'       => $this->path,
            'passphrase' => $this->passphrase === null ? null : '********',
        ];
    }

    /**
     * messages for validation
     *
     * @return object{notFound: string, format: string, base64: string, temporary: string}
     */
    public static function messages(): object
    {
        return (object) [
            'notFound'  => 'arquivo não encontrado ou sem permissão de leitura em %s.',
            'format'    => 'use o arquivo .p12 ou .pem emitido pelo PSP. Um .pfx é o mesmo formato do .p12: basta renomear.',
            'base64'    => 'o conteúdo informado não é um base64 válido.',
            'temporary' => 'não foi possível gravar o certificado em um arquivo temporário.',
        ];
    }

    /**
     * lowercase extension of a path
     *
     * @param string $path
     * @return string
     */
    private static function extensionOf(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
}
