<?php
$pdo = new PDO('mysql:host=localhost;dbname=nu_farm', 'root', '');
$p = password_hash('admin2026', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
$stmt->execute(['Administrator', '@admin2026', $p, 'admin']);
echo 'Admin user created successfully';
