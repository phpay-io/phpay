<?php

/*
| Copie este arquivo para credentials.php e preencha com as suas credenciais
| de sandbox. credentials.php é ignorado pelo git — nunca commite segredo real.
|
| Client ID e Client Secret saem do detalhe da aplicação, no Internet Banking.
*/

const CLIENT_ID     = '';
const CLIENT_SECRET = '';

/*
| O Inter entrega o certificado da aplicação em DOIS arquivos: um .crt e um
| .key. Não precisa converter para .p12 — o Certificate::pair() entrega as duas
| metades ao cURL como elas vêm.
|
| Certificados (.crt, .key, .p12, .pem, .pfx) são ignorados pelo git.
*/
const CERTIFICATE = '';
const PRIVATE_KEY = '';

/* uma chave Pix da sua conta de sandbox, que recebe as cobranças */
const PIX_KEY = '';

/*
| Só quando a aplicação está vinculada a mais de uma conta corrente. Deixe
| vazio se for uma só: o header x-conta-corrente não é mandado sem valor.
*/
const ACCOUNT = '';

const NAME     = 'Mário Lucas';
const CPF_CNPJ = '00000000000';
