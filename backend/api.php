<?php

declare(strict_types=1);
ob_start();
session_set_cookie_params(['path' => '/']);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/services/EmailService.php';
require __DIR__ . '/services/JsonFallbackService.php';

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body = getJsonBody();
$pdo = null;
try {
    $pdo = db();
} catch (Exception $e) {
    // Database is down, will use fallback for specific routes
}

try {
    if ($route === 'health') {
        jsonResponse(true, ['status' => 'ok']);
    }

    switch ($route) {
        case 'push.subscribe':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $userId = currentUserId();
            $endpoint = trim((string) ($body['endpoint'] ?? ''));
            $p256dh = trim((string) ($body['keys']['p256dh'] ?? ''));
            $auth = trim((string) ($body['keys']['auth'] ?? ''));

            if ($endpoint === '' || $p256dh === '' || $auth === '') {
                jsonResponse(false, null, 'Invalid push subscription payload', 422);
            }

            if ($pdo) {
                $stmt = $pdo->prepare('INSERT INTO push_subscriptions (user_id, endpoint, keys_p256dh, keys_auth) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), keys_p256dh = VALUES(keys_p256dh), keys_auth = VALUES(keys_auth)');
                $stmt->execute([$userId, $endpoint, $p256dh, $auth]);
                jsonResponse(true, null, 'Push subscription saved');
            } else {
                jsonResponse(false, null, 'Database unavailable');
            }
            break;

        case 'products.latest_notification':
            if ($pdo) {
                $stmt = $pdo->query('SELECT id, name, description, price, category, image_url FROM products ORDER BY id DESC LIMIT 1');
                $product = $stmt->fetch();
                if ($product) {
                    $baseUrl = getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' : "http://localhost/");
                    $product['image_url'] = $product['image_url'] ? (str_starts_with($product['image_url'], 'http') ? $product['image_url'] : $baseUrl . ltrim($product['image_url'], '/')) : $baseUrl . "assets/images/placeholder.png";
                    jsonResponse(true, $product);
                } else {
                    jsonResponse(false, null, 'No products found');
                }
            } else {
                jsonResponse(false, null, 'Database unavailable');
            }
            break;

        case 'auth.register':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $name = trim((string) ($body['name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $phone = trim((string) ($body['phone'] ?? ''));
            $password = (string) ($body['password'] ?? '');

            if ($name === '' || $email === '' || $phone === '' || $password === '') {
                jsonResponse(false, null, 'Name, email, phone and password are required', 422);
            }

            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                jsonResponse(false, null, 'Email already exists', 409);
            }

            $code = (string) rand(100000, 999999);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role, verification_code, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'customer', $code, 0]);
            
            // Send real email
            EmailService::sendVerificationCode($email, $code);
            
            jsonResponse(true, ['email' => $email], 'Registered. Verification code sent.');
            break;

        case 'auth.resend':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            if ($email === '') {
                jsonResponse(false, null, 'Email is required', 422);
            }

            $code = (string) rand(100000, 999999);
            $stmt = $pdo->prepare('UPDATE users SET verification_code = ? WHERE email = ? AND is_verified = 0');
            $stmt->execute([$code, $email]);

            if ($stmt->rowCount() > 0) {
                EmailService::sendVerificationCode($email, $code);
                jsonResponse(true, null, 'New verification code sent');
            } else {
                jsonResponse(false, null, 'User not found or already verified', 404);
            }
            break;

        case 'auth.verify':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $code = trim((string) ($body['code'] ?? ''));

            if ($email === '' || $code === '') {
                jsonResponse(false, null, 'Email and code are required', 422);
            }

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND verification_code = ? LIMIT 1');
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch();

            if (!$user) {
                jsonResponse(false, null, 'Invalid verification code', 401);
            }

            $update = $pdo->prepare('UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?');
            $update->execute([(int) $user['id']]);
            
            jsonResponse(true, null, 'Account verified successfully');
            break;

        case 'auth.login':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $password = (string) ($body['password'] ?? '');
            $remember = !empty($body['remember']);

            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, name, role, password_hash, is_verified FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
            } else {
                $user = JsonFallbackService::login($email, $password);
            }

            if (!$user || (isset($user['password_hash']) && !password_verify($password, $user['password_hash']))) {
                jsonResponse(false, null, 'Invalid credentials', 401);
            }

            if (!$user['is_verified']) {
                jsonResponse(false, ['email' => $email], 'Account not verified', 403);
            }

            $_SESSION['user_id'] = (int) $user['id'];
            
            if ($remember) {
                // Set the session cookie to expire in 30 days
                setcookie(session_name(), session_id(), time() + (86400 * 30), '/');
            }
            
            if ($pdo) {
                $audit = $pdo->prepare('INSERT INTO sessions_audit (user_id, event, ip_address, user_agent) VALUES (?, ?, ?, ?)');
                $audit->execute([(int) $user['id'], 'login', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            }
            jsonResponse(true, [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
            ], 'Logged in');
            break;

        case 'auth.logout':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = currentUserId();
            if ($uid && $pdo) {
                $audit = $pdo->prepare('INSERT INTO sessions_audit (user_id, event, ip_address, user_agent) VALUES (?, ?, ?, ?)');
                $audit->execute([$uid, 'logout', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            }
            $_SESSION = [];
            session_destroy();
            jsonResponse(true, null, 'Logged out');
            break;

        case 'auth.me':
            $userId = currentUserId();
            if (!$userId) {
                jsonResponse(false, null, 'Not logged in', 401);
            }
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, name, email, role, phone, address FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                $user = JsonFallbackService::getUserById($userId);
            }
            if (!$user) {
                jsonResponse(false, null, 'User not found', 404);
            }
            jsonResponse(true, $user);
            break;

        case 'auth.reset.request':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            if (!$pdo) {
                jsonResponse(true, null, 'If this email is registered, a password reset link has been sent to it.');
            }
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(16));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
                $ins->execute([(int) $user['id'], $token, $expiresAt]);

                require_once __DIR__ . '/services/EmailService.php';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $basePath = str_replace('/backend/api.php', '', $_SERVER['SCRIPT_NAME']);
                $basePath = implode('/', array_map('rawurlencode', explode('/', $basePath)));
                $domain = $protocol . $host . $basePath;

                EmailService::sendPasswordResetLink($email, $token, $domain);
            }
            jsonResponse(true, null, 'If this email is registered, a password reset link has been sent to it.');
            break;

        case 'auth.reset.confirm':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            if (!$pdo) {
                jsonResponse(false, null, 'Password reset requires a database connection.', 503);
            }
            $token = trim((string) ($body['token'] ?? ''));
            $password = (string) ($body['password'] ?? '');
            if ($token === '' || $password === '') {
                jsonResponse(false, null, 'Token and password required', 422);
            }
            $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token = ? LIMIT 1');
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
                jsonResponse(false, null, 'Invalid reset token', 422);
            }
            $pdo->beginTransaction();
            $updUser = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $updUser->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
            $updReset = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
            $updReset->execute([(int) $row['id']]);
            $pdo->commit();
            jsonResponse(true, null, 'Password reset successful');
            break;

        case 'profile.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            if (!$pdo) {
                jsonResponse(false, null, 'Profile updates require a database connection.', 503);
            }
            $name = trim((string) ($body['name'] ?? ''));
            $phone = trim((string) ($body['phone'] ?? ''));
            $address = trim((string) ($body['address'] ?? ''));
            $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $address, $uid]);
            jsonResponse(true, null, 'Profile updated');
            break;

        case 'categories.list':
            if ($pdo) {
                $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getCategories());
            }
            break;

        case 'categories.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '') {
                jsonResponse(false, null, 'Category name required', 422);
            }
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            try {
                $stmt->execute([$name]);
                jsonResponse(true, ['id' => $pdo->lastInsertId()], 'Category created');
            } catch (PDOException $e) {
                jsonResponse(false, null, 'Category already exists', 409);
            }
            break;

        case 'categories.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['id'] ?? 0);
            $name = trim((string) ($body['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                jsonResponse(false, null, 'ID and name required', 422);
            }
            $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            jsonResponse(true, null, 'Category updated');
            break;

        case 'categories.delete':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            jsonResponse(true, null, 'Category deleted');
            break;

        case 'products.list':
            $search = strtolower(trim((string) ($_GET['search'] ?? '')));
            $category = trim((string) ($_GET['category'] ?? ''));
            if ($pdo) {
                if ($search !== '') {
                    $sql = 'SELECT id, name, description, price, stock, category, image_url FROM products WHERE (LOWER(name) LIKE ? OR LOWER(category) LIKE ?)';
                    $params = ['%' . $search . '%', '%' . $search . '%'];
                    if ($category !== '') {
                        $sql .= ' AND category = ?';
                        $params[] = $category;
                    }
                    $sql .= ' ORDER BY id DESC';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } else {
                    if ($category !== '') {
                        $stmt = $pdo->prepare('SELECT id, name, description, price, stock, category, image_url FROM products WHERE category = ? ORDER BY id DESC');
                        $stmt->execute([$category]);
                    } else {
                        $stmt = $pdo->query('SELECT id, name, description, price, stock, category, image_url FROM products ORDER BY id DESC');
                    }
                }
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getProducts($search, $category));
            }
            break;

        case 'products.details':
            $id = (int) ($_GET['id'] ?? 0);
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, name, description, price, stock, category, image_url FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $product = $stmt->fetch();
            } else {
                $product = JsonFallbackService::getProductDetails($id);
            }
            if (!$product) {
                jsonResponse(false, null, 'Product not found', 404);
            }
            jsonResponse(true, $product);
            break;

        case 'products.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $name = trim((string) ($body['name'] ?? ''));
            $description = trim((string) ($body['description'] ?? ''));
            $price = (float) ($body['price'] ?? 0);
            $stock = (int) ($body['stock'] ?? 0);
            $category = trim((string) ($body['category'] ?? ''));
            $image_url = trim((string) ($body['image_url'] ?? ''));
            if ($name === '' || $price <= 0 || $category === '') {
                jsonResponse(false, null, 'Name, price, and category are required', 422);
            }
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price, stock, category, image_url) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $price, $stock, $category, $image_url ?: null]);
            $productId = (int) $pdo->lastInsertId();

            // Broadcast real-time Web Push notification to PWA users
            try {
                broadcastPushNotification();
            } catch (Exception $e) {
                error_log("Web Push broadcast error: " . $e->getMessage());
            }

            // Send HTML email notification to all registered users
            try {
                $userStmt = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
                $users = $userStmt->fetchAll();
                foreach ($users as $user) {
                    EmailService::sendNewProductNotification($user['email'], $name, $description, $price, $image_url ?: null);
                }
            } catch (Exception $e) {
                error_log("Email broadcast error: " . $e->getMessage());
            }

            jsonResponse(true, ['id' => $productId], 'Product created');
            break;

        case 'products.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['id'] ?? 0);
            $name = trim((string) ($body['name'] ?? ''));
            $description = trim((string) ($body['description'] ?? ''));
            $price = (float) ($body['price'] ?? 0);
            $stock = (int) ($body['stock'] ?? 0);
            $category = trim((string) ($body['category'] ?? ''));
            $image_url = trim((string) ($body['image_url'] ?? ''));

            if ($id <= 0 || $name === '' || $price <= 0) {
                jsonResponse(false, null, 'Valid ID, name, and price are required', 422);
            }

            $stmt = $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category = ?, image_url = ? WHERE id = ?');
            $stmt->execute([$name, $description, $price, $stock, $category, $image_url ?: null, $id]);
            jsonResponse(true, null, 'Product updated successfully');
            break;

        case 'products.delete':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(false, null, 'Invalid ID', 422);
            }
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);
            jsonResponse(true, null, 'Product deleted');
            break;

        case 'cart.add':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $userId = requireAuth();
            $productId = (int) ($body['product_id'] ?? 0);
            $quantity = max(1, (int) ($body['quantity'] ?? 1));

            $check = $pdo->prepare('SELECT id, stock FROM products WHERE id = ?');
            $check->execute([$productId]);
            $product = $check->fetch();
            if (!$product) {
                jsonResponse(false, null, 'Product not found', 404);
            }
            if ($quantity > (int) $product['stock']) {
                jsonResponse(false, null, 'Insufficient stock', 422);
            }

            $exists = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
            $exists->execute([$userId, $productId]);
            $item = $exists->fetch();
            if ($item) {
                $newQty = (int) $item['quantity'] + $quantity;
                $upd = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                $upd->execute([$newQty, (int) $item['id']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
                $ins->execute([$userId, $productId, $quantity]);
            }
            jsonResponse(true, null, 'Added to cart');
            break;

        case 'cart.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $productId = (int) ($body['product_id'] ?? 0);
            $quantity = max(1, (int) ($body['quantity'] ?? 1));
            $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$quantity, $uid, $productId]);
            jsonResponse(true, null, 'Cart updated');
            break;

        case 'cart.remove':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $productId = (int) ($body['product_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$uid, $productId]);
            jsonResponse(true, null, 'Item removed');
            break;

        case 'cart.list':
            $userId = requireAuth();
            if ($pdo) {
                $stmt = $pdo->prepare('
                    SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.image_url, p.category
                    FROM cart_items c
                    JOIN products p ON p.id = c.product_id
                    WHERE c.user_id = ?
                ');
                $stmt->execute([$userId]);
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, []); // Return empty cart in fallback mode
            }
            break;

        case 'wishlist.add':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $productId = (int) ($body['product_id'] ?? 0);
            $stmt = $pdo->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)');
            $stmt->execute([$uid, $productId]);
            jsonResponse(true, null, 'Added to wishlist');
            break;

        case 'wishlist.remove':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $productId = (int) ($body['product_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$uid, $productId]);
            jsonResponse(true, null, 'Removed from wishlist');
            break;

        case 'wishlist.list':
            $uid = requireAuth();
            $stmt = $pdo->prepare('SELECT p.id, p.name, p.price, p.category, p.image_url FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.id DESC');
            $stmt->execute([$uid]);
            jsonResponse(true, $stmt->fetchAll());
            break;

        case 'ai.chat':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            require_once __DIR__ . '/services/AiService.php';
            $msg = trim((string) ($body['message'] ?? ''));
            $history = (array) ($body['history'] ?? []);
            $context = (array) ($body['context'] ?? []);
            if ($msg === '') {
                jsonResponse(false, null, 'Message is required', 422);
            }
            $reply = AiService::chat($msg, $history, $context);
            jsonResponse(true, ['reply' => $reply]);
            break;

        case 'orders.checkout':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $userId = requireAuth();
            $payMethod = trim((string) ($body['payment_method'] ?? 'Online'));

            $itemsStmt = $pdo->prepare('
                SELECT c.product_id, c.quantity, p.price, p.stock
                FROM cart_items c
                JOIN products p ON p.id = c.product_id
                WHERE c.user_id = ?
            ');
            $itemsStmt->execute([$userId]);
            $items = $itemsStmt->fetchAll();
            if (!$items) {
                jsonResponse(false, null, 'Cart is empty', 422);
            }

            $total = 0.0;
            foreach ($items as $item) {
                if ((int) $item['quantity'] > (int) $item['stock']) {
                    jsonResponse(false, null, 'Stock changed. Please review cart.', 409);
                }
                $total += (float) $item['price'] * (int) $item['quantity'];
            }

            $pdo->beginTransaction();
            $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, status, total_amount, payment_status, payment_method) VALUES (?, ?, ?, ?, ?)');
            $orderStmt->execute([$userId, 'Pending', $total, 'Pending', $payMethod]);
            $orderId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
                $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);

                // Decrement stock
                $updateStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $updateStock->execute([(int) $item['quantity'], $item['product_id']]);
            }

            $clearCart = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
            $clearCart->execute([$userId]);

            $pdo->commit();
            jsonResponse(true, ['order_id' => $orderId], 'Order created');
            break;

        case 'orders.list':
            $userId = requireAuth();
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, status, total_amount, payment_status, payment_method, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
                $stmt->execute([$userId]);
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getOrders($userId));
            }
            break;

        case 'orders.details':
            $userId = requireAuth();
            $orderId = (int) ($_GET['id'] ?? 0);
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, status, total_amount, payment_status, payment_method, created_at FROM orders WHERE id = ? AND user_id = ?');
                $stmt->execute([$orderId, $userId]);
                $order = $stmt->fetch();
                if (!$order) {
                    jsonResponse(false, null, 'Order not found', 404);
                }
                $itemStmt = $pdo->prepare('
                    SELECT oi.quantity, oi.unit_price, p.id as product_id, p.name, p.image_url, p.category
                    FROM order_items oi
                    JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = ?
                ');
                $itemStmt->execute([$orderId]);
                $order['items'] = $itemStmt->fetchAll();
                jsonResponse(true, $order);
            } else {
                $order = JsonFallbackService::getOrderDetails($orderId);
                if (!$order || (int) $order['user_id'] !== $userId) {
                    jsonResponse(false, null, 'Order not found', 404);
                }
                jsonResponse(true, $order);
            }
            break;

        case 'orders.all':
            requireRole(['admin']);
            if ($pdo) {
                $rows = $pdo->query('SELECT o.id, u.name AS customer_name, o.status, o.payment_status, o.payment_method, o.total_amount, o.created_at FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC')->fetchAll();
                jsonResponse(true, $rows);
            } else {
                jsonResponse(true, JsonFallbackService::getAllOrders());
            }
            break;

        case 'orders.status.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['order_id'] ?? 0);
            $status = (string) ($body['status'] ?? 'Pending');
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            jsonResponse(true, null, 'Order status updated');
            break;

        case 'uploads.image':
            requireRole(['admin', 'staff']);
            if (!isset($_FILES['image'])) {
                $keys = implode(', ', array_keys($_FILES));
                $len = $_SERVER['CONTENT_LENGTH'] ?? 'unknown';
                $type = $_SERVER['CONTENT_TYPE'] ?? 'unknown';
                jsonResponse(false, null, "No image uploaded. Keys: [$keys]. Length: $len. Type: $type", 400);
            }
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, null, 'Upload error: ' . $file['error'], 500);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid('img_', true) . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $url = 'uploads/' . $newName;
                jsonResponse(true, ['url' => $url], 'Image uploaded successfully');
            } else {
                jsonResponse(false, null, 'Failed to save file', 500);
            }
            break;

        case 'payments.initialize':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $orderId = (int) ($body['order_id'] ?? 0);
            
            $stmt = $pdo->prepare('SELECT o.id, o.total_amount, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.user_id = ?');
            $stmt->execute([$orderId, $uid]);
            $order = $stmt->fetch();
            
            if (!$order) {
                jsonResponse(false, null, 'Order not found', 404);
            }

            $config = require __DIR__ . '/config.php';
            $sk = $config['paystack']['secret_key'];
            $ref = 'NUFARM-' . strtoupper(bin2hex(random_bytes(6)));

            // Dynamically build the callback URL for production and local environments
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $basePath = str_replace('/backend/api.php', '', $_SERVER['SCRIPT_NAME']);
            // URL encode the path to handle special characters in the folder name
            $basePath = implode('/', array_map('rawurlencode', explode('/', $basePath)));
            
            $callbackUrl = $protocol . $host . $basePath . '/pages/customer/orders.html?verified=' . $ref;

            // Paystack Initialize Payload
            $url = "https://api.paystack.co/transaction/initialize";
            $fields = [
                'email' => $order['email'],
                'amount' => (int) ($order['total_amount'] * 100), // Kobo
                'reference' => $ref,
                'callback_url' => $callbackUrl
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $sk",
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($result, true);

            if (!$res || !$res['status']) {
                jsonResponse(false, null, 'Paystack Error: ' . ($res['message'] ?? 'Unable to connect'), 500);
            }

            $ins = $pdo->prepare('INSERT INTO payments (order_id, amount, currency, reference_code, status) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$orderId, (float) $order['total_amount'], 'NGN', $ref, 'initialized']);
            
            jsonResponse(true, [
                'authorization_url' => $res['data']['authorization_url'],
                'reference' => $ref
            ], 'Payment initialized');
            break;

        case 'payments.verify':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $reference = (string) ($body['reference'] ?? '');
            
            $stmt = $pdo->prepare('
                SELECT p.id, p.order_id, o.total_amount, u.email 
                FROM payments p 
                JOIN orders o ON o.id = p.order_id 
                JOIN users u ON u.id = o.user_id
                WHERE p.reference_code = ? AND o.user_id = ?
            ');
            $stmt->execute([$reference, $uid]);
            $payment = $stmt->fetch();

            if (!$payment) {
                jsonResponse(false, null, 'Payment reference not found', 404);
            }

            // Real Paystack Verification
            $config = require __DIR__ . '/config.php';
            $sk = $config['paystack']['secret_key'];
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $sk",
                "Cache-Control: no-cache"
            ]);
            $result = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($result, true);

            if (!$res || !$res['status'] || $res['data']['status'] !== 'success') {
                jsonResponse(false, null, 'Payment verification failed: ' . ($res['message'] ?? 'Transaction not successful'), 400);
            }

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE payments SET status = ?, raw_response = ? WHERE id = ?')
                ->execute(['success', $result, (int) $payment['id']]);
            $pdo->prepare('UPDATE orders SET payment_status = ?, status = ? WHERE id = ?')
                ->execute(['Paid', 'Paid', (int) $payment['order_id']]);
            $pdo->commit();

            // Fetch items for the email
            $itemsStmt = $pdo->prepare('
                SELECT p.name, oi.quantity, oi.unit_price as price
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
            ');
            $itemsStmt->execute([(int) $payment['order_id']]);
            $items = $itemsStmt->fetchAll();

            // Send Email
            EmailService::sendOrderSummary($payment['email'], (int) $payment['order_id'], (float) $payment['total_amount'], $items);

            jsonResponse(true, null, 'Payment verified and confirmation sent');
            break;

        case 'livestock.list':
            requireRole(['admin', 'staff']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT * FROM livestock ORDER BY id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getLivestock());
            }
            break;
        case 'livestock.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Adding livestock requires a database connection.', 503);
            }
            $stmt = $pdo->prepare('INSERT INTO livestock (animal_type, quantity, age_stage, health_status, note) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(string) ($body['animal_type'] ?? ''), (int) ($body['quantity'] ?? 0), (string) ($body['age_stage'] ?? ''), (string) ($body['health_status'] ?? ''), (string) ($body['note'] ?? '')]);
            jsonResponse(true, null, 'Livestock added');
            break;

        case 'crops.list':
            requireRole(['admin', 'staff']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT * FROM crop_cycles ORDER BY id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getCrops());
            }
            break;
        case 'crops.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Adding crops requires a database connection.', 503);
            }
            $stmt = $pdo->prepare('INSERT INTO crop_cycles (crop_name, planted_on, expected_harvest_on, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([(string) ($body['crop_name'] ?? ''), (string) ($body['planted_on'] ?? date('Y-m-d')), (string) ($body['expected_harvest_on'] ?? null), (string) ($body['status'] ?? 'Planted')]);
            jsonResponse(true, null, 'Crop cycle added');
            break;

        case 'inventory.list':
            requireRole(['admin', 'staff']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT * FROM inventory_items ORDER BY id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getInventory());
            }
            break;
        case 'inventory.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Adding inventory requires a database connection.', 503);
            }
            $stmt = $pdo->prepare('INSERT INTO inventory_items (name, category, quantity, unit, reorder_level) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(string) ($body['name'] ?? ''), (string) ($body['category'] ?? ''), (float) ($body['quantity'] ?? 0), (string) ($body['unit'] ?? 'pcs'), (float) ($body['reorder_level'] ?? 0)]);
            jsonResponse(true, null, 'Inventory item added');
            break;

        case 'tasks.list':
            $user = requireRole(['admin', 'staff']);
            if ($pdo) {
                if ($user['role'] === 'staff') {
                    $stmt = $pdo->prepare('SELECT t.* FROM task_assignments ta JOIN farm_tasks t ON t.id = ta.task_id WHERE ta.staff_id = ? ORDER BY t.id DESC');
                    $stmt->execute([(int) $user['id']]);
                } else {
                    $stmt = $pdo->query('SELECT * FROM farm_tasks ORDER BY id DESC');
                }
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getTasks($user));
            }
            break;
        case 'tasks.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $admin = requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Creating tasks requires a database connection.', 503);
            }
            $stmt = $pdo->prepare('INSERT INTO farm_tasks (title, description, priority, status, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([(string) ($body['title'] ?? ''), (string) ($body['description'] ?? ''), (string) ($body['priority'] ?? 'Medium'), 'Todo', (string) ($body['due_date'] ?? null), (int) $admin['id']]);
            $taskId = (int) $pdo->lastInsertId();
            if (!empty($body['staff_id'])) {
                $pdo->prepare('INSERT INTO task_assignments (task_id, staff_id) VALUES (?, ?)')->execute([$taskId, (int) $body['staff_id']]);
            }
            jsonResponse(true, null, 'Task created');
            break;
        case 'tasks.update.status':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            if (!$pdo) {
                jsonResponse(false, null, 'Updating tasks requires a database connection.', 503);
            }
            $taskId = (int) ($body['task_id'] ?? 0);
            $status = (string) ($body['status'] ?? 'Todo');
            $staff = requireRole(['admin', 'staff']);
            if ($staff['role'] === 'staff') {
                $check = $pdo->prepare('SELECT id FROM task_assignments WHERE task_id = ? AND staff_id = ?');
                $check->execute([$taskId, (int) $staff['id']]);
                if (!$check->fetch()) {
                    jsonResponse(false, null, 'Forbidden', 403);
                }
            }
            $pdo->prepare('UPDATE farm_tasks SET status = ? WHERE id = ?')->execute([$status, $taskId]);
            jsonResponse(true, null, 'Task updated');
            break;

        case 'finance.expenses.list':
            requireRole(['admin']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT * FROM expenses ORDER BY expense_date DESC, id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getExpenses());
            }
            break;
        case 'finance.expenses.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Adding expenses requires a database connection.', 503);
            }
            $pdo->prepare('INSERT INTO expenses (title, amount, expense_date, category, note) VALUES (?, ?, ?, ?, ?)')
                ->execute([(string) ($body['title'] ?? ''), (float) ($body['amount'] ?? 0), (string) ($body['expense_date'] ?? date('Y-m-d')), (string) ($body['category'] ?? 'General'), (string) ($body['note'] ?? '')]);
            jsonResponse(true, null, 'Expense added');
            break;
        case 'finance.income.list':
            requireRole(['admin']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT * FROM income_entries ORDER BY income_date DESC, id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getIncome());
            }
            break;
        case 'finance.income.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            if (!$pdo) {
                jsonResponse(false, null, 'Adding income requires a database connection.', 503);
            }
            $pdo->prepare('INSERT INTO income_entries (title, amount, income_date, source, note) VALUES (?, ?, ?, ?, ?)')
                ->execute([(string) ($body['title'] ?? ''), (float) ($body['amount'] ?? 0), (string) ($body['income_date'] ?? date('Y-m-d')), (string) ($body['source'] ?? 'General'), (string) ($body['note'] ?? '')]);
            jsonResponse(true, null, 'Income added');
            break;

        case 'admin.users.list':
            requireRole(['admin']);
            if ($pdo) {
                jsonResponse(true, $pdo->query('SELECT id, name, email, role, phone, address, created_at FROM users ORDER BY id DESC')->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getUsers());
            }
            break;

        case 'admin.users.update':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $id = (int) ($body['user_id'] ?? 0);
            $name = trim((string) ($body['name'] ?? ''));
            $phone = trim((string) ($body['phone'] ?? ''));
            $address = trim((string) ($body['address'] ?? ''));
            $role = trim((string) ($body['role'] ?? 'customer'));

            $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ?, role = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $address, $role, $id]);
            jsonResponse(true, null, 'User updated successfully');
            break;

        case 'admin.users.delete':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $admin = requireRole(['admin']);
            $id = (int) ($body['user_id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(false, null, 'Invalid user ID', 422);
            }
            // Prevent admin from deleting themselves
            if ($id === (int) $admin['id']) {
                jsonResponse(false, null, 'You cannot delete your own account', 403);
            }
            // Clean up related records first
            $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM wishlists WHERE user_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM task_assignments WHERE staff_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM notification_reads WHERE user_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM sessions_audit WHERE user_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                jsonResponse(false, null, 'User not found', 404);
            }
            jsonResponse(true, null, 'User deleted successfully');
            break;

        case 'reports.summary':
            requireRole(['admin']);
            if ($pdo) {
                $summary = [
                    'total_users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                    'total_products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
                    'total_orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
                    'paid_orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'Paid'")->fetchColumn(),
                    'expense_total' => (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM expenses')->fetchColumn(),
                    'income_total' => (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM income_entries')->fetchColumn(),
                ];
                jsonResponse(true, $summary);
            } else {
                jsonResponse(true, JsonFallbackService::getReportsSummary());
            }
            break;

        case 'notifications.list':
            $uid = requireAuth();
            if ($pdo) {
                $stmt = $pdo->prepare('SELECT id, title, body, type, created_at FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC');
                $stmt->execute([$uid]);
                jsonResponse(true, $stmt->fetchAll());
            } else {
                jsonResponse(true, JsonFallbackService::getNotifications($uid));
            }
            break;
        case 'notifications.create':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            requireRole(['admin']);
            $pdo->prepare('INSERT INTO notifications (user_id, title, body, type) VALUES (?, ?, ?, ?)')
                ->execute([isset($body['user_id']) ? (int) $body['user_id'] : null, (string) ($body['title'] ?? 'Update'), (string) ($body['body'] ?? ''), (string) ($body['type'] ?? 'info')]);
            jsonResponse(true, null, 'Notification created');
            break;

        case 'notifications.mark.read':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $uid = requireAuth();
            $notificationId = (int) ($body['notification_id'] ?? 0);
            $pdo->prepare('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)')
                ->execute([$notificationId, $uid]);
            jsonResponse(true, null, 'Marked as read');
            break;

        case 'emails.log':
            requireRole(['admin']);
            jsonResponse(true, $pdo->query('SELECT * FROM email_logs ORDER BY id DESC LIMIT 100')->fetchAll());
            break;

        case 'push.subscribe':
            if ($method !== 'POST') {
                jsonResponse(false, null, 'Method not allowed', 405);
            }
            $endpoint = trim((string) ($body['endpoint'] ?? ''));
            $keys = $body['keys'] ?? [];
            $p256dh = trim((string) ($keys['p256dh'] ?? ''));
            $auth = trim((string) ($keys['auth'] ?? ''));
            if ($endpoint === '' || $p256dh === '' || $auth === '') {
                jsonResponse(false, null, 'Invalid subscription data', 422);
            }
            $userId = currentUserId();
            $stmt = $pdo->prepare(
                'INSERT INTO push_subscriptions (user_id, endpoint, keys_p256dh, keys_auth)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE keys_p256dh = VALUES(keys_p256dh), keys_auth = VALUES(keys_auth), user_id = VALUES(user_id)'
            );
            $stmt->execute([$userId, $endpoint, $p256dh, $auth]);
            jsonResponse(true, null, 'Push subscription saved');
            break;

        case 'products.latest_notification':
            $stmt = $pdo->query('SELECT id, name, description, price, stock, category, image_url FROM products ORDER BY id DESC LIMIT 1');
            $product = $stmt->fetch();
            if ($product && !empty($product['image_url']) && !str_starts_with($product['image_url'], 'http')) {
                $baseUrl = getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' : 'http://localhost/');
                $product['image_url'] = $baseUrl . ltrim($product['image_url'], '/');
            }
            jsonResponse(true, $product);
            break;

        default:
            jsonResponse(false, null, 'Unknown route', 404);
    }
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}
