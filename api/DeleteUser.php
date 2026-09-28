<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

// { "targetUserId": 5 }  Deletes the account and everything in it.
$target = findUserOr404(requireId("targetUserId"));
if ((int)$target["ID"] === (int)$admin["ID"]) {
    fail(409, "You can't delete your own account from here. Ask another admin.");
}
if (isLastActiveAdmin($target)) {
    fail(409, "This is the last active admin; promote someone else first.");
}

logActivity("admin.user_deleted", "deleted account " . $target["Login"] . " (" . $target["FirstName"] . " " . $target["LastName"] . ")",
    "user", (int)$target["ID"]);
// Contacts, photos, tags, shares, favorites and sessions go with it (ON DELETE CASCADE).
$pdo->prepare("DELETE FROM Users WHERE ID = ?")->execute([$target["ID"]]);

http_response_code(200);
echo json_encode(["error" => ""]);
?>
