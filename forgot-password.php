<?php
require_once "db.php";
require_once "env.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SMTP_EMAIL', env_get('SMTP_EMAIL'));
define('SMTP_PASSWORD', env_get('SMTP_PASSWORD'));

if (file_exists("vendor/autoload.php")) {
    require 'vendor/autoload.php';
} else {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

$input = json_decode(file_get_contents("php://input"), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email)) {
    echo json_encode([
        "success" => false,
        "status" => "error",
        "message" => "Silakan masukkan email Anda!"
    ]);
    exit();
}

// ✅ RATE-LIMIT ANTI-SPAM: minimal 60 detik antar request per email
// Berlaku untuk SEMUA email (terdaftar atau tidak), jadi tidak bisa di-bypass
// oleh user yang belum punya token reset.
$minWait = 60;
$rlStmt = $conn->prepare("SELECT last_request_at FROM password_reset_attempts WHERE email = ?");
$rlStmt->bind_param("s", $email);
$rlStmt->execute();
$rlRes = $rlStmt->get_result();
$rlRow = $rlRes->fetch_assoc();
$rlStmt->close();

$lastAt = $rlRow ? strtotime($rlRow['last_request_at']) : null;
if ($lastAt !== null) {
    $wait = $minWait - (time() - $lastAt);
    if ($wait > 0) {
        echo json_encode([
            "success" => false,
            "status" => "error",
            "message" => "Harap tunggu " . $wait . " detik lagi sebelum meminta email baru!"
        ]);
        $conn->close();
        exit();
    }
}

// Catat waktu request (upsert)
$recordStmt = $conn->prepare("INSERT INTO password_reset_attempts (email, last_request_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_request_at = NOW()");
$recordStmt->bind_param("s", $email);
$recordStmt->execute();
$recordStmt->close();

$stmt = $conn->prepare("SELECT id, username, token_expires, TIMESTAMPDIFF(SECOND, NOW(), token_expires) AS sisa_detik FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $sisa_detik = intval($user['sisa_detik']);

    if ($sisa_detik > 0) {
        echo json_encode([
            "success" => false,
            "status" => "error",
            "message" => "Harap tunggu " . $sisa_detik . " detik lagi sebelum meminta email baru!"
        ]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $newToken = bin2hex(random_bytes(32));
    
    $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE email = ?");
    $updateStmt->bind_param("ss", $newToken, $email);
    $updateStmt->execute();
    $updateStmt->close();

    // 💡 LINK HTTPS PERANTARA AGAR DITERIMA DENGAN AMAN OLEH GMAIL
    $encodedEmail = urlencode($email);
    $baseUrl = env_get('BASE_URL', 'https://ambativasi.page.gd/ambativasi-api');
    $directAppLink = $baseUrl . "/reset-redirect.php?email=" . $encodedEmail . "&token=" . urlencode($newToken);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASSWORD; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mail->Username, 'Ambativasi Support');
        $mail->addReplyTo($mail->Username, 'Ambativasi Support');
        $mail->addAddress($email, $user['username']);

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);
        $mail->AltBody = 'Halo ' . $user['username'] . ',

Kami menerima permintaan untuk mengatur ulang kata sandi akun Ambativasi Anda.

Buka link berikut untuk membuat kata sandi baru (token berlaku 30 menit):
' . $directAppLink . '

Jika Anda tidak meminta ini, abaikan email ini.

Salam hangat,
Tim Ambativasi';
        $mail->Subject = 'Instruksi Pemulihan Kata Sandi - Ambativasi';
        $mail->Body    = '
            <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; background-color: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="color: #2563EB; margin: 0; font-size: 24px;">Ambativasi App</h2>
                    <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Pemulihan Kata Sandi Akun</p>
                </div>
                
                <p style="color: #334155; font-size: 15px;">Halo <b>' . htmlspecialchars($user['username']) . '</b>,</p>
                <p style="color: #334155; font-size: 15px; line-height: 1.5;">Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Ketuk tombol di bawah ini untuk langsung membuka aplikasi dan membuat kata sandi baru:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $directAppLink . '" style="background-color: #2563EB; color: #ffffff; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 15px; display: inline-block;">
                        🔑 Atur Ulang Kata Sandi
                    </a>
                </div>

                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
                <p style="color: #94a3b8; font-size: 12px; text-align: center; margin: 0;">Salam hangat,<br><b>Tim Ambativasi</b></p>
            </div>
        ';

        $mail->send();

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Instruksi pemulihan & link direct berhasil dikirim ke email Anda!"
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "status" => "error",
            "message" => "Gagal mengirim email: " . $mail->ErrorInfo
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email tidak terdaftar di sistem kami!"
    ]);
}

$stmt->close();
$conn->close();
?>