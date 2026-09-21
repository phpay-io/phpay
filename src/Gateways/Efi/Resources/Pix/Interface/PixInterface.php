<?php

namespace PHPay\Efi\Resources\Pix\Interface;

interface PixInterface
{
    /**
     * create a random Pix key (EVP).
     *
     * @return array<mixed>
     */
    public function createKey(): array;

    /**
     * list the random Pix keys of the account
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * remove a random Pix key
     *
     * @param string $key
     * @return bool
     */
    public function destroy(string $key): bool;
}
