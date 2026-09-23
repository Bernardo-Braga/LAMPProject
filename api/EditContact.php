<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["id"]) || empty($data["userId"]) || empty($data["firstName"]) || empty($data["lastName"]) || empty($data["cell"]) || empty($data["email"])) {
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

    $stmt = $pdo->prepare("UPDATE Contacts SET FirstName=?, LastName=?, Cell=?, Email=? WHERE ID=?");
    $stmt->execute([$data["firstName"], $data["lastName"], $data["cell"], $data["email"], $data["id"]]);

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}
?>
