<?php
require_once __DIR__ . '/db.php';

/**
 * Baca token dari header Authorization: Bearer <token>
 * (juga mendukung $_SERVER['HTTP_AUTHORIZATION'] untuk host non-Apache).
 */
function get_bearer_token(): ?string {
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    if (!is_array($headers)) {
        $headers = [];
    }
    $authHeader = null;
    foreach ($headers as $key => $value) {
        if (strtolower((string)$key) === 'authorization') {
            $authHeader = (string)$value;
            break;
        }
    }
    if ($authHeader === null && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = (string)$_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($authHeader === null) {
        return null;
    }
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Cari user berdasarkan auth_token.
 * Mengembalikan array user (id, username, email, profile_image) atau null.
 */
function get_user_by_token(string $token) {
    global $conn;
    if ($token === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, username, email, profile_image FROM users WHERE auth_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Resolusi user yang sedang aktif:
 * 1. Jika header Authorization (Bearer token) valid -> pakai user dari token.
 * 2. Jika token tidak ada / tidak valid -> fallback ke lookup berbasis email (sesi lama).
 *
 * Dengan cara ini sistem token aktif untuk semua request baru dari aplikasi,
 * namun tidak memutus sesi lama yang masih mengirim email.
 */
function resolve_user(?string $email = null) {
    $token = get_bearer_token();
    if ($token !== null) {
        $user = get_user_by_token($token);
        if ($user) {
            return $user;
        }
        // Token ada tapi tidak valid -> jangan jatuh ke email fallback (anti-spoofing)
        return null;
    }
    if ($email === null || $email === '') {
        return null;
    }
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, email, profile_image FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Verifikasi password dengan BCRYPT.
 * Untuk password lama (plain text dari versi aplikasi terdahulu) yang cocok,
 * otomatis dimigrasikan menjadi hash BCRYPT. Setelah migrasi, akun memakai BCRYPT.
 */
function verify_password_and_migrate(string $inputPassword, string $storedPassword, int $userId): bool {
    global $conn;
    if (password_verify($inputPassword, $storedPassword)) {
        return true;
    }
    $isBcrypt = preg_match('/^\$2[aby]\$/', $storedPassword) === 1;
    if (!$isBcrypt && $inputPassword === $storedPassword) {
        $newHash = password_hash($inputPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $userId);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}
