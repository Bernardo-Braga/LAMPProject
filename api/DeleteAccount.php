<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin();

$data = getRequestData();

if (empty($data["password"]) || !password_verify($data["password"], $user["Password"])) {
    fail(422, "Please fix the highlighted fields", ["password" => "Password is incorrect"]);
}
if (isLastActiveAdmin($user)) {
    fail(409, "You are the only active admin. Promote someone else before deleting your account.");
}

try {
    logActivity("account.deleted", "deleted their own account (" . $user["Login"] . ")", "user", (int)$user["ID"]);
    // Contacts, photos, tags, shares, favorites and sessions go with it (ON DELETE CASCADE).
    $pdo->prepare("DELETE FROM Users WHERE ID = ?")->execute([$user["ID"]]);
    signOut();

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Account deletion failed"]);
}
?>
