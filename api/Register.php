<?php
require_once "common.php";
requireMethod("POST");

$data = getRequestData();

if (empty($data["firstName"]) || empty($data["lastName"]) || empty($data["login"]) || empty($data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// Username 3-50 simple characters, password at least 8 characters (422 with a message per field).
$data = validateFields($data, [
    "firstName" => ["required", "max:50"],
    "lastName" => ["required", "max:50"],
    "login" => ["required", "username"],
    "password" => passwordRules(),
]);

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
    logActivity("user.registered", "created an account", "user", $newUserId, null, $newUserId);

    http_response_code(201);
    echo json_encode([
        "id"    => $newUserId,
        "error" => ""
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === "23000") { // unique username index: someone took it a moment ago
        http_response_code(409);
        echo json_encode(["error" => "Username already exists"]);
        exit();
    }
    http_response_code(500);
    echo json_encode(["error" => "Registration failed due to a database error"]);
}
?>
