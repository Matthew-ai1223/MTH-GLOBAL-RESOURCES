<?php
require_once __DIR__ . '/../backend/db.php';

try {
    $pdo = db();
    $r = $pdo->query('SHOW CREATE TABLE push_subscriptions');
    $row = $r->fetch();
    echo $row['Create Table'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // Table doesn't exist, create it
    echo "Creating push_subscriptions table...\n";
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `endpoint` text NOT NULL,
        `keys_p256dh` varchar(255) NOT NULL,
        `keys_auth` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_endpoint` (`endpoint`(500))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table created successfully!\n";
}
