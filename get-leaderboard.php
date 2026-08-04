<?php
require_once "db.php";

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = max(1, min(100, $limit));

if (empty($email)) {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Email tidak boleh kosong!"
    ]);
    exit();
}

$XP_PER_LEVEL = 500;

$sql = "SELECT u.id, u.username, u.email, u.profile_image, COALESCE(x.total_xp, 0) AS total_xp
        FROM users u
        LEFT JOIN user_xp x ON x.user_id = u.id
        ORDER BY total_xp DESC, u.username ASC";
$result = $conn->query($sql);

$leaderboard = [];
$myEntry = null;

if ($result) {
    $i = 0;
    while ($row = $result->fetch_assoc()) {
        $i++;
        $totalXp = (int)$row['total_xp'];
        $entry = [
            "rank" => $i,
            "username" => $row['username'],
            "email" => $row['email'],
            "profile_image" => $row['profile_image'],
            "total_xp" => $totalXp,
            "level" => (int)(floor($totalXp / $XP_PER_LEVEL) + 1)
        ];
        if ($row['email'] === $email) {
            $myEntry = $entry;
        }
        if ($i <= $limit) {
            $leaderboard[] = $entry;
        }
    }
}

echo json_encode([
    "status" => "success",
    "success" => true,
    "leaderboard" => $leaderboard,
    "me" => $myEntry
]);

$conn->close();
?>
