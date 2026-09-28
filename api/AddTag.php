<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin();

// { "name": "Family", "color": "#ec4899" }
$data = validateFields(getRequestData() ?? [], ["name" => ["required", "max:40"], "color" => ["color"]]);

$check = $pdo->prepare("SELECT ID FROM Tags WHERE UserID = ? AND Name = ?");
$check->execute([$user["ID"], $data["name"]]);
if ($check->fetch()) {
    fail(422, "Please fix the highlighted fields", ["name" => "You already have a tag with that name"]);
}

$pdo->prepare("INSERT INTO Tags (UserID, Name, Color) VALUES (?, ?, ?)")
    ->execute([$user["ID"], $data["name"], $data["color"] ?? "#ec4899"]);

http_response_code(201);
echo json_encode(["id" => (int)$pdo->lastInsertId(), "error" => ""]);
?>
