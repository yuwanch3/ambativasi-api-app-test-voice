<?php
require_once "auth.php";

$input = json_decode(file_get_contents("php://input"), true);
$current_email = isset($input['current_email']) ? trim($input['current_email']) : '';
$new_email = isset($input['new_email']) ? trim($input['new_email']) : '';

if (empty($current_email) || empty($new_email)) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Email lama dan email baru harus diisi!"]);
    exit();
}

$user = resolve_user($current_email);
if (!$user) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Pengguna tidak ditemukan!"]);
    exit();
}
$userId = (int)$user['id'];

// Cek apakah email baru sudah terpakai
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$check_stmt->bind_param("si", $new_email, $userId);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Email baru sudah digunakan akun lain!"]);
    $check_stmt->close();
    exit();
}
$check_stmt->close();

$update_stmt = $conn->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
$update_stmt->bind_param("si", $new_email, $userId);

if ($update_stmt->execute()) {
    echo json_encode(["status" => "success", "success" => true, "message" => "Alamat email berhasil diperbarui!"]);
} else {
    echo json_encode(["status" => "error", "success" => false, "message" => "Gagal memperbarui email."]);
}

$update_stmt->close();
$conn->close();
?>