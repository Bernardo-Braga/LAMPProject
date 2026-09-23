<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["firstName"]) || empty($data["lastName"]) || empty($data["login"]) || empty($data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT ID FROM Users WHERE Login = ?");
    $stmt->execute([$data["login"]]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Username already exists"]);
        exit();
    }

    $hashedPassword = password_hash($data["password"], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO Users (FirstName, LastName, Login, Password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data["firstName"], $data["lastName"], $data["login"], $hashedPassword]);

    $newUserId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        "id"    => $newUserId,
        "error" => ""
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Registration failed due to a database error"]);
}
?>
