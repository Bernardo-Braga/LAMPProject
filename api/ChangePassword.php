<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["adminId"]) || empty($data["targetUserId"]) || empty($data["newPassword"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

try {
    $adminCheck = $pdo->prepare("SELECT Role FROM Users WHERE ID = ?");
    $adminCheck->execute([$data["adminId"]]);
    $admin = $adminCheck->fetch();

    if (!$admin || $admin["Role"] !== "Admin") {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit();
    }

    $hash = password_hash($data["newPassword"], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE Users SET Password = ? WHERE ID = ?");
    $stmt->execute([$hash, $data["targetUserId"]]);

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Password change failed"]);
}
?>
