<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Response preflight request (OPTIONS) dari Ngrok / Expo
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/env.php';

$host = env_get('DB_HOST', 'localhost');
$user = env_get('DB_USER', 'root');
$pass = env_get('DB_PASS', '');
$dbname = env_get('DB_NAME', 'ambativasi');

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
} catch (mysqli_sql_exception $e) {
    $conn = null;
}

if (!$conn || $conn->connect_error) {
    $errMsg = ($conn && $conn->connect_error) ? $conn->connect_error : 'Koneksi database gagal (host tidak dapat dijangkau)';
    echo json_encode([
        "status" => "error",
        "message" => "Koneksi database gagal: " . $errMsg
    ]);
    exit();
}

// Pastikan tabel pendukung ada (aman dipanggil berulang: IF NOT EXISTS)
$conn->query("CREATE TABLE IF NOT EXISTS user_xp (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    total_xp INT(11) NOT NULL DEFAULT 0,
    attempts INT(11) NOT NULL DEFAULT 0,
    last_streak_bonus_date DATE DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Migrasi ringan: pastikan kolom auth_token ada di tabel users (idempotent)
$colCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'auth_token'");
if ($colCheck) {
    $row = $colCheck->fetch_assoc();
    if ($row && (int)$row['c'] === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN auth_token VARCHAR(255) DEFAULT NULL");
    }
}

// Tabel pembatasan frekuensi permintaan reset password (anti-spam)
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_attempts (
    email VARCHAR(100) NOT NULL,
    last_request_at DATETIME NOT NULL,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
?>