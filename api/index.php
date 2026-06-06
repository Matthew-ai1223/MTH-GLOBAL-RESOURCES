<?php
/**
 * Vercel Entry Point
 * This file acts as a bridge to the main backend logic.
 */

// TEMPORARY: Enable errors to diagnose 500 error
error_reporting(E_ALL);
ini_set('display_errors', '1');

$backendFile = __DIR__ . '/../backend/api.php';

if (file_exists($backendFile)) {
    require_once $backendFile;
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Critical Error: Backend API not found at $backendFile",
        "debug" => [
            "dir" => __DIR__,
            "cwd" => getcwd(),
            "file" => $backendFile
        ]
    ]);
}
