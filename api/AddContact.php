<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["userId"]) || empty($data["firstName"]) || empty($data["lastName"]) || empty($data["cell"]) || empty($data["email"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO Contacts (FirstName, LastName, Cell, Email, UserID) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data["firstName"], $data["lastName"], $data["cell"], $data["email"], $data["userId"]]);

    http_response_code(201);
    echo json_encode(["id" => (int)$pdo->lastInsertId(), "error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add contact"]);
}
?>
