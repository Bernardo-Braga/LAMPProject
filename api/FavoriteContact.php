<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 12, "favorite": true|false }  Favorites are per user, so shared contacts can be starred too.
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "view");
$data = validateFields(getRequestData() ?? [], ["favorite" => ["required", "bool"]]);

if ($data["favorite"]) {
    $pdo->prepare("INSERT IGNORE INTO ContactFavorites (UserID, ContactID) VALUES (?, ?)")->execute([$user["ID"], $contact["ID"]]);
} else {
    $pdo->prepare("DELETE FROM ContactFavorites WHERE UserID = ? AND ContactID = ?")->execute([$user["ID"], $contact["ID"]]);
}

http_response_code(200);
echo json_encode(["id" => (int)$contact["ID"], "isFavorite" => $data["favorite"], "error" => ""]);
?>
