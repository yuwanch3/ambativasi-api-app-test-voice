<?php
require_once "auth.php";

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Email tidak boleh kosong!"
    ]);
    exit();
}

$user = resolve_user($email);

if ($user) {
    echo json_encode([
        "status" => "success",
        "success" => true,
        "username" => $user['username'],
        "email" => $user['email'],
        "profile_image" => $user['profile_image']
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Pengguna tidak ditemukan!"
    ]);
}