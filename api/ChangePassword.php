<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

$data = getRequestData();

// Admin sets a new password for another user. Leave out "newPassword" to generate a
// temporary one, which is returned once. By default the user must pick a new one at sign-in.
if (empty($data["targetUserId"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

$clean = validateFields($data, [
    "newPassword" => ["min:" . MIN_PASSWORD_LENGTH, "password"],
    "mustChangePassword" => ["bool"],
]);
$temporaryPassword = null;
if ($clean["newPassword"] === null) {
    // 12 characters without look-alikes (0/O, 1/l/I), easy to read out.
    $alphabet = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $temporaryPassword = "";
    for ($i = 0; $i < 12; $i++) {
        $temporaryPassword .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
$newPassword = $clean["newPassword"] ?? $temporaryPassword;
$targetUserId = requireId("targetUserId");
$isSelf = $targetUserId === (int)$admin["ID"];
$mustChange = !$isSelf && ($clean["mustChangePassword"] ?? true);

try {
    $target = findUserOr404($targetUserId);

    // Also clears any lockout and signs the user out of their other sessions.
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE Users SET Password = ?, MustChangePassword = ?, FailedLoginCount = 0, LockedUntil = NULL,
        SessionVersion = SessionVersion + 1 WHERE ID = ?");
    $stmt->execute([$hash, $mustChange ? 1 : 0, $targetUserId]);
    if ($isSelf) {
        $_SESSION["ver"] = (int)$target["SessionVersion"] + 1; // keep the admin's own session alive
    }
    logActivity("admin.password_reset", "reset the password for " . $target["Login"], "user", (int)$target["ID"], (int)$target["ID"]);

    http_response_code(200);
    echo json_encode(["temporaryPassword" => $temporaryPassword, "mustChangePassword" => $mustChange, "error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Password change failed"]);
}
?>
