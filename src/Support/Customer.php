<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * a customer, in one shape.
 *
 * the same field is spelled five different ways across the gateways this
 * library supports — `cpfCnpj`, `tax_id`, `document`, `taxId`, `taxID`,
 * `cpf_cnpj` — so code written for one gateway does not move to another, and
 * nothing in the type system warns about it.
 *
 * This type is the one shape. Each gateway maps it to its own payload, and
 * `extra` carries whatever is specific to a gateway and has no home here.
 */
final class Customer
{
    /**
     * @var array<mixed> fields specific to one gateway, merged into its payload
     */
    private array $extra;

    /**
     * construct
     *
     * @param string $name
     * @param string|null $document CPF or CNPJ; punctuation is stripped
     * @param string|null $email
     * @param string|null $phone with area code; punctuation is stripped
     * @param string|null $id the customer id at the gateway, when it already exists
     * @param array<mixed> $extra
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $document = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $id = null,
        array $extra = [],
    ) {
        if (trim($name) === '') {
            throw ValidationException::make('PHPay', self::messages()->name);
        }

        if ($document !== null && !in_array(strlen($document), [11, 14], true)) {
            throw ValidationException::make('PHPay', self::messages()->document);
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::make('PHPay', self::messages()->email);
        }

        $this->extra = $extra;
    }

    /**
     * build a Customer, stripping punctuation from document and phone.
     *
     * use this when the values come from a form: `'123.456.789-01'` and
     * `'(11) 94002-8922'` become digits.
     *
     * @param string $name
     * @param string|null $document
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $id
     * @param array<mixed> $extra
     * @return self
     * @throws ValidationException
     */
    public static function make(
        string $name,
        ?string $document = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $id = null,
        array $extra = [],
    ): self {
        return new self(
            $name,
            $document === null ? null : self::onlyDigits($document),
            $email,
            $phone === null ? null : self::onlyDigits($phone),
            $id,
            $extra
        );
    }

    /**
     * whether the document is a CPF.
     *
     * @return bool
     */
    public function isIndividual(): bool
    {
        return $this->document !== null && strlen($this->document) === 11;
    }

    /**
     * whether the document is a CNPJ.
     *
     * @return bool
     */
    public function isCompany(): bool
    {
        return $this->document !== null && strlen($this->document) === 14;
    }

    /**
     * 'CPF', 'CNPJ', or null when there is no document.
     *
     * @return string|null
     */
    public function documentType(): ?string
    {
        if ($this->isIndividual()) {
            return 'CPF';
        }

        return $this->isCompany() ? 'CNPJ' : null;
    }

    /**
     * the first word of the name — Mercado Pago wants it apart.
     *
     * @return string
     */
    public function firstName(): string
    {
        $partes = preg_split('/\s+/', trim($this->name)) ?: [$this->name];

        return $partes[0];
    }

    /**
     * everything after the first word, or null for a single-word name.
     *
     * @return string|null
     */
    public function lastName(): ?string
    {
        $partes = preg_split('/\s+/', trim($this->name)) ?: [];

        if (count($partes) < 2) {
            return null;
        }

        return implode(' ', array_slice($partes, 1));
    }

    /**
     * the phone split the way PagBank wants it.
     *
     * assumes a Brazilian number: the last 8 or 9 digits are the number, the
     * two before them the area code, and the country is 55 unless the number
     * already carries it.
     *
     * @return array{country: string, area: string, number: string}|null
     */
    public function phoneParts(): ?array
    {
        if ($this->phone === null) {
            return null;
        }

        $digitos = self::onlyDigits($this->phone);

        if (strlen($digitos) > 11 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) < 10) {
            return null;
        }

        return [
            'country' => '55',
            'area'    => substr($digitos, 0, 2),
            'number'  => substr($digitos, 2),
        ];
    }

    /**
     * fields specific to one gateway.
     *
     * @return array<mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * a copy carrying extra fields for one gateway.
     *
     * @param array<mixed> $extra
     * @return self
     */
    public function withExtra(array $extra): self
    {
        return new self(
            $this->name,
            $this->document,
            $this->email,
            $this->phone,
            $this->id,
            array_merge($this->extra, $extra)
        );
    }

    /**
     * a copy pointing at an existing customer at the gateway.
     *
     * @param string $id
     * @return self
     */
    public function withId(string $id): self
    {
        return new self($this->name, $this->document, $this->email, $this->phone, $id, $this->extra);
    }

    /**
     * messages for validation
     *
     * @return object{name: string, document: string, email: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'     => 'O nome do cliente é obrigatório e não pode ser vazio.',
            'document' => 'O documento deve ter 11 dígitos (CPF) ou 14 (CNPJ), somente números. Use Customer::make() para limpar a pontuação automaticamente.',
            'email'    => 'O e-mail do cliente, quando informado, deve ser válido.',
        ];
    }

    /**
     * strip everything that is not a digit.
     *
     * @param string $value
     * @return string
     */
    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
