<?php
require_once "auth.php";

$input = json_decode(file_get_contents("php://input"), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$correct = isset($input['correct']) ? (int)$input['correct'] : 0;
$total = isset($input['total']) ? (int)$input['total'] : 0;
$streak = isset($input['streak']) ? (int)$input['streak'] : 0;

if (empty($email)) {
    echo json_encode([
        "status" => "error",
        "message" => "Email tidak boleh kosong!"
    ]);
    exit();
}

$user = resolve_user($email);

if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "Pengguna tidak ditemukan!"
    ]);
    exit();
}
$userId = (int)$user['id'];

$XP_PER_LEVEL = 500;

$xp = $total > 0 ? (int)round(($correct / $total) * 100) : 0;
$xp = max(1, $xp);

$bonus = 0;
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT total_xp, attempts, last_streak_bonus_date FROM user_xp WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$xpRow = $res->fetch_assoc();

if ($streak >= 7 && (!$xpRow || $xpRow['last_streak_bonus_date'] !== $today)) {
    $bonus = 25;
}

$xp += $bonus;

if ($xpRow) {
    $newTotal = (int)$xpRow['total_xp'] + $xp;
    $newAttempts = (int)$xpRow['attempts'] + 1;
    $newDate = $bonus > 0 ? $today : $xpRow['last_streak_bonus_date'];
    $stmt = $conn->prepare("UPDATE user_xp SET total_xp = ?, attempts = ?, last_streak_bonus_date = ? WHERE user_id = ?");
    $stmt->bind_param("iisi", $newTotal, $newAttempts, $newDate, $userId);
    $stmt->execute();
} else {
    $newTotal = $xp;
    $newDate = $bonus > 0 ? $today : null;
    $firstAttempt = 1;
    $stmt = $conn->prepare("INSERT INTO user_xp (user_id, total_xp, attempts, last_streak_bonus_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $userId, $newTotal, $firstAttempt, $newDate);
    $stmt->execute();
}

echo json_encode([
    "status" => "success",
    "success" => true,
    "message" => "XP berhasil disimpan!",
    "total_xp" => $newTotal,
    "level" => (int)(floor($newTotal / $XP_PER_LEVEL) + 1),
    "xp_gained" => $xp
]);

$conn->close();
?>
