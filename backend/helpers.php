<?php

declare(strict_types=1);

function jsonResponse(bool $success, $data = null, string $message = '', int $status = 200): void
{
    // Clear any previous output (warnings, etc.) to ensure valid JSON
    if (ob_get_length()) ob_clean();

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function requireAuth(): int
{
    $userId = currentUserId();
    if (!$userId) {
        jsonResponse(false, null, 'Authentication required', 401);
    }
    return $userId;
}

function requireRole(array $allowedRoles): array
{
    $userId = requireAuth();
    $stmt = db()->prepare('SELECT id, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        jsonResponse(false, null, 'User not found', 404);
    }
    if (!in_array($user['role'], $allowedRoles, true)) {
        jsonResponse(false, null, 'Forbidden', 403);
    }
    return $user;
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function derToIeee(string $der, int $keyLength = 64): string
{
    $offset = 2; // skip 0x30 and length
    if (ord($der[$offset]) === 0x81) {
        $offset++;
    }
    $offset++; // skip 0x02
    $lenR = ord($der[$offset++]);
    $R = substr($der, $offset, $lenR);
    $offset += $lenR;
    $offset++; // skip 0x02
    $lenS = ord($der[$offset++]);
    $S = substr($der, $offset, $lenS);

    $R = ltrim($R, "\x00");
    $S = ltrim($S, "\x00");

    $partLength = (int) ($keyLength / 2);
    $R = str_pad($R, $partLength, "\x00", STR_PAD_LEFT);
    $S = str_pad($S, $partLength, "\x00", STR_PAD_LEFT);

    return $R . $S;
}

function broadcastPushNotification(): void
{
    $pdo = db();
    $config = require __DIR__ . '/config.php';
    $vapid = $config['vapid'] ?? null;
    if (!$vapid) {
        return;
    }

    // Fetch all active subscriptions
    $stmt = $pdo->query('SELECT endpoint FROM push_subscriptions');
    $subs = $stmt->fetchAll();
    if (empty($subs)) {
        return;
    }

    $public_key = $vapid['public_key'];
    $private_key = $vapid['private_key'];

    $mh = curl_multi_init();
    $ch_list = [];
    $jwt_cache = [];

    foreach ($subs as $sub) {
        $endpoint = $sub['endpoint'];
        $parsed = parse_url($endpoint);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            continue;
        }
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        // Get or generate JWT for this audience
        if (!isset($jwt_cache[$origin])) {
            try {
                $header = base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
                $payload = base64UrlEncode(json_encode([
                    'aud' => $origin,
                    'exp' => time() + 3600, // 1 hour expiration
                    'sub' => 'mailto:no-reply@mthglobal.com'
                ]));
                $dataToSign = $header . '.' . $payload;
                $pkey = openssl_pkey_get_private($private_key);
                if ($pkey && openssl_sign($dataToSign, $signatureDer, $pkey, OPENSSL_ALGO_SHA256)) {
                    $signatureIeee = derToIeee($signatureDer);
                    $jwt_cache[$origin] = $dataToSign . '.' . base64UrlEncode($signatureIeee);
                } else {
                    continue; // Skip if signature fails
                }
            } catch (Exception $e) {
                continue;
            }
        }

        $jwt = $jwt_cache[$origin];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'TTL: 60',
            'Urgency: high',
            'Authorization: WebPush ' . $jwt,
            'Crypto-Key: p256ecdsa=' . $public_key,
            'Content-Length: 0'
        ]);
        // Set brief timeouts to avoid hanging
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        curl_multi_add_handle($mh, $ch);
        $ch_list[] = ['ch' => $ch, 'endpoint' => $endpoint];
    }

    // Execute handles in parallel
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    // Clean up handles and detect failed subscriptions
    $expiredEndpoints = [];
    foreach ($ch_list as $item) {
        $ch = $item['ch'];
        $endpoint = $item['endpoint'];
        $info = curl_getinfo($ch);
        $http_code = (int) ($info['http_code'] ?? 0);

        if ($http_code === 410 || $http_code === 404) {
            $expiredEndpoints[] = $endpoint;
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    // Delete expired subscriptions from db to keep it clean
    if (!empty($expiredEndpoints)) {
        $placeholders = implode(',', array_fill(0, count($expiredEndpoints), '?'));
        $del = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint IN ($placeholders)");
        $del->execute($expiredEndpoints);
    }
}
