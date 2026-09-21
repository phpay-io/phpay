<?php

/*
| Copie este arquivo para credentials.php e preencha com a sua secret key.
| credentials.php é ignorado pelo git — nunca commite credencial real.
|
| O Pagar.me não tem host de sandbox: teste e produção usam o mesmo endpoint,
| e o que decide o ambiente é o prefixo da chave (sk_test_ vs sk_live_).
*/

const SECRET_KEY_PAGARME = 'sk_test_';

const NAME     = 'Mário Lucas';
const EMAIL    = 'fale@phpay.io';
const DOCUMENT = '00000000000';
