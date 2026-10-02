<?php
require_once "common.php";
requireMethod("POST");
requireAdmin();

// { "targetUserId": 5 }  Clears a lockout caused by too many wrong passwords.
$target = findUserOr404(requireId("targetUserId"));
$pdo->prepare("UPDATE Users SET FailedLoginCount = 0, LockedUntil = NULL WHERE ID = ?")->execute([$target["ID"]]);
logActivity("admin.user_unlocked", "unlocked " . $target["Login"], "user", (int)$target["ID"], (int)$target["ID"]);

http_response_code(200);
echo json_encode(["error" => ""]);
?>
