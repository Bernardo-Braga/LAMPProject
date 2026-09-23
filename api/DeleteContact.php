<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["id"]) || empty($data["userId"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

try {
    $check = $pdo->prepare("SELECT ID FROM Contacts WHERE ID = ? AND UserID = ?");
    $check->execute([$data["id"], $data["userId"]]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(["error" => "Not your contact"]);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM Contacts WHERE ID = ?");
    $stmt->execute([$data["id"]]);

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Delete failed"]);
}
?>
