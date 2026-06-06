const crypto = require('crypto');

// Generate ECDH P-256 key pair
const ecdh = crypto.createECDH('prime256v1');
ecdh.generateKeys();

const publicKey = ecdh.getPublicKey();
const privateKey = ecdh.getPrivateKey();

function base64url(buffer) {
  return buffer.toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

console.log("PUBLIC KEY (VAPID):");
console.log(base64url(publicKey));
console.log("\nPRIVATE KEY (VAPID):");
console.log(base64url(privateKey));
