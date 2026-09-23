<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["adminId"]) || empty($data["firstName"]) || empty($data["lastName"]) || empty($data["login"]) || empty($data["password"])) {
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

    $check = $pdo->prepare("SELECT ID FROM Users WHERE Login = ?");
    $check->execute([$data["login"]]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Login already exists"]);
        exit();
    }

    $hash = password_hash($data["password"], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Role) VALUES (?, ?, ?, ?, 'Admin')");
    $stmt->execute([$data["firstName"], $data["lastName"], $data["login"], $hash]);

    http_response_code(201);
    echo json_encode(["id" => (int)$pdo->lastInsertId(), "error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Admin creation failed"]);
}
?>
