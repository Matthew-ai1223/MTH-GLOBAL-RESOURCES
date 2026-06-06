const crypto = require('crypto');

// Generate ECDH P-256 key pair
const { publicKey, privateKey } = crypto.generateKeyPairSync('ec', {
  namedCurve: 'prime256v1'
});

// Private key in PEM format
const privPem = privateKey.export({ type: 'sec1', format: 'pem' });

// Get public key details for base64url encoding
const pubDer = publicKey.export({ type: 'spki', format: 'der' });
// The first 26 bytes of standard prime256v1 SPKI DER are headers/metadata.
// The actual 65-byte uncompressed public key starts at byte 26.
const pubBytes = pubDer.slice(26);

function base64url(buffer) {
  return buffer.toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

console.log("=== PUBLIC KEY (VAPID Base64URL) ===");
console.log(base64url(pubBytes));
console.log("\n=== PRIVATE KEY (PEM) ===");
console.log(privPem);
