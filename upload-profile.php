<?php
require_once "auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        echo json_encode(["status" => "error", "success" => false, "message" => "Email wajib diisi!"]);
        exit();
    }

    $user = resolve_user($email);
    if (!$user) {
        echo json_encode(["status" => "error", "success" => false, "message" => "Pengguna tidak ditemukan!"]);
        exit();
    }
    $userId = (int)$user['id'];

    // 👇 TAMBAHAN: HANDLE HAPUS FOTO PROFIL
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $stmtGet = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmtGet->bind_param("i", $userId);
        $stmtGet->execute();
        $res = $stmtGet->get_result();
        $row = $res->fetch_assoc();
        $stmtGet->close();

        if ($row && !empty($row['profile_image']) && file_exists($row['profile_image'])) {
            unlink($row['profile_image']);
        }

        $stmtDel = $conn->prepare("UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE id = ?");
        $stmtDel->bind_param("i", $userId);

        if ($stmtDel->execute()) {
            echo json_encode(["status" => "success", "success" => true, "message" => "Foto profil berhasil dihapus."]);
        } else {
            echo json_encode(["status" => "error", "success" => false, "message" => "Gagal menghapus foto di database."]);
        }
        $stmtDel->close();
        $conn->close();
        exit();
    }
    // 👆 SAMPAI SINI TAMBAHAN

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // ✅ VALIDASI MIME-TYPE FILE (hanya gambar: JPG/PNG/WebP)
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];

        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
            }
        }
        if ($mimeType === null && function_exists('mime_content_type')) {
            $mimeType = mime_content_type($_FILES['image']['tmp_name']);
        }
        if ($mimeType === null) {
            $mimeType = (isset($_FILES['image']['type'])) ? $_FILES['image']['type'] : '';
        }

        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($mimeType, $allowedMime, true) || !in_array($fileExtension, $allowedExt, true)) {
            echo json_encode([
                "status" => "error",
                "success" => false,
                "message" => "File harus berupa gambar dengan format JPG, PNG, atau WebP!"
            ]);
            $conn->close();
            exit();
        }

        $newFileName = "profile_" . time() . "_" . rand(1000, 9999) . "." . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
            // Update path gambar di database
            $stmt = $conn->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $targetFilePath, $userId);

            if ($stmt->execute()) {
                echo json_encode([
                    "status" => "success",
                    "success" => true,
                    "message" => "Foto profil berhasil diperbarui!",
                    "profile_image" => $targetFilePath
                ]);
            } else {
                echo json_encode(["status" => "error", "success" => false, "message" => "Gagal memperbarui database."]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "success" => false, "message" => "Gagal mengunggah file ke server."]);
        }
    } else {
        echo json_encode(["status" => "error", "success" => false, "message" => "Tidak ada file gambar yang dikirim."]);
    }
} else {
    echo json_encode(["status" => "error", "success" => false, "message" => "Metode request tidak valid."]);
}

$conn->close();
?>