<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * a static Pix QR Code, composed here instead of asked for.
 *
 * By the BACEN standard a static QR Code is assembled locally from the Pix
 * key: there is nothing to request, and a gateway that offers an endpoint for
 * it does so as a convenience. That is why Asaas and Woovi have one and Efí
 * does not — and why this is support code, like Money, and not a capability:
 * no HTTP happens here, so no gateway is involved.
 *
 *     StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')
 *         ->withAmount(Money::reais('100,50'))
 *         ->withTxid('PEDIDO-1234')
 *         ->payload();
 *
 * Only the copy-and-paste string is returned. Rendering the image needs a QR
 * Code encoder, and the runtime of this library is ext-curl, ext-json,
 * ext-openssl and Guzzle — a new dependency for a picture is not a trade this
 * library makes.
 *
 * @see https://www.bcb.gov.br/content/estabilidadefinanceira/pix/Regulamento_Pix/II_ManualdePadroesparaIniciacaodoPix.pdf
 */
final class StaticPixCode
{
    /**
     * globally unique identifier of the Pix arrangement, in field 26.
     */
    private const GUI = 'br.gov.bcb.pix';

    /**
     * BRL, in field 53.
     */
    private const CURRENCY = '986';

    /**
     * field 58.
     */
    private const COUNTRY = 'BR';

    /**
     * merchant category code, field 52: zeros when not informed.
     */
    private const MCC = '0000';

    /**
     * txid that means "none", in field 62. Not an empty value: the standard
     * asks for these three asterisks.
     */
    private const TXID_FREE = '***';

    /**
     * a Pix key is at most 77 characters, which is also what fits field 26:
     * its length is two digits, so the whole value has to stay under 100.
     */
    private const KEY_MAX = 77;

    /**
     * field 59.
     */
    private const NAME_MAX = 25;

    /**
     * field 60.
     */
    private const CITY_MAX = 15;

    /**
     * field 62, sub 05.
     */
    private const TXID_MAX = 25;

    /**
     * field 54.
     */
    private const AMOUNT_MAX = 13;

    /**
     * construct
     *
     * @param string $key Pix key of the receiver
     * @param string $merchantName name in field 59
     * @param string $merchantCity city in field 60
     * @param string|null $amount already formatted as reais with a dot
     * @param string $txid
     */
    private function __construct(
        private readonly string $key,
        private readonly string $merchantName,
        private readonly string $merchantCity,
        private readonly ?string $amount = null,
        private readonly string $txid = self::TXID_FREE
    ) {
    }

    /**
     * build a code for a key.
     *
     * $merchantName is NOT what the payer sees. The app shows the name the
     * key resolves to at the DICT, and the standard says this field is
     * ignored by the payer's app — it is required by the payload and
     * cosmetic in practice, so do not build anything on top of it.
     *
     * The key format is not validated: EVP, e-mail, phone and CPF/CNPJ look
     * nothing alike, and a wrong guess here would refuse a good key.
     *
     * @param string $key
     * @param string $merchantName
     * @param string $merchantCity
     * @return self
     * @throws ValidationException
     */
    public static function make(string $key, string $merchantName, string $merchantCity): self
    {
        $key          = trim($key);
        $merchantName = trim($merchantName);
        $merchantCity = trim($merchantCity);
        $messages     = self::messages();

        if ($key === '') {
            throw ValidationException::make('PHPay', $messages->key);
        }

        if (strlen($key) > self::KEY_MAX) {
            throw ValidationException::make('PHPay', sprintf($messages->keyLength, self::KEY_MAX));
        }

        if ($merchantName === '' || strlen($merchantName) > self::NAME_MAX) {
            throw ValidationException::make('PHPay', sprintf($messages->name, self::NAME_MAX));
        }

        if ($merchantCity === '' || strlen($merchantCity) > self::CITY_MAX) {
            throw ValidationException::make('PHPay', sprintf($messages->city, self::CITY_MAX));
        }

        return new self($key, $merchantName, $merchantCity);
    }

