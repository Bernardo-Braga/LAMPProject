<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

// { "id": 12, "login": "rosie", "permission": "view" | "edit" }  Adds or changes access. Owner only.
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "owner");
$data = validateFields(getRequestData() ?? [], [
    "login" => ["required", "max:50"],
    "permission" => ["required", "in:view,edit"],
]);

$find = $pdo->prepare("SELECT ID, FirstName, LastName FROM Users WHERE Login = ? AND IsDisabled = 0");
$find->execute([$data["login"]]);
$target = $find->fetch();
if (!$target) {
    fail(422, "Please fix the highlighted fields", ["login" => "No active user with that username"]);
}
if ((int)$target["ID"] === (int)$user["ID"]) {
    fail(422, "Please fix the highlighted fields", ["login" => "You can't share a contact with yourself"]);
}

$existing = $pdo->prepare("SELECT Permission FROM ContactShares WHERE ContactID = ? AND SharedWithUserID = ?");
$existing->execute([$contact["ID"], $target["ID"]]);
$wasShared = $existing->fetchColumn() !== false;

$pdo->prepare("INSERT INTO ContactShares (ContactID, SharedWithUserID, Permission, SharedByUserID) VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE Permission = VALUES(Permission)")
    ->execute([$contact["ID"], $target["ID"], $data["permission"], $user["ID"]]);

$label = $data["permission"] === "edit" ? "can edit" : "view only";
$who = $target["FirstName"] . " " . $target["LastName"];
logActivity($wasShared ? "contact.share_updated" : "contact.shared",
    $wasShared ? "changed sharing of " . contactName($contact) . " to \"$label\" for $who"
               : "shared contact " . contactName($contact) . " with $who ($label)",
    "contact", (int)$contact["ID"], (int)$target["ID"]);

http_response_code($wasShared ? 200 : 201);
echo json_encode(["error" => ""]);
?>
