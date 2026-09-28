<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

$data = getRequestData();

if (empty($data["firstName"]) || empty($data["lastName"]) || empty($data["login"]) || empty($data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// "role" can be "User" to create a regular account; it defaults to Admin, as before.
$clean = validateFields($data, [
    "firstName" => ["required", "max:50"],
    "lastName" => ["required", "max:50"],
    "login" => ["required", "username"],
    "password" => passwordRules(),
    "role" => ["in:Admin,User"],
    "mustChangePassword" => ["bool"],
]);
$role = $clean["role"] ?? "Admin";
$mustChange = $clean["mustChangePassword"] ?? true; // new accounts pick their own password at first sign-in

try {
    $check = $pdo->prepare("SELECT ID FROM Users WHERE Login = ?");
    $check->execute([$clean["login"]]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Login already exists"]);
        exit();
    }

    $hash = password_hash($clean["password"], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Role, MustChangePassword) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$clean["firstName"], $clean["lastName"], $clean["login"], $hash, $role, $mustChange ? 1 : 0]);
    $newId = (int)$pdo->lastInsertId();
    logActivity("admin.user_created", "created $role account " . $clean["login"], "user", $newId, $newId);

    http_response_code(201);
    echo json_encode(["id" => $newId, "error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Admin creation failed"]);
}
?>
