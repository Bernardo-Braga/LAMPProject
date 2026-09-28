<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 3 }  The tag is removed from contacts; the contacts themselves are kept.
$stmt = $pdo->prepare("DELETE FROM Tags WHERE ID = ? AND UserID = ?");
$stmt->execute([requireId("id"), $user["ID"]]);
if ($stmt->rowCount() === 0) {
    fail(404, "Tag not found");
}

http_response_code(200);
echo json_encode(["error" => ""]);
?>
