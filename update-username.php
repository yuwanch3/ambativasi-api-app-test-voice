<?php
require_once "auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $newUsername = isset($_POST['new_username']) ? trim($_POST['new_username']) : (isset($_POST['username']) ? trim($_POST['username']) : '');

    // Fallback: kalau request-nya JSON, bukan form-data
    if (empty($email) || empty($newUsername)) {
        $jsonInput = json_decode(file_get_contents("php://input"), true);
        if ($jsonInput) {
            if (empty($email) && isset($jsonInput['email'])) {
                $email = trim($jsonInput['email']);
            }
            if (empty($newUsername)) {
                if (isset($jsonInput['new_username'])) {
                    $newUsername = trim($jsonInput['new_username']);
                } elseif (isset($jsonInput['username'])) {
                    $newUsername = trim($jsonInput['username']);
                }
            }
        }
    }

    if (empty($email) || empty($newUsername)) {
        echo json_encode(["status" => "error", "success" => false, "message" => "Email dan username baru wajib diisi!"]);
        exit();
    }

    $user = resolve_user($email);
    if (!$user) {
        echo json_encode(["status" => "error", "success" => false, "message" => "Pengguna tidak ditemukan!"]);
        exit();
    }
    $userId = (int)$user['id'];

    // Cek apakah username sudah dipakai user lain
    $stmtCheck = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCheck->bind_param("si", $newUsername, $userId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        echo json_encode(["status" => "error", "success" => false, "message" => "Username sudah digunakan, silakan pilih yang lain."]);
        $stmtCheck->close();
        $conn->close();
        exit();
    }
    $stmtCheck->close();

    // Update username di database
    $stmt = $conn->prepare("UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newUsername, $userId);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Username berhasil diperbarui!",
            "username" => $newUsername
        ]);
    } else {
        echo json_encode(["status" => "error", "success" => false, "message" => "Gagal memperbarui username di database."]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "success" => false, "message" => "Metode request tidak valid."]);
}

$conn->close();
?>