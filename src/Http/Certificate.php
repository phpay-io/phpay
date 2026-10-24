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
 * the PSPs disagree on how they hand it over. Efí issues one `.p12` with the
 * certificate and the private key inside; Inter issues a `.crt` and a `.key`
 * separately, and tells you to run `openssl pkcs12 -export` to join them. Both
 * shapes are accepted here — `new Certificate()` for a single bundled file and
 * `Certificate::pair()` for the two halves — because cURL speaks both and
 * asking for a conversion step is friction the library can absorb.
 *
 * the passphrase never shows up in a dump: __debugInfo() masks it.
 */
final class Certificate
{
    /**
     * formats cURL recognizes by extension when certificate and key travel in
     * the same file
     */
    private const FORMATS = ['p12', 'pem'];

    /**
     * the certificate half of a pair. all PEM-encoded, which is cURL's default
     * for the certificate when the extension says nothing — so no type option
     * is needed, and that matters: naming `CURLOPT_SSLCERTTYPE` in `curl` is
     * refused by recent Guzzle as conflicting with its own `cert` option.
     */
    private const PAIR_CERT_FORMATS = ['crt', 'cer', 'pem'];

    /**
     * the private key half of a pair, also PEM
     */
    private const PAIR_KEY_FORMATS = ['key', 'pem'];

    /**
     * construct
     *
     * @param string $path path to the .p12 or .pem file issued by the PSP, or
     *                     to the certificate half when $key is given
     * @param string|null $passphrase only when the private key is encrypted
     * @param string|null $key path to the private key, when the PSP issued it
     *                         in its own file. prefer Certificate::pair(),
     *                         which reads in the order the files come in.
     * @throws ValidationException
     */
    public function __construct(
        private string $path,
        #[\SensitiveParameter]
        private ?string $passphrase = null,
        private ?string $key = null,
    ) {
        $messages = self::messages();

        self::requireReadable($path, $messages->notFound);

        if ($key === null) {
            if (!in_array(self::extensionOf($path), self::FORMATS, true)) {
                throw ValidationException::make('Certificado', $messages->format);
            }

            return;
        }

        self::requireReadable($key, $messages->keyNotFound);

        if (!in_array(self::extensionOf($path), self::PAIR_CERT_FORMATS, true)) {
            throw ValidationException::make('Certificado', $messages->pairCertificate);
        }

        if (!in_array(self::extensionOf($key), self::PAIR_KEY_FORMATS, true)) {
            throw ValidationException::make('Certificado', $messages->pairKey);
        }
    }

    /**
     * build a certificate from the two files a PSP issued separately.
     *
     * this is the Inter shape: `Inter API_Certificado.crt` plus
     * `Inter API_Chave.key`. The documentation of the PSP itself tells you to
     * join them with `openssl pkcs12 -export`, and that works — but cURL
     * accepts the halves as they are, so there is nothing to convert.
     *
     * the passphrase here protects the **key**, which is where a passphrase can
     * exist at all: the certificate half is public.
     *
     * @param string $certificate path to the .crt, .cer or .pem certificate
     * @param string $key path to the .key or .pem private key
     * @param string|null $passphrase only when the private key is encrypted
     * @return self
     * @throws ValidationException
     */
    public static function pair(
        string $certificate,
        string $key,
        #[\SensitiveParameter]
        ?string $passphrase = null,
    ): self {
        return new self($certificate, $passphrase, $key);
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
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::make('Certificado', self::messages()->format);
        }

