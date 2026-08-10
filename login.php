<?php
require_once "auth.php";

$input = json_decode(file_get_contents("php://input"), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Email dan password wajib diisi!"
    ]);
    exit();
}

$stmt = $conn->prepare("SELECT id, username, email, password, profile_image FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    // Memeriksa password (hash BCRYPT, dengan migrasi otomatis untuk password lama)
    if (verify_password_and_migrate($password, $user['password'], (int)$user['id'])) {
        // Buat token sesi baru (dipakai aplikasi untuk semua request berikutnya)
        $newToken = bin2hex(random_bytes(32));
        $tokenStmt = $conn->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
        $tokenStmt->bind_param("si", $newToken, $user['id']);
        $tokenStmt->execute();
        $tokenStmt->close();

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Login berhasil!",
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "email" => $user['email'],
                "profile_image" => $user['profile_image'],
                "auth_token" => $newToken
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "status" => "error",
            "message" => "Password salah!"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Email tidak terdaftar!"
    ]);
}

$stmt->close();
$conn->close();
?>