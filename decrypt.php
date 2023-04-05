<?php

$token = 'Analytics TOKEN HERE';

$initializationVector = base64_decode("IV HERE");

$decrypted = openssl_decrypt(
    file_get_contents(__DIR__.'/data.tgz'),
    'AES-256-CBC',
    hash('sha256', $token),
    OPENSSL_RAW_DATA,
    $initializationVector
);


if (!$decrypted) {
    var_dump("ERROR");die;
}

file_put_contents('decrypted.tgz', $decrypted);