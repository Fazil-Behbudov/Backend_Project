<?php
/**
 * Example Database Configuration using PDO
 * Copy to config.php and set credentials.
 */

// Database Configuration (replace with your credentials)
define('DB_HOST', 'mysql-fazilbehbudov.alwaysdata.net');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_db_name');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

function generateAnonymousCode($id, $prefix = 'EXP') {
    return $prefix . '-' . base64_encode($id . time());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    if (!isset($_SESSION['experience_codes'])) {
        $_SESSION['experience_codes'] = [];
    }
    if (!isset($_SESSION['user_codes'])) {
        $_SESSION['user_codes'] = [];
    }
}
