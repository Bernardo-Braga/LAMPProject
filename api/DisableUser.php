<?php
require_once "common.php";
requireMethod("POST");
$admin = requireAdmin();

$data = getRequestData();

if (empty($data["targetUserId"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// "disabled": false re-enables the account; leaving it out disables, as before.
$disable = !array_key_exists("disabled", $data) || $data["disabled"] === true;

try {
    $target = findUserOr404(requireId("targetUserId"));

    if ((int)$target["ID"] === (int)$admin["ID"]) {
        http_response_code(409);
        echo json_encode(["error" => "You can't disable your own account. Ask another admin."]);
        exit();
    }
    if ($disable && isLastActiveAdmin($target)) {
        http_response_code(409);
        echo json_encode(["error" => "This is the last active admin; promote someone else first."]);
        exit();
    }

    if ($disable) {
        // Bumping SessionVersion signs them out everywhere immediately.
        $stmt = $pdo->prepare("UPDATE Users SET IsDisabled = 1, SessionVersion = SessionVersion + 1 WHERE ID = ?");
        $stmt->execute([$target["ID"]]);
        logActivity("admin.user_disabled", "disabled " . $target["Login"], "user", (int)$target["ID"], (int)$target["ID"]);
    } else {
        $stmt = $pdo->prepare("UPDATE Users SET IsDisabled = 0, FailedLoginCount = 0, LockedUntil = NULL WHERE ID = ?");
        $stmt->execute([$target["ID"]]);
        logActivity("admin.user_enabled", "re-enabled " . $target["Login"], "user", (int)$target["ID"], (int)$target["ID"]);
    }

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Disable failed"]);
}
?>
