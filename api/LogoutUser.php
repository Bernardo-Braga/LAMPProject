<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

// { "targetUserId": 5 }  Signs the user out on every device (their sessions stop being valid).
$target = findUserOr404(requireId("targetUserId"));
if ((int)$target["ID"] === (int)$admin["ID"]) {
    fail(409, "Use Log Out to sign yourself out.");
}
$pdo->prepare("UPDATE Users SET SessionVersion = SessionVersion + 1 WHERE ID = ?")->execute([$target["ID"]]);
$pdo->prepare("DELETE FROM Sessions WHERE UserID = ?")->execute([$target["ID"]]);
logActivity("admin.user_logged_out", "signed " . $target["Login"] . " out of all devices", "user", (int)$target["ID"], (int)$target["ID"]);

http_response_code(200);
echo json_encode(["error" => ""]);
?>
