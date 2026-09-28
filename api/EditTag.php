<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 3, "name": "Family", "color": "#2563eb" }
$tagId = requireId("id");
$data = validateFields(getRequestData() ?? [], ["name" => ["required", "max:40"], "color" => ["color"]]);

$check = $pdo->prepare("SELECT ID FROM Tags WHERE UserID = ? AND Name = ? AND ID <> ?");
$check->execute([$user["ID"], $data["name"], $tagId]);
if ($check->fetch()) {
    fail(422, "Please fix the highlighted fields", ["name" => "You already have a tag with that name"]);
}

$stmt = $pdo->prepare("UPDATE Tags SET Name = ?, Color = COALESCE(?, Color) WHERE ID = ? AND UserID = ?");
$stmt->execute([$data["name"], $data["color"], $tagId, $user["ID"]]);
$exists = $pdo->prepare("SELECT 1 FROM Tags WHERE ID = ? AND UserID = ?");
$exists->execute([$tagId, $user["ID"]]);
if (!$exists->fetch()) {
    fail(404, "Tag not found");
}

http_response_code(200);
echo json_encode(["error" => ""]);
?>
