<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

// { "targetUserId": 5, "role": "Admin" | "User" }
$data = validateFields(getRequestData() ?? [], ["role" => ["required", "in:Admin,User"]]);
$target = findUserOr404(requireId("targetUserId"));

if ((int)$target["ID"] === (int)$admin["ID"]) {
    fail(409, "You can't change your own role. Ask another admin.");
}
if ($data["role"] === "User" && isLastActiveAdmin($target)) {
    fail(409, "This is the last active admin; promote someone else first.");
}

$pdo->prepare("UPDATE Users SET Role = ? WHERE ID = ?")->execute([$data["role"], $target["ID"]]);
logActivity("admin.role_changed", ($data["role"] === "Admin" ? "promoted " : "demoted ") . $target["Login"] . " to " . $data["role"],
    "user", (int)$target["ID"], (int)$target["ID"]);

http_response_code(200);
echo json_encode(["error" => ""]);
?>
