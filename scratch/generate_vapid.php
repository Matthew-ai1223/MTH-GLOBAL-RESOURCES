<?php
// Generate EC key pair
$config = [
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1'
];
$res = openssl_pkey_new($config);
if (!$res) {
    die("OpenSSL key creation failed: " . openssl_error_string());
}

// Export private key
openssl_pkey_export($res, $privKeyPEM);

// Export public key details
$details = openssl_pkey_get_details($res);
$pubKeyDetails = $details['ec'];

// Private key bytes (d)
$privKeyBytes = $pubKeyDetails['d'];
// Public key bytes (x and y)
$pubKeyBytes = "\x04" . $pubKeyDetails['x'] . $pubKeyDetails['y'];

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$publicKeyBase64 = base64url_encode($pubKeyBytes);
$privateKeyBase64 = base64url_encode($privKeyBytes);

echo "PUBLIC KEY (VAPID):\n" . $publicKeyBase64 . "\n\n";
echo "PRIVATE KEY (VAPID):\n" . $privateKeyBase64 . "\n\n";
