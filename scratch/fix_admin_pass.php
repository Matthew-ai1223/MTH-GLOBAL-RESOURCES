<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=nu_farm', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $p = password_hash('admin2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $stmt->execute([$p, '@admin2026']);
    
    echo "Password updated successfully for @admin2026\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
