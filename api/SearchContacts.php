<?php
require_once "db.php";

$userId = $_GET["userId"] ?? null;
$term = $_GET["term"] ?? "";

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing userId"]);
    exit();
}

try {
    if ($term !== "") {
        $like = "%" . $term . "%";
        $stmt = $pdo->prepare("SELECT ID, FirstName, LastName, Cell, Email FROM Contacts
            WHERE UserID = ? AND (FirstName LIKE ? OR LastName LIKE ? OR Cell LIKE ? OR Email LIKE ?)");
        $stmt->execute([$userId, $like, $like, $like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT ID, FirstName, LastName, Cell, Email FROM Contacts WHERE UserID = ?");
        $stmt->execute([$userId]);
    }

    $contacts = $stmt->fetchAll();
    http_response_code(200);
    echo json_encode($contacts);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Search failed"]);
}
?>
