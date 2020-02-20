
<?php

//$key should have been previously generated in a cryptographically safe way, like openssl_random_pseudo_bytes

$key = bin2hex(openssl_random_pseudo_bytes(32));
//$key = base64_encode(openssl_random_pseudo_bytes(32));
$key = base64_decode("MDEyMzQ1NjcwMTIzNDU2NzAxMjM0NTY3MDEyMzR4eXo=");
echo "Key: " . $key . PHP_EOL;
echo "Key (hex): " . bin2hex($key) . PHP_EOL;

$plaintext = "message to be encrypted";
$plaintext = "A s 123 secret öäüß 1234 message";
$plaintext = base64_decode("QSBzIDEyMyBzZWNyZXQgw7bDpMO8w58gMTIzNCBtZXNzYWdl");
// padding
$len = strlen($plaintext);
echo "Initial Message: " . $plaintext . "\n";
echo "msg len: " . $len . PHP_EOL;
$pad = 16 - ($len % 16);
// openssl does pkcs7 padding by default. don't do again
//$plaintext .= str_repeat(chr($pad), $pad);
//echo "Len after padding " . strlen($plaintext) . PHP_EOL;


// public key
$pkmsg = "";
//$cert = "file://path/to/file.pem";
$cert = "file://oklab.pem";
$pubkey = openssl_pkey_get_public ( $cert);
openssl_public_encrypt ( $plaintext , $pkmsg , $pubkey);
echo bin2hex($pkmsg) . PHP_EOL;

//
$cert2 = "file://oklab-key.pem";
$pass = "xxx"; 
$privkey = openssl_pkey_get_private ( $cert2,$pass);
echo "Privkey: " . $privkey . PHP_EOL;
$dmsg = "";
openssl_private_decrypt ( $pkmsg, $dmsg, $privkey );
echo $dmsg . PHP_EOL;



?>

