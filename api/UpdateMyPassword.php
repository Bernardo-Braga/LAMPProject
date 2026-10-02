<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin(true); // also used when an admin has forced a password change

$data = validateFields(getRequestData() ?? [], [
    "currentPassword" => ["required", "password"],
    "newPassword" => passwordRules(),
]);

if (!password_verify($data["currentPassword"], $user["Password"])) {
    fail(422, "Please fix the highlighted fields", ["currentPassword" => "Current password is incorrect"]);
}
if (password_verify($data["newPassword"], $user["Password"])) {
    fail(422, "Please fix the highlighted fields", ["newPassword" => "Choose a password different from your current one"]);
}

try {
    // Bumping SessionVersion signs out every other device; this session stays signed in.
    $stmt = $pdo->prepare("UPDATE Users SET Password = ?, MustChangePassword = 0, SessionVersion = SessionVersion + 1 WHERE ID = ?");
    $stmt->execute([password_hash($data["newPassword"], PASSWORD_BCRYPT), $user["ID"]]);
    $_SESSION["ver"] = (int)$user["SessionVersion"] + 1;
    logActivity("account.password_changed", "changed their password", "user", (int)$user["ID"]);

    http_response_code(200);
    echo json_encode(userJson(findUserOr404((int)$user["ID"])));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Password change failed"]);
}
?>
