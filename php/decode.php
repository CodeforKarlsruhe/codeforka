<?php

// decrypt using openssl
// cert is like privat key like "file://path/to/file-key.pem";
// msg is hexencoded string
// return is plaintext message

function decrypt($msg,$cert,$pass) {
  try {
    $privkey = openssl_pkey_get_private ( $cert,$pass);
    if (Null == $privkey)
     throw new Exception("Key error" . PHP_EOL);

    $bmsg = hex2bin($msg); // binary encrypted message
    $umsg = ""; // return value
    openssl_private_decrypt ( $bmsg, $umsg, $privkey );
    return $umsg;
  } catch (Exception $e) {
    echo $e->getMessage();
    return Null;
  }
}

// adjust path, if required
$cfg = parse_ini_file("news.ini", false);

$curlSession = curl_init();
$url = "http://127.0.0.1:8000/php/action.php/?lang=de&down=" . $cfg["down"];
curl_setopt($curlSession, CURLOPT_URL, $url);
curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

$jsonData = json_decode(curl_exec($curlSession));
curl_close($curlSession);



$cert = $cfg["key"];
$pass = trim(file_get_contents($cfg["pass"]));


foreach ($jsonData as $j){
    echo (decrypt($j->email,$cert,$pass) . "," . decrypt($j->code,$cert,$pass) . PHP_EOL);
}


?>
