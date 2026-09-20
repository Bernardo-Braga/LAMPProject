<?php
require_once "db.php";

$data = getRequestData();

if (empty($data["login"]) || empty($data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing login or password"]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT ID, FirstName, LastName, Password, IsDisabled FROM Users WHERE Login = ?");
    $stmt->execute([$data["login"]]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid Credentials"]);
        exit();
    }

    if (!empty($user["IsDisabled"])) {
        http_response_code(403);
        echo json_encode(["error" => "Account suspended"]);
        exit();
    }

    $passwordValid = password_verify($data["password"], $user["Password"]);

    if ($passwordValid) {
        http_response_code(200);
        echo json_encode([
            "id"        => (int)$user["ID"],
            "firstName" => $user["FirstName"],
            "lastName"  => $user["LastName"],
            "error"     => ""
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid Credentials"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database query error"]);
}
?>