    /**
     * fix the amount, in reais.
     *
     * Without one the payer types the amount, which is what a static code is
     * usually for. A raw number is read as reais — field 54 is reais with a
     * dot — so withAmount(100) is a hundred reais, not a hundred cents.
     *
     * @param Money|int|float $amount
     * @return self
     * @throws ValidationException
     */
    public function withAmount(Money|int|float $amount): self
    {
        $money     = $amount instanceof Money ? $amount : Money::reais($amount);
        $formatted = $money->toDecimal();

        if (strlen($formatted) > self::AMOUNT_MAX) {
            throw ValidationException::make('PHPay', sprintf(self::messages()->amount, self::AMOUNT_MAX));
        }

        return new self($this->key, $this->merchantName, $this->merchantCity, $formatted, $this->txid);
    }

    /**
     * identify the transaction in your own system.
     *
     * Letters and digits only, up to 25 — the standard is strict here, and a
     * reader that refuses the code says nothing about why. Absent, the code
     * carries `***`, which means the receiver did not set one.
     *
     * @param string $txid
     * @return self
     * @throws ValidationException
     */
    public function withTxid(string $txid): self
    {
        $txid = trim($txid);

        if ($txid !== self::TXID_FREE && preg_match('/^[A-Za-z0-9]{1,' . self::TXID_MAX . '}$/', $txid) !== 1) {
            throw ValidationException::make('PHPay', sprintf(self::messages()->txid, self::TXID_MAX));
        }

        return new self($this->key, $this->merchantName, $this->merchantCity, $this->amount, $txid);
    }

    /**
     * the copy-and-paste string, CRC included.
     *
     * @return string
     */
    public function payload(): string
    {
        $account = $this->field('00', self::GUI) . $this->field('01', $this->key);

        $payload = $this->field('00', '01')
            . $this->field('26', $account)
            . $this->field('52', self::MCC)
            . $this->field('53', self::CURRENCY)
            . ($this->amount === null ? '' : $this->field('54', $this->amount))
            . $this->field('58', self::COUNTRY)
            . $this->field('59', $this->merchantName)
            . $this->field('60', $this->merchantCity)
            . $this->field('62', $this->field('05', $this->txid));

        /*
         * the CRC covers the `6304` that announces it, not only what comes
         * before: stopping earlier is the mistake that produces a code which
         * simply does not read, and the standard says as much in the footnote
         * of field 63.
         */
        return $payload . '6304' . self::crc16($payload . '6304');
    }

    /**
     * one field, as the standard wants it: id, two digits of length, value.
     *
     * @param string $id
     * @param string $value
     * @return string
     */
    private function field(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    /**
     * CRC-16/CCITT-FALSE, polynomial 0x1021 and initial value 0xFFFF, as the
     * footnote of field 63 spells out. Four uppercase hex digits.
     *
     * @param string $payload
     * @return string
     */
    private static function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= ord($payload[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * messages of this value object.
     *
     * @return object{key: string, keyLength: string, name: string, city: string, txid: string, amount: string}
     */
    public static function messages(): object
    {
        return (object) [
            'key'       => 'Informe a chave Pix do recebedor.',
            'keyLength' => 'A chave Pix não pode passar de %d caracteres.',
            'name'      => 'O nome do recebedor é obrigatório e não pode passar de %d caracteres. Ele é exigido pelo padrão, mas o app do pagador mostra o nome que vem do DICT, não este.',
            'city'      => 'A cidade do recebedor é obrigatória e não pode passar de %d caracteres.',
            'txid'      => 'O txid aceita apenas letras e números, até %d caracteres. Sem txid, o código leva *** no lugar.',
            'amount'    => 'O valor não cabe no campo do padrão, que aceita %d caracteres em reais.',
        ];
    }
}
