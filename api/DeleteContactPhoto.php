<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 12 }
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "edit");
$pdo->prepare("DELETE FROM ContactPhotos WHERE ContactID = ?")->execute([$contact["ID"]]);
$pdo->prepare("UPDATE Contacts SET PhotoUpdatedAt = NULL, DateUpdated = NOW() WHERE ID = ?")->execute([$contact["ID"]]);

http_response_code(200);
echo json_encode(["error" => ""]);
?>