        return new self(self::temporaryFile(self::decode($content), $format), $passphrase);
    }

    /**
     * build a pair from the base64 content of each half.
     *
     * same reason as fromBase64(), for the PSPs that issue two files: in a
     * container both halves arrive as environment variables.
     *
     * there is no format parameter here, unlike fromBase64(): the extension
     * carries no information for a pair, because both halves are PEM and cURL
     * assumes PEM for each. In fromBase64() the extension is what tells cURL
     * whether the single file is a PKCS#12 bundle or a PEM one.
     *
     * @param string $certificate base64 of the certificate
     * @param string $key base64 of the private key
     * @param string|null $passphrase only when the private key is encrypted
     * @return self
     * @throws ValidationException
     */
    public static function pairFromBase64(
        #[\SensitiveParameter]
        string $certificate,
        #[\SensitiveParameter]
        string $key,
        #[\SensitiveParameter]
        ?string $passphrase = null,
    ): self {
        return self::pair(
            self::temporaryFile(self::decode($certificate), 'crt'),
            self::temporaryFile(self::decode($key), 'key'),
            $passphrase,
        );
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
     * path to the private key, when it lives in its own file
     *
     * @return string|null
     */
    public function keyPath(): ?string
    {
        return $this->key;
    }

    /**
     * whether certificate and key are in separate files
     *
     * @return bool
     */
    public function isPair(): bool
    {
        return $this->key !== null;
    }

    /**
     * extension of the certificate file — 'p12' or 'pem' for a single file,
     * the certificate half for a pair
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
     * @return array{
     *     cert: string|array{0: string, 1: string},
     *     ssl_key?: string|array{0: string, 1: string}
     * }
     */
    public function guzzleOptions(): array
    {
        if ($this->key === null) {
            return [
                'cert' => $this->passphrase === null ? $this->path : [$this->path, $this->passphrase],
            ];
        }

        /*
         * the passphrase goes on `ssl_key` and not on `cert`: in a pair it is
         * the key that may be encrypted, and Guzzle turns the second element of
         * `cert` into CURLOPT_SSLCERTPASSWD — a password for a file that has
         * nothing to decrypt.
         */
        return [
            'cert'    => $this->path,
            'ssl_key' => $this->passphrase === null ? $this->key : [$this->key, $this->passphrase],
        ];
    }

    /**
     * keep the passphrase out of var_dump() and print_r().
     *
     * @return array{path: string, key: string|null, passphrase: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'path'       => $this->path,
            'key'        => $this->key,
            'passphrase' => $this->passphrase === null ? null : '********',
        ];
    }

    /**
     * messages for validation
     *
     * @return object{
     *     notFound: string,
     *     keyNotFound: string,
     *     format: string,
     *     pairCertificate: string,
     *     pairKey: string,
     *     base64: string,
     *     temporary: string
     * }
     */
    public static function messages(): object
    {
        return (object) [
            'notFound'        => 'arquivo não encontrado ou sem permissão de leitura em %s.',
            'keyNotFound'     => 'chave privada não encontrada ou sem permissão de leitura em %s.',
            'format'          => 'use o arquivo .p12 ou .pem emitido pelo PSP. Um .pfx é o mesmo formato do .p12: basta renomear. Se o PSP entregou certificado e chave em arquivos separados, use Certificate::pair().',
            'pairCertificate' => 'o certificado de um par deve ser .crt, .cer ou .pem.',
            'pairKey'         => 'a chave privada de um par deve ser .key ou .pem.',
            'base64'          => 'o conteúdo informado não é um base64 válido.',
            'temporary'       => 'não foi possível gravar o certificado em um arquivo temporário.',
        ];
    }

    /**
     * the file is there and can be read
     *
     * @param string $path
     * @param string $message sprintf template taking the path
     * @return void
     * @throws ValidationException
     */
    private static function requireReadable(string $path, string $message): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw ValidationException::make('Certificado', sprintf($message, $path));
        }
    }

    /**
     * raw bytes of a base64 payload
     *
     * @param string $content
     * @return string
     * @throws ValidationException
     */
    private static function decode(#[\SensitiveParameter] string $content): string
    {
        $bytes = base64_decode($content, true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::make('Certificado', self::messages()->base64);
        }

        return $bytes;
    }

    /**
     * write bytes to a private temporary file removed at the end of the process
     *
     * @param string $bytes
     * @param string $format extension to give the file
     * @return string
     * @throws ValidationException
     */
    private static function temporaryFile(#[\SensitiveParameter] string $bytes, string $format): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'phpay-cert-');

        if ($temporary === false) {
            throw ValidationException::make('Certificado', self::messages()->temporary);
        }

        // tempnam() creates the file as 0600; rename() keeps the mode and adds
        // the extension Guzzle reads to tell cURL the format.
        $path = "{$temporary}.{$format}";

        if (!rename($temporary, $path) || file_put_contents($path, $bytes, LOCK_EX) === false) {
            throw ValidationException::make('Certificado', self::messages()->temporary);
        }

        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return $path;
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
