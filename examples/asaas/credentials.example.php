<?php

/*
| Copie este arquivo para credentials.php e preencha com as suas credenciais
| de sandbox. credentials.php é ignorado pelo git — nunca commite token real.
*/

const TOKEN_ASAAS_SANDBOX = '';

const NAME     = 'Mário Lucas';
const CPF_CNPJ = '00000000000';

const WEBHOOK = [
    'name'        => 'PHPay webhook',
    'url'         => 'https://exemplo.test/webhook',
    'email'       => 'fale@phpay.io',
    'enabled'     => true,
    'interrupted' => false,
    'sendType'    => 'SEQUENTIALLY',
    'events'      => ['PAYMENT_RECEIVED'],
];
