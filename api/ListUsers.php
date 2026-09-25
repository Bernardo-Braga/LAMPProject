<?php
require_once "db.php";

$adminId = $_GET["adminId"] ?? null;
$term = $_GET["term"] ?? "";

if (empty($adminId)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing adminId"]);
    exit();
}

try {
    $adminCheck = $pdo->prepare("SELECT Role FROM Users WHERE ID = ?");
    $adminCheck->execute([$adminId]);
    $admin = $adminCheck->fetch();

    if (!$admin || $admin["Role"] !== "Admin") {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit();
    }

    if ($term !== "") {
        $like = "%" . $term . "%";
        $stmt = $pdo->prepare("SELECT ID, FirstName, LastName, Login, Role, IsDisabled FROM Users
            WHERE FirstName LIKE ? OR LastName LIKE ? OR Login LIKE ?");
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT ID, FirstName, LastName, Login, Role, IsDisabled FROM Users");
        $stmt->execute();
    }

    $users = $stmt->fetchAll();
    http_response_code(200);
    echo json_encode($users);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Search failed"]);
}
?>
