<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 12, "userId": 7 }  The owner can remove anyone; a recipient can remove themselves.
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "view");
$userId = requireId("userId");

if ($contact["Access"] !== "owner" && $userId !== (int)$user["ID"]) {
    fail(403, "Only the owner can change who this contact is shared with");
}

$stmt = $pdo->prepare("DELETE FROM ContactShares WHERE ContactID = ? AND SharedWithUserID = ?");
$stmt->execute([$contact["ID"], $userId]);
if ($stmt->rowCount() === 0) {
    fail(404, "This contact is not shared with that user");
}
// They can't see it any more, so it shouldn't stay in their favorites.
$pdo->prepare("DELETE FROM ContactFavorites WHERE ContactID = ? AND UserID = ?")->execute([$contact["ID"], $userId]);

if ($contact["Access"] === "owner") {
    logActivity("contact.unshared", "stopped sharing " . contactName($contact), "contact", (int)$contact["ID"], $userId);
} else {
    logActivity("contact.share_left", "removed shared contact " . contactName($contact) . " from their list", "contact", (int)$contact["ID"], (int)$contact["UserID"]);
}

http_response_code(200);
echo json_encode(["error" => ""]);
?>
