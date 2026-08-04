<?php
require_once "db.php";

// Membaca data JSON yang dikirimkan dari React Native (ResetPasswordScreen)
$input = json_decode(file_get_contents("php://input"), true);

$email    = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$token    = isset($input['token']) ? trim($input['token']) : '';

// 1. Validasi Input Kosong
if (empty($email) || empty($password)) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Email dan kata sandi baru tidak boleh kosong!"
    ]);
    exit();
}

// 1b. Token reset wajib ada (pengaman: mencegah reset oleh orang lain)
if (empty($token)) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Token reset tidak valid atau sudah kedaluwarsa!"
    ]);
    exit();
}

// 2. Cek Apakah Email Terdaftar & Token Masih Valid di Database MySQL
$check_stmt = $conn->prepare("SELECT id, reset_token, token_expires FROM users WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Akun dengan email tersebut tidak ditemukan!"
    ]);
    $check_stmt->close();
    $conn->close();
    exit();
}

$user = $check_result->fetch_assoc();
$tokenValid = !empty($user['reset_token'])
    && hash_equals($user['reset_token'], $token)
    && strtotime($user['token_expires']) > time();

$check_stmt->close();

if (!$tokenValid) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Token reset tidak valid atau sudah kedaluwarsa. Silakan minta ulang!"
    ]);
    $conn->close();
    exit();
}

// 3. Enkripsi Kata Sandi Baru dengan BCRYPT
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// 4. Update Password Baru & Hapus Token (Sekali Pakai) ke Database
$stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expires = NULL WHERE email = ?");
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    // Memicu json.success = true di React Native -> Menampilkan Toast Success
    echo json_encode([
        "success" => true,
        "status" => "success",
        "message" => "Password berhasil diubah!"
    ]);
} else {
    // Memicu json.success = false di React Native -> Menampilkan Toast Error
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Gagal mengbarui password di database. Silakan coba lagi!"
    ]);
}

$stmt->close();
$conn->close();
?>